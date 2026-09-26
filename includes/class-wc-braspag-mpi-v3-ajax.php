<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Braspag_Mpi_V3_Ajax
 *
 * Endpoints AJAX (autenticados por nonce do WordPress) consumidos pelo driver
 * client-side do MPI v3 (`assets/js/braspag-auth3ds-v3.js`) no checkout
 * clássico. Cada endpoint delega o trabalho de fato para
 * `WC_Braspag_Mpi_V3_Client` (Épico 1) — esta classe só faz a ponte
 * AJAX <-> cliente HTTP, montando os payloads a partir do carrinho/sessão do
 * WooCommerce. O `handle_enroll()` inclui o número do cartão (lido do
 * mesmo formulário clássico já usado para montar o payload do Pagador),
 * pois a Cielo rejeita 3ds/enroll com o objeto `card` vazio — o PAN já
 * trafega por este backend na submissão normal do pedido, então isso não
 * amplia o escopo PCI já existente do plugin.
 *
 * Segue o mesmo padrão de arquivo/nomenclatura de WC_Braspag_Client_Logger
 * (includes/class-wc-braspag-client-logger.php): `wp_ajax_*`/`wp_ajax_nopriv_*`
 * + `check_ajax_referer()`.
 *
 * @since 2.4.0
 */
class WC_Braspag_Mpi_V3_Ajax
{
    const ACTION_INIT = 'braspag_mpi_v3_init';
    const ACTION_ENROLL = 'braspag_mpi_v3_enroll';
    const ACTION_VALIDATE = 'braspag_mpi_v3_validate';
    const NONCE_ACTION = 'braspag_mpi_v3_nonce';

    /**
     * Prefixo do transient que guarda o estado server-side de uma sessão
     * 3DS (orderNumber + access_token), indexado pelo `ReferenceId` que a
     * Cielo devolve no `3ds/init`.
     *
     * Antes isso ficava em `WC()->session`, e o estado era perdido de forma
     * intermitente: o WooCommerce carrega o array inteiro da sessão no
     * início de cada request e o reescreve por completo no shutdown, então
     * um `?wc-ajax=update_order_review` disparado em paralelo ao nosso
     * `init` (ambos acontecem no carregamento do checkout) sobrescrevia o
     * orderNumber/token novos pelo snapshot antigo que aquele request havia
     * lido. O `enroll` seguinte então ia com o token/orderNumber da
     * tentativa ANTERIOR e a Cielo respondia HTTP 409 — foi exatamente o
     * que o log de 26/09 mostrou no débito (init gerou o orderNumber
     * `944e1b79…`, mas o enroll foi com `5fee8426…`, da tentativa de
     * crédito 3 minutos antes).
     *
     * Transient é escrito por chave (não é um array compartilhado), então
     * não há essa corrida. O `access_token` continua exclusivamente no
     * servidor (RN-3DS-013 / INV-004): o browser só conhece o
     * `ReferenceId`, que ele já recebia para o `MPI.init()`.
     */
    const SESSION_TRANSIENT_PREFIX = 'braspag_mpi_v3_session_';

    /**
     * Validade do estado da sessão 3DS. Cobre com folga o tempo de
     * challenge (5 min de timeout no driver JS) sem manter o token vivo
     * mais do que o necessário.
     */
    const SESSION_TTL = 1800;

    public static function init()
    {
        add_action('wp_ajax_' . self::ACTION_INIT, array(__CLASS__, 'handle_init'));
        add_action('wp_ajax_nopriv_' . self::ACTION_INIT, array(__CLASS__, 'handle_init'));

        add_action('wp_ajax_' . self::ACTION_ENROLL, array(__CLASS__, 'handle_enroll'));
        add_action('wp_ajax_nopriv_' . self::ACTION_ENROLL, array(__CLASS__, 'handle_enroll'));

        add_action('wp_ajax_' . self::ACTION_VALIDATE, array(__CLASS__, 'handle_validate'));
        add_action('wp_ajax_nopriv_' . self::ACTION_VALIDATE, array(__CLASS__, 'handle_validate'));
    }

