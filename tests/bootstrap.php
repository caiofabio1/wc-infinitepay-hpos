<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Define constants
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '/tmp/wordpress/' );
}
if ( ! defined( 'INFINITEPAY_WC_PATH' ) ) {
    define( 'INFINITEPAY_WC_PATH', __DIR__ . '/../' );
}
if ( ! defined( 'INFINITEPAY_WC_FILE' ) ) {
    define( 'INFINITEPAY_WC_FILE', __DIR__ . '/../wc-infinitepay-hpos.php' );
}
if ( ! defined( 'INFINITEPAY_WC_VERSION' ) ) {
    define( 'INFINITEPAY_WC_VERSION', '5.0.0' );
}

// Mock WordPress functions
if ( ! function_exists( 'get_option' ) ) {
    function get_option( $option, $default = false ) {
        global $wp_options;
        return isset( $wp_options[ $option ] ) ? $wp_options[ $option ] : $default;
    }
}

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = 'default' ) {
        return $text;
    }
}

if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( $text, $domain = 'default' ) {
        return $text;
    }
}

if ( ! function_exists( 'plugins_url' ) ) {
    function plugins_url( $path = '', $plugin = '' ) {
        return 'http://example.com/wp-content/plugins/' . $path;
    }
}

if ( ! function_exists( 'wp_register_script' ) ) {
    function wp_register_script( $handle, $src, $deps = array(), $ver = false, $in_footer = false ) {
        return true;
    }
}

// Load the mock abstract class
require_once __DIR__ . '/MockAbstractPaymentMethodType.php';

// Load the class under test
require_once __DIR__ . '/../includes/class-infinitepay-blocks.php';
