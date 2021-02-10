<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// if uninstall not called from WordPress exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/*
 * Only remove ALL product and page data if WC_REMOVE_ALL_DATA constant is set to true in user's
 * wp-config.php. This is to prevent data loss when deleting the plugin from the backend
 * and to ensure only the site owner can perform this action.
 */
if ( defined( 'WC_REMOVE_ALL_DATA' ) && true === WC_REMOVE_ALL_DATA ) {
	// Delete options.
	delete_option( 'woocommerce_braspag_settings' );
	delete_option( 'woocommerce_braspag_show_styles_notice' );
	delete_option( 'woocommerce_braspag_show_request_api_notice' );
	delete_option( 'woocommerce_braspag_show_ssl_notice' );
	delete_option( 'woocommerce_braspag_show_keys_notice' );
	delete_option( 'woocommerce_braspag_show_creditcard_notice' );
	delete_option( 'woocommerce_braspag_show_creditcard_justclick_notice' );
	delete_option( 'woocommerce_braspag_show_debitcard_notice' );
	delete_option( 'woocommerce_braspag_show_boleto_notice' );
	delete_option( 'woocommerce_braspag_version' );
	delete_option( 'woocommerce_braspag_creditcard_settings' );
	delete_option( 'woocommerce_braspag_creditcard_justclick_settings' );
	delete_option( 'woocommerce_braspag_debitcard_settings' );
	delete_option( 'woocommerce_braspag_boleto_settings' );
}
