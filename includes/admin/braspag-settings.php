<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return apply_filters(
	'woocommerce_braspag_settings',
	array(
        'title'       => array(
            'title'       => __( 'Global Settings', 'woocommerce-braspag' ),
            'type'        => 'title',
            'description' => '',
            'desc_tip'    => true,
        ),
		'enabled'                       => array(
			'title'       => __( 'Enable/Disable', 'woocommerce-braspag' ),
			'label'       => __( 'Enable Braspag', 'woocommerce-braspag' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		),
		'test_mode'       => array(
			'title'       => __( 'Test mode', 'woocommerce-braspag' ),
			'label'       => __( 'Enable Test Mode', 'woocommerce-braspag' ),
			'type'        => 'checkbox',
			'description' => __( 'Place the payment gateway in test mode using test API credentials.', 'woocommerce-braspag' ),
			'default'     => 'no',
			'desc_tip'    => true,
		),
        'debug'                       => array(
            'title'       => __( 'Debug', 'woocommerce-braspag' ),
            'label'       => __( 'Log debug messages', 'woocommerce-braspag' ),
            'type'        => 'checkbox',
            'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'woocommerce-braspag' ),
            'default'     => 'no',
            'desc_tip'    => true,
        ),
		'merchant_id'          => array(
			'title'       => __( 'Merchant ID', 'woocommerce-braspag' ),
			'type'        => 'text',
			'description' => __( 'Get your Merchant ID from Braspag Support.', 'woocommerce-braspag' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'merchant_key'               => array(
			'title'       => __( 'Merchant Key', 'woocommerce-braspag' ),
			'type'        => 'text',
			'description' => __( 'Get your Merchant Key from Braspag Support.', 'woocommerce-braspag' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'merchant_name'           => array(
			'title'       => __( 'Merchant Name', 'woocommerce-braspag' ),
			'type'        => 'text',
			'description' => __( 'Get your Merchant Name from Braspag Support.', 'woocommerce-braspag' ),
			'default'     => '',
			'desc_tip'    => true,
		),
        'merchant_category'           => array(
            'title'       => __( 'Merchant Category', 'woocommerce-braspag' ),
            'type'        => 'text',
            'description' => __( 'Get your Merchant Category from Braspag Support.', 'woocommerce-braspag' ),
            'default'     => '',
            'desc_tip'    => true,
        ),
		'establishment_code'               => array(
			'title'       => __( 'Establishment Code', 'woocommerce-braspag' ),
			'type'        => 'text',
			'description' => __( 'Get your Merchant Establishment Code from Braspag Support.', 'woocommerce-braspag' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'mcc'                    => array(
			'title'       => __( 'MCC', 'woocommerce-braspag' ),
			'type'        => 'text',
			'description' => __( 'Get your Merchant MCC Code from Braspag Support.', 'woocommerce-braspag' ),
			'default'     => '',
			'desc_tip'    => true,
		),
        'oauth_authentication'                       => array(
            'title'       => "<hr>".__( 'OAuth Authentication', 'woocommerce-braspag' ),
            'type'        => 'title',
            /* translators: post_notification URL */
            'description' => '',
        ),
        'oauth_authentication_client_id'                       => array(
            'title'       => __( 'Client ID', 'woocommerce-braspag' ),
            'type'        => 'text',
            'description' => __( 'Get your OAuth Client ID from Braspag Support.', 'woocommerce-braspag' ),
            'default'     => '',
            'desc_tip'    => true,
        ),
        'oauth_authentication_client_secret'                       => array(
            'title'       => __( 'Client Secret', 'woocommerce-braspag' ),
            'type'        => 'text',
            'description' => __( 'Get your OAuth Client Secret from Braspag Support.', 'woocommerce-braspag' ),
            'default'     => '',
            'desc_tip'    => true,
        ),
        'post_notification'                       => array(
            'title'       => "<hr>".__( 'Post Notification', 'woocommerce-braspag' ),
            'type'        => 'title',
            /* translators: post_notification URL */
            'description' => $this->display_admin_settings_webhook_description(),
        ),
        'antifraud'                       => array(
            'title'       => "<hr>".__( 'Anti Fraud', 'woocommerce-braspag' ),
            'type'        => 'title',
            'description' => '',
        ),
        'antifraud_enabled'                       => array(
            'title'       => __( 'Enable/Disable', 'woocommerce-braspag' ),
            'label'       => __( 'Enable Anti Fraud', 'woocommerce-braspag' ),
            'type'        => 'checkbox',
            'description' => '',
            'default'     => 'no',
        ),
        'antifraud_send_with_pagador_transaction'                       => array(
            'title'       => __( 'Send with Pagador Transaction', 'woocommerce-braspag' ),
            'label'       => __( 'Yes', 'woocommerce-braspag' ),
            'type'        => 'checkbox',
            'description' => __( 'Choose whether you wish to send Anti Fraud data with Pagador or not.'),
            'default'     => 'no',
            'desc_tip'    => true,
        ),
        'antifraud_finger_print_org_id'                       => array(
            'title'       => __( 'Finger Print Org ID', 'woocommerce-braspag' ),
            'type'        => 'text',
            'description' => __( 'Get your Finger Print Org ID from Braspag Support.', 'woocommerce-braspag' ),
            'default'     => '',
            'desc_tip'    => true,
        ),
        'antifraud_finger_print_merchant_id'                       => array(
            'title'       => __( 'Finger Print Merchant ID', 'woocommerce-braspag' ),
            'type'        => 'text',
            'description' => __( 'Get your Finger Print Merchant ID from Braspag Support.', 'woocommerce-braspag' ),
            'default'     => '',
            'desc_tip'    => true,
        ),
        'antifraud_finger_print_use_order_id'                       => array(
            'title'       => __( 'Finger Print Use Order ID', 'woocommerce-braspag' ),
            'label'       => __( 'Use Order ID', 'woocommerce-braspag' ),
            'type'        => 'checkbox',
            'description' => __( 'Choose whether you wish to use Order ID to compose Finger Print ID or not', 'woocommerce-braspag' ),
            'default'     => 'no',
            'desc_tip'    => true,
        ),
        'antifraud_options_sequence'                       => array(
            'title'       => __( 'Options Sequence', 'woocommerce-braspag' ),
            'type'        => 'select',
            'description' => __( 'This controls the option Sequence data to send for Braspag.', 'woocommerce-braspag' ),
            'default'     => '',
            'options'     => array(
                'AnalyseOnly' =>  __( 'Analyse Only', 'woocommerce' ),
                'AnalyseFirst' =>  __( 'Analyse First', 'woocommerce' ),
                'AuthorizeFirst' => __( 'Authorize First', 'woocommerce' )
            ),
            'desc_tip'    => true,
        ),
        'antifraud_options_sequence_criteria'                       => array(
            'title'       => __( 'Options Sequence Criteria', 'woocommerce-braspag' ),
            'type'        => 'select',
            'description' => __( 'This controls the option Sequence Criteria data to send for Braspag.', 'woocommerce-braspag' ),
            'default'     => '',
            'options'     => array(
                'OnSuccess' =>  __( 'On Success', 'woocommerce' ),
                'Always' => __( 'Always', 'woocommerce' )
            ),
            'desc_tip'    => true,
        ),
        'antifraud_options_capture_on_low_risk'                       => array(
            'title'       => __( 'Options Capture On Low Risk', 'woocommerce-braspag' ),
            'label'       => __( 'Capture On Low Risk', 'woocommerce-braspag' ),
            'type'        => 'checkbox',
            'description' => __( 'This controls the option Capture On Low Risk data to send for Braspag.', 'woocommerce-braspag' ),
            'default'     => 'no',
            'desc_tip'    => true,
        ),
        'antifraud_options_void_on_righ_risk'                       => array(
            'title'       => __( 'Options Void On High Risk', 'woocommerce-braspag' ),
            'label'       => __( 'Void On High Risk', 'woocommerce-braspag' ),
            'type'        => 'checkbox',
            'description' => __( 'This controls the option Void On Hgh Risk data to send for Braspag.', 'woocommerce-braspag' ),
            'default'     => 'no',
            'desc_tip'    => true,
        ),
        'auth3ds20'      => array(
            'title'       => "<hr>".__( 'Authentication 3DS 2.0', 'woocommerce-braspag' ),
            'type'        => 'title',
            'description' => '',
        ),
        'auth3ds20_oauth_authentication_client_id'                       => array(
            'title'       => __( 'Client ID', 'woocommerce-braspag' ),
            'type'        => 'text',
            'description' => __( 'Get your Authentication 3DS 2.0 OAuth Client ID from Braspag Support.', 'woocommerce-braspag' ),
            'default'     => '',
            'desc_tip'    => true,
        ),
        'auth3ds20_oauth_authentication_client_secret'                       => array(
            'title'       => __( 'Client Secret', 'woocommerce-braspag' ),
            'type'        => 'text',
            'description' => __( 'Get your Authentication 3DS 2.0 Client Secret from Braspag Support.', 'woocommerce-braspag' ),
            'default'     => '',
            'desc_tip'    => true,
        )
	)
);
