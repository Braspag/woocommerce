<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WC_Gateway_Braspag_Pix
 * 
 * @since 2.3.0
 * @version 0.1.0
 *
 */
class WC_Gateway_Braspag_Pix extends WC_Gateway_Braspag
{

    public $enabled;

    public $payment_instructions_for_customer;

    public $payment_instructions_for_bank;

    public $days_to_expire;

    public $available_type;

    public $pix_qr_code_image;

    public function __construct()
    {
        $this->retry_interval = 1;
        $this->id = 'braspag_pix';
        $this->method_title = __('Braspag Pix', 'woocommerce-braspag');
        /* translators: 1) link to Braspag register page 2) link to Braspag api keys page */
        $this->method_description = __('Take payments via Pix with Braspag.');
        $this->has_fields = true;
        $this->supports = array(
            'add_payment_method'
        );

        $this->init_form_fields();

        $this->init_settings();

        $braspag_main_settings = get_option('woocommerce_braspag_settings');

        $braspag_enabled = isset($braspag_main_settings['enabled']) ? $braspag_main_settings['enabled'] : 'no';
        $test_mode = isset($braspag_main_settings['test_mode']) ? $braspag_main_settings['test_mode'] : 'no';

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $braspag_enabled == 'yes' ? $this->get_option('enabled') : 'no';

        $this->test_mode = $test_mode == 'yes';

        $this->payment_instructions_for_customer = $this->get_option('payment_instructions_for_customer');
        $this->payment_instructions_for_bank = $this->get_option('payment_instructions_for_bank');
        $this->days_to_expire = $this->get_option('days_to_expire');
        $this->available_type = $this->get_option('available_type');
        $this->pix_qr_code_image = $this->get_option('pix_qr_code_image');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_customer_save_address', array($this, 'show_update_card_notice'), 10, 2);
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_order_pix_data'));

        add_action('wc_gateway_braspag_pagador_pix_process_payment_after', array($this, 'save_payment_response_data'), 10, 3);
        add_filter("wc_gateway_braspag_pagador_{$this->id}_request_payment_builder", array($this, 'braspag_pagador_pix_payment_request_builder'), 10, 4);
    }

    /**
     * @return bool
     */
    public function is_available()
    {
        return $this->enabled == 'yes';
    }

    public function init_form_fields()
    {
        $this->form_fields = require(WC_BRASPAG_PLUGIN_PATH . '/includes/admin/braspag-pix-settings.php');
    }

