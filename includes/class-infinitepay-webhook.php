<?php
/**
 * Handler do Webhook InfinitePay.
 *
 * Segurança:
 * - Valida webhook_secret via hash_equals (timing-safe)
 * - Rate limiting por IP (30 req/min via transient)
 * - Proteção contra replay attacks via nonce de transação
 * - Valida que o pedido pertence ao gateway InfinitePay
 * - Logs detalhados para debug
 *
 * @package InfinitePay\WooCommerce
 * @since   4.0.0
 * @updated 5.1.0 — IP detection para proxy/Cloudflare, sanitização order_nsu,
 *                   tratamento de pagamento falho/cancelado.
 * @updated 5.2.0 — log_critical() para eventos de segurança e pagamento
 *                   (grava SEMPRE, independente do debug toggle).
 * @updated 5.4.0 — Fail-closed sem secret configurado (rejeita em vez de
 *                   aceitar), anti-replay atômico via object cache, constante
 *                   REST_NAMESPACE e ID compartilhado com o gateway.
 * @updated 5.5.0 — Valida secret ANTES do rate limit; requisições autenticadas
 *                   bypassam o rate limit por IP (anti Denial-of-Wallet com IPs
 *                   compartilhados da InfinitePay). Reconciliação Jules PR #22/#32.
 */

defined( 'ABSPATH' ) || exit;

class InfinitePay_Webhook {

    /**
     * Namespace REST do plugin.
     *
     * @since 5.4.0
     */
    const REST_NAMESPACE = 'wc-infinitepay/v1';

