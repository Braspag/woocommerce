<?php
if (!defined('ABSPATH')) {
    exit;
}

return apply_filters(
    'woocommerce_braspag_pix_settings',
    array(
        'enabled' => array(
            'title' => __('Enable/Disable', 'woocommerce-braspag'),
            'label' => __('Enable Braspag Pix', 'woocommerce-braspag'),
            'type' => 'checkbox',
            'description' => 'This controls enable methods PIX during checkout.',
            'default' => 'no',
            'desc_tip' => true,
        ),
        'title' => array(
            'title' => __('Title', 'woocommerce-braspag'),
            'type' => 'text',
            'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-braspag'),
            'default' => __('Pix', 'woocommerce-braspag'),
            'desc_tip' => true,
        ),
        'description' => array(
            'title' => __('Description', 'woocommerce-braspag'),
            'type' => 'text',
            'description' => __('This controls the description which the user sees during checkout.', 'woocommerce-braspag'),
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
        'available_type' => array(
            'title' => __('Available Type', 'woocommerce-braspag'),
            'type' => 'select',
            'class' => 'wc-enhanced-select',
            'description' => __('Choose the payment type during the checkout.', 'woocommerce'),
            'default' => 'Simulado',
            'desc_tip' => true,
            'options' => [
                "Cielo30" => "Pix Gateway -> Cielo30",
				"Cielo2" => "Pix Gateway -> Cielo2",
                "Bradesco2" => "Pix Gateway -> Bradesco",
                "Simulado" => "Simulado"
            ]
        ),
        'payment_instructions_for_customer' => array(
            'title' => __('Payment Instructions for Customer', 'woocommerce-braspag'),
            'type' => 'textarea',
            'description' => __('This controls the payment instructions which the user sees during checkout.', 'woocommerce-braspag'),
            'default' => 'Pagamento Pix - Braspag. Clique em finalizar pedido e escaneie seu QR code. ',
            'desc_tip' => true,
        ),
        'days_to_expire' => array(
            'title' => __('Seconds to Expire', 'woocommerce-braspag'),
            'type' => 'number',
            'description' => __('Tempo de expiração do QR Code, em segundos. Ex: 24 horas = 86400.
            Para provider Cielo30: o tempo de expiração é de 24 horas e não é parametrizavel.
            Para provider Bradesco: o tempo de expiração do QR Code pode ser configurado no painel Shopfácil ou no momento da autorização pelo parâmetro.', 'woocommerce-braspag'),
            'desc_tip' => true,
        ),
    )
);