=== WooCommerce InfinitePay Gateway ===
Contributors: infinitepaywoo
Tags: woocommerce, payment gateway, infinitepay, pix, cartão de crédito, brasil
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 5.5.0
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

= 5.5.0 =
* Privacidade/LGPD: Redação de PII (nome, e-mail, telefone, documento, endereço) nos logs de payload e de resposta da API — antes o payload era gravado em texto puro. (reconciliação Jules PR #38)
* Segurança/confiabilidade: Webhook valida o secret ANTES do rate limit; requisições autenticadas bypassam o limite por IP — evita que IPs compartilhados da InfinitePay sejam bloqueados durante um flood (Denial-of-Wallet). (reconciliação Jules PR #22/#32)

= 5.4.0 =
* Segurança: Webhook agora REJEITA requisições quando o Webhook Secret não está configurado (fail-closed), em vez de aceitar. Configure o secret para receber confirmações.
* Segurança: Anti-replay atômico via object cache (Redis/Memcached) quando disponível — fecha a janela de corrida entre webhooks simultâneos com a mesma transação.
* Segurança: Validação de esquema HTTPS na URL de checkout retornada pela API (bloqueia esquemas forjados).
* Fix: Guarda contra dupla-confirmação na página de obrigado (abas concorrentes ou corrida com o webhook) — relê o pedido antes de completar.
* Refatoração: Constantes `ID` e `REST_NAMESPACE` eliminam strings mágicas e o risco de divergência.

= 5.3.0 =
* Novo: Página de transição com a marca da loja antes de redirecionar ao pagamento.
* Novo: Página de obrigado com resumo do pedido, atualização automática (polling) do status e link de suporte por WhatsApp.
* Melhoria: Carrinho esvaziado apenas APÓS a confirmação do pagamento (não antes) — cliente que fecha a aba sem pagar não perde o carrinho.
* Melhoria: Descrição rica no checkout clássico (bandeiras de cartão, PIX, parcelamento, aviso de redirecionamento seguro).
* Novo: `icon_url` no checkout por blocos.

= 5.2.0 =
* Novo: `log_critical()` — eventos críticos (pagamentos, erros de API, segurança) SEMPRE gravam no log, independente do toggle de debug
* Novo: Endpoint REST `/wc-infinitepay/v1/audit` — diagnóstico remoto autenticado via WC API keys (config, cron, Action Scheduler, pedidos recentes, ambiente)
* Melhoria: Webhook handler agora registra IP do cliente em todas as rejeições de segurança (rate limit, secret inválido, body inválido, order mismatch)
* Melhoria: Cron de expiração agora SEMPRE loga cancelamentos (antes só com debug=yes)
* Melhoria: `process_payment()` loga sucesso e falha na criação de checkout link
* Melhoria: Logs de API incluem tentativa/total e código HTTP para debug mais rápido
* Melhoria: Confirmação de pagamento (webhook e thankyou) inclui total e moeda no log

= 5.1.0 =
* Fix: Polyfill `str_starts_with()` para compatibilidade real com PHP 7.4
* Fix: Detecção de IP real no rate limiting (suporte a Cloudflare e reverse proxy)
* Fix: Sanitização de `order_nsu` no webhook com `absint()`
* Novo: Auto-cancelamento de pedidos on-hold expirados via WP-Cron (configurável)
* Novo: Tratamento de webhooks de pagamento falho/cancelado/expirado
* Novo: Ícone de pagamento (cartão + pix) exibido no checkout
* Novo: Desconto de cupom visível como line item no checkout InfinitePay
* Novo: Campo "Expiração de pedidos" no admin (padrão: 48 horas, 0 para desativar)
* Novo: Action `infinitepay_webhook_payment_failed` para pagamentos recusados
* Novo: Filtro `infinitepay_gateway_icon` para customizar ícone do gateway
* Melhoria: Limpeza do cron no `uninstall.php`

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

= 5.5.0 =
Privacidade e confiabilidade: redação de PII nos logs (LGPD) e webhook que prioriza autenticação sobre rate limit (evita bloquear confirmações legítimas da InfinitePay). Melhorias conciliadas das revisões automáticas (Jules).

= 5.4.0 =
Hardening de segurança do webhook: fail-closed sem secret, anti-replay atômico e validação HTTPS da URL de checkout. Inclui as melhorias de UX da 5.3.0 (página de transição, resumo na thankyou, carrinho preservado até o pagamento).

= 5.2.0 =
Observabilidade: eventos críticos (pagamentos, erros API, segurança) agora SEMPRE gravam no log WooCommerce, mesmo com debug desligado. Novo endpoint REST de auditoria para monitoramento remoto via API.

= 5.1.0 =
Correções de segurança: sanitização de input no webhook, detecção de IP real para Cloudflare/proxy, compatibilidade PHP 7.4. Novos recursos: auto-cancel de pedidos expirados, ícone no checkout, tratamento de pagamentos falhos.

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
