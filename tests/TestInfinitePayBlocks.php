<?php

use PHPUnit\Framework\TestCase;

class TestInfinitePayBlocks extends TestCase {

    protected function tearDown(): void {
        global $wp_options;
        $wp_options = [];
        parent::tearDown();
    }

    public function test_is_active_returns_true_when_enabled_and_handle_set() {
        global $wp_options;
        $wp_options['woocommerce_infinitepay_hpos_settings'] = [
            'enabled' => 'yes',
            'handle'  => 'test_handle'
        ];

        $instance = new InfinitePay_Blocks_Integration();
        $instance->initialize();

        $this->assertTrue( $instance->is_active() );
    }

    public function test_is_active_returns_false_when_disabled() {
        global $wp_options;
        $wp_options['woocommerce_infinitepay_hpos_settings'] = [
            'enabled' => 'no',
            'handle'  => 'test_handle'
        ];

        $instance = new InfinitePay_Blocks_Integration();
        $instance->initialize();

        $this->assertFalse( $instance->is_active() );
    }

    public function test_is_active_returns_false_when_handle_is_missing() {
        global $wp_options;
        $wp_options['woocommerce_infinitepay_hpos_settings'] = [
            'enabled' => 'yes',
            'handle'  => ''
        ];

        $instance = new InfinitePay_Blocks_Integration();
        $instance->initialize();

        $this->assertFalse( $instance->is_active() );
    }

    public function test_is_active_returns_false_when_settings_are_empty() {
        // No settings defined in wp_options, so get_option returns default array()
        $instance = new InfinitePay_Blocks_Integration();
        $instance->initialize();

        $this->assertFalse( $instance->is_active() );
    }
}
