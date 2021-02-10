<?php
if ( ! class_exists( 'WC_Abstract_Privacy' ) ) {
	return;
}

class WC_Braspag_Privacy extends WC_Abstract_Privacy {
	/**
	 * Constructor
	 *
	 */
	public function __construct() {
		parent::__construct( __( 'Braspag', 'woocommerce-braspag' ) );

		$this->add_exporter( 'wc-gateway-braspag-order-data', __( 'WooCommerce Braspag Order Data', 'woocommerce-braspag' ), array( $this, 'order_data_exporter' ) );

		if ( function_exists( 'wcs_get_subscriptions' ) ) {
			$this->add_exporter( 'wc-gateway-braspag-subscriptions-data', __( 'WooCommerce Braspag Subscriptions Data', 'woocommerce-braspag' ), array( $this, 'subscriptions_data_exporter' ) );
		}

		$this->add_exporter( 'wc-gateway-braspag-customer-data', __( 'WooCommerce Braspag Customer Data', 'woocommerce-braspag' ), array( $this, 'customer_data_exporter' ) );

		$this->add_eraser( 'wc-gateway-braspag-customer-data', __( 'WooCommerce Braspag Customer Data', 'woocommerce-braspag' ), array( $this, 'customer_data_eraser' ) );
		$this->add_eraser( 'wc-gateway-braspag-order-data', __( 'WooCommerce Braspag Data', 'woocommerce-braspag' ), array( $this, 'order_data_eraser' ) );

		add_filter( 'woocommerce_get_settings_account', array( $this, 'account_settings' ) );
	}

    /**
     * @param $settings
     * @return mixed
     */
	public function account_settings( $settings ) {
		$insert_setting = array(
			array(
				'title'       => __( 'Retain Braspag Data', 'woocommerce-braspag' ),
				'desc_tip'    => __( 'Retains any Braspag data such as Braspag customer ID, source ID.', 'woocommerce-braspag' ),
				'id'          => 'woocommerce_gateway_braspag_retention',
				'type'        => 'relative_date_selector',
				'placeholder' => __( 'N/A', 'woocommerce-braspag' ),
				'default'     => '',
				'autoload'    => false,
			),
		);

		$index = null;

		foreach ( $settings as $key => $value) {
			if ( 'sectionend' === $value[ 'type' ] && 'personal_data_retention' === $value[ 'id' ] ) {
				$index = $key;
				break;
			}
		}

		if ( ! is_null( $index ) ) {
			array_splice( $settings, $index, 0, $insert_setting );
		}

		return $settings;
	}

    /**
     * @param $email_address
     * @param $page
     * @return stdClass|WC_Order[]
     */
	protected function get_braspag_orders( $email_address, $page ) {
		$user = get_user_by( 'email', $email_address ); // Check if user has an ID in the DB to load stored personal data.

		$order_query = array(
			'payment_method' => array( 'braspag', 'braspag_creditcard', 'braspag_creditcard_justiclick', 'braspag_debitcard', 'braspag_boleto'),
			'limit'          => 10,
			'page'           => $page,
		);

		if ( $user instanceof WP_User ) {
			$order_query['customer_id'] = (int) $user->ID;
		} else {
			$order_query['billing_email'] = $email_address;
		}

		return wc_get_orders( $order_query );
	}

	/**
	 * Gets the message of the privacy to display.
	 *
	 */
	public function get_privacy_message() {
		/* translators: %s URL to docs */
		return wpautop( sprintf( __( 'By using this extension, you may be storing personal data or sharing data with an external service. <a href="%s" target="_blank">Learn more about how this works, including what you may want to include in your privacy policy.</a>', 'woocommerce-braspag' ), 'https://docs.woocommerce.com/document/privacy-payments/#wc-gateway-braspag' ) );
	}

    /**
     * @param $email_address
     * @param int $page
     * @return array
     */
	public function order_data_exporter( $email_address, $page = 1 ) {
		$done           = false;
		$data_to_export = array();

		$orders = $this->get_braspag_orders( $email_address, (int) $page );

		$done = true;

		if ( 0 < count( $orders ) ) {
			foreach ( $orders as $order ) {
				$data_to_export[] = array(
					'group_id'    => 'woocommerce_orders',
					'group_label' => __( 'Orders', 'woocommerce-braspag' ),
					'item_id'     => 'order-' . $order->get_id(),
					'data'        => array(
						array(
							'name'  => __( 'Braspag payment id', 'woocommerce-braspag' ),
							'value' => get_post_meta( $order->get_id(), '_braspag_source_id', true ),
						),
						array(
							'name'  => __( 'Braspag customer id', 'woocommerce-braspag' ),
							'value' => get_post_meta( $order->get_id(), '_braspag_customer_id', true ),
						),
					),
				);
			}

			$done = 10 > count( $orders );
		}

		return array(
			'data' => $data_to_export,
			'done' => $done,
		);
	}

