<?php
/**
 * PHPUnit Bootstrap
 */

// Define Constants
define( 'ABSPATH', '/tmp/wordpress/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );

// Mock WordPress Functions
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) {
        return trim( strip_tags( $str ) );
    }
}

if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( $url ) {
        return $url;
    }
}

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = 'default' ) {
        return $text;
    }
}

if ( ! function_exists( 'register_rest_route' ) ) {
    function register_rest_route( $namespace, $route, $args ) {
        // No-op for now
    }
}

if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( $transient ) {
        global $wp_transients;
        return isset( $wp_transients[ $transient ] ) ? $wp_transients[ $transient ] : false;
    }
}

if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( $transient, $value, $expiration = 0 ) {
        global $wp_transients;
        $wp_transients[ $transient ] = $value;
        return true;
    }
}

if ( ! function_exists( 'wc_get_order' ) ) {
    function wc_get_order( $id ) {
        // We'll mock this in the test case if needed, but for now return null or a mock object
        // For the rate limit test, we don't reach this part usually, but if we do...
        return null;
    }
}

if ( ! function_exists( 'do_action' ) ) {
    function do_action( $tag, ...$args ) {
        // No-op
    }
}

// Mock WordPress Classes
class WP_REST_Request {
    private $headers = [];
    private $params = [];
    private $body_params = [];

    public function get_header( $header ) {
        return isset( $this->headers[ $header ] ) ? $this->headers[ $header ] : null;
    }

    public function set_header( $header, $value ) {
        $this->headers[ $header ] = $value;
    }

    public function get_json_params() {
        return $this->body_params;
    }

    public function set_body_params( $params ) {
        $this->body_params = $params;
    }
}

class WP_REST_Response {
    public $data;
    public $status;

    public function __construct( $data = null, $status = 200 ) {
        $this->data   = $data;
        $this->status = $status;
    }

    public function get_status() {
        return $this->status;
    }

    public function get_data() {
        return $this->data;
    }
}

class WP_Error {
    public $errors = [];
    public $error_data = [];

    public function __construct( $code = '', $message = '', $data = '' ) {
        if ( empty( $code ) ) {
            return;
        }
        $this->errors[ $code ][] = $message;
        if ( ! empty( $data ) ) {
            $this->error_data[ $code ] = $data;
        }
    }

    public function get_error_code() {
        $codes = array_keys( $this->errors );
        return empty( $codes ) ? '' : $codes[0];
    }

    public function get_error_message( $code = '' ) {
        if ( empty( $code ) ) {
            $code = $this->get_error_code();
        }
        $messages = isset( $this->errors[ $code ] ) ? $this->errors[ $code ] : array();
        return empty( $messages ) ? '' : $messages[0];
    }
}

// Mock WooCommerce Classes
class WC_Order {
    // Mock methods as needed
}

class WC_Gateway_InfinitePay_HPOS {
    public $webhook_secret = 'test_secret';

    public function log( $message, $level = 'info' ) {
        // No-op or echo for debug
    }
}

class WC_Payment_Gateways {
    public function payment_gateways() {
        return [
            'infinitepay_hpos' => new WC_Gateway_InfinitePay_HPOS()
        ];
    }
}

class WC_Main {
    public function payment_gateways() {
        return new WC_Payment_Gateways();
    }
}

if ( ! function_exists( 'WC' ) ) {
    function WC() {
        return new WC_Main();
    }
}

// Global storage for transients
global $wp_transients;
$wp_transients = [];

// Load the class to test
require_once dirname( __DIR__ ) . '/includes/class-infinitepay-webhook.php';
