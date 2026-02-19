<?php
/**
 * Tests for WC_Gateway_InfinitePay_HPOS.
 */

namespace InfinitePay\Tests;

use PHPUnit\Framework\TestCase;
use WC_Gateway_InfinitePay_HPOS;
use WC_Order;
use WC_Order_Item;
use ReflectionClass;
use WP_Error;

class WC_Gateway_InfinitePay_HPOS_Test extends TestCase {

    private function get_gateway() {
        $gateway = new WC_Gateway_InfinitePay_HPOS();
        $gateway->handle = 'test-handle';
        $gateway->enabled = 'yes';
        return $gateway;
    }

    private function call_private_method( $object, $method_name, $args = array() ) {
        $reflection = new ReflectionClass( get_class( $object ) );
        $method = $reflection->getMethod( $method_name );
        $method->setAccessible( true );
        return $method->invokeArgs( $object, $args );
    }

    public function test_constructor() {
        $gateway = new WC_Gateway_InfinitePay_HPOS();
        $this->assertEquals( 'infinitepay_hpos', $gateway->id, 'Gateway ID should be infinitepay_hpos' );
        $this->assertFalse( $gateway->has_fields, 'Gateway should not have fields' );
    }

    public function test_is_available() {
        $gateway = new WC_Gateway_InfinitePay_HPOS();

        $gateway->enabled = 'no';
        $gateway->handle = 'test';
        $this->assertFalse( $gateway->is_available(), 'Gateway should not be available if disabled' );

        $gateway->enabled = 'yes';
        $gateway->handle = '';
        $this->assertFalse( $gateway->is_available(), 'Gateway should not be available if handle is empty' );

        $gateway->enabled = 'yes';
        $gateway->handle = 'test';
        $this->assertTrue( $gateway->is_available(), 'Gateway should be available if enabled and has handle' );
    }

    public function test_format_phone_e164_br() {
        $gateway = $this->get_gateway();

        $cases = array(
            '11999999999'   => '+5511999999999',
            '5511999999999' => '+5511999999999',
            '+5511999999999'=> '+5511999999999',
            '(11) 99999-9999' => '+5511999999999',
            '' => '',
        );

        foreach ( $cases as $input => $expected ) {
            $actual = $this->call_private_method( $gateway, 'format_phone_e164_br', array( $input ) );
            $this->assertEquals( $expected, $actual, "Phone $input should be formatted to $expected" );
        }
    }

    public function test_build_items_consolidated() {
        $gateway = $this->get_gateway();
        $gateway->send_items = false;

        $order = new WC_Order();
        $order->id = 456;
        $order->total = 150.00;

        $items = $this->call_private_method( $gateway, 'build_items', array( $order ) );

        $this->assertCount( 1, $items );
        $this->assertEquals( 1, $items[0]['quantity'] );
        $this->assertEquals( 15000, $items[0]['price'] );
        $this->assertStringContainsString( '#456', $items[0]['description'] );
    }

    public function test_process_refund() {
        $gateway = $this->get_gateway();
        $result = $gateway->process_refund( 123, 50.00 );

        $this->assertInstanceOf( WP_Error::class, $result, 'Refund should return WP_Error' );
        $this->assertEquals( 'infinitepay_no_refund_api', $result->code );
    }

    public function test_build_items_detailed() {
        $gateway = $this->get_gateway();
        $gateway->send_items = true;

        $order = new WC_Order();
        $order->total = 100.00;
        $order->shipping_total = 0;
        $order->items = array(
            new WC_Order_Item( 'Product A', 2, 40.00 ),
            new WC_Order_Item( 'Product B', 1, 60.00 ),
        );

        $items = $this->call_private_method( $gateway, 'build_items', array( $order ) );

        $this->assertCount( 2, $items );

        $this->assertEquals( 2, $items[0]['quantity'] );
        $this->assertEquals( 2000, $items[0]['price'] );
        $this->assertEquals( 'Product A', $items[0]['description'] );

        $this->assertEquals( 1, $items[1]['quantity'] );
        $this->assertEquals( 6000, $items[1]['price'] );
        $this->assertEquals( 'Product B', $items[1]['description'] );
    }

    public function test_build_payload() {
        $gateway = $this->get_gateway();
        $gateway->handle = 'my-handle';

        $order = new WC_Order();
        $order->id = 789;
        $order->billing_first_name = 'Jane';
        $order->billing_last_name = 'Doe';
        $order->billing_email = 'jane@example.com';
        $order->billing_phone = '11988888888';

        $payload = $this->call_private_method( $gateway, 'build_payload', array( $order ) );

        $this->assertEquals( 'my-handle', $payload['handle'] );
        $this->assertEquals( '789', $payload['order_nsu'] );
        $this->assertEquals( 'Jane Doe', $payload['customer']['name'] );
        $this->assertEquals( 'jane@example.com', $payload['customer']['email'] );
        $this->assertEquals( '+5511988888888', $payload['customer']['phone_number'] );
    }

    public function test_payment_check_success() {
        global $wp_remote_post_response;
        $gateway = $this->get_gateway();

        $wp_remote_post_response = array(
            'response' => array( 'code' => 200 ),
            'body' => json_encode( array( 'success' => true, 'paid' => true ) ),
        );

        $result = $gateway->payment_check( '123', 'abc', 'slug' );

        $this->assertTrue( $result['ok'] );
        $this->assertTrue( $result['paid'] );
    }

    public function test_payment_check_failure() {
        global $wp_remote_post_response;
        $gateway = $this->get_gateway();

        $wp_remote_post_response = array(
            'response' => array( 'code' => 400 ),
            'body' => json_encode( array( 'success' => false ) ),
        );

        $result = $gateway->payment_check( '123', 'abc', 'slug' );

        $this->assertFalse( $result['ok'] );
        $this->assertFalse( $result['paid'] );
    }
}