    /**
     * @return array Settings do MPI v3 (test_mode + credenciais OAuth +
     *               dados do estabelecimento exigidos pelo AUTH da v3), a
     *               partir das configurações gerais já cadastradas.
     */
    protected static function get_mpi_settings()
    {
        $general_settings = get_option('woocommerce_braspag_settings', array());

        return array(
            'test_mode' => isset($general_settings['test_mode']) ? $general_settings['test_mode'] : 'no',
            'auth3ds20_oauth_authentication_client_id' => isset($general_settings['auth3ds20_oauth_authentication_client_id']) ? $general_settings['auth3ds20_oauth_authentication_client_id'] : '',
            'auth3ds20_oauth_authentication_client_secret' => isset($general_settings['auth3ds20_oauth_authentication_client_secret']) ? $general_settings['auth3ds20_oauth_authentication_client_secret'] : '',
            'establishment_code' => isset($general_settings['establishment_code']) ? $general_settings['establishment_code'] : '',
            'merchant_name' => isset($general_settings['merchant_name']) ? $general_settings['merchant_name'] : '',
            'mcc' => isset($general_settings['mcc']) ? $general_settings['mcc'] : '',
        );
    }

    /**
     * A Cielo bloqueia chamadas repetidas de `3ds/init` com o mesmo
     * `orderNumber` (HTTP 409), então cada tentativa de checkout usa um
     * `orderNumber` novo.
     *
     * @return string
     */
    protected static function generate_order_number()
    {
        return wp_generate_uuid4();
    }

    /**
     * Persiste o estado server-side da sessão 3DS criada pelo `3ds/init`,
     * indexado pelo `ReferenceId`. Ver SESSION_TRANSIENT_PREFIX para o
     * motivo de não usar `WC()->session`.
     *
     * @param string $reference_id
     * @param string $order_number
     * @param string $access_token
     * @return void
     */
    protected static function store_3ds_session($reference_id, $order_number, $access_token)
    {
        if ('' === (string) $reference_id) {
            return;
        }

        set_transient(
            self::SESSION_TRANSIENT_PREFIX . $reference_id,
            array(
                'order_number' => (string) $order_number,
                'access_token' => (string) $access_token,
            ),
            self::SESSION_TTL
        );
    }

    /**
     * Lê o `orderNumber` e o `access_token` da sessão 3DS que o browser
     * está usando (identificada pelo `referenceId` que ele devolve). A
     * Cielo correlaciona init/enroll/validate por `orderNumber` e vincula o
     * token à sessão, então os três precisam bater — usar os de outra
     * tentativa resulta em HTTP 409.
     *
     * @param string $reference_id
     * @return array{order_number: string, access_token: string}
     */
    protected static function get_3ds_session($reference_id)
    {
        $empty = array('order_number' => '', 'access_token' => '');

        if ('' === (string) $reference_id) {
            return $empty;
        }

        $data = get_transient(self::SESSION_TRANSIENT_PREFIX . $reference_id);

        if (!is_array($data)) {
            return $empty;
        }

        return array_merge($empty, $data);
    }

    /**
     * POST /wp-admin/admin-ajax.php?action=braspag_mpi_v3_init
     *
     * Cria uma sessão 3DS nova para esta tentativa de checkout: gera um
     * access_token dedicado (guardado na sessão do WC para o enroll/validate
     * reusarem) e devolve `ReferenceId`+`Token` para o JS inicializar
     * `MPI.init()`.
     */
    public static function handle_init()
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (function_exists('WC') === false || WC()->cart === null) {
            wp_send_json_error(array('message' => __('Cart unavailable.', 'woocommerce-braspag')), 400);
        }

