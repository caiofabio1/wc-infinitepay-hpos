<?php
/**
 * Testes de segurança para o gateway InfinitePay.
 *
 * @package InfinitePay\WooCommerce\Tests
 */

// Define constants and mocks (reusing from test-gateway-items.php logic).
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '/' );
}
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
if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( $text, $domain = 'default' ) { return $text; }
}
if ( ! function_exists( 'wc_get_logger' ) ) {
    function wc_get_logger() {
        return new class {
            public function log( $level, $message, $context = array() ) {}
        };
    }
}

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
    class WC_Payment_Gateway {
        public $id;
        public function init_form_fields() {}
        public function init_settings() {}
        public function get_option( $key, $default = null ) { return $default; }
    }
}

require_once dirname( __DIR__ ) . '/includes/class-wc-gateway-infinitepay.php';

class Test_Gateway_Security extends \PHPUnit\Framework\TestCase {

    private $gateway;
    private $redact_payload;
    private $redact_json_string;

    public function setUp(): void {
        $this->gateway = new WC_Gateway_InfinitePay_HPOS();
        $ref = new ReflectionClass( $this->gateway );

        $this->redact_payload = $ref->getMethod( 'redact_payload' );
        $this->redact_payload->setAccessible( true );

        $this->redact_json_string = $ref->getMethod( 'redact_json_string' );
        $this->redact_json_string->setAccessible( true );
    }

    /**
     * Test redaction of nested customer fields.
     */
    public function test_redact_customer_fields(): void {
        $payload = array(
            'customer' => array(
                'name'         => 'John Doe',
                'email'        => 'john@example.com',
                'phone_number' => '+5511999999999',
                'document'     => '12345678901',
                'other'        => 'public'
            )
        );

        $redacted = $this->redact_payload->invoke( $this->gateway, $payload );

        $this->assertSame( '***', $redacted['customer']['name'] );
        $this->assertSame( '***', $redacted['customer']['email'] );
        $this->assertSame( '***', $redacted['customer']['phone_number'] );
        $this->assertSame( '***', $redacted['customer']['document'] );
        $this->assertSame( 'public', $redacted['customer']['other'] );
    }

    /**
     * Test redaction of nested address fields.
     */
    public function test_redact_address_fields(): void {
        $payload = array(
            'address' => array(
                'cep'          => '01001000',
                'number'       => '123',
                'complement'   => 'Apt 1',
                'street'       => 'Rua Teste',
                'neighborhood' => 'Centro',
                'city'         => 'São Paulo',
                'state'        => 'SP',
                'country'      => 'BR'
            )
        );

        $redacted = $this->redact_payload->invoke( $this->gateway, $payload );

        $this->assertSame( '***', $redacted['address']['cep'] );
        $this->assertSame( '***', $redacted['address']['number'] );
        $this->assertSame( '***', $redacted['address']['complement'] );
        $this->assertSame( '***', $redacted['address']['street'] );
        $this->assertSame( '***', $redacted['address']['neighborhood'] );
        $this->assertSame( '***', $redacted['address']['city'] );
        $this->assertSame( '***', $redacted['address']['state'] );
        $this->assertSame( 'BR', $redacted['address']['country'] );
    }

    /**
     * Test redaction of top-level PII fields.
     */
    public function test_redact_top_level_fields(): void {
        $payload = array(
            'name'  => 'John Doe',
            'email' => 'john@example.com',
            'city'  => 'São Paulo',
            'ok'    => true
        );

        $redacted = $this->redact_payload->invoke( $this->gateway, $payload );

        $this->assertSame( '***', $redacted['name'] );
        $this->assertSame( '***', $redacted['email'] );
        $this->assertSame( '***', $redacted['city'] );
        $this->assertSame( true, $redacted['ok'] );
    }

    /**
     * Test redaction of JSON strings.
     */
    public function test_redact_json_string(): void {
        $data = array(
            'customer' => array( 'name' => 'John Doe' ),
            'status'   => 'error'
        );
        $json = json_encode( $data );

        $redacted_json = $this->redact_json_string->invoke( $this->gateway, $json );
        $redacted_data = json_decode( $redacted_json, true );

        $this->assertSame( '***', $redacted_data['customer']['name'] );
        $this->assertSame( 'error', $redacted_data['status'] );
    }

    /**
     * Test non-JSON string handling in redact_json_string.
     */
    public function test_redact_non_json_string(): void {
        $text = 'Plain text error message';
        $redacted = $this->redact_json_string->invoke( $this->gateway, $text );
        $this->assertSame( $text, $redacted );
    }
}
