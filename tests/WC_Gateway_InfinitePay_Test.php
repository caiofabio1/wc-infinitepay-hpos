<?php

use WP_Mock\Tools\TestCase;

class Test_WC_Gateway_InfinitePay extends TestCase {

    public function setUp(): void {
        WP_Mock::setUp();

        WP_Mock::userFunction( 'rest_url', [
            'return' => 'http://example.com/wp-json/wc-infinitepay/v1/webhook',
        ] );

        WP_Mock::userFunction( 'sanitize_text_field', [
            'return' => function( $val ) { return $val; },
        ] );

        WP_Mock::userFunction( 'is_admin', [
            'return' => false,
        ] );

        WP_Mock::userFunction( '__', [
            'return' => function( $val ) { return $val; },
        ] );

        WP_Mock::userFunction( 'esc_html__', [
            'return' => function( $val ) { return $val; },
        ] );

        WP_Mock::userFunction( 'wp_json_encode', [
            'return' => function( $val ) { return json_encode( $val ); },
        ] );
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
    }

    public function test_thankyou_page_already_confirmed() {
        $order_id = 123;
        $order = Mockery::mock( 'WC_Order' );

        WP_Mock::userFunction( 'wc_get_order', [
            'args' => [ $order_id ],
            'return' => $order,
        ] );

        $order->shouldReceive( 'get_payment_method' )->andReturn( 'infinitepay_hpos' );
        $order->shouldReceive( 'get_meta' )->with( '_infinitepay_pending' )->andReturn( 'no' );
        // The constructor calls get_option which we mocked in bootstrap to return default (empty string)
        // so enabled is 'no'.

        $gateway = new WC_Gateway_InfinitePay_HPOS();

        ob_start();
        $gateway->thankyou_page( $order_id );
        $output = ob_get_clean();

        $this->assertStringContainsString( 'Pagamento confirmado!', $output );
    }

    public function test_thankyou_page_cached_success() {
        $order_id = 123;
        $tx = 'tx_123';
        $slug = 'slug_123';

        $_GET['transaction_nsu'] = $tx;
        $_GET['slug'] = $slug;

        $order = Mockery::mock( 'WC_Order' );

        WP_Mock::userFunction( 'wc_get_order', [
            'args' => [ $order_id ],
            'return' => $order,
        ] );

        $order->shouldReceive( 'get_payment_method' )->andReturn( 'infinitepay_hpos' );
        $order->shouldReceive( 'get_meta' )->with( '_infinitepay_pending' )->andReturn( 'yes' );
        $order->shouldReceive( 'get_id' )->andReturn( $order_id );

        WP_Mock::userFunction( 'wp_unslash', [
            'return' => function( $val ) { return $val; },
        ] );

        $cache_key = 'ip_check_' . $order_id . '_' . md5( $tx . $slug );

        WP_Mock::userFunction( 'get_transient', [
            'args' => [ $cache_key ],
            'return' => [ 'ok' => true, 'paid' => true ],
        ] );

        // Logger might be called
        WP_Mock::userFunction( 'wc_get_logger', [
            'return' => Mockery::mock( 'WC_Logger' )->shouldIgnoreMissing(),
        ] );

        $gateway = new WC_Gateway_InfinitePay_HPOS();

        ob_start();
        $gateway->thankyou_page( $order_id );
        $output = ob_get_clean();

        $this->assertStringContainsString( 'Pagamento confirmado!', $output );

        unset( $_GET['transaction_nsu'] );
        unset( $_GET['slug'] );
    }

