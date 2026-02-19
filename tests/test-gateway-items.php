<?php

// Define constants
if (!defined('ABSPATH')) define('ABSPATH', '/');
if (!defined('MINUTE_IN_SECONDS')) define('MINUTE_IN_SECONDS', 60);

// Mock WordPress functions
if (!function_exists('add_action')) { function add_action($tag, $callback, $priority = 10, $accepted_args = 1) {} }
if (!function_exists('apply_filters')) { function apply_filters($tag, $value) { return $value; } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($str) { return $str; } }
if (!function_exists('is_admin')) { function is_admin() { return false; } }
if (!function_exists('rest_url')) { function rest_url($path) { return 'http://example.com/' . $path; } }
if (!function_exists('__')) { function __($text, $domain) { return $text; } }
if (!function_exists('esc_html')) { function esc_html($text) { return $text; } }
if (!function_exists('esc_html__')) { function esc_html__($text, $domain) { return $text; } }
if (!function_exists('esc_url')) { function esc_url($url) { return $url; } }
if (!function_exists('is_email')) { function is_email($email) { return filter_var($email, FILTER_VALIDATE_EMAIL); } }
if (!function_exists('wc_get_logger')) { function wc_get_logger() { return new class { public function log($level, $message, $context = []) {} }; } }

// Mock WooCommerce classes
class WC_Order {
    public $items = [];
    public $fees = [];
    public $total = 0.0;
    public $shipping_total = 0.0;
    public $id = 123;

    public function get_id() { return $this->id; }
    public function get_total() { return $this->total; }
    public function get_items() { return $this->items; }
    public function get_shipping_total() { return $this->shipping_total; }
    public function get_fees() { return $this->fees; }
    public function get_billing_first_name() { return 'John'; }
    public function get_billing_last_name() { return 'Doe'; }
    public function get_billing_email() { return 'john.doe@example.com'; }
    public function get_billing_phone() { return '5511999999999'; }
    public function get_billing_postcode() { return '01001000'; }
    public function get_billing_address_1() { return 'Rua Teste, 123'; }
    public function get_billing_address_2() { return 'Apt 1'; }
    public function get_meta($key) { return ''; }
}

class WC_Order_Item {
    public $quantity = 1;
    public $total = 0.0;
    public $name = 'Item Name';

    public function get_quantity() { return $this->quantity; }
    public function get_total() { return $this->total; }
    public function get_name() { return $this->name; }
}

class WC_Order_Item_Fee {
    public $total = 0.0;
    public $name = 'Fee Name';

    public function get_total() { return $this->total; }
    public function get_name() { return $this->name; }
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
    public $form_fields = [];
    public $settings = [];

    public function init_form_fields() {}
    public function init_settings() {}
    public function get_option($key, $default = null) {
        // Return default settings for testing
        $settings = [
            'enabled' => 'yes',
            'handle' => 'test_handle',
            'send_items' => 'yes',
            'debug' => 'yes',
        ];
        return $settings[$key] ?? $default;
    }
    public function is_available() { return true; }
    public function get_return_url($order) { return 'http://example.com/return'; }
}

// Load the class file
require_once dirname(__DIR__) . '/includes/class-wc-gateway-infinitepay.php';

// Instantiate the gateway
$gateway = new WC_Gateway_InfinitePay_HPOS();

// Create a mock order with items, shipping, and fees
$order = new WC_Order();
$order->total = 160.00; // 16000 cents
$order->shipping_total = 20.00; // 2000 cents

$item1 = new WC_Order_Item();
$item1->quantity = 2;
$item1->total = 100.00; // 50.00 each -> 5000 cents
$item1->name = 'Product 1';

$item2 = new WC_Order_Item();
$item2->quantity = 1;
$item2->total = 25.00; // 25.00 each -> 2500 cents
$item2->name = 'Product 2';

// Rounding case: 10.00 split in 3 = 3.333... -> 333 cents each -> total 999 cents. Diff +1 cent.
$item3 = new WC_Order_Item();
$item3->quantity = 3;
$item3->total = 10.00;
$item3->name = 'Rounding Product';

$order->items = [$item1, $item2, $item3];

$fee1 = new WC_Order_Item_Fee();
$fee1->total = 5.00; // 500 cents
$fee1->name = 'Fee 1';

$order->fees = [$fee1];

// Use reflection to call private method build_items
$reflection = new ReflectionClass($gateway);
$method = $reflection->getMethod('build_items');
$method->setAccessible(true);

$result = [];

// Test detailed items
$gateway->send_items = true;
$result['detailed'] = $method->invoke($gateway, $order);

// Test consolidated items
$gateway->send_items = false;
$result['consolidated'] = $method->invoke($gateway, $order);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
