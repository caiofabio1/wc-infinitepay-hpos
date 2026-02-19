<?php

use WP_Mock\Tools\TestCase;

class InfinitePay_Webhook_Test extends TestCase {

    public function setUp(): void {
        WP_Mock::setUp();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
        \Mockery::close();
    }

    public function test_handle_rate_limit_exceeded() {
        WP_Mock::userFunction( 'sanitize_text_field', [ 'return' => function( $v ) { return $v; } ] );
        WP_Mock::userFunction( 'get_transient', [ 'return' => 30 ] );

        // Setup Gateway Mock for logging
        $gateway = \Mockery::mock( 'WC_Gateway_InfinitePay_HPOS' );
        $gateway->shouldReceive( 'log' )->with( \Mockery::type( 'string' ), 'warning' )->once();

        // Mock WC()
        $payment_gateways = \Mockery::mock( 'stdClass' );
        $payment_gateways->shouldReceive( 'payment_gateways' )->andReturn( [ 'infinitepay_hpos' => $gateway ] );
        $wc = \Mockery::mock( 'stdClass' );
        $wc->shouldReceive( 'payment_gateways' )->andReturn( $payment_gateways );
        WP_Mock::userFunction( 'WC', [ 'return' => $wc ] );

        $request = \Mockery::mock( 'WP_REST_Request' );
        $response = InfinitePay_Webhook::handle( $request );

        $this->assertEquals( 429, $response->status );
        $this->assertEquals( 'rate_limit', $response->data['error'] );
    }

    public function test_handle_gateway_not_found() {
        WP_Mock::userFunction( 'sanitize_text_field', [ 'return' => function( $v ) { return $v; } ] );
        WP_Mock::userFunction( 'get_transient', [ 'return' => 0 ] );
        WP_Mock::userFunction( 'set_transient', [ 'return' => true ] );

        // Mock WC() returning no infinitepay gateway
        $payment_gateways = \Mockery::mock( 'stdClass' );
        $payment_gateways->shouldReceive( 'payment_gateways' )->andReturn( [] );
        $wc = \Mockery::mock( 'stdClass' );
        $wc->shouldReceive( 'payment_gateways' )->andReturn( $payment_gateways );
        WP_Mock::userFunction( 'WC', [ 'return' => $wc ] );

        $request = \Mockery::mock( 'WP_REST_Request' );
        $response = InfinitePay_Webhook::handle( $request );

        $this->assertEquals( 500, $response->status );
        $this->assertEquals( 'gateway_not_found', $response->data['error'] );
    }

    public function test_handle_invalid_secret() {
        WP_Mock::userFunction( 'sanitize_text_field', [ 'return' => function( $v ) { return $v; } ] );
        WP_Mock::userFunction( 'get_transient', [ 'return' => 0 ] );
        WP_Mock::userFunction( 'set_transient', [ 'return' => true ] );

        $gateway = \Mockery::mock( 'WC_Gateway_InfinitePay_HPOS' );
        $gateway->webhook_secret = 'valid_secret';
        $gateway->shouldReceive( 'log' )->with( \Mockery::type( 'string' ), 'error' )->once();

        $payment_gateways = \Mockery::mock( 'stdClass' );
        $payment_gateways->shouldReceive( 'payment_gateways' )->andReturn( [ 'infinitepay_hpos' => $gateway ] );
        $wc = \Mockery::mock( 'stdClass' );
        $wc->shouldReceive( 'payment_gateways' )->andReturn( $payment_gateways );
        WP_Mock::userFunction( 'WC', [ 'return' => $wc ] );

        $request = \Mockery::mock( 'WP_REST_Request' );
        $request->shouldReceive( 'get_header' )->with( 'X-Webhook-Secret' )->andReturn( 'invalid_secret' );
        $request->shouldReceive( 'get_header' )->with( 'Authorization' )->andReturn( null );

        $response = InfinitePay_Webhook::handle( $request );

        $this->assertEquals( 401, $response->status );
        $this->assertEquals( 'unauthorized', $response->data['error'] );
    }

