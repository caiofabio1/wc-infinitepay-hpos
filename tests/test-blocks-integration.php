<?php
/**
 * Unit tests for InfinitePay_Blocks_Integration.
 *
 * @package InfinitePay\WooCommerce\Tests
 */

namespace Automattic\WooCommerce\Blocks\Payments\Integrations {
    /**
     * Mock the base class from WooCommerce Blocks.
     */
    if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        abstract class AbstractPaymentMethodType {
            /**
             * Settings array.
             * @var array
             */
            protected $settings = array();

            /**
             * Mock get_setting.
             * @param string $key
             * @param mixed $default
             * @return mixed
             */
            public function get_setting( $key, $default = null ) {
                return $this->settings[ $key ] ?? $default;
            }
        }
    }
}

namespace {
    // Define constants if not present.
    if ( ! defined( 'ABSPATH' ) ) {
        define( 'ABSPATH', '/' );
    }
    if ( ! defined( 'INFINITEPAY_WC_PATH' ) ) {
        define( 'INFINITEPAY_WC_PATH', dirname( __DIR__ ) . '/' );
    }
    if ( ! defined( 'INFINITEPAY_WC_VERSION' ) ) {
        define( 'INFINITEPAY_WC_VERSION', '5.0.0' );
    }
    if ( ! defined( 'INFINITEPAY_WC_FILE' ) ) {
        define( 'INFINITEPAY_WC_FILE', INFINITEPAY_WC_PATH . 'wc-infinitepay-hpos.php' );
    }

    // Mock functions if not present.
    if ( ! function_exists( 'get_option' ) ) {
        function get_option( $option, $default = false ) {
            global $mock_options;
            return $mock_options[ $option ] ?? $default;
        }
    }
    if ( ! function_exists( 'wp_register_script' ) ) {
        function wp_register_script( $handle, $src, $deps = array(), $ver = false, $in_footer = false ) {
            return true;
        }
    }
    if ( ! function_exists( 'plugins_url' ) ) {
        function plugins_url( $path, $plugin ) {
            return 'https://example.com/' . $path;
        }
    }
    if ( ! function_exists( 'wp_localize_script' ) ) {
        function wp_localize_script( $handle, $object_name, $l10n ) {
            return true;
        }
    }
    if ( ! function_exists( '__' ) ) {
        function __( $text, $domain = 'default' ) {
            return $text;
        }
    }

    // Mock class if not present.
    if ( ! class_exists( 'WC_Gateway_InfinitePay_HPOS' ) ) {
        class WC_Gateway_InfinitePay_HPOS {
            const ID = 'infinitepay_hpos';
        }
    }

    require_once dirname( __DIR__ ) . '/includes/class-infinitepay-blocks.php';

    /**
     * Tests for InfinitePay_Blocks_Integration.
     */
    class Test_Blocks_Integration extends \PHPUnit\Framework\TestCase {

        /**
         * Integration instance.
         *
         * @var InfinitePay_Blocks_Integration
         */
        private $integration;

        /**
         * Setup mocks and constants.
         */
        public function setUp(): void {
            global $mock_options;
            $mock_options = array();
            $this->integration = new InfinitePay_Blocks_Integration();
        }

        /**
         * Test initialize() loads settings.
         */
        public function test_initialize_loads_settings(): void {
            global $mock_options;
            $mock_options['woocommerce_infinitepay_hpos_settings'] = array(
                'enabled' => 'yes',
                'handle'  => 'test_handle',
            );

            $this->integration->initialize();

            $this->assertSame( 'yes', $this->integration->get_setting( 'enabled' ) );
            $this->assertSame( 'test_handle', $this->integration->get_setting( 'handle' ) );
        }

        /**
         * Test is_active() scenarios.
         */
        public function test_is_active(): void {
            global $mock_options;

            // Scenario 1: Active and valid.
            $mock_options['woocommerce_infinitepay_hpos_settings'] = array(
                'enabled' => 'yes',
                'handle'  => 'test_handle',
            );
            $this->integration->initialize();
            $this->assertTrue( $this->integration->is_active() );

            // Scenario 2: Disabled.
            $mock_options['woocommerce_infinitepay_hpos_settings'] = array(
                'enabled' => 'no',
                'handle'  => 'test_handle',
            );
            $this->integration->initialize();
            $this->assertFalse( $this->integration->is_active() );

            // Scenario 3: Missing handle.
            $mock_options['woocommerce_infinitepay_hpos_settings'] = array(
                'enabled' => 'yes',
                'handle'  => '',
            );
            $this->integration->initialize();
            $this->assertFalse( $this->integration->is_active() );
        }

        /**
         * Test script handle registration.
         */
        public function test_get_payment_method_script_handles(): void {
            $handles = $this->integration->get_payment_method_script_handles();
            $this->assertIsArray( $handles );
            $this->assertContains( 'wc-infinitepay-blocks', $handles );
        }

        /**
         * Test admin script handle registration.
         */
        public function test_get_payment_method_script_handles_for_admin(): void {
            $handles = $this->integration->get_payment_method_script_handles_for_admin();
            $this->assertSame( array( 'wc-infinitepay-blocks' ), $handles );
        }

        /**
         * Test payment method data structure.
         */
        public function test_get_payment_method_data(): void {
            global $mock_options;

            // With settings.
            $mock_options['woocommerce_infinitepay_hpos_settings'] = array(
                'title'       => 'Custom Title',
                'description' => 'Custom Desc',
            );
            $this->integration->initialize();

            $data = $this->integration->get_payment_method_data();
            $this->assertSame( 'Custom Title', $data['title'] );
            $this->assertSame( 'Custom Desc', $data['description'] );

            // Defaults.
            $mock_options['woocommerce_infinitepay_hpos_settings'] = array();
            $this->integration->initialize();

            $data = $this->integration->get_payment_method_data();
            $this->assertSame( 'Cartão/Pix (InfinitePay)', $data['title'] );
        }
    }
}
