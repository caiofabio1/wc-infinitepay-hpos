<?php

use WP_Mock\Tools\TestCase;

class WebhookSecretTest extends TestCase {

    public function setUp(): void {
        WP_Mock::setUp();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
        Mockery::close();
    }

    private function mock_dependencies( $webhook_secret = 'my_secret_key' ) {
        // Mock sanitization
        WP_Mock::userFunction( 'sanitize_text_field', array(
            'return' => function( $text ) { return $text; },
        ) );

        // Mock rate limit check (always pass)
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        WP_Mock::userFunction( 'get_transient', array(
            'return' => 0,
        ) );
        WP_Mock::userFunction( 'set_transient', array(
            'return' => true,
        ) );

        // Mock Gateway
        $gateway = Mockery::mock( 'WC_Gateway_InfinitePay_HPOS' );
        $gateway->webhook_secret = $webhook_secret;
        $gateway->shouldReceive( 'log' )->zeroOrMoreTimes();

        // Mock WC() -> payment_gateways() -> payment_gateways()
        $payment_gateways = Mockery::mock( 'WC_Payment_Gateways' );
        $payment_gateways->shouldReceive( 'payment_gateways' )
            ->andReturn( array( 'infinitepay_hpos' => $gateway ) );

        $wc = Mockery::mock( 'WooCommerce' );
        $wc->shouldReceive( 'payment_gateways' )->andReturn( $payment_gateways );

        WP_Mock::userFunction( 'WC', array(
            'return' => $wc,
        ) );

        return $gateway;
    }

    public function test_handle_rejects_missing_secret() {
        $this->mock_dependencies( 'valid_secret' );

        $request = Mockery::mock( 'WP_REST_Request' );
        $request->shouldReceive( 'get_header' )->with( 'X-Webhook-Secret' )->andReturn( null );
        $request->shouldReceive( 'get_header' )->with( 'Authorization' )->andReturn( null );

        $response = InfinitePay_Webhook::handle( $request );

        $this->assertEquals( 401, $response->get_status() );
        $this->assertEquals( 'unauthorized', $response->get_data()['error'] );
    }

    public function test_handle_rejects_invalid_secret() {
        $this->mock_dependencies( 'valid_secret' );

        $request = Mockery::mock( 'WP_REST_Request' );
        $request->shouldReceive( 'get_header' )->with( 'X-Webhook-Secret' )->andReturn( 'wrong_secret' );

        $response = InfinitePay_Webhook::handle( $request );

        $this->assertEquals( 401, $response->get_status() );
        $this->assertEquals( 'unauthorized', $response->get_data()['error'] );
    }

    public function test_handle_accepts_valid_secret() {
        $secret = 'valid_secret_123';
        $this->mock_dependencies( $secret );

        $request = Mockery::mock( 'WP_REST_Request' );
        $request->shouldReceive( 'get_header' )->with( 'X-Webhook-Secret' )->andReturn( $secret );
        $request->shouldReceive( 'get_json_params' )->andReturn( [] ); // Mock body parsing

        $response = InfinitePay_Webhook::handle( $request );

        $this->assertNotEquals( 401, $response->get_status() );
    }

    public function test_handle_accepts_bearer_token() {
        $secret = 'valid_secret_abc';
        $this->mock_dependencies( $secret );

        $request = Mockery::mock( 'WP_REST_Request' );
        $request->shouldReceive( 'get_header' )->with( 'X-Webhook-Secret' )->andReturn( null );
        $request->shouldReceive( 'get_header' )->with( 'Authorization' )->andReturn( 'Bearer ' . $secret );
        $request->shouldReceive( 'get_json_params' )->andReturn( [] );

        $response = InfinitePay_Webhook::handle( $request );

        $this->assertNotEquals( 401, $response->get_status() );
    }
}