        try {
            $order_number = self::generate_order_number();
            $settings = self::get_mpi_settings();

            // Token novo por sessão 3DS: reaproveitar um token já usado em
            // outro init faz a Cielo responder 409.
            $access_token = WC_Braspag_Mpi_V3_Client::create_access_token($settings);

            $response = WC_Braspag_Mpi_V3_Client::init($order_number, $settings, $access_token, array(
                'currency' => WC_Braspag_Mpi_V3_Client::CURRENCY_BRL_ISO,
                'amount' => (int) round(WC()->cart->get_total('edit') * 100),
            ));

            // A resposta real do init usa PascalCase (ReferenceId/Token) —
            // ler em camelCase devolvia valores vazios para o MPI.init().
            // O orderNumber vai junto porque o JS precisa dele para montar o
            // objeto `order` de MPI.challenge().
            $reference_id = isset($response->ReferenceId) ? $response->ReferenceId : '';

            // orderNumber e access_token ficam no servidor, indexados pelo
            // ReferenceId que o browser passa a conhecer (o token nunca vai
            // para o browser — RN-3DS-013 / INV-004).
            self::store_3ds_session($reference_id, $order_number, $access_token);

            wp_send_json_success(array(
                'referenceId' => $reference_id,
                'token' => isset($response->Token) ? $response->Token : '',
                'orderNumber' => $order_number,
            ));
        } catch (WC_Braspag_Exception $e) {
            WC_Braspag_Logger::log('MPI v3 init (ajax): ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getLocalizedMessage()), 400);
        }
    }

    /**
     * POST /wp-admin/admin-ajax.php?action=braspag_mpi_v3_enroll
     *
     * Monta o payload de enroll (orderNumber, currency, billTo, browserInfo)
     * a partir do carrinho/cliente do WooCommerce + `browserInfo` vindo do
     * JS (via `MPIHelpers.getBrowserInfo()`), e chama
     * `WC_Braspag_Mpi_V3_Client::enroll()`. Retorna status 0/1/2.
     *
     * A Cielo exige o objeto 'card' (com cardNumber) não-vazio no payload
     * de enroll — o número do cartão é lido do mesmo formulário clássico
     * já usado para montar o payload do Pagador (o PAN já trafega por
     * este backend na submissão do pedido, então isso não amplia o
     * escopo PCI já existente do plugin).
     */
    public static function handle_enroll()
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (function_exists('WC') === false || WC()->cart === null) {
            wp_send_json_error(array('message' => __('Cart unavailable.', 'woocommerce-braspag')), 400);
        }