    public function test_handle_invalid_body() {
        WP_Mock::userFunction( 'sanitize_text_field', [ 'return' => function( $v ) { return $v; } ] );
        WP_Mock::userFunction( 'get_transient', [ 'return' => 0 ] );
        WP_Mock::userFunction( 'set_transient', [ 'return' => true ] );

        $gateway = \Mockery::mock( 'WC_Gateway_InfinitePay_HPOS' );
        $gateway->webhook_secret = 'valid_secret';
        $gateway->shouldReceive( 'log' )->with( \Mockery::type( 'string' ), 'error' )->once();

        $payment_gateways = \Mockery::mock( 'stdClass' );
        $payment_gateways->shouldReceive( 'payment_gateways' )->andReturn( [ 'infinitepay_hpos' => $gateway ] );
        $wc = \Mockery::mock( 'stdClass' );
        $wc->shouldReceive( 'payment_gateways' )->andReturn( $payment_gateways );
        WP_Mock::userFunction( 'WC', [ 'return' => $wc ] );

        $request = \Mockery::mock( 'WP_REST_Request' );
        $request->shouldReceive( 'get_header' )->with( 'X-Webhook-Secret' )->andReturn( 'valid_secret' );
        $request->shouldReceive( 'get_json_params' )->andReturn( null );

        $response = InfinitePay_Webhook::handle( $request );

        $this->assertEquals( 400, $response->status );
        $this->assertEquals( 'invalid_body', $response->data['error'] );
    }

    public function test_handle_missing_fields() {
        WP_Mock::userFunction( 'sanitize_text_field', [ 'return' => function( $v ) { return $v; } ] );
        WP_Mock::userFunction( 'get_transient', [ 'return' => 0 ] );
        WP_Mock::userFunction( 'set_transient', [ 'return' => true ] );
        WP_Mock::userFunction( 'esc_url_raw', [ 'return' => function( $v ) { return $v; } ] );

        $gateway = \Mockery::mock( 'WC_Gateway_InfinitePay_HPOS' );
        $gateway->webhook_secret = 'valid_secret';
        $gateway->shouldReceive( 'log' )->with( \Mockery::type( 'string' ), 'warning' )->once();

        $payment_gateways = \Mockery::mock( 'stdClass' );
        $payment_gateways->shouldReceive( 'payment_gateways' )->andReturn( [ 'infinitepay_hpos' => $gateway ] );
        $wc = \Mockery::mock( 'stdClass' );
        $wc->shouldReceive( 'payment_gateways' )->andReturn( $payment_gateways );
        WP_Mock::userFunction( 'WC', [ 'return' => $wc ] );

        $request = \Mockery::mock( 'WP_REST_Request' );
        $request->shouldReceive( 'get_header' )->with( 'X-Webhook-Secret' )->andReturn( 'valid_secret' );
        $request->shouldReceive( 'get_json_params' )->andReturn( [ 'transaction_nsu' => '123' ] ); // Missing other fields

        $response = InfinitePay_Webhook::handle( $request );

        $this->assertEquals( 400, $response->status );
        $this->assertEquals( 'missing_fields', $response->data['error'] );
    }

    public function test_handle_duplicate_transaction() {
        WP_Mock::userFunction( 'sanitize_text_field', [ 'return' => function( $v ) { return $v; } ] );
        WP_Mock::userFunction( 'esc_url_raw', [ 'return' => function( $v ) { return $v; } ] );
        // First call for rate limit (false), second call for is_duplicate (true)
        WP_Mock::userFunction( 'get_transient', [
            'return_in_order' => [ 0, true ]
        ] );
        WP_Mock::userFunction( 'set_transient', [ 'return' => true ] );

        $gateway = \Mockery::mock( 'WC_Gateway_InfinitePay_HPOS' );
        $gateway->webhook_secret = 'valid_secret';
        $gateway->shouldReceive( 'log' )->with( \Mockery::type( 'string' ) )->once(); // Log "Webhook ignorado..."

        $payment_gateways = \Mockery::mock( 'stdClass' );
        $payment_gateways->shouldReceive( 'payment_gateways' )->andReturn( [ 'infinitepay_hpos' => $gateway ] );
        $wc = \Mockery::mock( 'stdClass' );
        $wc->shouldReceive( 'payment_gateways' )->andReturn( $payment_gateways );
        WP_Mock::userFunction( 'WC', [ 'return' => $wc ] );

        $request = \Mockery::mock( 'WP_REST_Request' );
        $request->shouldReceive( 'get_header' )->with( 'X-Webhook-Secret' )->andReturn( 'valid_secret' );
        $request->shouldReceive( 'get_json_params' )->andReturn( [
            'order_nsu' => '100',
            'transaction_nsu' => 'tx123',
            'invoice_slug' => 'slug',
            'receipt_url' => 'http://receipt.url'
        ] );

        $response = InfinitePay_Webhook::handle( $request );

        $this->assertEquals( 200, $response->status );
        $this->assertEquals( 'duplicate', $response->data['note'] );
    }

