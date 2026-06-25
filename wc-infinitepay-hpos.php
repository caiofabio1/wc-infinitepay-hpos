<?php
/**
 * Plugin Name:       WooCommerce InfinitePay (HPOS)
 * Plugin URI:        https://nraizes.com.br/
 * Description:       Gateway InfinitePay via API oficial com checkout link, webhook REST seguro, verificação inteligente e compatibilidade HPOS + Blocks Checkout.
 * Version:           5.5.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            NRaizes
 * Author URI:        https://nraizes.com.br/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wc-infinitepay-hpos
 * Domain Path:       /languages
 * WC requires at least: 7.0
 * WC tested up to:   10.8
 *
 * @package NRaizes\InfinitePay
 */

defined( 'ABSPATH' ) || exit;

define( 'INFINITEPAY_WC_VERSION', '5.5.0' );

/**
 * Polyfill para str_starts_with (PHP 8.0+).
 * Garante compatibilidade com PHP 7.4.
 */
if ( ! function_exists( 'str_starts_with' ) ) {
    function str_starts_with( string $haystack, string $needle ): bool {
        return 0 === strncmp( $haystack, $needle, strlen( $needle ) );
    }
}
define( 'INFINITEPAY_WC_FILE', __FILE__ );
define( 'INFINITEPAY_WC_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Declara compatibilidade com HPOS e Cart/Checkout Blocks.
 */
add_action( 'before_woocommerce_init', function () {
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
} );

/**
 * Inicializa o gateway após o WooCommerce carregar.
 */
add_action( 'plugins_loaded', 'infinitepay_wc_init', 0 );

function infinitepay_wc_init() {
    if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Payment_Gateway' ) ) {
        add_action( 'admin_notices', function () {
            printf(
                '<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
                esc_html__( 'InfinitePay:', 'wc-infinitepay-hpos' ),
                esc_html__( 'Este plugin requer o WooCommerce ativo.', 'wc-infinitepay-hpos' )
            );
        } );
        return;
    }

    require_once INFINITEPAY_WC_PATH . 'includes/class-wc-gateway-infinitepay.php';
    require_once INFINITEPAY_WC_PATH . 'includes/class-infinitepay-webhook.php';
    require_once INFINITEPAY_WC_PATH . 'includes/class-infinitepay-audit.php';

    // Registra o gateway.
    add_filter( 'woocommerce_payment_gateways', function ( $methods ) {
        $methods[] = 'WC_Gateway_InfinitePay_HPOS';
        return $methods;
    } );

    // Registra endpoints REST (webhook + audit).
    add_action( 'rest_api_init', array( 'InfinitePay_Webhook', 'register_routes' ) );
    add_action( 'rest_api_init', array( 'InfinitePay_Audit', 'register_routes' ) );
}

/**
 * Registra integração com WooCommerce Blocks Checkout.
 */
add_action( 'woocommerce_blocks_loaded', function () {
    if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        return;
    }

    require_once INFINITEPAY_WC_PATH . 'includes/class-infinitepay-blocks.php';

    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function ( \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry ) {
            $registry->register( new InfinitePay_Blocks_Integration() );
        }
    );
} );

/**
 * Agenda cron para cancelar pedidos on-hold expirados.
 */
register_activation_hook( __FILE__, function () {
    if ( ! wp_next_scheduled( 'infinitepay_cancel_stale_orders' ) ) {
        wp_schedule_event( time(), 'twicedaily', 'infinitepay_cancel_stale_orders' );
    }
} );

register_deactivation_hook( __FILE__, function () {
    wp_clear_scheduled_hook( 'infinitepay_cancel_stale_orders' );
} );

add_action( 'infinitepay_cancel_stale_orders', 'infinitepay_cancel_stale_orders_handler' );

/**
 * Cancela pedidos InfinitePay on-hold que ultrapassaram o prazo configurado.
 */
