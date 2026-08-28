<?php

/**
 * Testa que os métodos de pagamento Braspag são registrados corretamente
 * no WooCommerce Checkout Blocks.
 *
 * @group integration
 */
class BlocksRegistrationTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        update_option('woocommerce_braspag_settings', ['enabled' => 'yes']);
        update_option('woocommerce_braspag_creditcard_settings', ['enabled' => 'yes']);
        update_option('woocommerce_braspag_debitcard_settings',  ['enabled' => 'yes']);
        update_option('woocommerce_braspag_pix_settings',        ['enabled' => 'yes']);
        update_option('woocommerce_braspag_boleto_settings',     ['enabled' => 'yes']);
    }

    /**
     * @test
     * Os 4 métodos Braspag devem estar registrados no payment method registry do Blocks.
     */
    public function test_todos_os_metodos_braspag_registrados_nos_blocks(): void
    {
        if (!class_exists('Automattic\WooCommerce\Blocks\Package')) {
            $this->markTestSkipped('WooCommerce Blocks não disponível.');
        }

        $registry = \Automattic\WooCommerce\Blocks\Package::container()
            ->get(\Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry::class);

        $registered = $registry->get_all_registered_payment_method_script_data();

        $expected = ['braspag_creditcard', 'braspag_debitcard', 'braspag_pix', 'braspag_boleto'];

        foreach ($expected as $method) {
            $this->assertArrayHasKey($method, $registered, "Método '$method' não registrado no Blocks.");
        }
    }

    /**
     * @test
     * Cada método deve ter dados de configuração acessíveis pelo frontend.
     */
    public function test_cada_metodo_tem_payment_method_data(): void
    {
        $blocks = [
            'braspag_creditcard' => new WC_Braspag_Blocks_CreditCard(),
            'braspag_debitcard'  => new WC_Braspag_Blocks_DebitCard(),
            'braspag_pix'        => new WC_Braspag_Blocks_Pix(),
            'braspag_boleto'     => new WC_Braspag_Blocks_Boleto(),
        ];

        foreach ($blocks as $name => $block) {
            $block->initialize();
            $data = $block->get_payment_method_data();

            $this->assertIsArray($data, "$name deve retornar array em get_payment_method_data()");
            $this->assertArrayHasKey('title', $data, "$name deve ter 'title'");
            $this->assertArrayHasKey('supports', $data, "$name deve ter 'supports'");
        }
    }

    /**
     * @test
     * Checkout Blocks e checkout clássico devem coexistir — o gateway clássico
     * ainda deve estar disponível via woocommerce_payment_gateways.
     */
    public function test_checkout_classico_continua_disponivel(): void
    {
        $gateways = WC()->payment_gateways()->get_available_payment_gateways();

        $this->assertNotEmpty($gateways, 'Deve haver gateways disponíveis no checkout clássico.');
    }
}
