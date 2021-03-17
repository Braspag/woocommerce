<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Gateway_Braspag
 */
class WC_Gateway_Braspag extends WC_Braspag_Payment_Gateway
{
    public $enabled;

    protected $test_mode;
    protected $antifraud_enabled;
    protected $antifraud_status;
    protected $antifraud_finger_print_org_id;
    protected $antifraud_finger_print_session_id;
    protected $antifraud_finger_print_merchant_id;
    protected $antifraud_finger_print_id;

	public function __construct() {
		$this->id             = 'braspag';
		$this->method_title   = __( 'Braspag', 'woocommerce-braspag' );
        $this->method_description =  __( 'Braspag Payment Gateway general configurations.' );
		/* translators: 1) link to Braspag register page 2) link to Braspag api keys page */
		$this->has_fields         = false;
        $this->supports           = array(
            'add_payment_method'
        );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title                = 'Braspag';

		$this->enabled = $this->get_option( 'enabled' );
		$this->test_mode = 'yes' === $this->get_option( 'test_mode' );

        $this->test_mode = 'yes' === $this->get_option( 'test_mode' );
        $this->antifraud_enabled = 'yes' === $this->get_option( 'antifraud_enabled' );
        $this->antifraud_finger_print_org_id = $this->get_option( 'antifraud_finger_print_org_id' );
        $this->antifraud_finger_print_merchant_id = $this->get_option( 'antifraud_finger_print_merchant_id' );
        $this->antifraud_finger_print_session_id = $this->get_option( 'antifraud_finger_print_session_id' );

        if (WC()->cart) {
            $this->antifraud_finger_print_id = WC()->cart->get_cart_hash();
        }

        $this->antifraud_finger_print_session_id = $this->antifraud_finger_print_merchant_id.$this->antifraud_finger_print_id;

        // Hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

        add_filter( 'woocommerce_order_button_html', array($this, 'wc_gateway_braspag_order_button_html'));

        add_action( 'woocommerce_review_order_before_payment', array( $this, 'get_braspag_auth3ds20_elements' ) );

        add_action( 'admin_menu', array( $this, 'settings_menu' ), 60 );
	}

