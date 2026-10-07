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

            // Sem este log a causa da recusa do Pagador nunca aparecia em
            // lugar algum: `body` é anulado aqui e o caminho de erro só
            // registrava a mensagem amigável (que vinha vazia — ver
            // WC_Gateway_Braspag::get_localized_error_message_from_response()).
            WC_Braspag_Logger::log(
                'Pagador respondeu HTTP ' . $response_data['status'] . ': '
                . print_r($response_data['errors'], true)
            );
        }

        return (object) $response_data;
    }

    /**
     * Extrai as mensagens de erro do corpo que o Pagador devolve numa
     * resposta não-2xx. O formato real é uma lista de objetos
     * `{Code, Message}` (ex.: `[{"Code":126,"Message":"Card Number length
     * exceeded"}]`), mas respostas de erro de outros endpoints aparecem como
     * objeto único ou string — todas são aceitas aqui.
     *
     * Função pura, sem dependência de WordPress: é o ponto testável do
     * tratamento de erro do Pagador.
     *
     * @param mixed $errors Conteúdo de `$response->errors`.
     * @return string[] Mensagens no formato "[Code] Message" (ou só a
     *                  mensagem, quando não há código).
     */
    public static function extract_error_messages($errors)
    {
        if (empty($errors)) {
            return array();
        }

        if (is_string($errors)) {
            return array(trim($errors));
        }

        if (is_object($errors) || (is_array($errors) && !isset($errors[0]))) {
            $errors = array($errors);
        }

        if (!is_array($errors)) {
            return array();
        }

        $messages = array();

        foreach ($errors as $error) {
            $error = is_array($error) ? (object) $error : $error;

            if (is_string($error)) {
                $messages[] = trim($error);
                continue;
            }

            if (!is_object($error)) {
                continue;
            }

            $message = isset($error->Message) ? (string) $error->Message : '';
            $code = isset($error->Code) ? (string) $error->Code : '';

            if ('' === $message && '' === $code) {
                continue;
            }

            if ('' === $message) {
                $messages[] = sprintf('[%s]', $code);
                continue;
            }

            $messages[] = '' !== $code ? sprintf('[%s] %s', $code, $message) : $message;
        }

        return $messages;
    }
}