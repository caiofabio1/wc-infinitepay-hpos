## 2023-10-27 - [Fail Open Webhook Authentication]
**Vulnerability:** The webhook verification code allowed requests to pass if the webhook secret was not configured in the settings. This "fail open" configuration could allow an attacker to forge a payment confirmation if the merchant had not properly configured the plugin.
**Learning:** Returning `true` during backward-compatibility fallback logic on critical verification features (like webhooks in a payment gateway) constitutes a critical vulnerability. Security should never fail open.
**Prevention:** If required security configurations are missing, the process should fail closed (return `false`) and log an explicit error informing the merchant of the missing configuration.
