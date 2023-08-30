<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Gateway_Braspag class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Braspag_DebitCard extends WC_Gateway_Braspag
{
    public $enabled;
    public $test_mode;
    protected $label_pay_button;
    protected $bank_automatic_redirect;
    protected $bank_return_url;
    protected $available_types;
    protected $auth3ds20_mpi_is_active;
    protected $auth3ds20_mpi_mastercard_notify_only;
    protected $auth3ds20_mpi_authorize_on_error;
    protected $auth3ds20_mpi_authorize_on_failure;
    protected $auth3ds20_mpi_authorize_on_unenrolled;
    protected $auth3ds20_mpi_authorize_on_unsupported_brand;

    public function __construct()
    {
        $this->retry_interval = 1;
        $this->id = 'braspag_debitcard';
        $this->method_title = __('Braspag Debit Card', 'woocommerce-braspag');
        $this->method_description = __('Take payments via Debit Card with Braspag.');
        /* translators: 1) link to Braspag register page 2) link to Braspag api keys page */
        $this->has_fields = true;
        $this->supports = array(
            'add_payment_method'
        );

        $this->init_form_fields();

        $this->init_settings();

        // Load the settings extra data collection.
        $this->settings_extra_data();

        $braspag_main_settings = get_option('woocommerce_braspag_settings');

        $braspag_enabled = isset($braspag_main_settings['enabled']) ? $braspag_main_settings['enabled'] : 'no';
        $test_mode = isset($braspag_main_settings['test_mode']) ? $braspag_main_settings['test_mode'] : 'no';

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->soft_descriptor = substr($this->get_option('SoftDescriptor'), 0, 13);
        $this->enabled = $braspag_enabled == 'yes' ? $this->get_option('enabled') : 'no';
        $this->test_mode = $test_mode == 'yes';
        $this->bank_automatic_redirect = $this->get_option('bank_automatic_redirect', 'no') == 'yes';
        $this->available_types = $this->get_option('available_types', array());
        $this->label_pay_button = $this->get_option('label_pay_button', 'Pay');
        $this->bank_return_url = $this->get_option('bank_return_url', '');

        $this->auth3ds20_mpi_is_active = $this->get_option('auth3ds20_mpi_is_active', 'no');
        $this->auth3ds20_mpi_mastercard_notify_only = $this->get_option('auth3ds20_mpi_mastercard_notify_only', 'no');
        $this->auth3ds20_mpi_authorize_on_error = $this->get_option('auth3ds20_mpi_authorize_on_error', 'no');
        $this->auth3ds20_mpi_authorize_on_failure = $this->get_option('auth3ds20_mpi_authorize_on_failure', 'no');
        $this->auth3ds20_mpi_authorize_on_unenrolled = $this->get_option('auth3ds20_mpi_authorize_on_unenrolled', 'no');
        $this->auth3ds20_mpi_authorize_on_unsupported_brand = $this->get_option('auth3ds20_mpi_authorize_on_unsupported_brand', 'no');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_customer_save_address', array($this, 'show_update_card_notice'), 10, 2);

        add_action('woocommerce_order_details_after_order_table', array($this, 'display_order_debitcard_data'));

        add_action('wc_gateway_braspag_pagador_debitcard_process_payment_after', array($this, 'save_payment_response_data'), 10, 3);
        add_filter("wc_gateway_braspag_pagador_{$this->id}_request_payment_builder", array($this, 'braspag_pagador_debitcard_payment_request_builder'), 10, 4);

        add_filter('wc_gateway_braspag_pagador_request_debitcard_payment_builder', array($this, 'braspag_pagador_debitcard_payment_request_auth3ds20_builder'), 10, 4);

        add_filter('wc_gateway_braspag_pagador_auth3ds20_params', array($this, 'get_auth3ds20_params'), 10, 1);

        add_action('wc_gateway_braspag_pagador_debitcard_process_payment_validation', array($this, 'process_payment_validation'), 10, 1);
    }

    /**
     * @return bool
     */
    public function is_available()
    {
        return $this->enabled == 'yes';
    }

    /**
     * @return mixed|string|void
     */
    public function get_icon()
    {
        $available_types = $this->get_available_payment_types_options();
        $icons = $this->payment_icons();

        $icons_str = '';

        foreach ($available_types as $available_key_type => $available_type) {
            $available_brand = explode("-", $available_key_type);

            if (!$available_brand[1] || !isset($icons[strtolower($available_brand[1])])) {
                continue;
            }

            $brand = strtolower($available_brand[1]);

            if ($brand == 'master') {
                $brand = 'maestro';
            }

            $icons_str .= $icons[$brand];
        }

        return apply_filters('woocommerce_gateway_icon', $icons_str, $this->id);
    }

    public function init_form_fields()
    {
        $this->form_fields = require(WC_BRASPAG_PLUGIN_PATH . '/includes/admin/braspag-debitcard-settings.php');
    }

    public function payment_fields()
    {
        ob_start();

        echo '<div id="braspag-debitcard-payment-data">';

        do_action('wc_gateway_braspag_pagador_debitcard_payment_fields_before', $this->id);

        $descriptionText = '';
        if ($this->test_mode) {
            /* translators: link to Braspag testing page */
            $descriptionText .= ' ' . __('TEST MODE ENABLED.') . '</br></br>';
        }
        $description = $this->get_description();
        $descriptionText .= !empty($description) ? $description : '';

        $descriptionText = trim($descriptionText);

        echo apply_filters('wc_braspag_description', wpautop(wp_kses_post($descriptionText)), $this->id); // wpcs: xss ok.

        $this->elements_form();

        do_action('wc_gateway_braspag_pagador_debitcard_payment_fields_after', $this->id);

        echo '</div>';

        ob_end_flush();
    }

    public function elements_form()
    {
        wp_enqueue_script('wc-credit-card-form');

        $fields = array();

        do_action('wc_gateway_braspag_pagador_debitcard_elements_form_before', $this->id);

        $default_fields = array(
            'debitcard-holder-field' => '<p class="form-row form-row-wide">
				<label for="' . esc_attr($this->id) . '-card-holder">' . esc_html__('Nome do Titular', 'woocommerce') . '&nbsp;<span class="required">*</span></label>
				<input id="' . esc_attr($this->id) . '-card-holder" class="input-text wc-braspag-elements-field wc-credit-card-form-card-holder" inputmode="string" autocomplete="cc-holder" autocorrect="no" type="text" autocapitalize="no" spellcheck="no" type="holder" ' . $this->field_name('card-holder') . ' />
			</p>',
            'debitcard-number-field' => '<p class="form-row form-row-wide">
				<label for="' . esc_attr($this->id) . '-card-number">' . esc_html__('Número do Cartão', 'woocommerce') . '&nbsp;<span class="required">*</span></label>
				<input id="' . esc_attr($this->id) . '-card-number" class="input-text wc-credit-card-form-braspag-card-number" onkeypress="" inputmode="numeric" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" ' . $this->field_name('card-number') . ' />
			</p>',
            'debitcard-expiry-field' => '<p class="form-row form-row-wide">
				<label for="' . esc_attr($this->id) . '-card-expiry">' . esc_html__('Data de Expiração (MM/YY)', 'woocommerce') . '&nbsp;<span class="required">*</span></label>
				<input id="' . esc_attr($this->id) . '-card-expiry" class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" autocomplete="cc-exp" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="' . esc_attr__('MM / YY', 'woocommerce') . '" ' . $this->field_name('card-expiry') . ' />
			</p>',
            'debitcard-cvc-field' => '<p class="form-row form-row-wide">
                <label for="' . esc_attr($this->id) . '-card-cvc">' . esc_html__('Código de Segurança', 'woocommerce') . '&nbsp;<span class="required">*</span></label>
                <input id="' . esc_attr($this->id) . '-card-cvc" class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="4" placeholder="' . esc_attr__('CVC', 'woocommerce') . '" ' . $this->field_name('card-cvc') . ' style="width:100px" />
            </p>',
            'debitcard-type-field' => '<input type="hidden" id="' . esc_attr($this->id) . '-card-type" class="wc-credit-card-form-card-type" ' . $this->field_name('card-type') . ' />'
        );

        $fields = apply_filters('wc_gateway_braspag_pagador_debitcard_elements_form_filter', $fields);

        $fields = wp_parse_args($fields, apply_filters('woocommerce_credit_card_form_fields', $default_fields, $this->id));
        ?>

                        <fieldset id="wc-<?php echo esc_attr($this->id); ?>-cc-form" class='wc-credit-card-form wc-payment-form'>
                            <?php do_action('woocommerce_debit_card_form_start', $this->id); ?>
                            <?php
                            foreach ($fields as $field) {
                                echo $field; // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
                            }
                            ?>
                            <?php do_action('woocommerce_debit_card_form_end', $this->id); ?>
                            <div class="clear"></div>
                        </fieldset>

                    <?php

                    do_action('wc_gateway_braspag_pagador_debitcard_elements_form_after', $this->id);
    }

    /**
     * @param int $order_id
     * @param bool $retry
     * @param bool $previous_error
     * @param bool $use_order_source
     * @return array|void
     */
    public function process_payment($order_id, $retry = true, $previous_error = false, $use_order_source = false)
    {
        try {
            do_action('wc_gateway_braspag_pagador_debitcard_process_payment_before', $order_id, $retry, $previous_error, $use_order_source);

            $order = wc_get_order($order_id);

            do_action('wc_gateway_braspag_pagador_debitcard_process_payment_validation', $order);

            $default_request_params = $this->braspag_pagador_get_default_request_params(get_current_user_id());

            if (0 >= $order->get_total()) {
                return $this->complete_free_order($order);
            }

            WC_Braspag_Logger::log("Info: Begin processing payment for order $order_id for the amount of {$order->get_total()}");

            $request_builder = $this->braspag_pagador_request_builder($this->id, $order, $default_request_params);

            $response = $this->braspag_pagador_request($request_builder, 'v2/sales/', $default_request_params);

            if (empty($response->errors)) {
                $this->lock_order_payment($order, $response);
            }

            if (!empty($response->errors)) {

                if ($this->is_retryable_error($response)) {
                    return $this->retry_after_error($response, $order, $retry, $previous_error, $use_order_source);
                }

                $this->unlock_order_payment($order);
                $this->throw_localized_message($response, $order);
            }

            $this->process_pagador_response(
                $response,
                $order,
            [
                'antifraud_review_order_status' => $this->get_option('antifraud_review_order_status'),
                'antifraud_reject_order_status' => $this->get_option('antifraud_reject_order_status')
            ]
            );

            if (isset(WC()->cart)) {
                WC()->cart->empty_cart();
            }

            $this->unlock_order_payment($order);

            do_action('wc_gateway_braspag_pagador_debitcard_process_payment_after', $order_id, $order, $response);

            $this->save_payment_response_data($order_id, $order, $response);

            $redirect_url = $this->get_return_url($order);
            if ($this->bank_automatic_redirect == 'yes') {
                $redirect_url = $order->get_meta('_braspag_debitcard_authentication_url');
            }

            return array(
                'result' => 'success',
                'redirect' => $redirect_url,
            );
        }
        catch (WC_Braspag_Exception $e) {
            wc_add_notice($e->getLocalizedMessage(), 'error');
            WC_Braspag_Logger::log('Error: ' . $e->getMessage());

            do_action('wc_gateway_braspag_pagador_process_payment_error', $e, $order);

            /* translators: error message */
            $order->update_status('failed');

            $statuses = array('failed');

            if ($order->has_status($statuses)) {
                $this->send_failed_order_email($order_id);
            }

            return array(
                'result' => 'fail',
                'redirect' => '',
            );
        }
    }

    /**
     * @param $order
     * @throws WC_Braspag_Exception
     */
    public function process_payment_validation($order)
    {
        $checkout = WC()->checkout();

        if ($this->auth3ds20_mpi_is_active == 'yes') {

            $failureType = $checkout->get_value('bpmpi_auth_failure_type');

            if (
            $failureType == '4'
            && $this->auth3ds20_mpi_authorize_on_error == 'no'
            ) {
                throw new WC_Braspag_Exception(
                    print_r([], true),
                    __("Debit Card Payment Failure. #MPI{$failureType}")
                    );
            }

            if (
            $failureType == '1'
            && $this->auth3ds20_mpi_authorize_on_failure == 'no'
            ) {
                throw new WC_Braspag_Exception(
                    print_r([], true),
                    __("Debit Card Payment Failure. #MPI{$failureType}")
                    );
            }

            if (
            $failureType == '2'
            && $this->auth3ds20_mpi_authorize_on_unenrolled == 'no'
            ) {
                throw new WC_Braspag_Exception(
                    print_r([], true),
                    __("Debit Card Payment Failure. #MPI{$failureType}")
                    );
            }

            if (
            $failureType == '5'
            && $this->auth3ds20_mpi_authorize_on_unsupported_brand == 'no'
            ) {
                throw new WC_Braspag_Exception(
                    print_r([], true),
                    __("Debit Card Payment Failure. #MPI{$failureType}")
                    );
            }

            $provider = $this
                ->get_braspag_payment_provider(
                $checkout->get_value('braspag_debitcard-card-type'),
                $this->test_mode
            );

            if (
            !$this->test_mode
            && !preg_match("#cielo#is", $provider)
            && $failureType != '3'
            ) {
                throw new WC_Braspag_Exception(
                    print_r([], true),
                    __("Debit Card Payment Failure. #MPI{$failureType}")
                    );
            }
        }
    }

    /**
     * @param $response
     * @param $order
     * @param array $options
     * @throws WC_Braspag_Exception
     */
    public function process_pagador_response($response, $order, $options = array())
    {
        WC_Braspag_Logger::log('Processing response: ' . print_r($response, true));

        do_action('wc_gateway_braspag_pagador_process_response_before', $response, $order);

        $order_id = WC_Braspag_Helper::is_wc_lt('3.0') ? $order->id : $order->get_id();

        if (in_array($response->body->Payment->Status, ['2'])) {

            WC_Braspag_Helper::is_wc_lt('3.0') ? update_post_meta($order_id, '_braspag_charge_captured', 'yes') : $order->update_meta_data('_braspag_charge_captured', 'yes');

            $order->payment_complete($response->body->Payment->PaymentId);

            /* translators: transaction id */
            $message = sprintf(__('Braspag charge complete (Charge ID: %s)', 'woocommerce-braspag'), $response->body->Payment->PaymentId);
            $order->add_order_note($message);
        }
        elseif (in_array($response->body->Payment->Status, ['0', '1', '12'])) {

            WC_Braspag_Helper::is_wc_lt('3.0') ? update_post_meta($order_id, '_transaction_id', $response->body->Payment->PaymentId) : $order->set_transaction_id($response->body->Payment->PaymentId);

            if ($order->has_status(array('pending', 'failed'))) {
                WC_Braspag_Helper::is_wc_lt('3.0') ? $order->reduce_order_stock() : wc_reduce_stock_levels($order_id);
            }

            /* translators: transaction id */
            $order->update_status('on-hold', sprintf(__('Braspag charge authorized (Charge ID: %s). Process order to take payment, or cancel to remove the pre-authorization.', 'woocommerce-braspag'), $response->body->Payment->PaymentId));
        }
        else {

            $localized_message = __('Payment processing failed. Please retry.', 'woocommerce-braspag');
            $order->add_order_note($localized_message);
            throw new WC_Braspag_Exception(print_r($response, true), $localized_message);
        }

        $order->set_transaction_id($response->body->Payment->PaymentId);

        if (is_callable(array($order, 'save'))) {
            $order->save();
        }

        do_action('wc_gateway_braspag_pagador_process_response_after', $response, $order);

        return $response;
    }

    /**
     * @param $order_id
     * @param $order
     * @param $response
     * @return $this
     */
    public function save_payment_response_data($order_id, $order, $response)
    {
        $dataToSave = [
            "_braspag_debitcard_acquirer_transaction_id" => $response->body->Payment->AcquirerTransactionId,
            "_braspag_debitcard_authentication_url" => $response->body->Payment->AuthenticationUrl
        ];

        $dataToSave = array_merge($dataToSave, apply_filters('wc_gateway_braspag_pagador_debitcard_save_payment_response_data', $dataToSave, $order, $response));

        foreach ($dataToSave as $key => $data) {
            $order->add_meta_data($key, $data, false);
        }

        $order->save();

        return $this;
    }

    /**
     * @param $order
     * @param $checkout
     * @param $cart
     * @return array
     */
    public function braspag_pagador_debitcard_payment_request_builder($payment_data, $order, $checkout, $cart)
    {
        $card_expiration_date = str_replace(" ", "", $checkout->get_value('braspag_debitcard-card-expiry'));

        if (preg_match("#^\d{2}\/\d{2}$#is", $card_expiration_date)) {
            $card_expiration_date_exploded = explode("/", $card_expiration_date);
            $card_expiration_date = $card_expiration_date_exploded[0] . "/20" . $card_expiration_date_exploded[1];
        }

        $card_data = [
            "CardNumber" => str_replace(" ", "", $checkout->get_value('braspag_debitcard-card-number')),
            "Holder" => $checkout->get_value('braspag_debitcard-card-holder'),
            "ExpirationDate" => $card_expiration_date,
            "SecurityCode" => $checkout->get_value('braspag_debitcard-card-cvc'),
            "Brand" => $checkout->get_value('braspag_debitcard-card-type')
        ];

        $card_type = $checkout->get_value('braspag_debitcard-card-type');
        $provider = $this->get_braspag_payment_provider($card_type, $this->test_mode);

        $authenticate = ($this->auth3ds20_mpi_is_active) ? true : false;

        if (isset($this->soft_descriptor) && !empty($this->soft_descriptor)) {
            $payment_data['SoftDescriptor'] = $this->soft_descriptor;
        }

        $payment_data = array_merge($payment_data, [
            "Provider" => $provider,
            "Type" => "DebitCard",
            "Amount" => intval($order->get_total() * 100),
            "Installments" => '1',
            "ReturnUrl" => sprintf($this->bank_return_url, $order->get_id()),
            "DebitCard" => $card_data,
            "Authenticate" => $authenticate,
            "Currency" => "BRL",
            "Country" => "BRA",
            "Interest" => "ByMerchant",
            "Capture" => true,
            "Recurrent" => false,
            "DoSplit" => false,
            "ExtraDataCollection" => $this->extra_data_collection
        ]);
       
        return apply_filters('wc_gateway_braspag_pagador_request_debitcard_payment_builder', $payment_data, $order, $checkout, $cart);
    }

    /**
     * @param $payment_data
     * @param $cart
     * @param $order
     * @return array
     */
    public function braspag_pagador_debitcard_payment_request_auth3ds20_builder($payment_data, $order, $checkout, $cart)
    {
        if ('yes' !== $this->auth3ds20_mpi_is_active) {
            return $payment_data;
        }

        $payment_data_auth3ds20_data = [
            "Cavv" => $checkout->get_value('bpmpi_auth_cavv'),
            "Xid" => $checkout->get_value('bpmpi_auth_xid'),
            "Eci" => $checkout->get_value('bpmpi_auth_eci'),
            "Version" => $checkout->get_value('bpmpi_auth_version'),
            "ReferenceID" => $checkout->get_value('bpmpi_auth_reference_id')
        ];

        $payment_data_external_authentication_data = apply_filters(
            'wc_gateway_braspag_pagador_request_debitcard_payment_auth3ds20_builder',
            $payment_data_auth3ds20_data,
            $order,
            $checkout,
            $cart
        );

        $payment_data['ExternalAuthentication'] = $payment_data_external_authentication_data;

        return $payment_data;
    }

    /**
     * @return array
     */
    public function get_debitcard_payment_types_options()
    {
        return [
            'Cielo-Visa' => 'Cielo Visa',
            'Cielo-Master' => 'Cielo Master',
            'Cielo30-Visa' => 'Cielo 3.0 Visa',
            'Cielo30-Master' => 'Cielo 3.0 Master',
            'Getnet-Visa' => 'Getnet Visa',
            'Getnet-Master' => 'Getnet Master',
            'FirstData-Visa' => 'FirstData Visa',
            'FirstData-Master' => 'FirstData Master',
            'GlobalPayments-Visa' => 'GlobalPayments Visa',
            'GlobalPayments-Master' => 'GlobalPayments Master',
            'Simulado-Simulado' => 'Simulado',
        ];
    }

    /**
     * @param $order
     * @return |null
     */
    public function display_order_debitcard_data($order)
    {
        if ($order->get_payment_method() != $this->id || in_array($order->get_status(), ['processing', 'completed'])) {
            return null;
        }

        ?>

                        <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
                            <tfoot>
                                <tr class="woocommerce-table__line-item order_item">
                                    <td class="woocommerce-table__product-total product-total text-center" colspan="2">
                                        <a href="<?php echo $order->get_meta('_braspag_debitcard_authentication_url'); ?>" target="_blank">
                                            <b><?php echo $this->label_pay_button ?></b>
                                        </a>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                <?php
    }

    /**
     * @return array
     */
    public function get_available_payment_types_options()
    {
        $creditCardPaymentTypes = $this->get_debitcard_payment_types_options();

        $availableTypes = [];
        foreach ($this->available_types as $paymentType) {
            if (isset($creditCardPaymentTypes[$paymentType])) {
                $availableTypes[$paymentType] = $creditCardPaymentTypes[$paymentType];
            }
        }

        return $availableTypes;
    }

    /**
     * @param $params
     * @return mixed
     */
    public function get_auth3ds20_params($params)
    {
        $params['isBpmpiEnabledDC'] = $this->auth3ds20_mpi_is_active === 'yes';
        $params['isBpmpiMasterCardNotifyOnlyEnabledDC'] = $this->auth3ds20_mpi_mastercard_notify_only === 'yes';
        return $params;
    }
}