        try {
            $reference_id = isset($_POST['referenceId']) ? sanitize_text_field(wp_unslash($_POST['referenceId'])) : '';
            $raw_browser_info = isset($_POST['browserInfo']) ? wp_unslash($_POST['browserInfo']) : '';
            $browser_info = self::decode_browser_info($raw_browser_info);

            // A Cielo exige o objeto 'card' (com cardNumber) não-vazio em
            // 3ds/enroll ({"Code":"Card","Message":"'Card' must not be
            // empty."}) — o PAN já trafega por este mesmo backend na
            // submissão clássica do checkout (ver
            // class-wc-gateway-braspag-creditcard.php, builder do Pagador),
            // então este payload não amplia o escopo PCI já existente do
            // plugin. Nunca logar o valor cru (WC_Braspag_Mpi_V3_Client já
            // redige 'cardNumber' antes de qualquer log).
            $card_number = isset($_POST['cardNumber']) ? preg_replace('/\D+/', '', wp_unslash($_POST['cardNumber'])) : '';
            $card_expiration_month = isset($_POST['cardExpirationMonth']) ? preg_replace('/\D+/', '', wp_unslash($_POST['cardExpirationMonth'])) : '';
            $card_expiration_year = isset($_POST['cardExpirationYear']) ? preg_replace('/\D+/', '', wp_unslash($_POST['cardExpirationYear'])) : '';
            $card_payment_method = isset($_POST['cardPaymentMethod']) ? sanitize_text_field(wp_unslash($_POST['cardPaymentMethod'])) : '';
            $card_brand = isset($_POST['cardBrand']) ? sanitize_text_field(wp_unslash($_POST['cardBrand'])) : '';

            if ('' === $card_number) {
                wp_send_json_error(array('message' => __('Missing card data.', 'woocommerce-braspag')), 400);
            }

            $session = self::get_3ds_session($reference_id);
            $order_number = $session['order_number'];
            $access_token = $session['access_token'];

            if ('' === $order_number || '' === $access_token) {
                wp_send_json_error(array('message' => __('3DS session expired, please try again.', 'woocommerce-braspag')), 400);
            }

            $cart = WC()->cart;
            $customer = WC()->customer;
            $settings = self::get_mpi_settings();

            $payload = array(
                'referenceId' => $reference_id,
                'orderNumber' => $order_number,
                'currency' => WC_Braspag_Mpi_V3_Client::CURRENCY_BRL_ALPHA,
                'totalAmount' => (int) round($cart->get_total('edit') * 100),
                'billTo' => self::build_bill_to($customer),
                'browserInfo' => $browser_info,
                'card' => array(
                    'cardNumber' => $card_number,
                    'expirationMonth' => $card_expiration_month,
                    'expirationYear' => $card_expiration_year,
                ),
            );

            if ('' !== $card_payment_method) {
                $payload['card']['paymentMethod'] = $card_payment_method;
            }

            // Data Only: modo "somente notificação" — frictionless, sem
            // challenge e sem liability shift. Só se aplica a Mastercard e
            // Visa; nas demais bandeiras faz fallback silencioso para o 3DS
            // normal, sem bloquear a venda.
            $data_only = self::should_use_data_only($card_brand, $card_payment_method);

            if ($data_only) {
                $payload['authNotifyOnly'] = true;
            }

            $payload = apply_filters('wc_gateway_braspag_mpi_v3_enroll_payload', $payload);

            $response = WC_Braspag_Mpi_V3_Client::enroll($payload, $settings, $access_token);

            $data = self::extract_authentication_data($response);
            $data['dataOnly'] = $data_only;

            if ($data_only && '2' === $data['status']) {
                // INV-003/BDD-3DS-018: Data Only nunca deveria abrir
                // challenge. O driver JS também não exibe o desafio nesse
                // caso; registramos para diagnóstico.
                WC_Braspag_Logger::log('MPI v3 enroll (ajax): challenge retornado em transação Data Only (anomalia).');
            }

            wp_send_json_success($data);
        } catch (WC_Braspag_Exception $e) {
            WC_Braspag_Logger::log('MPI v3 enroll (ajax): ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getLocalizedMessage()), 400);
        }
    }

    /**
     * POST /wp-admin/admin-ajax.php?action=braspag_mpi_v3_validate
     *
     * Chamado só após o challenge (status=2 do enroll) ser resolvido pelo
     * cardholder — quando o enroll já retorna status=1, o resultado final
     * (Cavv/Xid/Eci/Version) já vem no próprio enroll, sem precisar deste
     * endpoint (ver `handle_enroll()`/`extract_authentication_data()`).
     *
     * O VALIDATE não aceita só um `referenceId`: exige o `transactionId`
     * devolvido pelo `Challenge` do enroll, mais orderNumber/currency/
     * totalAmount/card repetidos — ver docs.cielo.com.br/gateway/docs/mpi-v3.
     */
    public static function handle_validate()
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (function_exists('WC') === false || WC()->cart === null) {
            wp_send_json_error(array('message' => __('Cart unavailable.', 'woocommerce-braspag')), 400);
        }