function infinitepay_cancel_stale_orders_handler() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    $gateways = WC()->payment_gateways()->payment_gateways();
    $gateway  = $gateways['infinitepay_hpos'] ?? null;
    if ( ! $gateway ) {
        return;
    }

    $hours = (int) $gateway->get_option( 'order_expiration', 48 );
    if ( $hours <= 0 ) {
        return;
    }

    $orders = wc_get_orders( array(
        'status'         => 'on-hold',
        'payment_method' => 'infinitepay_hpos',
        'date_created'   => '<' . strtotime( "-{$hours} hours" ),
        'meta_key'       => '_infinitepay_pending',
        'meta_value'     => 'yes',
        'limit'          => 50,
    ) );

    foreach ( $orders as $order ) {
        $order->update_status(
            'cancelled',
            sprintf(
                /* translators: %d: hours */
                __( 'Cancelado automaticamente: sem pagamento após %d horas.', 'wc-infinitepay-hpos' ),
                $hours
            )
        );
        $order->delete_meta_data( '_infinitepay_pending' );
        $order->save();

        // ALWAYS log order cancellations (critical business event).
        $gateway->log_critical( 'EXPIRED: pedido #' . $order->get_id() . ' cancelado por expiração (' . $hours . 'h). Total: ' . $order->get_total() . ' ' . $order->get_currency(), 'warning' );
    }
}

/**
 * AJAX endpoint for thank-you page auto-polling of payment status.
 *
 * Called by frontend JS every 5 seconds to check if the webhook has
 * confirmed payment. Returns JSON with { paid: bool }.
 *
 * @since 5.3.0
 */
add_action( 'wp_ajax_infinitepay_check_status', 'infinitepay_ajax_check_status' );
add_action( 'wp_ajax_nopriv_infinitepay_check_status', 'infinitepay_ajax_check_status' );

function infinitepay_ajax_check_status() {
    $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
    $nonce    = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

    if ( ! $order_id || ! wp_verify_nonce( $nonce, 'ip_check_' . $order_id ) ) {
        wp_send_json_error( 'invalid_request', 403 );
    }

    $order = wc_get_order( $order_id );
    if ( ! $order || 'infinitepay_hpos' !== $order->get_payment_method() ) {
        wp_send_json_error( 'order_not_found', 404 );
    }

    $is_paid = 'yes' !== $order->get_meta( '_infinitepay_pending' );
    $status  = $order->get_status();

    wp_send_json_success( array(
        'paid'   => $is_paid,
        'status' => $status,
    ) );
}

/**
 * Enriches the gateway description in classic checkout with card brand
 * badges, PIX icon, installment info, and redirect notice.
 *
 * @since 5.3.0
 */
add_filter( 'woocommerce_gateway_description', 'infinitepay_rich_description', 10, 2 );

