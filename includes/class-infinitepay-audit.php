<?php
/**
 * Audit / Diagnostic REST endpoint for InfinitePay.
 *
 * Exposes plugin health data via authenticated REST API so external
 * monitoring scripts (Python, cron, etc.) can query plugin status
 * without needing wp-admin or SSH access.
 *
 * Endpoint: GET /wc-infinitepay/v1/audit
 * Auth:     WooCommerce API keys (Basic Auth) — same keys used by woo_client.py
 *
 * @package InfinitePay\WooCommerce
 * @since   5.2.0
 */

defined( 'ABSPATH' ) || exit;

class InfinitePay_Audit {

    /**
     * Registers the REST route.
     */
    public static function register_routes(): void {
        register_rest_route( 'wc-infinitepay/v1', '/audit', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'handle' ),
            'permission_callback' => array( __CLASS__, 'check_permission' ),
        ) );
    }

    /**
     * Permission check: require manage_woocommerce capability.
     *
     * WooCommerce REST authentication (consumer key/secret via Basic Auth)
     * sets the current user automatically. This check ensures only
     * admin-level API keys can access audit data.
     *
     * @return bool|WP_Error
     */
    public static function check_permission() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'Acesso negado. Requer permissão manage_woocommerce.', 'wc-infinitepay-hpos' ),
                array( 'status' => 403 )
            );
        }
        return true;
    }

    /**
     * Main handler — returns full audit payload.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public static function handle( WP_REST_Request $request ): WP_REST_Response {
        $data = array(
            'plugin_version'    => defined( 'INFINITEPAY_WC_VERSION' ) ? INFINITEPAY_WC_VERSION : 'unknown',
            'generated_at'      => gmdate( 'c' ),
            'gateway_config'    => self::get_gateway_config(),
            'cron_status'       => self::get_cron_status(),
            'scheduled_actions' => self::get_scheduled_actions(),
            'recent_orders'     => self::get_recent_orders(),
            'environment'       => self::get_environment(),
        );

        return new WP_REST_Response( $data, 200 );
    }

    /**
     * Gateway configuration (safe subset — no secrets).
     *
     * @return array
     */
    private static function get_gateway_config(): array {
        $gateways = WC()->payment_gateways()->payment_gateways();
        $gw       = $gateways['infinitepay_hpos'] ?? null;

        if ( ! $gw ) {
            return array( 'error' => 'gateway_not_found' );
        }

        return array(
            'enabled'           => $gw->enabled,
            'handle_set'        => ! empty( $gw->handle ),
            'debug'             => $gw->debug,
            'send_customer'     => $gw->send_customer,
            'send_address'      => $gw->send_address,
            'send_items'        => $gw->send_items,
            'webhook_secret_set' => ! empty( $gw->webhook_secret ),
            'order_expiration'  => $gw->get_option( 'order_expiration', '48' ),
            'webhook_url'       => rest_url( 'wc-infinitepay/v1/webhook' ),
            'audit_url'         => rest_url( 'wc-infinitepay/v1/audit' ),
        );
    }

    /**
     * WP-Cron status for InfinitePay's scheduled event.
     *
     * @return array
     */
    private static function get_cron_status(): array {
        $next = wp_next_scheduled( 'infinitepay_cancel_stale_orders' );
        $wp_cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;

        return array(
            'hook'              => 'infinitepay_cancel_stale_orders',
            'next_run'          => $next ? gmdate( 'c', $next ) : null,
            'next_run_human'    => $next ? human_time_diff( $next ) . ( $next > time() ? ' from now' : ' ago (overdue)' ) : 'NOT SCHEDULED',
            'is_overdue'        => $next && $next < time(),
            'wp_cron_disabled'  => $wp_cron_disabled,
            'note'              => $wp_cron_disabled ? 'WP-Cron is disabled. Server must trigger wp-cron.php via crontab.' : 'WP-Cron is active.',
        );
    }

    /**
     * Queries WooCommerce Action Scheduler for InfinitePay-related actions.
     *
     * @return array
     */
    private static function get_scheduled_actions(): array {
        // Action Scheduler is bundled with WooCommerce since 3.x.
        if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
            return array( 'error' => 'Action Scheduler not available.' );
        }

        $actions = array();

        // Search for any actions with "infinitepay" in the hook.
        $results = as_get_scheduled_actions( array(
            'hook'     => 'infinitepay_cancel_stale_orders',
            'status'   => \ActionScheduler_Store::STATUS_PENDING,
            'per_page' => 10,
            'orderby'  => 'date',
            'order'    => 'ASC',
        ), 'ARRAY_A' );

        foreach ( $results as $action ) {
            $actions[] = array(
                'hook'      => $action['hook'] ?? 'unknown',
                'status'    => $action['status'] ?? 'unknown',
                'scheduled' => $action['scheduled_date_gmt'] ?? null,
                'group'     => $action['group'] ?? '',
            );
        }

        // Also check for WooCommerce's own scheduled actions (order cleanup, etc.)
        $wc_actions = as_get_scheduled_actions( array(
            'group'    => 'woocommerce',
            'status'   => \ActionScheduler_Store::STATUS_PENDING,
            'per_page' => 5,
            'orderby'  => 'date',
            'order'    => 'ASC',
        ), 'ARRAY_A' );

        $wc_summary = array();
        foreach ( $wc_actions as $action ) {
            $wc_summary[] = array(
                'hook'      => $action['hook'] ?? 'unknown',
                'scheduled' => $action['scheduled_date_gmt'] ?? null,
            );
        }

        return array(
            'infinitepay_actions' => $actions,
            'wc_actions_sample'   => $wc_summary,
        );
    }

    /**
     * Recent InfinitePay orders summary (last 30 days).
     *
     * @return array
     */
    private static function get_recent_orders(): array {
        $cutoff = gmdate( 'Y-m-d', strtotime( '-30 days' ) );

        $statuses = array( 'on-hold', 'processing', 'completed', 'cancelled', 'failed' );
        $summary  = array();
        $total_revenue = 0;

        foreach ( $statuses as $status ) {
            $orders = wc_get_orders( array(
                'status'         => $status,
                'payment_method' => 'infinitepay_hpos',
                'date_created'   => '>=' . $cutoff,
                'limit'          => -1,
                'return'         => 'ids',
            ) );

            $count = count( $orders );

            // For revenue, only count processing + completed.
            if ( in_array( $status, array( 'processing', 'completed' ), true ) && $count > 0 ) {
                foreach ( $orders as $oid ) {
                    $order = wc_get_order( $oid );
                    if ( $order ) {
                        $total_revenue += (float) $order->get_total();
                    }
                }
            }

            $summary[ $status ] = $count;
        }

        // Pending on-hold: orders still waiting for payment.
        $pending_onhold = wc_get_orders( array(
            'status'         => 'on-hold',
            'payment_method' => 'infinitepay_hpos',
            'meta_key'       => '_infinitepay_pending',
            'meta_value'     => 'yes',
            'limit'          => 50,
            'return'         => 'ids',
        ) );

        $pending_details = array();
        foreach ( $pending_onhold as $oid ) {
            $order = wc_get_order( $oid );
            if ( $order ) {
                $pending_details[] = array(
                    'id'      => $oid,
                    'total'   => $order->get_total(),
                    'created' => $order->get_date_created() ? $order->get_date_created()->format( 'c' ) : null,
                    'has_url' => ! empty( $order->get_meta( '_infinitepay_checkout_url' ) ),
                );
            }
        }

        return array(
            'period'                => 'last_30_days',
            'by_status'             => $summary,
            'total_orders'          => array_sum( $summary ),
            'revenue_confirmed'     => round( $total_revenue, 2 ),
            'pending_payment'       => $pending_details,
            'pending_payment_count' => count( $pending_details ),
        );
    }

    /**
     * WooCommerce environment snapshot.
     *
     * @return array
     */
    private static function get_environment(): array {
        $env = array(
            'wc_version'    => defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
            'wp_version'    => get_bloginfo( 'version' ),
            'php_version'   => PHP_VERSION,
            'hpos_enabled'  => 'yes' === get_option( 'woocommerce_custom_orders_table_enabled', 'no' ),
            'currency'      => get_woocommerce_currency(),
            'timezone'      => wp_timezone_string(),
            'server_time'   => gmdate( 'c' ),
            'local_time'    => wp_date( 'c' ),
        );

        // Check if external object cache is active.
        $env['object_cache'] = wp_using_ext_object_cache() ? 'external' : 'internal';

        return $env;
    }
}
