<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WC_Gateway_Braspag_Boleto
 */
class WC_Gateway_Braspag_Boleto extends WC_Gateway_Braspag {

    public $enabled;

    public $payment_instructions_for_customer;

    public $payment_instructions_for_bank;

    public $days_to_expire;

    public $available_type;

    public $label_print_button;

    public function __construct() {
        $this->retry_interval = 1;
        $this->id             = 'braspag_boleto';
        $this->method_title   = __( 'Braspag Boleto', 'woocommerce-braspag' );
        /* translators: 1) link to Braspag register page 2) link to Braspag api keys page */
        $this->method_description =  __( 'Take payments via Boleto with Braspag.' );
        $this->has_fields         = true;
        $this->supports           = array(
            'add_payment_method'
        );

        $this->init_form_fields();

        $this->init_settings();

        $braspag_main_settings = get_option( 'woocommerce_braspag_settings' );

        $braspag_enabled = isset($braspag_main_settings['enabled']) ? $braspag_main_settings['enabled'] : 'no';
        $test_mode = isset($braspag_main_settings['test_mode']) ? $braspag_main_settings['test_mode'] : 'no';

        $this->title                = $this->get_option( 'title' );
        $this->description          = $this->get_option( 'description' );
        $this->enabled  = $braspag_enabled == 'yes' ? $this->get_option( 'enabled' ) : 'no';

        $this->test_mode =  $test_mode == 'yes';

        $this->payment_instructions_for_customer          = $this->get_option( 'payment_instructions_for_customer' );
        $this->payment_instructions_for_bank          = $this->get_option( 'payment_instructions_for_bank' );
        $this->days_to_expire          = $this->get_option( 'days_to_expire' );
        $this->available_type          = $this->get_option( 'available_type' );
        $this->label_print_button          = $this->get_option( 'label_print_button' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_customer_save_address', array( $this, 'show_update_card_notice' ), 10, 2 );
        add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_order_boleto_data' ) );

        add_action( 'wc_gateway_braspag_pagador_boleto_process_payment_after', array( $this, 'save_payment_response_data' ), 10, 3);
        add_filter( "wc_gateway_braspag_pagador_{$this->id}_request_payment_builder", array( $this, 'braspag_pagador_boleto_payment_request_builder' ), 10, 4);

        add_filter( "wc_gateway_braspag_pagador_request_builder", array( $this, 'braspag_pagador_request_builder_boleto' ), 10, 3);
    }

    /**
     * @return bool
     */
    public function is_available() {

        return $this->enabled == 'yes';
    }

    public function init_form_fields() {
        $this->form_fields = require( WC_BRASPAG_PLUGIN_PATH . '/includes/admin/braspag-boleto-settings.php' );
    }

    public function payment_fields() {
        $descriptionText          = '';

        ob_start();

        echo '<div id="braspag-payment-data">';

        do_action( 'wc_gateway_braspag_pagador_boleto_payment_fields_before', $this->id );

        if ( $this->test_mode ) {
            /* translators: link to Braspag testing page */
            $descriptionText .= ' ' . sprintf( __( 'TEST MODE ENABLED.', 'woocommerce-braspag' ))."</br></br>";
        }

        $paymentInstructionsForCustomer = apply_filters( 'wc_gateway_braspag_boleto_payment_instructions_for_customer', wpautop( wp_kses_post( $this->payment_instructions_for_customer ) ), $this->id ); // wpcs: xss ok.

        $description          = $this->get_description();
        $descriptionText          .= ! empty( $description ) ? $description."</br></br>" : '';

        $descriptionText .= $paymentInstructionsForCustomer;
        $descriptionText = trim( $descriptionText );

        echo $descriptionText;

        do_action( 'wc_gateway_braspag_pagador_boleto_payment_fields_after', $this->id );

        echo '</div>';

        ob_end_flush();
    }

    /**
     * @param int $order_id
     * @param bool $retry
     * @param bool $previous_error
     * @param bool $use_order_source
     * @return array|void
     * @throws Exception
     */
    public function process_payment( $order_id, $retry = true, $previous_error = false, $use_order_source = false ) {
        try {

            do_action( 'wc_gateway_braspag_pagador_boleto_process_payment_before', $order_id, $retry, $previous_error, $use_order_source);

            $order = wc_get_order( $order_id );

            $default_request_params = $this->braspag_pagador_get_default_request_params( get_current_user_id());

            if ( 0 >= $order->get_total() ) {
                return $this->complete_free_order( $order );
            }

            WC_Braspag_Logger::log( "Info: Begin processing payment for order $order_id for the amount of {$order->get_total()}" );

            $request_builder = $this->braspag_pagador_request_builder( $this->id, $order, $default_request_params );

            $response = $this->braspag_pagador_request( $request_builder, 'v2/sales/', $default_request_params );

            if ( empty( $response->errors ) ) {
                $this->lock_order_payment( $order, $response );
            }

            if ( ! empty( $response->errors ) ) {

                if ( $this->is_retryable_error( $response ) ) {
                    return $this->retry_after_error( $response, $order, $retry, $previous_error, $use_order_source );
                }

                $this->unlock_order_payment( $order );
                $this->throw_localized_message( $response, $order );
            }

            $this->process_pagador_response( $response, $order );

            if ( isset( WC()->cart ) ) {
                WC()->cart->empty_cart();
            }

            $this->unlock_order_payment( $order );

            do_action( 'wc_gateway_braspag_pagador_boleto_process_payment_after', $order_id, $order, $response);

            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url( $order ),
            );

        } catch ( WC_Braspag_Exception $e ) {
            wc_add_notice( $e->getLocalizedMessage(), 'error' );
            WC_Braspag_Logger::log( 'Error: ' . $e->getMessage() );

            do_action( 'wc_gateway_braspag_pagador_process_payment_error', $e, $order );

            /* translators: error message */
            $order->update_status( 'failed' );

            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }
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
            "_braspag_boleto_instructions" => $response->body->Payment->Instructions,
            "_braspag_boleto_expiration_date" => $response->body->Payment->ExpirationDate,
            "_braspag_boleto_demonstrative" => $response->body->Payment->Demonstrative,
            "_braspag_boleto_url" => $response->body->Payment->Url,
            "_braspag_boleto_boleto_number" => $response->body->Payment->BoletoNumber,
            "_braspag_boleto_bar_code_number" => $response->body->Payment->BarCodeNumber,
            "_braspag_boleto_digitable_line" => $response->body->Payment->DigitableLine
        ];

