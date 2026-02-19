<?php

use PHPUnit\Framework\TestCase;

class Test_InfinitePay_Webhook extends TestCase {

    protected $gateway;

    protected function setUp(): void {
        parent::setUp();
        // Reset globals
        $GLOBALS['wp_transients'] = [];
        $GLOBALS['wc_orders'] = [];
        $GLOBALS['wc_gateways'] = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        // Setup gateway mock
        $this->gateway = new WC_Gateway_InfinitePay_HPOS();
        $this->gateway->webhook_secret = 'secret123';
        $GLOBALS['wc_gateways']['infinitepay_hpos'] = $this->gateway;
    }

    public function test_register_routes() {
        // Just ensure it doesn't crash
        InfinitePay_Webhook::register_routes();
        $this->assertTrue(true);
    }

    public function test_handle_missing_gateway() {
        $GLOBALS['wc_gateways'] = []; // No gateway
        $request = new WP_REST_Request();

        $response = InfinitePay_Webhook::handle($request);

        $this->assertEquals(500, $response->get_status());
        $this->assertEquals('gateway_not_found', $response->get_data()['error']);
    }

    public function test_handle_rate_limit_exceeded() {
        // Fill transient to limit
        $ip = '127.0.0.1';
        $key = 'ip_wh_rl_' . md5( $ip );
        $GLOBALS['wp_transients'][$key] = 30; // Limit is 30

        $request = new WP_REST_Request();
        $response = InfinitePay_Webhook::handle($request);

        $this->assertEquals(429, $response->get_status());
        $this->assertEquals('rate_limit', $response->get_data()['error']);
    }

    public function test_handle_invalid_secret() {
        $request = new WP_REST_Request();
        $request->set_header('X-Webhook-Secret', 'wrong_secret');

        $response = InfinitePay_Webhook::handle($request);

        $this->assertEquals(401, $response->get_status());
        $this->assertEquals('unauthorized', $response->get_data()['error']);
    }

