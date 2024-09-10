<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Braspag_API class.
 *
 * Communicates with Braspag API.
 */
class WC_Braspag_OAuth_API
{

    /**
     *
     */
    const PRODUCTION_ENDPOINT = 'https://auth.braspag.com.br/';
    const SANDBOX_ENDPOINT = 'https://authsandbox.braspag.com.br/';
    const BRASPAG_API_VERSION = '2020-02-10';


/**
     * @param $request
     * @return mixed|void
     * @throws WC_Braspag_Exception
     */
    public static function get_headers_sop($request)
    {
        if (
            !isset($request['sop_authentication_client_id'])
            || !isset($request['sop_authentication_client_secret'])
        ) {
            throw new WC_Braspag_Exception(
                print_r(
                    $request,
                    true
                ),
                __('Invalid Oauth Request Data', 'woocommerce-braspag')
            );
        }

        $authorization = self::get_authorization(
            $request['sop_authentication_client_id'], $request['sop_authentication_client_secret']
        );

        return apply_filters(
            'wc_braspag_request_headers',
            array(
                'Content-Type' => "application/x-www-form-urlencoded; charset=UTF-8",
                'Authorization' => "Basic " . $authorization
            )
        );
    }

    /**
     * @param $request
     * @return mixed|void
     * @throws WC_Braspag_Exception
     */
    public static function get_headers($request)
    {
        if (
            !isset($request['oauth_authentication_client_id'])
            || !isset($request['oauth_authentication_client_secret'])
        ) {
            throw new WC_Braspag_Exception(
                print_r(
                    $request,
                    true
                ),
                __('Invalid Oauth Request Data', 'woocommerce-braspag')
            );
        }

        $authorization = self::get_authorization(
            $request['oauth_authentication_client_id'], $request['oauth_authentication_client_secret']
        );

        return apply_filters(
            'wc_braspag_request_headers',
            array(
                'Content-Type' => "application/x-www-form-urlencoded; charset=UTF-8",
                'Authorization' => "Basic " . $authorization
            )
        );
    }

    /**
     * @param $client_id
     * @param $client_secret
     * @return string
     */
    public static function get_authorization($client_id, $client_secret)
    {
        return base64_encode($client_id . ":" . $client_secret);
    }

    /**
     * @param $request
     * @param string $api
     * @param string $method
     * @param bool $sop
     * @param bool $with_headers
     * @return array|object
     * @throws WC_Braspag_Exception
     */
    public static function request($request, $api = 'oauth2/token', $method = 'POST', $sop = false, $with_headers = false)
    {
        WC_Braspag_Logger::log("{$api} request: " . print_r($request, true));

        $headers = $sop ? self::get_headers_sop($request) : self::get_headers($request);

        $end_point = 'yes' === $request['test_mode'] ? self::SANDBOX_ENDPOINT : self::PRODUCTION_ENDPOINT;

        $body = isset($request['body']) ? $request['body'] : [];

        $requestOptions = array(
            'method' => $method,
            'headers' => $headers,
            'body' => $body,
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