    public function test_handle_order_not_found() {
        WP_Mock::userFunction( 'sanitize_text_field', [ 'return' => function( $v ) { return $v; } ] );
        WP_Mock::userFunction( 'esc_url_raw', [ 'return' => function( $v ) { return $v; } ] );
        WP_Mock::userFunction( 'get_transient', [ 'return' => 0 ] );
        WP_Mock::userFunction( 'set_transient', [ 'return' => true ] );

        $gateway = \Mockery::mock( 'WC_Gateway_InfinitePay_HPOS' );
        $gateway->webhook_secret = 'valid_secret';
        $gateway->shouldReceive( 'log' )->with( \Mockery::type( 'string' ), 'error' )->once();

        $payment_gateways = \Mockery::mock( 'stdClass' );
        $payment_gateways->shouldReceive( 'payment_gateways' )->andReturn( [ 'infinitepay_hpos' => $gateway ] );
        $wc = \Mockery::mock( 'stdClass' );
        $wc->shouldReceive( 'payment_gateways' )->andReturn( $payment_gateways );
        WP_Mock::userFunction( 'WC', [ 'return' => $wc ] );

        WP_Mock::userFunction( 'wc_get_order', [ 'return' => false ] );

        $request = \Mockery::mock( 'WP_REST_Request' );
        $request->shouldReceive( 'get_header' )->with( 'X-Webhook-Secret' )->andReturn( 'valid_secret' );
        $request->shouldReceive( 'get_json_params' )->andReturn( [
            'order_nsu' => '100',
            'transaction_nsu' => 'tx123',
            'invoice_slug' => 'slug'
        ] );

        $response = InfinitePay_Webhook::handle( $request );

        $this->assertEquals( 404, $response->status );
        $this->assertEquals( 'order_not_found', $response->data['error'] );
    }

    public function test_handle_order_mismatch() {
        WP_Mock::userFunction( 'sanitize_text_field', [ 'return' => function( $v ) { return $v; } ] );
        WP_Mock::userFunction( 'esc_url_raw', [ 'return' => function( $v ) { return $v; } ] );
        WP_Mock::userFunction( 'get_transient', [ 'return' => 0 ] );
        WP_Mock::userFunction( 'set_transient', [ 'return' => true ] );

        $gateway = \Mockery::mock( 'WC_Gateway_InfinitePay_HPOS' );
        $gateway->webhook_secret = 'valid_secret';
        $gateway->shouldReceive( 'log' )->with( \Mockery::type( 'string' ), 'error' )->once();

        $payment_gateways = \Mockery::mock( 'stdClass' );
        $payment_gateways->shouldReceive( 'payment_gateways' )->andReturn( [ 'infinitepay_hpos' => $gateway ] );
        $wc = \Mockery::mock( 'stdClass' );
        $wc->shouldReceive( 'payment_gateways' )->andReturn( $payment_gateways );
        WP_Mock::userFunction( 'WC', [ 'return' => $wc ] );

        $order = \Mockery::mock( 'WC_Order' );
        $order->shouldReceive( 'get_payment_method' )->andReturn( 'other_gateway' );
        WP_Mock::userFunction( 'wc_get_order', [ 'return' => $order ] );

        $request = \Mockery::mock( 'WP_REST_Request' );
        $request->shouldReceive( 'get_header' )->with( 'X-Webhook-Secret' )->andReturn( 'valid_secret' );
        $request->shouldReceive( 'get_json_params' )->andReturn( [
            'order_nsu' => '100',
            'transaction_nsu' => 'tx123',
            'invoice_slug' => 'slug'
        ] );

        $response = InfinitePay_Webhook::handle( $request );

        $this->assertEquals( 403, $response->status );
        $this->assertEquals( 'order_mismatch', $response->data['error'] );
    }

