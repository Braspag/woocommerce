<?php

use PHPUnit\Framework\TestCase;

/**
 * Testa os métodos de construção de payload do WC_Gateway_Braspag_CreditCard
 * sem instanciar o construtor completo (usa disableOriginalConstructor).
 *
 * Cobre:
 *  - braspag_pagador_creditcard_payment_request_builder: SOP vs CardNumber
 *  - braspag_pagador_creditcard_payment_request_auth3ds20_builder: ExternalAuthentication
 *  - braspag_pagador_creditcard_payment_request_antifraud_builder: FraudAnalysis
 *  - process_payment_validation: bloqueio por failureType
 */
class CreditCardPayloadBuilderTest extends TestCase
{
    /** @var WC_Gateway_Braspag_CreditCard&\PHPUnit\Framework\MockObject\MockObject */
    private $gateway;

    protected function setUp(): void
    {
        $GLOBALS['_braspag_test_options'] = [];

        $this->gateway = $this->getMockBuilder(WC_Gateway_Braspag_CreditCard::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_braspag_payment_provider', 'get_antifraud_browser_fingerprint', 'get_antifraud_provider_name', 'get_customer_identity_data', 'get_logged_in_customer_id'])
            ->getMock();

        $this->gateway->method('get_braspag_payment_provider')->willReturn('Cielo30');
        $this->gateway->method('get_antifraud_provider_name')->willReturn('CyberSource');
        $this->gateway->method('get_antifraud_browser_fingerprint')->willReturn('fp-abc123');
        $this->gateway->method('get_customer_identity_data')->willReturn(['value' => '123.456.789-00']);
        $this->gateway->method('get_logged_in_customer_id')->willReturn(0);

        $this->setProperty('soft_descriptor', '');
        $this->setProperty('capture', true);
        $this->setProperty('save_card', 'no');
        $this->setProperty('test_mode', false);
        $this->setProperty('merchant_category', '');
        $this->setProperty('extra_data_collection', []);
    }

    protected function tearDown(): void
    {
        $GLOBALS['_braspag_test_options'] = [];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function setProperty(string $name, mixed $value): void
    {
        $ref  = new ReflectionClass($this->gateway);
        $prop = $ref->getProperty($name);
        $prop->setAccessible(true);
        $prop->setValue($this->gateway, $value);
    }

    private function makeCheckout(array $values): object
    {
        return new class($values) {
            private array $v;
            public function __construct(array $v) { $this->v = $v; }
            public function get_value(string $key, mixed $default = '') { return $this->v[$key] ?? $default; }
        };
    }

    private function makeOrder(float $total = 100.00): object
    {
        return new class($total) {
            private float $total;
            public function __construct(float $t) { $this->total = $t; }
            public function get_total(): float { return $this->total; }
            public function get_billing_address_1(): string { return 'Rua A'; }
            public function get_billing_email(): string { return 'test@test.com'; }
            public function get_billing_phone(): string { return '11999999999'; }
            public function get_billing_first_name(): string { return 'João'; }
            public function get_billing_last_name(): string { return 'Silva'; }
            public function get_shipping_address_1(): string { return 'Rua A'; }
            public function get_shipping_first_name(): string { return 'João'; }
            public function get_shipping_last_name(): string { return 'Silva'; }
            public function get_address(string $type): array { return ['number' => '1', 'neighborhood' => 'Centro', 'city' => 'SP', 'state' => 'SP', 'country' => 'BR', 'postcode' => '01310-100', 'address_2' => '']; }
            public function get_payment_method(): string { return 'braspag_creditcard'; }
            public function get_customer_ip_address(): string { return '127.0.0.1'; }
            public function get_formatted_billing_full_name(): string { return 'João Silva'; }
            public function get_id(): int { return 999; }
            public function get_meta(string $key) { return ''; }
        };
    }

    private function makeCart(array $items = []): object
    {
        return new class($items) {
            private array $items;
            public function __construct(array $i) { $this->items = $i; }
            public function get_cart_contents(): array { return $this->items; }
            public function get_total(string $context = ''): float { return 100.00; }
        };
    }

    // ── SOP: usa PaymentToken quando SOP ativo ────────────────────────────────

    public function test_sop_ativo_usa_payment_token_no_payload(): void
    {
        $this->setProperty('sop_enabled', 'yes');
        $this->setProperty('sop_tokenize', 'no');

        $checkout = $this->makeCheckout([
            'braspag_creditcard-card-expiry'        => '12/28',
            'braspag_creditcard-card-type'          => 'Visa',
            'braspag_creditcard-card-holder'        => 'JOAO SILVA',
            'braspag_creditcard-card-cvc'           => '123',
            'braspag_creditcard-card-paymenttoken'  => 'tok-sop-xyz',
            'braspag_creditcard-card-installments'  => '1',
            'wc-braspag_creditcard-new-payment-method' => 'false',
        ]);

        $result = $this->gateway->braspag_pagador_creditcard_payment_request_builder(
            [],
            $this->makeOrder(),
            $checkout,
            $this->makeCart()
        );

        $this->assertArrayHasKey('PaymentToken', $result['CreditCard']);
        $this->assertSame('tok-sop-xyz', $result['CreditCard']['PaymentToken']);
        $this->assertArrayNotHasKey('CardNumber', $result['CreditCard']);
    }

    public function test_sop_com_tokenize_usa_card_token(): void
    {
        $this->setProperty('sop_enabled', 'yes');
        $this->setProperty('sop_tokenize', 'yes');
        $this->setProperty('save_card', 'yes');

        $checkout = $this->makeCheckout([
            'braspag_creditcard-card-expiry'           => '12/28',
            'braspag_creditcard-card-type'             => 'Visa',
            'braspag_creditcard-card-holder'           => 'JOAO SILVA',
            'braspag_creditcard-card-cvc'              => '123',
            'braspag_creditcard-card-cardtoken'        => 'card-tok-abc',
            'braspag_creditcard-card-installments'     => '1',
            'wc-braspag_creditcard-new-payment-method' => 'true',
        ]);

        $result = $this->gateway->braspag_pagador_creditcard_payment_request_builder(
            [],
            $this->makeOrder(),
            $checkout,
            $this->makeCart()
        );

        $this->assertArrayHasKey('CardToken', $result['CreditCard']);
        $this->assertSame('card-tok-abc', $result['CreditCard']['CardToken']);
    }

    public function test_sem_sop_usa_card_number(): void
    {
        $this->setProperty('sop_enabled', 'no');

        $checkout = $this->makeCheckout([
            'braspag_creditcard-card-expiry'       => '12/28',
            'braspag_creditcard-card-type'         => 'Visa',
            'braspag_creditcard-card-holder'       => 'JOAO SILVA',
            'braspag_creditcard-card-cvc'          => '123',
            'braspag_creditcard-card-number'       => '4111 1111 1111 1111',
            'braspag_creditcard-card-installments' => '1',
            'wc-braspag_creditcard-new-payment-method' => 'false',
        ]);

        $result = $this->gateway->braspag_pagador_creditcard_payment_request_builder(
            [],
            $this->makeOrder(),
            $checkout,
            $this->makeCart()
        );

        $this->assertArrayHasKey('CardNumber', $result['CreditCard']);
        $this->assertSame('4111111111111111', $result['CreditCard']['CardNumber']);
        $this->assertArrayNotHasKey('PaymentToken', $result['CreditCard']);
    }

    // ── ZeroAuth (VerifyCard): só roda sem SOP ────────────────────────────────

    public function test_verifycard_ativo_sem_sop_chama_zero_auth_e_mantem_card_number(): void
    {
        $this->setProperty('sop_enabled', 'no');
        $this->setProperty('verifycard_enabled', 'yes');
        $this->setProperty('merchant_id', 'merchant-id');
        $this->setProperty('merchant_key', 'merchant-key');

        $captured = [];
        $GLOBALS['_braspag_test_http_handler'] = function ($url, $args) use (&$captured) {
            $captured = ['url' => $url, 'body' => json_decode($args['body'], true)];
            return ['response' => ['code' => 200], 'body' => json_encode(['Valid' => true, 'ReturnCode' => '00'])];
        };

        $checkout = $this->makeCheckout([
            'braspag_creditcard-card-expiry'       => '12/28',
            'braspag_creditcard-card-type'         => 'Visa',
            'braspag_creditcard-card-holder'       => 'JOAO SILVA',
            'braspag_creditcard-card-cvc'          => '123',
            'braspag_creditcard-card-number'       => '4111 1111 1111 1111',
            'braspag_creditcard-card-installments' => '1',
            'wc-braspag_creditcard-new-payment-method' => 'false',
        ]);

        $result = $this->gateway->braspag_pagador_creditcard_payment_request_builder(
            [],
            $this->makeOrder(),
            $checkout,
            $this->makeCart()
        );

        unset($GLOBALS['_braspag_test_http_handler']);

        $this->assertStringContainsString('zeroauth', $captured['url']);
        $this->assertSame('4111111111111111', $captured['body']['CardNumber']);
        $this->assertSame('4111111111111111', $result['CreditCard']['CardNumber']);
    }

    public function test_verifycard_ativo_com_sop_nao_chama_zero_auth(): void
    {
        $this->setProperty('sop_enabled', 'yes');
        $this->setProperty('sop_tokenize', 'no');
        $this->setProperty('verifycard_enabled', 'yes');
        $this->setProperty('merchant_id', 'merchant-id');
        $this->setProperty('merchant_key', 'merchant-key');

        $called = false;
        $GLOBALS['_braspag_test_http_handler'] = function ($url, $args) use (&$called) {
            $called = true;
            return ['response' => ['code' => 200], 'body' => json_encode(['Valid' => true, 'ReturnCode' => '00'])];
        };

        $checkout = $this->makeCheckout([
            'braspag_creditcard-card-expiry'        => '12/28',
            'braspag_creditcard-card-type'          => 'Visa',
            'braspag_creditcard-card-holder'        => 'JOAO SILVA',
            'braspag_creditcard-card-cvc'           => '123',
            'braspag_creditcard-card-paymenttoken'  => 'tok-sop-xyz',
            'braspag_creditcard-card-installments'  => '1',
            'wc-braspag_creditcard-new-payment-method' => 'false',
        ]);

        $this->gateway->braspag_pagador_creditcard_payment_request_builder(
            [],
            $this->makeOrder(),
            $checkout,
            $this->makeCart()
        );

        unset($GLOBALS['_braspag_test_http_handler']);

        $this->assertFalse($called, 'ZeroAuth não deve ser chamado quando SOP está ativo.');
    }

    public function test_verifycard_ativo_cartao_invalido_bloqueia_pagamento(): void
    {
        $this->setProperty('sop_enabled', 'no');
        $this->setProperty('verifycard_enabled', 'yes');
        $this->setProperty('merchant_id', 'merchant-id');
        $this->setProperty('merchant_key', 'merchant-key');

        $GLOBALS['_braspag_test_http_handler'] = function ($url, $args) {
            return ['response' => ['code' => 200], 'body' => json_encode(['Valid' => false, 'ReturnCode' => '57'])];
        };

        $checkout = $this->makeCheckout([
            'braspag_creditcard-card-expiry'       => '12/28',
            'braspag_creditcard-card-type'         => 'Visa',
            'braspag_creditcard-card-holder'       => 'JOAO SILVA',
            'braspag_creditcard-card-cvc'          => '123',
            'braspag_creditcard-card-number'       => '4111111111111111',
            'braspag_creditcard-card-installments' => '1',
            'wc-braspag_creditcard-new-payment-method' => 'false',
        ]);

        $this->expectException(WC_Braspag_Exception::class);

        try {
            $this->gateway->braspag_pagador_creditcard_payment_request_builder(
                [],
                $this->makeOrder(),
                $checkout,
                $this->makeCart()
            );
        } finally {
            unset($GLOBALS['_braspag_test_http_handler']);
        }
    }

    public function test_verifycard_ativo_amex_ignora_zero_auth(): void
    {
        $this->setProperty('sop_enabled', 'no');
        $this->setProperty('verifycard_enabled', 'yes');
        $this->setProperty('merchant_id', 'merchant-id');
        $this->setProperty('merchant_key', 'merchant-key');

        $called = false;
        $GLOBALS['_braspag_test_http_handler'] = function ($url, $args) use (&$called) {
            $called = true;
            return ['response' => ['code' => 200], 'body' => json_encode(['Valid' => false, 'ReturnCode' => '57'])];
        };

        $checkout = $this->makeCheckout([
            'braspag_creditcard-card-expiry'       => '12/28',
            'braspag_creditcard-card-type'         => 'Amex',
            'braspag_creditcard-card-holder'       => 'JOAO SILVA',
            'braspag_creditcard-card-cvc'          => '1234',
            'braspag_creditcard-card-number'       => '374245455400126',
            'braspag_creditcard-card-installments' => '1',
            'wc-braspag_creditcard-new-payment-method' => 'false',
        ]);

        $result = $this->gateway->braspag_pagador_creditcard_payment_request_builder(
            [],
            $this->makeOrder(),
            $checkout,
            $this->makeCart()
        );

        unset($GLOBALS['_braspag_test_http_handler']);

        $this->assertFalse($called, 'ZeroAuth não deve ser chamado para Amex (erro 57 na Cielo).');
        $this->assertSame('374245455400126', $result['CreditCard']['CardNumber']);
    }

    // ── Expiração: converte MM/YY → MM/20YY ───────────────────────────────────

    public function test_expiracao_curta_e_expandida_para_4_digitos(): void
    {
        $this->setProperty('sop_enabled', 'no');

        $checkout = $this->makeCheckout([
            'braspag_creditcard-card-expiry'       => '12/28',
            'braspag_creditcard-card-type'         => 'Visa',
            'braspag_creditcard-card-holder'       => 'JOAO',
            'braspag_creditcard-card-cvc'          => '123',
            'braspag_creditcard-card-number'       => '4111111111111111',
            'braspag_creditcard-card-installments' => '1',
            'wc-braspag_creditcard-new-payment-method' => 'false',
        ]);

        $result = $this->gateway->braspag_pagador_creditcard_payment_request_builder(
            [],
            $this->makeOrder(),
            $checkout,
            $this->makeCart()
        );

        $this->assertSame('12/2028', $result['CreditCard']['ExpirationDate']);
    }

    // ── 3DS: ExternalAuthentication incluído quando 3DS ativo ────────────────

    public function test_3ds_inclui_external_authentication(): void
    {
        $this->setProperty('auth3ds20_mpi_is_active', 'yes');
        $this->setProperty('auth3ds20_mpi_authorize_on_error', 'no');
        $this->setProperty('auth3ds20_mpi_authorize_on_failure', 'no');
        $this->setProperty('auth3ds20_mpi_authorize_on_unenrolled', 'no');
        $this->setProperty('auth3ds20_mpi_authorize_on_unsupported_brand', 'no');

        $checkout = $this->makeCheckout([
            'bpmpi_auth_failure_type'  => '0',
            'bpmpi_auth_cavv'          => 'cavv-abc',
            'bpmpi_auth_xid'           => 'xid-xyz',
            'bpmpi_auth_eci'           => '05',
            'bpmpi_auth_version'       => '2.2.0',
            'bpmpi_auth_reference_id'  => 'ref-001',
        ]);

        $result = $this->gateway->braspag_pagador_creditcard_payment_request_auth3ds20_builder(
            [],
            $this->makeOrder(),
            $checkout,
            $this->makeCart()
        );

        $this->assertArrayHasKey('ExternalAuthentication', $result);
        $this->assertSame('cavv-abc', $result['ExternalAuthentication']['Cavv']);
        $this->assertSame('05', $result['ExternalAuthentication']['Eci']);
        $this->assertSame('2.2.0', $result['ExternalAuthentication']['Version']);
        $this->assertSame('ref-001', $result['ExternalAuthentication']['ReferenceId']);
        $this->assertFalse($result['ExternalAuthentication']['DataOnly']);
    }

    public function test_3ds_data_only_marca_dataonly_true_quando_notifyonly_ativo(): void
    {
        $this->setProperty('auth3ds20_mpi_is_active', 'yes');
        $this->setProperty('auth3ds20_mpi_authorize_on_error', 'no');
        $this->setProperty('auth3ds20_mpi_authorize_on_failure', 'no');
        $this->setProperty('auth3ds20_mpi_authorize_on_unenrolled', 'no');
        $this->setProperty('auth3ds20_mpi_authorize_on_unsupported_brand', 'no');

        $checkout = $this->makeCheckout([
            'bpmpi_auth_failure_type'  => '0',
            'bpmpi_auth_cavv'          => 'cavv-abc',
            'bpmpi_auth_eci'           => '04',
            'bpmpi_auth_version'       => '2.2.0',
            'bpmpi_auth_notifyonly'    => 'true',
        ]);

        $result = $this->gateway->braspag_pagador_creditcard_payment_request_auth3ds20_builder(
            [],
            $this->makeOrder(),
            $checkout,
            $this->makeCart()
        );

        $this->assertTrue($result['ExternalAuthentication']['DataOnly']);
    }

    public function test_3ds_nao_inclui_external_authentication_quando_inativo(): void
    {
        $this->setProperty('auth3ds20_mpi_is_active', 'no');

        $checkout = $this->makeCheckout(['bpmpi_auth_failure_type' => '0']);

        $result = $this->gateway->braspag_pagador_creditcard_payment_request_auth3ds20_builder(
            ['Type' => 'CreditCard'],
            $this->makeOrder(),
            $checkout,
            $this->makeCart()
        );

        $this->assertArrayNotHasKey('ExternalAuthentication', $result);
    }

    // ── 3DS: failure_type '1' com authorize_on_failure=no → bloqueia ─────────

    public function test_3ds_failure_type_1_com_authorize_off_bloqueia_external_authentication(): void
    {
        $this->setProperty('auth3ds20_mpi_is_active', 'yes');
        $this->setProperty('auth3ds20_mpi_authorize_on_failure', 'no');
        $this->setProperty('auth3ds20_mpi_authorize_on_error', 'no');
        $this->setProperty('auth3ds20_mpi_authorize_on_unenrolled', 'no');
        $this->setProperty('auth3ds20_mpi_authorize_on_unsupported_brand', 'no');

        $checkout = $this->makeCheckout([
            'bpmpi_auth_failure_type' => '1',
            'bpmpi_auth_cavv'         => '',
            'bpmpi_auth_xid'          => '',
            'bpmpi_auth_eci'          => '',
            'bpmpi_auth_version'      => '',
            'bpmpi_auth_reference_id' => '',
        ]);

        $result = $this->gateway->braspag_pagador_creditcard_payment_request_auth3ds20_builder(
            [],
            $this->makeOrder(),
            $checkout,
            $this->makeCart()
        );

        // Quando failure_type indica falha e authorize=no, ExternalAuthentication
        // ainda é incluído com dados vazios (decisão de bloqueio fica no process_payment_validation).
        // O builder adiciona os campos — a validação é feita em processo separado.
        $this->assertArrayHasKey('ExternalAuthentication', $result);
    }

    public function test_3ds_failure_type_1_com_authorize_on_failure_yes_retorna_sem_bloquear(): void
    {
        $this->setProperty('auth3ds20_mpi_is_active', 'yes');
        $this->setProperty('auth3ds20_mpi_authorize_on_failure', 'yes');
        $this->setProperty('auth3ds20_mpi_authorize_on_error', 'no');
        $this->setProperty('auth3ds20_mpi_authorize_on_unenrolled', 'no');
        $this->setProperty('auth3ds20_mpi_authorize_on_unsupported_brand', 'no');

        $checkout = $this->makeCheckout([
            'bpmpi_auth_failure_type' => '1',
            'bpmpi_auth_cavv'         => 'c',
            'bpmpi_auth_xid'          => 'x',
            'bpmpi_auth_eci'          => '06',
            'bpmpi_auth_version'      => '2.2.0',
            'bpmpi_auth_reference_id' => 'r',
        ]);

        $result = $this->gateway->braspag_pagador_creditcard_payment_request_auth3ds20_builder(
            [],
            $this->makeOrder(),
            $checkout,
            $this->makeCart()
        );

        // authorize_on_failure=yes → passa sem ExternalAuthentication (block=false, early return)
        $this->assertArrayNotHasKey('ExternalAuthentication', $result);
    }

    // ── Antifraude: FraudAnalysis incluído na transação Pagador ──────────────

    public function test_antifraud_inclui_fraud_analysis_quando_send_with_pagador_ativo(): void
    {
        $this->setProperty('antifraud_enabled', 'yes');
        $this->setProperty('antifraud_send_with_pagador_transaction', 'yes');
        $this->setProperty('sop_enabled', 'no');
        $this->setProperty('antifraud_options_sequence', 'AuthorizeFirst');
        $this->setProperty('antifraud_options_sequence_criteria', 'Always');
        $this->setProperty('antifraud_options_capture_on_low_risk', 'no');
        $this->setProperty('antifraud_options_void_on_righ_risk', 'yes');
        $this->setProperty('merchant_category', '5999');

        $checkout = $this->makeCheckout([]);
        $order    = $this->makeOrder();
        $cart     = $this->makeCart([]);

        $result = $this->gateway->braspag_pagador_creditcard_payment_request_antifraud_builder(
            [],
            $order,
            $checkout,
            $cart
        );

        $this->assertArrayHasKey('FraudAnalysis', $result);
        $this->assertSame('AuthorizeFirst', $result['FraudAnalysis']['Sequence']);
        $this->assertSame('Always', $result['FraudAnalysis']['SequenceCriteria']);
        $this->assertTrue($result['FraudAnalysis']['VoidOnHighRisk']);
        $this->assertFalse($result['FraudAnalysis']['CaptureOnLowRisk']);
    }

    public function test_antifraud_nao_inclui_fraud_analysis_quando_desabilitado(): void
    {
        $this->setProperty('antifraud_enabled', 'no');
        $this->setProperty('antifraud_send_with_pagador_transaction', 'yes');
        $this->setProperty('sop_enabled', 'no');

        $result = $this->gateway->braspag_pagador_creditcard_payment_request_antifraud_builder(
            ['Type' => 'CreditCard'],
            $this->makeOrder(),
            $this->makeCheckout([]),
            $this->makeCart()
        );

        $this->assertArrayNotHasKey('FraudAnalysis', $result);
    }

    public function test_antifraud_inclui_fraud_analysis_quando_sop_ativo(): void
    {
        // ADR-004 revogado: SOP + Antifraude são compatíveis. FraudAnalysis deve ser incluído.
        $this->setProperty('antifraud_enabled', 'yes');
        $this->setProperty('antifraud_send_with_pagador_transaction', 'yes');
        $this->setProperty('sop_enabled', 'yes');

        $result = $this->gateway->braspag_pagador_creditcard_payment_request_antifraud_builder(
            [],
            $this->makeOrder(),
            $this->makeCheckout([]),
            $this->makeCart()
        );

        $this->assertArrayHasKey('FraudAnalysis', $result);
    }

    // ── Antifraude ClearSale ──────────────────────────────────────────────────

    private function makeClearSaleGateway(array $identityData = ['value' => '123.456.789-00']): WC_Gateway_Braspag_CreditCard
    {
        $gw = $this->getMockBuilder(WC_Gateway_Braspag_CreditCard::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_braspag_payment_provider', 'get_antifraud_browser_fingerprint', 'get_antifraud_provider_name', 'get_customer_identity_data', 'get_logged_in_customer_id'])
            ->getMock();

        $gw->method('get_braspag_payment_provider')->willReturn('Cielo30');
        $gw->method('get_antifraud_provider_name')->willReturn('ClearSale');
        $gw->method('get_antifraud_browser_fingerprint')->willReturn('fp-abc123');
        $gw->method('get_customer_identity_data')->willReturn($identityData);
        $gw->method('get_logged_in_customer_id')->willReturn(0);

        return $gw;
    }

    private function setPropertyOn(object $obj, string $name, mixed $value): void
    {
        $prop = (new ReflectionClass($obj))->getProperty($name);
        $prop->setAccessible(true);
        $prop->setValue($obj, $value);
    }

    private function setupClearSaleProperties(object $gw, string $appKey = 'my-clearsale-app-key'): void
    {
        $this->setPropertyOn($gw, 'antifraud_enabled', 'yes');
        $this->setPropertyOn($gw, 'antifraud_send_with_pagador_transaction', 'yes');
        $this->setPropertyOn($gw, 'antifraud_clearsale_app_key', $appKey);
        $this->setPropertyOn($gw, 'antifraud_options_sequence', 'AuthorizeFirst');
        $this->setPropertyOn($gw, 'antifraud_options_sequence_criteria', 'OnSuccess');
        $this->setPropertyOn($gw, 'antifraud_options_capture_on_low_risk', 'no');
        $this->setPropertyOn($gw, 'antifraud_options_void_on_righ_risk', 'no');
        $this->setPropertyOn($gw, 'merchant_category', '5999');
        $this->setPropertyOn($gw, 'extra_data_collection', []);
        $this->setPropertyOn($gw, 'sop_enabled', 'no');
    }

    public function test_clearsale_inclui_app_key_no_fraud_analysis(): void
    {
$gw = $this->makeClearSaleGateway();
        $this->setupClearSaleProperties($gw, 'my-clearsale-app-key');

        $result = $gw->braspag_pagador_creditcard_payment_request_antifraud_builder(
            [],
            $this->makeOrder(),
            $this->makeCheckout([]),
            $this->makeCart([])
        );

        $this->assertArrayHasKey('FraudAnalysis', $result);
        $this->assertSame('ClearSale', $result['FraudAnalysis']['Provider']);
        $this->assertSame('my-clearsale-app-key', $result['FraudAnalysis']['AppKey']);
    }

    public function test_clearsale_sem_app_key_nao_inclui_campo_app_key(): void
    {
        $gw = $this->makeClearSaleGateway();
        $this->setupClearSaleProperties($gw, '');

        $result = $gw->braspag_pagador_creditcard_payment_request_antifraud_builder(
            [],
            $this->makeOrder(),
            $this->makeCheckout([]),
            $this->makeCart([])
        );

        $this->assertArrayHasKey('FraudAnalysis', $result);
        $this->assertArrayNotHasKey('AppKey', $result['FraudAnalysis']);
    }

    public function test_clearsale_shipping_inclui_identity_identitytype_e_endereco(): void
    {
        // Doc: docs.cielo.com.br/gateway/reference/antifraude-clearsale
        // FraudAnalysis.Shipping exige Identity, IdentityType (1=PF/2=PJ) e
        // endereço completo (Street, Number, Neighborhood, City, State,
        // Country, ZipCode) — ausentes causavam 400 no fluxo embutido.
        $gw = $this->makeClearSaleGateway(['type' => 'CPF', 'value' => '123.456.789-00']);
        $this->setupClearSaleProperties($gw, 'my-clearsale-app-key');

        $result = $gw->braspag_pagador_creditcard_payment_request_antifraud_builder(
            [],
            $this->makeOrder(),
            $this->makeCheckout([]),
            $this->makeCart([])
        );

        $shipping = $result['FraudAnalysis']['Shipping'];
        $this->assertSame('12345678900', $shipping['Identity']);
        $this->assertSame('1', $shipping['IdentityType']);
        $this->assertSame('Rua A', $shipping['Street']);
        $this->assertSame('1', $shipping['Number']);
        $this->assertSame('Centro', $shipping['Neighborhood']);
        $this->assertSame('SP', $shipping['City']);
        $this->assertSame('SP', $shipping['State']);
        $this->assertSame('BR', $shipping['Country']);
        $this->assertSame('01310-100', $shipping['ZipCode']);
        $this->assertSame('test@test.com', $shipping['Email']);
    }

    public function test_clearsale_shipping_identitytype_pj(): void
    {
        $gw = $this->makeClearSaleGateway(['type' => 'CNPJ', 'value' => '12.345.678/0001-90']);
        $this->setupClearSaleProperties($gw, 'my-clearsale-app-key');

        $result = $gw->braspag_pagador_creditcard_payment_request_antifraud_builder(
            [],
            $this->makeOrder(),
            $this->makeCheckout([]),
            $this->makeCart([])
        );

        $this->assertSame('2', $result['FraudAnalysis']['Shipping']['IdentityType']);
        $this->assertSame('12345678000190', $result['FraudAnalysis']['Shipping']['Identity']);
    }

    public function test_cybersource_nao_inclui_app_key(): void
    {
        $this->setProperty('antifraud_enabled', 'yes');
        $this->setProperty('antifraud_send_with_pagador_transaction', 'yes');
        $this->setProperty('antifraud_clearsale_app_key', 'should-not-appear');
        $this->setProperty('antifraud_options_sequence', 'AuthorizeFirst');
        $this->setProperty('antifraud_options_sequence_criteria', 'OnSuccess');
        $this->setProperty('antifraud_options_capture_on_low_risk', 'no');
        $this->setProperty('antifraud_options_void_on_righ_risk', 'no');
        $this->setProperty('merchant_category', '5999');

        $result = $this->gateway->braspag_pagador_creditcard_payment_request_antifraud_builder(
            [],
            $this->makeOrder(),
            $this->makeCheckout([]),
            $this->makeCart([])
        );

        $this->assertArrayNotHasKey('Identity', $result['FraudAnalysis']['Shipping']);
        $this->assertArrayNotHasKey('IdentityType', $result['FraudAnalysis']['Shipping']);
        $this->assertArrayHasKey('FraudAnalysis', $result);
        $this->assertArrayNotHasKey('AppKey', $result['FraudAnalysis']);
    }

    // ── braspag_antifraud_request_builder (chamada standalone /Analysis/v2) ────

    private function makePagadorRequestStub(): array
    {
        return [
            'MerchantOrderId' => 'order-999',
            'Payment' => [
                'Currency' => 'BRL',
                'Card' => [
                    'Number' => '411111******1111',
                    'Holder' => 'JOAO SILVA',
                    'ExpirationDate' => '12/2028',
                    'Cvv' => '123',
                    'Brand' => 'Visa',
                ],
            ],
            'Customer' => [
                'Email' => 'test@test.com',
                'Phone' => '11999999999',
            ],
        ];
    }

    private function makePagadorResponseStub(): object
    {
        return (object) [
            'body' => (object) [
                'Payment' => (object) [
                    'PaymentId' => 'pay-123',
                    'AuthorizationCode' => 'auth-456',
                ],
            ],
        ];
    }

    public function test_clearsale_shipping_inclui_document_type_e_number(): void
    {
        $gw = $this->makeClearSaleGateway(['type' => 'CPF', 'value' => '123.456.789-00']);

        $result = $gw->braspag_antifraud_request_builder(
            $this->makeCart([]),
            $this->makeOrder(),
            $this->makePagadorRequestStub(),
            $this->makePagadorResponseStub()
        );

        $this->assertSame('CPF', $result['Shipping']['DocumentType']);
        $this->assertSame('12345678900', $result['Shipping']['DocumentNumber']);
    }

    public function test_cybersource_shipping_nao_inclui_document_type_e_number(): void
    {
        $gw = $this->getMockBuilder(WC_Gateway_Braspag_CreditCard::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_braspag_payment_provider', 'get_antifraud_browser_fingerprint', 'get_antifraud_provider_name', 'get_customer_identity_data', 'get_logged_in_customer_id'])
            ->getMock();

        $gw->method('get_braspag_payment_provider')->willReturn('Cielo30');
        $gw->method('get_antifraud_provider_name')->willReturn('Cybersource');
        $gw->method('get_antifraud_browser_fingerprint')->willReturn('fp-abc123');
        $gw->method('get_customer_identity_data')->willReturn(['type' => 'CPF', 'value' => '123.456.789-00']);
        $gw->method('get_logged_in_customer_id')->willReturn(0);

        $result = $gw->braspag_antifraud_request_builder(
            $this->makeCart([]),
            $this->makeOrder(),
            $this->makePagadorRequestStub(),
            $this->makePagadorResponseStub()
        );

        $this->assertArrayNotHasKey('DocumentType', $result['Shipping']);
        $this->assertArrayNotHasKey('DocumentNumber', $result['Shipping']);
    }

    public function test_clearsale_shipping_e_customer_phone_normalizados(): void
    {
        $gw = $this->makeClearSaleGateway(['type' => 'CPF', 'value' => '12345678900']);

        $result = $gw->braspag_antifraud_request_builder(
            $this->makeCart([]),
            $this->makeOrder(),
            $this->makePagadorRequestStub(),
            $this->makePagadorResponseStub()
        );

        $this->assertSame('11999999999', $result['Shipping']['Phone']);
    }

    public function test_document_number_do_checkout_blocks_e_reaproveitado(): void
    {
        // get_customer_identity_data já resolve CPF/CNPJ vindos do Checkout Blocks
        // (chaves 'braspag-wcbcf/cpf', 'braspag-wcbcf/cnpj', 'braspag-wcbcf/persontype').
        // Este teste garante que braspag_antifraud_request_builder simplesmente
        // reaproveita esse retorno, então o mesmo funciona nos dois checkouts.
        $gw = $this->makeClearSaleGateway(['type' => 'CNPJ', 'value' => '12.345.678/0001-90']);

        $result = $gw->braspag_antifraud_request_builder(
            $this->makeCart([]),
            $this->makeOrder(),
            $this->makePagadorRequestStub(),
            $this->makePagadorResponseStub()
        );

        $this->assertSame('CNPJ', $result['Shipping']['DocumentType']);
        $this->assertSame('12345678000190', $result['Shipping']['DocumentNumber']);
    }
}