        $reference_id = isset($_POST['referenceId']) ? sanitize_text_field(wp_unslash($_POST['referenceId'])) : '';
        $transaction_id = isset($_POST['transactionId']) ? sanitize_text_field(wp_unslash($_POST['transactionId'])) : '';
        $card_number = isset($_POST['cardNumber']) ? preg_replace('/\D+/', '', wp_unslash($_POST['cardNumber'])) : '';
        $card_expiration_month = isset($_POST['cardExpirationMonth']) ? preg_replace('/\D+/', '', wp_unslash($_POST['cardExpirationMonth'])) : '';
        $card_expiration_year = isset($_POST['cardExpirationYear']) ? preg_replace('/\D+/', '', wp_unslash($_POST['cardExpirationYear'])) : '';

        if ('' === $transaction_id || '' === $card_number) {
            wp_send_json_error(array('message' => __('Missing validation data.', 'woocommerce-braspag')), 400);
        }

        $session = self::get_3ds_session($reference_id);
        $order_number = $session['order_number'];
        $access_token = $session['access_token'];

        if ('' === $order_number || '' === $access_token) {
            wp_send_json_error(array('message' => __('3DS session expired, please try again.', 'woocommerce-braspag')), 400);
        }

        try {
            $cart = WC()->cart;
            $settings = self::get_mpi_settings();

            $payload = array(
                'orderNumber' => $order_number,
                'currency' => WC_Braspag_Mpi_V3_Client::CURRENCY_BRL_ALPHA,
                'totalAmount' => (int) round($cart->get_total('edit') * 100),
                'transactionId' => $transaction_id,
                'card' => array(
                    'cardNumber' => $card_number,
                    'expirationMonth' => $card_expiration_month,
                    'expirationYear' => $card_expiration_year,
                ),
            );

            $payload = apply_filters('wc_gateway_braspag_mpi_v3_validate_payload', $payload);

            $response = WC_Braspag_Mpi_V3_Client::validate($payload, $settings, $access_token);

            wp_send_json_success(self::extract_authentication_data($response));
        } catch (WC_Braspag_Exception $e) {
            WC_Braspag_Logger::log('MPI v3 validate (ajax): ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getLocalizedMessage()), 400);
        }
    }

    /**
     * Bandeiras que suportam Data Only, conforme a documentação da Cielo
     * (docs.cielo.com.br/ecommerce-cielo/docs/data-only): apenas Mastercard
     * e Visa. Os valores batem com o que `assets/js/braspag.js` grava no
     * campo de card-type.
     *
     * @var string[]
     */
    protected static $data_only_brands = array('master', 'visa');

    /**
     * Data Only só é aplicado quando a configuração está ativa E a bandeira
     * suporta o modo. Em bandeira não suportada (Elo, Amex, etc.) faz
     * fallback para o 3DS normal em vez de bloquear a venda.
     *
     * @param string $card_brand Bandeira detectada no checkout ('Visa', 'Master', 'Elo'...).
     * @param string $payment_method 'debit' para débito; qualquer outro valor usa crédito.
     * @return bool
     */
    protected static function should_use_data_only($card_brand, $payment_method = 'credit')
    {
        $option = 'debit' === $payment_method
            ? 'woocommerce_braspag_debitcard_settings'
            : 'woocommerce_braspag_creditcard_settings';

        $gateway_settings = get_option($option, array());
        $enabled = isset($gateway_settings['auth3ds20_mpi_mastercard_notify_only'])
            ? $gateway_settings['auth3ds20_mpi_mastercard_notify_only']
            : 'no';

        if ('yes' !== $enabled) {
            return false;
        }

        return in_array(strtolower(trim((string) $card_brand)), self::$data_only_brands, true);
    }

