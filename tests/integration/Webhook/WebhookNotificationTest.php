<?php

/**
 * Testes de integração para WC_Braspag_Webhook_Handler.
 *
 * Requerem ambiente WordPress + WooCommerce.
 *
 * @group integration
 */
class WebhookNotificationTest extends WP_UnitTestCase
{
    private WC_Braspag_Webhook_Handler $handler;

    public function setUp(): void
    {
        parent::setUp();
        update_option('woocommerce_braspag_settings', ['enabled' => 'yes', 'test_mode' => 'yes']);
        $this->handler = new WC_Braspag_Webhook_Handler();
    }

    // ── PIX: pago → processing ────────────────────────────────────────────────

    /**
     * @test
     * Webhook de PIX com Status=2 (PaymentConfirmed) deve mover pedido para 'processing'.
     */
    public function test_webhook_pix_pago_move_para_processing(): void
    {
        $order = WC_Helper_Order::create_order();
        $order->update_status('on-hold');
        $order->update_meta_data('_braspag_charge_id', 'pix-pay-001');
        $order->save();

        $response = (object) [
            'body' => (object) [
                'Payment' => (object) ['Status' => '2', 'PaymentId' => 'pix-pay-001'],
            ],
        ];

        $this->handler->process_change_type_status_update_response($response, $order);

        $this->assertSame('processing', $order->get_status());

        $order->delete(true);
    }

    // ── Boleto: liquidado → processing ───────────────────────────────────────

    /**
     * @test
     * Webhook de boleto liquidado (Status=2) deve mover para 'processing'.
     */
    public function test_webhook_boleto_liquidado_move_para_processing(): void
    {
        $order = WC_Helper_Order::create_order();
        $order->update_status('on-hold');
        $order->save();

        $response = (object) [
            'body' => (object) [
                'Payment' => (object) ['Status' => '2', 'PaymentId' => 'blt-001'],
            ],
        ];

        $this->handler->process_change_type_status_update_response($response, $order);

        $this->assertSame('processing', $order->get_status());

        $order->delete(true);
    }

    // ── Idempotência ──────────────────────────────────────────────────────────

    /**
     * @test
     * Pedido já em 'processing' não deve ter status alterado ao receber webhook duplicado.
     */
    public function test_webhook_idempotente_para_pedido_ja_em_processing(): void
    {
        $order = WC_Helper_Order::create_order();
        $order->update_status('processing');
        $order->save();

        $response = (object) [
            'body' => (object) [
                'Payment' => (object) ['Status' => '2', 'PaymentId' => 'dup-001'],
            ],
        ];

        $this->handler->process_change_type_status_update_response($response, $order);

        // Status não deve ter mudado
        $this->assertSame('processing', $order->get_status());

        $order->delete(true);
    }

    /**
     * @test
     * Pedido em status final ('completed', 'cancelled', 'refunded') deve ser
     * ignorado silenciosamente — retorna true sem lançar exceção.
     */
    public function test_webhook_idempotente_para_status_finais(): void
    {
        foreach (['completed', 'cancelled', 'refunded'] as $finalStatus) {
            $order = WC_Helper_Order::create_order();
            $order->update_status($finalStatus);
            $order->save();

            $response = (object) [
                'body' => (object) [
                    'Payment' => (object) ['Status' => '2', 'PaymentId' => "pid-$finalStatus"],
                ],
            ];

            $result = $this->handler->process_change_type_status_update_response($response, $order);
            $this->assertTrue($result, "Status final '$finalStatus' deve retornar true");
            $this->assertSame($finalStatus, $order->get_status(), "Status não deve ser alterado para '$finalStatus'");

            $order->delete(true);
        }
    }

    // ── Pagamento negado → cancelled ─────────────────────────────────────────

    /**
     * @test
     * Status=3 (Denied) deve mover pedido para 'cancelled'.
     */
    public function test_webhook_pagamento_negado_cancela_pedido(): void
    {
        $order = WC_Helper_Order::create_order();
        $order->update_status('on-hold');
        $order->save();

        $response = (object) [
            'body' => (object) [
                'Payment' => (object) ['Status' => '3', 'PaymentId' => 'denied-001'],
            ],
        ];

        $this->handler->process_change_type_status_update_response($response, $order);

        $this->assertSame('cancelled', $order->get_status());

        $order->delete(true);
    }

    // ── Assinatura inválida → rejeita webhook ─────────────────────────────────

    /**
     * @test
     * Requisição com secret configurado e assinatura errada deve retornar false.
     */
    public function test_webhook_assinatura_invalida_rejeitada(): void
    {
        update_option('woocommerce_braspag_settings', [
            'enabled'        => 'yes',
            'webhook_secret' => 'segredo-teste',
        ]);

        $body    = ['PaymentId' => 'pid-999', 'ChangeType' => '1'];
        $raw     = json_encode($body);
        $headers = ['X-BRASPAG-SIGNATURE' => 'assinatura-errada'];

        $result = $this->handler->is_valid_request($headers, $body, $raw);
        $this->assertFalse($result);
    }
}
