<?php
/**
 * Limpeza ao desinstalar o plugin.
 *
 * Remove opções, transients e metadados criados pelo plugin.
 * Executado apenas quando o plugin é desinstalado via admin.
 *
 * @package InfinitePay\WooCommerce
 * @since   5.0.0
 * @updated 5.1.0 — Limpeza do cron de expiração.
 */

// Segurança: impede acesso direto.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove opções do gateway.
delete_option( 'woocommerce_infinitepay_hpos_settings' );

// Remove cron de expiração de pedidos.
wp_clear_scheduled_hook( 'infinitepay_cancel_stale_orders' );

// Remove transients de rate limiting e anti-replay.
global $wpdb;

$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like( '_transient_ip_wh_rl_' ) . '%',
        $wpdb->esc_like( '_transient_ip_wh_tx_' ) . '%',
        $wpdb->esc_like( '_transient_ip_check_' ) . '%'
    )
);

// Remove transient timeouts correspondentes.
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like( '_transient_timeout_ip_wh_rl_' ) . '%',
        $wpdb->esc_like( '_transient_timeout_ip_wh_tx_' ) . '%',
        $wpdb->esc_like( '_transient_timeout_ip_check_' ) . '%'
    )
);
