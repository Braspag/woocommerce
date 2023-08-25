<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WC_Gateway_Braspag_CreditCard
 */
class WC_Gateway_Braspag_CreditCard extends WC_Gateway_Braspag
{
    public $enabled;

    protected $test_mode;

    protected $capture;

    protected $save_card;

    protected $available_types;

    protected $maximum_installments;

    protected $minimum_amount_of_installment;

    protected $merchant_category;

    protected $antifraud_enabled;

    protected $antifraud_send_with_pagador_transaction;

    protected $antifraud_options_sequence;

    protected $antifraud_options_sequence_criteria;

    protected $antifraud_options_capture_on_low_risk;

    protected $antifraud_options_void_on_righ_risk;

    protected $antifraud_finger_print_org_id;

    protected $antifraud_finger_print_merchant_id;

    protected $antifraud_finger_print_use_order_id;

    protected $antifraud_finger_print_id;

    protected $antifraud_finger_print_session_id;

    protected $auth3ds20_mpi_is_active;

    protected $auth3ds20_mpi_mastercard_notify_only;

    protected $auth3ds20_mpi_authorize_on_error;

    protected $auth3ds20_mpi_authorize_on_failure;

    protected $auth3ds20_mpi_authorize_on_unenrolled;

    protected $auth3ds20_mpi_authorize_on_unsupported_brand;

