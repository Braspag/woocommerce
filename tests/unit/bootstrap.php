<?php
/**
 * Bootstrap para a suíte de testes unitários "puros" (sem WordPress/DB
 * completos — ver tests/integration para os testes que precisam da suíte
 * oficial do WP, instalada em .wp-tests-lib/.wp-tests-core).
 *
 * Define apenas os stubs mínimos de funções/classes do WordPress usados
 * pelas classes exercitadas aqui (WC_Braspag_Mpi_V3_Client e
 * WC_Braspag_Logger), permitindo mockar wp_safe_remote_request() e
 * set_transient()/get_transient() com controle total em cada teste.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (!defined('WC_BRASPAG_VERSION')) {
    define('WC_BRASPAG_VERSION', 'test');
}

global $wp_version;
$wp_version = 'test';

/**
 * Registro em memória usado pelos testes para controlar a resposta HTTP
 * simulada de wp_safe_remote_request() e inspecionar o request enviado.
 */
class WC_Braspag_Test_Http_Mock
{
    /** @var callable|null */
    public static $handler = null;

    /** @var array<int, array{url:string,args:array}> */
    public static $requests = array();

    public static function reset()
    {
        self::$handler = null;
        self::$requests = array();
    }

    public static function set_handler($handler)
    {
        self::$handler = $handler;
    }

    public static function handle($url, $args)
    {
        self::$requests[] = array('url' => $url, 'args' => $args);

        if (is_callable(self::$handler)) {
            return call_user_func(self::$handler, $url, $args);
        }

        return new WP_Error('http_request_failed', 'No handler configured for WC_Braspag_Test_Http_Mock');
    }
}

/**
 * In-memory transient store — substitui set_transient()/get_transient() com
 * suporte real a expiração (o que é o próprio objeto sob teste: 3DS-12).
 */
class WC_Braspag_Test_Transients
{
    /** @var array<string, array{value:mixed, expires_at:int}> */
    public static $store = array();

    /** @var int|null Quando definido, sobrepõe o "agora" usado para expiração. */
    public static $now_override = null;

    public static function reset()
    {
        self::$store = array();
        self::$now_override = null;
    }

    public static function now()
    {
        return null !== self::$now_override ? self::$now_override : time();
    }

    public static function set($key, $value, $ttl)
    {
        self::$store[$key] = array(
            'value' => $value,
            'expires_at' => self::now() + $ttl,
        );
        return true;
    }

    public static function get($key)
    {
        if (!isset(self::$store[$key])) {
            return false;
        }

        if (self::$store[$key]['expires_at'] <= self::now()) {
            unset(self::$store[$key]);
            return false;
        }

        return self::$store[$key]['value'];
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public $code;
        public $message;
        public $data;

        public function __construct($code = '', $message = '', $data = '')
        {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_message()
        {
            return $this->message;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing)
    {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('wp_safe_remote_request')) {
    function wp_safe_remote_request($url, $args = array())
    {
        return WC_Braspag_Test_Http_Mock::handle($url, $args);
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response)
    {
        if (is_wp_error($response)) {
            return '';
        }
        return isset($response['response']['code']) ? $response['response']['code'] : '';
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response)
    {
        if (is_wp_error($response)) {
            return '';
        }
        return isset($response['body']) ? $response['body'] : '';
    }
}

if (!function_exists('wp_remote_retrieve_headers')) {
    function wp_remote_retrieve_headers($response)
    {
        return isset($response['headers']) ? $response['headers'] : array();
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data)
    {
        return json_encode($data);
    }
}

if (!function_exists('set_transient')) {
    function set_transient($key, $value, $ttl = 0)
    {
        return WC_Braspag_Test_Transients::set($key, $value, $ttl);
    }
}

if (!function_exists('get_transient')) {
    function get_transient($key)
    {
        return WC_Braspag_Test_Transients::get($key);
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default')
    {
        return $text;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value)
    {
        return $value;
    }
}

if (!function_exists('add_filter')) {
    function add_filter()
    {
        return true;
    }
}

if (!function_exists('get_option')) {
    function get_option($name, $default = false)
    {
        if ('woocommerce_braspag_settings' === $name) {
            return array('debug' => 'yes', 'logging' => 'yes');
        }
        return $default;
    }
}

/**
 * Fake mínimo de WC_Logger — captura as entradas em memória para os testes
 * de "nenhum segredo aparece no log" inspecionarem.
 */
class WC_Logger_Fake
{
    /** @var string[] */
    public static $entries = array();

    public static function reset()
    {
        self::$entries = array();
    }

    public function debug($message, $context = array())
    {
        self::$entries[] = $message;
    }

    public function add($handle, $message)
    {
        self::$entries[] = $message;
    }
}

if (!function_exists('wc_get_logger')) {
    function wc_get_logger()
    {
        return new WC_Logger_Fake();
    }
}

if (!class_exists('WC_Logger')) {
    class WC_Logger extends WC_Logger_Fake
    {
    }
}

if (!class_exists('WC_Braspag_Helper')) {
    class WC_Braspag_Helper
    {
        public static function is_wc_lt($version)
        {
            return false;
        }
    }
}

require_once dirname(__DIR__, 2) . '/includes/class-wc-braspag-exception.php';
require_once dirname(__DIR__, 2) . '/includes/class-wc-braspag-logger.php';
require_once dirname(__DIR__, 2) . '/includes/class-wc-braspag-mpi-v3-client.php';
require_once dirname(__DIR__, 2) . '/includes/class-wc-braspag-auth3ds-v3-gate.php';
require_once dirname(__DIR__, 2) . '/includes/class-wc-braspag-pagador-api.php';
