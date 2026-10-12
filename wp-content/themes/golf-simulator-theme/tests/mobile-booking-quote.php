<?php

if (PHP_SAPI !== 'cli') {
    exit;
}

define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
define('DAY_IN_SECONDS', 86400);
$test_membership = null;
$test_params = array();
$test_used_free_hours = array();
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_shortcode($tag, $callback) {}
function register_deactivation_hook($file, $callback) {}
function apply_filters($hook, $value) { return $value; }
function absint($value) { return abs((int) $value); }
function sanitize_text_field($value) { return trim((string) $value); }
function current_time($format) { return '2026-10-08'; }
function wp_timezone() { return new DateTimeZone('America/Toronto'); }
function get_option($key, $default = false) {
    return $key === 'ttn_bays' ? array('apex' => array('name' => 'Apex', 'type' => 'dual', 'location' => 'Main', 'premium' => false)) : $default;
}
function update_option($key, $value) { return true; }
function get_posts($args) { return array_keys($GLOBALS['test_used_free_hours']); }
function get_post_meta($id, $key, $single = false) {
    return $key === 'ttn_booking_member_free_hours' ? $GLOBALS['test_used_free_hours'][$id] : '';
}
function golf_simulator_theme_get_user_membership_record($user_id) { return $GLOBALS['test_membership']; }
class WP_Error {
    public $code;
    public function __construct($code, $message, $data = array()) { $this->code = $code; }
}
class TestResponse {
    public $data;
    public $headers = array();
    public function __construct($data) { $this->data = $data; }
    public function header($key, $value) { $this->headers[$key] = $value; }
}
function rest_ensure_response($value) { return new TestResponse($value); }
class WP_User {
    public $ID = 13;
}
class WP_REST_Request {
    public function get_param($key) { return $key === 'ttn_auth_user' ? new WP_User() : ($GLOBALS['test_params'][$key] ?? null); }
}

require dirname(__DIR__, 3) . '/plugins/tee-time-nexus-bookings/tee-time-nexus-bookings.php';

function check_value($actual, $expected, $message) {
    if ($actual !== $expected) {
        throw new RuntimeException($message . ' Got ' . var_export($actual, true));
    }
}

$bay = array_keys(ttn_booking_get_bay_configs())[0];
$hourly = (float) ttn_booking_get_hourly_price($bay);
$quote = function ($duration, $players = 1) use ($bay) {
    $GLOBALS['test_params'] = array('bay' => $bay, 'date' => '2026-10-09', 'time' => '2:00 PM', 'duration' => $duration, 'players' => $players);
    return ttn_mobile_booking_route_quote(new WP_REST_Request());
};

$test_membership = (object) array('package_name' => 'EAGLE', 'status' => 'active', 'payment_status' => 'paid');
$response = $quote(1);
check_value($response->data['member_free_hours'], 1, 'EAGLE first hour must be free.');
check_value($response->data['total'], 0.0, 'Fully free booking must total zero.');
check_value($response->headers['Cache-Control'], 'private, no-store', 'Quotes must not be cached.');
check_value($quote(3)->data['total'], $hourly, 'EAGLE gets two free hours per day.');
check_value($quote(1, 4)->data['member_guest_charge'], 15.0, 'EAGLE includes two guests; the third costs 15/hour.');
$test_used_free_hours = array(501 => 2);
check_value($quote(1)->data['total'], $hourly, 'Used daily allowance must be charged.');
$test_used_free_hours = array();
$test_membership = (object) array('package_name' => 'ALBATROSS', 'status' => 'active', 'payment_status' => 'pending');
check_value($quote(2)->data['total'], $hourly * 2, 'Unpaid membership gets no free hours.');
$test_membership = null;
check_value($quote(1)->data['member_package'], '', 'Non-members have no package.');
$GLOBALS['test_params'] = array('bay' => 'missing', 'date' => '2026-10-09', 'time' => '2:00 PM');
check_value(ttn_mobile_booking_route_quote(new WP_REST_Request()) instanceof WP_Error, true, 'Invalid bay must be rejected.');
$balance = function ($date = null) {
    $GLOBALS['test_params'] = $date === null ? array() : array('date' => $date);
    return ttn_mobile_booking_route_free_hours(new WP_REST_Request());
};
$test_membership = (object) array('package_name' => 'EAGLE', 'status' => 'active', 'payment_status' => 'paid');
$test_used_free_hours = array();
check_value($balance()->data, array('date' => '2026-10-08', 'daily_allowance' => 2, 'used' => 0, 'remaining' => 2), 'Balance defaults to website today.');
$test_used_free_hours = array(601 => 1);
check_value($balance('2026-10-09')->data['remaining'], 1, 'Balance subtracts used hours.');
check_value($balance('2026-10-09')->headers['Cache-Control'], 'private, no-store', 'Balance must not be cached.');
$test_used_free_hours = array(601 => 3);
check_value($balance('2026-10-09')->data['remaining'], 0, 'Remaining never goes negative.');
$test_membership = null;
check_value($balance('2026-10-09')->data['daily_allowance'], 0, 'Non-members have no allowance.');
check_value($balance('2026-02-30') instanceof WP_Error, true, 'Invalid date must be rejected.');
echo "Mobile booking quote tests passed.\n";
