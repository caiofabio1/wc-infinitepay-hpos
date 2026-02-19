<?php

if ( ! class_exists( 'WP_REST_Response' ) ) {
    class WP_REST_Response {
        public $data;
        public $status;
        public function __construct( $data = null, $status = 200 ) {
            $this->data   = $data;
            $this->status = $status;
        }
        public function get_data() {
            return $this->data;
        }
        public function get_status() {
            return $this->status;
        }
    }
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
    class WP_REST_Request {
        public function get_header( $header ) {}
        public function get_json_params() {}
    }
}

if ( ! class_exists( 'WC_Order' ) ) {
    class WC_Order {
        public function get_id() {}
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
        public function get_option( $key, $default = false ) {}
        public function is_available() {}
    }
}

if ( ! function_exists( 'wc_get_logger' ) ) {
    function wc_get_logger() {
        return new class {
            public function log( $level, $message, $context = array() ) {}
        };
    }
}
