<?php

use PHPUnit\Framework\TestCase;

class BlocksDebitCardTest extends TestCase
{
    private WC_Braspag_Blocks_DebitCard $block;

    protected function setUp(): void
    {
        $GLOBALS['_braspag_test_options'] = [];
        $this->block = new WC_Braspag_Blocks_DebitCard();
    }

    protected function tearDown(): void
    {
        $GLOBALS['_braspag_test_options'] = [];
    }

    // ── is_active ─────────────────────────────────────────────────────────────

    public function test_is_active_retorna_false_quando_desabilitado(): void
    {
        $GLOBALS['_braspag_test_options']['woocommerce_braspag_debitcard_settings'] = ['enabled' => 'no'];
        $this->block->initialize();

        $this->assertFalse($this->block->is_active());
    }

    public function test_is_active_retorna_true_quando_habilitado(): void
    {
        $GLOBALS['_braspag_test_options']['woocommerce_braspag_debitcard_settings'] = ['enabled' => 'yes'];
        $this->block->initialize();

        $this->assertTrue($this->block->is_active());
    }

    // ── get_payment_method_data ───────────────────────────────────────────────

    public function test_retorna_chaves_obrigatorias(): void
    {
        $this->block->initialize();
        $data = $this->block->get_payment_method_data();

        foreach (['title', 'description', 'supports', 'available_types', 'auth3ds20_enabled', 'test_mode'] as $key) {
            $this->assertArrayHasKey($key, $data, "Chave ausente: $key");
        }
    }

    public function test_3ds_obrigatorio_presente_nos_dados(): void
    {
        $this->block->initialize();

        // auth3ds20_enabled deve existir — débito requer 3DS pelo spec
        $this->assertArrayHasKey('auth3ds20_enabled', $this->block->get_payment_method_data());
    }

    public function test_auth3ds20_enabled_true_quando_configurado(): void
    {
        $GLOBALS['_braspag_test_options']['woocommerce_braspag_debitcard_settings'] = [
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

    public function test_title_usa_configuracao(): void
    {
        $GLOBALS['_braspag_test_options']['woocommerce_braspag_debitcard_settings'] = [
            'enabled' => 'yes',
            'title'   => 'Débito',
        ];
        $this->block->initialize();

        $this->assertSame('Débito', $this->block->get_payment_method_data()['title']);
    }

    public function test_available_types_vazio_quando_nao_configurado(): void
    {
        $this->block->initialize();

        $this->assertSame([], $this->block->get_payment_method_data()['available_types']);
    }

    public function test_test_mode_herdado_das_configuracoes_principais(): void
    {
        $GLOBALS['_braspag_test_options']['woocommerce_braspag_settings'] = ['test_mode' => 'yes'];
        $this->block->initialize();

        $this->assertTrue($this->block->get_payment_method_data()['test_mode']);
    }

    // ── Script handles ────────────────────────────────────────────────────────

    public function test_script_handle_correto(): void
    {
        $handles = $this->block->get_payment_method_script_handles();

        $this->assertContains('wc-braspag-blocks-debitcard', $handles);
    }
}
