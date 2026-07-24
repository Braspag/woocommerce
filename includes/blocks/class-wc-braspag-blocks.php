<?php
/**
 * Braspag - Cart/Checkout Blocks Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('woocommerce_blocks_loaded', function () {
    if (!class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        return;
    }

    require_once WC_BRASPAG_PLUGIN_PATH . '/includes/blocks/class-wc-braspag-blocks-abstract.php';
    require_once WC_BRASPAG_PLUGIN_PATH . '/includes/blocks/payments/class-wc-braspag-blocks-main.php';
    require_once WC_BRASPAG_PLUGIN_PATH . '/includes/blocks/payments/class-wc-braspag-blocks-pix.php';
    require_once WC_BRASPAG_PLUGIN_PATH . '/includes/blocks/payments/class-wc-braspag-blocks-boleto.php';
    require_once WC_BRASPAG_PLUGIN_PATH . '/includes/blocks/payments/class-wc-braspag-blocks-creditcard.php';
    require_once WC_BRASPAG_PLUGIN_PATH . '/includes/blocks/payments/class-wc-braspag-blocks-debitcard.php';

    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function (\Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
            $payment_method_registry->register(new WC_Braspag_Blocks_Main());
            $payment_method_registry->register(new WC_Braspag_Blocks_Pix());
            $payment_method_registry->register(new WC_Braspag_Blocks_Boleto());
            $payment_method_registry->register(new WC_Braspag_Blocks_CreditCard());
            $payment_method_registry->register(new WC_Braspag_Blocks_DebitCard());
        }
    );
});