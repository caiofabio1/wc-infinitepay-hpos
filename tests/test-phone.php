<?php
/**
 * Unit tests for format_phone_e164_br().
 *
 * @package InfinitePay\WooCommerce\Tests
 */

use PHPUnit\Framework\TestCase;

class Test_Phone extends TestCase {

    /** @var WC_Gateway_InfinitePay_HPOS */
    private $gw;

    /** @var ReflectionMethod */
    private $fmt;

    protected function setUp(): void {
        $ref       = new ReflectionClass( 'WC_Gateway_InfinitePay_HPOS' );
        $this->gw  = $ref->newInstanceWithoutConstructor();
        $this->fmt = $ref->getMethod( 'format_phone_e164_br' );
        $this->fmt->setAccessible( true );
    }

    private function fmt( string $raw ): string {
        return $this->fmt->invoke( $this->gw, $raw );
    }

    public function test_adds_br_country_code_to_local_number(): void {
        $this->assertSame( '+5511999998888', $this->fmt( '(11) 99999-8888' ) );
    }

    public function test_keeps_existing_country_code(): void {
        $this->assertSame( '+5511999998888', $this->fmt( '5511999998888' ) );
    }

    public function test_strips_non_digits(): void {
        $this->assertSame( '+5511988887777', $this->fmt( '+55 (11) 98888-7777' ) );
    }

    public function test_empty_or_invalid_returns_empty(): void {
        $this->assertSame( '', $this->fmt( '' ) );
        $this->assertSame( '', $this->fmt( 'sem numero' ) );
    }
}
