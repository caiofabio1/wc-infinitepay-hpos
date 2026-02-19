<?php
/**
 * PHPUnit Bootstrap.
 */

// Load Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Define constants.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'INFINITEPAY_WC_PATH' ) ) {
    define( 'INFINITEPAY_WC_PATH', ABSPATH );
}
if ( ! defined( 'INFINITEPAY_WC_VERSION' ) ) {
    define( 'INFINITEPAY_WC_VERSION', '5.0.0' );
}
if ( ! defined( 'INFINITEPAY_WC_FILE' ) ) {
    define( 'INFINITEPAY_WC_FILE', ABSPATH . 'wc-infinitepay-hpos.php' );
}

// Load mocks.
require_once __DIR__ . '/mocks/class-abstract-payment-method-type.php';

// Load the class under test.
require_once INFINITEPAY_WC_PATH . 'includes/class-infinitepay-blocks.php';