function infinitepay_rich_description( $description, $payment_id ) {
    if ( 'infinitepay_hpos' !== $payment_id || is_admin() ) {
        return $description;
    }

    $badges = array(
        array( 'Visa',       '#1a1f71' ),
        array( 'Mastercard', '#eb001b' ),
        array( 'Elo',        '#00a4e0' ),
        array( 'PIX',        '#32bcad' ),
    );

    $html  = '<div class="ip-rich-desc" style="font-size:14px;line-height:1.5">';
    $html .= '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px">';
    foreach ( $badges as $b ) {
        $html .= sprintf(
            '<span style="display:inline-flex;align-items:center;padding:2px 10px;border-radius:4px;font-size:11px;font-weight:600;letter-spacing:.5px;color:%1$s;border:1px solid %1$s">%2$s</span>',
            esc_attr( $b[1] ),
            esc_html( $b[0] )
        );
    }
    $html .= '</div>';
    $html .= '<div style="font-size:13px;color:#555;margin-bottom:8px">';
    $html .= esc_html__( 'Cartão em até 12x · PIX à vista', 'wc-infinitepay-hpos' );
    $html .= '</div>';
    $html .= '<div style="display:flex;align-items:center;gap:6px;font-size:12px;color:#888;background:#f8f6f3;padding:8px 12px;border-radius:6px">';
    $html .= '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>';
    $html .= esc_html__( 'Você será redirecionado para pagamento seguro', 'wc-infinitepay-hpos' );
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

/**
 * Branded transition page — shown briefly before redirecting to InfinitePay.
 *
 * Intercepts requests with ?infinitepay-redirect=1 and renders a branded
 * interstitial page that auto-forwards to the InfinitePay checkout URL
 * after 1.5 seconds, providing a smoother checkout experience.
 *
 * @since 5.3.0
 */
add_action( 'template_redirect', 'infinitepay_transition_page' );

function infinitepay_transition_page() {
    if ( empty( $_GET['infinitepay-redirect'] ) ) {
        return;
    }

    $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
    $nonce    = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';

    if ( ! $order_id || ! wp_verify_nonce( $nonce, 'ip_redirect_' . $order_id ) ) {
        wp_safe_redirect( wc_get_checkout_url() );
        exit;
    }

    $order = wc_get_order( $order_id );
    if ( ! $order || 'infinitepay_hpos' !== $order->get_payment_method() ) {
        wp_safe_redirect( wc_get_checkout_url() );
        exit;
    }

    $checkout_url = $order->get_meta( '_infinitepay_checkout_url' );
    if ( ! $checkout_url ) {
        wp_safe_redirect( $order->get_checkout_order_received_url() );
        exit;
    }

    // Get store name and logo.
    $store_name = get_bloginfo( 'name' );
    $custom_logo_id = get_theme_mod( 'custom_logo' );
    $logo_url = $custom_logo_id ? wp_get_attachment_image_url( $custom_logo_id, 'medium' ) : '';

    // Render the transition page and exit (no WP template loaded).
    nocache_headers();
    header( 'Content-Type: text/html; charset=utf-8' );
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $store_name ); ?> — Redirecionando para pagamento</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: #f8f6f3;
        color: #2d3436;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        padding: 1rem;
    }
    .ip-transition {
        text-align: center;
        max-width: 420px;
        background: #fff;
        border-radius: 16px;
        padding: 3rem 2rem;
        box-shadow: 0 4px 24px rgba(0,0,0,0.06);
    }
    .ip-transition__logo {
        max-height: 48px;
        max-width: 200px;
        margin-bottom: 1.5rem;
    }
    .ip-transition__title {
        font-size: 1.25rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: #1a472a;
    }
    .ip-transition__subtitle {
        font-size: 0.95rem;
        color: #636e72;
        margin-bottom: 2rem;
        line-height: 1.5;
    }
    .ip-transition__spinner {
        width: 40px;
        height: 40px;
        border: 3px solid #e8e4df;
        border-top-color: #1a472a;
        border-radius: 50%;
        animation: ip-spin 0.8s linear infinite;
        margin: 0 auto 1.5rem;
    }
    @keyframes ip-spin {
        to { transform: rotate(360deg); }
    }
    .ip-transition__badges {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        margin-top: 1rem;
        font-size: 0.8rem;
        color: #b2bec3;
    }
    .ip-transition__badges svg {
        width: 16px;
        height: 16px;
        fill: currentColor;
        flex-shrink: 0;
    }
    .ip-transition__link {
        display: inline-block;
        margin-top: 1.5rem;
        font-size: 0.85rem;
        color: #1a472a;
        text-decoration: underline;
    }
</style>
</head>
<body>
<div class="ip-transition">
    <?php if ( $logo_url ) : ?>
        <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $store_name ); ?>" class="ip-transition__logo">
    <?php endif; ?>

    <div class="ip-transition__spinner"></div>

    <h1 class="ip-transition__title">Redirecionando para pagamento seguro</h1>
    <p class="ip-transition__subtitle">
        Aguarde enquanto conectamos ao ambiente seguro de pagamento.
        <br>Pedido #<?php echo esc_html( $order_id ); ?>
    </p>

    <div class="ip-transition__badges">
        <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/></svg>
        <span>Conexao segura</span>
        <svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
        <span>Dados protegidos</span>
    </div>

    <a href="<?php echo esc_url( $checkout_url ); ?>" class="ip-transition__link">
        Clique aqui se nao for redirecionado
    </a>
</div>

<script>
    // Auto-redirect after 1.5 seconds.
    setTimeout( function() {
        window.location.href = <?php echo wp_json_encode( $checkout_url ); ?>;
    }, 1500 );
</script>
</body>
</html>
    <?php
    exit;
}

/**
 * Link de configurações na lista de plugins.
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
    $url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=infinitepay_hpos' );
    array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( $url ), __( 'Configurações', 'wc-infinitepay-hpos' ) ) );
    return $links;
} );

/**
 * Carrega traduções do plugin.
 */
add_action( 'init', function () {
    load_plugin_textdomain( 'wc-infinitepay-hpos', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );
