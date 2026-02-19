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
 */

defined( 'ABSPATH' ) || exit;

class InfinitePay_Webhook {

    /**
     * Namespace da API REST.
     * @var string
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

        // Se não há secret configurado, loga aviso mas aceita (backward compat).
        if ( empty( $configured_secret ) ) {
            $gateway->log( 'AVISO: Webhook secret não configurado. Todas as requisições serão aceitas.', 'warning' );
            return true;
        }

        // Tenta X-Webhook-Secret primeiro, depois Authorization Bearer.
        $received = $request->get_header( 'X-Webhook-Secret' );
        if ( ! $received ) {
            $auth = $request->get_header( 'Authorization' );
            if ( $auth && 0 === strpos( $auth, 'Bearer ' ) ) {
                $received = substr( $auth, 7 );
            }
        }

        if ( ! $received ) {
            return false;
        }

        return hash_equals( $configured_secret, $received );
    }

    /**
     * Rate limiting simples por IP via transient.
     *
     * Limita a 30 requests por minuto por IP.
     *
     * @return bool true se dentro do limite.
     */
    private static function check_rate_limit(): bool {
        $ip  = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
        $key = 'ip_wh_rl_' . md5( $ip );
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
        $key = 'ip_wh_tx_' . md5( $tx );
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

        // Rate limit.
        if ( ! self::check_rate_limit() ) {
            if ( $gateway ) {
                $gateway->log( 'Webhook bloqueado: rate limit excedido.', 'warning' );
            }
            return new WP_REST_Response( array( 'success' => false, 'error' => 'rate_limit' ), 429 );
        }

        if ( ! $gateway ) {
            return new WP_REST_Response( array( 'success' => false, 'error' => 'gateway_not_found' ), 500 );
        }

        // Validação do secret.
        if ( ! self::validate_secret( $request, $gateway ) ) {
            $gateway->log( 'Webhook rejeitado: secret inválido.', 'error' );
            return new WP_REST_Response( array( 'success' => false, 'error' => 'unauthorized' ), 401 );
        }

        // Parse do body.
        $body = $request->get_json_params();
        if ( ! is_array( $body ) ) {
            $gateway->log( 'Webhook rejeitado: body inválido.', 'error' );
            return new WP_REST_Response( array( 'success' => false, 'error' => 'invalid_body' ), 400 );
        }

        $order_nsu = isset( $body['order_nsu'] ) ? (string) $body['order_nsu'] : '';
        $tx        = isset( $body['transaction_nsu'] ) ? sanitize_text_field( $body['transaction_nsu'] ) : '';
        $slug      = isset( $body['invoice_slug'] ) ? sanitize_text_field( $body['invoice_slug'] ) : '';
        $receipt   = isset( $body['receipt_url'] ) ? esc_url_raw( $body['receipt_url'] ) : '';

        if ( ! $order_nsu || ! $tx || ! $slug ) {
            $gateway->log( 'Webhook rejeitado: campos obrigatórios ausentes.', 'warning' );
            return new WP_REST_Response( array( 'success' => false, 'error' => 'missing_fields' ), 400 );
        }

        // Anti-replay.
        if ( self::is_duplicate( $tx ) ) {
            $gateway->log( 'Webhook ignorado: transação ' . $tx . ' já processada (duplicate).' );
            return new WP_REST_Response( array( 'success' => true, 'note' => 'duplicate' ), 200 );
        }

        // Busca o pedido.
        $order = wc_get_order( (int) $order_nsu );
        if ( ! $order ) {
            $gateway->log( 'Webhook: pedido #' . $order_nsu . ' não encontrado.', 'error' );
            return new WP_REST_Response( array( 'success' => false, 'error' => 'order_not_found' ), 404 );
        }

        // Valida que o pedido pertence a este gateway.
        if ( ! self::validate_order_ownership( $order ) ) {
            $gateway->log( 'Webhook rejeitado: pedido #' . $order_nsu . ' não pertence ao gateway InfinitePay (method: ' . $order->get_payment_method() . ').', 'error' );
            return new WP_REST_Response( array( 'success' => false, 'error' => 'order_mismatch' ), 403 );
        }

        // Já finalizado? Retorna sucesso sem reprocessar.
        if ( in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
            $gateway->log( 'Webhook: pedido #' . $order_nsu . ' já processado, status: ' . $order->get_status() );
            return new WP_REST_Response( array( 'success' => true ), 200 );
        }

        // Confirma pagamento.
        // Define metadados ANTES de payment_complete(), pois ele chama save() internamente.
        $order->update_meta_data( '_infinitepay_slug', $slug );
        $order->delete_meta_data( '_infinitepay_pending' );

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

        // payment_complete() chama save() internamente, dispensando save() explícito.
        $order->payment_complete( $tx );

        $gateway->log( 'Webhook: pedido #' . $order_nsu . ' confirmado. TX: ' . $tx );

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