    public function payment_fields()
    {
        $descriptionText = '';

        ob_start();

        echo '<div id="braspag-payment-data">';

        do_action('wc_gateway_braspag_pagador_pix_payment_fields_before', $this->id);

        if ($this->test_mode) {
            /* translators: link to Braspag testing page */
            $descriptionText .= ' ' . sprintf(__('TEST MODE ENABLED.', 'woocommerce-braspag')) . "</br></br>";
        }

        $paymentInstructionsForCustomer = apply_filters('wc_gateway_braspag_pix_payment_instructions_for_customer', wpautop(wp_kses_post($this->payment_instructions_for_customer)), $this->id); // wpcs: xss ok.

        $description = $this->get_description();
        $descriptionText .= !empty($description) ? $description . "</br></br>" : '';

        $descriptionText .= $paymentInstructionsForCustomer;
        $descriptionText = trim($descriptionText);

        echo $descriptionText;

        do_action('wc_gateway_braspag_pagador_pix_payment_fields_after', $this->id);

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
    public function process_payment($order_id, $retry = true, $previous_error = false, $use_order_source = false)
    {
        try {
            do_action('wc_gateway_braspag_pagador_pix_process_payment_before', $order_id, $retry, $previous_error, $use_order_source);

            $order = wc_get_order($order_id);

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

            $this->process_pagador_response($response, $order);

            if (isset(WC()->cart)) {
                WC()->cart->empty_cart();
            }

            $this->unlock_order_payment($order);

            do_action('wc_gateway_braspag_pagador_pix_process_payment_after', $order_id, $order, $response);

            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
            );

        } catch (WC_Braspag_Exception $e) {
            wc_add_notice($e->getLocalizedMessage(), 'error');
            WC_Braspag_Logger::log('Error: ' . $e->getMessage());

            do_action('wc_gateway_braspag_pagador_process_payment_error', $e, $order);

            /* translators: error message */
            $order->update_status('failed');

            return array(
                'result' => 'fail',
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
            "_braspag_pix_payment_id" => $response->body->Payment->PaymentId,
            "_braspag_pix_expiration_date" => $response->body->Payment->QrCodeExpiration,
            "_braspag_pix_received_date" => $response->body->Payment->ReceivedDate,
            "_braspag_pix_transaction_id" => $response->body->Payment->AcquirerTransactionId,
            "_braspag_pix_qr_code_image" => $response->body->Payment->QrCodeBase64Image,
            "_braspag_pix_digitable_line" => $response->body->Payment->QrCodeString
        ];

        // WooCommerce 3.0 or later
        if (!method_exists($order, 'update_meta_data')) {
           $dataToSave = array_merge($dataToSave, apply_filters('wc_gateway_braspag_pagador_pix_save_payment_response_data', $dataToSave, $order, $response));

            foreach ($dataToSave as $key => $value) {
                $order = wc_get_order($order_id, $key, $value);
            }
        }else{
            $dataToSave = array_merge($dataToSave, apply_filters('wc_gateway_braspag_pagador_pix_save_payment_response_data', $dataToSave, $order, $response));

            foreach ($dataToSave as $key => $data) {
                $order->update_meta_data($key, $data);
            }

            $order->save();
        }

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
    public function braspag_pagador_pix_payment_request_builder($payment_data, $order, $checkout, $cart)
    {
        $payment_data = array_merge($payment_data, [
            "Provider" => $this->available_type,
            "Type" => "Pix",
            "Amount" => intval($order->get_total() * 100),
            "QrCodeExpiration" => $this->days_to_expire,
        ]);

        return apply_filters('wc_gateway_braspag_pagador_request_pix_payment_builder', $payment_data, $order, $checkout, $cart);
    }

    /**
     * @param $order
     * @return |null
     */
    public function display_order_pix_data($order)
    {
        if ($order->get_payment_method() != $this->id || in_array($order->get_status(), ['processing', 'completed'])) {
            return null;
        }

        $_braspag_pix_expiration_date = $order->get_meta('_braspag_pix_expiration_date');
        $_braspag_pix_received_date = $order->get_meta('_braspag_pix_received_date');
        $explodeExpirationDate = explode("-", $_braspag_pix_expiration_date);
        $startTime = strtotime($_braspag_pix_received_date);
        $endTime = strtotime("+{$explodeExpirationDate[0]} seconds", $startTime);
        $expirationDate = date('H:i', $endTime);

        do_action('wc_gateway_braspag_pagador_pix_display_order_data_before', $order);
        $swf_url = esc_url(plugins_url('assets/images/pix.webp', dirname(dirname(__FILE__))));
        $timer_url = esc_url(plugins_url( 'assets/images/timer.svg', dirname(dirname(__FILE__))));
        ?>
                        <div class="header">
                            <h4>SEU CÓDIGO PIX FOI GERADO</h4>
                            <img class="image-pix" src="<?php echo $swf_url; ?>" alt="pix">
                        </div>
                        <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
                            <tbody>
                                <tr class="woocommerce-table__line-item order_item">
                                    <td class="woocommerce-table__product-total product-total text-center validade-pix" colspan="2">
                                        <p class="stopwatch">
                                            <img class="image-time" src="<?php echo $timer_url; ?>" alt="timer">
                                             Validade do código Pix até às: <strong><?php echo $expirationDate; ?></strong>
                                        </p>
                                    </td>
                                </tr>

                                <tr class="woocommerce-table__line-item order_item">
                                    <td class="woocommerce-table__product-total product-total text-center image-qrcode" colspan="2">
                                        <p>
                                            Para pagar no banco on-line ou aplicativo do seu banco,
                                            <strong>Escanei o QR Code ou copie o código Pix:</strong>
                                        </p>
                                        <?php $imageQrcode = $order->get_meta('_braspag_pix_qr_code_image'); ?>
                                        <image alt="QR-Code PIX" src="data:image/png;base64,<?php echo $imageQrcode; ?>" />
                                    </td>
                                </tr>

                                <tr class="woocommerce-table__line-item order_item">
                                    <td class="woocommerce-table__product-total product-total text-center">
                                        <textarea disabled id="linha-digitavel"><?php echo $order->get_meta('_braspag_pix_digitable_line'); ?></textarea>
                                        <br>
                                        <button onclick="copiarTexto()"><b>Copiar código Pix</b></button>
                                    </td>
                                </tr>

                                <tr class="woocommerce-table__line-item order_item">
                                    <td class="woocommerce-table__product-total product-total text-center" colspan="2">
                                        <p>
                                            Como pagar com Pix:

                                            <ol>
                                                <li>1 - Acesse o app ou site do seu banco</li>
                                                <li>2 - Busque a opção para pagamento com pix</li>
                                                <li>3 - Escaneie o QR code ou copie o código Pix</li>
                                                <li>4 - Pronto! Você verá a confirmação do pagamento</li>
                                            </ol>
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <script>
                            function copiarTexto() {
                                let textoCopiado = document.getElementById("linha-digitavel");

                                textoCopiado.select();
                                textoCopiado.setSelectionRange(0, 99999);

                                navigator.clipboard.writeText(textoCopiado.value);

                                console.log("O texto é: " + textoCopiado.value);
                            }
                        </script>
                        <?php

                        do_action('wc_gateway_braspag_pagador_pix_display_order_data_after', $order);
    }
}