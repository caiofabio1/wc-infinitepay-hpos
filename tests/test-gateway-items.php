<?php
/**
 * Testes unitários para build_items() do gateway InfinitePay.
 *
 * @package InfinitePay\WooCommerce\Tests
 */

// Define constants.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '/' );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
    define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}

// Mock WordPress functions.
if ( ! function_exists( 'add_action' ) ) {
    function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {}
}
if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( $tag, $value ) { return $value; }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) { return $str; }
}
if ( ! function_exists( 'is_admin' ) ) {
    function is_admin() { return false; }
}
if ( ! function_exists( 'rest_url' ) ) {
    function rest_url( $path ) { return 'http://example.com/' . $path; }
}
if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = 'default' ) { return $text; }
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
if ( ! function_exists( 'is_email' ) ) {
    function is_email( $email ) { return filter_var( $email, FILTER_VALIDATE_EMAIL ); }
}
if ( ! function_exists( 'wc_get_logger' ) ) {
    function wc_get_logger() {
        return new class {
            public function log( $level, $message, $context = array() ) {}
        };
    }
}

// Mock WC_Order.
class WC_Order {
    public $items   = array();
    public $fees    = array();
    public $total   = 0.0;
    public $shipping_total = 0.0;
    public $id      = 123;

    public function get_id()                { return $this->id; }
    public function get_total()             { return $this->total; }
    public function get_items()             { return $this->items; }
    public function get_shipping_total()    { return $this->shipping_total; }
    public function get_fees()              { return $this->fees; }
    public function get_billing_first_name() { return 'John'; }
    public function get_billing_last_name()  { return 'Doe'; }
    public function get_billing_email()      { return 'john@example.com'; }
    public function get_billing_phone()      { return '5511999999999'; }
    public function get_billing_postcode()   { return '01001000'; }
    public function get_billing_address_1()  { return 'Rua Teste, 123'; }
    public function get_billing_address_2()  { return 'Apt 1'; }
    public function get_meta( $key )         { return ''; }
}

class WC_Order_Item {
    public $quantity = 1;
    public $total    = 0.0;
    public $name     = 'Item Name';

    public function get_quantity() { return $this->quantity; }
    public function get_total()    { return $this->total; }
    public function get_name()     { return $this->name; }
}

class WC_Order_Item_Fee {
    public $total = 0.0;
    public $name  = 'Fee Name';

    public function get_total() { return $this->total; }
    public function get_name()  { return $this->name; }
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
    public $form_fields = array();
    public $settings    = array();

    public function init_form_fields() {}
    public function init_settings() {}
    public function get_option( $key, $default = null ) {
        $settings = array(
            'enabled'    => 'yes',
            'handle'     => 'test_handle',
            'send_items' => 'yes',
            'debug'      => 'no',
        );
        return $settings[ $key ] ?? $default;
    }
    public function is_available()             { return true; }
    public function get_return_url( $order )    { return 'http://example.com/return'; }
}

require_once dirname( __DIR__ ) . '/includes/class-wc-gateway-infinitepay.php';

/**
 * Tests for WC_Gateway_InfinitePay_HPOS::build_items().
 */
class Test_Gateway_Items extends \PHPUnit\Framework\TestCase {

    /**
     * Gateway instance.
     *
     * @var WC_Gateway_InfinitePay_HPOS
     */
    private $gateway;

    /**
     * Reflection method for build_items (private).
     *
     * @var ReflectionMethod
     */
    private $build_items;

    protected function setUp(): void {
        $this->gateway = new WC_Gateway_InfinitePay_HPOS();
        $ref = new ReflectionClass( $this->gateway );
        $this->build_items = $ref->getMethod( 'build_items' );
        $this->build_items->setAccessible( true );
    }

    /**
     * Consolidated item when send_items is disabled.
     */
    public function test_consolidated_item_when_send_items_disabled(): void {
        $this->gateway->send_items = false;

        $order        = new WC_Order();
        $order->total = 150.00;

        $items = $this->build_items->invoke( $this->gateway, $order );

        $this->assertCount( 1, $items );
        $this->assertSame( 15000, $items[0]['price'] );
        $this->assertSame( 1, $items[0]['quantity'] );
        $this->assertStringContainsString( '#123', $items[0]['description'] );
    }

