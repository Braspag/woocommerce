<?php
if (!defined('ABSPATH')) {
    exit;
}

return apply_filters(
    'woocommerce_braspag_creditcard_justclick_settings',
    array(
        'enabled' => array(
            'title' => __('Enable/Disable', 'woocommerce-braspag'),
            'label' => __('Enable Braspag Credit Card JustClick', 'woocommerce-braspag'),
            'type' => 'checkbox',
            'description' => '',
            'default' => 'no',
        ),
        'title' => array(
            'title' => __('Title', 'woocommerce-braspag'),
            'type' => 'text',
            'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-braspag'),
            'default' => __('Cartão de Crédito JustClick', 'woocommerce-braspag'),
            'desc_tip' => true,
        ),
        'description' => array(
            'title' => __('Description', 'woocommerce-braspag'),
            'type' => 'text',
            'description' => __('This controls the description which the user sees during checkout.', 'woocommerce-braspag'),
            'desc_tip' => true,
        ),
        'payment_action' => array(
            'title' => __('Payment Action', 'woocommerce-braspag'),
            'type' => 'select',
            'class' => 'wc-enhanced-select',
            'description' => __('Choose whether you wish to capture funds immediately or authorize payment only.', 'woocommerce'),
            'default' => 'authorize',
            'desc_tip' => true,
            'options' => [
                'authorize' => 'Authorize',
                'authorize_capture' => 'Authorize and Capture'
            ]
        ),
        'new_order_status' => array(
            'title' => __('New Order Status', 'woocommerce-braspag'),
            'type' => 'select',
            'class' => 'wc-enhanced-select',
            'description' => __('Choose the new order status after checkout.', 'woocommerce'),
            'default' => 'pending',
            'desc_tip' => true,
            'options' => wc_get_is_pending_statuses()
        ),
        'minimum_amount_of_installment' => array(
            'title' => __('Minimum Amount of Installment', 'woocommerce-braspag'),
            'type' => 'number',
            'description' => __('This controls the minimum installment amount during the checkout.', 'woocommerce-braspag'),
            'desc_tip' => true,
        ),
        'maximum_installments' => array(
            'title' => __('Maximum Installments', 'woocommerce-braspag'),
            'type' => 'number',
            'description' => __('This controls the maximum installment count during the checkout.', 'woocommerce-braspag'),
            'desc_tip' => true,
        ),
        'antifraud' => array(
            'title' => __('Anti Fraud', 'woocommerce-braspag'),
            'type' => 'title',
            'description' => '',
        ),
        'antifraud_reject_order_status' => array(
            'title' => __('Reject Order Status', 'woocommerce-braspag'),
            'type' => 'select',
            'class' => 'wc-enhanced-select',
            'description' => __('Choose the new order status after checkout when order is rejected by Anti Fraud.', 'woocommerce'),
            'default' => 'wc-failed',
            'desc_tip' => true,
            'options' => wc_get_order_statuses()
        ),
        'antifraud_review_order_status' => array(
            'title' => __('Review Order Status', 'woocommerce-braspag'),
            'type' => 'select',
            'class' => 'wc-enhanced-select',
            'description' => __('Choose the new order status after checkout when order is on review by Anti Fraud.', 'woocommerce'),
            'default' => 'wc-pending',
            'desc_tip' => true,
            'options' => wc_get_order_statuses()
        ),
    )
);