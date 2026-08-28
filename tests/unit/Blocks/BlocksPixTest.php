<?php

use PHPUnit\Framework\TestCase;

class BlocksPixTest extends TestCase
{
    private WC_Braspag_Blocks_Pix $block;

    protected function setUp(): void
    {
        $GLOBALS['_braspag_test_options'] = [];
        $this->block = new WC_Braspag_Blocks_Pix();
    }

    protected function tearDown(): void
    {
        $GLOBALS['_braspag_test_options'] = [];
    }

    // ── is_active ─────────────────────────────────────────────────────────────

    public function test_is_active_false_quando_desabilitado(): void
    {
        $GLOBALS['_braspag_test_options']['woocommerce_braspag_pix_settings'] = ['enabled' => 'no'];
        $this->block->initialize();

        $this->assertFalse($this->block->is_active());
    }

    public function test_is_active_true_quando_habilitado(): void
    {
        $GLOBALS['_braspag_test_options']['woocommerce_braspag_pix_settings'] = ['enabled' => 'yes'];
        $this->block->initialize();

        $this->assertTrue($this->block->is_active());
    }

    // ── get_payment_method_data ───────────────────────────────────────────────

    public function test_retorna_chaves_obrigatorias(): void
    {
        $this->block->initialize();
        $data = $this->block->get_payment_method_data();

        foreach (['title', 'description', 'supports'] as $key) {
            $this->assertArrayHasKey($key, $data, "Chave ausente: $key");
        }
    }

    public function test_title_usa_configuracao(): void
    {
        $GLOBALS['_braspag_test_options']['woocommerce_braspag_pix_settings'] = [
            'enabled' => 'yes',
            'title'   => 'Pix Instantâneo',
        ];
        $this->block->initialize();

        $this->assertSame('Pix Instantâneo', $this->block->get_payment_method_data()['title']);
    }

    public function test_title_usa_fallback_quando_ausente(): void
    {
        $this->block->initialize();

        $this->assertNotEmpty($this->block->get_payment_method_data()['title']);
    }

    public function test_description_vazia_por_padrao(): void
    {
        $this->block->initialize();

        $this->assertSame('', $this->block->get_payment_method_data()['description']);
    }

    public function test_description_usa_configuracao(): void
    {
        $GLOBALS['_braspag_test_options']['woocommerce_braspag_pix_settings'] = [
            'enabled'     => 'yes',
            'description' => 'Pague com Pix em segundos.',
        ];
        $this->block->initialize();

        $this->assertSame('Pague com Pix em segundos.', $this->block->get_payment_method_data()['description']);
    }

    public function test_supports_presente(): void
    {
        $this->block->initialize();

        $supports = $this->block->get_payment_method_data()['supports'];
        $this->assertNotEmpty($supports);
    }

    // ── Script handles ────────────────────────────────────────────────────────

    public function test_script_handle_correto(): void
    {
        $this->assertContains('wc-braspag-blocks-pix', $this->block->get_payment_method_script_handles());
    }

    // ── Name ──────────────────────────────────────────────────────────────────

    public function test_name_correto(): void
    {
        $this->assertSame('braspag_pix', $this->block->get_name());
    }
}
