# AGENTS.md - WooCommerce InfinitePay Gateway

## Project Overview

WooCommerce payment gateway plugin integrating with InfinitePay (Brazilian payment processor). Customers are redirected to InfinitePay's hosted checkout to pay via Card or Pix, then payment is confirmed via webhook or thank-you page verification.

## Architecture

```
wc-infinitepay-hpos.php                  → Bootstrap, HPOS + Blocks compat declarations
includes/
  class-wc-gateway-infinitepay.php       → Payment gateway (extends WC_Payment_Gateway)
  class-infinitepay-webhook.php          → REST API webhook handler
  class-infinitepay-blocks.php           → WooCommerce Blocks Checkout integration
assets/js/
  blocks.js                              → JS for Blocks Checkout registration
  blocks.asset.php                       → Script dependencies metadata
uninstall.php                            → Cleanup on plugin removal
readme.txt                               → WordPress.org format
```

## Payment Flow

```
1. Customer selects InfinitePay at checkout
2. Plugin calls POST https://api.infinitepay.io/invoices/public/checkout/links
3. Customer is redirected to InfinitePay hosted checkout
4. After payment, InfinitePay sends webhook to /wp-json/wc-infinitepay/v1/webhook
5. Fallback: thank-you page calls POST .../payment_check to verify
```

## InfinitePay API Details

### Endpoints (public, no API key required)

| Endpoint | Method | Purpose |
|---|---|---|
| `https://api.infinitepay.io/invoices/public/checkout/links` | POST | Create checkout link |
| `https://api.infinitepay.io/invoices/public/checkout/payment_check` | POST | Verify payment status |

### Checkout Link Payload

```json
{
  "handle": "merchant-handle",
  "order_nsu": "123",
  "redirect_url": "https://store.com/checkout/order-received/123/",
  "webhook_url": "https://store.com/wp-json/wc-infinitepay/v1/webhook",
  "items": [
    { "quantity": 1, "price": 15000, "description": "Product Name" }
  ],
  "customer": { "name": "...", "email": "...", "phone_number": "+5511999999999" },
  "address": { "cep": "04101300", "number": "123", "complement": "Apt 1" }
}
```

- `price` is in **centavos** (BRL cents). R$ 150.00 = 15000
- `phone_number` must be E.164 format with +55 prefix
- `handle` is the merchant's InfiniteTag (no $ sign)

### Webhook Payload (received from InfinitePay)

```json
{
  "order_nsu": "123",
  "transaction_nsu": "abc123def456",
  "invoice_slug": "slug-identifier",
  "receipt_url": "https://infinitepay.io/receipt/..."
}
```

### Payment Check Payload (sent to verify)

```json
{
  "handle": "merchant-handle",
  "order_nsu": "123",
  "transaction_nsu": "abc123def456",
  "slug": "slug-identifier"
}
```

Response: `{ "success": true, "paid": true }` or `{ "success": false, "paid": false }`

## Security Measures

- **Webhook secret**: validated via `hash_equals()` (timing-safe) from `X-Webhook-Secret` or `Authorization: Bearer` header
- **Rate limiting**: 30 requests/minute per IP via WordPress transients
- **Anti-replay**: transaction NSU tracked for 1 hour via transient
- **Order ownership**: webhook validates `payment_method === infinitepay_hpos`
- **Input sanitization**: all inputs use `sanitize_text_field()`, `esc_url_raw()`, `(int)` casts

## WordPress/WooCommerce Standards

- **HPOS**: Uses `$order->get_meta()` / `$order->update_meta_data()` everywhere. Never `get_post_meta()`.
- **Escaping**: All output uses `esc_html()`, `esc_html__()`, `esc_url()`
- **i18n**: Text domain `wc-infinitepay-hpos`, all strings wrapped in `__()` or `esc_html__()`
- **No direct SQL**: All DB access through WooCommerce API
- **PHP 8.2+**: All custom properties are explicitly typed

## Order Meta Keys

| Key | Purpose |
|---|---|
| `_infinitepay_handle` | Merchant handle used |
| `_infinitepay_pending` | `'yes'` while awaiting payment |
| `_infinitepay_checkout_url` | Generated checkout link |
| `_infinitepay_slug` | Invoice slug from InfinitePay |
| `_infinitepay_receipt_url` | Receipt URL (from webhook) |

## Extensibility Hooks

| Hook | Type | Parameters |
|---|---|---|
| `infinitepay_checkout_payload` | filter | `$payload, $order` |
| `infinitepay_checkout_link_created` | action | `$order, $url` |
| `infinitepay_webhook_payment_confirmed` | action | `$order, $tx, $body` |

## Constants

| Constant | Value |
|---|---|
| `INFINITEPAY_WC_VERSION` | Current version |
| `INFINITEPAY_WC_FILE` | Main plugin file path |
| `INFINITEPAY_WC_PATH` | Plugin directory path |

## Testing Notes

- **Automated Tests**: Unit tests are located in the `tests/` directory.
- **Running Tests**:
  1. Install dependencies: `composer install`
  2. Run PHPUnit: `./vendor/bin/phpunit`
- Manual testing requires an InfinitePay account.
- The InfinitePay API is public (no auth tokens for checkout links), authenticated only by the merchant `handle`.
- Webhook testing can be done with tools like `curl` or Postman sending POST to the webhook URL.
- For local development, use a tunnel (ngrok, Cloudflare Tunnel) so InfinitePay can reach the webhook.

## Known API Limitations

- **No refund API**: InfinitePay does not offer a refund endpoint. Refunds must be done manually in their dashboard.
- **No itemized receipt**: The checkout page shows items but the API doesn't guarantee how they're displayed.
- **Rate limits**: The InfinitePay API has undocumented rate limits. The plugin uses 10s timeout with 2 retries.

## Compatibility Targets

- WordPress 6.0+
- WooCommerce 7.0+
- PHP 7.4+
- HPOS (custom_order_tables)
- WC Blocks Checkout (cart_checkout_blocks)