    public function test_handle_missing_secret_config_allows_request() {
        $this->gateway->webhook_secret = ''; // No secret configured

        $request = new WP_REST_Request();
        // No header needed
        $request->set_json_params(['order_nsu' => '123', 'transaction_nsu' => 'tx1', 'invoice_slug' => 'slug1']);

        // Need to mock order to pass subsequent checks
        $order = new WC_Order(123);
        $order->set_payment_method('infinitepay_hpos');
        $order->update_meta_data('_infinitepay_pending', 'yes');
        $GLOBALS['wc_orders'][123] = $order;

        $response = InfinitePay_Webhook::handle($request);

        // Should succeed (200) because secret validation is bypassed
        $this->assertEquals(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
    }

    public function test_handle_valid_secret_header() {
        $request = new WP_REST_Request();
        $request->set_header('X-Webhook-Secret', 'secret123');
        $request->set_json_params(['order_nsu' => '123', 'transaction_nsu' => 'tx1', 'invoice_slug' => 'slug1']);

        $order = new WC_Order(123);
        $order->set_payment_method('infinitepay_hpos');
        $order->update_meta_data('_infinitepay_pending', 'yes');
        $GLOBALS['wc_orders'][123] = $order;

        $response = InfinitePay_Webhook::handle($request);

        $this->assertEquals(200, $response->get_status());
    }

    public function test_handle_valid_auth_bearer() {
        $request = new WP_REST_Request();
        $request->set_header('Authorization', 'Bearer secret123');
        $request->set_json_params(['order_nsu' => '123', 'transaction_nsu' => 'tx1', 'invoice_slug' => 'slug1']);

        $order = new WC_Order(123);
        $order->set_payment_method('infinitepay_hpos');
        $order->update_meta_data('_infinitepay_pending', 'yes');
        $GLOBALS['wc_orders'][123] = $order;

        $response = InfinitePay_Webhook::handle($request);

        $this->assertEquals(200, $response->get_status());
    }

    public function test_handle_invalid_body() {
        $request = new WP_REST_Request();
        $request->set_header('X-Webhook-Secret', 'secret123');
        $request->set_json_params(null); // Invalid body

        $response = InfinitePay_Webhook::handle($request);

        $this->assertEquals(400, $response->get_status());
        $this->assertEquals('invalid_body', $response->get_data()['error']);
    }

    public function test_handle_missing_fields() {
        $request = new WP_REST_Request();
        $request->set_header('X-Webhook-Secret', 'secret123');
        $request->set_json_params(['order_nsu' => '123']); // Missing tx and slug

        $response = InfinitePay_Webhook::handle($request);

        $this->assertEquals(400, $response->get_status());
        $this->assertEquals('missing_fields', $response->get_data()['error']);
    }

    public function test_handle_duplicate_transaction() {
        $tx = 'tx_dup';
        $key = 'ip_wh_tx_' . md5( $tx );
        $GLOBALS['wp_transients'][$key] = 1; // Already processed

        $request = new WP_REST_Request();
        $request->set_header('X-Webhook-Secret', 'secret123');
        $request->set_json_params(['order_nsu' => '123', 'transaction_nsu' => $tx, 'invoice_slug' => 'slug1']);

        $response = InfinitePay_Webhook::handle($request);

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals('duplicate', $response->get_data()['note']);
    }

    public function test_handle_order_not_found() {
        $request = new WP_REST_Request();
        $request->set_header('X-Webhook-Secret', 'secret123');
        $request->set_json_params(['order_nsu' => '999', 'transaction_nsu' => 'tx1', 'invoice_slug' => 'slug1']);

        $response = InfinitePay_Webhook::handle($request);

        $this->assertEquals(404, $response->get_status());
        $this->assertEquals('order_not_found', $response->get_data()['error']);
    }

    public function test_handle_order_mismatch_payment_method() {
        $order = new WC_Order(123);
        $order->set_payment_method('other_gateway');
        $GLOBALS['wc_orders'][123] = $order;

        $request = new WP_REST_Request();
        $request->set_header('X-Webhook-Secret', 'secret123');
        $request->set_json_params(['order_nsu' => '123', 'transaction_nsu' => 'tx1', 'invoice_slug' => 'slug1']);

        $response = InfinitePay_Webhook::handle($request);

        $this->assertEquals(403, $response->get_status());
        $this->assertEquals('order_mismatch', $response->get_data()['error']);
    }

    public function test_handle_order_mismatch_not_pending() {
        $order = new WC_Order(123);
        $order->set_payment_method('infinitepay_hpos');
        // Missing _infinitepay_pending meta
        // And status is not on-hold
        $order->set_status('pending');
        $GLOBALS['wc_orders'][123] = $order;

        $request = new WP_REST_Request();
        $request->set_header('X-Webhook-Secret', 'secret123');
        $request->set_json_params(['order_nsu' => '123', 'transaction_nsu' => 'tx1', 'invoice_slug' => 'slug1']);

        $response = InfinitePay_Webhook::handle($request);

        $this->assertEquals(403, $response->get_status());
        $this->assertEquals('order_mismatch', $response->get_data()['error']);
    }

    public function test_handle_already_processed() {
        $order = new WC_Order(123);
        $order->set_payment_method('infinitepay_hpos');
        $order->update_meta_data('_infinitepay_pending', 'yes');
        $order->set_status('completed'); // Already completed
        $GLOBALS['wc_orders'][123] = $order;

        $request = new WP_REST_Request();
        $request->set_header('X-Webhook-Secret', 'secret123');
        $request->set_json_params(['order_nsu' => '123', 'transaction_nsu' => 'tx1', 'invoice_slug' => 'slug1']);

        $response = InfinitePay_Webhook::handle($request);

        $this->assertEquals(200, $response->get_status());
        // Should not have processed again (no note added, status not changed by processing logic)
    }

    public function test_handle_success_processing() {
        $order = new WC_Order(123);
        $order->set_payment_method('infinitepay_hpos');
        $order->update_meta_data('_infinitepay_pending', 'yes');
        $order->set_status('on-hold');
        $GLOBALS['wc_orders'][123] = $order;

        $request = new WP_REST_Request();
        $request->set_header('X-Webhook-Secret', 'secret123');
        $request->set_json_params([
            'order_nsu' => '123',
            'transaction_nsu' => 'tx_success',
            'invoice_slug' => 'slug_success',
            'receipt_url' => 'http://receipt.url'
        ]);

        $response = InfinitePay_Webhook::handle($request);

        $this->assertEquals(200, $response->get_status());

        // Verify order updates
        $this->assertEquals('processing', $order->get_status()); // payment_complete sets to processing
        $this->assertEquals('slug_success', $order->get_meta('_infinitepay_slug'));
        $this->assertEquals('http://receipt.url', $order->get_meta('_infinitepay_receipt_url'));
        $this->assertEquals('', $order->get_meta('_infinitepay_pending')); // Deleted
    }
}
