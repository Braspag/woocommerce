<?php

use PHPUnit\Framework\TestCase;

/**
 * Testa o builder de payload do WC_Gateway_Braspag_Pix.
 * Verifica Type, Amount em centavos, Provider (inclui Cielo2) e QrCodeExpiration.
 */
class PixPayloadBuilderTest extends TestCase
{
    private WC_Gateway_Braspag_Pix $gateway;

    protected function setUp(): void
    {
        $ref = new ReflectionClass(WC_Gateway_Braspag_Pix::class);
        $this->gateway = $ref->newInstanceWithoutConstructor();

        $this->setProperty('available_type', 'Cielo2');
        $this->setProperty('days_to_expire', 7200);
    }

    private function setProperty(string $name, mixed $value): void
    {
        $ref  = new ReflectionClass($this->gateway);
        $prop = $ref->getProperty($name);
        $prop->setAccessible(true);
        $prop->setValue($this->gateway, $value);
    }

    private function makeOrder(float $total = 50.00): object
    {
        $order = $this->createMock(WC_Order::class);
        $order->method('get_total')->willReturn($total);
        $order->method('get_id')->willReturn(1);
        return $order;
    }

    public function test_payload_type_is_pix()
    {
        $result = $this->gateway->braspag_pagador_pix_payment_request_builder(
            [], $this->makeOrder(), (object)[], null
        );

        $this->assertEquals('Pix', $result['Type']);
    }

    public function test_payload_amount_in_cents()
    {
        $result = $this->gateway->braspag_pagador_pix_payment_request_builder(
            [], $this->makeOrder(49.99), (object)[], null
        );

        $this->assertEquals(4999, $result['Amount']);
    }

    public function test_payload_provider_cielo2()
    {
        $result = $this->gateway->braspag_pagador_pix_payment_request_builder(
            [], $this->makeOrder(), (object)[], null
        );

        $this->assertEquals('Cielo2', $result['Provider']);
    }

    public function test_payload_provider_banco_do_brasil()
    {
        $this->setProperty('available_type', 'BancodoBrasil3');

        $result = $this->gateway->braspag_pagador_pix_payment_request_builder(
            [], $this->makeOrder(), (object)[], null
        );

        $this->assertEquals('BancodoBrasil3', $result['Provider']);
    }

    public function test_payload_qr_code_expiration_matches_setting()
    {
        $result = $this->gateway->braspag_pagador_pix_payment_request_builder(
            [], $this->makeOrder(), (object)[], null
        );

        $this->assertEquals(7200, $result['QrCodeExpiration']);
    }

    public function test_payload_partner_is_woo()
    {
        $result = $this->gateway->braspag_pagador_pix_payment_request_builder(
            [], $this->makeOrder(), (object)[], null
        );

        $this->assertEquals('WOO', $result['Partner']);
    }
}
