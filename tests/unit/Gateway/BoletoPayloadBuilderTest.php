<?php

use PHPUnit\Framework\TestCase;

/**
 * Testa o builder de payload do WC_Gateway_Braspag_Boleto.
 * Verifica Type, Amount em centavos, ExpirationDate e Instructions.
 */
class BoletoPayloadBuilderTest extends TestCase
{
    private WC_Gateway_Braspag_Boleto $gateway;

    protected function setUp(): void
    {
        $ref = new ReflectionClass(WC_Gateway_Braspag_Boleto::class);
        $this->gateway = $ref->newInstanceWithoutConstructor();

        $this->setProperty('available_type', 'Bradesco2');
        $this->setProperty('days_to_expire', 3);
        $this->setProperty('payment_instructions_for_bank', 'Não aceitar após o vencimento.');
    }

    private function setProperty(string $name, mixed $value): void
    {
        $ref  = new ReflectionClass($this->gateway);
        $prop = $ref->getProperty($name);
        $prop->setAccessible(true);
        $prop->setValue($this->gateway, $value);
    }

    private function makeOrder(float $total = 150.00, int $id = 10): object
    {
        $created = new WC_DateTime();
        $order = $this->createMock(WC_Order::class);
        $order->method('get_total')->willReturn($total);
        $order->method('get_id')->willReturn($id);
        $order->method('get_date_created')->willReturn($created);
        return $order;
    }

    public function test_payload_type_is_boleto()
    {
        $result = $this->gateway->braspag_pagador_boleto_payment_request_builder(
            [], $this->makeOrder(), (object)[], null
        );

        $this->assertEquals('Boleto', $result['Type']);
    }

    public function test_payload_amount_in_cents()
    {
        $result = $this->gateway->braspag_pagador_boleto_payment_request_builder(
            [], $this->makeOrder(150.00), (object)[], null
        );

        $this->assertEquals(15000, $result['Amount']);
    }

    public function test_payload_provider_matches_setting()
    {
        $result = $this->gateway->braspag_pagador_boleto_payment_request_builder(
            [], $this->makeOrder(), (object)[], null
        );

        $this->assertEquals('Bradesco2', $result['Provider']);
    }

    public function test_payload_expiration_date_offset_by_days()
    {
        $result = $this->gateway->braspag_pagador_boleto_payment_request_builder(
            [], $this->makeOrder(), (object)[], null
        );

        $expiration = new DateTime($result['ExpirationDate']);
        $today      = new DateTime('today');
        $diff       = (int) $today->diff($expiration)->days;

        $this->assertEquals(3, $diff);
    }

    public function test_payload_instructions_match_setting()
    {
        $result = $this->gateway->braspag_pagador_boleto_payment_request_builder(
            [], $this->makeOrder(), (object)[], null
        );

        $this->assertEquals('Não aceitar após o vencimento.', $result['Instructions']);
    }

    public function test_payload_boleto_number_is_order_id()
    {
        $result = $this->gateway->braspag_pagador_boleto_payment_request_builder(
            [], $this->makeOrder(10.00, 55), (object)[], null
        );

        $this->assertEquals(55, $result['BoletoNumber']);
    }

    public function test_payload_partner_is_woo()
    {
        $result = $this->gateway->braspag_pagador_boleto_payment_request_builder(
            [], $this->makeOrder(), (object)[], null
        );

        $this->assertEquals('WOO', $result['Partner']);
    }

    public function test_minimum_expiration_one_day_when_zero_set()
    {
        $this->setProperty('days_to_expire', 0);

        $result = $this->gateway->braspag_pagador_boleto_payment_request_builder(
            [], $this->makeOrder(), (object)[], null
        );

        $expiration = new DateTime($result['ExpirationDate']);
        $today      = new DateTime('today');

        $this->assertGreaterThanOrEqual(1, (int) $today->diff($expiration)->days);
    }
}
