<?php
/**
 * Unit tests for webhook secret validation (fail-closed, v5.4.0+).
 *
 * @package InfinitePay\WooCommerce\Tests
 */

use PHPUnit\Framework\TestCase;

class Test_Webhook_Secret extends TestCase {

    /**
     * Invokes the private static InfinitePay_Webhook::validate_secret().
     */
    private function validate( string $configured_secret, ?string $header_value, string $header_name = 'X-Webhook-Secret' ): bool {
        $gw_ref = new ReflectionClass( 'WC_Gateway_InfinitePay_HPOS' );
        $gw     = $gw_ref->newInstanceWithoutConstructor();
        $gw->webhook_secret = $configured_secret;

        $req = new WP_REST_Request();
        if ( null !== $header_value ) {
            $req->set_header( $header_name, $header_value );
        }

        $wh_ref = new ReflectionClass( 'InfinitePay_Webhook' );
        $m      = $wh_ref->getMethod( 'validate_secret' );
        $m->setAccessible( true );

        return (bool) $m->invoke( null, $req, $gw );
    }

    public function test_fail_closed_when_no_secret_configured(): void {
        // v5.5.0: an unconfigured secret must REJECT (fail-closed), not accept.
        $this->assertFalse( $this->validate( '', 'anything' ) );
        $this->assertFalse( $this->validate( '', null ) );
    }

    public function test_matching_secret_header_accepted(): void {
        $this->assertTrue( $this->validate( 's3cr3t-key', 's3cr3t-key' ) );
    }

    public function test_wrong_secret_rejected(): void {
        $this->assertFalse( $this->validate( 's3cr3t-key', 'wrong-key' ) );
    }

    public function test_bearer_authorization_header_accepted(): void {
        $this->assertTrue( $this->validate( 's3cr3t-key', 'Bearer s3cr3t-key', 'Authorization' ) );
    }

    public function test_missing_header_rejected(): void {
        $this->assertFalse( $this->validate( 's3cr3t-key', null ) );
    }
}
