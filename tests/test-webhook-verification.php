<?php

// Mocking WordPress environment
define('ABSPATH', __DIR__ . '/');

class WP_REST_Request {
    private $headers = [];
    public function __construct($headers = []) {
        $this->headers = $headers;
    }
    public function get_header($name) {
        return $this->headers[$name] ?? null;
    }
}

class WC_Gateway_InfinitePay_HPOS {
    public $webhook_secret = '';
    public $logs = [];
    public function log($message, $level = 'info') {
        $this->logs[] = ['message' => $message, 'level' => $level];
        echo "LOG [$level]: $message\n";
    }
}

// Load the class
require_once dirname(__DIR__) . '/includes/class-infinitepay-webhook.php';

function test_scenario($name, $configured_secret, $headers, $expected_result) {
    echo "Testing Scenario: $name\n";
    $gateway = new WC_Gateway_InfinitePay_HPOS();
    $gateway->webhook_secret = $configured_secret;
    $request = new WP_REST_Request($headers);

    $class = new ReflectionClass('InfinitePay_Webhook');
    $method = $class->getMethod('validate_secret');
    $method->setAccessible(true);

    $result = $method->invoke(null, $request, $gateway);

    if ($result === $expected_result) {
        echo "PASS: Result matches expected " . ($expected_result ? 'true' : 'false') . "\n";
    } else {
        echo "FAIL: Expected " . ($expected_result ? 'true' : 'false') . " but got " . ($result ? 'true' : 'false') . "\n";
        exit(1);
    }

    if ($configured_secret === '') {
        $found_error_log = false;
        foreach ($gateway->logs as $log) {
            if ($log['level'] === 'error' && strpos($log['message'], 'Webhook secret não configurado') !== false) {
                $found_error_log = true;
                break;
            }
        }
        if (!$found_error_log) {
            echo "FAIL: Expected error log when secret is empty\n";
            exit(1);
        }
    }
    echo "---------------------------\n";
}

// Scenario 1: Empty secret
test_scenario('Empty secret', '', [], false);

// Scenario 2: Matching secret (X-Webhook-Secret)
test_scenario('Matching secret (X-Webhook-Secret)', 'my_secret', ['X-Webhook-Secret' => 'my_secret'], true);

// Scenario 3: Matching secret (Authorization Bearer)
test_scenario('Matching secret (Authorization Bearer)', 'my_secret', ['Authorization' => 'Bearer my_secret'], true);

// Scenario 4: Non-matching secret
test_scenario('Non-matching secret', 'my_secret', ['X-Webhook-Secret' => 'wrong_secret'], false);

// Scenario 5: Missing header with configured secret
test_scenario('Missing header', 'my_secret', [], false);

echo "All tests passed successfully!\n";
