<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WC_Gateway_Braspag class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Braspag_CreditCard_JustClick extends WC_Gateway_Braspag_CreditCard {

    public $enabled;

    protected $saved_cards;

    protected $capture;

    protected $maximum_installments;

    protected $minimum_amount_of_installment;

    public function __construct() {

        $this->retry_interval = 1;
        $this->id             = 'braspag_creditcard_justclick';
        $this->method_title   = __( 'Braspag Credit Card JustClick', 'woocommerce-braspag' );
        $this->method_description =  __( 'Take payments via Credit Card JustClick with Braspag.' );
        /* translators: 1) link to Braspag register page 2) link to Braspag api keys page */
        $this->has_fields         = true;
        $this->supports           = array(
            'add_payment_method',
            'tokenization'
        );

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Get setting values.

        $braspag_main_settings = get_option( 'woocommerce_braspag_settings' );

        $braspag_enabled = isset($braspag_main_settings['enabled']) ? $braspag_main_settings['enabled'] : 'no';
        $test_mode = isset($braspag_main_settings['test_mode']) ? $braspag_main_settings['test_mode'] : 'no';

        $this->title = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );
        $this->enabled = $braspag_enabled == 'yes' ? $this->get_option( 'enabled' ) : 'no';
        $this->test_mode = $test_mode == 'yes';
        $this->maximum_installments = $this->get_option( 'maximum_installments');
        $this->minimum_amount_of_installment = $this->get_option( 'minimum_amount_of_installment');

        $this->capture              = 'authorize_capture' === $this->get_option( 'payment_action', 'authorize' );
        $this->saved_cards          = WC_Payment_Tokens::get_customer_tokens($this->get_logged_in_customer_id(),'braspag');
        $this->available_types = WC_Braspag_Payment_Tokens::get_customer_tokens_types($this->get_logged_in_customer_id(),'braspag');

        // Hooks.
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_customer_save_address', array( $this, 'show_update_card_notice' ), 10, 2 );

        add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_order_creditcard_data' ) );

        add_filter( "wc_gateway_braspag_pagador_{$this->id}_request_payment_builder", array( $this, 'braspag_pagador_creditcard_justclick_payment_request_builder' ), 10, 4);
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
    public function get_icon() {

        $availableTypes = $this->get_available_payment_types_options();
        $icons = $this->payment_icons();
        $icons_str = '';

        foreach ($availableTypes as $availableType) {
            $icons_str .= $icons[strtolower($availableType)];
        }

        return apply_filters( 'woocommerce_gateway_icon', $icons_str, $this->id );
    }

    /**
     * @return array
     */
    public function get_available_payment_types_options()
    {
        return $this->available_types;
    }

    public function display_order_creditcard_data( $order ) {

        if ($order->get_payment_method() != 'braspag_creditcard_justclick') {
            return null;
        }

        do_action( 'wc_gateway_braspag_pagador_creditcard_justclick_display_order_data_before', $this->id );

        ?>

        <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
            <tfoot>
            <tr>
                <th width="50%" scope="row"><?php echo __("Installments") ?>:</th>
                <td>
                    <?php echo $order->get_meta('_braspag_creditcard_installments'); ?>x
                </td>
            </tr>
            </tfoot>
        </table>
        <?php

        do_action( 'wc_gateway_braspag_pagador_creditcard_justclick_display_order_data_after', $order );
    }

    public function init_form_fields() {
        $this->form_fields = require( WC_BRASPAG_PLUGIN_PATH . '/includes/admin/braspag-creditcard-justclick-settings.php' );
    }

    public function payment_fields() {

        ob_start();

        echo '<div id="braspag-creditcard-justclick-payment-data">';

        do_action( 'wc_gateway_braspag_pagador_creditcard_justclick_payment_fields_before', $this->id );

        $descriptionText = '';
        if ( $this->test_mode ) {
            /* translators: link to Braspag testing page */
            $descriptionText .= ' '.__( 'TEST MODE ENABLED.').'</br></br>';
        }
        $description          = $this->get_description();
        $descriptionText          .= ! empty( $description ) ? $description : '';

        $descriptionText = trim( $descriptionText );

        echo apply_filters( 'wc_braspag_description', wpautop( wp_kses_post( $descriptionText ) ), $this->id ); // wpcs: xss ok.

        $this->elements_form();

        do_action( 'wc_braspag_cards_payment_fields', $this->id );

        do_action( 'wc_gateway_braspag_pagador_creditcard_payment_fields_after', $this->id );

        echo '</div>';

        ob_end_flush();
    }

    public function get_installments(){

        $installments = $this->maximum_installments;
        $installmentsMinAmount = $this->minimum_amount_of_installment;
        $grandTotal = WC()->cart->get_cart_contents_total();

        if (empty($installments)) {
            return [sprintf('%1$s x R$ %2$s', 1, number_format( $grandTotal, wc_get_price_decimals(), ',', '' ))];
        }

        $return = array();
        $installments++;

        for ($i = 1; $i < $installments; $i++) {
            $installmentAmount = $grandTotal / $i;

            if ($i > 1 && $installmentAmount < $installmentsMinAmount) {
                break;
            }

            $return[$i] = sprintf('%1$s x R$ %2$s', $i, number_format( $installmentAmount, wc_get_price_decimals(), ',', '' ));
        }

        return $return;
    }

    public function elements_form() {
        wp_enqueue_script( 'wc-credit-card-form' );

        $fields = array();

        do_action( 'wc_gateway_braspag_pagador_creditcard_justclick_elements_form_before', $this->id );

        $savedCardOptions = '';
        foreach ($this->saved_cards as $key => $saved_card) {
            $savedCardOptions .= "<option value=".$saved_card->get_token().">************".$saved_card->get_meta('last4')." (".$saved_card->get_meta('card_type').")</option>";
        }

        $installmentsOptions = '';
        foreach ($this->get_installments() as $key => $installment) {
            $installmentsOptions .= "<option value=".$key.">".$installment."</option>";
        }

        $default_fields = array(
            'creditcard-justiclick-saved-field' => '<p class="form-row form-row-wide">
				<label for="' . esc_attr( $this->id ) . '-card-holder">' . esc_html__( 'Saved Card', 'woocommerce-braspag' ) . '&nbsp;<span class="required">*</span></label>
				<select id="' . esc_attr( $this->id ) . '-card-saved" class="input-text wc-credit-card-form-card-cvc"  ' . esc_attr__( 'MM / YY', 'woocommerce' ) . '" ' . $this->field_name( 'card-saved' ) . ' > 
				    "'.$savedCardOptions.'"
				</select>
			</p>',
            'creditcard-justiclick-cvc-field' => '<p class="form-row form-row-wide">
                <label for="' . esc_attr( $this->id ) . '-card-cvc">' . esc_html__( 'Código de Segurança', 'woocommerce-braspag' ) . '&nbsp;<span class="required">*</span></label>
                <input id="' . esc_attr( $this->id ) . '-card-cvc" class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="4" placeholder="' . esc_attr__( 'CVC', 'woocommerce' ) . '" ' . $this->field_name( 'card-cvc' ) . ' style="width:100px" />
            </p>',
            'creditcard-justiclick-installments-field' => '<p class="form-row form-row-wide">
				<label for="' . esc_attr( $this->id ) . '-card-installments">' . esc_html__( 'Parcelamento', 'woocommerce-braspag' ) . '&nbsp;<span class="required">*</span></label>
				<select id="' . esc_attr( $this->id ) . '-card-installments" class="input-text wc-credit-card-form-card-cvc"  ' . esc_attr__( 'MM / YY', 'woocommerce' ) . '" ' . $this->field_name( 'card-installments' ) . ' > 
				    "'.$installmentsOptions.'"
				</select>
			</p>',
        );

        $fields = apply_filters( 'wc_gateway_braspag_pagador_creditcard_justclick_elements_form_filter', $fields );

        $fields = wp_parse_args( $fields, apply_filters( 'woocommerce_credit_card_form_fields', $default_fields, $this->id ) );
        ?>

        <noscript><iframe src="<?php echo "https://h.online-metrix.net/fp/tags.js?org_id={$this->antifraud_finger_print_org_id}&session_id={$this->antifraud_finger_print_session_id}"?>"></iframe></noscript>

        <fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-cc-form" class='wc-credit-card-form wc-payment-form'>
            <?php do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>
            <?php
            foreach ( $fields as $field ) {
                echo $field; // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
            }
            ?>
            <?php do_action( 'woocommerce_credit_card_form_end', $this->id ); ?>
            <div class="clear"></div>
        </fieldset>

        <?php
        do_action( 'wc_gateway_braspag_pagador_creditcard_justclick_elements_form_after', $this->id );

    }

    /**
     * @param $payment_data
     * @param $order
     * @param $checkout
     * @param $cart
     * @return mixed|void
     */
    public function braspag_pagador_creditcard_justclick_payment_request_builder($payment_data, $order, $checkout, $cart) {

        $card_token = $checkout->get_value('braspag_creditcard_justclick-card-saved');

        $card_token_object = WC_Braspag_Payment_Tokens::get_customer_token($this->get_logged_in_customer_id(), 'braspag', $card_token);

        $card_data = [
            "CardToken" => $card_token,
            "SecurityCode" => $checkout->get_value('braspag_creditcard_justclick-card-cvc'),
            "Brand" => $card_token_object->get_meta('card_type')
        ];

        $card_type = $checkout->get_value('braspag_creditcard-card-type');
        $provider = $this->get_braspag_payment_provider($card_type, $this->test_mode);

        $payment_data = [
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
            "SoftDescriptor" => "",
            "DoSplit" => false,
            "CreditCard" => $card_data
        ];

        return apply_filters( 'wc_gateway_braspag_pagador_request_creditcard_payment_builder', $payment_data, $order, $checkout, $cart);
    }
}