    /**
     * Registra a rota REST do webhook.
     */
    public static function register_routes(): void {
        register_rest_route( self::REST_NAMESPACE, '/webhook', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'handle' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Obtém a instância do gateway.
     *
     * @return WC_Gateway_InfinitePay_HPOS|null
     */
    private static function get_gateway(): ?WC_Gateway_InfinitePay_HPOS {
        $gateways = WC()->payment_gateways()->payment_gateways();
        return $gateways[ WC_Gateway_InfinitePay_HPOS::ID ] ?? null;
    }

    /**
     * Valida o webhook secret.
     *
     * Compara o header X-Webhook-Secret (ou Authorization) com o
     * secret configurado no gateway. Usa hash_equals para prevenir
     * timing attacks.
     *
     * @param WP_REST_Request             $request Requisição.
     * @param WC_Gateway_InfinitePay_HPOS $gateway Instância do gateway.
     * @return bool
     */
    private static function validate_secret( WP_REST_Request $request, WC_Gateway_InfinitePay_HPOS $gateway ): bool {
        $configured_secret = $gateway->webhook_secret;

        // Fail-closed: sem secret configurado, REJEITA (não confirma pedidos sem
        // autenticação). Configure o Webhook Secret no gateway e no painel InfinitePay.
        if ( empty( $configured_secret ) ) {
            $gateway->log_critical( 'SECURITY: Webhook rejeitado — secret não configurado. Configure o Webhook Secret para receber confirmações.', 'error' );
            return false;
        }

        // Tenta X-Webhook-Secret primeiro, depois Authorization Bearer.
        $received = $request->get_header( 'X-Webhook-Secret' );
        if ( ! $received ) {
            $auth = $request->get_header( 'Authorization' );
            if ( $auth && str_starts_with( $auth, 'Bearer ' ) ) {
                $received = substr( $auth, 7 );
            }
        }

        if ( ! $received ) {
            return false;
        }

        return hash_equals( $configured_secret, $received );
    }

    /**
     * Detecta o IP real do cliente, considerando Cloudflare e reverse proxies.
     *
     * @since 5.1.0
     *
     * @return string IP do cliente.
     */
    private static function get_client_ip(): string {
        // Security: Default to REMOTE_ADDR to prevent IP spoofing attacks via headers.
        $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );

        /**
         * Filters the client IP address used for webhook rate limiting.
         *
         * If the server is behind a trusted reverse proxy (like Cloudflare),
         * this can be overridden to return the real client IP.
         *
         * @since 5.4.0
         *
         * @param string $ip Client IP.
         */
        return (string) apply_filters( 'infinitepay_webhook_ip', $ip );
    }

    /**
     * Rate limiting por IP via transient.
     *
     * Limita a 30 requests por minuto por IP.
     * Usa detecção inteligente de IP real (Cloudflare/proxy).
     *
     * @return bool true se dentro do limite.
     */
    private static function check_rate_limit(): bool {
        $ip    = self::get_client_ip();
        $key   = 'ip_wh_rl_' . hash( 'sha256', $ip );
        $count = (int) get_transient( $key );

        if ( $count >= 30 ) {
            return false;
        }

        set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
        return true;
    }

    /**
     * Verifica se a transação já foi processada (anti-replay).
     *
     * @param string $tx Transaction NSU.
     * @return bool true se já processada.
     */
    private static function is_duplicate( string $tx ): bool {
        $key = 'ip_wh_tx_' . hash( 'sha256', $tx );

        // Com object cache externo (Redis/Memcached), wp_cache_add é atômico:
        // retorna false se a chave já existe, fechando a janela de corrida entre
        // dois webhooks simultâneos com o mesmo transaction_nsu.
        if ( wp_using_ext_object_cache() ) {
            if ( false === wp_cache_add( $key, 1, 'infinitepay_replay', HOUR_IN_SECONDS ) ) {
                return true;
            }
            return false;
        }

        // Fallback sem cache externo: transient (get/set não-atômico).
        if ( get_transient( $key ) ) {
            return true;
        }
        // Marca como processada por 1 hora.
        set_transient( $key, 1, HOUR_IN_SECONDS );
        return false;
    }

    /**
     * Valida que o pedido pertence a este gateway.
     *
     * Impede que webhooks forjados confirmem pedidos de outros gateways.
     *
     * @param WC_Order $order Pedido.
     * @return bool
     */
    private static function validate_order_ownership( WC_Order $order ): bool {
        // Verifica se o método de pagamento é o nosso.
        if ( WC_Gateway_InfinitePay_HPOS::ID !== $order->get_payment_method() ) {
            return false;
        }

        // Verifica se o pedido está marcado como pendente InfinitePay.
        if ( 'yes' !== $order->get_meta( '_infinitepay_pending' ) ) {
            // Pode já ter sido confirmado — aceita se status for on-hold.
            if ( 'on-hold' !== $order->get_status() ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Handler principal do webhook.
     *
     * @param WP_REST_Request $request Requisição.
     * @return WP_REST_Response
     */
    public static function handle( WP_REST_Request $request ): WP_REST_Response {
        $gateway = self::get_gateway();
        if ( ! $gateway ) {
            return new WP_REST_Response( array( 'success' => false, 'error' => 'gateway_not_found' ), 500 );
        }

        // Valida o secret PRIMEIRO (hash_equals, leve). Requisições autenticadas
        // BYPASSAM o rate limit por IP — a InfinitePay usa IPs compartilhados e não
        // pode ser bloqueada durante um flood (Denial-of-Wallet). O rate limit
        // protege apenas requisições NÃO autenticadas (anti brute force).
        // (reconciliação Jules PR #22/#32)
        $is_valid_secret = self::validate_secret( $request, $gateway );

        if ( ! $is_valid_secret ) {
            if ( ! self::check_rate_limit() ) {
                $gateway->log_critical( 'SECURITY: Webhook bloqueado — rate limit excedido. IP: ' . self::get_client_ip(), 'warning' );
                return new WP_REST_Response( array( 'success' => false, 'error' => 'rate_limit' ), 429 );
            }
            $gateway->log_critical( 'SECURITY: Webhook rejeitado — secret inválido. IP: ' . self::get_client_ip(), 'error' );
            return new WP_REST_Response( array( 'success' => false, 'error' => 'unauthorized' ), 401 );
        }

        // Parse do body.
        $body = $request->get_json_params();
        if ( ! is_array( $body ) ) {
            $gateway->log_critical( 'SECURITY: Webhook rejeitado — body inválido. IP: ' . self::get_client_ip(), 'error' );
            return new WP_REST_Response( array( 'success' => false, 'error' => 'invalid_body' ), 400 );
        }

        $order_nsu = isset( $body['order_nsu'] ) ? absint( $body['order_nsu'] ) : 0;
        $tx        = isset( $body['transaction_nsu'] ) ? sanitize_text_field( $body['transaction_nsu'] ) : '';
        $slug      = isset( $body['invoice_slug'] ) ? sanitize_text_field( $body['invoice_slug'] ) : '';
        $receipt   = isset( $body['receipt_url'] ) ? esc_url_raw( $body['receipt_url'] ) : '';
        $status    = isset( $body['status'] ) ? sanitize_text_field( $body['status'] ) : '';

        if ( ! $order_nsu || ! $tx || ! $slug ) {
            $gateway->log_critical( 'Webhook rejeitado: campos obrigatórios ausentes. IP: ' . self::get_client_ip(), 'warning' );
            return new WP_REST_Response( array( 'success' => false, 'error' => 'missing_fields' ), 400 );
        }

        // Anti-replay.
        if ( self::is_duplicate( $tx ) ) {
            $gateway->log( 'Webhook ignorado: transação ' . $tx . ' já processada (duplicate).' );
            return new WP_REST_Response( array( 'success' => true, 'note' => 'duplicate' ), 200 );
        }

        // Busca o pedido.
        $order = wc_get_order( $order_nsu );
        if ( ! $order ) {
            $gateway->log_critical( 'Webhook: pedido #' . $order_nsu . ' não encontrado. IP: ' . self::get_client_ip(), 'error' );
            return new WP_REST_Response( array( 'success' => false, 'error' => 'order_not_found' ), 404 );
        }

        // Valida que o pedido pertence a este gateway.
        if ( ! self::validate_order_ownership( $order ) ) {
            $gateway->log_critical( 'SECURITY: Webhook rejeitado — pedido #' . $order_nsu . ' não pertence ao gateway InfinitePay (method: ' . $order->get_payment_method() . '). IP: ' . self::get_client_ip(), 'error' );
            return new WP_REST_Response( array( 'success' => false, 'error' => 'order_mismatch' ), 403 );
        }

        // Já finalizado? Retorna sucesso sem reprocessar.
        if ( in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
            $gateway->log( 'Webhook: pedido #' . $order_nsu . ' já processado, status: ' . $order->get_status() );
            return new WP_REST_Response( array( 'success' => true ), 200 );
        }

        // Pagamento falho/cancelado? Marca pedido como failed.
        if ( in_array( $status, array( 'failed', 'cancelled', 'expired', 'refunded', 'rejected' ), true ) ) {
            $order->update_status(
                'failed',
                sprintf(
                    /* translators: 1: payment status 2: transaction NSU */
                    __( 'Pagamento %1$s via webhook InfinitePay. TX: %2$s', 'wc-infinitepay-hpos' ),
                    $status,
                    $tx
                )
            );
            $order->delete_meta_data( '_infinitepay_pending' );
            $order->save();

            $gateway->log_critical( 'PAYMENT FAILED: pedido #' . $order_nsu . ' marcado como falho. Status recebido: ' . $status . '. TX: ' . $tx, 'warning' );

            /**
             * Dispara após um pagamento falho ser reportado via webhook.
             *
             * @since 5.1.0
             *
             * @param WC_Order $order  Pedido.
             * @param string   $status Status recebido (failed, cancelled, expired, etc).
             * @param array    $body   Dados completos do webhook.
             */
            do_action( 'infinitepay_webhook_payment_failed', $order, $status, $body );

            return new WP_REST_Response( array( 'success' => true ), 200 );
        }

        // Confirma pagamento.
        $order->payment_complete( $tx );
        $order->update_meta_data( '_infinitepay_slug', $slug );
        $order->delete_meta_data( '_infinitepay_pending' );
        // Mark cart as safe to clear on next page load (session-based).
        $order->update_meta_data( '_infinitepay_cart_cleared', 'yes' );

        if ( $receipt ) {
            $order->update_meta_data( '_infinitepay_receipt_url', $receipt );
        }

        $order->add_order_note(
            sprintf(
                /* translators: %s: transaction NSU */
                __( 'Pagamento confirmado via webhook. Transação: %s', 'wc-infinitepay-hpos' ),
                $tx
            )
        );
        $order->save();

        $gateway->log_critical( 'PAYMENT OK: pedido #' . $order_nsu . ' confirmado via webhook. TX: ' . $tx . '. Total: ' . $order->get_total() . ' ' . $order->get_currency() );

        /**
         * Dispara após a confirmação de pagamento via webhook.
         *
         * @since 5.0.0
         *
         * @param WC_Order $order Pedido confirmado.
         * @param string   $tx    Transaction NSU.
         * @param array    $body  Dados do webhook.
         */
        do_action( 'infinitepay_webhook_payment_confirmed', $order, $tx, $body );

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }
}