        $dataToSave = array_merge($dataToSave, apply_filters( 'wc_gateway_braspag_pagador_boleto_save_payment_response_data', $dataToSave, $order, $response));

        foreach ($dataToSave as $key => $data) {
            $order->add_meta_data($key, $data, false );
        }

        $order->save();

        return $this;
    }

    /**
     * @param $payment_data
     * @param $order
     * @param $checkout
     * @param $cart
     * @return array
     * @throws Exception
     */
    public function braspag_pagador_boleto_payment_request_builder($payment_data, $order, $checkout, $cart) {

        $days_to_expire = $this->days_to_expire > 0 ? $this->days_to_expire : 1;

        $created_date = $order->get_date_created();
        $created_date->add(new DateInterval("P{$days_to_expire}D"));
        $expiration_date =  $created_date->format('Y-m-d');

        $payment_data = array_merge($payment_data, [
            "Provider" => $this->available_type,
            "Type" => "Boleto",
            "Amount" => intval($order->get_total() * 100),
            "BoletoNumber" => $order->get_id(),
            "Assignor" => "",
            "Demonstrative" => "",
            "ExpirationDate" => $expiration_date,
            "Identification" => "",
            "Instructions" => $this->payment_instructions_for_bank,
            "DaysToFine" => '',
            "FineRate" => '',
            "FineAmount" => '',
            "DaysToInterest" => '',
            "InterestRate" => '',
            "InterestAmount" => ''
        ]);

        return apply_filters( 'wc_gateway_braspag_pagador_request_boleto_payment_builder', $payment_data, $order, $checkout, $cart);
    }

    /**
     * @param $order
     * @return |null
     */
    public function display_order_boleto_data( $order ) {

        if ($order->get_payment_method() != $this->id || in_array($order->get_status(), ['processing', 'completed'])) {
            return null;
        }

        $expirationDate = $order->get_meta('_braspag_boleto_expiration_date');
        $explodeExpirationDate = explode("-", $expirationDate);
        $expirationDate = $explodeExpirationDate[2]."/".$explodeExpirationDate[1]."/".$explodeExpirationDate[0];

        do_action( 'wc_gateway_braspag_pagador_boleto_display_order_data_before', $order );

        ?>

        <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">

            <tbody>
                <tr class="woocommerce-table__line-item order_item">
                    <td class="woocommerce-table__product-name product-name">
                        <b>Boleto Bar Code</b>
                    </td>
                    <td class="woocommerce-table__product-total product-total">
                        <?php echo $order->get_meta('_braspag_boleto_digitable_line'); ?>
                    </td>
                </tr>

                <tr class="woocommerce-table__line-item order_item">
                    <td class="woocommerce-table__product-name product-name">
                        <b>Boleto Expiration Date</b>
                    </td>
                    <td class="woocommerce-table__product-total product-total">
                        <?php echo $expirationDate; ?>
                    </td>
                </tr>

                <tr class="woocommerce-table__line-item order_item">
                    <td class="woocommerce-table__product-total product-total text-center" colspan="2">
                        <a href="<?php echo $order->get_meta('_braspag_boleto_url'); ?>" target="_blank">
                            <b><?php echo $this->label_print_button ?></b>
                        </a>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php

        do_action( 'wc_gateway_braspag_pagador_boleto_display_order_data_after', $order );
    }

    /**
     * @param $request
     * @param $order
     * @param $default_request_params
     * @return mixed
     */
    public function braspag_pagador_request_builder_boleto($request, $order, $default_request_params) {

        if (!isset ($request['Payment']) || $request['Payment']['Type'] != 'Boleto') {
            return $request;
        }

        $fields = [];

        switch ($request['Payment']['Provider']) {

            case 'Bradesco2':

                $fields = [
                    [
                        'field' => 'Id do Pedido',
                        'size_limit' => 27,
                        'size' => strlen($request['MerchantOrderId'])
                    ],[
                        'field' => 'Número do Boleto',
                        'size_limit' => 11,
                        'size' => strlen($request['Payment']['BoletoNumber'])
                    ],[
                        'field' => 'Nome do Cliente',
                        'size_limit' => 34,
                        'size' => strlen($request['Customer']['Name'])
                    ],[
                        'field' => 'Endereço do cliente - Campo Endereço',
                        'size_limit' => 70,
                        'size' => strlen($request['Customer']['Address']['Street'])
                    ],[
                        'field' => 'Endereço do cliente - Campo Número',
                        'size_limit' => 10,
                        'size' => strlen($request['Customer']['Address']['Number'])
                    ],[
                        'field' => 'Endereço do cliente - Campo Complemento',
                        'size_limit' => 20,
                        'size' => strlen($request['Customer']['Address']['Complement'])
                    ],[
                        'field' => 'Endereço do cliente - Campo Bairro',
                        'size_limit' => 50,
                        'size' => strlen($request['Customer']['Address']['District'])
                    ],[
                        'field' => 'Endereço do cliente - Campo Cidade',
                        'size_limit' => 50,
                        'size' => strlen($request['Customer']['Address']['City'])
                    ],[
                        'field' => 'Instruções do Boleto',
                        'size_limit' => 450,
                        'size' => strlen($request['Payment']['Instructions'])
                    ],[
                        'field' => 'Texto de Demonstrativo',
                        'size_limit' => 255,
                        'size' => strlen($request['Payment']['Demonstrative'])
                    ],
                ];

                break;

            case 'BancoDoBrasil2':

                $fields = [
                    [
                        'field' => 'Id do Pedido',
                        'size_limit' => 50,
                        'size' => strlen($request['MerchantOrderId'])
                    ],[
                        'field' => 'Número do Boleto',
                        'size_limit' => 9,
                        'size' => strlen($request['Payment']['BoletoNumber'])
                    ],[
                        'field' => 'Nome do Cliente',
                        'size_limit' => 60,
                        'size' => strlen($request['Customer']['Name'])
                    ],[
                        'field' => 'Endereço do cliente - Campos Endereço, Número, Complemento e Bairro',
                        'size_limit' => 60,
                        'size' => strlen($request['Customer']['Address']['Street']. " ".$request['Customer']['Address']['Number']. " ".$request['Customer']['Address']['Complement']. " ".$request['Customer']['Address']['District'])
                    ],[
                        'field' => 'Endereço do cliente - Campo Cidade',
                        'size_limit' => 18,
                        'size' => strlen($request['Customer']['Address']['City'])
                    ],[
                        'field' => 'Instruções do Boleto',
                        'size_limit' => 450,
                        'size' => strlen($request['Payment']['Instructions'])
                    ],[
                        'field' => 'Texto de Demonstrativo',
                        'size_limit' => 999999999999999,
                        'size' => strlen($request['Payment']['Demonstrative'])
                    ],
                ];

                break;

            case 'ItauShopline':

                $fields = [
                    [
                        'field' => 'Id do Pedido',
                        'size_limit' => 8,
                        'size' => strlen($request['MerchantOrderId'])
                    ],[
                        'field' => 'Número do Boleto',
                        'size_limit' => 8,
                        'size' => strlen($request['Payment']['BoletoNumber'])
                    ],[
                        'field' => 'Nome do Cliente',
                        'size_limit' => 30,
                        'size' => strlen($request['Customer']['Name'])
                    ],[
                        'field' => 'Endereço do cliente - Campos Endereço, Número e Complemento',
                        'size_limit' => 40,
                        'size' => strlen($request['Customer']['Address']['Street']. " ".$request['Customer']['Address']['Number']. " ".$request['Customer']['Address']['Complement'])
                    ],[
                        'field' => 'Endereço do cliente - Campo Bairro',
                        'size_limit' => 15,
                        'size' => strlen($request['Customer']['Address']['District'])
                    ],[
                        'field' => 'Endereço do cliente - Campo Cidade',
                        'size_limit' => 15,
                        'size' => strlen($request['Customer']['Address']['City'])
                    ],[
                        'field' => 'Instruções do Boleto',
                        'size_limit' => 999999999999999,
                        'size' => strlen($request['Payment']['Instructions'])
                    ],[
                        'field' => 'Texto de Demonstrativo',
                        'size_limit' => 999999999999999,
                        'size' => strlen($request['Payment']['Demonstrative'])
                    ],
                ];

                break;

            case 'Santander2':

                $fields = [
                    [
                        'field' => 'Id do Pedido',
                        'size_limit' => 50,
                        'size' => strlen($request['MerchantOrderId'])
                    ],[
                        'field' => 'Número do Boleto',
                        'size_limit' => 13,
                        'size' => strlen($request['Payment']['BoletoNumber'])
                    ],[
                        'field' => 'Nome do Cliente',
                        'size_limit' => 40,
                        'size' => strlen($request['Customer']['Name'])
                    ],[
                        'field' => 'Endereço do cliente - Campos Endereço, Número e Complemento',
                        'size_limit' => 40,
                        'size' => strlen($request['Customer']['Address']['Street']. " ".$request['Customer']['Address']['Number']. " ".$request['Customer']['Address']['Complement'])
                    ],[
                        'field' => 'Endereço do cliente - Campo Bairro',
                        'size_limit' => 15,
                        'size' => strlen($request['Customer']['Address']['District'])
                    ],[
                        'field' => 'Endereço do cliente - Campo Cidade',
                        'size_limit' => 30,
                        'size' => strlen($request['Customer']['Address']['City'])
                    ],[
                        'field' => 'Instruções do Boleto',
                        'size_limit' => 450,
                        'size' => strlen($request['Payment']['Instructions'])
                    ],[
                        'field' => 'Texto de Demonstrativo',
                        'size_limit' => 255,
                        'size' => strlen($request['Payment']['Demonstrative'])
                    ],
                ];
                break;

            case 'Caixa2':

                $fields = [
                    [
                        'field' => 'Id do Pedido',
                        'size_limit' => 11,
                        'size' => strlen($request['MerchantOrderId'])
                    ],[
                        'field' => 'Número do Boleto',
                        'size_limit' => 12,
                        'size' => strlen($request['Payment']['BoletoNumber'])
                    ],[
                        'field' => 'Nome do Cliente',
                        'size_limit' => 40,
                        'size' => strlen($request['Customer']['Name'])
                    ],[
                        'field' => 'Endereço do cliente - Campos Endereço, Número e Complemento',
                        'size_limit' => 40,
                        'size' => strlen($request['Customer']['Address']['Street']. " ".$request['Customer']['Address']['Number']. " ".$request['Customer']['Address']['Complement'])
                    ],[
                        'field' => 'Endereço do cliente - Campo Bairro',
                        'size_limit' => 15,
                        'size' => strlen($request['Customer']['Address']['District'])
                    ],[
                        'field' => 'Endereço do cliente - Campo Cidade',
                        'size_limit' => 15,
                        'size' => strlen($request['Customer']['Address']['City'])
                    ],[
                        'field' => 'Instruções do Boleto',
                        'size_limit' => 450,
                        'size' => strlen($request['Payment']['Instructions'])
                    ],[
                        'field' => 'Texto de Demonstrativo',
                        'size_limit' => 255,
                        'size' => strlen($request['Payment']['Demonstrative'])
                    ],
                ];
                break;

            case 'Citibank2':

                $fields = [
                    [
                        'field' => 'Id do Pedido',
                        'size_limit' => 10,
                        'size' => strlen($request['MerchantOrderId'])
                    ],[
                        'field' => 'Número do Boleto',
                        'size_limit' => 11,
                        'size' => strlen($request['Payment']['BoletoNumber'])
                    ],[
                        'field' => 'Nome do Cliente',
                        'size_limit' => 50,
                        'size' => strlen($request['Customer']['Name'])
                    ],[
                        'field' => 'Endereço do cliente - Campos Endereço, Número e Complemento',
                        'size_limit' => 40,
                        'size' => strlen($request['Customer']['Address']['Street']. " ".$request['Customer']['Address']['Number']. " ".$request['Customer']['Address']['Complement'])
                    ],[
                        'field' => 'Endereço do cliente - Campo Bairro',
                        'size_limit' => 50,
                        'size' => strlen($request['Customer']['Address']['District'])
                    ],[
                        'field' => 'Endereço do cliente - Campo Cidade',
                        'size_limit' => 50,
                        'size' => strlen($request['Customer']['Address']['City'])
                    ],[
                        'field' => 'Instruções do Boleto',
                        'size_limit' => 450,
                        'size' => strlen($request['Payment']['Instructions'])
                    ],[
                        'field' => 'Texto de Demonstrativo',
                        'size_limit' => 255,
                        'size' => strlen($request['Payment']['Demonstrative'])
                    ],
                ];
                break;
        }

        foreach ($fields as $field) {
            if ($field['size'] > $field['size_limit']) {
                throw new \Exception("O número máximo de caracteres permitidos, de {$field['size_limit']} caractere(s), para o item '{$field['field']}' foi ultrapassado.");
            }
        }

        return $request;
    }
}