    /**
     * Detailed product items with correct unit prices.
     */
    public function test_detailed_product_items(): void {
        $this->gateway->send_items = true;

        $order        = new WC_Order();
        $order->total = 100.00;

        $item       = new WC_Order_Item();
        $item->quantity = 2;
        $item->total    = 100.00; // 50.00 each = 5000 cents.
        $item->name     = 'Widget';
        $order->items   = array( $item );

        $items = $this->build_items->invoke( $this->gateway, $order );

        $this->assertCount( 1, $items );
        $this->assertSame( 2, $items[0]['quantity'] );
        $this->assertSame( 5000, $items[0]['price'] );
        $this->assertSame( 'Widget', $items[0]['description'] );
    }

    /**
     * Shipping item is included when shipping total > 0.
     */
    public function test_shipping_item_included(): void {
        $this->gateway->send_items = true;

        $order                = new WC_Order();
        $order->total         = 120.00;
        $order->shipping_total = 20.00;

        $item            = new WC_Order_Item();
        $item->quantity  = 1;
        $item->total     = 100.00;
        $item->name      = 'Product';
        $order->items    = array( $item );

        $items = $this->build_items->invoke( $this->gateway, $order );

        $this->assertCount( 2, $items );
        $this->assertSame( 'Product', $items[0]['description'] );
        $this->assertSame( 'Frete', $items[1]['description'] );
        $this->assertSame( 2000, $items[1]['price'] );
    }

    /**
     * Fee items are included.
     */
    public function test_fee_items_included(): void {
        $this->gateway->send_items = true;

        $order        = new WC_Order();
        $order->total = 105.00;

        $item           = new WC_Order_Item();
        $item->quantity = 1;
        $item->total    = 100.00;
        $item->name     = 'Product';
        $order->items   = array( $item );

        $fee        = new WC_Order_Item_Fee();
        $fee->total = 5.00;
        $fee->name  = 'Service Fee';
        $order->fees = array( $fee );

        $items = $this->build_items->invoke( $this->gateway, $order );

        $this->assertCount( 2, $items );
        $this->assertSame( 'Service Fee', $items[1]['description'] );
        $this->assertSame( 500, $items[1]['price'] );
    }

    /**
     * Rounding adjustment is applied to the last item.
     */
    public function test_rounding_adjustment(): void {
        $this->gateway->send_items = true;

        $order        = new WC_Order();
        $order->total = 10.00; // 1000 cents.

        // 10.00 / 3 = 3.333... -> 333 cents each -> 999 total -> diff +1.
        $item           = new WC_Order_Item();
        $item->quantity = 3;
        $item->total    = 10.00;
        $item->name     = 'Rounding Item';
        $order->items   = array( $item );

        $items = $this->build_items->invoke( $this->gateway, $order );

        // Total must match exactly 1000 cents.
        $total_cents = 0;
        foreach ( $items as $i ) {
            $total_cents += $i['price'] * $i['quantity'];
        }
        $this->assertSame( 1000, $total_cents );
    }

    /**
     * Fallback to consolidated when no items exist.
     */
    public function test_fallback_to_consolidated_on_empty_items(): void {
        $this->gateway->send_items = true;

        $order        = new WC_Order();
        $order->total = 50.00;
        $order->items = array();

        $items = $this->build_items->invoke( $this->gateway, $order );

        $this->assertCount( 1, $items );
        $this->assertSame( 5000, $items[0]['price'] );
        $this->assertSame( 1, $items[0]['quantity'] );
    }

    /**
     * Description is truncated to MAX_DESCRIPTION_LENGTH.
     */
    public function test_description_truncated(): void {
        $this->gateway->send_items = true;

        $order        = new WC_Order();
        $order->total = 10.00;

        $item           = new WC_Order_Item();
        $item->quantity = 1;
        $item->total    = 10.00;
        $item->name     = str_repeat( 'A', 300 ); // 300 chars.
        $order->items   = array( $item );

        $items = $this->build_items->invoke( $this->gateway, $order );

        $this->assertSame( 200, mb_strlen( $items[0]['description'] ) );
    }
}
