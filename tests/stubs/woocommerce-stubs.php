<?php
/**
 * Stubs para testes unitários sem WordPress/WooCommerce.
 *
 * Carregado pelo bootstrap de testes. Permite instanciar as classes do plugin
 * sem ambiente WordPress real. Use $GLOBALS['_braspag_test_options'] para
 * controlar o retorno de get_option() nos testes.
 */

// ─── WooCommerce Blocks: AbstractPaymentMethodType ────────────────────────────
namespace Automattic\WooCommerce\Blocks\Payments\Integrations {

    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        abstract class AbstractPaymentMethodType
        {
            abstract public function initialize();
            abstract public function is_active();
            abstract public function get_payment_method_script_handles();
            abstract public function get_payment_method_data();

            public function get_name()
            {
                return $this->name ?? '';
            }
        }
    }
}

// ─── Global stubs (WordPress + WooCommerce classes) ───────────────────────────
namespace {

    // ── Constantes do plugin ──────────────────────────────────────────────────
    if (!defined('WC_BRASPAG_VERSION')) {
        define('WC_BRASPAG_VERSION', '0.0.0-test');
    }
    if (!defined('WC_BRASPAG_MAIN_FILE')) {
        define('WC_BRASPAG_MAIN_FILE', dirname(__DIR__, 2) . '/wc-gateway-braspag.php');
    }
    if (!defined('WC_BRASPAG_PLUGIN_PATH')) {
        define('WC_BRASPAG_PLUGIN_PATH', dirname(__DIR__, 2));
    }

    // ── WordPress functions ───────────────────────────────────────────────────

    if (!function_exists('get_option')) {
        /**
         * Retorna valor de $GLOBALS['_braspag_test_options'][$option] ou $default.
         * Nos testes, preencha $GLOBALS['_braspag_test_options'] no setUp().
         */
        function get_option($option, $default = false)
        {
            return $GLOBALS['_braspag_test_options'][$option] ?? $default;
        }
    }

    if (!function_exists('__')) {
        function __($text, $domain = '')
        {
            return $text;
        }
    }

    if (!function_exists('esc_html__')) {
        function esc_html__($text, $domain = '')
        {
            return $text;
        }
    }

    if (!function_exists('esc_attr')) {
        function esc_attr($text)
        {
            return htmlspecialchars((string) $text, ENT_QUOTES);
        }
    }

    if (!function_exists('wp_kses_post')) {
        function wp_kses_post($text)
        {
            return $text;
        }
    }

    if (!function_exists('wpautop')) {
        function wpautop($text)
        {
            return $text;
        }
    }

    if (!function_exists('add_action')) {
        function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
        {
        }
    }

    if (!function_exists('add_filter')) {
        function add_filter($hook, $callback, $priority = 10, $accepted_args = 1)
        {
        }
    }

    if (!function_exists('apply_filters')) {
        function apply_filters($tag, $value, ...$args)
        {
            return $value;
        }
    }

    if (!function_exists('do_action')) {
        function do_action($tag, ...$args)
        {
        }
    }

    if (!function_exists('plugins_url')) {
        function plugins_url($path = '', $file = '')
        {
            return 'http://test.local/wp-content/plugins/' . ltrim($path, '/');
        }
    }

    if (!function_exists('is_checkout')) {
        function is_checkout()
        {
            return $GLOBALS['_braspag_test_is_checkout'] ?? true;
        }
    }

    if (!function_exists('is_add_payment_method_page')) {
        function is_add_payment_method_page()
        {
            return false;
        }
    }

    if (!function_exists('wp_register_script')) {
        function wp_register_script()
        {
        }
    }

    if (!function_exists('wp_enqueue_script')) {
        function wp_enqueue_script()
        {
        }
    }

