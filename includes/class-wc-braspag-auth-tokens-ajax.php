<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Endpoint AJAX que fornece sob demanda os tokens de autenticação MPI/3DS e
 * SOP para o checkout, em vez de embuti-los em texto puro no HTML da página
 * (wp_localize_script).
 *
 * Registrado no bootstrap do plugin (não dentro do construtor de uma classe
 * de gateway): WooCommerce só instancia os objetos de payment gateway sob
 * demanda (tipicamente ao renderizar o checkout), então uma requisição "crua"
 * a admin-ajax.php pode chegar sem nenhum WC_Gateway_Braspag* jamais ter sido
 * construído — nesse caso a action `wp_ajax_braspag_get_auth_tokens` nunca
 * seria registrada e admin-ajax.php responderia "0". Registrar aqui, sempre,
 * evita essa corrida; os gateways são instanciados dentro do handler, só
 * quando a requisição efetivamente chega.
 */
class WC_Braspag_Auth_Tokens_Ajax
{
    const ACTION = 'braspag_get_auth_tokens';
    const NONCE_ACTION = 'braspag_get_auth_tokens_nonce';

    public static function init()
    {
        add_action('wp_ajax_' . self::ACTION, array(__CLASS__, 'handle_request'));
        add_action('wp_ajax_nopriv_' . self::ACTION, array(__CLASS__, 'handle_request'));
    }

    public static function handle_request()
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $tokens = array();

        // Este é um endpoint JSON: qualquer notice/warning impresso durante a
        // construção dos gateways ou as chamadas de API corromperia a
        // resposta no cliente ("Unexpected token '<'"). Descartar saída
        // acidental é uma proteção barata independente da causa.
        ob_start();

        try {
            // O toggle 'auth3ds20_mpi_is_active' vive nas configurações de
            // CreditCard/DebitCard (não nas configurações gerais 'braspag'),
            // então é preciso consultar os dois métodos para saber se o 3DS
            // está ativo em algum deles.
            $creditcard = new WC_Gateway_Braspag_CreditCard();
            $debitcard = new WC_Gateway_Braspag_DebitCard();
            $base = new WC_Gateway_Braspag();

            $auth3ds20_enabled = 'yes' === $creditcard->get_option('auth3ds20_mpi_is_active', 'no')
                || 'yes' === $debitcard->get_option('auth3ds20_mpi_is_active', 'no');

            if ($auth3ds20_enabled) {
                $tokens['bpmpiToken'] = $base->get_mpi_auth_token();
            }

            if ('yes' === $base->get_option('silentpost_enabled', 'no')) {
                $sop_merchant_id = $base->get_option('silentpost_merchant_id');
                $sop_url = 'yes' === $base->get_option('test_mode', 'no')
                    ? 'https://transactionsandbox.pagador.com.br/post/api/public/v2'
                    : 'https://transaction.pagador.com.br/post/api/public/v2';

                $auth_sop_token = $base->get_oauth_token_sop();

                $tokens['bpOauthToken'] = $auth_sop_token;
                $tokens['bpAccessToken'] = $base->get_access_token_sop(
                    $sop_url,
                    'accesstoken',
                    'POST',
                    $auth_sop_token,
                    $sop_merchant_id
                );
            }
        } catch (WC_Braspag_Exception $e) {
            self::discard_stray_output();

            WC_Braspag_Logger::log('Erro ao obter tokens de autenticação via AJAX: ' . $e->getMessage());
            wp_send_json_error('token_fetch_failed');

            return;
        }

        self::discard_stray_output();

        wp_send_json_success($tokens);
    }

    protected static function discard_stray_output()
    {
        $stray = ob_get_clean();

        if ('' !== trim((string) $stray)) {
            WC_Braspag_Logger::log('Saída inesperada descartada do endpoint de tokens: ' . $stray);
        }
    }
}

WC_Braspag_Auth_Tokens_Ajax::init();
