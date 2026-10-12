<?php

if (PHP_SAPI !== 'cli') {
    exit;
}

define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
define('DAY_IN_SECONDS', 86400);
$test_meta = array();
$test_orders = array();
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_shortcode($tag, $callback) {}
function register_deactivation_hook($file, $callback) {}
function apply_filters($hook, $value) { return $value; }
function absint($value) { return abs((int) $value); }
function current_time($format) { return '2026-10-09'; }
function wp_timezone() { return new DateTimeZone('America/Toronto'); }
function get_posts($args) { return array_keys($GLOBALS['test_meta']); }
function get_post_meta($id, $key, $single = false) { return $GLOBALS['test_meta'][$id][$key] ?? ''; }
function update_post_meta($id, $key, $value) { $GLOBALS['test_meta'][$id][$key] = $value; return true; }
function wc_get_order($id) { return $GLOBALS['test_orders'][$id] ?? false; }
function golf_simulator_theme_get_user_membership_record($user_id) {
    return (object) array('package_name' => 'EAGLE', 'status' => 'active', 'payment_status' => 'paid');
}
class TestItem {
    private $meta;
    public function __construct($meta) { $this->meta = $meta; }
    public function get_meta($key) { return $this->meta[$key] ?? ''; }
}
class TestOrder {
    private $items;
    public function __construct($items) { $this->items = $items; }
    public function get_items() { return $this->items; }
}

require dirname(__DIR__, 3) . '/plugins/tee-time-nexus-bookings/tee-time-nexus-bookings.php';

function check_value($actual, $expected, $message) {
    if ($actual !== $expected) {
        throw new RuntimeException($message . ' Got ' . var_export($actual, true));
    }
}

ttn_save_booking_metadata(10, array('date' => '2026-10-09', 'member_free_hours' => 1, 'user_id' => 13));
check_value(get_post_meta(10, 'ttn_booking_member_free_hours', true), 1, 'New bookings must persist free hours.');
check_value(ttn_booking_get_member_free_hours_used(13, '2026-10-09'), 1, 'Persisted free hours count as used.');
check_value(ttn_booking_get_member_free_hours(13, '2026-10-09', '3:00 PM', 2), 1, 'EAGLE has one free hour left.');

ttn_save_booking_metadata(11, array('date' => '2026-10-09', 'member_free_hours' => 1, 'parent_id' => 10, 'user_id' => 13));
check_value(ttn_booking_get_member_free_hours_used(13, '2026-10-09'), 1, 'Child slot bookings are not double counted.');

$test_meta[12] = array('ttn_booking_order_id' => 99, 'ttn_booking_bay' => 'Apex', 'ttn_booking_date' => '2026-10-09', 'ttn_booking_time' => '1:00 AM');
$test_orders[99] = new TestOrder(array(
    new TestItem(array('ttn_booking_bay' => 'Nexus', 'ttn_booking_date' => '2026-10-09', 'ttn_booking_time' => '1:00 AM', 'ttn_booking_member_free_hours' => '2')),
    new TestItem(array('ttn_booking_bay' => 'Apex', 'ttn_booking_date' => '2026-10-09', 'ttn_booking_time' => '1:00 AM', 'ttn_booking_member_free_hours' => '1')),
));
check_value(ttn_booking_get_member_free_hours_used(13, '2026-10-09'), 2, 'Legacy bookings are backfilled from the matching order line.');
check_value($test_meta[12]['ttn_booking_member_free_hours'], 1, 'Backfill is saved on the booking.');
check_value(ttn_booking_get_member_free_hours(13, '2026-10-09', '3:00 PM', 1), 0, 'Allowance is exhausted.');

$test_meta[13] = array('ttn_booking_bay' => 'Apex', 'ttn_booking_date' => '2026-10-09', 'ttn_booking_time' => '5:00 AM');
check_value(ttn_booking_get_member_free_hours_used(13, '2026-10-09'), 2, 'Bookings without orders count zero.');
$test_meta[10]['ttn_booking_status'] = 'cancelled';
check_value(ttn_booking_get_member_free_hours_used(13, '2026-10-09'), 1, 'Cancelled bookings return their hours.');
echo "Member free-hour usage tests passed.\n";
