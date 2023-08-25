<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class WC_Braspag_Admin_Notices
 */
class WC_Braspag_Admin_Notices
{
	public $notices = array();

	public function __construct()
	{
		add_action('admin_notices', array($this, 'admin_notices'));
		add_action('wp_loaded', array($this, 'hide_notices'));
		add_action('wc_braspag_updated', array($this, 'braspag_updated'));
	}

	/**
	 * @param $slug
	 * @param $class
	 * @param $message
	 * @param bool $dismissible
	 */
	public function add_admin_notice($slug, $class, $message, $dismissible = false)
	{
		$this->notices[$slug] = array(
			'class' => $class,
			'message' => $message,
			'dismissible' => $dismissible,
		);
	}

	public function admin_notices()
	{
		if (!current_user_can('manage_woocommerce')) {
			return;
		}

		$this->braspag_check_environment();

		$this->payment_methods_check_environment();

		foreach ((array) $this->notices as $notice_key => $notice) {
			echo '<div class="' . esc_attr($notice['class']) . '" style="position:relative;">';

			if ($notice['dismissible']) {
				?>
					<a href="<?php echo esc_url(wp_nonce_url(add_query_arg('wc-braspag-hide-notice', $notice_key), 'wc_braspag_hide_notices_nonce', '_wc_braspag_notice_nonce')); ?>" class="woocommerce-message-close notice-dismiss" style="position:relative;float:right;padding:9px 0px 9px 9px 9px;text-decoration:none;"></a>
				<?php
			}

			echo '<p>';
			echo wp_kses($notice['message'], array('a' => array('href' => array(), 'target' => array())));
			echo '</p></div>';
		}
	}

	/**
	 * Get payments Methods
	 * 
	 * Method get for return payments methods this plugin
	 * 
	 * @since 1.0.0
	 * @version 0.3.0
	 * 
	 * @return array
	 */
	public function get_payment_methods()
	{
		return array(
			'CreditCard' => 'WC_Gateway_Braspag_CreditCard',
			'CreditCard_JustClick' => 'WC_Gateway_Braspag_CreditCard_JustClick',
			'DebitCard' => 'WC_Gateway_Braspag_DebitCard',
			'Boleto' => 'WC_Gateway_Braspag_Boleto',
			'Pix' => 'WC_Gateway_Braspag_Pix',
		);
	}

	/**
	 * Check versions the system
	 * 
	 * Method get for return payments methods this plugin
	 * 
	 * @since 1.0.0
	 * @version 0.2.0
	 * 
	 * @return void
	 */
	public function braspag_check_environment()
	{
		$show_style_notice = get_option('wc_braspag_show_style_notice');
		$show_ssl_notice = get_option('woocommerce_braspag_show_ssl_notice');
		$show_phpver_notice = get_option('wc_braspag_show_phpver_notice');
		$show_wcver_notice = get_option('wc_braspag_show_wcver_notice');
		$show_wpver_notice = get_option('wc_braspag_show_wpver_notice');
		$show_curl_notice = get_option('wc_braspag_show_curl_notice');
		$options = get_option('woocommerce_braspag_settings');
		$test_mode = (isset($options['test_mode']) && 'yes' === $options['test_mode']) ? true : false;

		if (isset($options['enabled']) && 'yes' === $options['enabled']) {

			if (empty($show_style_notice)) {
				/* translators: 1) int version 2) int version */
				$message = __('WooCommerce Braspag - We recently made changes to Braspag that may impact the appearance of your checkout.', 'woocommerce-braspag');

				$this->add_admin_notice('style', 'notice notice-warning', $message, true);

				return;
			}

			if (empty($show_phpver_notice)) {
				if (version_compare(phpversion(), WC_BRASPAG_MIN_PHP_VER, '<')) {
					/* translators: 1) int version 2) int version */
					$message = __('WooCommerce Braspag - The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-braspag');

					$this->add_admin_notice('phpver', 'error', sprintf($message, WC_BRASPAG_MIN_PHP_VER, phpversion()), true);

					return;
				}
			}

			if (empty($show_wcver_notice)) {
				if (version_compare(WC_VERSION, WC_BRASPAG_MIN_WC_VER, '<')) {
					/* translators: 1) int version 2) int version */
					$message = __('WooCommerce Braspag - The minimum WooCommerce version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-braspag');

					$this->add_admin_notice('wcver', 'notice notice-warning', sprintf($message, WC_BRASPAG_MIN_WC_VER, WC_VERSION), true);

					return;
				}
			}

			if (empty($show_wpver_notice)) {
				if (version_compare(WC_BRASPAG_WP_VERSION, WC_BRASPAG_MIN_WP_VER, '<')) {
					/* translators: 1) int version 2) int version */
					$message = __('Wordpress Braspag - The minimum Wordpress version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-braspag');

					$this->add_admin_notice('wcver', 'notice notice-warning', sprintf($message, WC_BRASPAG_MIN_WC_VER, WC_BRASPAG_WP_VERSION), true);

					return;
				}
			}

			if (empty($show_curl_notice)) {
				if (!function_exists('curl_init')) {
					$this->add_admin_notice('curl', 'notice notice-warning', __('WooCommerce Braspag - cURL is not installed.', 'woocommerce-braspag'), true);
				}
			}

			if (empty($show_ssl_notice)) {

				if (!wc_checkout_is_https()) {
					/* translators: 1) link */
					$this->add_admin_notice('ssl', 'notice notice-warning', sprintf(__('Braspag is enabled, but a SSL certificate is not detected. Your checkout may not be secure! Please ensure your server has a valid <a href="%1$s" target="_blank">SSL certificate</a>', 'woocommerce-braspag'), 'https://en.wikipedia.org/wiki/Transport_Layer_Security'), true);
				}
			}
		}
	}

