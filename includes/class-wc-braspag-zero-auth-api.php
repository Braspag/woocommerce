<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Braspag_Zero_Auth_API class.
 *
 * Communicates with the Braspag VerifyCard (ZeroAuth) API server-side, so the
 * PAN and merchant credentials never need to be exposed in the browser.
 */
class WC_Braspag_Zero_Auth_API
{
    const PRODUCTION_ENDPOINT = 'https://api.braspag.com.br/v2/zeroauth';
    const SANDBOX_ENDPOINT = 'https://apisandbox.braspag.com.br/v2/zeroauth';

    /**
     * Amex returns error 57 from Cielo when submitted to ZeroAuth and must be
     * skipped gracefully (ADR-005).
     */
    const UNSUPPORTED_BRANDS = ['Amex'];

    /**
     * @param string $brand
     * @return bool
     */
    public static function brand_supported($brand)
    {
        return !in_array($brand, self::UNSUPPORTED_BRANDS, true);
    }

    /**
     * @param object $response
     * @return bool
     */
    public static function is_valid($response)
    {
        return isset($response->Valid) && true === $response->Valid;
    }

    /**
     * Validates a raw card (PAN) against the Braspag ZeroAuth API.
     *
     * @param array $request {
     *     @type string $merchant_id
     *     @type string $merchant_key
     *     @type string $test_mode
     *     @type string $card_number
     *     @type string $card_holder
     *     @type string $card_expiration_date
     *     @type string $card_security_code
     *     @type string $brand
     * }
     * @return object
     * @throws WC_Braspag_Exception
     */
    public static function validate_pan(array $request)
    {
        return self::request($request, [
            'CardNumber' => $request['card_number'],
            'Holder' => $request['card_holder'],
            'ExpirationDate' => $request['card_expiration_date'],
            'SecurityCode' => $request['card_security_code'],
            'Brand' => $request['brand'],
        ]);
    }

    /**
     * Validates an already tokenized card against the Braspag ZeroAuth API.
     *
     * @param array $request {
     *     @type string $merchant_id
     *     @type string $merchant_key
     *     @type string $test_mode
     *     @type string $card_token
     *     @type string $card_security_code
     *     @type string $brand
     * }
     * @return object
     * @throws WC_Braspag_Exception
     */
    public static function validate_token(array $request)
    {
        return self::request($request, [
            'CardToken' => $request['card_token'],
            'SecurityCode' => $request['card_security_code'],
            'Brand' => $request['brand'],
        ]);
    }

    /**
     * @param array $request
     * @param array $body
     * @return object
     * @throws WC_Braspag_Exception
     */
    private static function request(array $request, array $body)
    {
        $end_point = 'yes' === $request['test_mode'] ? self::SANDBOX_ENDPOINT : self::PRODUCTION_ENDPOINT;

        $headers = [
            'Content-Type' => 'application/json',
            'MerchantId' => $request['merchant_id'],
            'MerchantKey' => $request['merchant_key'],
            'RequestId' => wp_generate_uuid4(),
        ];

        WC_Braspag_Logger::log('ZeroAuth request: ' . print_r($body, true));

        $response = wp_safe_remote_post(
            $end_point,
            [
                'method' => 'POST',
                'headers' => $headers,
                'body' => json_encode($body),
                'timeout' => 30,
            ]
        );

        if (is_wp_error($response) || empty($response['body'])) {
            WC_Braspag_Logger::log('ZeroAuth error response: ' . print_r($response, true));

            throw new WC_Braspag_Exception(
                is_wp_error($response) ? $response->get_error_message() : 'Empty ZeroAuth response',
                __('Não foi possível validar o cartão informado.', 'woocommerce-braspag')
            );
        }

        return json_decode($response['body']);
    }
}
