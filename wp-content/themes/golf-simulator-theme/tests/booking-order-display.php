<?php

if (PHP_SAPI !== 'cli') {
    exit;
}

define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
define('DAY_IN_SECONDS', 86400);
$test_endpoint = 'order-received';
$test_orders = array();
$test_order_id = 0;
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_shortcode($tag, $callback) {}
function register_deactivation_hook($file, $callback) {}
function apply_filters($hook, $value) { return $value; }
function absint($value) { return abs((int) $value); }
function __($text, $domain = 'default') { return $text; }
function _n($single, $plural, $number, $domain = 'default') { return $number === 1 ? $single : $plural; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function wp_strip_all_tags($value) { return strip_tags($value); }
function wp_timezone() { return new DateTimeZone('America/Toronto'); }
function wp_date($format, $timestamp, $timezone) { return (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone)->format($format); }
function is_admin() { return false; }
function did_action($hook) { return 1; }
function is_wc_endpoint_url($endpoint) { return $GLOBALS['test_endpoint'] === $endpoint; }
function get_query_var($key) { return $GLOBALS['test_order_id']; }
function wc_get_order($id) { return $GLOBALS['test_orders'][$id] ?? false; }

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
function meta($key, $value) { return (object) array('key' => $key, 'value' => $value); }

$expected_labels = array(
    'ttn_booking_bay' => 'Bay', 'ttn_booking_date' => 'Date', 'ttn_booking_time' => 'Start time',
    'ttn_booking_duration' => 'Duration', 'ttn_booking_players' => 'Players',
    'ttn_booking_club_preference' => 'Clubs', 'ttn_booking_member_free_hours' => 'Free member hours',
);
foreach ($expected_labels as $key => $label) {
    check_value(ttn_booking_order_meta_display_key($key, meta($key, '1')), $label, "Label for $key.");
}
check_value(ttn_booking_order_meta_display_key('Other', meta('other', 'x')), 'Other', 'Unrelated meta keeps its label.');
check_value(ttn_booking_order_meta_display_value('2026-10-09', meta('ttn_booking_date', '2026-10-09')), 'Friday, October 9, 2026', 'Date is readable.');
check_value(ttn_booking_order_meta_display_value('1', meta('ttn_booking_duration', '1')), '1 hour', 'Singular duration.');
check_value(ttn_booking_order_meta_display_value('4', meta('ttn_booking_duration', '4')), '4 hours', 'Plural duration.');
check_value(ttn_booking_order_meta_display_value('own-clubs', meta('ttn_booking_club_preference', 'own-clubs')), 'Own clubs', 'Club preference is readable.');
check_value(ttn_booking_order_meta_display_value('1', meta('ttn_booking_member_free_hours', '1')), '1 hour', 'Free hours are readable.');
check_value(ttn_booking_order_meta_display_value('Apex', meta('ttn_booking_bay', 'Apex')), 'Apex', 'Bay is unchanged.');
check_value(ttn_booking_order_meta_display_value('bad', meta('ttn_booking_date', '2026-02-30')), 'bad', 'Invalid dates are left alone.');
$formatted = ttn_booking_hide_zero_free_hours(array(1 => meta('ttn_booking_member_free_hours', '0'), 2 => meta('ttn_booking_bay', 'Apex')), null);
check_value(array_keys($formatted), array(2), 'Zero free hours are hidden.');

$test_orders = array(
    7 => new TestOrder(array(new TestItem(array('ttn_booking_bay' => 'Apex')))),
    8 => new TestOrder(array(new TestItem(array('_membership_revision' => 'x')))),
);
$test_order_id = 7;
check_value(ttn_booking_order_page_text('Order details', 'Order details', 'woocommerce'), 'Booking details', 'Booking order heading.');
check_value(ttn_booking_order_page_text('Product', 'Product', 'woocommerce'), 'Booking', 'Booking column heading.');
check_value(ttn_booking_order_page_text('Total', 'Total', 'woocommerce'), 'Total', 'Other strings are unchanged.');
check_value(ttn_booking_order_page_text('Order details', 'Order details', 'other'), 'Order details', 'Other text domains are unchanged.');
check_value(ttn_booking_order_has_booking($test_orders[8]), false, 'Membership orders are not booking orders.');
check_value(ttn_booking_order_has_booking(false), false, 'Missing orders are not booking orders.');
echo "Booking order display tests passed.\n";
