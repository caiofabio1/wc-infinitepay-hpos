<?php
/**
 * Bootstrap for tests.
 */

// Define constants used by the plugin.
define( 'ABSPATH', true );

// Mock WordPress functions.
if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = 'default' ) { return $text; }
}
if ( ! function_exists( '_e' ) ) {
    function _e( $text, $domain = 'default' ) { echo $text; }
}
if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ) { return $text; }
}
if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( $text, $domain = 'default' ) { return $text; }
}
if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $url ) { return $url; }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( $url ) { return $url; }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $text ) { return $text; }
}
if ( ! function_exists( 'add_action' ) ) {
    function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
}
if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( $tag, $value, ...$args ) { return $value; }
}
if ( ! function_exists( 'rest_url' ) ) {
    function rest_url( $path ) { return 'https://example.com/wp-json/' . $path; }
}
if ( ! function_exists( 'admin_url' ) ) {
    function admin_url( $path ) { return 'https://example.com/wp-admin/' . $path; }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data, $options = 0, $depth = 512 ) { return json_encode( $data, $options, $depth ); }
}
if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
}
if ( ! function_exists( 'is_email' ) ) {
    function is_email( $email ) { return false !== strpos( $email, '@' ); }
}
if ( ! function_exists( 'wp_unslash' ) ) {
    function wp_unslash( $value ) { return $value; }
}
if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( $key ) { return false; }
}
if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( $key, $value, $expiration = 0 ) { return true; }
}
if ( ! function_exists( 'delete_transient' ) ) {
    function delete_transient( $key ) { return true; }
}
if ( ! function_exists( 'is_admin' ) ) {
    function is_admin() { return false; }
}

global $wp_remote_post_response;
$wp_remote_post_response = array();
if ( ! function_exists( 'wp_remote_post' ) ) {
    function wp_remote_post( $url, $args = array() ) {
        global $wp_remote_post_response;
        return $wp_remote_post_response;
    }
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
    function wp_remote_retrieve_response_code( $response ) {
        return $response['response']['code'] ?? 200;
    }
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
    function wp_remote_retrieve_body( $response ) {
        return $response['body'] ?? '';
    }
}

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        public $code;
        public $message;
        public $data;
        public function __construct( $code = '', $message = '', $data = '' ) {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }
        public function get_error_message() { return $this->message; }
    }
}

// Mock WooCommerce classes and functions.
if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
    class WC_Payment_Gateway {
        public $id;
        public $method_title;
        public $method_description;
        public $has_fields;
        public $supports;
        public $title;
        public $description;
        public $enabled;
        public $settings = array();
        public $form_fields = array();

        public function init_form_fields() {}
        public function init_settings() {}
        public function get_option( $key, $default = '' ) {
            return $this->settings[ $key ] ?? $default;
        }
        public function get_return_url( $order = null ) {
            return 'https://example.com/checkout/order-received/123/?key=wc_order_123';
        }
        public function process_admin_options() {}
        public function is_available() { return true; }
    }
}

if ( ! function_exists( 'wc_get_logger' ) ) {
    function wc_get_logger() {
        return new class {
            public function log( $level, $message, $context = array() ) {}
        };
    }
}

if ( ! function_exists( 'wc_add_notice' ) ) {
    function wc_add_notice( $message, $notice_type = 'success' ) {}
}

if ( ! function_exists( 'WC' ) ) {
    function WC() {
        return new class {
            public $cart;
            public function __construct() {
                $this->cart = new class {
                    public function empty_cart() {}
                };
            }
        };
    }
}

if ( ! class_exists( 'WC_Order_Item' ) ) {
    class WC_Order_Item {
        public $name;
        public $quantity;
        public $total;
        public function __construct( $name, $quantity, $total ) {
            $this->name = $name;
            $this->quantity = $quantity;
            $this->total = $total;
        }
        public function get_name() { return $this->name; }
        public function get_quantity() { return $this->quantity; }
        public function get_total() { return $this->total; }
    }
}

// Order mock.
if ( ! class_exists( 'WC_Order' ) ) {
    class WC_Order {
        public $id = 123;
        public $total = 100.00;
        public $shipping_total = 10.00;
        public $billing_first_name = 'John';
        public $billing_last_name = 'Doe';
        public $billing_email = 'john.doe@example.com';
        public $billing_phone = '11999999999';
        public $billing_postcode = '01234-567';
        public $payment_method = 'infinitepay_hpos';
        public $status = 'pending';
        public $meta = array();
        public $items = array();

        public function get_id() { return $this->id; }
        public function get_total() { return $this->total; }
        public function get_shipping_total() { return $this->shipping_total; }
        public function get_billing_first_name() { return $this->billing_first_name; }
        public function get_billing_last_name() { return $this->billing_last_name; }
        public function get_billing_email() { return $this->billing_email; }
        public function get_billing_phone() { return $this->billing_phone; }
        public function get_billing_postcode() { return $this->billing_postcode; }
        public function get_billing_address_1() { return 'Main St'; }
        public function get_billing_address_2() { return 'Apt 1'; }
        public function get_payment_method() { return $this->payment_method; }
        public function get_items() { return $this->items; }
        public function get_fees() { return array(); }
        public function get_meta( $key, $single = true, $context = 'view' ) { return $this->meta[ $key ] ?? ''; }
        public function update_meta_data( $key, $value, $meta_id = 0 ) { $this->meta[ $key ] = $value; }
        public function delete_meta_data( $key ) { unset( $this->meta[ $key ] ); }
        public function save() {}
        public function update_status( $new_status, $note = '', $manual = false ) { $this->status = $new_status; }
        public function add_order_note( $note, $is_customer_note = 0, $added_by_user = false ) {}
        public function payment_complete( $transaction_id = '' ) { $this->status = 'processing'; }
    }
}

if ( ! function_exists( 'wc_get_order' ) ) {
    function wc_get_order( $order_id ) {
        if ( $order_id === 123 ) {
            return new WC_Order();
        }
        return false;
    }
}

// Load the class under test.
require_once __DIR__ . '/../includes/class-wc-gateway-infinitepay.php';
