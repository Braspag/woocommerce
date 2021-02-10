<?php
/**
 * Extra checkout fields customer admin.
 *
 * @package Techvil_Marketplace_Sellers/Admin/Customer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Techvil_Marketplace_Sellers_Customer class.
 */
class Braspag_Customer_Seller_Attributes {

    protected $profile_user;
    protected $user_id;

	/**
	 * Initialize the customer actions.
	 */
	public function __construct() {
		add_filter( 'woocommerce_customer_meta_fields', array( $this, 'customer_meta_fields' ) );
	}

	/**
	 * Custom user edit fields.
	 *
	 * @param  array $fields Default fields.
	 *
	 * @return array         Custom fields.
	 */
	public function customer_meta_fields( $fields ) {

        global $user_id;

        $profileuser = get_user_to_edit( $user_id );

        $user_roles = array_intersect( array_values( $profileuser->roles ), array_keys( get_editable_roles() ) );
        $user_role  = reset( $user_roles );

	    if (!preg_match("#seller#is", $user_role)) {
	        return $fields;
        }

		$new_fields['braspag_sellers']['title'] = __( 'Seller', 'woocommerce-braspag' );

        $new_fields['braspag_sellers']['fields']['merchant_id'] = array(
            'label'       => __( 'Merchant ID', 'woocommerce-braspag' ),
            'description' => '',
        );

		$new_fields['braspag_split_payment']['title'] = __( 'Seller - Splits Payment', 'woocommerce-braspag' );

        $new_fields['braspag_split_payment']['fields']['mdr'] = array(
            'label'       => __( 'Mdr', 'woocommerce-braspag' ),
            'description' => '',
        );
        $new_fields['braspag_split_payment']['fields']['fee'] = array(
            'label'       => __( 'Fee', 'woocommerce-braspag' ),
            'description' => '',
        );

        $fields = array_merge($new_fields, $fields);

        $fields = apply_filters( 'wc_braspag_customer_seller_meta_fields', $fields );

		return $fields;
	}
}

new Braspag_Customer_Seller_Attributes();
