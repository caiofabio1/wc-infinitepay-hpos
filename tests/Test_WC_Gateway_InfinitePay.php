<?php

use WP_Mock\Tools\TestCase;

class Test_WC_Gateway_InfinitePay extends TestCase {

	protected $gateway;

	public function setUp(): void {
		WP_Mock::setUp();

		// Mock WordPress functions used in constructor
		WP_Mock::userFunction( 'is_admin', [
			'return' => false,
		] );

		WP_Mock::userFunction( 'sanitize_text_field', [
			'return' => function ( $str ) {
				return $str;
			},
		] );

		WP_Mock::userFunction( '__', [
			'return' => function ( $str, $domain ) {
				return $str;
			},
		] );

		WP_Mock::userFunction( 'esc_html__', [
			'return' => function ( $str, $domain ) {
				return $str;
			},
		] );

		WP_Mock::userFunction( 'esc_html', [
			'return' => function ( $str ) {
				return $str;
			},
		] );

		WP_Mock::userFunction( 'esc_url', [
			'return' => function ( $str ) {
				return $str;
			},
		] );

		WP_Mock::userFunction( 'rest_url', [
			'return' => 'https://example.com/wp-json/wc-infinitepay/v1/webhook',
		] );

		WP_Mock::userFunction( 'admin_url', [
			'return' => 'https://example.com/wp-admin/',
		] );

		// Load the class file if not loaded
		if ( ! class_exists( 'WC_Gateway_InfinitePay_HPOS' ) ) {
			require_once dirname( __DIR__ ) . '/includes/class-wc-gateway-infinitepay.php';
		}

		$this->gateway = new WC_Gateway_InfinitePay_HPOS();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
	}

	public function test_payment_check_success() {
		$order_nsu       = '123';
		$transaction_nsu = 'abc';
		$slug            = 'test-slug';

		$api_response = [
			'success' => true,
			'paid'    => true,
		];

		$expected_payload = [
			'handle'          => '',
			'order_nsu'       => $order_nsu,
			'transaction_nsu' => $transaction_nsu,
			'slug'            => $slug,
		];
		$encoded_payload = json_encode( $expected_payload );

		WP_Mock::userFunction( 'wp_json_encode', [
			'args'   => [ $expected_payload ],
			'return' => $encoded_payload,
		] );

		WP_Mock::userFunction( 'wp_remote_post', [
			'args'   => [
				'https://api.infinitepay.io/invoices/public/checkout/payment_check',
				[
					'timeout' => 10,
					'headers' => [
						'Accept'       => 'application/json',
						'Content-Type' => 'application/json',
					],
					'body'    => $encoded_payload,
				],
			],
			'return' => [
				'response' => [ 'code' => 200 ],
				'body'     => json_encode( $api_response ),
			],
		] );

		WP_Mock::userFunction( 'is_wp_error', [
			'return' => false,
		] );

		WP_Mock::userFunction( 'wp_remote_retrieve_response_code', [
			'return' => 200,
		] );

		WP_Mock::userFunction( 'wp_remote_retrieve_body', [
			'return' => json_encode( $api_response ),
		] );

		$result = $this->gateway->payment_check( $order_nsu, $transaction_nsu, $slug );

		$this->assertTrue( $result['ok'] );
		$this->assertTrue( $result['paid'] );
		$this->assertEquals( $api_response, $result['data'] );
	}

	public function test_payment_check_not_paid() {
		$order_nsu       = '123';
		$transaction_nsu = 'abc';
		$slug            = 'test-slug';

		$api_response = [
			'success' => true,
			'paid'    => false,
		];

		WP_Mock::userFunction( 'wp_remote_post', [
			'return' => [
				'response' => [ 'code' => 200 ],
				'body'     => json_encode( $api_response ),
			],
		] );

		WP_Mock::userFunction( 'is_wp_error', [
			'return' => false,
		] );

		WP_Mock::userFunction( 'wp_remote_retrieve_response_code', [
			'return' => 200,
		] );

		WP_Mock::userFunction( 'wp_remote_retrieve_body', [
			'return' => json_encode( $api_response ),
		] );

		WP_Mock::userFunction( 'wp_json_encode', [
			'return' => '{}',
		] );

		$result = $this->gateway->payment_check( $order_nsu, $transaction_nsu, $slug );

		$this->assertTrue( $result['ok'] );
		$this->assertFalse( $result['paid'] );
	}

	public function test_payment_check_api_error() {
		$order_nsu       = '123';
		$transaction_nsu = 'abc';
		$slug            = 'test-slug';

		// Mock WP_Error object using Mockery
		$error_mock = \Mockery::mock( 'WP_Error' );
		$error_mock->shouldReceive( 'get_error_message' )->andReturn( 'Connection failed' );

		WP_Mock::userFunction( 'wp_remote_post', [
			'return' => $error_mock,
		] );

		WP_Mock::userFunction( 'is_wp_error', [
			'return' => true,
		] );

		WP_Mock::userFunction( 'wp_json_encode', [
			'return' => '{}',
		] );

		$result = $this->gateway->payment_check( $order_nsu, $transaction_nsu, $slug );

		$this->assertFalse( $result['ok'] );
		$this->assertFalse( $result['paid'] );
	}

	public function test_payment_check_http_error() {
		$order_nsu       = '123';
		$transaction_nsu = 'abc';
		$slug            = 'test-slug';

		WP_Mock::userFunction( 'wp_remote_post', [
			'return' => [ 'response' => [ 'code' => 500 ] ],
		] );

		WP_Mock::userFunction( 'is_wp_error', [
			'return' => false,
		] );

		WP_Mock::userFunction( 'wp_remote_retrieve_response_code', [
			'return' => 500,
		] );

		WP_Mock::userFunction( 'wp_json_encode', [
			'return' => '{}',
		] );

		$result = $this->gateway->payment_check( $order_nsu, $transaction_nsu, $slug );

		$this->assertFalse( $result['ok'] );
		$this->assertFalse( $result['paid'] );
	}

	public function test_payment_check_malformed_response() {
		$order_nsu       = '123';
		$transaction_nsu = 'abc';
		$slug            = 'test-slug';

		// Malformed JSON or unexpected structure
		$body = 'invalid json';

		WP_Mock::userFunction( 'wp_remote_post', [
			'return' => [
				'response' => [ 'code' => 200 ],
				'body'     => $body,
			],
		] );

		WP_Mock::userFunction( 'is_wp_error', [
			'return' => false,
		] );

		WP_Mock::userFunction( 'wp_remote_retrieve_response_code', [
			'return' => 200,
		] );

		WP_Mock::userFunction( 'wp_remote_retrieve_body', [
			'return' => $body,
		] );

		WP_Mock::userFunction( 'wp_json_encode', [
			'return' => '{}',
		] );

		$result = $this->gateway->payment_check( $order_nsu, $transaction_nsu, $slug );

		$this->assertTrue( $result['ok'] );
		$this->assertFalse( $result['paid'] );
		$this->assertNull( $result['data'] ); // json_decode returns null for invalid json
	}
}
