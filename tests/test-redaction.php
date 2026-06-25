<?php
/**
 * Unit tests for PII redaction in logs (v5.5.0).
 *
 * @package InfinitePay\WooCommerce\Tests
 */

use PHPUnit\Framework\TestCase;

class Test_Redaction extends TestCase {

    /** @var WC_Gateway_InfinitePay_HPOS */
    private $gw;

    /** @var ReflectionMethod */
    private $redact;

    /** @var ReflectionMethod */
    private $redact_json;

    protected function setUp(): void {
        $ref          = new ReflectionClass( 'WC_Gateway_InfinitePay_HPOS' );
        $this->gw     = $ref->newInstanceWithoutConstructor();
        $this->redact = $ref->getMethod( 'redact_payload' );
        $this->redact->setAccessible( true );
        $this->redact_json = $ref->getMethod( 'redact_json_string' );
        $this->redact_json->setAccessible( true );
    }

    public function test_customer_pii_is_redacted(): void {
        $payload = array(
            'handle'    => 'mystore',
            'order_nsu' => '123',
            'customer'  => array(
                'name'         => 'Caio Fabio',
                'email'        => 'caio@example.com',
                'phone_number' => '+5511999998888',
                'document'     => '12345678900',
            ),
        );

        $r = $this->redact->invoke( $this->gw, $payload );

        $this->assertSame( '***', $r['customer']['name'] );
        $this->assertSame( '***', $r['customer']['email'] );
        $this->assertSame( '***', $r['customer']['phone_number'] );
        $this->assertSame( '***', $r['customer']['document'] );
        // Non-PII preserved.
        $this->assertSame( 'mystore', $r['handle'] );
        $this->assertSame( '123', $r['order_nsu'] );
    }

    public function test_address_pii_is_redacted(): void {
        $payload = array( 'address' => array( 'cep' => '04101300', 'number' => '10', 'complement' => 'Apto 2' ) );

        $r = $this->redact->invoke( $this->gw, $payload );

        $this->assertSame( '***', $r['address']['cep'] );
        $this->assertSame( '***', $r['address']['number'] );
        $this->assertSame( '***', $r['address']['complement'] );
    }

    public function test_items_are_preserved(): void {
        $payload = array( 'items' => array( array( 'quantity' => 1, 'price' => 1000, 'description' => 'Produto X' ) ) );

        $r = $this->redact->invoke( $this->gw, $payload );

        $this->assertSame( 'Produto X', $r['items'][0]['description'] );
        $this->assertSame( 1000, $r['items'][0]['price'] );
    }

    public function test_top_level_flat_pii_is_redacted(): void {
        $payload = array( 'email' => 'flat@example.com', 'cep' => '01001000', 'order_nsu' => '9' );

        $r = $this->redact->invoke( $this->gw, $payload );

        $this->assertSame( '***', $r['email'] );
        $this->assertSame( '***', $r['cep'] );
        $this->assertSame( '9', $r['order_nsu'] );
    }

    public function test_redact_json_string_masks_pii(): void {
        $out = $this->redact_json->invoke( $this->gw, json_encode( array( 'customer' => array( 'email' => 'x@y.com' ) ) ) );

        $this->assertStringContainsString( '***', $out );
        $this->assertStringNotContainsString( 'x@y.com', $out );
    }

    public function test_redact_json_string_passes_through_non_json(): void {
        $this->assertSame( 'plain error text', $this->redact_json->invoke( $this->gw, 'plain error text' ) );
    }
}
