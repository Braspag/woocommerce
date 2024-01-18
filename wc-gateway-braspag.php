<?php
/**
 * Plugin Name: Braspag for WooCommerce Oficial
 * Plugin URI: https://wordpress.org/plugins/woocommerce-braspag/
 * Description: Take payments on your store using Braspag.
 * Author: Braspag
 * Author URI: https://braspag.com.br/
 * 
 * Version: 2.3.1
 * WP requires at least: 5.3.2
 * WP tested up to: 6.2.2
 * 
 * WC requires at least: 4.0.0
 * WC tested up to: 7.9.0
 * Text Domain: woocommerce-braspag
 * Domain Path: /languages
 *
 */

if (!defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.Files.FileName

/**
 * WooCommerce fallback notice.
 *
 * @return string
 */
function wc_braspag_missing_wc_notice()
{
	/* translators: 1. URL link. */
	echo '<div class="error"><p><strong>' . sprintf(esc_html__('Braspag requires WooCommerce to be installed and active. You can download %s here.', 'woocommerce-braspag'), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>') . '</strong></p></div>';
}

add_action('plugins_loaded', 'woocommerce_gateway_braspag_init');

function woocommerce_gateway_braspag_init()
{
	load_plugin_textdomain('woocommerce-braspag', false, plugin_basename(dirname(__FILE__)) . '/languages');

	if (!class_exists('WooCommerce')) {
		add_action('admin_notices', 'wc_braspag_missing_wc_notice');
		return;
	}

	if (!class_exists('WC_Braspag')):
		/**
		 * Required minimums and constants
		 */

		global $wp_version;

		define('WC_BRASPAG_VERSION', '2.3.1');
		define('WC_BRASPAG_WP_VERSION', $wp_version);
		define('WC_BRASPAG_MIN_PHP_VER', '5.6.0');
		define('WC_BRASPAG_MIN_WC_VER', '4.0.0');
		define('WC_BRASPAG_MIN_WP_VER', '5.3.2');
		define('WC_BRASPAG_MAIN_FILE', __FILE__);
		define('WC_BRASPAG_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
		define('WC_BRASPAG_PLUGIN_PATH', untrailingslashit(plugin_dir_path(__FILE__)));

		class WC_Braspag
		{

			/**
			 * @var Singleton The reference the *Singleton* instance of this class
			 */
			private static $instance;

			/**
			 * Returns the *Singleton* instance of this class.
			 *
			 * @return Singleton The *Singleton* instance.
			 */
			public static function get_instance()
			{
				if (null === self::$instance) {
					self::$instance = new self();
				}
				return self::$instance;
			}

			/**
			 * Private clone method to prevent cloning of the instance of the
			 * *Singleton* instance.
			 *
			 * @return void
			 */
			private function __clone()
			{
			}

			/**
			 * Private unserialize method to prevent unserializing of the *Singleton*
			 * instance.
			 *
			 * @return void
			 */
			private function __wakeup()
			{
			}

			/**
			 * Protected constructor to prevent creating a new instance of the
			 * *Singleton* via the `new` operator from outside of this class.
			 */
			private function __construct()
			{
				add_action('admin_init', array($this, 'install'));
				$this->init();
			}

			/**
			 * Inicializa o plugin
			 * 
			 * Init the plugin after plugins_loaded so environment variables are set.
			 *
			 * @since 1.0.0
			 * @version 0.0.3
			 */
			public function init()
			{
				if (is_admin()) {
					require_once dirname(__FILE__) . '/includes/admin/class-wc-braspag-privacy.php';
					require_once dirname(__FILE__) . '/includes/admin/class-wc-braspag-customer-seller-attributes.php';
				}

				require_once dirname(__FILE__) . '/includes/class-wc-braspag-exception.php';
				require_once dirname(__FILE__) . '/includes/class-wc-braspag-logger.php';
				require_once dirname(__FILE__) . '/includes/class-wc-braspag-helper.php';
				include_once dirname(__FILE__) . '/includes/class-wc-braspag-payment-tokens.php';
				include_once dirname(__FILE__) . '/includes/class-wc-braspag-pagador-api.php';
				include_once dirname(__FILE__) . '/includes/class-wc-braspag-risk-api.php';
				include_once dirname(__FILE__) . '/includes/class-wc-braspag-oauth-api.php';
				include_once dirname(__FILE__) . '/includes/class-wc-braspag-mpi-api.php';
				include_once dirname(__FILE__) . '/includes/class-wc-braspag-pagador-api-query.php';
				require_once dirname(__FILE__) . '/includes/abstracts/abstract-wc-braspag-payment-gateway.php';
				require_once dirname(__FILE__) . '/includes/class-wc-braspag-webhook-handler.php';
				require_once dirname(__FILE__) . '/includes/class-wc-gateway-braspag.php';
				require_once dirname(__FILE__) . '/includes/payment-methods/class-wc-gateway-braspag-creditcard.php';
				require_once dirname(__FILE__) . '/includes/payment-methods/class-wc-gateway-braspag-creditcard-justclick.php';
				require_once dirname(__FILE__) . '/includes/payment-methods/class-wc-gateway-braspag-debitcard.php';
				require_once dirname(__FILE__) . '/includes/payment-methods/class-wc-gateway-braspag-boleto.php';
				require_once dirname(__FILE__) . '/includes/payment-methods/class-wc-gateway-braspag-pix.php';
				require_once dirname(__FILE__) . '/includes/class-wc-braspag-order-handler.php';
				require_once dirname(__FILE__) . '/includes/class-wc-braspag-customer.php';

				if (is_admin()) {
					require_once dirname(__FILE__) . '/includes/admin/class-wc-braspag-admin-notices.php';
				}

				add_filter('woocommerce_payment_gateways', array($this, 'add_gateways'));
				add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));

				if (version_compare(WC_VERSION, '3.4', '<')) {
					add_filter('woocommerce_get_sections_checkout', array($this, 'filter_gateway_order_admin'));
				}
			}

			/**
			 * Updates the plugin version in db
			 *
			 * @since 1.0.0
			 * @version 1.0.0
			 */
			public function update_plugin_version()
			{
				delete_option('woocommerce_braspag_version');
				update_option('woocommerce_braspag_version', WC_BRASPAG_VERSION);
			}

			/**
			 * Handles upgrade routines.
			 *
			 * @since 1.0.0
			 * @version 1.0.0
			 */
			public function install()
			{
				if (!is_plugin_active(plugin_basename(__FILE__))) {
					return;
				}

				if (!defined('IFRAME_REQUEST') && (WC_BRASPAG_VERSION !== get_option('woocommerce_braspag_version'))) {
					do_action('wc_braspag_updated');

					if (!defined('WC_BRASPAG_INSTALLING')) {
						define('WC_BRASPAG_INSTALLING', true);
					}

					$this->update_plugin_version();
				}
			}

			/**
			 * Adds plugin action links.
			 *
			 * @since 1.0.0
			 * @version 1.0.0
			 */
			public function plugin_action_links($links)
			{
				$plugin_links = array(
					'<a href="admin.php?page=wc-settings&tab=checkout&section=braspag">' . esc_html__('Settings', 'woocommerce-braspag') . '</a>',
				);
				return array_merge($plugin_links, $links);
			}

			/**
			 * Add the gateways to WooCommerce.
			 *
			 * @since 1.0.0
			 * @version 1.0.0
			 */
			public function add_gateways($methods)
			{

				$methods[] = 'WC_Gateway_Braspag';
				$methods[] = 'WC_Gateway_Braspag_CreditCard';
				$methods[] = 'WC_Gateway_Braspag_CreditCard_JustClick';
				$methods[] = 'WC_Gateway_Braspag_DebitCard';
				$methods[] = 'WC_Gateway_Braspag_Boleto';
				$methods[] = 'WC_Gateway_Braspag_Pix';

				return $methods;
			}

			/**
			 * Modifies the order of the gateways displayed in admin.
			 *
			 * @since 1.0.0
			 * @version 0.0.3
			 */
			public function filter_gateway_order_admin($sections)
			{
				unset($sections['braspag']);
				unset($sections['braspag_creditcard']);
				unset($sections['braspag_creditcard_justclick']);
				unset($sections['braspag_debitcard']);
				unset($sections['braspag_boleto']);
				unset($sections['braspag_pix']);

				$sections['braspag'] = 'Braspag';
				$sections['braspag_creditcard'] = __('Braspag CreditCard', 'woocommerce-braspag');
				$sections['braspag_creditcard_justclick'] = __('Braspag CreditCard JustClick', 'woocommerce-braspag');
				$sections['braspag_debitcard'] = __('Braspag DebitCard', 'woocommerce-braspag');
				$sections['braspag_boleto'] = __('Braspag Boleto', 'woocommerce-braspag');
				$sections['braspag_pix'] = __('Braspag Pix', 'woocommerce-braspag');

				return $sections;
			}
		}

		WC_Braspag::get_instance();
	endif;
}