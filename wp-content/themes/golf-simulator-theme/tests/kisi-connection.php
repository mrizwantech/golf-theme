<?php

if (PHP_SAPI !== 'cli') {
    exit;
}
define('ABSPATH', __DIR__);
define('TTN_KISI_API_KEY', 'test-only-placeholder');
define('TTN_KISI_ENTRANCE_LOCK_ID', 123);
class WP_Error {
    private $message;
    public function __construct($code, $message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function add_action($hook, $callback, $priority = 10) {}
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_remote_get($url, $options) {
    if ($url !== 'https://api.kisi.io/locks/123' || $options['redirection'] !== 0 || !$options['sslverify']
        || $options['headers']['Authorization'] !== 'KISI-LOGIN test-only-placeholder') {
        throw new RuntimeException('Unexpected request configuration.');
    }
    return $GLOBALS['response'];
}
function wp_remote_retrieve_response_code($response) { return $response['status']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
require dirname(__DIR__, 3) . '/plugins/tee-time-nexus-bookings/inc/class-ttn-kisi-connection.php';

$response = array('status' => 200, 'body' => '{"id":123}');
if (ttn_kisi_test_connection() !== true) {
    throw new RuntimeException('Valid lock must pass.');
}
foreach (array(401, 403, 404, 429, 500) as $status) {
    $response = array('status' => $status, 'body' => 'Sensitive response should never be shown.');
    $result = ttn_kisi_test_connection();
    if (!is_wp_error($result) || strpos($result->get_error_message(), 'HTTP ' . $status) === false
        || strpos($result->get_error_message(), 'Sensitive') !== false) {
        throw new RuntimeException('HTTP errors must be explicit without exposing response content.');
    }
}
foreach (array('not-json', '{"id":456}', '{}') as $body) {
    $response = array('status' => 200, 'body' => $body);
    if (!is_wp_error(ttn_kisi_test_connection())) {
        throw new RuntimeException('Malformed or mismatched lock must fail.');
    }
}
$response = new WP_Error('network', 'Sensitive transport detail');
$result = ttn_kisi_test_connection();
if (!is_wp_error($result) || strpos($result->get_error_message(), 'Sensitive') !== false) {
    throw new RuntimeException('Network failures must not expose transport details.');
}
echo "Kisi connection tests passed.\n";
