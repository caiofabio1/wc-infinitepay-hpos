<?php

require_once __DIR__ . '/../vendor/autoload.php';

define( 'ABSPATH', '/tmp/wordpress/' );
define( 'MINUTE_IN_SECONDS', 60 );

// Mock WordPress functions
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
    return true;
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
    return true;
}

function apply_filters( $hook, $value, ...$args ) {
    return $value;
}

function do_action( $hook, ...$args ) {
    return true;
}

function __( $text, $domain = 'default' ) {
    return $text;
}

function esc_html__( $text, $domain = 'default' ) {
    return $text;
}

function esc_html( $text ) {
    return $text;
}

function esc_url( $url ) {
    return $url;
}

function esc_url_raw( $url ) {
    return $url;
}

function wp_json_encode( $data, $options = 0 ) {
    return json_encode( $data, $options );
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post( $url, $args = array() ) {
        // This will be mocked in tests via a static helper or global variable if needed,
        // but for now let's just return a default structure.
        // In the test we will likely override or use Mockery on a wrapper if possible,
        // but since it's a global function, we might need a different approach like "Patchwork" or "uopz".
        // However, "WP Mock" or "Brain Monkey" are popular for this.
        // For simplicity here, we'll use a global variable to store the mock response.
        global $wp_remote_post_mock;
        if ( isset( $wp_remote_post_mock ) && is_callable( $wp_remote_post_mock ) ) {
            return $wp_remote_post_mock( $url, $args );
        }
        return array('response' => array('code' => 200), 'body' => '{}');
    }
}

function wp_remote_retrieve_response_code( $response ) {
    if ( is_wp_error( $response ) ) {
        return '';
    }
    return isset( $response['response']['code'] ) ? $response['response']['code'] : 200;
}

function wp_remote_retrieve_body( $response ) {
    if ( is_wp_error( $response ) ) {
        return '';
    }
    return isset( $response['body'] ) ? $response['body'] : '';
}

function is_wp_error( $thing ) {
    return $thing instanceof WP_Error;
}

function is_email( $email ) {
    return filter_var( $email, FILTER_VALIDATE_EMAIL );
}

function sanitize_text_field( $str ) {
    return trim( strip_tags( $str ) );
}

function get_transient( $transient ) {
    return false;
}

function set_transient( $transient, $value, $expiration = 0 ) {
    return true;
}

function rest_url( $path = '' ) {
    return 'https://example.com/wp-json/' . $path;
}

function admin_url( $path = '' ) {
    return 'https://example.com/wp-admin/' . $path;
}

function wp_unslash( $value ) {
    return $value;
}

function is_admin() {
    return false;
}

// Mock WP_Error
class WP_Error {
    public $code;
    public $message;
    public $data;

    public function __construct( $code = '', $message = '', $data = '' ) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }

    public function get_error_message() {
        return $this->message;
    }
}

// Mock WooCommerce functions
function wc_get_logger() {
    return new class {
        public function log( $level, $message, $context = array() ) {}
    };
}

function wc_add_notice( $message, $notice_type = 'success', $data = array() ) {
    // Helper to capture notices for testing
    global $wc_notices;
    $wc_notices[] = array( 'message' => $message, 'type' => $notice_type );
}

function wc_get_order( $the_order = false ) {
    global $wc_get_order_mock;
    if ( isset( $wc_get_order_mock ) && is_callable( $wc_get_order_mock ) ) {
        return $wc_get_order_mock( $the_order );
    }
    return false;
}

// Mock WC classes
class WC_Payment_Gateway {
    public $id;
    public $method_title;
    public $method_description;
    public $has_fields;
    public $supports;
    public $title;
    public $description;
    public $enabled;
    public $settings;
    public $form_fields = array();

    public function init_form_fields() {}
    public function init_settings() {
        $this->settings = array();
    }
    public function get_option( $key, $empty_value = null ) {
        return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $empty_value;
    }
    public function is_available() { return true; }
    public function get_return_url( $order = null ) { return 'https://example.com/return'; }
}

class WC_Order {
    public function get_id() { return 0; }
    public function get_total() { return 0; }
    public function get_items() { return array(); }
    public function get_shipping_total() { return 0; }
    public function get_fees() { return array(); }
    public function get_billing_first_name() { return ''; }
    public function get_billing_last_name() { return ''; }
    public function get_billing_email() { return ''; }
    public function get_billing_phone() { return ''; }
    public function get_billing_postcode() { return ''; }
    public function get_billing_address_1() { return ''; }
    public function get_billing_address_2() { return ''; }
    public function get_meta( $key ) { return ''; }
    public function update_status( $status, $note = '' ) {}
    public function update_meta_data( $key, $value, $meta_id = 0 ) {}
    public function delete_meta_data( $key ) {}
    public function save() {}
    public function add_order_note( $note ) {}
    public function payment_complete( $transaction_id = '' ) {}
    public function get_payment_method() { return ''; }
}

class WC_Cart {
    public function empty_cart() {}
}

class WC_Order_Item {
    public function get_quantity() { return 1; }
    public function get_total() { return 10; }
    public function get_name() { return 'Item'; }
}

class WC_Order_Item_Fee {
    public function get_total() { return 0; }
    public function get_name() { return 'Fee'; }
}

function WC() {
    static $wc;
    if ( ! isset( $wc ) ) {
        $wc = new stdClass();
        $wc->cart = new WC_Cart();
    }
    return $wc;
}

// Include the class file
require_once __DIR__ . '/../includes/class-wc-gateway-infinitepay.php';
