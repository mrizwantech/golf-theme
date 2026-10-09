<?php

if (PHP_SAPI !== 'cli') {
    exit;
}

define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
define('DAY_IN_SECONDS', 86400);
$test_today = '2026-10-08';
$test_membership = null;
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_shortcode($tag, $callback) {}
function register_deactivation_hook($file, $callback) {}
function apply_filters($hook, $value) { return $value; }
function absint($value) { return abs((int) $value); }
function current_time($format) { return $GLOBALS['test_today']; }
function wp_timezone() { return new DateTimeZone('America/Toronto'); }
function golf_simulator_theme_get_user_membership_record($user_id) { return $GLOBALS['test_membership']; }
function rest_ensure_response($value) { return $value; }
class WP_User {
    public $ID = 13;
}
class WP_REST_Request {
    public function get_param($key) { return $key === 'ttn_auth_user' ? new WP_User() : null; }
}

require dirname(__DIR__, 3) . '/plugins/tee-time-nexus-bookings/tee-time-nexus-bookings.php';

function check_value($actual, $expected, $message) {
    if ($actual !== $expected) {
        throw new RuntimeException($message);
    }
}

foreach (array('' => 7, 'PAR' => 7, 'BIRDIE' => 14, 'ALBATROSS' => 21, 'EAGLE' => 21) as $tier => $days) {
    $test_membership = $tier ? (object) array('package_name' => $tier, 'status' => 'active', 'payment_status' => 'paid') : null;
    $last = (new DateTimeImmutable($test_today, wp_timezone()))->modify('+' . $days . ' days')->format('Y-m-d');
    $after = (new DateTimeImmutable($last, wp_timezone()))->modify('+1 day')->format('Y-m-d');
    check_value(ttn_booking_get_membership_booking_window_days(13), $days, 'Wrong tier booking window.');
    check_value(ttn_booking_is_date_within_membership_window(13, $test_today), true, 'Today must be allowed.');
    check_value(ttn_booking_is_date_within_membership_window(13, $last), true, 'Last allowed day must be inclusive.');
    check_value(ttn_booking_is_date_within_membership_window(13, $after), false, 'Day after the limit must be rejected.');
    check_value(ttn_booking_is_date_within_membership_window(13, '2026-10-07'), false, 'Past date must be rejected.');
    check_value(ttn_booking_is_date_within_membership_window(13, '2026-02-30'), false, 'Malformed date must be rejected.');
    $response = ttn_mobile_booking_route_member_time_slots(new WP_REST_Request());
    check_value($response['min_booking_date'], $test_today, 'API must use website today.');
    check_value($response['max_booking_date'], $last, 'API must share checkout date bounds.');
    check_value($response['booking_window_days'], $days, 'API must return the effective limit.');
}
foreach (array(array('active', 'unpaid'), array('cancelled', 'paid')) as $state) {
    $test_membership = (object) array('package_name' => 'ALBATROSS', 'status' => $state[0], 'payment_status' => $state[1]);
    check_value(ttn_booking_get_membership_booking_window_days(13), 7, 'Inactive/unpaid users must use the public limit.');
}
$test_membership = null;
$test_today = '2026-12-28';
check_value(ttn_booking_get_membership_max_booking_date(13), '2027-01-04', 'Limit must cross year boundaries.');
echo "Mobile booking window tests passed.\n";
