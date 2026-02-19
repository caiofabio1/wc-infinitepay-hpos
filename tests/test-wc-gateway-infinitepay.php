<?php

use PHPUnit\Framework\TestCase;

class Test_WC_Gateway_InfinitePay extends TestCase {

    protected $gateway;

    protected function setUp(): void {
        parent::setUp();
        // Reset globals
        global $wc_notices, $wp_remote_post_mock, $wc_get_order_mock;
        $wc_notices = array();
        $wp_remote_post_mock = null;
        $wc_get_order_mock = null;

        // Instantiate gateway
        $this->gateway = new WC_Gateway_InfinitePay_HPOS();
    }

    protected function tearDown(): void {
        parent::tearDown();
        Mockery::close();
    }

    public function test_process_payment_success() {
        // Mock Order
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_id')->andReturn(123);
        $order->shouldReceive('get_return_url')->andReturn('https://store.com/checkout/order-received/123/');
        $order->shouldReceive('get_total')->andReturn(100.00);
        $order->shouldReceive('get_shipping_total')->andReturn(10.00);
        $order->shouldReceive('get_items')->andReturn(array());
        $order->shouldReceive('get_fees')->andReturn(array());
        $order->shouldReceive('get_billing_first_name')->andReturn('John');
        $order->shouldReceive('get_billing_last_name')->andReturn('Doe');
        $order->shouldReceive('get_billing_email')->andReturn('john@example.com');
        $order->shouldReceive('get_billing_phone')->andReturn('11999999999');
        $order->shouldReceive('get_billing_postcode')->andReturn('01001000');
        $order->shouldReceive('get_meta')->with('_billing_number')->andReturn('123');
        $order->shouldReceive('get_billing_address_2')->andReturn('Apt 1');

        // Expect updates
        $order->shouldReceive('update_status')->with('on-hold', Mockery::type('string'))->once();
        $order->shouldReceive('update_meta_data')->with('_infinitepay_handle', '')->once();
        $order->shouldReceive('update_meta_data')->with('_infinitepay_pending', 'yes')->once();
        $order->shouldReceive('update_meta_data')->with('_infinitepay_checkout_url', 'https://pay.infinitepay.io/checkout/abc')->once();
        $order->shouldReceive('save')->once();

        global $wc_get_order_mock;
        $wc_get_order_mock = function( $id ) use ( $order ) {
            return $order;
        };

        // Mock API Response
        global $wp_remote_post_mock;
        $wp_remote_post_mock = function( $url, $args ) {
            return array(
                'response' => array( 'code' => 201 ),
                'body'     => json_encode( array( 'url' => 'https://pay.infinitepay.io/checkout/abc' ) )
            );
        };

        // Mock Cart
        $cart = Mockery::mock('WC_Cart');
        $cart->shouldReceive('empty_cart')->once();

        // Inject Cart into WC() global
        $wc_global = WC();
        $wc_global->cart = $cart;

        // Run
        $result = $this->gateway->process_payment( 123 );

        // Assert
        $this->assertEquals( 'success', $result['result'] );
        $this->assertEquals( 'https://pay.infinitepay.io/checkout/abc', $result['redirect'] );
    }

    public function test_process_payment_order_not_found() {
        global $wc_get_order_mock;
        $wc_get_order_mock = function( $id ) {
            return false;
        };

        $result = $this->gateway->process_payment( 999 );

        $this->assertEquals( 'failure', $result['result'] );

        global $wc_notices;
        $this->assertNotEmpty( $wc_notices );
        $this->assertEquals( 'Pedido não encontrado.', $wc_notices[0]['message'] );
    }

    public function test_process_payment_api_error() {
        // Mock Order
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_id')->andReturn(123);
        $order->shouldReceive('get_return_url')->andReturn('https://store.com/return');
        $order->shouldReceive('get_total')->andReturn(50.00);
        $order->shouldReceive('get_items')->andReturn(array());
        $order->shouldReceive('get_shipping_total')->andReturn(0);
        $order->shouldReceive('get_fees')->andReturn(array());

        // Customer data stubs to avoid strict mock errors
        $order->shouldReceive('get_billing_first_name')->andReturn('');
        $order->shouldReceive('get_billing_last_name')->andReturn('');
        $order->shouldReceive('get_billing_email')->andReturn('');
        $order->shouldReceive('get_billing_phone')->andReturn('');
        $order->shouldReceive('get_billing_postcode')->andReturn('');
        $order->shouldReceive('get_meta')->andReturn('');
        $order->shouldReceive('get_billing_address_1')->andReturn('');
        $order->shouldReceive('get_billing_address_2')->andReturn('');

        // Expect error handling
        $order->shouldReceive('add_order_note')->with(Mockery::pattern('/Erro InfinitePay: HTTP 400/'))->once();

        global $wc_get_order_mock;
        $wc_get_order_mock = function( $id ) use ( $order ) {
            return $order;
        };

        // Mock API Error
        global $wp_remote_post_mock;
        $wp_remote_post_mock = function( $url, $args ) {
            return array(
                'response' => array( 'code' => 400 ),
                'body'     => 'Bad Request'
            );
        };

        $result = $this->gateway->process_payment( 123 );

        $this->assertEquals( 'failure', $result['result'] );

        global $wc_notices;
        $this->assertNotEmpty( $wc_notices );
        $this->assertEquals( 'Erro ao processar pagamento. Tente novamente.', $wc_notices[0]['message'] );
    }

    public function test_process_payment_wp_error() {
         // Mock Order
         $order = Mockery::mock('WC_Order');
         $order->shouldReceive('get_id')->andReturn(123);
         $order->shouldReceive('get_return_url')->andReturn('https://store.com/return');
         $order->shouldReceive('get_total')->andReturn(50.00);
         $order->shouldReceive('get_items')->andReturn(array());
         $order->shouldReceive('get_shipping_total')->andReturn(0);
         $order->shouldReceive('get_fees')->andReturn(array());

         // Customer data stubs
         $order->shouldReceive('get_billing_first_name')->andReturn('');
         $order->shouldReceive('get_billing_last_name')->andReturn('');
         $order->shouldReceive('get_billing_email')->andReturn('');
         $order->shouldReceive('get_billing_phone')->andReturn('');
         $order->shouldReceive('get_billing_postcode')->andReturn('');
         $order->shouldReceive('get_meta')->andReturn('');
         $order->shouldReceive('get_billing_address_1')->andReturn('');
         $order->shouldReceive('get_billing_address_2')->andReturn('');

         // Expect error handling
         $order->shouldReceive('add_order_note')->with(Mockery::pattern('/Erro InfinitePay: cURL error 28/'))->once();

         global $wc_get_order_mock;
         $wc_get_order_mock = function( $id ) use ( $order ) {
             return $order;
         };

         // Mock WP Error
         global $wp_remote_post_mock;
         $wp_remote_post_mock = function( $url, $args ) {
             return new WP_Error( 'http_request_failed', 'cURL error 28' );
         };

         $result = $this->gateway->process_payment( 123 );

         $this->assertEquals( 'failure', $result['result'] );
    }
}
