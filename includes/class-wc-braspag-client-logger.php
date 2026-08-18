<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/**
 * Recebe logs do console/navegador (JS) via AJAX e grava no log do plugin.
 */
class WC_Braspag_Client_Logger
{
	const ACTION = 'braspag_client_log';
	const NONCE_ACTION = 'braspag_client_log_nonce';
	const MAX_ENTRIES = 25;

	public static function init()
	{
		add_action('wp_ajax_' . self::ACTION, array(__CLASS__, 'handle_request'));
		add_action('wp_ajax_nopriv_' . self::ACTION, array(__CLASS__, 'handle_request'));
	}

	public static function handle_request()
	{
		check_ajax_referer(self::NONCE_ACTION, 'nonce');

		$raw_entries = isset($_POST['entries']) ? wp_unslash($_POST['entries']) : '';
		$entries = json_decode($raw_entries, true);

		if (!is_array($entries) || empty($entries)) {
			wp_send_json_error('empty_entries', 400);
		}

		$entries = array_slice($entries, 0, self::MAX_ENTRIES);
		$lines = array();

		foreach ($entries as $entry) {
			$level = isset($entry['level']) ? sanitize_text_field($entry['level']) : 'log';
			$text = isset($entry['message']) ? sanitize_textarea_field($entry['message']) : '';

			if ('' === $text) {
				continue;
			}

			$lines[] = '[' . strtoupper($level) . '] ' . $text;
		}

		if (empty($lines)) {
			wp_send_json_error('empty_entries', 400);
		}

		WC_Braspag_Logger::log_client(implode("\n", $lines));

		wp_send_json_success();
	}
}

WC_Braspag_Client_Logger::init();
