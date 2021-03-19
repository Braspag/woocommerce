<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles and process orders from asyncronous flows.
 *
 * @since 2.0.0
 */
class WC_Braspag_Order_Handler extends WC_Braspag_Payment_Gateway {
	private static $_this;
	public $retry_interval;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function __construct() {
		self::$_this = $this;

        add_action( 'woocommerce_order_status_processing', array( $this, 'wc_gateway_braspag_pagador_status_update_capture' ) );
        add_action( 'woocommerce_order_status_cancelled', array( $this, 'wc_gateway_braspag_pagador_status_update_void' ) );
        add_action( 'woocommerce_order_status_refunded', array( $this, 'wc_gateway_braspag_pagador_status_update_refund' ) );
	}

	/**
	 * Public access to instance object.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public static function get_instance() {
		return self::$_this;
	}

    /**
     * @param $order_id
     * @return $this
     * @throws WC_Braspag_Exception
     */
    public function wc_gateway_braspag_pagador_status_update_capture($order_id) {

        $order = wc_get_order( $order_id );

        if (!preg_match("#braspag#is", $order->get_payment_method())
            || $order->get_meta( '_braspag_charge_captured') === 'yes'
        ) {
            return $this;
        }

        $default_request_params = $this->braspag_pagador_get_default_request_params( get_current_user_id());

        $payment_id = $order->get_transaction_id();

        $amount = $order->get_total();

        if ( 0 < $order->get_total_refunded() ) {
            $amount = $amount - $order->get_total_refunded();
        }

        $amount = (int) $amount * 100;
        $service_tax_amount = (float) $order->get_meta('_braspag_service_tax_amount');

        $request_builder = $this->braspag_pagador_capture_request_builder($order);

        $api = "v2/sales/{$payment_id}/capture?amount={$amount}&serviceTaxAmount=$service_tax_amount";

        $response = $this->braspag_pagador_action_request( $request_builder, $api, $default_request_params );

        $this->process_braspag_pagador_action_response($response, $order);

        WC_Braspag_Helper::is_wc_lt( '3.0' ) ? update_post_meta( $order_id, '_braspag_charge_captured', 'yes' ) : $order->update_meta_data( '_braspag_charge_captured', 'yes' );
        $order->save();

        return $this;
    }

    /**
     * @param $order
     * @return mixed|void
     */
    public function braspag_pagador_capture_request_builder($order) {

        return apply_filters( "wc_gateway_braspag_pagador_capture_request_builder", [], $order);
    }

    /**
     * @param $order_id
     * @return $this
     * @throws WC_Braspag_Exception
     */
    public function wc_gateway_braspag_pagador_status_update_void($order_id) {

        $order = wc_get_order( $order_id );

        if (!preg_match("#braspag#is", $order->get_payment_method())
            || $order->get_meta( '_braspag_charge_refunded') === 'yes')
        {
            return $this;
        }

        $default_request_params = $this->braspag_pagador_get_default_request_params( get_current_user_id());

        $payment_id = $order->get_transaction_id();

        $amount = (int) $order->get_total() * 100;

        $request_builder = $this->braspag_pagador_void_request_builder($order);

        $api = "v2/sales/{$payment_id}/void?amount={$amount}";

        $response = $this->braspag_pagador_action_request( $request_builder, $api, $default_request_params );

        $this->process_braspag_pagador_action_response($response, $order);

        return $this;
    }

    /**
     * @param $order
     * @return mixed|void
     */
    public function braspag_pagador_void_request_builder($order) {

        return apply_filters( "wc_gateway_braspag_pagador_void_request_builder", [], $order);
    }

    /**
     * @param $order_id
     * @return $this
     * @throws WC_Braspag_Exception
     */
    public function wc_gateway_braspag_pagador_status_update_refund($order_id) {

        $order = wc_get_order( $order_id );

        if (!preg_match("#braspag#is", $order->get_payment_method())) {
            return $this;
        }

        $default_request_params = $this->braspag_pagador_get_default_request_params( get_current_user_id());

        $payment_id = $order->get_transaction_id();
        $amount = $order->get_total() * 100;

        $request_builder = $this->braspag_pagador_refund_request_builder($order);

        $api = "v2/sales/{$payment_id}/void?amount={$amount}";

        $response = $this->braspag_pagador_action_request( $request_builder, $api, $default_request_params );

        $this->process_braspag_pagador_action_response($response, $order);

        WC_Braspag_Helper::is_wc_lt( '3.0' ) ? update_post_meta( $order_id, '_braspag_charge_refunded', 'yes' ) : $order->update_meta_data( '_braspag_charge_refunded', 'yes' );
        $order->save();

        return $this;
    }

    /**
     * @param $order
     * @return mixed|void
     */
    public function braspag_pagador_refund_request_builder($order) {

        return apply_filters( "wc_gateway_braspag_pagador_refund_request_builder", [], $order);
    }
}

new WC_Braspag_Order_Handler();
