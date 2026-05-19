<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WC_Braspag_Webhook_Handler
 */
class WC_Braspag_Webhook_Handler extends WC_Braspag_Payment_Gateway
{
    public $retry_interval;

    public $test_mode;

    public function __construct()
    {
        $this->retry_interval = 2;
        $braspag_settings = get_option('woocommerce_braspag_settings', array());
        $this->test_mode = (!empty($braspag_settings['test_mode']) && 'yes' === $braspag_settings['test_mode']) ? true : false;

        add_action('woocommerce_api_wc_braspag', array($this, 'check_for_webhook'));
    }

    public function check_for_webhook()
    {
        if (
            ('POST' !== $_SERVER['REQUEST_METHOD'])
            || !isset($_GET['wc-api'])
            || ('wc_braspag' !== $_GET['wc-api'])
        ) {
            return;
        }

        $raw_body = file_get_contents('php://input');
        $request_body = json_decode($raw_body, true);
        $request_headers = array_change_key_case($this->get_request_headers(), CASE_UPPER);

        try {
            if (!$this->is_valid_request($request_headers, $request_body, $raw_body)) {
                throw new WC_Braspag_Exception('Incoming webhook validation Error');
            }

            $this->process_webhook($request_body);
            status_header(200);
            exit;

        } catch (WC_Braspag_Exception $e) {

            WC_Braspag_Logger::log('Process Change Type Error: ' . $e->getMessage());
            WC_Braspag_Logger::log('Incoming webhook failed: ' . print_r($request_body, true));

            status_header(400);
            exit;
        }
    }

    /**
     * @param null $request_headers
     * @param null $request_body
     * @return bool
     */
    public function is_valid_request($request_headers = null, $request_body = null, $raw_body = null)
    {
        if (null === $request_headers || null === $request_body || null === $raw_body) {
            return false;
        }

        if (JSON_ERROR_NONE !== json_last_error() || !is_array($request_body)) {
            return false;
        }

        if (empty($request_body['PaymentId']) || empty($request_body['ChangeType'])) {
            return false;
        }

        $change_type = (string) $request_body['ChangeType'];

        if (!in_array($change_type, array('1', '2', '3', '4', '5', '6', '7', '8'), true)) {
            return false;
        }

        if (!$this->is_valid_signature($request_headers, $raw_body)) {
            return false;
        }

        return (bool) apply_filters('wc_braspag_validate_webhook_request', true, $request_headers, $request_body, $raw_body);
    }

    /**
     * @param array $request_headers
     * @param string $raw_body
     * @return bool
     */
    private function is_valid_signature($request_headers, $raw_body)
    {
        $secret = $this->get_webhook_secret();

        if ('' === $secret) {
            return true;
        }

        $signature_header = strtoupper((string) apply_filters('wc_braspag_webhook_signature_header', 'X-BRASPAG-SIGNATURE'));
        $signature = isset($request_headers[$signature_header]) ? (string) $request_headers[$signature_header] : '';

        if ('' === $signature) {
            return false;
        }

        $expected = hash_hmac('sha256', $raw_body, $secret);

        if (hash_equals($expected, $signature)) {
            return true;
        }

        $expected_base64 = base64_encode(hex2bin($expected));

        return hash_equals($expected_base64, $signature);
    }

    /**
     * @return string
     */
    private function get_webhook_secret()
    {
        $settings = get_option('woocommerce_braspag_settings', array());

        return isset($settings['webhook_secret']) ? trim((string) $settings['webhook_secret']) : '';
    }

