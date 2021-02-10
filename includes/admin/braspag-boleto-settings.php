<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return apply_filters(
	'woocommerce_braspag_boleto_settings',
	array(
		'enabled'     => array(
			'title'       => __( 'Enable/Disable', 'woocommerce-braspag' ),
			'label'       => __( 'Enable Braspag Boleto', 'woocommerce-braspag' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		),
		'title'       => array(
			'title'       => __( 'Title', 'woocommerce-braspag' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-braspag' ),
			'default'     => __( 'Boleto', 'woocommerce-braspag' ),
			'desc_tip'    => true,
		),
		'description' => array(
			'title'       => __( 'Description', 'woocommerce-braspag' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-braspag' ),
			'desc_tip'    => true,
		),
        'new_order_status' => array(
            'title'       => __( 'New Order Status', 'woocommerce-braspag' ),
            'type'        => 'select',
            'class'       => 'wc-enhanced-select',
            'description' => __( 'Choose the new order status after checkout.', 'woocommerce' ),
            'default'     => 'pending',
            'desc_tip'    => true,
            'options'     => wc_get_is_pending_statuses()
        ),
        'available_type' => array(
            'title'       => __( 'Available Type', 'woocommerce-braspag' ),
            'type'        => 'select',
            'class'       => 'wc-enhanced-select',
            'description' => __( 'Choose the payment type during the checkout.', 'woocommerce' ),
            'default'     => 'Simulado',
            'desc_tip'    => true,
            'options'     => [
                "Braspag" => "Boleto Registrado Braspag",
                "Bradesco2" => "Boleto Registrado Bradesco",
                "BancoDoBrasil2" => "Boleto Registrado Banco do Brasil",
                "ItauShopline" => "Boleto Registrado Itau Shopline",
                "Itau2" => "Boleto Registrado Itau",
                "Santander2" => "Boleto Registrado Santander",
                "Caixa2" => "Boleto Registrado Caixa",
                "CitiBank2" => "Boleto Registrado Citi Bank",
                "BankOfAmerica" => "Boleto Registrado Bank Of America",
                "Simulado" => "Simulado"
            ]
        ),
        'payment_instructions_for_customer' => array(
            'title'       => __( 'Payment Instructions for Customer', 'woocommerce-braspag' ),
            'type'        => 'textarea',
            'description' => __( 'This controls the payment instructions which the user sees during checkout.', 'woocommerce-braspag' ),
            'default' => 'Pagável em qualquer agência bancária ou bankline até a data do vencimento.',
            'desc_tip'    => true,
        ),
        'payment_instructions_for_bank' => array(
            'title'       => __( 'Payment Instructions for Bank', 'woocommerce-braspag' ),
            'type'        => 'textarea',
            'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-braspag' ),
            'default' => "Sr. Caixa, não conceder desconto. \n Não receber após o vencimento.",
            'desc_tip'    => true,
        ),
        'label_print_button' => array(
            'title'       => __( 'Label Print Button', 'woocommerce-braspag' ),
            'type'        => 'text',
            'description' => __( 'This controls the label of print button.', 'woocommerce-braspag' ),
            'default'     => 'Print',
            'desc_tip'    => true,
        ),
        'days_to_expire' => array(
            'title'       => __( 'Days to Expire', 'woocommerce-braspag' ),
            'type'        => 'number',
            'description' => __( 'This controls the days to boleto expire.', 'woocommerce-braspag' ),
            'desc_tip'    => true,
        ),
	)
);
