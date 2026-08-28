<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/stubs/woocommerce-stubs.php';
require_once dirname(__DIR__, 3) . '/includes/class-wc-braspag-mpi-api.php';

/**
 * Testa WC_Braspag_Mpi_API — métodos estáticos puros, sem HTTP real.
 */
class MpiApiTest extends TestCase
{
    // ── get_authorization ─────────────────────────────────────────────────────

    public function test_get_authorization_retorna_base64_correto(): void
    {
        $result = WC_Braspag_Mpi_API::get_authorization('client_id_test', 'client_secret_test');
        $this->assertSame(base64_encode('client_id_test:client_secret_test'), $result);
    }

    public function test_get_authorization_com_valores_vazios(): void
    {
        $result = WC_Braspag_Mpi_API::get_authorization('', '');
        $this->assertSame(base64_encode(':'), $result);
    }

    // ── get_headers ───────────────────────────────────────────────────────────

    public function test_get_headers_retorna_content_type_e_authorization(): void
    {
        $request = [
            'auth3ds20_oauth_authentication_client_id'     => 'id-3ds',
            'auth3ds20_oauth_authentication_client_secret' => 'secret-3ds',
        ];

        $headers = WC_Braspag_Mpi_API::get_headers($request);

        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertStringContainsString('Basic ', $headers['Authorization']);
        $expectedAuth = base64_encode('id-3ds:secret-3ds');
        $this->assertSame('Basic ' . $expectedAuth, $headers['Authorization']);
    }

    public function test_get_headers_lanca_excecao_sem_client_id(): void
    {
        $this->expectException(WC_Braspag_Exception::class);

        WC_Braspag_Mpi_API::get_headers([
            'auth3ds20_oauth_authentication_client_secret' => 'secret',
        ]);
    }

    public function test_get_headers_lanca_excecao_sem_client_secret(): void
    {
        $this->expectException(WC_Braspag_Exception::class);

        WC_Braspag_Mpi_API::get_headers([
            'auth3ds20_oauth_authentication_client_id' => 'id',
        ]);
    }

    public function test_get_headers_lanca_excecao_com_request_vazio(): void
    {
        $this->expectException(WC_Braspag_Exception::class);

        WC_Braspag_Mpi_API::get_headers([]);
    }

    // ── prepare_response ──────────────────────────────────────────────────────

    public function test_prepare_response_status_200_retorna_body(): void
    {
        $response = [
            'body'     => json_encode(['access_token' => 'tok-abc', 'token_type' => 'Bearer']),
            'response' => ['code' => 200, 'message' => 'OK'],
        ];

        $result = WC_Braspag_Mpi_API::prepare_response($response);

        $this->assertSame(200, $result->status);
        $this->assertNotNull($result->body);
        $this->assertSame('tok-abc', $result->body->access_token);
    }

    public function test_prepare_response_status_201_retorna_body(): void
    {
        $response = [
            'body'     => json_encode(['id' => 'txn-001']),
            'response' => ['code' => 201, 'message' => 'Created'],
        ];

        $result = WC_Braspag_Mpi_API::prepare_response($response);

        $this->assertSame(201, $result->status);
        $this->assertNotNull($result->body);
    }

    public function test_prepare_response_status_400_move_body_para_errors(): void
    {
        $response = [
            'body'     => json_encode(['message' => 'Bad Request']),
            'response' => ['code' => 400, 'message' => 'Bad Request'],
        ];

        $result = WC_Braspag_Mpi_API::prepare_response($response);

        $this->assertSame(400, $result->status);
        $this->assertNull($result->body);
        $this->assertNotNull($result->errors);
    }

    public function test_prepare_response_status_401_move_body_para_errors(): void
    {
        $response = [
            'body'     => json_encode(['message' => 'Unauthorized']),
            'response' => ['code' => 401, 'message' => 'Unauthorized'],
        ];

        $result = WC_Braspag_Mpi_API::prepare_response($response);

        $this->assertNull($result->body);
        $this->assertNotNull($result->errors);
    }

    public function test_prepare_response_body_string_json_decodificado(): void
    {
        $response = [
            'body'     => '{"foo":"bar"}',
            'response' => ['code' => 200, 'message' => 'OK'],
        ];

        $result = WC_Braspag_Mpi_API::prepare_response($response);
        $this->assertSame('bar', $result->body->foo);
    }

    // ── endpoints (determinado por test_mode no método request) ──────────────

    public function test_sandbox_endpoint_constante(): void
    {
        $this->assertSame('https://mpisandbox.braspag.com.br/', WC_Braspag_Mpi_API::SANDBOX_ENDPOINT);
    }

    public function test_production_endpoint_constante(): void
    {
        $this->assertSame('https://mpi.braspag.com.br/', WC_Braspag_Mpi_API::PRODUCTION_ENDPOINT);
    }
}
