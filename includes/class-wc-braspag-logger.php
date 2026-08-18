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

			if (!is_null($start_time)) {

				$formatted_start_time = date_i18n(get_option('date_format') . ' g:ia', $start_time);
				$end_time = is_null($end_time) ? current_time('timestamp') : $end_time;
				$formatted_end_time = date_i18n(get_option('date_format') . ' g:ia', $end_time);
				$elapsed_time = round(abs($end_time - $start_time) / 60, 2);

				$log_entry = "\n" . '====Braspag Version: ' . WC_BRASPAG_VERSION . '====' . "\n";
				$log_entry .= '====Start Log ' . $formatted_start_time . '====' . "\n" . $message . "\n";
				$log_entry .= '====End Log ' . $formatted_end_time . ' (' . $elapsed_time . ')====' . "\n\n";

			} else {
				$log_entry = "\n" . '====Braspag Version: ' . WC_BRASPAG_VERSION . '====' . "\n";
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