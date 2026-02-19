<?php
/**
 * Bootstrap the plugin unit testing environment.
 */

require_once __DIR__ . '/../vendor/autoload.php';

WP_Mock::bootstrap();

// Mock WooCommerce parent class if not exists
if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
	abstract class WC_Payment_Gateway {
		public $id;
		public $method_title;
		public $method_description;
		public $has_fields;
		public $supports;
		public $title;
		public $description;
		public $enabled;
		public $form_fields;
		public $settings;

		public function init_form_fields() {}
		public function init_settings() {}
		public function get_option( $key, $default = '' ) {
			return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
		}
		public function is_available() { return true; }
		public function get_return_url( $order = null ) { return ''; }
	}
}

// Define constants used in the plugin
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
    define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! defined( 'JSON_UNESCAPED_UNICODE' ) ) {
    define( 'JSON_UNESCAPED_UNICODE', 256 );
}
if ( ! defined( 'JSON_UNESCAPED_SLASHES' ) ) {
    define( 'JSON_UNESCAPED_SLASHES', 64 );
}

// Load plugin file
require_once __DIR__ . '/../includes/class-wc-gateway-infinitepay.php';
