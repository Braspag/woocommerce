<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/stubs/woocommerce-stubs.php';
require_once dirname(__DIR__, 3) . '/includes/class-wc-braspag-oauth-api.php';

/**
 * Testa WC_Braspag_OAuth_API — get_headers (Verify), get_headers_sop (SOP),
 * get_authorization e prepare_response.
 */
class OAuthApiTest extends TestCase
{
    // ── get_authorization ─────────────────────────────────────────────────────

    public function test_get_authorization_retorna_base64_correto(): void
    {
        $result = WC_Braspag_OAuth_API::get_authorization('oauth_id', 'oauth_secret');
        $this->assertSame(base64_encode('oauth_id:oauth_secret'), $result);
    }

    // ── get_headers (Verify) ──────────────────────────────────────────────────

    public function test_get_headers_verify_retorna_authorization_com_oauth_credentials(): void
    {
        $request = [
            'oauth_authentication_client_id'     => 'verify-id',
            'oauth_authentication_client_secret' => 'verify-secret',
        ];

        $headers = WC_Braspag_OAuth_API::get_headers($request);

        $expectedAuth = 'Basic ' . base64_encode('verify-id:verify-secret');
        $this->assertSame($expectedAuth, $headers['Authorization']);
        $this->assertStringContainsString('application/x-www-form-urlencoded', $headers['Content-Type']);
    }

    public function test_get_headers_verify_lanca_excecao_sem_client_id(): void
    {
        $this->expectException(WC_Braspag_Exception::class);

        WC_Braspag_OAuth_API::get_headers([
            'oauth_authentication_client_secret' => 'secret',
        ]);
    }

    public function test_get_headers_verify_lanca_excecao_sem_client_secret(): void
    {
        $this->expectException(WC_Braspag_Exception::class);

        WC_Braspag_OAuth_API::get_headers([
            'oauth_authentication_client_id' => 'id',
        ]);
    }

    public function test_get_headers_verify_lanca_excecao_com_request_vazio(): void
    {
        $this->expectException(WC_Braspag_Exception::class);

        WC_Braspag_OAuth_API::get_headers([]);
    }

    // ── get_headers_sop (SOP) ─────────────────────────────────────────────────

    public function test_get_headers_sop_usa_silentpost_credentials(): void
    {
        $request = [
            'silentpost_oauth_client_id'     => 'sop-id',
            'silentpost_oauth_client_secret' => 'sop-secret',
        ];

        $headers = WC_Braspag_OAuth_API::get_headers_sop($request);

        $expectedAuth = 'Basic ' . base64_encode('sop-id:sop-secret');
        $this->assertSame($expectedAuth, $headers['Authorization']);
    }

    public function test_get_headers_sop_lanca_excecao_sem_client_id(): void
    {
        $this->expectException(WC_Braspag_Exception::class);

        WC_Braspag_OAuth_API::get_headers_sop([
            'silentpost_oauth_client_secret' => 'secret',
        ]);
    }

    public function test_get_headers_sop_lanca_excecao_sem_client_secret(): void
    {
        $this->expectException(WC_Braspag_Exception::class);

        WC_Braspag_OAuth_API::get_headers_sop([
            'silentpost_oauth_client_id' => 'id',
        ]);
    }

    // ── SOP vs Verify usam credenciais distintas ──────────────────────────────

    public function test_sop_e_verify_usam_credenciais_distintas(): void
    {
        $requestSop = [
            'silentpost_oauth_client_id'     => 'sop-id',
            'silentpost_oauth_client_secret' => 'sop-secret',
        ];
        $requestVerify = [
            'oauth_authentication_client_id'     => 'verify-id',
            'oauth_authentication_client_secret' => 'verify-secret',
        ];

        $headersSop    = WC_Braspag_OAuth_API::get_headers_sop($requestSop);
        $headersVerify = WC_Braspag_OAuth_API::get_headers($requestVerify);

        $this->assertNotSame($headersSop['Authorization'], $headersVerify['Authorization']);
    }

    // ── prepare_response ──────────────────────────────────────────────────────

    public function test_prepare_response_status_200_retorna_body(): void
    {
        $response = [
            'body'     => json_encode(['access_token' => 'tok-xyz']),
            'response' => ['code' => 200, 'message' => 'OK'],
        ];

        $result = WC_Braspag_OAuth_API::prepare_response($response);

        $this->assertSame(200, $result->status);
        $this->assertSame('tok-xyz', $result->body->access_token);
    }

    public function test_prepare_response_status_400_move_body_para_errors(): void
    {
        $response = [
            'body'     => json_encode(['error' => 'invalid_client']),
            'response' => ['code' => 400, 'message' => 'Bad Request'],
        ];

        $result = WC_Braspag_OAuth_API::prepare_response($response);

        $this->assertNull($result->body);
        $this->assertNotNull($result->errors);
        $this->assertSame('invalid_client', $result->errors->error);
    }

    public function test_prepare_response_status_401_move_body_para_errors(): void
    {
        $response = [
            'body'     => json_encode(['error' => 'unauthorized']),
            'response' => ['code' => 401, 'message' => 'Unauthorized'],
        ];

        $result = WC_Braspag_OAuth_API::prepare_response($response);

        $this->assertNull($result->body);
        $this->assertNotNull($result->errors);
    }

    // ── endpoints ─────────────────────────────────────────────────────────────

    public function test_sandbox_endpoint_constante(): void
    {
        $this->assertSame('https://authsandbox.braspag.com.br/', WC_Braspag_OAuth_API::SANDBOX_ENDPOINT);
    }

    public function test_production_endpoint_constante(): void
    {
        $this->assertSame('https://auth.braspag.com.br/', WC_Braspag_OAuth_API::PRODUCTION_ENDPOINT);
    }
}