    /**
     * @param $email_address
     * @param int $page
     * @return array
     */
	public function subscriptions_data_exporter( $email_address, $page = 1 ) {
		$done           = false;
		$page           = (int) $page;
		$data_to_export = array();

		$meta_query = array(
			'relation' => 'AND',
			array(
				'key'     => '_payment_method',
				'value'   => array( 'braspag', 'braspag_creditcard', 'braspag_creditcard_justclick', 'braspag_debitcard', 'braspag_boleto'),
				'compare' => 'IN',
			),
			array(
				'key'     => '_billing_email',
				'value'   => $email_address,
				'compare' => '=',
			),
		);

		$subscription_query = array(
			'posts_per_page' => 10,
			'page'           => $page,
			'meta_query'     => $meta_query,
		);

		$subscriptions = wcs_get_subscriptions( $subscription_query );

		$done = true;

		if ( 0 < count( $subscriptions ) ) {
			foreach ( $subscriptions as $subscription ) {
				$data_to_export[] = array(
					'group_id'    => 'woocommerce_subscriptions',
					'group_label' => __( 'Subscriptions', 'woocommerce-braspag' ),
					'item_id'     => 'subscription-' . $subscription->get_id(),
					'data'        => array(
						array(
							'name'  => __( 'Braspag payment id', 'woocommerce-braspag' ),
							'value' => get_post_meta( $subscription->get_id(), '_braspag_source_id', true ),
						),
						array(
							'name'  => __( 'Braspag customer id', 'woocommerce-braspag' ),
							'value' => get_post_meta( $subscription->get_id(), '_braspag_customer_id', true ),
						),
					),
				);
			}

			$done = 10 > count( $subscriptions );
		}

		return array(
			'data' => $data_to_export,
			'done' => $done,
		);
	}

    /**
     * @param $email_address
     * @param $page
     * @return array
     */
	public function customer_data_exporter( $email_address, $page ) {
		$user           = get_user_by( 'email', $email_address ); // Check if user has an ID in the DB to load stored personal data.
		$data_to_export = array();

		if ( $user instanceof WP_User ) {
			$braspag_user = new WC_Braspag_Customer( $user->ID );

			$data_to_export[] = array(
				'group_id'    => 'woocommerce_customer',
				'group_label' => __( 'Customer Data', 'woocommerce-braspag' ),
				'item_id'     => 'user',
				'data'        => array(
					array(
						'name'  => __( 'Braspag payment id', 'woocommerce-braspag' ),
						'value' => get_user_option( '_braspag_source_id', $user->ID ),
					),
					array(
						'name'  => __( 'Braspag customer id', 'woocommerce-braspag' ),
						'value' => $braspag_user->get_id(),
					),
				),
			);
		}

		return array(
			'data' => $data_to_export,
			'done' => true,
		);
	}

