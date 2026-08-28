<?php

use PHPUnit\Framework\TestCase;

/**
 * Testa o suporte ao ChangeType 25 (reversão parcial) no webhook handler.
 *
 * Cobre:
 *  - is_valid_request() aceita ChangeType 25
 *  - process_webhook() processa ChangeType 25 via process_change_type_status_update()
 */
class WebhookChangeType25Test extends TestCase
{
    private WC_Braspag_Webhook_Handler $handler;

    protected function setUp(): void
    {
        $GLOBALS['_braspag_test_options'] = [];
        $this->handler = new WC_Braspag_Webhook_Handler();
    }

    protected function tearDown(): void
    {
        $GLOBALS['_braspag_test_options'] = [];
    }

    private function buildHeaders(array $extra = []): array
    {
        return array_merge(['CONTENT-TYPE' => 'application/json'], $extra);
    }

    // ── Validação ─────────────────────────────────────────────────────────────

    public function test_is_valid_request_aceita_change_type_25(): void
    {
        $body = ['PaymentId' => 'abc-123', 'ChangeType' => '25'];
        $raw  = json_encode($body);

        $this->assertTrue(
            $this->handler->is_valid_request($this->buildHeaders(), $body, $raw),
            'ChangeType 25 deve ser aceito pelo is_valid_request()'
        );
    }

    public function test_change_types_invalidos_ainda_sao_rejeitados(): void
    {
        foreach (['26', '24', '0', '99'] as $invalid) {
            $body = ['PaymentId' => 'abc-123', 'ChangeType' => $invalid];
            $raw  = json_encode($body);

            $this->assertFalse(
                $this->handler->is_valid_request($this->buildHeaders(), $body, $raw),
                "ChangeType '$invalid' deve continuar sendo rejeitado"
            );
        }
    }

    // ── process_webhook() com mock ────────────────────────────────────────────

    public function test_process_webhook_change_type_25_chama_status_update(): void
    {
        $called = false;

        $handler = $this->getMockBuilder(WC_Braspag_Webhook_Handler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['process_change_type_status_update'])
            ->getMock();

        $handler->expects($this->once())
            ->method('process_change_type_status_update')
            ->with('pid-partial-refund')
            ->willReturn(null);

        $body = ['PaymentId' => 'pid-partial-refund', 'ChangeType' => '25'];
        $handler->process_webhook($body);

        // Se chegou aqui sem exceção, o case 25 foi processado corretamente
        $this->assertTrue(true);
    }

    public function test_process_webhook_change_type_25_nao_lanca_excecao(): void
    {
        $handler = $this->getMockBuilder(WC_Braspag_Webhook_Handler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['process_change_type_status_update'])
            ->getMock();

        $handler->method('process_change_type_status_update')->willReturn(true);

        $body = ['PaymentId' => 'pid-test', 'ChangeType' => '25'];

        $this->expectNotToPerformAssertions();

        try {
            $handler->process_webhook($body);
        } catch (WC_Braspag_Exception $e) {
            $this->fail('ChangeType 25 não deve lançar WC_Braspag_Exception: ' . $e->getMessage());
        }
    }
}
