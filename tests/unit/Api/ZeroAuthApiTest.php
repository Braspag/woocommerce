<?php

use PHPUnit\Framework\TestCase;

class ZeroAuthApiTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['_braspag_test_http_handler']);
    }

    private function base_request(array $overrides = []): array
    {
        return array_merge([
            'merchant_id'  => 'test-merchant-id',
            'merchant_key' => 'test-merchant-key',
            'test_mode'    => 'yes',
        ], $overrides);
    }

    private function mock_http(callable $handler): void
    {
        $GLOBALS['_braspag_test_http_handler'] = $handler;
    }

    // ── brand_supported ───────────────────────────────────────────────────────

    public function test_brand_supported_returns_true_for_visa()
    {
        $this->assertTrue(WC_Braspag_Zero_Auth_API::brand_supported('Visa'));
    }

    public function test_brand_supported_returns_true_for_master()
    {
        $this->assertTrue(WC_Braspag_Zero_Auth_API::brand_supported('Master'));
    }

    public function test_brand_supported_returns_true_for_elo()
    {
        $this->assertTrue(WC_Braspag_Zero_Auth_API::brand_supported('Elo'));
    }

    public function test_brand_supported_returns_false_for_amex()
    {
        // Amex returns error 57 from Cielo — must be skipped gracefully (ADR-005)
        $this->assertFalse(WC_Braspag_Zero_Auth_API::brand_supported('Amex'));
    }

    // ── is_valid ──────────────────────────────────────────────────────────────

    public function test_is_valid_returns_true_when_valid_true()
    {
        $response = (object) ['Valid' => true, 'ReturnCode' => '00'];
        $this->assertTrue(WC_Braspag_Zero_Auth_API::is_valid($response));
    }

    public function test_is_valid_returns_false_when_valid_false()
    {
        $response = (object) ['Valid' => false, 'ReturnCode' => '57'];
        $this->assertFalse(WC_Braspag_Zero_Auth_API::is_valid($response));
    }

    public function test_is_valid_returns_false_when_field_missing()
    {
        $this->assertFalse(WC_Braspag_Zero_Auth_API::is_valid((object) []));
    }

    // ── validate_pan ──────────────────────────────────────────────────────────

    public function test_validate_pan_hits_sandbox_endpoint()
    {
        $captured = [];
        $this->mock_http(function ($url, $args) use (&$captured) {
            $captured = ['url' => $url, 'body' => json_decode($args['body'], true)];
            return ['response' => ['code' => 200], 'body' => json_encode(['Valid' => true, 'ReturnCode' => '00'])];
        });

        $response = WC_Braspag_Zero_Auth_API::validate_pan($this->base_request([
            'card_number'          => '4000000000002701',
            'card_holder'          => 'TESTE BRASPAG',
            'card_expiration_date' => '12/2030',
            'card_security_code'   => '123',
            'brand'                => 'Visa',
        ]));

        $this->assertStringContainsString('zeroauth', $captured['url']);
        $this->assertStringContainsString('apisandbox', $captured['url']);
        $this->assertEquals('4000000000002701', $captured['body']['CardNumber']);
        $this->assertEquals('Visa', $captured['body']['Brand']);
        $this->assertTrue($response->Valid);
    }

    public function test_validate_pan_hits_production_when_test_mode_no()
    {
        $captured_url = '';
        $this->mock_http(function ($url) use (&$captured_url) {
            $captured_url = $url;
            return ['response' => ['code' => 200], 'body' => json_encode(['Valid' => false, 'ReturnCode' => '57'])];
        });

        WC_Braspag_Zero_Auth_API::validate_pan($this->base_request([
            'test_mode'            => 'no',
            'card_number'          => '4111111111111111',
            'card_holder'          => 'TESTE',
            'card_expiration_date' => '12/2030',
            'card_security_code'   => '123',
            'brand'                => 'Visa',
        ]));

        $this->assertStringContainsString('api.braspag.com.br', $captured_url);
        $this->assertStringNotContainsString('sandbox', $captured_url);
    }

    // ── validate_token ────────────────────────────────────────────────────────

    public function test_validate_token_sends_card_token_not_pan()
    {
        $captured = [];
        $this->mock_http(function ($url, $args) use (&$captured) {
            $captured = json_decode($args['body'], true);
            return ['response' => ['code' => 200], 'body' => json_encode(['Valid' => true, 'ReturnCode' => '00'])];
        });

        WC_Braspag_Zero_Auth_API::validate_token($this->base_request([
            'card_token'         => 'abc123token',
            'card_security_code' => '123',
            'brand'              => 'Master',
        ]));

        $this->assertEquals('abc123token', $captured['CardToken']);
        $this->assertArrayNotHasKey('CardNumber', $captured);
    }

    // ── error handling ────────────────────────────────────────────────────────

    public function test_api_error_throws_exception()
    {
        $this->mock_http(function () {
            return new WP_Error('http_request_failed', 'Connection refused');
        });

        $this->expectException(WC_Braspag_Exception::class);

        WC_Braspag_Zero_Auth_API::validate_pan($this->base_request([
            'card_number'          => '4111111111111111',
            'card_holder'          => 'TESTE',
            'card_expiration_date' => '12/2030',
            'card_security_code'   => '123',
            'brand'                => 'Visa',
        ]));
    }
}
