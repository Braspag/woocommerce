<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WC_Braspag_Pagador_API_Query
 */
class WC_Braspag_Pagador_API_Query
{
    const PRODUCTION_ENDPOINT = 'https://apiquery.braspag.com.br/';
    const SANDBOX_ENDPOINT = 'https://apiquerysandbox.braspag.com.br/';
    const BRASPAG_API_VERSION = '2020-02-10';

    /**
     * @param $braspag_settings
     * @return mixed|void
     */
    public static function get_headers($braspag_settings)
    {
        $requestId = self::get_request_id();

        return apply_filters(
            'wc_braspag_request_headers',
            array(
                'Content-Type' => "application/json",
                'MerchantId' => $braspag_settings['merchant_id'],
                'MerchantKey' => $braspag_settings['merchant_key'],
                'RequestId' => $requestId,
            )
        );
    }

    /**
     * @return false|string
     */
    public function get_request_id()
    {
        return substr(base64_encode(gethostname()), 0, 36);
    }

    /**
     * @param $paymentId
     * @return array|mixed
     * @throws WC_Braspag_Exception
     */
    public static function requestByPaymentId($paymentId)
    {
        return self::request([], 'v2/sales/' . $paymentId);
    }

    /**
     * @param $orderId
     * @return array|mixed
     * @throws WC_Braspag_Exception
     */
    public static function requestByOrderId($orderId)
    {
        return self::request([], 'v2/sales?merchantOrderId=' . $orderId);
    }

    /**
     * @param $request
     * @param string $api
     * @param string $method
     * @param bool $with_headers
     * @return array|object
     * @throws WC_Braspag_Exception
     */
    public static function request($request, $api = 'v2/sales/', $method = 'GET', $with_headers = false)
    {
        $braspag_settings = get_option('woocommerce_braspag_settings');

        WC_Braspag_Logger::log("{$api} request: " . print_r($request, true));

        $headers = self::get_headers($braspag_settings);

        $end_point = 'yes' === $braspag_settings['test_mode'] ? self::SANDBOX_ENDPOINT : self::PRODUCTION_ENDPOINT;

        $requestOptions = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => 60,
        );

        $response = wp_safe_remote_request(
            $end_point . $api . $request['PaymentId'],
            $requestOptions
        );

        if (is_wp_error($response) || empty($response['body'])) {
            WC_Braspag_Logger::log(
                'Error Response: ' . print_r($response, true) . PHP_EOL . PHP_EOL . 'Failed request: ' . print_r(
                    array(
                        'api' => $api,
                        'request' => $request
                    ),
                    true
                )
            );

            throw new WC_Braspag_Exception(print_r($response, true), __('There was a problem connecting to the Braspag API endpoint.', 'woocommerce-braspag'));
        }

        if ($with_headers) {
            return array(
                'headers' => wp_remote_retrieve_headers($response),
                'body' => json_decode($response['body']),
            );
        }

        return self::prepare_response($response);
    }

    /**
     * @param $response
     * @return object
     */
    public static function prepare_response($response)
    {
        $response_data = [];
        if (isset($response['body'])) {

            $response_body = $response['body'];

            if (is_string($response_body)) {
                $response_body = json_decode($response_body);
            }

            $response_data['body'] = $response_body;
        }

        if (isset($response['response'])) {
            $response_data['status'] = $response['response']['code'];
            $response_data['message'] = $response['response']['message'];
        }

        if ($response_data['status'] != '200' && $response_data['status'] != '201') {
            $response_data['errors'] = $response_data['body'];
            $response_data['body'] = null;
        }

        return (object) $response_data;
    }
}