    /**
     * @return array|false|string
     */
    public function get_request_headers()
    {
        if (!function_exists('getallheaders')) {
            $headers = array();

            foreach ($_SERVER as $name => $value) {
                if ('HTTP_' === substr($name, 0, 5)) {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }

            return $headers;
        } else {
            return getallheaders();
        }
    }

    /**
     * @param $request_body
     * @return bool
     * @throws WC_Braspag_Exception
     */
    public function process_webhook($request_body)
    {
        $changeType = (string) $request_body['ChangeType'];
        $payment_id = sanitize_text_field($request_body['PaymentId']);

        switch ($changeType) {
            case '1':
                $this->process_change_type_status_update($payment_id);
                break;

            case '2':
            case '3':
            case '4':
            case '5':
            case '6':
            case '7':
            case '8':
                break;
            default:
                throw new WC_Braspag_Exception('Process Webhook Error');
        }

        return true;
    }

    /**
     * @param $paymentId
     * @return bool
     * @throws WC_Braspag_Exception
     */
    public function process_change_type_status_update($paymentId)
    {
        $order = WC_Braspag_Helper::get_order_by_charge_id($paymentId, ['_braspag_pix_payment_id']);

        if (!$order) {
            throw new WC_Braspag_Exception('Process Webhook Change Type Status Update Error: Order not found');
        }

        // Make the request.
        $response = WC_Braspag_Pagador_API_Query::requestByPaymentId($paymentId);

        return $this->process_change_type_status_update_response($response, $order);
    }

    /**
     * @param $response
     * @param $order
     * @return bool
     * @throws WC_Braspag_Exception
     */
    public function process_change_type_status_update_response($response, $order)
    {
        if (in_array($order->get_status(), array('completed', 'cancelled', 'refunded'), true)) {
            WC_Braspag_Logger::log('Webhook ignorado: Pedido #' . $order->get_id() . ' já está em status final.');
            return true;
        }

        $payment_status = $response->body->Payment->Status;
        $payment_id = $response->body->Payment->PaymentId;

        switch ($payment_status) {

            case '2': #PaymentConfirmed
                if ($order->get_status() !== 'processing') {
                    $order->add_meta_data('_braspag_charge_captured', 'yes', true);
                    $order->update_status('processing', sprintf(__('Webhook: Pagamento confirmado (PaymentId: %s).', 'woocommerce-braspag'), $payment_id));
                    $order->add_order_note(sprintf(__('Pagamento confirmado pelo webhook. PaymentId: %s.', 'woocommerce-braspag'), $payment_id));
                }
                break;

            case '1': #Authorized
                if ($order->has_status('pending')) {
                    WC_Braspag_Helper::is_wc_lt('3.0') ? $order->reduce_order_stock() : wc_reduce_stock_levels($order->get_id());
                }
                if ($order->get_status() !== 'on-hold') {
                    $order->add_meta_data('_braspag_charge_authorized', 'yes', true);
                    $order->update_status('on-hold', sprintf(__('Webhook: Pagamento autorizado (PaymentId: %s).', 'woocommerce-braspag'), $payment_id));
                    $order->add_order_note(sprintf(__('Pagamento autorizado pelo webhook. PaymentId: %s.', 'woocommerce-braspag'), $payment_id));
                }
                break;

            case '0': #NotFinished
                if ($order->get_status() !== 'failed') {
                    $order->update_status('failed', sprintf(__('Webhook: Pagamento não finalizado (PaymentId: %s).', 'woocommerce-braspag'), $payment_id));
                    $order->add_order_note(sprintf(__('Pagamento não finalizado (PaymentId: %s).', 'woocommerce-braspag'), $payment_id));
                }
                break;
            case '12': #Pending
                $order->update_status('pending', sprintf(__('Post Notification Message: Braspag charge Payment Pending (Charge ID: %s).', 'woocommerce-braspag'), $payment_id));
                $order->add_order_note(sprintf(__('Pagamento aguardando aprovação ou esta pendente de pagamento. (PaymentId: %s).', 'woocommerce-braspag'), $payment_id));
                break;
            case '20': #Scheduled
                if ($order->get_status() !== 'pending') {
                    $order->update_status('pending', sprintf(__('Webhook: Pagamento pendente/agendado (PaymentId: %s).', 'woocommerce-braspag'), $payment_id));
                    $order->add_order_note(sprintf(__('Pagamento pendente ou agendado. PaymentId: %s.', 'woocommerce-braspag'), $payment_id));
                }
                break;

            case '3': #Denied
            case '10': #Voided
            case '13': #Aborted
                #cancel
                if ($order->get_status() !== 'cancelled') {
                    $order->update_status('cancelled', sprintf(__('Webhook: Pagamento cancelado (PaymentId: %s).', 'woocommerce-braspag'), $payment_id));
                    $order->add_order_note(sprintf(__('Pagamento cancelado. PaymentId: %s.', 'woocommerce-braspag'), $payment_id));
                }
                break;

            case '11': #Refunded
                #refund
                if ($order->get_status() !== 'refunded') {
                    $order->update_status('refunded', sprintf(__('Webhook: Pagamento reembolsado (PaymentId: %s).', 'woocommerce-braspag'), $payment_id));
                    $order->add_order_note(sprintf(__('Pagamento reembolsado. PaymentId: %s.', 'woocommerce-braspag'), $payment_id));
                }
                break;

            default:
                throw new WC_Braspag_Exception('Process Webhook Change Type Status Update Error: Invalid Order Status');
        }

        $order->save();
        return true;
    }
}

new WC_Braspag_Webhook_Handler();