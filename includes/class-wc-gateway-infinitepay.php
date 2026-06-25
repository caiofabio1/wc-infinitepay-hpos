<?php
/**
 * Gateway de pagamento InfinitePay para WooCommerce.
 *
 * Recursos:
 * - Checkout via link externo InfinitePay (Cartão + Pix)
 * - Webhook REST seguro com rate limiting e anti-replay
 * - Verificação inteligente na thank-you page com cache
 * - Retry com backoff progressivo na API
 * - Line items detalhados no checkout InfinitePay
 * - Compatibilidade total HPOS e WC Blocks Checkout
 * - Filtros e actions para extensibilidade
 * - PHP 8.2+ compatível (propriedades tipadas)
 *
 * @package InfinitePay\WooCommerce
 * @since   4.0.0
 * @updated 5.1.0 — Ícone de pagamento, auto-cancel por expiração,
 *                   cupons como desconto visível, order_expiration setting.
 * @updated 5.2.0 — log_critical() para eventos que SEMPRE gravam (independente
 *                   do debug toggle), audit REST endpoint.
 * @updated 5.3.0 — Cart emptied after payment (not before), branded redirect
 *                   transition page, rich thank-you with auto-polling, order
 *                   summary, and WhatsApp support link.
 * @updated 5.4.0 — Validação HTTPS da URL de checkout, guarda contra
 *                   dupla-confirmação na thankyou page, constante ID.
 * @updated 5.5.0 — Redação de PII (dados do cliente) nos logs de payload e de
 *                   resposta da API (reconciliação Jules PR #38).
 */

defined( 'ABSPATH' ) || exit;

class WC_Gateway_InfinitePay_HPOS extends WC_Payment_Gateway {

    /**
     * ID único do gateway (payment_method e chave de vínculo).
     *
     * @since 5.4.0
     */
    const ID = 'infinitepay_hpos';

    /**
     * Endpoints da API InfinitePay.
     */
    const API_LINKS        = 'https://api.infinitepay.io/invoices/public/checkout/links';
    const API_PAYMENTCHECK = 'https://api.infinitepay.io/invoices/public/checkout/payment_check';

    /**
     * Timeout da API em segundos.
     */
    const API_TIMEOUT = 10;

    /**
     * Máximo de tentativas na API.
     */
    const API_MAX_RETRIES = 2;

    /**
     * Campos de cliente omitidos nos logs (LGPD/privacidade).
     *
     * @since 5.5.0
     */
    private const REDACTED_CUSTOMER_FIELDS = array( 'name', 'email', 'phone_number', 'document' );

    /**
     * Campos de endereço omitidos nos logs (LGPD/privacidade).
     *
     * @since 5.5.0
     */
    private const REDACTED_ADDRESS_FIELDS = array( 'cep', 'number', 'complement', 'street', 'neighborhood', 'city', 'state' );

    /**
     * Propriedades declaradas para PHP 8.2+.
     */
    public string $handle         = '';
    public bool   $debug          = false;
    public bool   $send_customer  = true;
    public bool   $send_address   = true;
    public bool   $send_items     = true;
    public string $webhook_secret = '';

