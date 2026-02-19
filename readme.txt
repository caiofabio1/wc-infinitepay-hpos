=== WooCommerce InfinitePay Gateway ===
Contributors: infinitepaywoo
Tags: woocommerce, payment gateway, infinitepay, pix, cartão de crédito, brasil
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 5.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Aceite pagamentos via Cartão e Pix com InfinitePay no seu WooCommerce. Checkout seguro, webhook REST e compatibilidade total com HPOS e Blocks.

== Description ==

Integre sua loja WooCommerce com a **InfinitePay** para aceitar pagamentos via **Cartão de Crédito** e **Pix** de forma simples e segura.

O plugin cria um **link de checkout** na InfinitePay e redireciona o cliente para uma página de pagamento segura. Após o pagamento, o pedido é confirmado automaticamente via **webhook** ou verificação na página de obrigado.

= Recursos =

* **Cartão e Pix** — Aceite os dois métodos de pagamento mais usados no Brasil
* **Checkout Seguro** — O cliente paga na página da InfinitePay (PCI DSS compliant)
* **Webhook REST** — Confirmação instantânea com validação de segurança
* **Rate Limiting** — Proteção contra flood no webhook (30 req/min por IP)
* **Anti-Replay** — Previne processamento duplicado de transações
* **Retry Inteligente** — Tentativas automáticas com backoff progressivo
* **HPOS Compatível** — Funciona com High-Performance Order Storage do WooCommerce
* **Blocks Checkout** — Compatível com o novo checkout por blocos do WooCommerce
* **Itens Detalhados** — Envia nome e quantidade de cada produto para o checkout
* **PHP 8.2+** — Propriedades tipadas, sem warnings de propriedades dinâmicas
* **Extensível** — Filtros e actions para customização por desenvolvedores
* **Logs Detalhados** — Debug opcional com logs em WooCommerce > Status > Logs

= Requisitos =

* WordPress 6.0+
* WooCommerce 7.0+
* PHP 7.4+
* Conta InfinitePay com Handle (InfiniteTag) configurado

= Como Funciona =

1. O cliente seleciona "Cartão/Pix (InfinitePay)" no checkout
2. O plugin cria um link de pagamento via API InfinitePay
3. O cliente é redirecionado para a página segura de pagamento
4. Após pagar, o webhook confirma o pedido automaticamente
5. Como fallback, a página de obrigado também verifica o pagamento

== Installation ==

1. Faça upload da pasta `wc-infinitepay-hpos` para `/wp-content/plugins/`
2. Ative o plugin em **Plugins > Plugins Instalados**
3. Vá em **WooCommerce > Configurações > Pagamentos > InfinitePay (HPOS)**
4. Insira seu **Handle** (InfiniteTag) — encontre no app InfinitePay
5. Configure o **Webhook Secret** para segurança (recomendado)
6. Copie a **Webhook URL** e configure no painel InfinitePay
7. Ative o gateway e pronto!

== Frequently Asked Questions ==

= Onde encontro meu Handle (InfiniteTag)? =

No app InfinitePay: **Configurações > Link integrado**. O Handle é o identificador da sua conta (sem o símbolo $).

= É necessário configurar o Webhook Secret? =

Altamente recomendado. Sem o webhook secret, qualquer requisição ao endpoint pode confirmar pedidos. Configure a mesma chave secreta no plugin e no painel InfinitePay.

= O plugin suporta reembolsos automáticos? =

Não. A API InfinitePay não oferece endpoint de reembolso. Reembolsos devem ser processados manualmente no painel InfinitePay e marcados como reembolsados no WooCommerce.

= Funciona com o checkout por blocos? =

Sim. O plugin é compatível tanto com o checkout clássico (shortcode) quanto com o novo checkout por blocos do WooCommerce.

= Funciona com HPOS (High-Performance Order Storage)? =

Sim. O plugin é 100% compatível com HPOS. Todos os acessos a dados de pedidos usam a API do WooCommerce (`$order->get_meta()`, `wc_get_order()`), sem nenhuma query direta a `wp_postmeta`.

= Os produtos aparecem no checkout InfinitePay? =

Sim, se a opção "Itens detalhados" estiver habilitada (padrão). Cada produto aparece com nome e quantidade. Frete e taxas também são enviados separadamente.

= É compatível com PHP 8.2+? =

Sim. Todas as propriedades são tipadas explicitamente, eliminando deprecation warnings de propriedades dinâmicas.

== Changelog ==

= 5.0.0 =
* Novo: Compatibilidade com WooCommerce Blocks Checkout
* Novo: Itens detalhados no checkout InfinitePay (produtos + frete + taxas)
* Novo: Aviso no admin quando Webhook Secret não está configurado
* Novo: Filtro `infinitepay_checkout_payload` para customizar payload
* Novo: Action `infinitepay_checkout_link_created` após criar checkout link
* Novo: Action `infinitepay_webhook_payment_confirmed` após confirmar pagamento
* Novo: Validação de ownership no webhook (pedido deve pertencer ao gateway)
* Novo: Validação de gateway na thank-you page
* Novo: Arquivo `uninstall.php` para limpeza ao desinstalar
* Novo: Suporte a traduções com `load_plugin_textdomain()`
* Melhoria: Refund retorna WP_Error explicativo (em vez de sucesso silencioso)
* Melhoria: Removido `'refunds'` de supports (não há API de refund)
* Melhoria: Opção configurável para envio de itens detalhados vs consolidado
* Melhoria: Branding genérico para distribuição

= 4.0.0 =
* Timeout reduzido (10s) com retry inteligente e backoff
* Thankyou page verifica meta antes de chamar API
* Cache transient evita chamadas redundantes
* Typo HPOS corrigido (era HP0S com zero)
* Classe separada como plugin independente
* Propriedades PHP 8.2+ compatíveis
* Rate limiting e anti-replay no webhook
* Validação de secret com hash_equals (timing-safe)

== Upgrade Notice ==

= 5.0.0 =
Atualização recomendada. Inclui melhorias de segurança (validação de ownership no webhook), compatibilidade com Blocks Checkout, e itens detalhados no checkout InfinitePay.

== Hooks para Desenvolvedores ==

= Filtros =

**`infinitepay_checkout_payload`** — Modifica o payload antes de enviar à API.

`add_filter( 'infinitepay_checkout_payload', function( $payload, $order ) {
    // Customizar payload
    return $payload;
}, 10, 2 );`

= Actions =

**`infinitepay_checkout_link_created`** — Após o checkout link ser criado.

`add_action( 'infinitepay_checkout_link_created', function( $order, $url ) {
    // Ação após criação do link
}, 10, 2 );`

**`infinitepay_webhook_payment_confirmed`** — Após confirmação via webhook.

`add_action( 'infinitepay_webhook_payment_confirmed', function( $order, $tx, $body ) {
    // Ação pós-confirmação
}, 10, 3 );`
