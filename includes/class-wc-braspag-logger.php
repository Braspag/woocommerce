<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/**
 * Log all things!
 *
 * @since 1.0.0
 * @version 1.0.0
 */
class WC_Braspag_Logger
{
	public static $logger;
	const WC_LOG_FILENAME = 'woocommerce-braspag';

	/**
	 * Verifica se logging está habilitado nas configurações do plugin.
	 *
	 * @return bool
	 */
	public static function is_logging_enabled()
	{
		$settings = get_option('woocommerce_braspag_settings');

		// Mesmo campo checado por log() logo abaixo — o setting real gravado pelo
		// admin é 'debug' ("Log debug messages"); 'logging' nunca existe no
		// array salvo, então checar essa chave sempre retornava false e o
		// client-logger.js nunca era enfileirado.
		return empty($settings) === FALSE && isset($settings['debug']) === TRUE && 'yes' === $settings['debug'];
	}

	/**
	 * Loga uma entrada enviada pelo navegador (console/erro JS) via AJAX.
	 *
	 * @param string $message
	 */
	public static function log_client($message)
	{
		self::log("Client Log:\n" . $message);
	}

	/**
	 * @param $message
	 * @param null $start_time
	 * @param null $end_time
	 */
	public static function log($message, $start_time = null, $end_time = null)
	{
		if (!class_exists('WC_Logger')) {
			return;
		}

		if (apply_filters('wc_braspag_logging', true, $message)) {
			if (empty(self::$logger)) {
				if (WC_Braspag_Helper::is_wc_lt('3.0')) {
					self::$logger = new WC_Logger();
				} else {
					self::$logger = wc_get_logger();
				}
			}

			$settings = get_option('woocommerce_braspag_settings');

			if (empty($settings) || isset($settings['logging']) && 'yes' !== $settings['logging']) {
				return;
			}

			$env_line = self::get_env_debug_line($settings);

			if (!is_null($start_time)) {

				$formatted_start_time = date_i18n(get_option('date_format') . ' g:ia', $start_time);
				$end_time = is_null($end_time) ? current_time('timestamp') : $end_time;
				$formatted_end_time = date_i18n(get_option('date_format') . ' g:ia', $end_time);
				$elapsed_time = round(abs($end_time - $start_time) / 60, 2);

				$log_entry = "\n" . '====Braspag Version: ' . WC_BRASPAG_VERSION . '====' . "\n" . $env_line;
				$log_entry .= '====Start Log ' . $formatted_start_time . '====' . "\n" . $message . "\n";
				$log_entry .= '====End Log ' . $formatted_end_time . ' (' . $elapsed_time . ')====' . "\n\n";

			} else {
				$log_entry = "\n" . '====Braspag Version: ' . WC_BRASPAG_VERSION . '====' . "\n" . $env_line;
				$log_entry .= '====Start Log====' . "\n" . $message . "\n" . '====End Log====' . "\n\n";

			}

			if (WC_Braspag_Helper::is_wc_lt('3.0')) {
				self::$logger->add(self::WC_LOG_FILENAME, $log_entry);
			} else {
				self::$logger->debug($log_entry, array('source' => self::WC_LOG_FILENAME));
			}
		}
	}

	/**
     * Register the logger as a source.
     *
     * @return array
     */
    public static function register_logger_source($sources)
    {
        $sources[] = self::WC_LOG_FILENAME;
        return $sources;
    }

	/**
	 * Monta uma linha de diagnóstico de ambiente/config para o topo de cada
	 * entrada de log — versões (PHP/WP/WC) e toggles principais do gateway,
	 * pra não precisar pedir print de tela do admin toda vez que dá suporte.
	 *
	 * @param array|false $settings
	 * @return string
	 */
	public static function get_env_debug_line($settings)
	{
		$settings = is_array($settings) === TRUE ? $settings : array();
		$cc_settings = get_option('woocommerce_braspag_creditcard_settings');
		$dc_settings = get_option('woocommerce_braspag_debitcard_settings');
		$cc_settings = is_array($cc_settings) === TRUE ? $cc_settings : array();
		$dc_settings = is_array($dc_settings) === TRUE ? $dc_settings : array();

		$yn = function ($value) {
			return 'yes' === $value ? 'yes' : 'no';
		};

		$auth3ds20_enabled = $yn($cc_settings['auth3ds20_mpi_is_active'] ?? '') === 'yes'
			|| $yn($dc_settings['auth3ds20_mpi_is_active'] ?? '') === 'yes'
			? 'yes' : 'no';

		global $wp_version;

		$wc_version = defined('WC_VERSION') === TRUE ? WC_VERSION : 'desconhecida';
		$line = 'PHP: ' . PHP_VERSION . ' | WordPress: ' . $wp_version . ' | WooCommerce: ' . $wc_version . ' | Checkout: ' . self::get_checkout_type() . "\n";
		$line .= 'SOP: ' . $yn($settings['silentpost_enabled'] ?? '')
			. ' | Antifraude: ' . $yn($settings['antifraud_enabled'] ?? '')
			. ' | VerifyCard: ' . $yn($settings['verifycard_enabled'] ?? '')
			. ' | 3DS: ' . $auth3ds20_enabled
			. ' | BinQuery: ' . $yn($settings['silentpost_binquery_enable'] ?? '')
			. ' | Card+CardToken: ' . $yn($settings['silentpost_token_type'] ?? '')
			. "\n";

		return $line;
	}

	/**
	 * Detecta se a página de checkout atual usa o bloco "Checkout" (Gutenberg,
	 * renderização React/JS) ou o shortcode/formulário clássico — os dois
	 * fluxos divergem bastante (hooks PHP clássicos não disparam do mesmo
	 * jeito no Blocks), então saber qual está em uso evita diagnóstico às
	 * cegas quando o suporte não sabe informar.
	 *
	 * @return string
	 */
	public static function get_checkout_type()
	{
		if (function_exists('has_block') === FALSE || function_exists('is_checkout') === FALSE) {
			return 'desconhecido';
		}

		if (is_checkout() === FALSE) {
			return 'n/a';
		}

		$checkout_page_id = function_exists('wc_get_page_id') === TRUE ? wc_get_page_id('checkout') : 0;

		if ($checkout_page_id > 0 && has_block('woocommerce/checkout', $checkout_page_id) === TRUE) {
			return 'blocks';
		}

		return 'classic';
	}

	/**
     * Ensure logs are eligible for remote logging.
     *
     * @param bool $should_log
     * @param array $context
     * @return bool
     */
    public static function allow_remote_logging($should_log, $context)
    {
        if (isset($context['source']) && $context['source'] === self::WC_LOG_FILENAME) {
            return true; // Enable remote logging for this source
        }
        return $should_log;
    }
}

// Hooks for WooCommerce Remote Logging
add_filter('woocommerce_logger_sources', array('WC_Braspag_Logger', 'register_logger_source'));
add_filter('woocommerce_remote_logger_should_log', array('WC_Braspag_Logger', 'allow_remote_logging'), 10, 2);