    public function __construct()
    {
        $this->retry_interval = 1;
        $this->id = 'braspag_creditcard';
        $this->method_title = __('Braspag Credit Card', 'woocommerce-braspag');
        $this->method_description = __('Take payments via Credit Card with Braspag.');
        /* translators: 1) link to Braspag register page 2) link to Braspag api keys page */
        $this->has_fields = true;
        $this->supports = array(
            'add_payment_method',
            'tokenization'
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
        $this->available_types = $this->get_option('available_types', array());
        $this->maximum_installments = $this->get_option('maximum_installments');
        $this->minimum_amount_of_installment = $this->get_option('minimum_amount_of_installment');

        $this->merchant_category = $this->get_option('merchant_category');

        $this->antifraud_enabled = isset($braspag_main_settings['antifraud_enabled']) ? $braspag_main_settings['antifraud_enabled'] : 'no';
        $this->antifraud_send_with_pagador_transaction = isset($braspag_main_settings['antifraud_send_with_pagador_transaction']) ? $braspag_main_settings['antifraud_send_with_pagador_transaction'] : 'no';

        $this->antifraud_options_sequence = isset($braspag_main_settings['antifraud_options_sequence']) ? $braspag_main_settings['antifraud_options_sequence'] : '';
        $this->antifraud_options_sequence_criteria = isset($braspag_main_settings['antifraud_options_sequence_criteria']) ? $braspag_main_settings['antifraud_options_sequence_criteria'] : '';
        $this->antifraud_options_capture_on_low_risk = isset($braspag_main_settings['antifraud_options_capture_on_low_risk']) ? $braspag_main_settings['antifraud_options_capture_on_low_risk'] : 'no';
        $this->antifraud_options_void_on_righ_risk = isset($braspag_main_settings['antifraud_options_void_on_righ_risk']) ? $braspag_main_settings['antifraud_options_void_on_righ_risk'] : 'no';
        $this->antifraud_finger_print_org_id = isset($braspag_main_settings['antifraud_finger_print_org_id']) ? $braspag_main_settings['antifraud_finger_print_org_id'] : '';
        $this->antifraud_finger_print_merchant_id = isset($braspag_main_settings['antifraud_finger_print_merchant_id']) ? $braspag_main_settings['antifraud_finger_print_merchant_id'] : '';
        $this->antifraud_finger_print_use_order_id = isset($braspag_main_settings['antifraud_finger_print_use_order_id']) ? $braspag_main_settings['antifraud_finger_print_use_order_id'] : '';

        $this->auth3ds20_mpi_is_active = $this->get_option('auth3ds20_mpi_is_active', 'no');
        $this->auth3ds20_mpi_mastercard_notify_only = $this->get_option('auth3ds20_mpi_mastercard_notify_only', 'no');
        $this->auth3ds20_mpi_authorize_on_error = $this->get_option('auth3ds20_mpi_authorize_on_error', 'no');
        $this->auth3ds20_mpi_authorize_on_failure = $this->get_option('auth3ds20_mpi_authorize_on_failure', 'no');
        $this->auth3ds20_mpi_authorize_on_unenrolled = $this->get_option('auth3ds20_mpi_authorize_on_unenrolled', 'no');
        $this->auth3ds20_mpi_authorize_on_unsupported_brand = $this->get_option('auth3ds20_mpi_authorize_on_unsupported_brand', 'no');

        $this->capture = 'authorize_capture' === $this->get_option('payment_action', 'authorize');
        $this->save_card = $this->get_option('save_card');

        if (WC()->cart) {
            $this->antifraud_finger_print_id = WC()->cart->get_cart_hash();
        }

        $this->antifraud_finger_print_session_id = $this->antifraud_finger_print_merchant_id . $this->antifraud_finger_print_id;

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_customer_save_address', array($this, 'show_update_card_notice'), 10, 2);

        add_action('woocommerce_order_details_after_order_table', array($this, 'display_order_creditcard_data'));

        add_action('wc_gateway_braspag_pagador_creditcard_process_payment_after', array($this, 'save_payment_response_data'), 10, 3);

        add_filter("wc_gateway_braspag_pagador_{$this->id}_request_payment_builder", array($this, 'braspag_pagador_creditcard_payment_request_builder'), 10, 4);

        add_filter('wc_gateway_braspag_pagador_request_creditcard_payment_builder', array($this, 'braspag_pagador_creditcard_payment_request_antifraud_builder'), 10, 4);

        add_filter('wc_gateway_braspag_pagador_request_creditcard_payment_builder', array($this, 'braspag_pagador_creditcard_payment_request_auth3ds20_builder'), 10, 4);

        add_filter('wc_gateway_braspag_pagador_auth3ds20_params', array($this, 'get_auth3ds20_params'), 10, 1);

        add_action('wc_gateway_braspag_pagador_creditcard_process_payment_validation', array($this, 'process_payment_validation'), 10, 1);
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
        $availableTypes = $this->get_available_payment_types_options();
        $icons = $this->payment_icons();
        $icons_str = '';

        foreach ($availableTypes as $availableKeyType => $availableType) {
            $availableBrand = explode("-", $availableKeyType);

            if (!$availableBrand[1] || !isset($icons[strtolower($availableBrand[1])])) {
                continue;
            }

            $icons_str .= $icons[strtolower($availableBrand[1])];
        }

        return apply_filters('woocommerce_gateway_icon', $icons_str, $this->id);
    }

    public function init_form_fields()
    {
        $this->form_fields = require(WC_BRASPAG_PLUGIN_PATH . '/includes/admin/braspag-creditcard-settings.php');
    }

    public function payment_fields()
    {
        $display_tokenization = $this->supports('tokenization') && is_checkout() && $this->save_card == 'yes';

        ob_start();

        echo '<div id="braspag-creditcard-payment-data">';

        do_action('wc_gateway_braspag_pagador_creditcard_payment_fields_before', $this->id);

        $descriptionText = '';
        if ($this->test_mode) {
            /* translators: link to Braspag testing page */
            $descriptionText .= ' ' . __('TEST MODE ENABLED.') . '</br></br>';
        }
        $description = $this->get_description();
        $descriptionText .= !empty($description) ? $description : '';

        $descriptionText = trim($descriptionText);

        echo apply_filters('wc_braspag_description', wpautop(wp_kses_post($descriptionText)), $this->id); // wpcs: xss ok.

        if ($display_tokenization) {
            $this->tokenization_script();
            $this->saved_payment_methods();
        }

        $this->elements_form();

        if (
        apply_filters('wc_gateway_braspag_display_save_payment_method_checkbox', $display_tokenization)
        && !is_add_payment_method_page() && !isset($_GET['change_payment_method'])
        ) { // wpcs: csrf ok.
            $this->save_payment_method_checkbox();
        }

        do_action('wc_gateway_braspag_pagador_creditcard_payment_fields_after', $this->id);

        echo '</div>';

        ob_end_flush();
    }

    public function get_installments()
    {
        $installments = $this->maximum_installments;
        $installmentsMinAmount = $this->minimum_amount_of_installment;

        if (empty($installments)) {
            return [];
        }

        $return = array();
        $installments++;

        $grandTotal = WC()->cart->get_cart_contents_total();

        for ($i = 1; $i < $installments; $i++) {
            $installmentAmount = $grandTotal / $i;

            if ($i > 1 && $installmentAmount < $installmentsMinAmount) {
                break;
            }

            $return[$i] = sprintf('%1$s x R$ %2$s', $i, number_format($installmentAmount, wc_get_price_decimals(), ',', ''));
        }

        return $return;
    }

    public function elements_form()
    {
        wp_enqueue_script('wc-credit-card-form');

        $fields = array();

        do_action('wc_gateway_braspag_pagador_creditcard_elements_form_before', $this->id);

        $installmentsOptions = '';
        foreach ($this->get_installments() as $key => $installment) {
            $installmentsOptions .= "<option value=" . $key . ">" . $installment . "</option>";
        }

        $default_fields = array(
            'creditcard-holder-field' => '<p class="form-row form-row-wide">
				<label for="' . esc_attr($this->id) . '-card-holder">' . esc_html__('Nome do Titular', 'woocommerce-braspag') . '&nbsp;<span class="required">*</span></label>
				<input id="' . esc_attr($this->id) . '-card-holder" class="input-text wc-braspag-elements-field wc-credit-card-form-card-holder" inputmode="string" autocomplete="cc-holder" autocorrect="no" type="text" autocapitalize="no" spellcheck="no" type="holder" ' . $this->field_name('card-holder') . ' />
			</p>',
            'creditcard-number-field' => '<p class="form-row form-row-wide">
				<label for="' . esc_attr($this->id) . '-card-number">' . esc_html__('Número do Cartão', 'woocommerce-braspag') . '&nbsp;<span class="required">*</span></label>
				<input id="' . esc_attr($this->id) . '-card-number" class="input-text wc-credit-card-form-braspag-card-number" onkeypress="" inputmode="numeric" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" ' . $this->field_name('card-number') . ' />
			</p>',
            'creditcard-expiry-field' => '<p class="form-row form-row-wide">
				<label for="' . esc_attr($this->id) . '-card-expiry">' . esc_html__('Data de Expiração (MM/YY)', 'woocommerce-braspag') . '&nbsp;<span class="required">*</span></label>
				<input id="' . esc_attr($this->id) . '-card-expiry" class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" autocomplete="cc-exp" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="' . esc_attr__('MM / YY', 'woocommerce') . '" ' . $this->field_name('card-expiry') . ' />
			</p>',
            'creditcard-cvc-field' => '<p class="form-row form-row-wide">
                <label for="' . esc_attr($this->id) . '-card-cvc">' . esc_html__('Código de Segurança', 'woocommerce-braspag') . '&nbsp;<span class="required">*</span></label>
                <input id="' . esc_attr($this->id) . '-card-cvc" class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="4" placeholder="' . esc_attr__('CVC', 'woocommerce') . '" ' . $this->field_name('card-cvc') . ' style="width:100px" />
            </p>',
            'creditcard-installments-field' => '<p class="form-row form-row-wide">
				<label for="' . esc_attr($this->id) . '-card-installments">' . esc_html__('Parcelamento', 'woocommerce-braspag') . '&nbsp;<span class="required">*</span></label>
				<select id="' . esc_attr($this->id) . '-card-installments" class="input-text wc-credit-card-form-card-cvc"  ' . esc_attr__('MM / YY', 'woocommerce') . '" ' . $this->field_name('card-installments') . ' > 
				    "' . $installmentsOptions . '"
				</select>
			</p>',
            'creditcard-type-field' => '<input type="hidden" id="' . esc_attr($this->id) . '-card-type" class="wc-credit-card-form-card-type" ' . $this->field_name('card-type') . ' />'
        );

        $fields = apply_filters('wc_gateway_braspag_pagador_creditcard_elements_form_filter', $fields);

        $fields = wp_parse_args($fields, apply_filters('woocommerce_credit_card_form_fields', $default_fields, $this->id));

        ?>

                        <noscript><iframe src="<?php echo "https://h.online-metrix.net/fp/tags.js?org_id={$this->antifraud_finger_print_org_id}&session_id={$this->antifraud_finger_print_session_id}" ?>"></iframe></noscript>

                        <fieldset id="wc-<?php echo esc_attr($this->id); ?>-cc-form" class='wc-credit-card-form wc-payment-form'>
                            <?php do_action('woocommerce_credit_card_form_start', $this->id); ?>
                            <?php
                            foreach ($fields as $field) {
                                echo $field; // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
                            }
                            ?>
                            <?php do_action('woocommerce_credit_card_form_end', $this->id); ?>
                            <div class="clear"></div>
                        </fieldset>

                    <?php

                    do_action('wc_gateway_braspag_pagador_creditcard_elements_form_after', $this->id);
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

            do_action(
                'wc_gateway_braspag_pagador_creditcard_process_payment_before',
                $order_id,
                $retry,
                $previous_error,
                $use_order_source
            );

            $order = wc_get_order($order_id);

            do_action('wc_gateway_braspag_pagador_creditcard_process_payment_validation', $order);

            $default_request_params = $this->braspag_pagador_get_default_request_params(get_current_user_id());

            if (0 >= $order->get_total()) {
                return $this->complete_free_order($order);
            }

            WC_Braspag_Logger::log(
                "Info: Begin processing payment for order $order_id for the amount of {$order->get_total()}"
            );

            $request_builder = $this->braspag_pagador_request_builder($this->id, $order, $default_request_params);

            /**
             * autoriza e analisa antifraud na mesma requisição braspag - OK
             * analise primeiro - OK
             * autorize primeiro - OK
             *
             * autoriza e analisa antifraud em requisições diferentes braspag
             * analise primeiro - OK
             * autorise primeiro - OK
             *
             *
             * apenas autoriza braspag - OK
             *
             * apenas analisa braspag - OK
             */

            $process_authorization = true;

            if (
            'yes' === $this->antifraud_enabled
            && 'no' === $this->antifraud_send_with_pagador_transaction
            && 'AnalyseOnly' === $this->antifraud_options_sequence
            ) {
                $process_authorization = false;
            }

            if (
            'yes' === $this->antifraud_enabled
            && 'no' === $this->antifraud_send_with_pagador_transaction
            && in_array($this->antifraud_options_sequence, ['AnalyseFirst', 'AnalyseOnly'])
            ) {
                $this->process_antifraud_analysis_transaction(WC()->cart, $order, $request_builder, false);
            }

            if ($process_authorization) {

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

                $card_token = $response->body->Payment->CreditCard->CardToken;

                if ('yes' === $this->save_card && !empty($card_token)) {
                    $this->process_payment_response_creditcard_card_token($card_token, $response);
                }

                if (
                'yes' === $this->antifraud_enabled
                && 'no' === $this->antifraud_send_with_pagador_transaction
                && 'AuthorizeFirst' === $this->antifraud_options_sequence
                ) {
                    $this->process_antifraud_analysis_transaction(WC()->cart, $order, $request_builder, $response);
                }
            }

            if (isset(WC()->cart)) {
                WC()->cart->empty_cart();
            }

            $this->unlock_order_payment($order);

            do_action('wc_gateway_braspag_pagador_creditcard_process_payment_after', $order_id, $order, $response);

            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
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
                    __("Credit Card Payment Failure. #MPI{$failureType}")
                    );
            }

