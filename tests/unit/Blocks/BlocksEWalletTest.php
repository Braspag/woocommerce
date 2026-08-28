<?php

use PHPUnit\Framework\TestCase;

/**
 * Testa WC_Braspag_Blocks_EWallet sem ambiente WordPress.
 */
class BlocksEWalletTest extends TestCase
{
    private WC_Braspag_Blocks_EWallet $block;

    protected function setUp(): void
    {
        $GLOBALS['_braspag_test_options'] = [];
        $this->block = new WC_Braspag_Blocks_EWallet();
    }

    protected function tearDown(): void
    {
        $GLOBALS['_braspag_test_options'] = [];
    }

    // ── is_active ─────────────────────────────────────────────────────────────

    public function test_is_active_false_quando_gateway_desabilitado(): void
    {
        $GLOBALS['_braspag_test_options']['woocommerce_braspag_ewallet_settings'] = [
            'enabled' => 'no',
        ];
        $this->block->initialize();

        $this->assertFalse($this->block->is_active());
    }

    public function test_is_active_false_quando_nenhuma_wallet_habilitada(): void
    {
        $GLOBALS['_braspag_test_options']['woocommerce_braspag_ewallet_settings'] = [
            'enabled'             => 'yes',
            'apple_pay_enabled'   => 'no',
            'google_pay_enabled'  => 'no',
            'samsung_pay_enabled' => 'no',
        ];
        $this->block->initialize();

        $this->assertFalse($this->block->is_active());
    }

    public function test_is_active_true_quando_apple_pay_habilitado(): void
    {
        $GLOBALS['_braspag_test_options']['woocommerce_braspag_ewallet_settings'] = [
            'enabled'           => 'yes',
            'apple_pay_enabled' => 'yes',
        ];
        $this->block->initialize();

        $this->assertTrue($this->block->is_active());
    }

    public function test_is_active_true_quando_google_pay_habilitado(): void
    {
        $GLOBALS['_braspag_test_options']['woocommerce_braspag_ewallet_settings'] = [
            'enabled'            => 'yes',
            'google_pay_enabled' => 'yes',
        ];
        $this->block->initialize();

        $this->assertTrue($this->block->is_active());
    }

    // ── get_payment_method_data ───────────────────────────────────────────────

    public function test_retorna_chaves_obrigatorias(): void
    {
        $this->block->initialize();
        $data = $this->block->get_payment_method_data();

        $required = [
            'title', 'description', 'supports', 'wallets',
            'appleMerchantId', 'googleMerchantId', 'samsungServiceId',
            'installments', 'ajaxUrl', 'isSandbox', 'test_mode', 'assets_url',
        ];

        foreach ($required as $key) {
            $this->assertArrayHasKey($key, $data, "Chave ausente: {$key}");
        }
    }

    public function test_wallets_lista_apenas_as_habilitadas(): void
    {
        $GLOBALS['_braspag_test_options']['woocommerce_braspag_ewallet_settings'] = [
            'enabled'             => 'yes',
            'apple_pay_enabled'   => 'yes',
            'google_pay_enabled'  => 'no',
            'samsung_pay_enabled' => 'yes',
        ];
        $this->block->initialize();

        $data = $this->block->get_payment_method_data();

        $this->assertContains('ApplePay', $data['wallets']);
        $this->assertNotContains('GooglePay', $data['wallets']);
        $this->assertContains('SamsungPay', $data['wallets']);
    }

    public function test_wallets_vazio_quando_nenhuma_habilitada(): void
    {
        $this->block->initialize();
        $data = $this->block->get_payment_method_data();

        $this->assertIsArray($data['wallets']);
        $this->assertEmpty($data['wallets']);
    }

    public function test_apple_merchant_id_retornado(): void
    {
        $GLOBALS['_braspag_test_options']['woocommerce_braspag_ewallet_settings'] = [
            'apple_pay_merchant_id' => 'merchant.com.loja',
        ];
        $this->block->initialize();

        $this->assertSame('merchant.com.loja', $this->block->get_payment_method_data()['appleMerchantId']);
    }

    public function test_test_mode_true_quando_sandbox(): void
    {
        $GLOBALS['_braspag_test_options']['woocommerce_braspag_settings'] = [
            'test_mode' => 'yes',
        ];
        $this->block->initialize();

        $data = $this->block->get_payment_method_data();
        $this->assertTrue($data['isSandbox']);
        $this->assertTrue($data['test_mode']);
    }

    public function test_test_mode_false_por_padrao(): void
    {
        $this->block->initialize();

        $this->assertFalse($this->block->get_payment_method_data()['test_mode']);
    }

    // ── Script handles ────────────────────────────────────────────────────────

    public function test_script_handles_contem_braspag_ewallet(): void
    {
        $handles = $this->block->get_payment_method_script_handles();

        $this->assertIsArray($handles);
        $this->assertContains('braspag-ewallet', $handles);
    }
}