    if (!function_exists('wp_enqueue_style')) {
        function wp_enqueue_style()
        {
        }
    }

    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field($str)
        {
            return strip_tags((string) $str);
        }
    }

    if (!function_exists('wp_json_encode')) {
        function wp_json_encode($data, $options = 0, $depth = 512)
        {
            return json_encode($data, $options, $depth);
        }
    }

    if (!function_exists('wp_generate_uuid4')) {
        function wp_generate_uuid4()
        {
            $data = random_bytes(16);
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        }
    }

    if (!function_exists('wc_add_notice')) {
        function wc_add_notice($message, $type = 'success')
        {
        }
    }

    if (!function_exists('status_header')) {
        function status_header($code)
        {
        }
    }

    if (!function_exists('wc_get_is_pending_statuses')) {
        function wc_get_is_pending_statuses()
        {
            return [];
        }
    }

    if (!function_exists('wc_get_order_statuses')) {
        function wc_get_order_statuses()
        {
            return [];
        }
    }

    if (!function_exists('wc_get_price_decimals')) {
        function wc_get_price_decimals()
        {
            return 2;
        }
    }

    if (!function_exists('wc_reduce_stock_levels')) {
        function wc_reduce_stock_levels($order_id)
        {
        }
    }

    if (!function_exists('wp_script_is')) {
        function wp_script_is(string $handle, string $list = 'enqueued'): bool
        {
            return false;
        }
    }

    if (!function_exists('wp_localize_script')) {
        function wp_localize_script(string $handle, string $object_name, array $l10n): bool
        {
            return true;
        }
    }

    if (!function_exists('wc_get_order')) {
        function wc_get_order($order_id)
        {
            return $GLOBALS['_braspag_test_orders'][$order_id] ?? false;
        }
    }

    if (!function_exists('WC')) {
        function WC()
        {
            static $instance = null;
            if ($instance === null) {
                $instance = new class {
                    public $cart;
                    public function __construct()
                    {
                        $this->cart = new class {
                            public function get_cart_hash(): string { return ''; }
                        };
                    }
                };
            }
            return $instance;
        }
    }

    // ── WooCommerce exception ─────────────────────────────────────────────────

    if (!class_exists('WC_Braspag_Exception')) {
        class WC_Braspag_Exception extends \RuntimeException
        {
            private string $localized;

            public function __construct(string $message = '', string $localized = '')
            {
                parent::__construct($message);
                $this->localized = $localized !== '' ? $localized : $message;
            }

            public function getLocalizedMessage(): string
            {
                return $this->localized;
            }
        }
    }

    // ── Logger stub ───────────────────────────────────────────────────────────

    if (!class_exists('WC_Braspag_Logger')) {
        class WC_Braspag_Logger
        {
            public static array $logs = [];

            public static function log(string $message): void
            {
                self::$logs[] = $message;
            }

            public static function reset(): void
            {
                self::$logs = [];
            }
        }
    }

    // ── WC_Payment_Gateway stub (WooCommerce base) ────────────────────────────

    if (!class_exists('WC_Payment_Gateway')) {
        abstract class WC_Payment_Gateway
        {
            public $id = '';
            public $title = '';
            public $description = '';
            public $enabled = 'yes';
            public $has_fields = false;
            public $supports = [];
            public $settings = [];
            public $form_fields = [];

            public function init_settings()
            {
                $this->settings = [];
            }

            public function get_option($key, $default = null)
            {
                return $this->settings[$key] ?? $default;
            }

            public function init_form_fields() {}
            public function admin_options() {}
            public function validate_fields() { return true; }
            public function payment_fields() {}
            public function process_payment($order_id) { return []; }
            public function get_title() { return $this->title; }
            public function is_available() { return $this->enabled === 'yes'; }
            public function get_icon() { return ''; }
            public function needs_setup() { return false; }
            public function get_return_url($order = null) { return ''; }
            public function add_payment_method() { return []; }
        }
    }

    // ── WC_Braspag_Pagador_API_Query stub ─────────────────────────────────────

    if (!class_exists('WC_Braspag_Pagador_API_Query')) {
        class WC_Braspag_Pagador_API_Query
        {
            public static function requestByPaymentId(string $payment_id): object
            {
                return $GLOBALS['_braspag_test_pagador_response'] ?? (object) [
                    'body' => (object) [
                        'Payment' => (object) [
                            'Status'    => '2',
                            'PaymentId' => $payment_id,
                        ],
                    ],
                ];
            }
        }
    }

    // ── WC_Order stub ─────────────────────────────────────────────────────────

    if (!class_exists('WC_Order')) {
        class WC_Order
        {
            public function get_total(): float { return 0.0; }
            public function get_id(): int { return 0; }
            public function get_date_created(): WC_DateTime { return new WC_DateTime(); }
            public function get_meta(string $key, bool $single = true) { return ''; }
            public function update_status(string $status, string $note = ''): void {}
            public function add_order_note(string $note): void {}
        }
    }

    // ── WC_DateTime stub ──────────────────────────────────────────────────────

    if (!class_exists('WC_DateTime')) {
        class WC_DateTime extends DateTime {}
    }

    // ── WP_Error stub ─────────────────────────────────────────────────────────

    if (!class_exists('WP_Error')) {
        class WP_Error
        {
            public string $code;
            public string $message;

            public function __construct(string $code = '', string $message = '')
            {
                $this->code    = $code;
                $this->message = $message;
            }
        }
    }

    // ── HTTP functions ────────────────────────────────────────────────────────

    if (!function_exists('is_wp_error')) {
        function is_wp_error($thing): bool
        {
            return $thing instanceof WP_Error;
        }
    }

    if (!function_exists('wp_safe_remote_post')) {
        function wp_safe_remote_post(string $url, array $args = [])
        {
            if (isset($GLOBALS['_braspag_test_http_handler'])) {
                return ($GLOBALS['_braspag_test_http_handler'])($url, $args);
            }
            return new WP_Error('http_not_mocked', 'HTTP not mocked in tests');
        }
    }

    if (!function_exists('wp_safe_remote_request')) {
        function wp_safe_remote_request(string $url, array $args = [])
        {
            if (isset($GLOBALS['_braspag_test_http_handler'])) {
                return ($GLOBALS['_braspag_test_http_handler'])($url, $args);
            }
            return new WP_Error('http_not_mocked', 'HTTP not mocked in tests');
        }
    }

    if (!function_exists('remove_all_filters')) {
        function remove_all_filters(string $hook): void
        {
            unset($GLOBALS['_braspag_test_http_handler']);
        }
    }
}
