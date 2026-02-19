---
name: Monthly Maintenance
about: Routine monthly update check
labels: maintenance
---

## Monthly Maintenance - [MÊS/ANO]

Run all checks below and create a single PR with all changes.

### 1. Version Compatibility Check

- [ ] Check the latest stable WooCommerce version at https://wordpress.org/plugins/woocommerce/
- [ ] Check the latest stable WordPress version at https://wordpress.org/download/
- [ ] Update `WC tested up to` in `wc-infinitepay-hpos.php` plugin header if outdated
- [ ] Update `Tested up to` in `readme.txt` if outdated
- [ ] Update `Requires PHP` if WooCommerce raised its minimum PHP version

### 2. Deprecated Functions Audit

- [ ] Search all PHP files for any WordPress or WooCommerce functions that were deprecated in recent versions
- [ ] Check if `WC_Payment_Gateway` class has any new required methods or changed signatures
- [ ] Check if `AbstractPaymentMethodType` (Blocks integration) has any new required methods
- [ ] Verify `register_rest_route` and REST API patterns are still current
- [ ] Check if `FeaturesUtil::declare_compatibility` API changed

### 3. Security Review

- [ ] Check if any `sanitize_*`, `esc_*`, or `wp_*` functions were deprecated or have new recommended replacements
- [ ] Verify `hash_equals` is still the recommended timing-safe comparison
- [ ] Check if WordPress added new nonce or capability patterns for REST endpoints
- [ ] Review `wp_remote_post` - check if there are new recommended HTTP API patterns

### 4. Blocks Checkout Compatibility

- [ ] Check if `@woocommerce/blocks-registry` API changed (registerPaymentMethod signature)
- [ ] Check if `wc.wcSettings.getSetting` pattern is still valid
- [ ] Verify `woocommerce_blocks_payment_method_type_registration` hook still exists
- [ ] Check if new required `supports` features were added for payment methods

### 5. Code Quality

- [ ] Check all strings use the text domain `wc-infinitepay-hpos`
- [ ] Verify no PHP 8.x deprecation warnings (dynamic properties, implicit nullable, etc.)
- [ ] Check if any class property types should be updated for newer PHP versions
- [ ] Review `readme.txt` changelog - add entry if any changes were made

### 6. Deliverables

If any changes were needed:
- [ ] Bump `INFINITEPAY_WC_VERSION` in `wc-infinitepay-hpos.php`
- [ ] Bump `Stable tag` in `readme.txt`
- [ ] Bump `version` in `assets/js/blocks.asset.php`
- [ ] Add changelog entry in `readme.txt` under a new version section
- [ ] Create PR with title: `chore: monthly maintenance [MÊS/ANO]`

If no changes were needed:
- [ ] Comment on this issue: "All checks passed, no changes needed for [MÊS/ANO]"
- [ ] Close this issue
