<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Braspag_API class.
 *
 * Communicates with Braspag API.
 */
class WC_Braspag_Pagador_API
{

    /**
     *
     */
    const PRODUCTION_ENDPOINT = 'https://api.braspag.com.br/';
    const SANDBOX_ENDPOINT = 'https://apisandbox.braspag.com.br/';
    const BRASPAG_API_VERSION = '2020-02-10';


    /**
     * @param $request
     * @return mixed|void
     */
    public static function get_headers($request)
    {
        $requestId = self::get_request_id();

        return apply_filters(
            'wc_braspag_request_headers',
            array(
                'Content-Type' => "application/json",
                'MerchantId' => $request['merchant_id'],
                'MerchantKey' => $request['merchant_key'],
                'RequestId' => $requestId,
            )
        );
    }

    /**
     * @return false|string
     */
    public static function get_request_id()
    {
        return substr(base64_encode(gethostname()), 0, 36);
    }

    /**
     * @param $request
     * @param string $api
     * @param string $method
     * @param bool $with_headers
     * @return array|object
     * @throws WC_Braspag_Exception
     */
    public static function request($request, $api = 'v2/sales/', $method = 'POST', $with_headers = false)
    {
        $headers = self::get_headers($request);

        $end_point = 'yes' === $request['test_mode'] ? self::SANDBOX_ENDPOINT : self::PRODUCTION_ENDPOINT;

        WC_Braspag_Logger::log($end_point . $api . " {$method} request: " . print_r(['headers' => $headers, 'body' => $request['body']], true));

        $requestOptions = array(
            'method' => $method,
            'headers' => $headers,
            'body' => json_encode(apply_filters('wc_braspag_request_body', $request['body'], $api)),
            'timeout' => 60,
        );

        $response = wp_safe_remote_request(
            $end_point . $api,
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
     * @param $request
     * @param string $api
     * @param string $method
     * @param bool $with_headers
     * @return array|object
     * @throws WC_Braspag_Exception
     */
    public static function request_action($request, $api = 'v2/sales/', $method = 'PUT', $with_headers = false)
    {
        $headers = self::get_headers($request);

        $end_point = 'yes' === $request['test_mode'] ? self::SANDBOX_ENDPOINT : self::PRODUCTION_ENDPOINT;

        WC_Braspag_Logger::log($end_point . $api . " {$method} request: " . print_r(['headers' => $headers, 'body' => $request['body']], true));

        $requestOptions = array(
            'method' => $method,
            'headers' => $headers,
            'body' => json_encode($request['body']),
            'timeout' => 60,
        );

        $response = wp_safe_remote_request(
            $end_point . $api,
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