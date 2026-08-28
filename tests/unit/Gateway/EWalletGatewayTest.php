<?php

use PHPUnit\Framework\TestCase;

/**
 * Testa validate_fields(), get_enabled_wallets(), is_available() e form_fields do gateway braspag_ewallet.
 * Usa newInstanceWithoutConstructor() + ReflectionProperty para evitar dependências WP.
 */
class EWalletGatewayTest extends TestCase
{
    private WC_Gateway_Braspag_EWallet $gateway;

    protected function setUp(): void
    {
        $ref           = new ReflectionClass(WC_Gateway_Braspag_EWallet::class);
        $this->gateway = $ref->newInstanceWithoutConstructor();

        // Simula settings padrão carregados no constructor
        $this->setProp('id', 'braspag_ewallet');
        $this->setProp('enabled', 'yes');
        $this->setProp('apple_pay_enabled', 'yes');
        $this->setProp('apple_pay_merchant_id', 'merchant.test');
        $this->setProp('google_pay_enabled', 'yes');
        $this->setProp('google_pay_merchant_id', '');
        $this->setProp('samsung_pay_enabled', 'no');
        $this->setProp('samsung_pay_service_id', '');
        $this->setProp('installments', 1);
        $this->setProp('soft_descriptor', '');
        $this->setProp('test_mode', false);

        // form_fields via init_form_fields()
        $this->gateway->init_form_fields();

        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_POST = [];
    }

    private function setProp(string $name, mixed $value): void
    {
        $ref  = new ReflectionClass($this->gateway);
        // Traverse parent classes if property not in direct class
        while ($ref !== false) {
            if ($ref->hasProperty($name)) {
                $prop = $ref->getProperty($name);
                $prop->setAccessible(true);
                $prop->setValue($this->gateway, $value);
                return;
            }
            $ref = $ref->getParentClass();
        }
    }

    // ── validate_fields ───────────────────────────────────────────────────────

    public function test_validate_fields_retorna_true_quando_campos_validos(): void
    {
        $_POST['braspag_wallet_type'] = 'ApplePay';
        $_POST['braspag_wallet_key']  = 'valid_token';

        $this->assertTrue($this->gateway->validate_fields());
    }

    public function test_validate_fields_retorna_false_quando_wallet_key_vazio(): void
    {
        $_POST['braspag_wallet_type'] = 'ApplePay';
        $_POST['braspag_wallet_key']  = '';

        $this->assertFalse($this->gateway->validate_fields());
    }

    public function test_validate_fields_retorna_false_quando_wallet_type_invalido(): void
    {
        $_POST['braspag_wallet_type'] = 'InvalidWallet';
        $_POST['braspag_wallet_key']  = 'token';

        $this->assertFalse($this->gateway->validate_fields());
    }

    public function test_validate_fields_retorna_false_quando_wallet_type_vazio(): void
    {
        $_POST['braspag_wallet_type'] = '';
        $_POST['braspag_wallet_key']  = 'token';

        $this->assertFalse($this->gateway->validate_fields());
    }

    public function test_validate_fields_aceita_google_pay(): void
    {
        $_POST['braspag_wallet_type'] = 'GooglePay';
        $_POST['braspag_wallet_key']  = 'google_token';

        $this->assertTrue($this->gateway->validate_fields());
    }

    public function test_validate_fields_aceita_samsung_pay(): void
    {
        $_POST['braspag_wallet_type'] = 'SamsungPay';
        $_POST['braspag_wallet_key']  = 'samsung_ref_id';

        $this->assertTrue($this->gateway->validate_fields());
    }

    // ── get_enabled_wallets ───────────────────────────────────────────────────

    public function test_get_enabled_wallets_retorna_apple_e_google(): void
    {
        $wallets = $this->gateway->get_enabled_wallets();

        $this->assertContains('ApplePay', $wallets);
        $this->assertContains('GooglePay', $wallets);
        $this->assertNotContains('SamsungPay', $wallets);
    }

    public function test_get_enabled_wallets_vazio_quando_nenhuma_habilitada(): void
    {
        $this->setProp('apple_pay_enabled', 'no');
        $this->setProp('google_pay_enabled', 'no');
        $this->setProp('samsung_pay_enabled', 'no');

        $this->assertEmpty($this->gateway->get_enabled_wallets());
    }

    public function test_get_enabled_wallets_inclui_samsung_quando_habilitado(): void
    {
        $this->setProp('samsung_pay_enabled', 'yes');

        $this->assertContains('SamsungPay', $this->gateway->get_enabled_wallets());
    }

    // ── is_available ──────────────────────────────────────────────────────────

    public function test_is_available_true_quando_ao_menos_uma_wallet_habilitada(): void
    {
        $this->assertTrue($this->gateway->is_available());
    }

    public function test_is_available_false_quando_gateway_disabled(): void
    {
        $this->setProp('enabled', 'no');

        $this->assertFalse($this->gateway->is_available());
    }

    public function test_is_available_false_quando_nenhuma_wallet_habilitada(): void
    {
        $this->setProp('apple_pay_enabled', 'no');
        $this->setProp('google_pay_enabled', 'no');
        $this->setProp('samsung_pay_enabled', 'no');

        $this->assertFalse($this->gateway->is_available());
    }

    // ── Admin settings ────────────────────────────────────────────────────────

    public function test_id_do_gateway_e_braspag_ewallet(): void
    {
        $this->assertSame('braspag_ewallet', $this->gateway->id);
    }

    public function test_form_fields_tem_todas_as_chaves_obrigatorias(): void
    {
        $fields = $this->gateway->form_fields;

        $required = [
            'enabled', 'title', 'description',
            'apple_pay_enabled', 'apple_pay_merchant_id',
            'google_pay_enabled', 'google_pay_merchant_id',
            'samsung_pay_enabled', 'samsung_pay_service_id',
            'installments', 'soft_descriptor',
        ];

        foreach ($required as $key) {
            $this->assertArrayHasKey($key, $fields, "Campo ausente nos form_fields: {$key}");
        }
    }
}