	public function payment_methods_check_environment()
	{
		$payment_methods = $this->get_payment_methods();

		foreach ($payment_methods as $method => $class) {
			$show_notice = get_option('wc_braspag_show_' . strtolower($method) . '_notice');

			$gateway = new $class();

			if ('yes' !== $gateway->enabled || 'no' === $show_notice) {
				continue;
			}

			if (!in_array(get_woocommerce_currency(), $gateway->get_supported_currency())) {
				/* translators: %1$s Payment method, %2$s List of supported currencies */
				$this->add_admin_notice($method, 'notice notice-error', sprintf(__('%1$s is enabled - it requires store currency to be set to %2$s', 'woocommerce-braspag'), $method, implode(', ', $gateway->get_supported_currency())), true);
			}
		}
	}

	/**
	 * Get payments Methods
	 * 
	 * Method get for return payments methods this plugin
	 * 
	 * @since 1.0.0
	 * @version 0.2.0
	 * 
	 * @return array
	 */
	public function hide_notices()
	{
		if (isset($_GET['wc-braspag-hide-notice']) && isset($_GET['_wc_braspag_notice_nonce'])) {
			if (!wp_verify_nonce($_GET['_wc_braspag_notice_nonce'], 'wc_braspag_hide_notices_nonce')) {
				wp_die(__('Action failed. Please refresh the page and retry.', 'woocommerce-braspag'));
			}

			if (!current_user_can('manage_woocommerce')) {
				wp_die(__('Cheatin&#8217; huh?', 'woocommerce-braspag'));
			}

			$notice = wc_clean($_GET['wc-braspag-hide-notice']);

			switch ($notice) {
				case 'style':
					update_option('wc_braspag_show_style_notice', 'no');
					break;
				case 'phpver':
					update_option('wc_braspag_show_phpver_notice', 'no');
					break;
				case 'wcver':
					update_option('wc_braspag_show_wcver_notice', 'no');
					break;
				case 'wpver':
					update_option('wc_braspag_show_wpver_notice', 'no');
					break;
				case 'curl':
					update_option('wc_braspag_show_curl_notice', 'no');
					break;
				case 'ssl':
					update_option('woocommerce_braspag_show_ssl_notice', 'no');
					break;
				case 'creditcard':
					update_option('woocommerce_braspag_show_creditcard_notice', 'no');
					break;
				case 'creditcard_justclick':
					update_option('woocommerce_braspag_show_creditcard_justclick_notice', 'no');
					break;
				case 'debitcard':
					update_option('woocommerce_braspag_show_debitcard_notice', 'no');
					break;
				case 'boleto':
					update_option('woocommerce_braspag_show_boleto_notice', 'no');
					break;
				case 'pix':
					update_option('woocommerce_braspag_show_pix_notice', 'no');
					break;
			}
		}
	}

	/**
	 * @return string|void
	 */
	public function get_setting_link()
	{
		$use_id_as_section = function_exists('WC') ? version_compare(WC()->version, '2.6', '>=') : false;

		$section_slug = $use_id_as_section ? 'braspag' : strtolower('WC_Gateway_Braspag');

		return admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $section_slug);
	}

	public function braspag_updated()
	{
		$previous_version = get_option('woocommerce_braspag_version');

		if (empty($previous_version) || version_compare($previous_version, '1.0.0', 'ge')) {
			update_option('wc_braspag_show_style_notice', 'no');
			update_option('wc_braspag_show_sca_notice', 'no');
		}
	}
}

new WC_Braspag_Admin_Notices();