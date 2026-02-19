<?php
// Mock WordPress environment
define( 'ABSPATH', __DIR__ . '/../' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );

// Mock Functions
$transients = [];

function get_transient( $key ) {
    global $transients;
    return isset( $transients[ $key ] ) ? $transients[ $key ] : false;
}

function set_transient( $key, $value, $expiration = 0 ) {
    global $transients;
    $transients[ $key ] = $value;
    return true;
}

function sanitize_text_field( $str ) {
    return trim( strip_tags( $str ) );
}

function esc_url_raw( $url ) {
    return $url;
}

function wc_get_logger() {
    return new class {
        public function log( $level, $message, $context = [] ) {
            // echo "[LOG $level] $message\n";
        }
    };
}

class WC_Order {
    public function get_payment_method() { return 'infinitepay_hpos'; }
    public function get_meta( $key ) { return 'yes'; }
    public function get_status() { return 'pending'; }
    public function payment_complete( $tx ) {}
    public function update_meta_data( $key, $value ) {}
    public function delete_meta_data( $key ) {}
    public function add_order_note( $note ) {}
    public function save() {}
    public function get_id() { return 1; }
}

function wc_get_order( $id ) {
    return new WC_Order();
}

function do_action( $hook, ...$args ) {}

function __($text, $domain) { return $text; }

// Mock Classes
class WP_REST_Request {
    private $headers = [];
    private $body = [];

    public function get_header( $key ) {
        return isset( $this->headers[ $key ] ) ? $this->headers[ $key ] : null;
    }
    public function set_header( $key, $value ) {
        $this->headers[ $key ] = $value;
    }
    public function get_json_params() {
        return $this->body;
    }
    public function set_body( $body ) {
        $this->body = $body;
    }
}

class WP_REST_Response {
    public $data;
    public $status;
    public function __construct( $data, $status ) {
        $this->data = $data;
        $this->status = $status;
    }
}

class WC_Payment_Gateway {} // Base class needed if extended

class WC_Gateway_InfinitePay_HPOS extends WC_Payment_Gateway {
    public $webhook_secret = 'secret123';
    public function log( $msg, $level = 'info' ) {}
}

// Mock WC() function
function WC() {
    return new class {
        public function payment_gateways() {
            return new class {
                public function payment_gateways() {
                    return [ 'infinitepay_hpos' => new WC_Gateway_InfinitePay_HPOS() ];
                }
            };
        }
    };
}

// Include the class to test
require_once __DIR__ . '/../includes/class-infinitepay-webhook.php';

// Test Scenario
echo "Starting test...\n";

// Set shared IP
$_SERVER['REMOTE_ADDR'] = '1.2.3.4';

// 1. Send 30 BAD requests (no auth)
echo "Sending 30 unauthenticated requests...\n";
for ( $i = 0; $i < 30; $i++ ) {
    $req = new WP_REST_Request();
    // No auth headers
    $res = InfinitePay_Webhook::handle( $req );

    // We expect 401 (unauthorized) for the first 30, but count increments.
    // Wait, currently handle checks rate limit FIRST.
    // So 1st -> Rate limit OK -> Secret check FAIL -> 401.
    // 30th -> Rate limit OK -> Secret check FAIL -> 401.
    // 31st -> Rate limit FAIL -> 429.

    if ( $res->status !== 401 && $res->status !== 200 ) {
         echo "Unexpected status at request $i: " . $res->status . "\n";
    }
}

// 2. Send 31st BAD request
echo "Sending 31st unauthenticated request...\n";
$req = new WP_REST_Request();
$res = InfinitePay_Webhook::handle( $req );
if ( $res->status === 429 ) {
    echo "SUCCESS: 31st unauthenticated request blocked (429).\n";
} else {
    echo "FAILURE: 31st unauthenticated request NOT blocked (status: " . $res->status . ").\n";
}

// 3. Send Authenticated Request (Simulate legitimate traffic from same IP)
echo "Sending authenticated request from same IP...\n";
$req = new WP_REST_Request();
$req->set_header( 'X-Webhook-Secret', 'secret123' ); // Valid secret
$req->set_body( [ 'order_nsu' => '1', 'transaction_nsu' => 'tx1', 'invoice_slug' => 'slug1' ] );

$res = InfinitePay_Webhook::handle( $req );

if ( $res->status === 200 ) {
    echo "SUCCESS: Authenticated request allowed (200).\n";
} elseif ( $res->status === 429 ) {
    echo "FAILURE: Authenticated request blocked (429) - Vulnerability confirmed.\n";
} else {
    echo "FAILURE: Unexpected status for authenticated request: " . $res->status . "\n";
}
