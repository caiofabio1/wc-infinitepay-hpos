<?php
/**
 * Plugin Name:       WooCommerce InfinitePay Gateway
 * Plugin URI:        https://infinitepay.io/
 * Description:       Aceite Cartão e Pix via InfinitePay. Checkout link seguro, webhook REST com validação, retry inteligente e compatibilidade total com HPOS e Blocks Checkout.
 * Version:           5.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            InfinitePay WooCommerce
 * Author URI:        https://infinitepay.io/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wc-infinitepay-hpos
 * Domain Path:       /languages
 * WC requires at least: 7.0
 * WC tested up to:   9.6
 *
 * @package InfinitePay\WooCommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'INFINITEPAY_WC_VERSION', '5.0.0' );
define( 'INFINITEPAY_WC_FILE', __FILE__ );
define( 'INFINITEPAY_WC_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Declara compatibilidade com HPOS e Cart/Checkout Blocks.
 */
add_action( 'before_woocommerce_init', 'infinitepay_wc_declare_compatibility' );

function infinitepay_wc_declare_compatibility() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            __FILE__,
            true
        );
    }
}

/**
 * Inicializa o gateway após o WooCommerce carregar.
 */
add_action( 'plugins_loaded', 'infinitepay_wc_init', 0 );

function infinitepay_wc_init() {
    if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Payment_Gateway' ) ) {
        add_action( 'admin_notices', 'infinitepay_wc_missing_notice' );
        return;
    }

    require_once INFINITEPAY_WC_PATH . 'includes/class-wc-gateway-infinitepay.php';
    require_once INFINITEPAY_WC_PATH . 'includes/class-infinitepay-webhook.php';

    // Registra o gateway.
    add_filter( 'woocommerce_payment_gateways', 'infinitepay_wc_add_gateway' );

    // Registra endpoint REST do webhook.
    add_action( 'rest_api_init', array( 'InfinitePay_Webhook', 'register_routes' ) );
}

function infinitepay_wc_missing_notice() {
    printf(
        '<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
        esc_html__( 'InfinitePay:', 'wc-infinitepay-hpos' ),
        esc_html__( 'Este plugin requer o WooCommerce ativo.', 'wc-infinitepay-hpos' )
    );
}

function infinitepay_wc_add_gateway( $methods ) {
    $methods[] = 'WC_Gateway_InfinitePay_HPOS';
    return $methods;
}

/**
 * Registra integração com WooCommerce Blocks Checkout.
 */
add_action( 'woocommerce_blocks_loaded', 'infinitepay_wc_blocks_init' );

function infinitepay_wc_blocks_init() {
    if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        return;
    }

    require_once INFINITEPAY_WC_PATH . 'includes/class-infinitepay-blocks.php';

    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        'infinitepay_wc_register_blocks_method'
    );
}

function infinitepay_wc_register_blocks_method( \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry ) {
    $registry->register( new InfinitePay_Blocks_Integration() );
}

/**
 * Link de configurações na lista de plugins.
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'infinitepay_wc_action_links' );

function infinitepay_wc_action_links( $links ) {
    $url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=infinitepay_hpos' );
    array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( $url ), __( 'Configurações', 'wc-infinitepay-hpos' ) ) );
    return $links;
}

/**
 * Carrega traduções do plugin.
 */
add_action( 'init', 'infinitepay_wc_load_textdomain' );

function infinitepay_wc_load_textdomain() {
    load_plugin_textdomain( 'wc-infinitepay-hpos', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
