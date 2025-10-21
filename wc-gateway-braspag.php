<?php
/**
 * Braspag for WooCommerce Oficial
 * 
 * @package           Braspag
 * @author            Braspag
 * @copyright         2024 Braspag
 * @license           GPL-3.0
 * 
 * @wordpress-plugin
 * Plugin Name: Braspag for WooCommerce Oficial
 * Plugin URI: https://wordpress.org/plugins/woocommerce-braspag/
 * Description: Take payments on your store using Braspag.
 * Author: Braspag
 * Author URI: https://braspag.com.br/
 *
 * Version: 2.3.5.35
 * Requires at least: 5.3.2
 * Tested up to: 6.8.2
 * Requires PHP: 7.4
 *
 * WC requires at least: 4.0.0
 * WC tested up to: 10.0.4
 * License: GPL-3.0
 * License URI: https://opensource.org/license/gpl-3/
 * Text Domain: woocommerce-braspag
 * Domain Path: /languages
 * Requires Plugins: woocommerce, woocommerce-extra-checkout-fields-for-brazil
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Load plugin textdomain for translations
 */
add_action('init', function () {
	load_plugin_textdomain(
		'woocommerce-braspag',
		false,
		dirname(plugin_basename(__FILE__)) . '/languages'
	);
});

/**
 * Unschedule Token Cleanup on deactivation
 *
 * @return void
 */
function wc_braspag_unschedule_token_cleanup()
{
	if (function_exists('as_get_scheduled_actions')) {
		$scheduled_actions = as_get_scheduled_actions([
			'hook' => 'woocommerce_payment_tokens_cleanup',
			'status' => ActionScheduler_Store::STATUS_PENDING,
		]);

		foreach ($scheduled_actions as $action) {
			if ($action instanceof ActionScheduler_Action) {
				as_unschedule_action('woocommerce_payment_tokens_cleanup', $action->get_args());
			}
		}
	}
}
register_deactivation_hook(__FILE__, 'wc_braspag_unschedule_token_cleanup');

/**
 * Dependency notices
 */
function wc_braspag_missing_wc_notice()
{
	echo '<div class="notice notice-error"><p><strong>' .
		sprintf(
			esc_html__('Braspag requires WooCommerce to be installed and active. You can download %s here.', 'woocommerce-braspag'),
			'<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
		) .
		'</strong></p></div>';
}

/**
 * Extra Checkout Fields for Brazil fallback notice.
 *
 * @return string
 */
function wc_braspag_missing_extra_checkout_fields_notice()
{
	return '<div class="notice notice-error"><p><strong>' .
		sprintf(
			esc_html__('Braspag requires the Extra Checkout Fields for Brazil plugin to be installed and active. You can download %s here.', 'woocommerce-braspag'),
			'<a href="https://wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/" target="_blank">Extra Checkout Fields for Brazil</a>'
		) .
		'</strong></p></div>';
}

/**
 * Initialize Braspag Gateway
 */
add_action('plugins_loaded', 'wc_braspag_init');
function wc_braspag_init()
{
	if (!class_exists('WooCommerce')) {
		add_action('admin_notices', 'wc_braspag_missing_wc_notice');
		return;
	}

	// Verifica Extra Checkout Fields for Brazil.
	if (!class_exists('Extra_Checkout_Fields_For_Brazil')) {
		add_action('admin_notices', 'wc_braspag_missing_extra_checkout_fields_notice');
		return;
	}

	/**
	 * Required minimums and constants
	 */

	global $wp_version;
	$bp_version = '2.3.5.35';
	$min_php_ver = '5.6.0';
	$min_wc_ver = '4.0.0';
	$min_wp_ver = '5.3.2';
	$current_wc_ver = WC()->version ?? get_option('woocommerce_version');

	if (
		version_compare(PHP_VERSION, $min_php_ver, '<') ||
		version_compare($wp_version, $min_wp_ver, '<') ||
		version_compare($current_wc_ver, $min_wc_ver, '<')
	) {
		add_action('admin_notices', function () use ($min_php_ver, $min_wc_ver, $min_wp_ver) {
			echo '<div class="notice notice-error"><p>' .
				sprintf(
					esc_html__('Braspag requires PHP %s, WordPress %s and WooCommerce %s or higher.', 'woocommerce-braspag'),
					esc_html($min_php_ver),
					esc_html($min_wp_ver),
					esc_html($min_wc_ver)
				) .
				'</p></div>';
		});
		return;
	}

	/**
	 * WooCommerce fallback notice.
	 * Declare HPOS and Checkout Blocks compatibility
	 * @since 2.3.5
	 * @return void
	 */

	add_action('before_woocommerce_init', function () {
		if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
		}
	});

	if (!defined('WC_VERSION')) {
		define('WC_VERSION', WC()->version ?? get_option('woocommerce_version'));
	}

	define('WC_BRASPAG_VERSION', $bp_version);
	define('WC_BRASPAG_WP_VERSION', $wp_version);
	define('WC_BRASPAG_MIN_PHP_VER', $min_php_ver);
	define('WC_BRASPAG_MIN_WC_VER', $min_wc_ver);
	define('WC_BRASPAG_MIN_WP_VER', $min_wp_ver);
	define('WC_BRASPAG_MAIN_FILE', __FILE__);
	define('WC_BRASPAG_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
	define('WC_BRASPAG_PLUGIN_PATH', untrailingslashit(plugin_dir_path(__FILE__)));
  
	add_action(
		'admin_notices',
		function () {
			// Verifica se o usuário tem permissões para instalar plugins
			$currentUserCanInstallPlugins = current_user_can('install_plugins');

			$minilogo = sprintf('%s%s', plugin_dir_url(__FILE__), 'assets/images/minilogo.png');
			$translations = [
				'activate_plugin' => __('Activate %s', 'woocommerce-braspag'),
				'install_plugin' => __('Install %s', 'woocommerce-braspag'),
				'see_plugin' => __('See %s', 'woocommerce-braspag'),
				'miss_plugin' => __('The Braspag module needs an active version of %s in order to work!', 'woocommerce-braspag'),
			];

			$requiredPlugins = [
				'WooCommerce' => [
					'slug' => 'woocommerce',
					'file' => 'woocommerce/woocommerce.php',
					'url' => 'https://wordpress.org/plugins/woocommerce/',
				],
				'Extra Checkout Fields for Brazil' => [
					'slug' => 'woocommerce-extra-checkout-fields-for-brazil',
					'file' => 'woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php',
					'url' => 'https://wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/',
				],
			];

			$allPlugins = function_exists('get_plugins') ? get_plugins() : [];

			foreach ($requiredPlugins as $pluginName => $pluginData) {
				$isInstalled = !empty($allPlugins[$pluginData['file']]); // Verifica se está instalado
				$isActive = is_plugin_active($pluginData['file']); // Verifica se está ativo
	
				// Define a ação e o link com base no estado do plugin
				if (!$isInstalled && $currentUserCanInstallPlugins) {
					// Plugin não está instalado
					$action = 'install';
					$link = wp_nonce_url(
						self_admin_url("update.php?action=install-plugin&plugin={$pluginData['slug']}"),
						"install-plugin_{$pluginData['slug']}"
					);
				} elseif (!$isActive && $isInstalled && $currentUserCanInstallPlugins) {
					// Plugin está instalado, mas não está ativo
					$action = 'activate';
					$link = wp_nonce_url(
						self_admin_url("plugins.php?action=activate&plugin={$pluginData['file']}&plugin_status=all"),
						"activate-plugin_{$pluginData['file']}"
					);
				} else {
					// Plugin já está ativo ou não pode ser instalado
					continue;
				}

				// Exibe o aviso
				echo sprintf(
					'<div class="notice notice-error is-dismissible"><p><img src="%s" style="vertical-align: middle; margin-right: 5px;">%s <a href="%s">%s</a></p></div>',
					esc_url($minilogo),
					sprintf(esc_html($translations['miss_plugin']), esc_html($pluginName)),
					esc_url($link),
					esc_html($translations["{$action}_plugin"])
				);
			}
		}
	);

	final class WC_Braspag
	{
		/**
		 * @var Singleton The reference the *Singleton* instance of this class
		 */
		private static ?WC_Braspag $instance = null;

		/**
		 * Returns the *Singleton* instance of this class.
		 *
		 * @return Singleton The *Singleton* instance.
		 */
		public static function get_instance()
		{
			if (self::$instance === null) {
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
		public function init(): void
		{
			if (is_admin()) {
				require_once WC_BRASPAG_PLUGIN_PATH . '/includes/admin/class-wc-braspag-privacy.php';
				require_once WC_BRASPAG_PLUGIN_PATH . '/includes/admin/class-wc-braspag-customer-seller-attributes.php';
				require_once WC_BRASPAG_PLUGIN_PATH . '/includes/admin/class-wc-braspag-admin-notices.php';
			}

			// Load Core includes
			require_once WC_BRASPAG_PLUGIN_PATH . '/includes/class-wc-braspag-exception.php';
			require_once WC_BRASPAG_PLUGIN_PATH . '/includes/class-wc-braspag-logger.php';
			require_once WC_BRASPAG_PLUGIN_PATH . '/includes/class-wc-braspag-helper.php';
			include_once WC_BRASPAG_PLUGIN_PATH . '/includes/class-wc-braspag-payment-tokens.php';
			include_once WC_BRASPAG_PLUGIN_PATH . '/includes/class-wc-braspag-pagador-api.php';
			include_once WC_BRASPAG_PLUGIN_PATH . '/includes/class-wc-braspag-risk-api.php';
			include_once WC_BRASPAG_PLUGIN_PATH . '/includes/class-wc-braspag-oauth-api.php';
			include_once WC_BRASPAG_PLUGIN_PATH . '/includes/class-wc-braspag-mpi-api.php';
			include_once WC_BRASPAG_PLUGIN_PATH . '/includes/class-wc-braspag-pagador-api-query.php';
			require_once WC_BRASPAG_PLUGIN_PATH . '/includes/abstracts/abstract-wc-braspag-payment-gateway.php';
			require_once WC_BRASPAG_PLUGIN_PATH . '/includes/class-wc-braspag-webhook-handler.php';
			require_once WC_BRASPAG_PLUGIN_PATH . '/includes/class-wc-gateway-braspag.php';
			require_once WC_BRASPAG_PLUGIN_PATH . '/includes/payment-methods/class-wc-gateway-braspag-creditcard.php';
			require_once WC_BRASPAG_PLUGIN_PATH . '/includes/payment-methods/class-wc-gateway-braspag-creditcard-justclick.php';
			require_once WC_BRASPAG_PLUGIN_PATH . '/includes/payment-methods/class-wc-gateway-braspag-debitcard.php';
			require_once WC_BRASPAG_PLUGIN_PATH . '/includes/payment-methods/class-wc-gateway-braspag-boleto.php';
			require_once WC_BRASPAG_PLUGIN_PATH . '/includes/payment-methods/class-wc-gateway-braspag-pix.php';
			require_once WC_BRASPAG_PLUGIN_PATH . '/includes/class-wc-braspag-order-handler.php';
			require_once WC_BRASPAG_PLUGIN_PATH . '/includes/class-wc-braspag-customer.php';

			add_filter('woocommerce_payment_gateways', array($this, 'add_gateways'));
			add_filter('plugin_action_links_' . plugin_basename(WC_BRASPAG_MAIN_FILE), array($this, 'plugin_action_links'));

			if (version_compare(WC_VERSION, '3.4', '<')) {
				add_filter('woocommerce_get_sections_checkout', array($this, 'filter_gateway_order_admin'));
			}
		}

		/**
		 * Handles upgrade routines.
		 *
		 * @since 1.0.0
		 * @version 1.0.0
		 */
		public function install()
		{
			if (!is_plugin_active(plugin_basename(WC_BRASPAG_MAIN_FILE))) {
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
}
