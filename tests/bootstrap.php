<?php
/**
 * Bootstrap para testes unitários (sem WordPress).
 * Para testes de integração com WordPress, troque pelo bloco comentado abaixo.
 */
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

define( 'BRASPAG_PLUGIN_TESTS', true );
define( 'ABSPATH', dirname( __DIR__, 3 ) . '/' );

// 1. Stubs primeiro: funções WP + exception/logger stubs (usados nos unit tests sem WP real).
require_once __DIR__ . '/stubs/woocommerce-stubs.php';

// 2. Classes reais não cobertas pelos stubs.
require_once dirname( __DIR__ ) . '/includes/class-wc-braspag-helper.php';

// 3. Classes reais de API e gateway.
require_once dirname( __DIR__ ) . '/includes/abstracts/abstract-wc-braspag-payment-gateway.php';
require_once dirname( __DIR__ ) . '/includes/class-wc-braspag-pagador-api.php';
require_once dirname( __DIR__ ) . '/includes/class-wc-braspag-risk-api.php';
require_once dirname( __DIR__ ) . '/includes/class-wc-braspag-zero-auth-api.php';
require_once dirname( __DIR__ ) . '/includes/class-wc-gateway-braspag.php';
require_once dirname( __DIR__ ) . '/includes/payment-methods/class-wc-gateway-braspag-creditcard.php';
require_once dirname( __DIR__ ) . '/includes/payment-methods/class-wc-gateway-braspag-debitcard.php';
require_once dirname( __DIR__ ) . '/includes/payment-methods/class-wc-gateway-braspag-boleto.php';
require_once dirname( __DIR__ ) . '/includes/payment-methods/class-wc-gateway-braspag-pix.php';
require_once dirname( __DIR__ ) . '/includes/payment-methods/class-wc-gateway-braspag-ewallet.php';

// Carrega classes do plugin que podem ser testadas sem WordPress.
require_once dirname( __DIR__ ) . '/includes/blocks/class-wc-braspag-blocks-abstract.php';
require_once dirname( __DIR__ ) . '/includes/blocks/payments/class-wc-braspag-blocks-creditcard.php';
require_once dirname( __DIR__ ) . '/includes/blocks/payments/class-wc-braspag-blocks-debitcard.php';
require_once dirname( __DIR__ ) . '/includes/blocks/payments/class-wc-braspag-blocks-pix.php';
require_once dirname( __DIR__ ) . '/includes/blocks/payments/class-wc-braspag-blocks-boleto.php';
require_once dirname( __DIR__ ) . '/includes/blocks/payments/class-wc-braspag-blocks-ewallet.php';
require_once dirname( __DIR__ ) . '/includes/class-wc-braspag-webhook-handler.php';

/*
 * --- Bootstrap de integração com WordPress (WP_UnitTestCase) ---
 * Descomente este bloco e comente o bloco acima para ativar.
 *
 * $_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/tmp/wordpress-tests-lib';
 * require_once $_tests_dir . '/includes/functions.php';
 *
 * function _braspag_load_plugin() {
 *     require_once WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
 *     require dirname( __DIR__ ) . '/wc-gateway-braspag.php';
 * }
 * tests_add_filter( 'muplugins_loaded', '_braspag_load_plugin' );
 *
 * require $_tests_dir . '/includes/bootstrap.php';
 */