    public function test_handle_order_already_processed() {
        WP_Mock::userFunction( 'sanitize_text_field', [ 'return' => function( $v ) { return $v; } ] );
        WP_Mock::userFunction( 'esc_url_raw', [ 'return' => function( $v ) { return $v; } ] );
        WP_Mock::userFunction( 'get_transient', [ 'return' => 0 ] );
        WP_Mock::userFunction( 'set_transient', [ 'return' => true ] );

        $gateway = \Mockery::mock( 'WC_Gateway_InfinitePay_HPOS' );
        $gateway->webhook_secret = 'valid_secret';
        $gateway->shouldReceive( 'log' )->with( \Mockery::type( 'string' ) )->once();

        $payment_gateways = \Mockery::mock( 'stdClass' );
        $payment_gateways->shouldReceive( 'payment_gateways' )->andReturn( [ 'infinitepay_hpos' => $gateway ] );
        $wc = \Mockery::mock( 'stdClass' );
        $wc->shouldReceive( 'payment_gateways' )->andReturn( $payment_gateways );
        WP_Mock::userFunction( 'WC', [ 'return' => $wc ] );

        $order = \Mockery::mock( 'WC_Order' );
        $order->shouldReceive( 'get_payment_method' )->andReturn( 'infinitepay_hpos' );
        $order->shouldReceive( 'get_meta' )->with( '_infinitepay_pending' )->andReturn( 'yes' );
        $order->shouldReceive( 'get_status' )->andReturn( 'completed' );

        WP_Mock::userFunction( 'wc_get_order', [ 'return' => $order ] );

        $request = \Mockery::mock( 'WP_REST_Request' );
        $request->shouldReceive( 'get_header' )->with( 'X-Webhook-Secret' )->andReturn( 'valid_secret' );
        $request->shouldReceive( 'get_json_params' )->andReturn( [
            'order_nsu' => '100',
            'transaction_nsu' => 'tx123',
            'invoice_slug' => 'slug'
        ] );

        $response = InfinitePay_Webhook::handle( $request );

        $this->assertEquals( 200, $response->status );
        $this->assertTrue( $response->data['success'] );
    }

    public function test_handle_success_payment() {
        WP_Mock::userFunction( 'sanitize_text_field', [ 'return' => function( $v ) { return $v; } ] );
        WP_Mock::userFunction( 'esc_url_raw', [ 'return' => function( $v ) { return $v; } ] );
        WP_Mock::userFunction( 'get_transient', [ 'return' => 0 ] );
        WP_Mock::userFunction( 'set_transient', [ 'return' => true ] );
        WP_Mock::userFunction( '__', [ 'return' => 'Translated String' ] );

        $gateway = \Mockery::mock( 'WC_Gateway_InfinitePay_HPOS' );
        $gateway->webhook_secret = 'valid_secret';
        $gateway->shouldReceive( 'log' )->with( \Mockery::type( 'string' ) )->once();

        $payment_gateways = \Mockery::mock( 'stdClass' );
        $payment_gateways->shouldReceive( 'payment_gateways' )->andReturn( [ 'infinitepay_hpos' => $gateway ] );
        $wc = \Mockery::mock( 'stdClass' );
        $wc->shouldReceive( 'payment_gateways' )->andReturn( $payment_gateways );
        WP_Mock::userFunction( 'WC', [ 'return' => $wc ] );

        $order = \Mockery::mock( 'WC_Order' );
        $order->shouldReceive( 'get_payment_method' )->andReturn( 'infinitepay_hpos' );
        $order->shouldReceive( 'get_meta' )->with( '_infinitepay_pending' )->andReturn( 'yes' );
        $order->shouldReceive( 'get_status' )->andReturn( 'pending' );

        $order->shouldReceive( 'payment_complete' )->with( 'tx123' )->once();
        $order->shouldReceive( 'update_meta_data' )->with( '_infinitepay_slug', 'slug' )->once();
        $order->shouldReceive( 'delete_meta_data' )->with( '_infinitepay_pending' )->once();
        $order->shouldReceive( 'update_meta_data' )->with( '_infinitepay_receipt_url', 'http://receipt.url' )->once();
        $order->shouldReceive( 'add_order_note' )->once();
        $order->shouldReceive( 'save' )->once();

        WP_Mock::userFunction( 'wc_get_order', [ 'return' => $order ] );

        $body = [
            'order_nsu' => '100',
            'transaction_nsu' => 'tx123',
            'invoice_slug' => 'slug',
            'receipt_url' => 'http://receipt.url'
        ];

        WP_Mock::expectAction( 'infinitepay_webhook_payment_confirmed', $order, 'tx123', $body );

        $request = \Mockery::mock( 'WP_REST_Request' );
        $request->shouldReceive( 'get_header' )->with( 'X-Webhook-Secret' )->andReturn( 'valid_secret' );
        $request->shouldReceive( 'get_json_params' )->andReturn( [
            'order_nsu' => '100',
            'transaction_nsu' => 'tx123',
            'invoice_slug' => 'slug',
            'receipt_url' => 'http://receipt.url'
        ] );

        $response = InfinitePay_Webhook::handle( $request );

        $this->assertEquals( 200, $response->status );
        $this->assertTrue( $response->data['success'] );
    }

}