    /**
     * Normaliza a resposta de `3ds/enroll` ou `3ds/validate` para o formato
     * que o JS consome. O corpo real da Cielo usa chaves PascalCase e
     * objetos aninhados (`Status`, `Authentication.{Cavv,Xid,Eci,Version}`,
     * `Challenge.{AcsUrl,Pareq,TransactionId}`, `Reason.{Code,Message}`) —
     * ver exemplos de resposta em docs.cielo.com.br/gateway/docs/mpi-v3.
     * As versões anteriores deste método liam campos que não existem no
     * schema real (`response->status`/`response->cavv` em minúsculo,
     * `response->challengeData`/`response->acsUrl` soltos).
     *
     * @param object $response
     * @return array
     */
    protected static function extract_authentication_data($response)
    {
        $status = isset($response->Status) ? (string) $response->Status : '';
        $auth = isset($response->Authentication) ? $response->Authentication : null;

        $reason = isset($response->Reason) ? $response->Reason : null;

        $data = array(
            'status' => $status,
            'cavv' => $auth && isset($auth->Cavv) ? $auth->Cavv : '',
            'xid' => $auth && isset($auth->Xid) ? $auth->Xid : '',
            'eci' => $auth && isset($auth->Eci) ? $auth->Eci : '',
            'version' => $auth && isset($auth->Version) ? $auth->Version : '',
            // Código de retorno ('100' = Success) — usado pelo JS para
            // distinguir um Data Only concluído com sucesso (que também vem
            // com Status=0) de um Status=0 de fato não autenticado.
            'reasonCode' => $reason && isset($reason->Code) ? (string) $reason->Code : '',
        );

        if ('2' === $status && isset($response->Challenge)) {
            $challenge = $response->Challenge;
            $data['challengeData'] = array(
                'acsUrl' => isset($challenge->AcsUrl) ? $challenge->AcsUrl : '',
                'payload' => isset($challenge->Pareq) ? $challenge->Pareq : '',
                'transactionId' => isset($challenge->TransactionId) ? $challenge->TransactionId : '',
            );
        }

        return $data;
    }

    /**
     * @param string $raw JSON com o retorno de `MPIHelpers.getBrowserInfo()`.
     * @return array
     */
    protected static function decode_browser_info($raw)
    {
        $decoded = !empty($raw) ? json_decode($raw, true) : array();

        if (!is_array($decoded)) {
            $decoded = array();
        }

        $allowed_keys = array('userAgent', 'screenWidth', 'screenHeight', 'colorDepth', 'timeZoneOffset', 'language', 'javaEnabled', 'javascriptEnabled');
        $safe = array();

        foreach ($allowed_keys as $key) {
            if (isset($decoded[$key])) {
                $safe[$key] = is_string($decoded[$key]) ? sanitize_text_field($decoded[$key]) : $decoded[$key];
            }
        }

        return $safe;
    }

    /**
     * Monta o objeto `billTo` exigido pelo 3ds/enroll. Os nomes de campo
     * seguem o schema documentado pela Cielo (Name combinado — não
     * firstName/lastName separados —, PhoneNumber, Street1/Street2,
     * ZipCode), não os nomes usados no builder do Pagador; ver
     * docs.cielo.com.br/gateway/docs/mpi-v3.
     *
     * @param WC_Customer|null $customer
     * @return array
     */
    protected static function build_bill_to($customer)
    {
        if (!$customer) {
            return array();
        }

        $name = trim($customer->get_billing_first_name() . ' ' . $customer->get_billing_last_name());

        return array(
            'name' => $name,
            'phoneNumber' => preg_replace('/\D+/', '', (string) $customer->get_billing_phone()),
            'email' => $customer->get_billing_email(),
            'street1' => $customer->get_billing_address_1(),
            'street2' => $customer->get_billing_address_2(),
            'city' => $customer->get_billing_city(),
            'state' => $customer->get_billing_state(),
            'zipCode' => preg_replace('/\D+/', '', (string) $customer->get_billing_postcode()),
            'country' => $customer->get_billing_country(),
        );
    }
}

WC_Braspag_Mpi_V3_Ajax::init();
