<?php

use PHPUnit\Framework\TestCase;

/**
 * Testa o builder de payload do WC_Gateway_Braspag_DebitCard.
 */
class DebitCardPayloadBuilderTest extends TestCase
{
    /** @var WC_Gateway_Braspag_DebitCard&\PHPUnit\Framework\MockObject\MockObject */
    private $gateway;

    protected function setUp(): void
    {
        $this->gateway = $this->getMockBuilder(WC_Gateway_Braspag_DebitCard::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_braspag_payment_provider'])
            ->getMock();

        $this->gateway->method('get_braspag_payment_provider')->willReturn('Cielo30');

        $this->setProperty('soft_descriptor', '');
        $this->setProperty('bank_return_url', 'https://example.com/return/%s');
        $this->setProperty('extra_data_collection', []);
        $this->setProperty('auth3ds20_mpi_is_active', false);
        $this->setProperty('test_mode', 'yes');
    }

    private function setProperty(string $name, mixed $value): void
    {
        $ref  = new ReflectionClass($this->gateway);
        $prop = $ref->getProperty($name);
        $prop->setAccessible(true);
        $prop->setValue($this->gateway, $value);
    }

    private function makeOrder(float $total = 100.00, int $id = 42): object
    {
        $order = $this->createMock(WC_Order::class);
        $order->method('get_total')->willReturn($total);
        $order->method('get_id')->willReturn($id);
        return $order;
    }

    private function makeCheckout(array $values): object
    {
        return new class($values) {
            private array $v;
            public function __construct(array $v) { $this->v = $v; }
            public function get_value(string $key): string { return $this->v[$key] ?? ''; }
        };
    }

    private function defaultCheckout(): object
    {
        return $this->makeCheckout([
            'braspag_debitcard-card-number' => '4000000000002701',
            'braspag_debitcard-card-holder' => 'TESTE BRASPAG',
            'braspag_debitcard-card-expiry' => '12/30',
            'braspag_debitcard-card-cvc'    => '123',
            'braspag_debitcard-card-type'   => 'Visa',
        ]);
    }

    public function test_payload_type_is_debitcard()
    {
        $result = $this->gateway->braspag_pagador_debitcard_payment_request_builder(
            [], $this->makeOrder(), $this->defaultCheckout(), null
        );

        $this->assertEquals('DebitCard', $result['Type']);
    }

    public function test_payload_amount_converted_to_cents()
    {
        $result = $this->gateway->braspag_pagador_debitcard_payment_request_builder(
            [], $this->makeOrder(99.90), $this->defaultCheckout(), null
        );

        $this->assertEquals(9990, $result['Amount']);
    }

    public function test_payload_return_url_contains_order_id()
    {
        $result = $this->gateway->braspag_pagador_debitcard_payment_request_builder(
            [], $this->makeOrder(10.00, 77), $this->defaultCheckout(), null
        );

        $this->assertStringContainsString('77', $result['ReturnUrl']);
    }

    public function test_payload_authenticate_false_when_3ds_inactive()
    {
        $result = $this->gateway->braspag_pagador_debitcard_payment_request_builder(
            [], $this->makeOrder(), $this->defaultCheckout(), null
        );

        $this->assertFalse($result['Authenticate']);
    }

    public function test_payload_authenticate_true_when_3ds_active()
    {
        $this->setProperty('auth3ds20_mpi_is_active', true);

        $result = $this->gateway->braspag_pagador_debitcard_payment_request_builder(
            [], $this->makeOrder(), $this->defaultCheckout(), null
        );

        $this->assertTrue($result['Authenticate']);
    }

    public function test_payload_debitcard_brand_from_checkout()
    {
        $result = $this->gateway->braspag_pagador_debitcard_payment_request_builder(
            [], $this->makeOrder(), $this->defaultCheckout(), null
        );

        $this->assertEquals('Visa', $result['DebitCard']['Brand']);
    }

    public function test_payload_partner_is_woo()
    {
        $result = $this->gateway->braspag_pagador_debitcard_payment_request_builder(
            [], $this->makeOrder(), $this->defaultCheckout(), null
        );

        $this->assertEquals('WOO', $result['Partner']);
    }

    public function test_payload_currency_and_installments()
    {
        $result = $this->gateway->braspag_pagador_debitcard_payment_request_builder(
            [], $this->makeOrder(), $this->defaultCheckout(), null
        );

        $this->assertEquals('BRL', $result['Currency']);
        $this->assertEquals('1', $result['Installments']);
    }

    public function test_payload_expiry_short_format_expanded()
    {
        // "12/30" deve virar "12/2030"
        $result = $this->gateway->braspag_pagador_debitcard_payment_request_builder(
            [], $this->makeOrder(), $this->defaultCheckout(), null
        );

        $this->assertEquals('12/2030', $result['DebitCard']['ExpirationDate']);
    }
}
