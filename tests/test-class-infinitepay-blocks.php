<?php
/**
 * Testes para InfinitePay_Blocks_Integration.
 */

use Brain\Monkey;
use Brain\Monkey\Functions;

class Test_InfinitePay_Blocks_Integration extends PHPUnit\Framework\TestCase {

    /**
     * Setup.
     */
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    /**
     * Teardown.
     */
    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test initialize() loads settings correctly.
     */
    public function test_initialize_loads_settings() {
        $settings = array( 'enabled' => 'yes', 'handle' => 'test_handle' );

        Functions\when( 'get_option' )
            ->justReturn( $settings );

        $integration = new InfinitePay_Blocks_Integration();
        $integration->initialize();

        $this->assertEquals( 'yes', $integration->get_setting( 'enabled' ) );
        $this->assertEquals( 'test_handle', $integration->get_setting( 'handle' ) );
    }

    /**
     * Test is_active() logic.
     */
    public function test_is_active_returns_correct_status() {
        $integration = new InfinitePay_Blocks_Integration();

        // Case 1: Enabled and Handle set -> Active
        Functions\when( 'get_option' )->justReturn( array( 'enabled' => 'yes', 'handle' => 'my-handle' ) );
        $integration->initialize();
        $this->assertTrue( $integration->is_active(), 'Should be active when enabled and handle is present.' );

        // Case 2: Disabled -> Inactive
        Functions\when( 'get_option' )->justReturn( array( 'enabled' => 'no', 'handle' => 'my-handle' ) );
        $integration->initialize();
        $this->assertFalse( $integration->is_active(), 'Should be inactive when disabled.' );

        // Case 3: Enabled but no Handle -> Inactive
        Functions\when( 'get_option' )->justReturn( array( 'enabled' => 'yes', 'handle' => '' ) );
        $integration->initialize();
        $this->assertFalse( $integration->is_active(), 'Should be inactive when handle is missing.' );
    }

    /**
     * Test get_payment_method_script_handles().
     */
    public function test_get_payment_method_script_handles_registers_script() {
        Functions\expect( 'plugins_url' )
            ->once()
            ->with( 'assets/js/blocks.js', INFINITEPAY_WC_FILE )
            ->andReturn( 'http://example.com/assets/js/blocks.js' );

        Functions\expect( 'wp_register_script' )
            ->once()
            ->with(
                'wc-infinitepay-blocks',
                'http://example.com/assets/js/blocks.js',
                \Mockery::type( 'array' ),
                \Mockery::type( 'string' ),
                true
            );

        // Mock file_exists for the asset file.
        // Since we can't easily mock file_exists with Brain Monkey (it's a core PHP function),
        // we rely on the fact that INFINITEPAY_WC_PATH points to the real path where assets exist,
        // or ensure our test environment has the file.
        // In this case, the file exists in the repo, so file_exists should return true.

        $integration = new InfinitePay_Blocks_Integration();
        $handles = $integration->get_payment_method_script_handles();

        $this->assertContains( 'wc-infinitepay-blocks', $handles );
    }

    /**
     * Test get_payment_method_data().
     */
    public function test_get_payment_method_data_returns_correct_structure() {
        Functions\when( '__' )
            ->alias( function( $text ) {
                return $text;
            } );

        Functions\when( 'get_option' )
            ->justReturn( array(
                'title'       => 'Custom Title',
                'description' => 'Custom Description',
            ) );

        $integration = new InfinitePay_Blocks_Integration();
        $integration->initialize();

        $data = $integration->get_payment_method_data();

        $this->assertArrayHasKey( 'title', $data );
        $this->assertEquals( 'Custom Title', $data['title'] );

        $this->assertArrayHasKey( 'description', $data );
        $this->assertEquals( 'Custom Description', $data['description'] );

        $this->assertArrayHasKey( 'supports', $data );
        $this->assertContains( 'products', $data['supports'] );
    }
}
