<?php

if (PHP_SAPI !== 'cli') {
    exit;
}

define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
$current_user_id = 13;
$meta = array(
    'first_name' => 'Muhammad',
    'last_name' => 'Rizwan',
    'phone_number' => '+15555550123',
);
function add_action($hook, $callback) {}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
function get_current_user_id() { return $GLOBALS['current_user_id']; }
function get_userdata($user_id) {
    return $user_id === 13 ? (object) array('user_email' => 'example@privaterelay.appleid.com') : false;
}
function get_user_meta($user_id, $key, $single) {
    return $GLOBALS['meta'][$key] ?? '';
}

require dirname(__DIR__, 3) . '/plugins/tee-time-nexus-bookings/inc/class-ttn-mobile-checkout-bridge.php';

function check_value($actual, $expected, $message) {
    if ($actual !== $expected) {
        throw new RuntimeException($message);
    }
}

check_value(ttn_mobile_prefill_checkout_contact(null, 'billing_first_name'), 'Muhammad', 'Profile given name should prefill checkout.');
check_value(ttn_mobile_prefill_checkout_contact('', 'billing_last_name'), 'Rizwan', 'Profile family name should prefill checkout.');
check_value(ttn_mobile_prefill_checkout_contact(null, 'billing_email'), 'example@privaterelay.appleid.com', 'Relay email should be preserved.');
check_value(ttn_mobile_prefill_checkout_contact(null, 'billing_phone'), '+15555550123', 'Saved phone should prefill checkout.');
check_value(ttn_mobile_prefill_checkout_contact('Entered name', 'billing_first_name'), 'Entered name', 'Entered checkout values must not be overwritten.');
$meta['billing_first_name'] = 'Billing name';
check_value(ttn_mobile_prefill_checkout_contact(null, 'billing_first_name'), 'Billing name', 'Saved billing names should take precedence.');
$meta['last_name'] = '';
check_value(ttn_mobile_prefill_checkout_contact(null, 'billing_last_name'), null, 'Missing names must remain blank for checkout entry.');
check_value(ttn_mobile_prefill_checkout_contact(null, 'billing_address_1'), null, 'Unrelated fields must remain unchanged.');
$current_user_id = 0;
check_value(ttn_mobile_prefill_checkout_contact(null, 'billing_first_name'), null, 'Guest checkout must remain unchanged.');
echo "Apple checkout contact tests passed.\n";