    public function test_thankyou_page_api_success() {
        $order_id = 123;
        $tx = 'tx_123';
        $slug = 'slug_123';

        $_GET['transaction_nsu'] = $tx;
        $_GET['slug'] = $slug;

        $order = Mockery::mock( 'WC_Order' );

        WP_Mock::userFunction( 'wc_get_order', [
            'args' => [ $order_id ],
            'return' => $order,
        ] );

        $order->shouldReceive( 'get_payment_method' )->andReturn( 'infinitepay_hpos' );
        $order->shouldReceive( 'get_meta' )->with( '_infinitepay_pending' )->andReturn( 'yes' );
        $order->shouldReceive( 'get_id' )->andReturn( $order_id );

        WP_Mock::userFunction( 'wp_unslash', [
            'return' => function( $val ) { return $val; },
        ] );

        $cache_key = 'ip_check_' . $order_id . '_' . md5( $tx . $slug );

        WP_Mock::userFunction( 'get_transient', [
            'args' => [ $cache_key ],
            'return' => false,
        ] );

        WP_Mock::userFunction( 'wp_remote_post', [
            'return' => [ 'response' => [ 'code' => 200 ], 'body' => json_encode( [ 'success' => true, 'paid' => true ] ) ],
        ] );

        WP_Mock::userFunction( 'is_wp_error', [
            'return' => false,
        ] );

        WP_Mock::userFunction( 'wp_remote_retrieve_response_code', [
            'return' => 200,
        ] );

        WP_Mock::userFunction( 'wp_remote_retrieve_body', [
            'return' => json_encode( [ 'success' => true, 'paid' => true ] ),
        ] );

        WP_Mock::userFunction( 'set_transient', [
            'args' => [ $cache_key, [ 'ok' => true, 'paid' => true, 'data' => [ 'success' => true, 'paid' => true ] ], 300 ],
            'return' => true,
        ] );

        $order->shouldReceive( 'payment_complete' )->with( $tx )->once();
        $order->shouldReceive( 'delete_meta_data' )->with( '_infinitepay_pending' )->once();
        $order->shouldReceive( 'update_meta_data' )->with( '_infinitepay_slug', $slug )->once();
        $order->shouldReceive( 'save' )->once();

        $gateway = new WC_Gateway_InfinitePay_HPOS();

        ob_start();
        $gateway->thankyou_page( $order_id );
        $output = ob_get_clean();

        $this->assertStringContainsString( 'Pagamento confirmado!', $output );

        unset( $_GET['transaction_nsu'] );
        unset( $_GET['slug'] );
    }

    public function test_thankyou_page_api_failure() {
        $order_id = 123;
        $tx = 'tx_123';
        $slug = 'slug_123';

        $_GET['transaction_nsu'] = $tx;
        $_GET['slug'] = $slug;

        $order = Mockery::mock( 'WC_Order' );

        WP_Mock::userFunction( 'wc_get_order', [
            'args' => [ $order_id ],
            'return' => $order,
        ] );

        $order->shouldReceive( 'get_payment_method' )->andReturn( 'infinitepay_hpos' );
        $order->shouldReceive( 'get_meta' )->with( '_infinitepay_pending' )->andReturn( 'yes' );
        $order->shouldReceive( 'get_id' )->andReturn( $order_id );

        WP_Mock::userFunction( 'wp_unslash', [
            'return' => function( $val ) { return $val; },
        ] );

        $cache_key = 'ip_check_' . $order_id . '_' . md5( $tx . $slug );

        WP_Mock::userFunction( 'get_transient', [
            'args' => [ $cache_key ],
            'return' => false,
        ] );

        WP_Mock::userFunction( 'wp_remote_post', [
            'return' => [ 'response' => [ 'code' => 400 ], 'body' => 'Error' ],
        ] );

        WP_Mock::userFunction( 'wc_get_logger', [
            'return' => Mockery::mock( 'WC_Logger' )->shouldIgnoreMissing(),
        ] );

        WP_Mock::userFunction( 'is_wp_error', [
            'return' => false,
        ] );

        WP_Mock::userFunction( 'wp_remote_retrieve_response_code', [
            'return' => 400,
        ] );

        WP_Mock::userFunction( 'set_transient', [
            'args' => [ $cache_key, [ 'ok' => false, 'paid' => false ], 300 ],
            'return' => true,
        ] );

        $gateway = new WC_Gateway_InfinitePay_HPOS();

        ob_start();
        $gateway->thankyou_page( $order_id );
        $output = ob_get_clean();

        $this->assertStringContainsString( 'Confirmando pagamento...', $output );

        unset( $_GET['transaction_nsu'] );
        unset( $_GET['slug'] );
    }

    public function test_thankyou_page_pending() {
        $order_id = 123;

        $order = Mockery::mock( 'WC_Order' );

        WP_Mock::userFunction( 'wc_get_order', [
            'args' => [ $order_id ],
            'return' => $order,
        ] );

        $order->shouldReceive( 'get_payment_method' )->andReturn( 'infinitepay_hpos' );
        $order->shouldReceive( 'get_meta' )->with( '_infinitepay_pending' )->andReturn( 'yes' );

        // No GET params

        $gateway = new WC_Gateway_InfinitePay_HPOS();

        ob_start();
        $gateway->thankyou_page( $order_id );
        $output = ob_get_clean();

        $this->assertStringContainsString( 'Confirmando pagamento...', $output );
    }
}
