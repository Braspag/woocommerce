<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Braspag_Webhook_Handler
 */
class WC_Braspag_Webhook_Handler extends WC_Braspag_Payment_Gateway {

	public $retry_interval;

	public $test_mode;

	public function __construct() {
		$this->retry_interval = 2;
		$braspag_settings      = get_option( 'woocommerce_braspag_settings', array() );
		$this->test_mode       = ( ! empty( $braspag_settings['test_mode'] ) && 'yes' === $braspag_settings['test_mode'] ) ? true : false;

		add_action( 'woocommerce_api_wc_braspag', array( $this, 'check_for_webhook' ) );
	}

	public function check_for_webhook() {

		if ( ( 'POST' !== $_SERVER['REQUEST_METHOD'] )
			|| ! isset( $_GET['wc-api'] )
			|| ( 'wc_braspag' !== $_GET['wc-api'] )
		) {
			return;
		}

		$request_body    = json_decode(file_get_contents( 'php://input' ), true);
		$request_headers = array_change_key_case( $this->get_request_headers(), CASE_UPPER );

        try{
            if ( !$this->is_valid_request( $request_headers, $request_body )) {
                throw new WC_Braspag_Exception('Incoming webhook validation Error');
            }

            $this->process_webhook( $request_body );
            status_header( 200 );
            exit;

        } catch ( WC_Braspag_Exception $e ) {

            WC_Braspag_Logger::log( 'Process Change Type Error: ' . $e->getMessage() );
            WC_Braspag_Logger::log( 'Incoming webhook failed: ' . print_r( $request_body, true ) );

            status_header( 400 );
            exit;
        }
	}

    /**
     * @param null $request_headers
     * @param null $request_body
     * @return bool
     */
	public function is_valid_request( $request_headers = null, $request_body = null ) {
		if ( null === $request_headers || null === $request_body || !$request_body) {
			return false;
		}

		if (!isset($request_body['PaymentId']) || !isset($request_body['ChangeType'])) {
		    return false;
        }

		return true;
	}

    /**
     * @return array|false|string
     */
	public function get_request_headers() {
		if ( ! function_exists( 'getallheaders' ) ) {
			$headers = array();

			foreach ( $_SERVER as $name => $value ) {
				if ( 'HTTP_' === substr( $name, 0, 5 ) ) {
					$headers[ str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $name, 5 ) ) ) ) ) ] = $value;
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
	public function process_webhook( $request_body ) {
		$changeType = $request_body['ChangeType'];

		switch ( $changeType ) {
			case '1':
			    $this->process_change_type_status_update($request_body['PaymentId']);
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
	public function process_change_type_status_update($paymentId) {

        $order = WC_Braspag_Helper::get_order_by_charge_id($paymentId);

        if (!$order) {
            throw new WC_Braspag_Exception('Process Webhook Change Type Status Update Error: Order not found');
        }

        // Make the request.
        $response = WC_Braspag_Pagador_API_Query::requestByPaymentId($paymentId);

        return $this->process_change_type_status_update_response( $response, $order );
    }

    /**
     * @param $response
     * @param $order
     * @return bool
     * @throws WC_Braspag_Exception
     */
    public function process_change_type_status_update_response($response, $order) {

        switch ( $response->body->Payment->Status) {

            case '2': #PaymentConfirmed
                $order->payment_complete($response->body->Payment->PaymentId);

                /* translators: transaction id */
                $message = sprintf( __( 'Post Notification Message: Braspag charge complete (Charge ID: %s)', 'woocommerce-braspag' ), $response->body->Payment->PaymentId);
                $order->add_order_note( $message );
                break;

            case '1': #Authorized
                if ( $order->has_status( array( 'pending' ) ) ) {
                    WC_Braspag_Helper::is_wc_lt( '3.0' ) ? $order->reduce_order_stock() : wc_reduce_stock_levels( $order->get_id() );
                }

                /* translators: transaction id */
                $order->update_status( 'on-hold', sprintf( __( 'Post Notification Message: Braspag charge authorized (Charge ID: %s). Process order to take payment, or cancel to remove the pre-authorization.', 'woocommerce-braspag' ), $response->body->Payment->PaymentId));
                break;

            case '0': #NotFinished
                $order->update_status( 'failed', sprintf( __( 'Post Notification Message: Braspag charge Payment Not Finished (Charge ID: %s).', 'woocommerce-braspag' ), $response->body->Payment->PaymentId));
                break;
            case '12': #Pending
                $order->update_status( 'pending', sprintf( __( 'Post Notification Message: Braspag charge Payment Pending (Charge ID: %s).', 'woocommerce-braspag' ), $response->body->Payment->PaymentId));
                break;
            case '20': #Scheduled
                $order->update_status( 'pending', sprintf( __( 'Post Notification Message: Braspag charge Payment Scheduled (Charge ID: %s).', 'woocommerce-braspag' ), $response->body->Payment->PaymentId));
                break;

            case '3': #Denied
            case '10': #Voided
            case '13': #Aborted
            #cancel
                $order->update_status( 'cancelled', sprintf( __( 'Braspag charge payment Cancelled (Charge ID: %s).', 'woocommerce-braspag' ), $response->body->Payment->PaymentId));
                break;

            case '11': #Refunded
                #refund
                $order->update_status( 'refunded', sprintf( __( 'Braspag charge refunded (Charge ID: %s).', 'woocommerce-braspag' ), $response->body->Payment->PaymentId) );
                break;

            default:
                throw new WC_Braspag_Exception('Process Webhook Change Type Status Update Error: Invalid Order Status');
        }

        return true;
    }
}

new WC_Braspag_Webhook_Handler();
