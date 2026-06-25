<?php
/**
 * PHPUnit bootstrap — minimal WP/WC stubs so the plugin classes load in isolation.
 *
 * @package InfinitePay\WooCommerce\Tests
 */

error_reporting( E_ALL );

if ( ! defined( 'ABSPATH' ) )            { define( 'ABSPATH', sys_get_temp_dir() . '/' ); }
if ( ! defined( 'MINUTE_IN_SECONDS' ) )  { define( 'MINUTE_IN_SECONDS', 60 ); }
if ( ! defined( 'HOUR_IN_SECONDS' ) )    { define( 'HOUR_IN_SECONDS', 3600 ); }
if ( ! defined( 'INFINITEPAY_WC_VERSION' ) ) { define( 'INFINITEPAY_WC_VERSION', 'test' ); }
if ( ! defined( 'INFINITEPAY_WC_FILE' ) ) { define( 'INFINITEPAY_WC_FILE', __FILE__ ); }
if ( ! defined( 'INFINITEPAY_WC_PATH' ) ) { define( 'INFINITEPAY_WC_PATH', dirname( __DIR__ ) . '/' ); }

// Polyfill str_starts_with for PHP 7.4 (the plugin polyfills it in its main file,
// which we do not load here).
if ( ! function_exists( 'str_starts_with' ) ) {
    function str_starts_with( string $haystack, string $needle ): bool {
        return 0 === strncmp( $haystack, $needle, strlen( $needle ) );
    }
}

// --- WordPress / WooCommerce function stubs ---
if ( ! function_exists( 'add_action' ) )          { function add_action( ...$a ) {} }
if ( ! function_exists( 'add_filter' ) )          { function add_filter( ...$a ) {} }
if ( ! function_exists( 'do_action' ) )           { function do_action( ...$a ) {} }
if ( ! function_exists( 'apply_filters' ) )       { function apply_filters( $t, $v = null ) { return $v; } }
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; } }
if ( ! function_exists( 'is_admin' ) )            { function is_admin() { return false; } }
if ( ! function_exists( 'rest_url' ) )            { function rest_url( $p = '' ) { return 'http://example.com/wp-json/' . ltrim( $p, '/' ); } }
if ( ! function_exists( '__' ) )                  { function __( $t, $d = 'default' ) { return $t; } }
if ( ! function_exists( 'esc_html' ) )            { function esc_html( $t ) { return $t; } }
if ( ! function_exists( 'esc_html__' ) )          { function esc_html__( $t, $d = 'default' ) { return $t; } }
if ( ! function_exists( 'esc_url' ) )             { function esc_url( $u ) { return $u; } }
if ( ! function_exists( 'esc_url_raw' ) )         { function esc_url_raw( $u ) { return $u; } }
if ( ! function_exists( 'esc_attr' ) )            { function esc_attr( $t ) { return $t; } }
if ( ! function_exists( 'is_email' ) )            { function is_email( $e ) { return filter_var( $e, FILTER_VALIDATE_EMAIL ); } }
if ( ! function_exists( 'plugins_url' ) )         { function plugins_url( $path = '', $plugin = '' ) { return 'http://example.com/' . ltrim( $path, '/' ); } }
if ( ! function_exists( 'wp_json_encode' ) )      { function wp_json_encode( $d, $o = 0 ) { return json_encode( $d, $o ); } }
if ( ! function_exists( 'wc_get_logger' ) ) {
    function wc_get_logger() {
        return new class {
            public function log( $level, $message, $context = array() ) {}
        };
    }
}
$GLOBALS['__ip_transients'] = array();
if ( ! function_exists( 'get_transient' ) )       { function get_transient( $k ) { return $GLOBALS['__ip_transients'][ $k ] ?? false; } }
if ( ! function_exists( 'set_transient' ) )       { function set_transient( $k, $v, $e = 0 ) { $GLOBALS['__ip_transients'][ $k ] = $v; return true; } }
if ( ! function_exists( 'wp_using_ext_object_cache' ) ) { function wp_using_ext_object_cache() { return false; } }
if ( ! function_exists( 'wp_cache_add' ) )        { function wp_cache_add( $k, $v, $g = '', $e = 0 ) { return true; } }

// --- WooCommerce class stubs ---
if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
    class WC_Payment_Gateway {
        public $id; public $method_title; public $method_description; public $has_fields;
        public $supports = array(); public $title; public $description; public $enabled;
        public $icon; public $form_fields = array(); public $settings = array();
        public function init_form_fields() {}
        public function init_settings() {}
        public function get_option( $k, $d = null ) { return $this->settings[ $k ] ?? $d; }
        public function is_available() { return true; }
        public function get_return_url( $order = null ) { return 'http://example.com/return'; }
    }
}
if ( ! class_exists( 'WC_Order_Item' ) ) {
    class WC_Order_Item {
        public $quantity = 1; public $total = 0.0; public $name = 'Item';
        public function get_quantity() { return $this->quantity; }
        public function get_total() { return $this->total; }
        public function get_name() { return $this->name; }
    }
}
if ( ! class_exists( 'WC_Order_Item_Fee' ) ) {
    class WC_Order_Item_Fee {
        public $total = 0.0; public $name = 'Fee';
        public function get_total() { return $this->total; }
        public function get_name() { return $this->name; }
    }
}
if ( ! class_exists( 'WC_Order' ) ) {
    class WC_Order {
        public $items = array(); public $fees = array(); public $coupons = array();
        public $total = 0.0; public $shipping_total = 0.0; public $discount_total = 0.0;
        public $id = 123; public $meta = array();
        public function get_id() { return $this->id; }
        public function get_total() { return $this->total; }
        public function get_items() { return $this->items; }
        public function get_fees() { return $this->fees; }
        public function get_shipping_total() { return $this->shipping_total; }
        public function get_discount_total() { return $this->discount_total; }
        public function get_coupon_codes() { return $this->coupons; }
        public function get_currency() { return 'BRL'; }
        public function get_billing_first_name() { return 'John'; }
        public function get_billing_last_name() { return 'Doe'; }
        public function get_billing_email() { return 'john@example.com'; }
        public function get_billing_phone() { return '11999998888'; }
        public function get_billing_postcode() { return '04101300'; }
        public function get_billing_address_1() { return 'Rua Teste 123'; }
        public function get_billing_address_2() { return 'Apto 1'; }
        public function get_meta( $k ) { return $this->meta[ $k ] ?? ''; }
    }
}
if ( ! class_exists( 'WP_REST_Request' ) ) {
    class WP_REST_Request {
        private $headers = array();
        private $json = array();
        public function set_header( $k, $v ) { $this->headers[ strtolower( $k ) ] = $v; }
        public function get_header( $k ) { return $this->headers[ strtolower( $k ) ] ?? null; }
        public function set_json_params( $p ) { $this->json = $p; }
        public function get_json_params() { return $this->json; }
    }
}
if ( ! class_exists( 'WP_REST_Response' ) ) {
    class WP_REST_Response {
        public $data; public $status;
        public function __construct( $data = null, $status = 200 ) { $this->data = $data; $this->status = $status; }
        public function get_data() { return $this->data; }
        public function get_status() { return $this->status; }
    }
}
if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        public $code; public $message;
        public function __construct( $code = '', $message = '', $data = array() ) { $this->code = $code; $this->message = $message; }
        public function get_error_message() { return $this->message; }
    }
}

// --- Classes under test ---
require_once dirname( __DIR__ ) . '/includes/class-wc-gateway-infinitepay.php';
require_once dirname( __DIR__ ) . '/includes/class-infinitepay-webhook.php';
