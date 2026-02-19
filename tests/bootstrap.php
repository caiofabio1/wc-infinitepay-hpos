<?php
/**
 * Bootstrap the plugin unit testing environment.
 */

// Define constants expected by the plugin.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', sys_get_temp_dir() . '/wordpress/' );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
    define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}

// Load the Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Load test stubs
require_once __DIR__ . '/stubs.php';

// Initialize WP_Mock.
WP_Mock::setUsePatchwork( true );
WP_Mock::bootstrap();
