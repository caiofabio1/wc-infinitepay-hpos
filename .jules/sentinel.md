# Sentinel Security Journal

## 2024-05-22 - PII Exposure in Application Logs
- **Vulnerability:** Potential exposure of Personally Identifiable Information (PII) in WooCommerce/InfinitePay logs. Request payloads and API responses could contain customer names, emails, phones, and address details.
- **Learning:** Existing redaction was insufficient and hardcoded. Logging raw API response bodies can leak data reflected back by the service (e.g., in error messages).
- **Prevention:** Centralized redacted field lists in class constants. Implemented a robust `redact_payload` method and a `redact_json_string` helper to clean all data before logging. Added unit tests to verify redaction coverage.