    /**
     * @param $email_address
     * @param $page
     * @return array
     */
	public function customer_data_eraser( $email_address, $page ) {
		$page               = (int) $page;
		$user               = get_user_by( 'email', $email_address ); // Check if user has an ID in the DB to load stored personal data.
		$braspag_customer_id = '';
		$braspag_source_id   = '';

		if ( $user instanceof WP_User ) {
			$braspag_customer_id = get_user_option( '_braspag_customer_id', $user->ID );
			$braspag_source_id   = get_user_option( '_braspag_source_id', $user->ID );
		}

		$items_removed = false;
		$messages      = array();

		if ( ! empty( $braspag_customer_id ) || ! empty( $braspag_source_id ) ) {
			$items_removed = true;
			delete_user_option( $user->ID, '_braspag_customer_id' );
			delete_user_option( $user->ID, '_braspag_source_id' );
			$messages[] = __( 'Braspag User Data Erased.', 'woocommerce-braspag' );
		}

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => false,
			'messages'       => $messages,
			'done'           => true,
		);
	}

    /**
     * @param $email_address
     * @param $page
     * @return array
     */
	public function order_data_eraser( $email_address, $page ) {
		$orders = $this->get_braspag_orders( $email_address, (int) $page );

		$items_removed  = false;
		$items_retained = false;
		$messages       = array();

		foreach ( (array) $orders as $order ) {
			$order = wc_get_order( $order->get_id() );

			list( $removed, $retained, $msgs ) = $this->maybe_handle_order( $order );
			$items_removed                    |= $removed;
			$items_retained                   |= $retained;
			$messages                          = array_merge( $messages, $msgs );

			list( $removed, $retained, $msgs ) = $this->maybe_handle_subscription( $order );
			$items_removed                    |= $removed;
			$items_retained                   |= $retained;
			$messages                          = array_merge( $messages, $msgs );
		}

		// Tell core if we have more orders to work on still
		$done = count( $orders ) < 10;

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => $messages,
			'done'           => $done,
		);
	}

    /**
     * @param $order
     * @return array
     */
	protected function maybe_handle_subscription( $order ) {
		if ( ! class_exists( 'WC_Subscriptions' ) ) {
			return array( false, false, array() );
		}

		if ( ! wcs_order_contains_subscription( $order ) ) {
			return array( false, false, array() );
		}

		$subscription    = current( wcs_get_subscriptions_for_order( $order->get_id() ) );
		$subscription_id = $subscription->get_id();

		$braspag_source_id = get_post_meta( $subscription_id, '_braspag_source_id', true );

		if ( empty( $braspag_source_id ) ) {
			return array( false, false, array() );
		}

		if ( ! $this->is_retention_expired( $order->get_date_created()->getTimestamp() ) ) {
			/* translators: %d Order ID */
			return array( false, true, array( sprintf( __( 'Order ID %d is less than set retention days. Personal data retained. (Braspag)', 'woocommerce-braspag' ), $order->get_id() ) ) );
		}

		if ( $subscription->has_status( apply_filters( 'wc_braspag_privacy_eraser_subs_statuses', array( 'on-hold', 'active' ) ) ) ) {
			/* translators: %d Order ID */
			return array( false, true, array( sprintf( __( 'Order ID %d contains an active Subscription. Personal data retained. (Braspag)', 'woocommerce-braspag' ), $order->get_id() ) ) );
		}

		$renewal_orders = WC_Subscriptions_Renewal_Order::get_renewal_orders( $order->get_id() );

		foreach ( $renewal_orders as $renewal_order_id ) {
			delete_post_meta( $renewal_order_id, '_braspag_source_id' );
			delete_post_meta( $renewal_order_id, '_braspag_refund_id' );
			delete_post_meta( $renewal_order_id, '_braspag_customer_id' );
		}

		delete_post_meta( $subscription_id, '_braspag_source_id' );
		delete_post_meta( $subscription_id, '_braspag_refund_id' );
		delete_post_meta( $subscription_id, '_braspag_customer_id' );

		return array( true, false, array( __( 'Braspag Subscription Data Erased.', 'woocommerce-braspag' ) ) );
	}

    /**
     * @param $order
     * @return array
     */
	protected function maybe_handle_order( $order ) {
		$order_id           = $order->get_id();
		$braspag_source_id   = get_post_meta( $order_id, '_braspag_source_id', true );
		$braspag_refund_id   = get_post_meta( $order_id, '_braspag_refund_id', true );
		$braspag_customer_id = get_post_meta( $order_id, '_braspag_customer_id', true );

		if ( ! $this->is_retention_expired( $order->get_date_created()->getTimestamp() ) ) {
			/* translators: %d Order ID */
			return array( false, true, array( sprintf( __( 'Order ID %d is less than set retention days. Personal data retained. (Braspag)', 'woocommerce-braspag' ), $order->get_id() ) ) );
		}

		if ( empty( $braspag_source_id ) && empty( $braspag_refund_id ) && empty( $braspag_customer_id ) ) {
			return array( false, false, array() );
		}

		delete_post_meta( $order_id, '_braspag_source_id' );
		delete_post_meta( $order_id, '_braspag_refund_id' );
		delete_post_meta( $order_id, '_braspag_customer_id' );

		return array( true, false, array( __( 'Braspag personal data erased.', 'woocommerce-braspag' ) ) );
	}

    /**
     * @param $created_date
     * @return bool
     */
	public function is_retention_expired( $created_date ) {
		$retention  = wc_parse_relative_date_option( get_option( 'woocommerce_gateway_braspag_retention' ) );
		$is_expired = false;
		$time_span  = time() - strtotime( $created_date );
		if ( empty( $retention ) || empty( $created_date ) ) {
			return false;
		}
		switch ( $retention['unit'] ) {
			case 'days':
				$retention = $retention['number'] * DAY_IN_SECONDS;
				if ( $time_span > $retention ) {
					$is_expired = true;
				}
				break;
			case 'weeks':
				$retention = $retention['number'] * WEEK_IN_SECONDS;
				if ( $time_span > $retention ) {
					$is_expired = true;
				}
				break;
			case 'months':
				$retention = $retention['number'] * MONTH_IN_SECONDS;
				if ( $time_span > $retention ) {
					$is_expired = true;
				}
				break;
			case 'years':
				$retention = $retention['number'] * YEAR_IN_SECONDS;
				if ( $time_span > $retention ) {
					$is_expired = true;
				}
				break;
		}
		return $is_expired;
	}
}

new WC_Braspag_Privacy();
