<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/stubs/woocommerce-stubs.php';
require_once dirname(__DIR__, 3) . '/includes/class-wc-braspag-risk-api.php';

/**
 * Testa WC_Braspag_Risk_API — endpoint, headers, request_id e prepare_response.
 */
class RiskApiTest extends TestCase
{
    // ── get_end_point ─────────────────────────────────────────────────────────

    public function test_get_end_point_sandbox_quando_test_mode_yes(): void
    {
        $settings = ['test_mode' => 'yes'];
        $this->assertSame(WC_Braspag_Risk_API::SANDBOX_ENDPOINT, WC_Braspag_Risk_API::get_end_point($settings));
    }

    public function test_get_end_point_producao_quando_test_mode_no(): void
    {
        $settings = ['test_mode' => 'no'];
        $this->assertSame(WC_Braspag_Risk_API::PRODUCTION_ENDPOINT, WC_Braspag_Risk_API::get_end_point($settings));
    }

    public function test_get_end_point_producao_quando_test_mode_ausente(): void
    {
        $settings = [];
        $this->assertSame(WC_Braspag_Risk_API::PRODUCTION_ENDPOINT, WC_Braspag_Risk_API::get_end_point($settings));
    }

    // ── get_request_id ────────────────────────────────────────────────────────

    public function test_get_request_id_retorna_string(): void
    {
        $id = WC_Braspag_Risk_API::get_request_id();
        $this->assertIsString($id);
    }

    public function test_get_request_id_maximo_36_caracteres(): void
    {
        $id = WC_Braspag_Risk_API::get_request_id();
        $this->assertLessThanOrEqual(36, strlen($id));
    }

    public function test_get_request_id_nao_vazio(): void
    {
        $id = WC_Braspag_Risk_API::get_request_id();
        $this->assertNotEmpty($id);
    }

    // ── get_headers ───────────────────────────────────────────────────────────

    public function test_get_headers_inclui_bearer_token(): void
    {
        $settings = ['merchant_id' => 'merch-001', 'test_mode' => 'yes'];
        $token    = 'access-token-xyz';

        $headers = WC_Braspag_Risk_API::get_headers($settings, $token);

        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertSame('Bearer ' . $token, $headers['Authorization']);
    }

    public function test_get_headers_inclui_merchant_id(): void
    {
        $settings = ['merchant_id' => 'merch-001', 'test_mode' => 'yes'];

        $headers = WC_Braspag_Risk_API::get_headers($settings, 'tok');

        $this->assertArrayHasKey('MerchantId', $headers);
        $this->assertSame('merch-001', $headers['MerchantId']);
    }

    public function test_get_headers_inclui_request_id(): void
    {
        $settings = ['merchant_id' => 'merch-001', 'test_mode' => 'yes'];

        $headers = WC_Braspag_Risk_API::get_headers($settings, 'tok');

        $this->assertArrayHasKey('RequestId', $headers);
        $this->assertNotEmpty($headers['RequestId']);
    }

    public function test_get_headers_content_type_json(): void
    {
        $settings = ['merchant_id' => 'merch-001', 'test_mode' => 'yes'];

        $headers = WC_Braspag_Risk_API::get_headers($settings, 'tok');

        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertSame('application/json', $headers['Content-Type']);
    }

    // ── prepare_response ──────────────────────────────────────────────────────

    public function test_prepare_response_status_200_retorna_body(): void
    {
        $response = [
            'body'     => json_encode(['Status' => 'Accept', 'Score' => 10]),
            'response' => ['code' => 200, 'message' => 'OK'],
        ];

        $result = WC_Braspag_Risk_API::prepare_response($response);

        $this->assertSame(200, $result->status);
        $this->assertNotNull($result->body);
        $this->assertSame('Accept', $result->body->Status);
    }

    public function test_prepare_response_status_201_retorna_body(): void
    {
        $response = [
            'body'     => json_encode(['Status' => 'Accept']),
            'response' => ['code' => 201, 'message' => 'Created'],
        ];

        $result = WC_Braspag_Risk_API::prepare_response($response);

        $this->assertSame(201, $result->status);
        $this->assertNotNull($result->body);
    }

    public function test_prepare_response_status_reject_move_body_para_errors(): void
    {
        $response = [
            'body'     => json_encode(['Status' => 'Reject', 'Score' => 90]),
            'response' => ['code' => 403, 'message' => 'Forbidden'],
        ];

        $result = WC_Braspag_Risk_API::prepare_response($response);

        $this->assertNull($result->body);
        $this->assertNotNull($result->errors);
        $this->assertSame('Reject', $result->errors->Status);
    }

    public function test_prepare_response_status_review_move_body_para_errors(): void
    {
        $response = [
            'body'     => json_encode(['Status' => 'Review', 'Score' => 60]),
            'response' => ['code' => 202, 'message' => 'Accepted'],
        ];

        // 202 não está em [200, 201] → errors
        $result = WC_Braspag_Risk_API::prepare_response($response);

        $this->assertNull($result->body);
        $this->assertNotNull($result->errors);
    }

    public function test_prepare_response_sem_body_nao_gera_erro(): void
    {
        $response = [
            'response' => ['code' => 200, 'message' => 'OK'],
        ];

        $result = WC_Braspag_Risk_API::prepare_response($response);

        $this->assertSame(200, $result->status);
    }

    // ── endpoints ─────────────────────────────────────────────────────────────

    public function test_sandbox_endpoint_constante(): void
    {
        $this->assertSame('https://risksandbox.braspag.com.br/', WC_Braspag_Risk_API::SANDBOX_ENDPOINT);
    }

    public function test_production_endpoint_constante(): void
    {
        $this->assertSame('https://risk.braspag.com.br/', WC_Braspag_Risk_API::PRODUCTION_ENDPOINT);
    }
}
