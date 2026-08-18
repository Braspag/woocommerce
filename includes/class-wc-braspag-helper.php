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
	 * @param $order
	 * @return bool|mixed
	 */
	public static function get_braspag_currency($order)
	{
		if (is_null($order)) {
			return false;
		}

		$order_id = WC_Braspag_Helper::is_wc_lt('3.0') ? $order->id : $order->get_id();

		return WC_Braspag_Helper::is_wc_lt('3.0') ? $order->get_meta($order_id, self::META_NAME_BRASPAG_CURRENCY, true) : $order->get_meta(self::META_NAME_BRASPAG_CURRENCY, true);
	}

	/**
	 * @param WC_Order $order
	 * @param string $currency
	 * @return bool
	 */
	public static function update_braspag_currency($order, $currency)
	{
		if (empty($order) || empty($currency)) {
			return false;
		}

		$order_id = WC_Braspag_Helper::is_wc_lt('3.0') ? $order->id : $order->get_id();

		WC_Braspag_Helper::is_wc_lt('3.0') ? $order = wc_get_order($order_id, self::META_NAME_BRASPAG_CURRENCY, $currency) : $order->update_meta_data(self::META_NAME_BRASPAG_CURRENCY, $currency);

		return true;
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
		return version_compare(defined('WC_VERSION') ? WC_VERSION : (WC()->version ?? get_option('woocommerce_version')), $version, '<');
	}

	/**
	 * @return string
	 */
	public static function get_webhook_url()
	{
		return add_query_arg('wc-api', 'wc_braspag', trailingslashit(get_home_url()));
	}

	/**
	 * @param string   $charge_id  PaymentId do Braspag
	 * @param string[] $extra_meta Meta keys adicionais para busca (ex: _braspag_pix_payment_id)
	 * @return WC_Order|false
	 */
	public static function get_order_by_charge_id(string $charge_id, array $extra_meta = [])
	{
		if (empty($charge_id)) {
			return false;
		}

		$order = self::find_order_by_transaction_id($charge_id);
		if ($order) {
			return $order;
		}

		foreach ($extra_meta as $meta_key) {
			$order = self::find_order_by_meta_key($meta_key, $charge_id);
			if ($order) {
				return $order;
			}
		}

		return self::find_order_by_legacy_sql($charge_id, $extra_meta);
	}

	private static function find_order_by_transaction_id(string $charge_id)
	{
		if (!function_exists('wc_get_orders')) {
			return false;
		}

		return self::extract_order(wc_get_orders([
			'limit'          => 1,
			'type'           => 'shop_order',
			'transaction_id' => $charge_id,
		]));
	}

	private static function find_order_by_meta_key(string $meta_key, string $charge_id)
	{
		if (!function_exists('wc_get_orders')) {
			return false;
		}

		return self::extract_order(wc_get_orders([
			'limit'      => 1,
			'type'       => 'shop_order',
			'meta_query' => [[
				'key'   => $meta_key,
				'value' => $charge_id,
			]],
		]));
	}

	private static function find_order_by_legacy_sql(string $charge_id, array $extra_meta = [])
	{
		$order = self::extract_order(wc_get_orders([
			'limit'      => 1,
			'type'       => 'shop_order',
			'meta_query' => [[
				'key'   => '_transaction_id',
				'value' => $charge_id,
			]],
		]));

		if (FALSE !== $order) {
			return $order;
		}

		foreach ($extra_meta as $meta_key) {
			$order = self::extract_order(wc_get_orders([
				'limit'      => 1,
				'type'       => 'shop_order',
				'meta_query' => [[
					'key'   => $meta_key,
					'value' => $charge_id,
				]],
			]));

			if (FALSE !== $order) {
				return $order;
			}
		}

		return false;
	}

	private static function extract_order(array $orders)
	{
		if (!empty($orders) && $orders[0] instanceof WC_Order) {
			return $orders[0];
		}
		return false;
	}

	/**
	 * Identifica se a requisição atual partiu do Checkout Blocks (Store API)
	 * ou do Checkout Clássico (formulário/admin-ajax).
	 *
	 * @return string 'blocks'|'classic'|'unknown'
	 */
	public static function get_checkout_type()
	{
		if (defined('REST_REQUEST') && REST_REQUEST) {
			$route = isset($GLOBALS['wp']->query_vars['rest_route']) ? $GLOBALS['wp']->query_vars['rest_route'] : '';
			if (empty($route) && !empty($_SERVER['REQUEST_URI'])) {
				$route = wp_parse_url(sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])), PHP_URL_PATH);
			}

			if (is_string($route) && (strpos($route, '/wc/store') !== false || strpos($route, 'wc/store') !== false)) {
				return 'blocks';
			}

			return 'unknown';
		}

		if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
			return 'classic';
		}

		if (function_exists('is_checkout') && is_checkout()) {
			return 'classic';
		}

		return 'unknown';
	}

	/**
	 * Monta o contexto de ambiente/versões a ser anexado aos logs do plugin.
	 *
	 * @return array
	 */
	public static function get_log_context()
	{
		global $wp_version;

		return array(
			'checkout_type' => self::get_checkout_type(),
			'module_version' => defined('WC_BRASPAG_VERSION') ? WC_BRASPAG_VERSION : '',
			'php_version' => phpversion(),
			'wp_version' => function_exists('get_bloginfo') ? get_bloginfo('version') : $wp_version,
			'wc_version' => defined('WC_VERSION') ? WC_VERSION : (function_exists('WC') && WC() ? WC()->version : get_option('woocommerce_version')),
			'request_uri' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '',
			'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
		);
	}
}