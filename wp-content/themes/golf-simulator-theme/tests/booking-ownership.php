<?php

if (PHP_SAPI !== 'cli') {
    exit;
}
define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
define('DAY_IN_SECONDS', 86400);
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_shortcode($tag, $callback) {}
function register_deactivation_hook($file, $callback) {}
function get_user_by($field, $email) {
    return strcasecmp($email, 'verified@example.test') === 0 ? (object) array('ID' => 13) : false;
}
function get_posts($args) { return array_keys($GLOBALS['bookings']); }
function get_option($key, $default = false) { return $key === 'ttn_bays' ? ttn_booking_get_default_bays() : $default; }
function get_post_meta($id, $key, $single = true) { return $GLOBALS['bookings'][$id][$key] ?? ''; }
function get_the_modified_date($format, $id) { return '2026-10-08 12:00:00'; }
function rest_ensure_response($value) { return $value; }
class WP_User {
    public $ID = 13;
    public $user_email = 'verified@example.test';
}
class WP_REST_Request {
    public function get_param($key) { return $key === 'ttn_auth_user' ? new WP_User() : null; }
}
function check_booking($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
require dirname(__DIR__, 3) . '/plugins/tee-time-nexus-bookings/tee-time-nexus-bookings.php';

$GLOBALS['bookings'] = array(
    1 => array('ttn_booking_user_id' => 13, 'ttn_booking_email' => 'hidden@privaterelay.appleid.com'),
    2 => array('ttn_booking_user_id' => 13, 'ttn_booking_email' => 'different-billing@example.test'),
    3 => array('ttn_booking_user_id' => 99, 'ttn_booking_email' => 'verified@example.test'),
    4 => array('ttn_booking_email' => 'VERIFIED@example.test'),
    5 => array('ttn_booking_user_id' => 13, 'ttn_booking_email' => 'hidden@privaterelay.appleid.com', 'ttn_booking_parent_id' => 1),
    6 => array('ttn_booking_email' => 'unrelated@example.test'),
    7 => array('ttn_booking_user_id' => 13, 'ttn_booking_status' => 'cancelled'),
    8 => array('ttn_booking_email' => 'hidden@privaterelay.appleid.com'),
);
check_booking(ttn_booking_belongs_to_user(1, 'verified@example.test', 13), 'Apple relay booking stays owned after an email change.');
check_booking(ttn_booking_belongs_to_user(2, 'verified@example.test', 13), 'Billing email need not equal account email.');
check_booking(!ttn_booking_belongs_to_user(3, 'verified@example.test', 13), 'Matching email must not override another account owner.');
check_booking(ttn_booking_belongs_to_user(4, 'verified@example.test', 13), 'Ownerless legacy email matching is case-insensitive.');
check_booking(!ttn_booking_belongs_to_user(1, 'hidden@privaterelay.appleid.com', 99), 'Old relay email must not grant another account access.');
check_booking(!ttn_booking_belongs_to_user(8, 'verified@example.test', 13), 'Ownerless old-email bookings must not be guessed or reassigned.');
check_booking(!ttn_booking_belongs_to_user(6, '', 13), 'Empty email cannot match legacy bookings.');
$records = ttn_get_user_bookings('verified@example.test', 13);
check_booking(array_column($records, 'ID') === array(1, 2, 4, 7), 'Listing includes account-owned bookings and legacy matches, excluding child and other-user records.');
check_booking(array_keys($records[0]) === array(
    'ID', 'bay', 'date', 'time', 'duration', 'players', 'phone', 'name', 'status',
    'updated_at', 'payment_status', 'member_free_hours', 'payment', 'booking_reference',
), 'Existing booking response shape is unchanged.');
check_booking(array_column(ttn_get_user_bookings('verified@example.test'), 'ID') === array(1, 2, 4, 7), 'Email-only callers resolve the stable account ID.');
check_booking(array_column(ttn_mobile_booking_route_my_bookings(new WP_REST_Request()), 'ID') === array(1, 2, 4, 7), 'JWT endpoint retains relay bookings after verification.');
check_booking(ttn_crud_update_user_booking(3, 'verified@example.test', array())['success'] === false, 'Editing cannot use an email match to bypass the owner ID.');
check_booking(ttn_crud_cancel_user_booking(3, 'verified@example.test')['success'] === false, 'Cancellation cannot use an email match to bypass the owner ID.');
check_booking($GLOBALS['bookings'][1]['ttn_booking_email'] === 'hidden@privaterelay.appleid.com', 'Listing does not rewrite historical contact details.');
echo "Booking ownership and Apple email-change regression tests passed.\n";
