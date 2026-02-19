<?php

use PHPUnit\Framework\TestCase;

class Test_InfinitePay_Webhook_Rate_Limit extends TestCase {

    private $request;

    public function setUp(): void {
        parent::setUp();
        // Reset transients
        global $wp_transients;
        $wp_transients = [];

        // Mock request
        $this->request = new WP_REST_Request();
    }

    public function test_rate_limit_exceeded() {
        // Set IP
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $key = 'ip_wh_rl_' . md5( '127.0.0.1' );

        // Set transient count to 30
        global $wp_transients;
        $wp_transients[ $key ] = 30;

        // Call handle
        $response = InfinitePay_Webhook::handle( $this->request );

        // Assert response
        $this->assertEquals( 429, $response->get_status() );
        $data = $response->get_data();
        $this->assertEquals( 'rate_limit', $data['error'] );
    }

    public function test_rate_limit_increment() {
        // Set IP
        $_SERVER['REMOTE_ADDR'] = '127.0.0.2';
        $key = 'ip_wh_rl_' . md5( '127.0.0.2' );

        // Set transient count to 29
        global $wp_transients;
        $wp_transients[ $key ] = 29;

        // Call handle (will fail later due to missing body/secret/etc, but check transient first)
        $response = InfinitePay_Webhook::handle( $this->request );

        // Assert transient incremented
        $this->assertEquals( 30, $wp_transients[ $key ] );

        // Since we didn't mock body/secret/headers properly for success, it should fail with 401 or something else, NOT 429
        // Specifically, validate_secret checks headers. We didn't set headers.
        $this->assertNotEquals( 429, $response->get_status() );
    }

    public function test_rate_limit_ok() {
        // Set IP
        $_SERVER['REMOTE_ADDR'] = '127.0.0.3';

        // Call handle
        $response = InfinitePay_Webhook::handle( $this->request );

        // Assert not 429
        $this->assertNotEquals( 429, $response->get_status() );
    }
}
