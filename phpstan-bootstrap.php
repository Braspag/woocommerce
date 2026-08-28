<?php

define( 'ABSPATH', dirname( __DIR__, 3 ) . '/' );

if ( ! defined( 'WP_DEBUG' ) ) {
    define( 'WP_DEBUG', true );
}

if ( ! defined( 'WC_ABSPATH' ) ) {
    define( 'WC_ABSPATH', ABSPATH . 'wp-content/plugins/woocommerce/' );
}