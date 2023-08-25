<?php
if (!defined('ABSPATH')) {
    exit;
}

return apply_filters(
    'woocommerce_braspag_creditcard_settings',
    array(
        'enabled' => array(
            'title' => __('Enable/Disable', 'woocommerce-braspag'),
            'label' => __('Enable Braspag Credit Card', 'woocommerce-braspag'),
            'type' => 'checkbox',
            'description' => '',
            'default' => 'no',
        ),
        'title' => array(
            'title' => __('Title', 'woocommerce-braspag'),
            'type' => 'text',
            'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-braspag'),
            'default' => __('Cartão de Crédito', 'woocommerce-braspag'),
            'desc_tip' => true,
        ),
        'description' => array(
            'title' => __('Description', 'woocommerce-braspag'),
            'type' => 'text',
            'description' => __('This controls the description which the user sees during checkout.', 'woocommerce-braspag'),
            'desc_tip' => true,
        ),
        'SoftDescriptor' => array(
            'title' => __('Soft Descriptor', 'woocommerce-braspag'),
            'type' => 'text',
            'description' => __('Value that will be concatenated with the value of registration at the acquirer for identification on the invoice.', 'woocommerce-braspag'),
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
        'save_card' => array(
            'title' => __('Save Credit Card Token', 'woocommerce-braspag'),
            'label' => __('', 'woocommerce-braspag'),
            'type' => 'checkbox',
            'description' => 'Choose whether you wish to save Credit Card token.',
            'default' => 'no',
            'desc_tip' => true,
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
        'available_types' => array(
            'title' => __('Available Types', 'woocommerce-braspag'),
            'type' => 'multiselect',
            'class' => 'wc-enhanced-select',
            'description' => __('Choose the payment types during the checkout.', 'woocommerce'),
            'default' => ['Simulado-Simulado' => 'Simulado'],
            'desc_tip' => true,
            'options' => $this->get_creditcard_payment_types_options()
        ),
        'antifraud' => array(
            'title' => "<hr>" . __('Anti Fraud', 'woocommerce-braspag'),
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
        'auth3ds20' => array(
            'title' => "<hr>" . __('Authentication 3DS 2.0 for Credit Card', 'woocommerce-braspag'),
            'type' => 'title',
            'description' => '',
        ),
        'auth3ds20_mpi_is_active' => array(
            'title' => __('Enable', 'woocommerce-braspag'),
            'label' => __('Enable', 'woocommerce-braspag'),
            'type' => 'checkbox',
            'description' => 'Choose whether you wish to enable Authentication 3ds 2.0 for Credit Card.',
            'default' => 'no',
            'desc_tip' => true,
        ),
        'auth3ds20_mpi_mastercard_notify_only' => array(
            'title' => __('MasterCard Notify Only', 'woocommerce-braspag'),
            'label' => __('Enable', 'woocommerce-braspag'),
            'type' => 'checkbox',
            'description' => 'Choose whether you wish to enable Authentication 3ds 2.0 MasterCard Notify Only for Credit Card.',
            'default' => 'no',
            'desc_tip' => true,
        ),
        'auth3ds20_mpi_authorize_on_error' => array(
            'title' => __('Authorization On Error', 'woocommerce-braspag'),
            'label' => __('Enable', 'woocommerce-braspag'),
            'type' => 'checkbox',
            'description' => 'Choose whether you wish to enable Authentication 3ds 2.0 Authorization On Error for Credit Card.',
            'default' => 'no',
            'desc_tip' => true,
        ),
        'auth3ds20_mpi_authorize_on_failure' => array(
            'title' => __('Authorization On Failure', 'woocommerce-braspag'),
            'label' => __('Enable', 'woocommerce-braspag'),
            'type' => 'checkbox',
            'description' => 'Choose whether you wish to enable Authentication 3ds 2.0 Authorization On Failure for Credit Card.',
            'default' => 'no',
            'desc_tip' => true,
        ),
        'auth3ds20_mpi_authorize_on_unenrolled' => array(
            'title' => __('Authorization On Unenrolled', 'woocommerce-braspag'),
            'label' => __('Enable', 'woocommerce-braspag'),
            'type' => 'checkbox',
            'description' => 'Choose whether you wish to enable Authentication 3ds 2.0 Authorization On Unenrolled for Credit Card.',
            'default' => 'no',
            'desc_tip' => true,
        ),
        'auth3ds20_mpi_authorize_on_unsupported_brand' => array(
            'title' => __('Authorization On Unsupported Brand', 'woocommerce-braspag'),
            'label' => __('Enable', 'woocommerce-braspag'),
            'type' => 'checkbox',
            'description' => 'Choose whether you wish to enable Authentication 3ds 2.0 Authorization On Unsupported Brand for Credit Card.',
            'default' => 'no',
            'desc_tip' => true,
        )
    )
);