    public function settings_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Sellers', 'wc-braspag' ),
            __( 'Sellers', 'wc-braspag' ),
            'manage_options',
            'woocommerce-extra-checkout-fields-for-brazil',
            array( $this, 'html_settings_page' )
        );
    }

    public function html_settings_page() {
        include dirname( __FILE__ ) . '/views/html-settings-page.php';
    }

    /**
     * @param $fields
     */
    public function get_braspag_auth3ds20_elements($fields) {

        $cart = WC()->cart;

        $cart_items = "";
        $key = 0;
        foreach ($cart->get_cart_contents() as $cart_content) {

            $cart_items .= '
                    <input type="hidden" name="bpmpi_cart_'.$key.'_description" class="bpmpi_cart_'.$key.'_description" value="'.$cart_content['data']->get_name().'"/>
                    <input type="hidden" name="bpmpi_cart_'.$key.'_name" class="bpmpi_cart_'.$key.'_name" value="'.$cart_content['data']->get_name().'"/>
                    <input type="hidden" name="bpmpi_cart_'.$key.'_sku" class="bpmpi_cart_'.$key.'_sku" value="'.(!empty($cart_content['data']->get_sku()) ? $cart_content['data']->get_sku() : $cart_content['data']->get_slug()).'"/>
                    <input type="hidden" name="bpmpi_cart_'.$key.'_quantity" class="bpmpi_cart_'.$key.'_quantity" value="'.$cart_content['quantity'].'"/>
                    <input type="hidden" name="bpmpi_cart_'.$key.'_unitprice" class="bpmpi_cart_'.$key.'_unitprice" value="'.($cart_content['data']->get_price()*100).'"/>';
            $key++;
        }

        echo '<div id="bpmpi_data">

                <div id="bpmpi_data_auth">
                    <input type="hidden" name="test_environment" class="test_environment" value="1"/>
                    <input type="hidden" name="bpmpi_accesstoken" class="bpmpi_accesstoken"/>
                    <input type="hidden" name="bpmpi_auth" class="bpmpi_auth" value="true"/>
                    <input type="hidden" name="bpmpi_auth_notifyonly" class="bpmpi_auth_notifyonly" value=""/>
                    <input type="hidden" name="bpmpi_auth_suppresschallenge" class="bpmpi_auth_suppresschallenge" value="false"/>
                    <input type="hidden" name="bpmpi_auth_failure_type" class="bpmpi_auth_failure_type" value=""/>
                    <input type="hidden" name="bpmpi_auth_cavv" class="bpmpi_auth_cavv" value=""/>
                    <input type="hidden" name="bpmpi_auth_xid" class="bpmpi_auth_xid" value=""/>
                    <input type="hidden" name="bpmpi_auth_eci" class="bpmpi_auth_eci" value=""/>
                    <input type="hidden" name="bpmpi_auth_version" class="bpmpi_auth_version" value=""/>
                    <input type="hidden" name="bpmpi_auth_reference_id" class="bpmpi_auth_reference_id" value=""/>
                </div>
            
                <div id="bpmpi_data_recurring">
                    <input type="hidden" name="bpmpi_recurring_enddate" class="bpmpi_recurring_enddate" value=""/>
                    <input type="hidden" name="bpmpi_recurring_frequency" class="bpmpi_recurring_frequency" value=""/>
                    <input type="hidden" name="bpmpi_recurring_originalpurchasedate" class="bpmpi_recurring_originalpurchasedate" value=""/>
                </div>
            
                <div id="bpmpi_data_payment">
                    <input type="hidden" class="bpmpi_paymentmethod" value=""/>
                    <input type="hidden" class="bpmpi_cardnumber" value=""/>
                    <input type="hidden" class="bpmpi_cardexpirationmonth" value=""/>
                    <input type="hidden" class="bpmpi_cardexpirationyear" value=""/>
                    <input type="hidden" class="bpmpi_installments" value=""/>
            
                    <input type="hidden" class="bpmpi_totalamount" value="'.($cart->get_cart_contents_total()*100).'"/>
                    <input type="hidden" class="bpmpi_currency" value="BRL"/>
                    <input type="hidden" class="bpmpi_ordernumber" value="'.(WC()->cart->get_cart_hash()).'"/>
                    <input type="hidden" class="bpmpi_transaction_mode" value=""/>
                    <input type="hidden" class="bpmpi_merchant_url" value="'.gethostname().'"/>
                </div>
            
                <div id="bpmpi_data_billto">
                    <input type="hidden" class="bpmpi_billto_contactname" value=""/>
                    <input type="hidden" class="bpmpi_billto_phonenumber" value=""/>
                    <input type="hidden" class="bpmpi_billto_customerid" value="'.$this->get_logged_in_customer_id().'"/>
                    <input type="hidden" class="bpmpi_billto_email" value=""/>
                    <input type="hidden" class="bpmpi_billto_street1" value=""/>
                    <input type="hidden" class="bpmpi_billto_street2" value=""/>
                    <input type="hidden" class="bpmpi_billto_city" value=""/>
                    <input type="hidden" class="bpmpi_billto_state" value=""/>
                    <input type="hidden" class="bpmpi_billto_zipcode" value=""/>
                    <input type="hidden" class="bpmpi_billto_country" value=""/>
                </div>
            
                <div id="bpmpi_data_shipto">
                    <input type="hidden" class="bpmpi_shipto_sameasbillto" value=""/>
                    <input type="hidden" class="bpmpi_shipto_addressee" value=""/>
                    <input type="hidden" class="bpmpi_shipto_phonenumber" value=""/>
                    <input type="hidden" class="bpmpi_shipto_email" value=""/>
                    <input type="hidden" class="bpmpi_shipto_street1" value=""/>
                    <input type="hidden" class="bpmpi_shipto_street2" value=""/>
                    <input type="hidden" class="bpmpi_shipto_city" value=""/>
                    <input type="hidden" class="bpmpi_shipto_state" value=""/>
                    <input type="hidden" class="bpmpi_shipto_zipcode" value=""/>
                    <input type="hidden" class="bpmpi_shipto_country" value=""/>
                </div>
            
                <div id="bpmpi_data_cart">
                    '.$cart_items.'
                </div>
            
                <div id="bpmpi_data_useraccount">
                    <input type="hidden" class="bpmpi_useraccount_guest" value="'.(empty($this->get_logged_in_customer_id()) ? 'true' : 'false').'"/>
                    <input type="hidden" class="bpmpi_useraccount_createddate" value=""/>
                    <input type="hidden" class="bpmpi_useraccount_changeddate" value=""/>
                    <input type="hidden" class="bpmpi_useraccount_authenticationmethod" value=""/>
                    <input type="hidden" class="bpmpi_useraccount_authenticationprotocol" value=""/>
                </div>
            
                <div id="bpmpi_data_device">
                    <input type="hidden" name="bpmpi_device_ipaddress" class="bpmpi_device_ipaddress" value="'.WC_Geolocation::get_ip_address().'"/>
                    <input type="hidden" class="bpmpi_device_0_fingerprint" value=""/>
                    <input type="hidden" class="bpmpi_device_0_provider" value=""/>
                </div>
            
                <div id="bpmpi_data_mdd">
                    <input type="hidden" class="bpmpi_mdd1" value=""/>
                    <input type="hidden" class="bpmpi_mdd2" value=""/>
                    <input type="hidden" class="bpmpi_mdd3" value=""/>
                    <input type="hidden" class="bpmpi_mdd4" value=""/>
                    <input type="hidden" class="bpmpi_mdd5" value=""/>
                </div>
                
                <div id="bpmpi_data_mdd">
                    <input type="hidden" class="bpmpi_order_productcode" value="PHY"/>
                </div>
            </div>
            ';
    }

    /**
     * @param $cart
     * @return array
     */
    public function get_auth3ds20_elements_fields_cart($cart) {

        $cart_items = [];
        foreach ($cart->get_cart_contents() as $key => $cart_content) {
            $cart_items[] = [
                "bpmpi_cart_{$key}}_description" => $cart_content['data']->get_description(),
                "bpmpi_cart_{$key}_name" => $cart_content['data']->get_name(),
                "bpmpi_cart_{$key}_sku" => $cart_content['data']->get_sku(),
                "bpmpi_cart_{$key}_quantity" => $cart_content->quantity,
                "bpmpi_cart_{$key}_unitprice" => $cart_content['data']->get_price()
            ];
        }

        return $cart_items;
    }

	public function wc_gateway_braspag_order_button_html(){
        $order_button_text = __("Finalizar Pedido", 'woocommerce-braspag');
        echo '<button type="button" class="button alt" class="woocommerce-checkout-place-order-braspag" name="woocommerce_checkout_place_order" id="place_order" onclick="braspag.placeOrder();" value="' . esc_attr( $order_button_text ) . '" data-value="' . esc_attr( $order_button_text ) . '">' . esc_html( $order_button_text ) . '</button>';
    }

    /**
     * @return mixed|void
     */
    public function get_supported_currency() {
        return apply_filters(
            'wc_braspag_supported_currencies',
            array(
                'BRL',
            )
        );
    }

    /**
     * @return mixed|void
     */
	public function get_logged_in_customer_id() {
        return apply_filters( 'woocommerce_checkout_customer_id', get_current_user_id() );
    }

    /**
     * @return bool
     */
    public function is_available()
    {
        return false;
    }

	public function init_form_fields() {
		$this->form_fields = require( dirname( __FILE__ ) . '/admin/braspag-settings.php' );
	}

	public function payment_scripts()
    {
		// If Braspag is not enabled bail.
		if ( 'no' === $this->enabled ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_style( 'wc-braspag', plugins_url( 'assets/css/braspag-styles.css', WC_BRASPAG_MAIN_FILE ), array(), WC_BRASPAG_VERSION );
		wp_enqueue_style( 'wc-braspag' );

		wp_register_script( 'wc-braspag', plugins_url( 'assets/js/braspag.js', WC_BRASPAG_MAIN_FILE ), array('prototype', 'jquery-payment'), WC_BRASPAG_VERSION, true );
        wp_enqueue_script( 'wc-braspag' );

        wp_register_script('wc-braspag-antifraud-fingerprint', "https://h.online-metrix.net/fp/tags.js?org_id={$this->antifraud_finger_print_org_id}&session_id={$this->antifraud_finger_print_session_id}", array(), '', false);
        wp_enqueue_script('wc-braspag-antifraud-fingerprint');


        $this->payment_scripts_auth3ds20();
	}

    /**
     * @throws WC_Braspag_Exception
     */
	public function payment_scripts_auth3ds20() {

        $auth3ds_params = apply_filters( 'wc_gateway_braspag_pagador_auth3ds20_params', array(
                'isTestEnvironment' => $this->test_mode
            )
        );

        if ($auth3ds_params['isBpmpiEnabledCC'] || $auth3ds_params['isBpmpiEnabledDC']) {

            wp_register_script('wc-braspag-auth3ds20-conf', plugins_url('assets/js/vendor/auth3ds20/BP.Mpi.3ds20.conf.js', WC_BRASPAG_MAIN_FILE), array(), WC_BRASPAG_VERSION, false);
            wp_enqueue_script('wc-braspag-auth3ds20-conf');

            wp_localize_script(
                'wc-braspag-auth3ds20-conf',
                'braspag_auth3ds20_params',
                $auth3ds_params
            );

            wp_register_script('wc-braspag-auth3ds20-lib', plugins_url('assets/js/vendor/auth3ds20/BP.Mpi.3ds20.lib.js', WC_BRASPAG_MAIN_FILE), array('wc-braspag-auth3ds20-conf'), WC_BRASPAG_VERSION, false);
            wp_enqueue_script('wc-braspag-auth3ds20-lib');

            wp_register_script('wc-braspag-auth3ds20-renderer', plugins_url('assets/js/braspag-auth3ds20-renderer.js', WC_BRASPAG_MAIN_FILE), array(), WC_BRASPAG_VERSION, true);
            wp_enqueue_script('wc-braspag-auth3ds20-renderer');

            wp_register_script('wc-braspag-auth3ds20', plugins_url('assets/js/braspag-auth3ds20.js', WC_BRASPAG_MAIN_FILE), array('wc-braspag-auth3ds20-conf', 'wc-braspag-auth3ds20-lib', 'wc-braspag-auth3ds20-renderer'), WC_BRASPAG_VERSION, true);
            wp_enqueue_script('wc-braspag-auth3ds20');

            wp_localize_script(
                'wc-braspag-auth3ds20',
                'braspag_auth3ds20_params',
                apply_filters( 'wc_gateway_braspag_pagador_auth3ds20_params', array(
                        'bpmpiToken' => $this->get_mpi_auth_token(),
                        'isTestEnvironment' => $this->test_mode,
                    )
                )
            );
        }
    }

    /**
     * @return bool|void
     */
	public function process_admin_options() {
		// Load all old values before the new settings get saved.
		$old_merchant_id      = $this->get_option( 'merchant_id' );
		$old_merchant_key           = $this->get_option( 'merchant_key' );

		parent::process_admin_options();

		// Load all old values after the new settings have been saved.
		$new_merchant_id      = $this->get_option( 'merchant_id' );
		$new_merchant_key           = $this->get_option( 'merchant_key' );

		// Checks whether a value has transitioned from a non-empty value to a new one.
		$has_changed = function( $old_value, $new_value ) {
			return ! empty( $old_value ) && ( $old_value !== $new_value );
		};

		// Look for updates.
		if (
			$has_changed( $old_merchant_id, $new_merchant_id )
			|| $has_changed( $old_merchant_key, $new_merchant_key )
		) {
			update_option( 'wc_braspag_show_changed_keys_notice', 'yes' );
		}
	}

    /**
     * @param $card_type
     * @param $testMode
     * @return mixed|string
     */
    public function get_braspag_payment_provider($card_type, $testMode) {
        $provider = '';

        if($testMode) {
            return 'Simulado';
        }

        if (empty($provider)) {
            $availableTypes = $this->get_available_payment_types_options();

            foreach($availableTypes as $key => $availableType) {
                $typeDetail = explode("-", $key);
                if (isset($typeDetail[1]) && $typeDetail[1] == $card_type) {
                    return $typeDetail[0];
                }
            }
        }

        return '';
    }

    /**
     * @param $order
     * @return array
     */
    public function get_customer_identity_data($order) {

        if ( '' === $order->get_meta( '_billing_persontype' ) ) {
            return '';
        }

        $customer_identity_type = $order->get_meta('_billing_persontype') == '1' ? 'CPF' : 'CNPJ';
        return [
            'type' => $customer_identity_type,
            'value' => preg_replace('/\D+/', '', $customer_identity_type == 'CPF' ? $order->get_meta('_billing_cpf') : $order->get_meta('_billing_cnpj'))
        ];
    }

    /**
     * @param $order
     * @return string
     */
    public function get_customer_birthdate_data($order) {
        if ( '' === $order->get_meta( '_billing_birthdate' ) ) {
            return '';
        }

        return $order->get_meta( '_billing_birthdate' );
    }

    /**
     * @param $order
     * @return array
     */
    public function get_braspag_pagador_request_customer_data($order){

        $customer_identity_data = $this->get_customer_identity_data($order);

        $billing_address = $order->get_address('billing');
        $shipping_address = $order->get_address('shipping');

        return [
            "Name" => $order->get_formatted_billing_full_name(),
            "Email" => $order->get_billing_email(),
            "Phone" => preg_replace('/\D+/', '', $order->get_billing_phone()),
            "Identity" => preg_replace('/\D+/', '', $customer_identity_data['value']),
            "IdentityType" => $customer_identity_data['type'],
            "Address" => [
                "Street" => $order->get_billing_address_1(),
                "Number" => $billing_address['number'],
                "Complement" => $billing_address['address_2'],
                "ZipCode" => $billing_address['postcode'],
                "City" => $billing_address['city'],
                "State" => $billing_address['state'],
                "Country" => $billing_address['country'] == 'BR' ? 'BRA' : '',
                "District" => $billing_address['neighborhood']
            ],
            "DeliveryAddress" => [
                "Street" => $order->get_shipping_address_1(),
                "Number" => $shipping_address['number'],
                "Complement" => $shipping_address['address_2'],
                "ZipCode" => $shipping_address['postcode'],
                "City" => $shipping_address['city'],
                "State" => $shipping_address['state'],
                "Country" => $shipping_address['country'] == 'BR' ? 'BRA' : '',
                "District" => $shipping_address['neighborhood']
            ]
        ];
    }

    /**
     * @param $payment_method
     * @param $order
     * @param $checkout
     * @param $cart
     * @return mixed|void
     */
    public function get_braspag_pagador_request_payment_data($payment_method, $order, $checkout, $cart) {

        return apply_filters( "wc_gateway_braspag_pagador_{$payment_method}_request_payment_builder", [], $order, $checkout, $cart);
    }

    /**
     * @param $payment_method
     * @param $order
     * @param $default_request_params
     * @return mixed|void
     */
    public function braspag_pagador_request_builder( $payment_method, $order, $default_request_params )
    {
        $checkout = WC()->checkout();
        $cart = WC()->cart;

        $request = [
            "MerchantOrderId" => $order->get_id(),
            "Customer" => $this->get_braspag_pagador_request_customer_data($order),
            "Payment" => $this->get_braspag_pagador_request_payment_data($payment_method, $order, $checkout, $cart)
        ];

        return apply_filters( 'wc_gateway_braspag_pagador_request_builder', $request, $order, $default_request_params );
    }

    /**
     * @param $order
     * @return array
     */
    public function complete_free_order( $order )
    {
        // Remove cart.
        WC()->cart->empty_cart();

        $order->payment_complete();

        // Return thank you page redirect.
        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order ),
        );
    }

    /**
     * @param $response
     * @param $order
     * @param array $options
     * @throws WC_Braspag_Exception
     */
    public function process_pagador_response( $response, $order, $options = array())
    {
        WC_Braspag_Logger::log( 'Processing response: ' . print_r( $response, true ) );

        do_action( 'wc_gateway_braspag_pagador_process_response_before', $response, $order );

        $order_id = WC_Braspag_Helper::is_wc_lt( '3.0' ) ? $order->id : $order->get_id();
        $captured = (( isset( $response->body->Payment->Capture ) && $response->body->Payment->Capture )) || ($this->antifraud_enabled && in_array($response->body->Payment->Status, ['2'] )) ? 'yes' : 'no';

        if ($this->antifraud_enabled && isset($response->body->Payment->FraudAnalysis)) {
            $this->antifraud_status = $response->body->Payment->FraudAnalysis->Status;
        }

        // Store charge data.
        WC_Braspag_Helper::is_wc_lt( '3.0' ) ? update_post_meta( $order_id, '_braspag_charge_captured', $captured ) : $order->update_meta_data( '_braspag_charge_captured', $captured );

        if ( 'yes' === $captured ) {

            if ( in_array($response->body->Payment->Status, ['2', '20'] )) {
                $order->payment_complete($response->body->Payment->PaymentId);

                /* translators: transaction id */
                $message = sprintf( __( 'Braspag charge complete (Charge ID: %s)', 'woocommerce-braspag' ), $response->body->Payment->PaymentId );
                $order->add_order_note( $message );
            }

        } else {

            if ( in_array($response->body->Payment->Status, ['1', '20'] )) {

                WC_Braspag_Helper::is_wc_lt( '3.0' ) ? update_post_meta( $order_id, '_transaction_id', $response->body->Payment->PaymentId ) : $order->set_transaction_id( $response->body->Payment->PaymentId );

                $payment_status = 'on-hold';

                if ($this->antifraud_enabled && isset($response->body->Payment->FraudAnalysis)) {
                    $this->antifraud_status = $response->body->Payment->FraudAnalysis->Status;

                    switch ($this->antifraud_status) {
                        case '2':
                            if (isset($options['antifraud_reject_order_status']) && !empty($options['antifraud_reject_order_status'])) {
                                $payment_status = $options['antifraud_reject_order_status'];
                            }
                            break;
                        case '3':
                            if (isset($options['antifraud_review_order_status']) && !empty($options['antifraud_review_order_status'])) {
                                $payment_status = $options['antifraud_review_order_status'];
                            }
                            break;
                    }
                }

                /* translators: transaction id */
                $order->update_status( $payment_status, sprintf( __( 'Braspag charge authorized (Charge ID: %s). Process order to take payment, or cancel to remove the pre-authorization.', 'woocommerce-braspag' ), $response->body->Payment->PaymentId ) );

            } elseif ( in_array($response->body->Payment->Status, ['12'] )) {

                WC_Braspag_Helper::is_wc_lt( '3.0' ) ? update_post_meta( $order_id, '_transaction_id', $response->body->Payment->PaymentId ) : $order->set_transaction_id( $response->body->Payment->PaymentId );

                /* translators: transaction id */
                $order->update_status( 'pending', sprintf( __( 'Braspag charge pending (Charge ID: %s).', 'woocommerce-braspag' ), $response->body->Payment->PaymentId ) );

            } else {

                $localized_message = __( 'Payment processing failed.', 'woocommerce-braspag' ). " ".$response->body->Payment->ProviderReturnMessage." (Cod. ".$response->body->Payment->ProviderReturnCode.").";
                $order->add_order_note( $localized_message );
                throw new WC_Braspag_Exception( print_r( $response, true ), $localized_message );
            }
        }

        $order->set_transaction_id($response->body->Payment->PaymentId);

        if ( is_callable( array( $order, 'save' ) ) ) {
            $order->save();
        }

        do_action( 'wc_gateway_braspag_pagador_process_response_after', $response, $order );

        return $response;
    }

    /**
     * @param $user_id
     * @param $load_address
     */
    public function show_update_card_notice( $user_id, $load_address ) {
        if ('billing' !== $load_address ) {
            return;
        }

        /* translators: 1) Opening anchor tag 2) closing anchor tag */
        wc_add_notice( sprintf( __( 'If your billing address has been changed for saved payment methods, be sure to remove any %1$ssaved payment methods%2$s on file and re-add them.', 'woocommerce-braspag' ), '<a href="' . esc_url( wc_get_endpoint_url( 'payment-methods' ) ) . '" class="wc-braspag-update-card-notice" style="text-decoration:underline;">', '</a>' ), 'notice' );
    }

    /**
     * @param $response
     * @return mixed
     */
    public function get_localized_error_message_from_response( $response ) {
        $localized_messages = WC_Braspag_Helper::get_localized_messages();

        if ( 'card_error' === $response->error->type ) {
            $localized_message = isset( $localized_messages[ $response->error->code ] ) ? $localized_messages[ $response->error->code ] : $response->error->message;
        } else {
            $localized_message = isset( $localized_messages[ $response->error->type ] ) ? $localized_messages[ $response->error->type ] : $response->error->message;
        }

        return $localized_message;
    }

    /**
     * @param $response
     * @param $order
     * @param string $localized_message
     * @throws WC_Braspag_Exception
     */
    public function throw_localized_message( $response, $order, $localized_message = '') {

        if (!empty($response)) {
            $localized_message = $this->get_localized_error_message_from_response( $response );
        }

        $order->add_order_note( $localized_message );

        throw new WC_Braspag_Exception( print_r( $response, true ), $localized_message );
    }

    /**
     * @param $response
     * @param $order
     * @param $retry
     * @param $previous_error
     * @param $use_order_source
     * @return array|void
     * @throws WC_Braspag_Exception
     */
    public function retry_after_error( $response, $order, $retry, $previous_error, $use_order_source ) {
        if ( ! $retry ) {
            $localized_message = __( 'Sorry, we are unable to process your payment at this time. Please retry later.', 'woocommerce-braspag' );
            $order->add_order_note( $localized_message );
            throw new WC_Braspag_Exception( print_r( $response, true ), $localized_message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.
        }

        // Don't do anymore retries after this.
        if ( 5 <= $this->retry_interval ) {
            return $this->process_payment( $order->get_id(), false, $response->errors, $previous_error );
        }

        sleep( $this->retry_interval );
        $this->retry_interval++;

        return $this->process_payment( $order->get_id(), true, $response->error, $previous_error, $use_order_source );
    }
}
