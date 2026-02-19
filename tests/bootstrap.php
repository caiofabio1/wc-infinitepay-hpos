<?php

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
    define( 'MINUTE_IN_SECONDS', 60 );
}

// Mock WordPress functions
if ( ! function_exists( 'register_rest_route' ) ) {
    function register_rest_route( $namespace, $route, $args = array() ) {}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) { return trim( strip_tags( $str ) ); }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( $url ) { return $url; }
}
if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( $transient ) { return $GLOBALS['wp_transients'][ $transient ] ?? false; }
}
if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( $transient, $value, $expiration = 0 ) { $GLOBALS['wp_transients'][ $transient ] = $value; return true; }
}
if ( ! function_exists( '__' ) ) {
    function __($text, $domain = 'default') { return $text; }
}
if ( ! function_exists( '_e' ) ) {
    function _e($text, $domain = 'default') { echo $text; }
}
if ( ! function_exists( 'esc_html' ) ) {
    function esc_html($text) { return $text; }
}
if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__($text, $domain = 'default') { return $text; }
}
if ( ! function_exists( 'add_action' ) ) {
    function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) {}
}
if ( ! function_exists( 'do_action' ) ) {
    function do_action($tag, ...$arg) {}
}
if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters($tag, $value, ...$var) { return $value; }
}
if ( ! function_exists( 'wc_get_order' ) ) {
    function wc_get_order($id) {
        if ( isset( $GLOBALS['wc_orders'][ $id ] ) ) {
            return $GLOBALS['wc_orders'][ $id ];
        }
        return false;
    }
}
if ( ! function_exists( 'wc_get_logger' ) ) {
    function wc_get_logger() { return new WC_Logger(); }
}

// Global state for mocks
$GLOBALS['wp_transients'] = [];
$GLOBALS['wc_orders'] = [];
$GLOBALS['wc_gateways'] = [];

// Mock Classes
class WP_REST_Request {
    private $headers = [];
    private $params = [];
    private $json_params = [];

    public function set_header($key, $value) { $this->headers[$key] = $value; }
    public function get_header($key) { return $this->headers[$key] ?? null; }
    public function set_param($key, $value) { $this->params[$key] = $value; }
    public function get_param($key) { return $this->params[$key] ?? null; }
    public function set_json_params($params) { $this->json_params = $params; }
    public function get_json_params() { return $this->json_params; }
}

class WP_REST_Response {
    public $data;
    public $status;
    public function __construct($data = null, $status = 200) {
        $this->data = $data;
        $this->status = $status;
    }
    public function get_data() { return $this->data; }
    public function get_status() { return $this->status; }
}

class WC_Payment_Gateway {
    public $id;
    public $method_title;
    public $method_description;
    public $has_fields;
    public $supports;
    public $title;
    public $description;
    public $enabled;
}

class WC_Gateway_InfinitePay_HPOS extends WC_Payment_Gateway {
    public string $webhook_secret = '';
    public function log( string $message, string $level = 'info' ): void {
        // Mock log
    }
}

class WC_Order {
    private $id;
    private $data = [];
    private $meta = [];
    public $order_notes = [];

    public function __construct($id = 0) { $this->id = $id; }
    public function get_id() { return $this->id; }
    public function get_payment_method() { return $this->data['payment_method'] ?? ''; }
    public function set_payment_method($method) { $this->data['payment_method'] = $method; }
    public function get_status() { return $this->data['status'] ?? 'pending'; }
    public function set_status($status) { $this->data['status'] = $status; }
    public function get_meta($key) { return $this->meta[$key] ?? ''; }
    public function update_meta_data($key, $value) { $this->meta[$key] = $value; }
    public function delete_meta_data($key) { unset($this->meta[$key]); }
    public function add_order_note($note) { $this->order_notes[] = $note; }
    public function payment_complete($tx_id = '') { $this->data['status'] = 'processing'; }
    public function save() {}
}

class WC_Logger {
    public function log($level, $message, $context = []) {}
}

class WC_Payment_Gateways {
    public function payment_gateways() {
        return $GLOBALS['wc_gateways'];
    }
}

class WC_Main {
    public function payment_gateways() {
        return new WC_Payment_Gateways();
    }
}

if ( ! function_exists( 'WC' ) ) {
    function WC() {
        static $instance;
        if ( ! $instance ) {
            $instance = new WC_Main();
        }
        return $instance;
    }
}

require_once __DIR__ . '/../includes/class-infinitepay-webhook.php';
