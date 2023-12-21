<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class WC_Braspag_Helper
 */
class WC_Braspag_Helper
{
	const META_NAME_BRASPAG_CURRENCY = '_braspag_currency';

	/**
	 * @param null $order
	 * @return bool|mixed
	 */
	public static function get_braspag_currency($order = null)
	{
		if (is_null($order)) {
			return false;
		}

		$order_id = WC_Braspag_Helper::is_wc_lt('3.0') ? $order->id : $order->get_id();

		return WC_Braspag_Helper::is_wc_lt('3.0') ? get_post_meta($order_id, self::META_NAME_BRASPAG_CURRENCY, true) : $order->get_meta(self::META_NAME_BRASPAG_CURRENCY, true);
	}

	/**
	 * @param null $order
	 * @param $currency
	 * @return bool
	 */
	public static function update_braspag_currency($order = null, $currency)
	{
		if (is_null($order)) {
			return false;
		}

		$order_id = WC_Braspag_Helper::is_wc_lt('3.0') ? $order->id : $order->get_id();

		WC_Braspag_Helper::is_wc_lt('3.0') ? update_post_meta($order_id, self::META_NAME_BRASPAG_CURRENCY, $currency) : $order->update_meta_data(self::META_NAME_BRASPAG_CURRENCY, $currency);
	}

	/**
	 * @return mixed|void
	 */
	public static function get_localized_messages()
	{
		return apply_filters(
			'wc_braspag_localized_messages',
			array(
				'invalid_number' => __('The card number is not a valid credit card number.', 'woocommerce-braspag'),
				'invalid_expiry_month' => __('The card\'s expiration month is invalid.', 'woocommerce-braspag'),
				'invalid_expiry_year' => __('The card\'s expiration year is invalid.', 'woocommerce-braspag'),
				'invalid_cvc' => __('The card\'s security code is invalid.', 'woocommerce-braspag'),
				'incorrect_number' => __('The card number is incorrect.', 'woocommerce-braspag'),
				'incomplete_number' => __('The card number is incomplete.', 'woocommerce-braspag'),
				'incomplete_cvc' => __('The card\'s security code is incomplete.', 'woocommerce-braspag'),
				'incomplete_expiry' => __('The card\'s expiration date is incomplete.', 'woocommerce-braspag'),
				'expired_card' => __('The card has expired.', 'woocommerce-braspag'),
				'incorrect_cvc' => __('The card\'s security code is incorrect.', 'woocommerce-braspag'),
				'incorrect_zip' => __('The card\'s zip code failed validation.', 'woocommerce-braspag'),
				'invalid_expiry_year_past' => __('The card\'s expiration year is in the past', 'woocommerce-braspag'),
				'card_declined' => __('The card was declined.', 'woocommerce-braspag'),
				'missing' => __('There is no card on a customer that is being charged.', 'woocommerce-braspag'),
				'processing_error' => __('An error occurred while processing the card.', 'woocommerce-braspag'),
				'invalid_request_error' => __('Unable to process this payment, please try again or use alternative method.', 'woocommerce-braspag'),
				'invalid_sofort_country' => __('The billing country is not accepted by SOFORT. Please try another country.', 'woocommerce-braspag'),
				'email_invalid' => __('Invalid email address, please correct and try again.', 'woocommerce-braspag'),
			)
		);
	}

	/**
	 * @param null $method
	 * @param null $setting
	 * @return mixed|string|void
	 */
	public static function get_settings($method = null, $setting = null)
	{
		$all_settings = null === $method ? get_option('woocommerce_braspag_settings', array()) : get_option('wc_braspag_' . $method . '_settings', array());

		if (null === $setting) {
			return $all_settings;
		}

		return isset($all_settings[$setting]) ? $all_settings[$setting] : '';
	}

	/**
	 * @param $version
	 * @return bool|int
	 */
	public static function is_wc_lt($version)
	{
		return version_compare(WC_VERSION, $version, '<');
	}

	/**
	 * @return string
	 */
	public static function get_webhook_url()
	{
		return add_query_arg('wc-api', 'wc_braspag', trailingslashit(get_home_url()));
	}

	/**
	 * @param $charge_id
	 * @return bool|WC_Order|WC_Order_Refund
	 */
	public static function get_order_by_charge_id($charge_id)
	{
		global $wpdb;

		if (empty($charge_id)) {
			return false;
		}

		$order_id = $wpdb->get_var($wpdb->prepare("SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_value = %s AND meta.meta_key = %s", $charge_id, '_transaction_id'));

		if (!empty($order_id)) {
			return wc_get_order($order_id);
		}

		return false;
	}
}