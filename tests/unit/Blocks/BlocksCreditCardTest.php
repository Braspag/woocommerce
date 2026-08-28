<?php

use PHPUnit\Framework\TestCase;

/**
 * Testa WC_Braspag_Blocks_CreditCard sem ambiente WordPress.
 *
 * Controle de get_option(): preencha $GLOBALS['_braspag_test_options'] antes
 * de chamar initialize().
 */
class BlocksCreditCardTest extends TestCase
{
    private WC_Braspag_Blocks_CreditCard $block;

    protected function setUp(): void
    {
        $GLOBALS['_braspag_test_options']     = [];
        $GLOBALS['_braspag_test_installments'] = [];
        $this->block = new WC_Braspag_Blocks_CreditCard();
    }

    protected function tearDown(): void
    {
        $GLOBALS['_braspag_test_options']     = [];
        $GLOBALS['_braspag_test_installments'] = [];
    }

    // ── is_active ─────────────────────────────────────────────────────────────

    public function test_is_active_retorna_false_quando_gateway_desabilitado(): void
    {
        $GLOBALS['_braspag_test_options']['woocommerce_braspag_creditcard_settings'] = ['enabled' => 'no'];
        $this->block->initialize();

        $this->assertFalse($this->block->is_active());
    }

    public function test_is_active_retorna_true_quando_gateway_habilitado(): void
    {
        $GLOBALS['_braspag_test_options']['woocommerce_braspag_creditcard_settings'] = ['enabled' => 'yes'];
        $this->block->initialize();

        $this->assertTrue($this->block->is_active());
    }

    // ── get_payment_method_data: campos obrigatórios ──────────────────────────

    public function test_get_payment_method_data_retorna_chaves_obrigatorias(): void
    {
        $this->block->initialize();
        $data = $this->block->get_payment_method_data();

        $required = [
            'title', 'description', 'supports', 'available_types', 'installments',
            'save_card', 'sop_enabled', 'sop_tokenize', 'auth3ds20_enabled',
            'antifraud_enabled', 'test_mode', 'assets_url',
        ];

        foreach ($required as $key) {
            $this->assertArrayHasKey($key, $data, "Chave ausente: $key");
        }
    }

    public function test_title_usa_configuracao_quando_definida(): void
    {
        $GLOBALS['_braspag_test_options']['woocommerce_braspag_creditcard_settings'] = [
            'enabled' => 'yes',
            'title'   => 'Cartão Braspag',
        ];
        $this->block->initialize();

        $this->assertSame('Cartão Braspag', $this->block->get_payment_method_data()['title']);
    }

    public function test_title_usa_fallback_quando_ausente(): void
    {
        $this->block->initialize();
        $data = $this->block->get_payment_method_data();

        $this->assertNotEmpty($data['title']);
    }

    // ── SOP ───────────────────────────────────────────────────────────────────

    public function test_sop_enabled_true_quando_configurado(): void
    {
        $GLOBALS['_braspag_test_options']['woocommerce_braspag_settings'] = [
            'silentpost_enabled' => 'yes',
        ];
        $this->block->initialize();

        $this->assertTrue($this->block->get_payment_method_data()['sop_enabled']);
    }

    public function test_sop_enabled_false_quando_nao_configurado(): void
    {
        $this->block->initialize();

        $this->assertFalse($this->block->get_payment_method_data()['sop_enabled']);
    }

    public function test_sop_tokenize_true_quando_configurado(): void
    {
        $GLOBALS['_braspag_test_options']['woocommerce_braspag_settings'] = [
            'silentpost_enabled'    => 'yes',
            'silentpost_token_type' => 'yes',
        ];
        $this->block->initialize();

        $this->assertTrue($this->block->get_payment_method_data()['sop_tokenize']);
    }

    // ── 3DS ───────────────────────────────────────────────────────────────────

    public function test_auth3ds20_enabled_true_quando_configurado(): void
    {
        $GLOBALS['_braspag_test_options']['woocommerce_braspag_creditcard_settings'] = [
            'enabled'               => 'yes',
            'auth3ds20_mpi_is_active' => 'yes',
        ];
        $this->block->initialize();

        $this->assertTrue($this->block->get_payment_method_data()['auth3ds20_enabled']);
    }

    public function test_auth3ds20_enabled_false_por_padrao(): void
    {
        $this->block->initialize();

        $this->assertFalse($this->block->get_payment_method_data()['auth3ds20_enabled']);
    }

    // ── Antifraude ────────────────────────────────────────────────────────────

    public function test_antifraud_enabled_true_quando_configurado(): void
    {
        $GLOBALS['_braspag_test_options']['woocommerce_braspag_settings'] = [
            'antifraud_enabled' => 'yes',
        ];
        $this->block->initialize();

        $this->assertTrue($this->block->get_payment_method_data()['antifraud_enabled']);
    }

    public function test_antifraud_enabled_false_por_padrao(): void
    {
        $this->block->initialize();

        $this->assertFalse($this->block->get_payment_method_data()['antifraud_enabled']);
    }

    // ── Test mode ─────────────────────────────────────────────────────────────

    public function test_test_mode_true_quando_configurado(): void
    {
        $GLOBALS['_braspag_test_options']['woocommerce_braspag_settings'] = [
            'test_mode' => 'yes',
        ];
        $this->block->initialize();

        $this->assertTrue($this->block->get_payment_method_data()['test_mode']);
    }

    public function test_test_mode_false_por_padrao(): void
    {
        $this->block->initialize();

        $this->assertFalse($this->block->get_payment_method_data()['test_mode']);
    }

    // ── Installments ──────────────────────────────────────────────────────────

    public function test_installments_retorna_fallback_quando_vazio(): void
    {
        $this->block->initialize();
        $installments = $this->block->get_payment_method_data()['installments'];

        $this->assertIsArray($installments);
        $this->assertNotEmpty($installments);
        $this->assertArrayHasKey('1', $installments);
    }

    public function test_installments_retorna_opcoes_do_gateway(): void
    {
        $this->block->initialize();

        $installments = $this->block->get_payment_method_data()['installments'];
        $this->assertIsArray($installments);
        $this->assertNotEmpty($installments);
        $this->assertArrayHasKey('1', $installments);
    }

    // ── available_types ───────────────────────────────────────────────────────

    public function test_available_types_retorna_array_vazio_quando_nao_configurado(): void
    {
        $this->block->initialize();

        $this->assertSame([], $this->block->get_payment_method_data()['available_types']);
    }

    public function test_available_types_retorna_valores_configurados(): void
    {
        $GLOBALS['_braspag_test_options']['woocommerce_braspag_creditcard_settings'] = [
            'enabled'         => 'yes',
            'available_types' => ['Cielo-Visa', 'Cielo-Master'],
        ];
        $this->block->initialize();

        $types = $this->block->get_payment_method_data()['available_types'];
        $this->assertContains('Cielo-Visa', $types);
        $this->assertContains('Cielo-Master', $types);
    }

    // ── Script handles ────────────────────────────────────────────────────────

    public function test_get_payment_method_script_handles_retorna_handle_correto(): void
    {
        $handles = $this->block->get_payment_method_script_handles();

        $this->assertIsArray($handles);
        $this->assertContains('wc-braspag-blocks-creditcard', $handles);
    }
}
