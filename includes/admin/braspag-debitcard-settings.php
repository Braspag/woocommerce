<?php
if (!defined('ABSPATH')) {
    exit;
}

return apply_filters(
    'woocommerce_braspag_debitcard_settings',
    array(
        'enabled' => array(
            'title' => __('Enable/Disable', 'woocommerce-braspag'),
            'label' => __('Enable Braspag Debit Card', 'woocommerce-braspag'),
            'type' => 'checkbox',
            'description' => '',
            'default' => 'no',
        ),
        'title' => array(
            'title' => __('Title', 'woocommerce-braspag'),
            'type' => 'text',
            'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-braspag'),
            'default' => __('Cartão de Débito', 'woocommerce-braspag'),
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
        'new_order_status' => array(
            'title' => __('New Order Status', 'woocommerce-braspag'),
            'type' => 'select',
            'class' => 'wc-enhanced-select',
            'description' => __('Choose the new order status after checkout.', 'woocommerce'),
            'default' => 'pending',
            'desc_tip' => true,
            'options' => wc_get_is_pending_statuses()
        ),
        'available_types' => array(
            'title' => __('Available Type', 'woocommerce-braspag'),
            'type' => 'multiselect',
            'class' => 'wc-enhanced-select',
            'description' => __('Choose the payment type during the checkout.', 'woocommerce'),
            'default' => ['Simulado-Simulado' => 'Simulado'],
            'desc_tip' => true,
            'options' => $this->get_debitcard_payment_types_options()
        ),
        'label_pay_button' => array(
            'title' => __('Label Pay Button', 'woocommerce-braspag'),
            'type' => 'text',
            'description' => __('This controls the label of pay button.', 'woocommerce-braspag'),
            'default' => 'Pay',
            'desc_tip' => true,
        ),
        'bank_automatic_redirect' => array(
            'title' => __('Automatic Redirect', 'woocommerce-braspag'),
            'type' => 'checkbox',
            'description' => 'This controls the redirect for bank page after checkout.',
            'default' => 'no',
        ),
        'bank_return_url' => array(
            'title' => __('Bank Return Url', 'woocommerce-braspag'),
            'type' => 'text',
            'description' => __('This controls the url to redirect after bank authentication. Example: https://URL.FROM.STORE/my-account/view-order/%s/ to redirect to order view page after authentication.', 'woocommerce-braspag'),
            'desc_tip' => false,
        ),
        'auth3ds20' => array(
            'title' => "<hr>" . __('Authentication 3DS 2.0 for Debit Card', 'woocommerce-braspag'),
            'type' => 'title',
            'description' => '',
        ),
        'auth3ds20_mpi_is_active' => array(
            'title' => __('Enable', 'woocommerce-braspag'),
            'label' => __('Enable', 'woocommerce-braspag'),
            'type' => 'checkbox',
            'description' => 'Choose whether you wish to enable Authentication 3ds 2.0 for Debit Card.',
            'default' => 'no',
            'desc_tip' => true,
        ),
        'auth3ds20_mpi_mastercard_notify_only' => array(
            'title' => __('MasterCard Notify Only', 'woocommerce-braspag'),
            'label' => __('Enable', 'woocommerce-braspag'),
            'type' => 'checkbox',
            'description' => 'Choose whether you wish to enable Authentication 3ds 2.0 MasterCard Notify Only for Debit Card.',
            'default' => 'no',
            'desc_tip' => true,
        ),
        'auth3ds20_mpi_authorize_on_error' => array(
            'title' => __('Authorization On Error', 'woocommerce-braspag'),
            'label' => __('Enable', 'woocommerce-braspag'),
            'type' => 'checkbox',
            'description' => 'Choose whether you wish to enable Authentication 3ds 2.0 Authorization On Error for Debit Card.',
            'default' => 'no',
            'desc_tip' => true,
        ),
        'auth3ds20_mpi_authorize_on_failure' => array(
            'title' => __('Authorization On Failure', 'woocommerce-braspag'),
            'label' => __('Enable', 'woocommerce-braspag'),
            'type' => 'checkbox',
            'description' => 'Choose whether you wish to enable Authentication 3ds 2.0 Authorization On Failure for Debit Card.',
            'default' => 'no',
            'desc_tip' => true,
        ),
        'auth3ds20_mpi_authorize_on_unenrolled' => array(
            'title' => __('Authorization On Unenrolled', 'woocommerce-braspag'),
            'label' => __('Enable', 'woocommerce-braspag'),
            'type' => 'checkbox',
            'description' => 'Choose whether you wish to enable Authentication 3ds 2.0 Authorization On Unenrolled for Debit Card.',
            'default' => 'no',
            'desc_tip' => true,
        ),
        'auth3ds20_mpi_authorize_on_unsupported_brand' => array(
            'title' => __('Authorization On Unsupported Brand', 'woocommerce-braspag'),
            'label' => __('Enable', 'woocommerce-braspag'),
            'type' => 'checkbox',
            'description' => 'Choose whether you wish to enable Authentication 3ds 2.0 Authorization On Unsupported Brand for Debit Card.',
            'default' => 'no',
            'desc_tip' => true,
        )
    )
);