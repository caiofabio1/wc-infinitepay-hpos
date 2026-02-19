<?php
/**
 * Asset metadata for WC Blocks integration.
 *
 * @package InfinitePay\WooCommerce
 */

return array(
    'dependencies' => array(
        'wp-element',
        'wp-html-entities',
        'wc-blocks-registry',
        'wc-settings',
    ),
    'version'      => defined( 'INFINITEPAY_WC_VERSION' ) ? INFINITEPAY_WC_VERSION : '5.0.0',
);
