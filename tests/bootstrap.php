<?php

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

WP_Mock::bootstrap();

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '/tmp/wordpress/' );
}

// Mock WC_Payment_Gateway class if it doesn't exist
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
        public $settings = [];
        public $form_fields = [];

        public function init_form_fields() {}
        public function init_settings() {}
        public function get_option( $key, $default = null ) {
            return $this->settings[ $key ] ?? $default;
        }
        public function is_available() { return true; }
        public function process_payment( $order_id ) {}
        public function get_return_url( $order = null ) { return ''; }
    }
}

// Mock WC_Order class
if ( ! class_exists( 'WC_Order' ) ) {
    class WC_Order {
        public function get_id() { return 123; }
        public function get_total() { return 100.00; }
        public function get_items() { return []; }
        public function get_shipping_total() { return 0; }
        public function get_fees() { return []; }
        public function get_billing_first_name() { return 'John'; }
        public function get_billing_last_name() { return 'Doe'; }
        public function get_billing_email() { return 'john@example.com'; }
        public function get_billing_phone() { return '5511999999999'; }
        public function get_billing_postcode() { return '12345678'; }
        public function get_meta( $key ) { return ''; }
        public function get_billing_address_1() { return 'Rua Exemplo'; }
        public function get_billing_address_2() { return ''; }
    }
}