            if (
            $failureType == '1'
            && $this->auth3ds20_mpi_authorize_on_failure == 'no'
            ) {
                throw new WC_Braspag_Exception(
                    print_r([], true),
                    __("Credit Card Payment Failure. #MPI{$failureType}")
                    );
            }

            if (
            $failureType == '2'
            && $this->auth3ds20_mpi_authorize_on_unenrolled == 'no'
            ) {
                throw new WC_Braspag_Exception(
                    print_r([], true),
                    __("Credit Card Payment Failure. #MPI{$failureType}")
                    );
            }

            if (
            $failureType == '5'
            && $this->auth3ds20_mpi_authorize_on_unsupported_brand == 'no'
            ) {
                throw new WC_Braspag_Exception(
                    print_r([], true),
                    __("Credit Card Payment Failure. #MPI{$failureType}")
                    );
            }

            $provider = $this
                ->get_braspag_payment_provider(
                $checkout->get_value('braspag_creditcard-card-type'),
                $this->test_mode
            );

            if (
            !$this->test_mode
            && !preg_match("#cielo#is", $provider)
            && $failureType != '3'
            ) {
                throw new WC_Braspag_Exception(
                    print_r([], true),
                    __("Credit Card Payment Failure. #MPI{$failureType}")
                    );
            }
        }
    }

    /**
     * @param $card_token
     * @param $response
     * @return bool
     */
    public function process_payment_response_creditcard_card_token($card_token, $response)
    {
        $customer_id = $this->get_logged_in_customer_id();

        if (!WC_Braspag_Payment_Tokens::is_customer_token_already_saved($customer_id, 'braspag', $card_token)) {

            $card_number_exploded = explode("******", $response->body->Payment->CreditCard->CardNumber);
            $card_expiration_date_exploded = explode("/", $response->body->Payment->CreditCard->ExpirationDate);

            $token = new WC_Payment_Token_CC();
            $token->set_token($card_token);
            $token->set_gateway_id('braspag');
            $token->set_card_type($response->body->Payment->CreditCard->Brand);
            $token->set_last4($card_number_exploded[1]);
            $token->set_expiry_month($card_expiration_date_exploded[0]);
            $token->set_expiry_year($card_expiration_date_exploded[1]);
            $token->set_user_id($customer_id);
            $token->save();
        }

        return true;
    }

    /**
     * @param $order_id
     * @param $order
     * @param $response
     * @return $this
     */
    public function save_payment_response_data($order_id, $order, $response)
    {
        if ($order->get_payment_method() != $this->id) {
            return $this;
        }

        $dataToSave = [
            "_braspag_creditcard_installments" => $response->body->Payment->Installments
        ];

        $dataToSave = array_merge(
            $dataToSave,
            apply_filters(
            'wc_gateway_braspag_pagador_creditcard_save_payment_response_data',
            $dataToSave,
            $order,
            $response
        )
        );

        foreach ($dataToSave as $key => $data) {
            $order->add_meta_data($key, $data, false);
        }

        $order->save();

        return $this;
    }

    /**
     * @param $payment_data
     * @param $order
     * @param $checkout
     * @param $cart
     * @return mixed|void
     */
    public function braspag_pagador_creditcard_payment_request_builder($payment_data, $order, $checkout, $cart)
    {
        $card_expiration_date = str_replace(
            " ",
            "",
            $checkout->get_value('braspag_creditcard-card-expiry')
        );

        if (preg_match("#^\d{2}\/\d{2}$#is", $card_expiration_date)) {
            $card_expiration_date_exploded = explode("/", $card_expiration_date);
            $card_expiration_date = $card_expiration_date_exploded[0] . "/20" . $card_expiration_date_exploded[1];
        }

        $customer_wants_to_save_card = $checkout->get_value('wc-braspag_creditcard-new-payment-method') == 'true';

        $card_data = [
            "CardNumber" => str_replace(" ", "", $checkout->get_value('braspag_creditcard-card-number')),
            "Holder" => $checkout->get_value('braspag_creditcard-card-holder'),
            "ExpirationDate" => $card_expiration_date,
            "SecurityCode" => $checkout->get_value('braspag_creditcard-card-cvc'),
            "Brand" => $checkout->get_value('braspag_creditcard-card-type'),
            "SaveCard" => $this->save_card == 'yes' && $customer_wants_to_save_card
        ];

        $provider = $this
            ->get_braspag_payment_provider($checkout->get_value('braspag_creditcard-card-type'), $this->test_mode);

        if (isset($this->soft_descriptor) && !empty($this->soft_descriptor)) {
            $payment_data = array_merge($payment_data, [
                "Provider" => $provider,
                "Type" => "CreditCard",
                "Amount" => intval($order->get_total() * 100),
                "Currency" => "BRL",
                "Country" => "BRA",
                "Installments" => $checkout->get_value('braspag_creditcard-card-installments'),
                "Interest" => "ByMerchant",
                "Capture" => $this->capture,
                "Authenticate" => false,
                "Recurrent" => false,
                "SoftDescriptor" => $this->soft_descriptor,
                "DoSplit" => false,
                "CreditCard" => $card_data,
                "ExtraDataCollection" => $this->extra_data_collection
            ]);
        }
        else {
            $payment_data = array_merge($payment_data, [
                "Provider" => $provider,
                "Type" => "CreditCard",
                "Amount" => intval($order->get_total() * 100),
                "Currency" => "BRL",
                "Country" => "BRA",
                "Installments" => $checkout->get_value('braspag_creditcard-card-installments'),
                "Interest" => "ByMerchant",
                "Capture" => $this->capture,
                "Authenticate" => false,
                "Recurrent" => false,
                "DoSplit" => false,
                "CreditCard" => $card_data,
                "ExtraDataCollection" => $this->extra_data_collection
            ]);
        }

        return apply_filters(
            'wc_gateway_braspag_pagador_request_creditcard_payment_builder',
            $payment_data,
            $order,
            $checkout,
            $cart
        );
    }

    /**
     * @param $cart
     * @param $order
     * @param $braspag_pagador_request
     * @param $braspag_pagador_response
     * @return array
     */
    public function process_antifraud_analysis_transaction($cart, $order, $braspag_pagador_request, $braspag_pagador_response)
    {
        try {
            $antifraud_request_builder = $this->braspag_antifraud_request_builder(
                $cart,
                $order,
                $braspag_pagador_request,
                $braspag_pagador_response
            );

            $antifraud_response = $this->braspag_antifraud_request(
                $antifraud_request_builder,
                'analysis/v2/',
            ['token' => $this->get_oauth_token()]
            );

            $this->process_antifraud_response($antifraud_response, $order);
        }
        catch (WC_Braspag_Exception $e) {
            wc_add_notice($e->getLocalizedMessage(), 'error');
            WC_Braspag_Logger::log('Error: ' . $e->getMessage());

            return array(
                'result' => 'fail',
                'redirect' => '',
            );
        }
    }

    /**
     * @param $cart
     * @param $order
     * @param $braspag_pagador_request
     * @param $braspag_pagador_response
     * @return array
     */
    public function braspag_antifraud_request_builder($cart, $order, $braspag_pagador_request, $braspag_pagador_response)
    {
        $billing_address = $order->get_address('billing');
        $shipping_address = $order->get_address('shipping');

        $customer_identity_data = $this->get_customer_identity_data($order);

        $fraudAnalysCartItems = [];
        foreach ($cart->get_cart_contents() as $cart_content) {

            $fraudAnalysCartItems[] = [
                "ProductName" => $cart_content['data']->get_name(),
                "UnitPrice" => intval($cart_content['data']->get_price() * 100),
                "MerchantItemId" => $cart_content['data']->get_id(),
                "Sku" => $cart_content['data']->get_sku(),
                "Quantity" => $cart_content->quantity
            ];
        }

        $return_data = [
            "MerchantOrderId" => $braspag_pagador_request['MerchantOrderId'],
            "TotalOrderAmount" => intval($order->get_total() * 100),
            "Currency" => $braspag_pagador_request['Payment']['Currency'],
            "Provider" => "Cybersource",
            "BraspagTransactionId" => $braspag_pagador_response->body->Payment->PaymentId,
            "AuthorizationCode" => $braspag_pagador_response->body->Payment->AuthorizationCode,
            "Card" => [
                "Number" => $braspag_pagador_request['Payment']['Card']['Number'],
                "Holder" => $braspag_pagador_request['Payment']['Card']['Holder'],
                "ExpirationDate" => $braspag_pagador_request['Payment']['Card']['ExpirationDate'],
                "Cvv" => $braspag_pagador_request['Payment']['Card']['Cvv'],
                "Brand" => $braspag_pagador_request['Payment']['Card']['Brand']
            ],
            "Billing" => [
                "Street" => $order->get_billing_address_1(),
                "Number" => $billing_address['number'],
                "Complement" => $billing_address['address_2'],
                "Neighborhood" => $billing_address['neighborhood'],
                "City" => $billing_address['city'],
                "State" => $billing_address['state'],
                "Country" => $billing_address['country'],
                "ZipCode" => $billing_address['postcode']
            ],
            "Shipping" => [
                "Street" => $order->get_shipping_address_1(),
                "Number" => $shipping_address['number'],
                "Complement" => $shipping_address['address_2'],
                "Neighborhood" => $shipping_address['neighborhood'],
                "City" => $shipping_address['city'],
                "State" => $shipping_address['state'],
                "Country" => $shipping_address['country'],
                "ZipCode" => $shipping_address['postcode'],
                "FirstName" => $order->get_shipping_first_name(),
                "LastName" => $order->get_shipping_last_name(),
                "ShippingMethod" => $order->get_payment_method(),
                "Phone" => preg_replace('/\D+/', '', $order->get_billing_phone())
            ],
            "Customer" => [
                "MerchantCustomerId" => $this->get_logged_in_customer_id(),
                "FirstName" => $order->get_billing_first_name(),
                "LastName" => $order->get_billing_last_name(),
                "BirthDate" => $this->get_customer_birthdate_data($order),
                "Email" => $braspag_pagador_request['Customer']['Email'],
                "Phone" => $braspag_pagador_request['Customer']['Phone'],
                "Ip" => $order->get_customer_ip_address(),
                "BrowserHostName" => gethostname(),
                "BrowserCookiesAccepted" => false,
                "BrowserEmail" => $order->get_billing_email(),
                "BrowserFingerprint" => $this->antifraud_finger_print_id
            ],
            "CartItems" => $fraudAnalysCartItems,
            "MerchantDefinedData" => [
                [
                    "Key" => 2,
                    "Value" => "100"
                ],
                [
                    "Key" => 4,
                    "Value" => "Web"
                ],
                [
                    "Key" => 9,
                    "Value" => "SIM"
                ],
                [
                    "Key" => 46,
                    "Value" => $customer_identity_data['value']
                ],
                [
                    "Key" => 83,
                    "Value" => $this->merchant_category
                ],
                [
                    "Key" => 84,
                    "Value" => "WooCommerce"
                ]
            ]
        ];

        if ($braspag_pagador_response !== false) {
            $return_data["BraspagTransactionId"] = $braspag_pagador_response->body->Payment->PaymentId;
            $return_data["AuthorizationCode"] = $braspag_pagador_response->body->Payment->AuthorizationCode;
        }
        else {
            $return_data["Tid"] = "";
            $return_data["Nsu"] = "";
            $return_data["SaleDate"] = "";
        }

        return $return_data;
    }

    /**
     * @param $payment_data
     * @param $cart
     * @param $order
     * @return array
     */
    public function braspag_pagador_creditcard_payment_request_antifraud_builder($payment_data, $order, $checkout, $cart)
    {
        if (
        'yes' !== $this->antifraud_enabled
        || 'yes' !== $this->antifraud_send_with_pagador_transaction
        || 'yes' === $this->auth3ds20_mpi_is_active
        ) {
            return $payment_data;
        }

        $customer_identity_data = $this->get_customer_identity_data($order);

        $fraudAnalysCartItems = [];
        foreach ($cart->get_cart_contents() as $cart_content) {

            $fraudAnalysCartItems[] = [
                "GiftCategory" => "Undefined",
                "HostHedge" => "Off",
                "NonSensicalHedge" => "Off",
                "ObscenitiesHedge" => "Off",
                "PhoneHedge" => "Off",
                "Name" => $cart_content['data']->get_name(),
                "Quantity" => $cart_content->quantity,
                "Sku" => $cart_content['data']->get_sku(),
                "UnitPrice" => intval($cart_content['data']->get_price() * 100),
            ];
        }

        $merchant_defined_fields = [
            [
                "Id" => 2,
                "Value" => "100"
            ],
            [
                "Id" => 4,
                "Value" => "Web"
            ],
            [
                "Id" => 9,
                "Value" => "SIM"
            ],
            [
                "Id" => 46,
                "Value" => $customer_identity_data['value']
            ],
            [
                "Id" => 83,
                "Value" => $this->merchant_category
            ],
            [
                "Id" => 84,
                "Value" => "WooCommerce"
            ]
        ];

        $merchant_defined_fields = apply_filters(
            'wc_gateway_braspag_pagador_request_creditcard_payment_antifraud_mdd_builder',
            $merchant_defined_fields,
            $order,
            $checkout,
            $cart
        );

        $payment_data_fraud_analysis_data = [
            "Sequence" => $this->antifraud_options_sequence,
            "SequenceCriteria" => $this->antifraud_options_sequence_criteria,
            "Provider" => "Cybersource",
            "CaptureOnLowRisk" => 'yes' === $this->antifraud_options_capture_on_low_risk ? true : false,
            "VoidOnHighRisk" => 'yes' === $this->antifraud_options_void_on_righ_risk ? true : false,
            "TotalOrderAmount" => intval($order->get_total() * 100),
            "FingerPrintId" => $this->antifraud_finger_print_id,
            "Browser" => [
                "CookiesAccepted" => false,
                "Email" => $order->get_billing_email(),
                "HostName" => substr(gethostname(), 0, 60),
                "IpAddress" => $order->get_customer_ip_address()
            ],
            "Cart" => [
                "IsGift" => false,
                "ReturnsAccepted" => true,
                "Items" => $fraudAnalysCartItems
            ],
            "MerchantDefinedFields" => $merchant_defined_fields,
            "Shipping" => [
                "Addressee" => $order->get_formatted_billing_full_name(),
                "Method" => "LowCost",
                "Phone" => preg_replace('/\D+/', '', $order->get_billing_phone())
            ]
        ];

        $payment_data_fraud_analysis_data = apply_filters(
            'wc_gateway_braspag_pagador_request_creditcard_payment_antifraud_builder',
            $payment_data_fraud_analysis_data,
            $order,
            $checkout,
            $cart
        );

        $payment_data['FraudAnalysis'] = $payment_data_fraud_analysis_data;

        return $payment_data;
    }

    /**
     * @param $payment_data
     * @param $cart
     * @param $order
     * @return array
     */
    public function braspag_pagador_creditcard_payment_request_auth3ds20_builder($payment_data, $order, $checkout, $cart)
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
            'wc_gateway_braspag_pagador_request_creditcard_payment_auth3ds20_builder',
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
    public function get_creditcard_payment_types_options()
    {
        return [
            'Cielo-Visa' => 'Cielo Visa',
            'Cielo-Master' => 'Cielo Master',
            'Cielo-Amex' => 'Cielo Amex',
            'Cielo-Elo' => 'Cielo Elo',
            'Cielo-Aura' => 'Cielo Aura',
            'Cielo-Jcb' => 'Cielo Jcb',
            'Cielo-Diners' => 'Cielo Diners',
            'Cielo-Discover' => 'Cielo Discover',
            'Cielo30-Visa' => 'Cielo 3.0 Visa',
            'Cielo30-Master' => 'Cielo 3.0 Master',
            'Cielo30-Amex' => 'Cielo 3.0 Amex',
            'Cielo30-Elo' => 'Cielo 3.0 Elo',
            'Cielo30-Aura' => 'Cielo 3.0 Aura',
            'Cielo30-Jcb' => 'Cielo 3.0 Jcb',
            'Cielo30-Diners' => 'Cielo 3.0 Diners',
            'Cielo30-Discover' => 'Cielo 3.0 Discover',
            'Cielo30-Hipercard' => 'Cielo 3.0 Hipercard',
            'Cielo30-Hiper' => 'Cielo 3.0 Hiper',
            'Redecard-Visa' => 'Redecard Visa',
            'Redecard-Master' => 'Redecard Master',
            'Redecard-Hipercard' => 'Redecard Hipercard',
            'Redecard-Hiper' => 'Redecard Hiper',
            'Redecard-Diners' => 'Redecard Diners',
            'Rede2-Visa' => 'Rede2-Visa',
            'Rede2-Master' => 'Rede2-Master',
            'Rede2-Hipercard' => 'Rede2-Hipercard',
            'Rede2-Hiper' => 'Rede2-Hiper',
            'Rede2-Diners' => 'Rede2-Diners',
            'Rede2-Elo' => 'Rede2-Elo',
            'Rede2-Amex' => 'Rede2-Amex',
            'Getnet-Visa' => 'Getnet-Visa',
            'Getnet-Master' => 'Getnet-Master',
            'Getnet-Elo' => 'Getnet-Elo',
            'Getnet-Amex' => 'Getnet-Amex',
            'GlobalPayments-Visa' => 'GlobalPayments Visa',
            'GlobalPayments-Master' => 'GlobalPayments Master',
            'Stone-Visa' => 'Stone Visa',
            'Stone-Master' => 'Stone Master',
            'Stone-Hipercard' => 'Stone Hipercard',
            'Stone-Elo' => 'Stone Elo',
            'FirstData-Visa' => 'FirstData Visa',
            'FirstData-Master' => 'FirstData Master',
            'FirstData-Cabal' => 'FirstData Cabal',
            'Sub1-Visa' => 'Sub1 Visa',
            'Sub1-Master' => 'Sub1 Master',
            'Sub1-Diners' => 'Sub1 Diners',
            'Sub1-Amex' => 'Sub1 Amex',
            'Sub1-Discover' => 'Sub1 Discover',
            'Sub1-Cabal' => 'Sub1 Cabal',
            'Sub1-Naranja e Nevada' => 'Sub1 Naranja e Nevada',
            'Banorte-Visa' => 'Banorte Visa',
            'Banorte-Master' => 'Banorte Master',
            'Banorte-Carnet' => 'Banorte Carnet',
            'Credibanco-Visa' => 'Credibanco Visa',
            'Credibanco-Master' => 'Credibanco Master',
            'Credibanco-Diners' => 'Credibanco Diners',
            'Credibanco-Amex' => 'Credibanco Amex',
            'Credibanco-Credential' => 'Credibanco Credential',
            'Transbank-Visa' => 'Transbank Visa',
            'Transbank-Master' => 'Transbank Master',
            'Transbank-Diners' => 'Transbank Diners',
            'Transbank-Amex' => 'Transbank Amex',
            'RedeSitef-Visa' => 'Rede Sitef Visa',
            'RedeSitef-Master' => 'Rede Sitef Master',
            'RedeSitef-Hipercard' => 'Rede Sitef Hipercard',
            'RedeSitef-Diners' => 'Rede Sitef Diners',
            'CieloSitef-Visa' => 'Cielo Sitef Visa',
            'CieloSitef-Master' => 'Cielo Sitef Master',
            'CieloSitef-Amex' => 'Cielo Sitef Amex',
            'CieloSitef-Elo' => 'Cielo Sitef Elo',
            'CieloSitef-Aura' => 'Cielo Sitef Aura',
            'CieloSitef-Jcb' => 'Cielo Sitef Jcb',
            'CieloSitef-Diners' => 'Cielo Sitef Diners',
            'CieloSitef-Discover' => 'Cielo Sitef Discover',
            'SantanderSitef-Visa' => 'Santander Sitef Visa',
            'SantanderSitef-Master' => 'Santander Sitef Master',
            'Safra2-Visa' => 'Safra2 Visa',
            'Safra2-Master' => 'Safra2 Master',
            'Safra2-Hipercard' => 'Safra2 Hipercard',
            'Safra2-Elo' => 'Safra2 Elo',
            'Safra2-Amex' => 'Safra2 Amex',
            'Simulado-Simulado' => 'Simulado',
        ];
    }

    /**
     * @param $order
     * @return |null
     */
    public function display_order_creditcard_data($order)
    {
        if ($order->get_payment_method() != 'braspag_creditcard') {
            return null;
        }

        do_action('wc_gateway_braspag_pagador_creditcard_display_order_data_before', $this->id);

        ?>

                        <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
                            <tfoot>
                                <tr>
                                    <th width="50%" scope="row"><?php echo __("Parcelamento", 'woocomerce-braspag') ?>:</th>
                                    <td>
                                        <?php echo $order->get_meta('_braspag_creditcard_installments'); ?>x
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                <?php

                do_action('wc_gateway_braspag_pagador_creditcard_display_order_data_after', $order);
    }

    /**
     * @return array
     */
    public function get_available_payment_types_options()
    {
        $creditCardPaymentTypes = $this->get_creditcard_payment_types_options();

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
        $params['isBpmpiEnabledCC'] = $this->auth3ds20_mpi_is_active === 'yes';
        $params['isBpmpiMasterCardNotifyOnlyEnabledCC'] = $this->auth3ds20_mpi_mastercard_notify_only === 'yes';
        return $params;
    }
}