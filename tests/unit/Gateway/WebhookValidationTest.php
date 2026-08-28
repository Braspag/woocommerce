<?php

use PHPUnit\Framework\TestCase;

/**
 * Testa a lógica de validação do WC_Braspag_Webhook_Handler sem WordPress.
 *
 * Cobre: is_valid_request() (estrutura do payload) e validação de assinatura
 * HMAC via is_valid_request() com secret configurado.
 */
class WebhookValidationTest extends TestCase
{
    private WC_Braspag_Webhook_Handler $handler;

    protected function setUp(): void
    {
        $GLOBALS['_braspag_test_options'] = [];
        // O construtor faz get_option() e add_action() — ambos stubbed.
        $this->handler = new WC_Braspag_Webhook_Handler();
    }

    protected function tearDown(): void
    {
        $GLOBALS['_braspag_test_options'] = [];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function validBody(): array
    {
        return ['PaymentId' => 'abc-123', 'ChangeType' => '1'];
    }

    private function validRaw(): string
    {
        return json_encode($this->validBody());
    }

    private function buildHeaders(array $extra = []): array
    {
        return array_merge(['CONTENT-TYPE' => 'application/json'], $extra);
    }

    // ── Validação estrutural ──────────────────────────────────────────────────

    public function test_rejeita_headers_nulos(): void
    {
        $this->assertFalse($this->handler->is_valid_request(null, $this->validBody(), $this->validRaw()));
    }

    public function test_rejeita_body_nulo(): void
    {
        $this->assertFalse($this->handler->is_valid_request($this->buildHeaders(), null, $this->validRaw()));
    }

    public function test_rejeita_raw_body_vazio(): void
    {
        $this->assertFalse($this->handler->is_valid_request($this->buildHeaders(), $this->validBody(), ''));
    }

    public function test_rejeita_sem_payment_id(): void
    {
        $body    = ['ChangeType' => '1'];
        $raw     = json_encode($body);
        $this->assertFalse($this->handler->is_valid_request($this->buildHeaders(), $body, $raw));
    }

    public function test_rejeita_sem_change_type(): void
    {
        $body = ['PaymentId' => 'abc-123'];
        $raw  = json_encode($body);
        $this->assertFalse($this->handler->is_valid_request($this->buildHeaders(), $body, $raw));
    }

    public function test_rejeita_payment_id_vazio(): void
    {
        $body = ['PaymentId' => '', 'ChangeType' => '1'];
        $raw  = json_encode($body);
        $this->assertFalse($this->handler->is_valid_request($this->buildHeaders(), $body, $raw));
    }

    public function test_rejeita_change_type_invalido(): void
    {
        foreach (['0', '9', 'abc', '99', '26'] as $invalid) {
            $body = ['PaymentId' => 'abc-123', 'ChangeType' => $invalid];
            $raw  = json_encode($body);
            $this->assertFalse(
                $this->handler->is_valid_request($this->buildHeaders(), $body, $raw),
                "ChangeType '$invalid' deveria ser rejeitado"
            );
        }
    }

    public function test_aceita_todos_os_change_types_validos(): void
    {
        foreach (['1', '2', '3', '4', '5', '6', '7', '8', '25'] as $type) {
            $body = ['PaymentId' => 'abc-123', 'ChangeType' => $type];
            $raw  = json_encode($body);
            $this->assertTrue(
                $this->handler->is_valid_request($this->buildHeaders(), $body, $raw),
                "ChangeType '$type' deveria ser aceito"
            );
        }
    }

    public function test_rejeita_payment_id_nao_escalar(): void
    {
        $body = ['PaymentId' => ['array'], 'ChangeType' => '1'];
        $raw  = json_encode($body);
        $this->assertFalse($this->handler->is_valid_request($this->buildHeaders(), $body, $raw));
    }

    // ── Assinatura HMAC ───────────────────────────────────────────────────────

    public function test_aceita_requisicao_sem_secret_configurado(): void
    {
        // Sem secret → skip de assinatura → válido
        $this->assertTrue(
            $this->handler->is_valid_request($this->buildHeaders(), $this->validBody(), $this->validRaw())
        );
    }

    public function test_rejeita_quando_secret_configurado_e_header_ausente(): void
    {
        $GLOBALS['_braspag_test_options']['woocommerce_braspag_settings'] = [
            'webhook_secret' => 'meu-segredo',
        ];
        $this->handler = new WC_Braspag_Webhook_Handler();

        $this->assertFalse(
            $this->handler->is_valid_request($this->buildHeaders(), $this->validBody(), $this->validRaw())
        );
    }

    public function test_aceita_assinatura_hmac_hex_valida(): void
    {
        $secret = 'meu-segredo';
        $raw    = $this->validRaw();
        $sig    = hash_hmac('sha256', $raw, $secret);

        $GLOBALS['_braspag_test_options']['woocommerce_braspag_settings'] = [
            'webhook_secret' => $secret,
        ];
        $this->handler = new WC_Braspag_Webhook_Handler();

        $headers = $this->buildHeaders(['X-BRASPAG-SIGNATURE' => $sig]);
        $this->assertTrue(
            $this->handler->is_valid_request($headers, $this->validBody(), $raw)
        );
    }

    public function test_aceita_assinatura_hmac_base64_valida(): void
    {
        $secret  = 'meu-segredo';
        $raw     = $this->validRaw();
        $sigB64  = base64_encode(hex2bin(hash_hmac('sha256', $raw, $secret)));

        $GLOBALS['_braspag_test_options']['woocommerce_braspag_settings'] = [
            'webhook_secret' => $secret,
        ];
        $this->handler = new WC_Braspag_Webhook_Handler();

        $headers = $this->buildHeaders(['X-BRASPAG-SIGNATURE' => $sigB64]);
        $this->assertTrue(
            $this->handler->is_valid_request($headers, $this->validBody(), $raw)
        );
    }

    public function test_rejeita_assinatura_incorreta(): void
    {
        $GLOBALS['_braspag_test_options']['woocommerce_braspag_settings'] = [
            'webhook_secret' => 'segredo-real',
        ];
        $this->handler = new WC_Braspag_Webhook_Handler();

        $headers = $this->buildHeaders(['X-BRASPAG-SIGNATURE' => 'assinatura-invalida']);
        $this->assertFalse(
            $this->handler->is_valid_request($headers, $this->validBody(), $this->validRaw())
        );
    }

    // ── Idempotência de status ────────────────────────────────────────────────

    public function test_process_change_type_status_update_response_ignora_pedido_completed(): void
    {
        $order = $this->createMock(stdClass::class);
        $order->method = function ($method) use ($order) {
            if ($method === 'get_status') return 'completed';
        };

        // Usa objeto simples com get_status() retornando 'completed'
        $orderMock = new class {
            public function get_status(): string   { return 'completed'; }
            public function get_id(): int          { return 1; }
            public function update_status(): void  {}
            public function add_order_note(): void {}
            public function add_meta_data(): void  {}
            public function save(): void           {}
        };

        $response = (object) [
            'body' => (object) [
                'Payment' => (object) ['Status' => '2', 'PaymentId' => 'pid-001'],
            ],
        ];

        $result = $this->handler->process_change_type_status_update_response($response, $orderMock);
        $this->assertTrue($result, 'Pedido em status final deve retornar true sem alterar status');
    }

    public function test_process_change_type_status_update_response_ignora_pedido_cancelled(): void
    {
        $orderMock = new class {
            public function get_status(): string   { return 'cancelled'; }
            public function get_id(): int          { return 2; }
            public function update_status(): void  {}
            public function add_order_note(): void {}
            public function add_meta_data(): void  {}
            public function save(): void           {}
        };

        $response = (object) [
            'body' => (object) [
                'Payment' => (object) ['Status' => '2', 'PaymentId' => 'pid-002'],
            ],
        ];

        $result = $this->handler->process_change_type_status_update_response($response, $orderMock);
        $this->assertTrue($result);
    }

    public function test_process_change_type_status_update_response_atualiza_para_processing(): void
    {
        $statusSet = null;

        $orderMock = new class($statusSet) {
            public ?string $capturedStatus = null;
            public function get_status(): string   { return 'on-hold'; }
            public function get_id(): int          { return 3; }
            public function update_status(string $status, string $note = ''): void {
                $this->capturedStatus = $status;
            }
            public function add_order_note(): void {}
            public function add_meta_data(): void  {}
            public function save(): void           {}
        };

        $response = (object) [
            'body' => (object) [
                'Payment' => (object) ['Status' => '2', 'PaymentId' => 'pid-003'],
            ],
        ];

        $this->handler->process_change_type_status_update_response($response, $orderMock);
        $this->assertSame('processing', $orderMock->capturedStatus);
    }
}