    /**
     * Construtor.
     */
    public function __construct() {
        $this->id                 = self::ID;
        $this->method_title       = __( 'InfinitePay (HPOS)', 'wc-infinitepay-hpos' );
        $this->method_description = __( 'Aceite Cartão e Pix via InfinitePay com checkout seguro, webhook REST e compatibilidade HPOS.', 'wc-infinitepay-hpos' );
        $this->has_fields         = false;
        $this->supports           = array( 'products' );

        // Ícone de pagamento (cartão + pix) exibido no checkout.
        $this->icon = apply_filters(
            'infinitepay_gateway_icon',
            plugins_url( 'assets/img/icon.svg', INFINITEPAY_WC_FILE )
        );

        $this->init_form_fields();
        $this->init_settings();

        $this->title          = $this->get_option( 'title', __( 'Cartão/Pix (InfinitePay)', 'wc-infinitepay-hpos' ) );
        $this->description    = $this->get_option( 'description', __( 'Pague com segurança via InfinitePay.', 'wc-infinitepay-hpos' ) );
        $this->enabled        = $this->get_option( 'enabled', 'no' );
        $this->handle         = sanitize_text_field( $this->get_option( 'handle', '' ) );
        $this->debug          = 'yes' === $this->get_option( 'debug', 'no' );
        $this->send_customer  = 'yes' === $this->get_option( 'send_customer', 'yes' );
        $this->send_address   = 'yes' === $this->get_option( 'send_address', 'yes' );
        $this->send_items     = 'yes' === $this->get_option( 'send_items', 'yes' );
        $this->webhook_secret = sanitize_text_field( $this->get_option( 'webhook_secret', '' ) );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ), 10, 1 );

        // Aviso de segurança no admin se webhook secret não configurado.
        if ( is_admin() && 'yes' === $this->enabled && empty( $this->webhook_secret ) ) {
            add_action( 'admin_notices', array( $this, 'admin_notice_missing_secret' ) );
        }
    }

    /**
     * Exibe aviso no admin quando o webhook secret não está configurado.
     */
    public function admin_notice_missing_secret(): void {
        $settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=infinitepay_hpos' );
        printf(
            '<div class="notice notice-warning is-dismissible"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
            esc_html__( 'InfinitePay:', 'wc-infinitepay-hpos' ),
            esc_html__( 'O Webhook Secret não está configurado. Sem ele, qualquer requisição pode confirmar pedidos. Configure para proteger sua loja.', 'wc-infinitepay-hpos' ),
            esc_url( $settings_url ),
            esc_html__( 'Configurar agora', 'wc-infinitepay-hpos' )
        );
    }

    /**
     * Campos de configuração no admin.
     */
    public function init_form_fields() {
        $webhook_url = rest_url( 'wc-infinitepay/v1/webhook' );

        $this->form_fields = array(
            'enabled'         => array(
                'title'   => __( 'Ativar/Desativar', 'wc-infinitepay-hpos' ),
                'type'    => 'checkbox',
                'label'   => __( 'Ativar InfinitePay', 'wc-infinitepay-hpos' ),
                'default' => 'no',
            ),
            'title'           => array(
                'title'   => __( 'Título', 'wc-infinitepay-hpos' ),
                'type'    => 'text',
                'default' => __( 'Cartão/Pix (InfinitePay)', 'wc-infinitepay-hpos' ),
                'desc_tip' => true,
                'description' => __( 'Nome exibido para o cliente no checkout.', 'wc-infinitepay-hpos' ),
            ),
            'description'     => array(
                'title'   => __( 'Descrição', 'wc-infinitepay-hpos' ),
                'type'    => 'textarea',
                'default' => __( 'Pague com segurança via InfinitePay.', 'wc-infinitepay-hpos' ),
            ),
            'handle'          => array(
                'title'       => __( 'Handle (InfiniteTag)', 'wc-infinitepay-hpos' ),
                'type'        => 'text',
                'description' => __( 'Seu identificador InfinitePay (sem $). Encontre no app InfinitePay > Configurações > Link integrado.', 'wc-infinitepay-hpos' ),
                'default'     => '',
            ),
            'send_customer'   => array(
                'title'   => __( 'Enviar dados do cliente', 'wc-infinitepay-hpos' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enviar nome, email e telefone para o checkout InfinitePay', 'wc-infinitepay-hpos' ),
                'default' => 'yes',
            ),
            'send_address'    => array(
                'title'   => __( 'Enviar endereço', 'wc-infinitepay-hpos' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enviar CEP, número e complemento para o checkout InfinitePay', 'wc-infinitepay-hpos' ),
                'default' => 'yes',
            ),
            'send_items'      => array(
                'title'   => __( 'Itens detalhados', 'wc-infinitepay-hpos' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enviar nome e quantidade de cada produto (em vez de valor total consolidado)', 'wc-infinitepay-hpos' ),
                'default' => 'yes',
            ),
            'webhook_secret'  => array(
                'title'       => __( 'Webhook Secret', 'wc-infinitepay-hpos' ),
                'type'        => 'password',
                'description' => __( 'Chave secreta para validar webhooks. Configure a mesma no painel InfinitePay. Altamente recomendado para segurança.', 'wc-infinitepay-hpos' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'order_expiration' => array(
                'title'       => __( 'Expiração de pedidos (horas)', 'wc-infinitepay-hpos' ),
                'type'        => 'number',
                'description' => __( 'Pedidos aguardando pagamento serão cancelados automaticamente após este prazo. Use 0 para desativar.', 'wc-infinitepay-hpos' ),
                'default'     => '48',
                'desc_tip'    => true,
                'custom_attributes' => array(
                    'min'  => '0',
                    'step' => '1',
                ),
            ),
            'debug'           => array(
                'title'   => __( 'Debug', 'wc-infinitepay-hpos' ),
                'type'    => 'checkbox',
                'label'   => __( 'Registrar logs em WooCommerce > Status > Logs', 'wc-infinitepay-hpos' ),
                'default' => 'no',
            ),
            '_webhook_help'   => array(
                'title'       => __( 'Webhook URL', 'wc-infinitepay-hpos' ),
                'type'        => 'title',
                'description' => sprintf(
                    /* translators: %s: webhook URL */
                    __( 'Configure esta URL no painel InfinitePay como destino do webhook:<br><code>%s</code>', 'wc-infinitepay-hpos' ),
                    esc_html( $webhook_url )
                ),
            ),
        );
    }

    /**
     * Verifica se o gateway está disponível.
     *
     * @return bool
     */
    public function is_available() {
        if ( 'yes' !== $this->enabled || empty( $this->handle ) ) {
            return false;
        }
        return parent::is_available();
    }

    /**
     * Logger condicional (só grava se debug habilitado).
     *
     * Use para mensagens de debug verbose (payloads, cache hits, etc).
     *
     * @param string $message Mensagem.
     * @param string $level   Nível (info, error, warning).
     */
    public function log( string $message, string $level = 'info' ): void {
        if ( ! $this->debug ) {
            return;
        }
        wc_get_logger()->log( $level, $message, array( 'source' => 'infinitepay-hpos' ) );
    }

    /**
     * Logger incondicional — SEMPRE grava, independente do debug.
     *
     * Use para eventos críticos: pagamento confirmado/falho, erros de API,
     * rejeições de webhook, expiração de pedidos, etc.
     *
     * @since 5.2.0
     *
     * @param string $message Mensagem.
     * @param string $level   Nível (info, error, warning, critical).
     */
    public function log_critical( string $message, string $level = 'info' ): void {
        wc_get_logger()->log( $level, $message, array( 'source' => 'infinitepay-hpos' ) );
    }

    /**
     * Formata telefone para E.164 brasileiro.
     *
     * @param string $raw Telefone bruto.
     * @return string
     */
    private function format_phone_e164_br( string $raw ): string {
        $digits = preg_replace( '/\D+/', '', $raw );
        if ( ! $digits ) {
            return '';
        }
        if ( str_starts_with( $digits, '55' ) ) {
            return '+' . $digits;
        }
        return '+55' . $digits;
    }

    /**
     * Monta os itens do pedido para o payload.
     *
     * @param WC_Order $order Pedido.
     * @return array
     */
    private function build_items( WC_Order $order ): array {
        $order_id    = (string) $order->get_id();
        $total_cents = (int) round( ( (float) $order->get_total() ) * 100 );

        // Se send_items desabilitado, envia consolidado.
        if ( ! $this->send_items ) {
            return array(
                array(
                    'quantity'    => 1,
                    'price'       => $total_cents,
                    'description' => sprintf(
                        /* translators: %s: order ID */
                        __( 'Pedido #%s', 'wc-infinitepay-hpos' ),
                        $order_id
                    ),
                ),
            );
        }

        $items         = array();
        $items_total   = 0;

        foreach ( $order->get_items() as $item ) {
            $quantity   = max( 1, (int) $item->get_quantity() );
            $line_total = (int) round( ( (float) $item->get_total() ) * 100 );
            $unit_price = (int) round( $line_total / $quantity );

            $items[] = array(
                'quantity'    => $quantity,
                'price'       => $unit_price,
                'description' => mb_substr( $item->get_name(), 0, 200 ),
            );

            $items_total += $unit_price * $quantity;
        }

        // Frete como item separado.
        $shipping_total = (int) round( ( (float) $order->get_shipping_total() ) * 100 );
        if ( $shipping_total > 0 ) {
            $items[] = array(
                'quantity'    => 1,
                'price'       => $shipping_total,
                'description' => __( 'Frete', 'wc-infinitepay-hpos' ),
            );
            $items_total += $shipping_total;
        }

        // Taxas adicionais.
        $fee_total = 0;
        foreach ( $order->get_fees() as $fee ) {
            $fee_amount = (int) round( ( (float) $fee->get_total() ) * 100 );
            if ( 0 !== $fee_amount ) {
                $items[] = array(
                    'quantity'    => 1,
                    'price'       => $fee_amount,
                    'description' => mb_substr( $fee->get_name(), 0, 200 ),
                );
                $fee_total += $fee_amount;
            }
        }

        $items_total += $fee_total;

        // Descontos (cupons) — mostra como item descritivo com valor zero.
        // Os valores dos itens já refletem o desconto. Isto adiciona visibilidade.
        $coupons = $order->get_coupon_codes();
        if ( ! empty( $coupons ) ) {
            $discount_total = (int) round( ( (float) $order->get_discount_total() ) * 100 );
            if ( $discount_total > 0 ) {
                $items[] = array(
                    'quantity'    => 1,
                    'price'       => 0,
                    'description' => sprintf(
                        /* translators: 1: coupon codes 2: formatted discount */
                        __( 'Desconto aplicado (%1$s): -R$ %2$s', 'wc-infinitepay-hpos' ),
                        implode( ', ', array_map( 'strtoupper', $coupons ) ),
                        number_format( $discount_total / 100, 2, ',', '.' )
                    ),
                );
            }
        }

        // Ajuste de arredondamento: diferença entre total e soma dos itens.
        $diff = $total_cents - $items_total;
        if ( 0 !== $diff && ! empty( $items ) ) {
            $items[ count( $items ) - 1 ]['price'] += $diff;
        }

        // Fallback: se nenhum item, consolidar.
        if ( empty( $items ) ) {
            $items[] = array(
                'quantity'    => 1,
                'price'       => $total_cents,
                'description' => sprintf(
                    /* translators: %s: order ID */
                    __( 'Pedido #%s', 'wc-infinitepay-hpos' ),
                    $order_id
                ),
            );
        }

        return $items;
    }

    /**
     * Monta o payload para a API de checkout links.
     *
     * @param WC_Order $order Pedido.
     * @return array
     */
    private function build_payload( WC_Order $order ): array {
        $order_id = (string) $order->get_id();

        $payload = array(
            'handle'       => $this->handle,
            'order_nsu'    => $order_id,
            'redirect_url' => $this->get_return_url( $order ),
            'webhook_url'  => rest_url( 'wc-infinitepay/v1/webhook' ),
            'items'        => $this->build_items( $order ),
        );

        // Dados do cliente.
        if ( $this->send_customer ) {
            $name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
            $email = $order->get_billing_email();
            $phone = $this->format_phone_e164_br( (string) $order->get_billing_phone() );

            $customer = array();
            if ( $name ) {
                $customer['name'] = $name;
            }
            if ( $email && is_email( $email ) ) {
                $customer['email'] = $email;
            }
            if ( $phone ) {
                $customer['phone_number'] = $phone;
            }
            if ( ! empty( $customer ) ) {
                $payload['customer'] = $customer;
            }
        }

        // Endereço.
        if ( $this->send_address ) {
            $cep    = preg_replace( '/\D+/', '', (string) $order->get_billing_postcode() );
            $number = $order->get_meta( '_billing_number' );

            if ( ! $number ) {
                $a1 = (string) $order->get_billing_address_1();
                if ( preg_match( '/\b(\d{1,6})\b/', $a1, $m ) ) {
                    $number = $m[1];
                }
            }

            $complement = (string) $order->get_billing_address_2();
            $address    = array();

            if ( 8 === strlen( $cep ) ) {
                $address['cep'] = $cep;
            }
            if ( $number ) {
                $address['number'] = (string) $number;
            }
            if ( $complement ) {
                $address['complement'] = $complement;
            }
            if ( ! empty( $address ) ) {
                $payload['address'] = $address;
            }
        }

        /**
         * Filtra o payload antes de enviar à API InfinitePay.
         *
         * @since 5.0.0
         *
         * @param array    $payload Dados do payload.
         * @param WC_Order $order   Pedido WooCommerce.
         */
        return apply_filters( 'infinitepay_checkout_payload', $payload, $order );
    }

    /**
     * Redige PII do payload antes de gravar em log (LGPD/privacidade).
     *
     * @since 5.5.0
     *
     * @param array $payload Payload a redigir.
     * @return array Payload redigido.
     */
    private function redact_payload( array $payload ): array {
        $redacted = $payload;

        if ( isset( $redacted['customer'] ) && is_array( $redacted['customer'] ) ) {
            foreach ( self::REDACTED_CUSTOMER_FIELDS as $field ) {
                if ( isset( $redacted['customer'][ $field ] ) ) {
                    $redacted['customer'][ $field ] = '***';
                }
            }
        }

        if ( isset( $redacted['address'] ) && is_array( $redacted['address'] ) ) {
            foreach ( self::REDACTED_ADDRESS_FIELDS as $field ) {
                if ( isset( $redacted['address'][ $field ] ) ) {
                    $redacted['address'][ $field ] = '***';
                }
            }
        }

        // Segurança extra contra payloads achatados (campos PII no topo).
        foreach ( array_merge( self::REDACTED_CUSTOMER_FIELDS, self::REDACTED_ADDRESS_FIELDS ) as $field ) {
            if ( isset( $redacted[ $field ] ) && ! is_array( $redacted[ $field ] ) ) {
                $redacted[ $field ] = '***';
            }
        }

        return $redacted;
    }

    /**
     * Redige PII de uma string JSON (corpo de resposta da API) antes de logar.
     *
     * @since 5.5.0
     *
     * @param string $json String JSON.
     * @return string JSON redigido (ou original se não for JSON).
     */
    private function redact_json_string( string $json ): string {
        $data = json_decode( $json, true );
        if ( is_array( $data ) ) {
            return (string) wp_json_encode( $this->redact_payload( $data ) );
        }
        return $json;
    }

    /**
     * Cria checkout link na API com retry inteligente.
     *
     * Tenta até API_MAX_RETRIES vezes com backoff progressivo.
     * Timeout reduzido para não travar o checkout do cliente.
     *
     * @param array $payload Dados para a API.
     * @return string|WP_Error URL do checkout ou erro.
     */
    private function create_checkout_link( array $payload ) {
        $last_error = null;

        for ( $attempt = 1; $attempt <= self::API_MAX_RETRIES; $attempt++ ) {
            // Backoff: 0s na primeira, 1s na segunda.
            if ( $attempt > 1 ) {
                sleep( $attempt - 1 );
                $this->log( sprintf( 'Retry %d/%d para criar checkout link.', $attempt, self::API_MAX_RETRIES ) );
            }

            $response = wp_remote_post( self::API_LINKS, array(
                'timeout' => self::API_TIMEOUT,
                'headers' => array(
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ),
                'body'    => wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            ) );

            if ( is_wp_error( $response ) ) {
                $last_error = $response;
                $this->log_critical( 'API ERROR: Erro HTTP ao criar checkout link (tentativa ' . $attempt . '/' . self::API_MAX_RETRIES . '): ' . $response->get_error_message(), 'error' );
                continue;
            }

            $code = (int) wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );

            if ( $code >= 500 ) {
                // Erro do servidor — vale retry.
                $last_error = new WP_Error( 'server_error', 'HTTP ' . $code );
                $this->log_critical( 'API ERROR: Servidor InfinitePay retornou HTTP ' . $code . ' (tentativa ' . $attempt . '/' . self::API_MAX_RETRIES . ')', 'warning' );
                continue;
            }

            if ( $code < 200 || $code >= 300 ) {
                // Erro do cliente (4xx) — não adianta retry.
                $this->log_critical( 'API ERROR: InfinitePay retornou HTTP ' . $code . ': ' . mb_substr( $this->redact_json_string( $body ), 0, 500 ), 'error' );
                return new WP_Error( 'api_error', 'HTTP ' . $code );
            }

            $data = json_decode( $body, true );
            if ( ! is_array( $data ) || empty( $data['url'] ) ) {
                $this->log_critical( 'API ERROR: Resposta inesperada da InfinitePay (sem URL): ' . mb_substr( $this->redact_json_string( $body ), 0, 500 ), 'error' );
                return new WP_Error( 'bad_response', __( 'URL de checkout não retornada pela API.', 'wc-infinitepay-hpos' ) );
            }

            // Segurança: aceita apenas HTTPS (bloqueia esquemas javascript:/http: forjados).
            $checkout_url = (string) $data['url'];
            if ( ! str_starts_with( $checkout_url, 'https://' ) ) {
                $this->log_critical( 'API ERROR: URL de checkout retornada não é HTTPS: ' . mb_substr( $checkout_url, 0, 200 ), 'error' );
                return new WP_Error( 'insecure_url', __( 'URL de checkout inválida retornada pela API.', 'wc-infinitepay-hpos' ) );
            }

            $this->log( 'Checkout link criado com sucesso na tentativa ' . $attempt );
            return $checkout_url;
        }

        return $last_error ?? new WP_Error( 'unknown', __( 'Falha ao criar link de pagamento.', 'wc-infinitepay-hpos' ) );
    }

    /**
     * Processa o pagamento — chamado pelo WooCommerce no checkout.
     *
     * @param int $order_id ID do pedido.
     * @return array{result: string, redirect?: string}
     */
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wc_add_notice( __( 'Pedido não encontrado.', 'wc-infinitepay-hpos' ), 'error' );
            return array( 'result' => 'failure' );
        }

        try {
            $payload = $this->build_payload( $order );
            $this->log( 'Payload pedido #' . $order_id . ': ' . wp_json_encode( $this->redact_payload( $payload ) ) );

            $url = $this->create_checkout_link( $payload );
            if ( is_wp_error( $url ) ) {
                $this->log_critical( 'CHECKOUT FAIL: pedido #' . $order_id . ' — API retornou erro: ' . $url->get_error_message(), 'error' );
                throw new \Exception( $url->get_error_message() );
            }

            $order->update_status( 'on-hold', __( 'Aguardando pagamento InfinitePay.', 'wc-infinitepay-hpos' ) );
            $order->update_meta_data( '_infinitepay_handle', $this->handle );
            $order->update_meta_data( '_infinitepay_pending', 'yes' );
            $order->update_meta_data( '_infinitepay_checkout_url', esc_url_raw( $url ) );
            $order->save();

            $this->log_critical( 'CHECKOUT OK: pedido #' . $order_id . ' — link criado. Total: ' . $order->get_total() . ' ' . $order->get_currency() );

            // ⚠ Cart is NOT emptied here. The customer is being redirected to
            // InfinitePay's hosted page — if they close that tab without paying,
            // they'd return to an empty cart. Instead, we save the order ID in
            // the session and empty the cart ONLY after payment is confirmed
            // (via webhook or thankyou_page verification).
            if ( WC()->session ) {
                WC()->session->set( 'infinitepay_pending_order', $order_id );
            }

            /**
             * Dispara após o checkout link ser criado com sucesso.
             *
             * @since 5.0.0
             *
             * @param WC_Order $order Pedido.
             * @param string   $url   URL do checkout InfinitePay.
             */
            do_action( 'infinitepay_checkout_link_created', $order, $url );

            // Redirect through a branded transition page instead of
            // abruptly sending the customer to api.infinitepay.io.
            $transition_url = add_query_arg( array(
                'infinitepay-redirect' => '1',
                'order_id'             => $order_id,
                'nonce'                => wp_create_nonce( 'ip_redirect_' . $order_id ),
            ), home_url( '/' ) );

            return array(
                'result'   => 'success',
                'redirect' => $transition_url,
            );
        } catch ( \Exception $e ) {
            $this->log_critical( 'CHECKOUT FAIL: pedido #' . $order_id . ' — exceção: ' . $e->getMessage(), 'error' );
            $order->add_order_note(
                sprintf(
                    /* translators: %s: error message */
                    __( 'Erro InfinitePay: %s', 'wc-infinitepay-hpos' ),
                    $e->getMessage()
                )
            );
            wc_add_notice(
                __( 'Erro ao processar pagamento. Tente novamente.', 'wc-infinitepay-hpos' ),
                'error'
            );
            return array( 'result' => 'failure' );
        }
    }

    /**
     * Reembolso — InfinitePay não tem API de refund.
     *
     * Retorna WP_Error informando que o reembolso deve ser feito
     * manualmente no painel InfinitePay. Isso evita confusão no admin.
     *
     * @param int        $order_id ID do pedido.
     * @param float|null $amount   Valor.
     * @param string     $reason   Motivo.
     * @return WP_Error Sempre retorna erro explicativo.
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        return new WP_Error(
            'infinitepay_no_refund_api',
            __( 'Reembolsos automáticos não são suportados pela InfinitePay. Processe o reembolso manualmente no painel InfinitePay e marque como reembolsado aqui.', 'wc-infinitepay-hpos' )
        );
    }

    /**
     * Página de obrigado — verifica pagamento de forma inteligente.
     *
     * Fluxo otimizado:
     * 1. Se webhook já confirmou -> exibe sucesso imediatamente (sem API call)
     * 2. Se tem parâmetros de verificação -> chama API com cache transient
     * 3. Senão -> exibe mensagem de aguardando com auto-polling JS
     *
     * @since 4.0.0
     * @updated 5.3.0 — Order summary, auto-polling JS, WhatsApp support link.
     *
     * @param int $order_id ID do pedido.
     */
    public function thankyou_page( $order_id ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Valida que o pedido pertence a este gateway.
        if ( self::ID !== $order->get_payment_method() ) {
            return;
        }

        // 1. Já confirmado pelo webhook? Não precisa chamar API.
        if ( 'yes' !== $order->get_meta( '_infinitepay_pending' ) ) {
            // Cart was preserved until payment confirmed — safe to clear now.
            $this->maybe_clear_cart( $order );
            $this->render_confirmed_state( $order );
            return;
        }

        // 2. Tenta verificar via API se temos os parâmetros.
        $tx   = isset( $_GET['transaction_nsu'] ) ? sanitize_text_field( wp_unslash( $_GET['transaction_nsu'] ) ) : '';
        $slug = isset( $_GET['slug'] ) ? sanitize_text_field( wp_unslash( $_GET['slug'] ) ) : '';

        if ( $tx && $slug ) {
            $result = $this->verify_payment_cached( $order, $tx, $slug );

            if ( $result['paid'] ) {
                $this->maybe_clear_cart( $order );
                $this->render_confirmed_state( $order );
                return;
            }
        }

        // 3. Ainda pendente — aguardando webhook. Show enhanced pending state.
        $this->render_pending_state( $order );
    }

    /**
     * Renders the confirmed payment state on the thank-you page.
     *
     * @since 5.3.0
     *
     * @param WC_Order $order The confirmed order.
     */
    private function render_confirmed_state( WC_Order $order ): void {
        $receipt_url = $order->get_meta( '_infinitepay_receipt_url' );
        ?>
        <div class="woocommerce-message" style="display:flex;align-items:center;gap:12px">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="#0f834d"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
            <div>
                <strong><?php echo esc_html__( 'Pagamento confirmado!', 'wc-infinitepay-hpos' ); ?></strong>
                <br><span style="font-size:13px;color:#555"><?php echo esc_html__( 'Obrigado pela sua compra. Você receberá um email com os detalhes.', 'wc-infinitepay-hpos' ); ?></span>
                <?php if ( $receipt_url ) : ?>
                    <br><a href="<?php echo esc_url( $receipt_url ); ?>" target="_blank" rel="noopener" style="font-size:13px"><?php echo esc_html__( 'Ver comprovante', 'wc-infinitepay-hpos' ); ?></a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Renders the pending payment state with order summary, auto-polling,
     * and WhatsApp support link.
     *
     * @since 5.3.0
     *
     * @param WC_Order $order The pending order.
     */
    private function render_pending_state( WC_Order $order ): void {
        $order_id    = $order->get_id();
        $nonce       = wp_create_nonce( 'ip_check_' . $order_id );
        $ajax_url    = admin_url( 'admin-ajax.php' );
        $whatsapp    = 'https://wa.me/5511999927588?text=' . rawurlencode( 'Olá! Preciso de ajuda com meu pedido #' . $order_id );
        $checkout_url = $order->get_meta( '_infinitepay_checkout_url' );
        ?>
        <div id="ip-pending-wrap" style="max-width:520px">
            <!-- Spinner + status message -->
            <div id="ip-pending-status" class="woocommerce-info" style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
                <div id="ip-spinner" style="width:24px;height:24px;border:3px solid #e8e4df;border-top-color:#1a472a;border-radius:50%;animation:ip-spin .8s linear infinite;flex-shrink:0"></div>
                <div>
                    <strong><?php echo esc_html__( 'Confirmando pagamento...', 'wc-infinitepay-hpos' ); ?></strong>
                    <br><span style="font-size:13px;color:#555"><?php echo esc_html__( 'Esta página atualiza automaticamente. Não precisa recarregar.', 'wc-infinitepay-hpos' ); ?></span>
                </div>
            </div>

            <!-- Order summary -->
            <div style="background:#f8f6f3;border-radius:8px;padding:16px 20px;margin-bottom:16px">
                <h3 style="margin:0 0 12px;font-size:15px;color:#333"><?php echo esc_html__( 'Resumo do pedido', 'wc-infinitepay-hpos' ); ?></h3>
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <?php foreach ( $order->get_items() as $item ) : ?>
                    <tr>
                        <td style="padding:4px 0;color:#555"><?php echo esc_html( $item->get_name() ); ?> &times; <?php echo esc_html( $item->get_quantity() ); ?></td>
                        <td style="padding:4px 0;text-align:right;color:#333;white-space:nowrap"><?php echo wp_kses_post( wc_price( $item->get_total() ) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ( (float) $order->get_shipping_total() > 0 ) : ?>
                    <tr>
                        <td style="padding:4px 0;color:#555"><?php echo esc_html__( 'Frete', 'wc-infinitepay-hpos' ); ?></td>
                        <td style="padding:4px 0;text-align:right;color:#333"><?php echo wp_kses_post( wc_price( $order->get_shipping_total() ) ); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr style="border-top:1px solid #ddd">
                        <td style="padding:8px 0 0;font-weight:600;color:#333"><?php echo esc_html__( 'Total', 'wc-infinitepay-hpos' ); ?></td>
                        <td style="padding:8px 0 0;text-align:right;font-weight:600;color:#1a472a"><?php echo wp_kses_post( wc_price( $order->get_total() ) ); ?></td>
                    </tr>
                </table>
            </div>

            <!-- Actions row -->
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;font-size:13px">
                <?php if ( $checkout_url ) : ?>
                    <a href="<?php echo esc_url( $checkout_url ); ?>" style="color:#1a472a;text-decoration:underline"><?php echo esc_html__( 'Voltar ao pagamento', 'wc-infinitepay-hpos' ); ?></a>
                    <span style="color:#ddd">|</span>
                <?php endif; ?>
                <a href="<?php echo esc_url( $whatsapp ); ?>" target="_blank" rel="noopener" style="color:#1a472a;text-decoration:underline"><?php echo esc_html__( 'Precisa de ajuda? WhatsApp', 'wc-infinitepay-hpos' ); ?></a>
            </div>
        </div>

        <style>
            @keyframes ip-spin { to { transform: rotate(360deg); } }
        </style>

        <script>
        (function() {
            var orderId  = <?php echo (int) $order_id; ?>;
            var nonce    = <?php echo wp_json_encode( $nonce ); ?>;
            var ajaxUrl  = <?php echo wp_json_encode( $ajax_url ); ?>;
            var maxPolls = 60; // 5 minutes (60 * 5s)
            var polls    = 0;

            function checkStatus() {
                if ( polls >= maxPolls ) {
                    var el = document.getElementById('ip-pending-status');
                    if ( el ) {
                        el.innerHTML = '<div style="color:#856404"><strong>Tempo esgotado.</strong> ' +
                            'Se você já pagou, o email de confirmação chegará em breve. ' +
                            'Caso contrário, <a href="' + <?php echo wp_json_encode( $checkout_url ?: '' ); ?> + '">clique aqui para tentar novamente</a>.</div>';
                        el.className = 'woocommerce-info';
                    }
                    return;
                }
                polls++;

                var fd = new FormData();
                fd.append( 'action', 'infinitepay_check_status' );
                fd.append( 'order_id', orderId );
                fd.append( 'nonce', nonce );

                fetch( ajaxUrl, { method: 'POST', body: fd } )
                    .then( function( r ) { return r.json(); } )
                    .then( function( data ) {
                        if ( data.success && data.data && data.data.paid ) {
                            // Payment confirmed — reload to show confirmed state.
                            window.location.reload();
                        } else {
                            setTimeout( checkStatus, 5000 );
                        }
                    } )
                    .catch( function() {
                        setTimeout( checkStatus, 10000 ); // Retry slower on error.
                    } );
            }

            // Start polling after 3 seconds (give webhook time to arrive).
            setTimeout( checkStatus, 3000 );
        })();
        </script>
        <?php
    }

    /**
     * Clears the cart only after payment is confirmed.
     *
     * Cart is deliberately NOT emptied in process_payment() because the
     * customer is redirected to InfinitePay's hosted checkout — if they
     * close that tab without paying, they'd return to an empty cart.
     * This method empties the cart only when called from the thank-you
     * page after payment confirmation (via webhook or API verification).
     *
     * @since 5.3.0
     *
     * @param WC_Order $order The confirmed order.
     */
    private function maybe_clear_cart( WC_Order $order ): void {
        if ( ! WC()->cart ) {
            return;
        }

        // Only clear if cart is not already empty.
        if ( WC()->cart->is_empty() ) {
            return;
        }

        WC()->cart->empty_cart();
        $this->log( 'Cart cleared after payment confirmation for order #' . $order->get_id() );

        // Clean up session marker.
        if ( WC()->session ) {
            WC()->session->set( 'infinitepay_pending_order', null );
        }
    }

    /**
     * Verifica pagamento com cache transient (evita chamadas duplicadas).
     *
     * Se o cliente recarregar a thankyou page, o transient retorna
     * o resultado anterior sem chamar a API novamente.
     *
     * @param WC_Order $order Pedido.
     * @param string   $tx    Transaction NSU.
     * @param string   $slug  Invoice slug.
     * @return array{ok: bool, paid: bool}
     */
    private function verify_payment_cached( WC_Order $order, string $tx, string $slug ): array {
        $cache_key = 'ip_check_' . $order->get_id() . '_' . md5( $tx . $slug );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            $this->log( 'Verificação do pedido #' . $order->get_id() . ' servida do cache.' );
            return $cached;
        }

        $result = $this->payment_check( (string) $order->get_id(), $tx, $slug );

        // Cache por 5 minutos (sucesso ou falha).
        set_transient( $cache_key, $result, 5 * MINUTE_IN_SECONDS );

        if ( $result['paid'] ) {
            // Guarda contra dupla-confirmação (abas concorrentes ou corrida com o
            // webhook): relê o pedido do banco e só completa se ainda não finalizado.
            $fresh = wc_get_order( $order->get_id() );
            if ( $fresh && in_array( $fresh->get_status(), array( 'processing', 'completed' ), true ) ) {
                $this->log( 'Pedido #' . $order->get_id() . ' já finalizado; confirmação duplicada ignorada (thankyou).' );
                return $result;
            }

            $order->payment_complete( $tx );
            $order->delete_meta_data( '_infinitepay_pending' );
            $order->update_meta_data( '_infinitepay_slug', $slug );
            $order->save();
            $this->log_critical( 'PAYMENT OK: pedido #' . $order->get_id() . ' confirmado via thankyou page. TX: ' . $tx . '. Total: ' . $order->get_total() . ' ' . $order->get_currency() );
        }

        return $result;
    }

    /**
     * Chama a API de payment check.
     *
     * @param string $order_nsu       NSU do pedido.
     * @param string $transaction_nsu NSU da transação.
     * @param string $slug            Slug do invoice.
     * @return array{ok: bool, paid: bool, data?: array}
     */
    public function payment_check( string $order_nsu, string $transaction_nsu, string $slug ): array {
        $response = wp_remote_post( self::API_PAYMENTCHECK, array(
            'timeout' => self::API_TIMEOUT,
            'headers' => array(
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ),
            'body'    => wp_json_encode( array(
                'handle'          => $this->handle,
                'order_nsu'       => $order_nsu,
                'transaction_nsu' => $transaction_nsu,
                'slug'            => $slug,
            ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            $this->log( 'Payment check erro: ' . $response->get_error_message(), 'error' );
            return array( 'ok' => false, 'paid' => false );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            $this->log( 'Payment check HTTP ' . $code, 'warning' );
            return array( 'ok' => false, 'paid' => false );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        $paid = ! empty( $data['success'] ) && ! empty( $data['paid'] );

        return array(
            'ok'   => true,
            'paid' => $paid,
            'data' => $data,
        );
    }
}
