<?php
/**
 * Bootstrap the test suite.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/wordpress/' );
}

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Bootstrap WP_Mock
WP_Mock::bootstrap();

// Define WooCommerce and WordPress classes/stubs if they don't exist
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

        public function init_form_fields() {}
        public function init_settings() {}
        public function get_option( $key, $empty_value = null ) { return ''; }
        public function process_admin_options() {}
        public function is_available() { return true; }
        public function log( string $message, string $level = 'info' ): void {}
    }
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
    class WP_REST_Request {
        public function get_header( $header ) {}
        public function get_json_params() {}
    }
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
    class WP_REST_Response {
        public $data;
        public $status;
        public function __construct( $data = null, $status = 200 ) {
            $this->data = $data;
            $this->status = $status;
        }
        public function get_status() {
            return $this->status;
        }
        public function get_data() {
            return $this->data;
        }
    }
}

if ( ! class_exists( 'WC_Order' ) ) {
    class WC_Order {
        public function get_payment_method() {}
        public function get_meta( $key ) {}
        public function get_status() {}
        public function payment_complete( $tx_id = '' ) {}
        public function update_meta_data( $key, $value ) {}
        public function delete_meta_data( $key ) {}
        public function add_order_note( $note ) {}
        public function save() {}
    }
}

if ( ! class_exists( 'WC_Payment_Gateways' ) ) {
    class WC_Payment_Gateways {
        public function payment_gateways() {}
    }
}

if ( ! class_exists( 'WooCommerce' ) ) {
    class WooCommerce {
        public function payment_gateways() {}
    }
}

// Define constants used in the plugin
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
    define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}

// Now load plugin files
require_once __DIR__ . '/../includes/class-wc-gateway-infinitepay.php';
require_once __DIR__ . '/../includes/class-infinitepay-webhook.php';
