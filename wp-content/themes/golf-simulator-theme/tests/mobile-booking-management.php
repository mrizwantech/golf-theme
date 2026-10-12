<?php
if (PHP_SAPI !== 'cli') { exit; }
define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
$meta = array();
$orders = array();
$locks = array();
$transients = array();
$refund_fail = false;
$insert_fail = false;
$next_id = 100;
$membership = (object) array('package_name' => 'EAGLE', 'status' => 'active', 'payment_status' => 'paid');
function add_action($hook, $callback, $priority = 10, $args = 1) {}
function add_filter($hook, $callback, $priority = 10, $args = 1) {}
function remove_filter($hook, $callback) {}
function add_shortcode($tag, $callback) {}
function register_deactivation_hook($file, $callback) {}
function apply_filters($hook, $value) { return $value; }
function absint($value) { return abs((int) $value); }
function sanitize_text_field($value) { return trim((string) $value); }
function sanitize_email($value) { return $value; }
function is_email($value) { return strpos($value, '@') !== false; }
function email_exists($value) { return 13; }
function esc_html($value) { return htmlspecialchars((string) $value); }
function home_url($path = '') { return 'https://example.test' . $path; }
function add_query_arg($key, $value = null, $url = null) { return is_array($key) ? $value . '?' . http_build_query($key) : $url . '?' . http_build_query(array($key => $value)); }
function is_user_logged_in() { return false; }
function golf_simulator_theme_render_email_template(...$args) { return implode(' ', $args); }
function wp_mail($to, $subject, $message, $headers) { $GLOBALS['mail'][] = $to; return true; }
function wp_json_encode($value) { return json_encode($value); }
function wp_timezone() { return new DateTimeZone('America/New_York'); }
function current_time($format) { return (new DateTimeImmutable('now', wp_timezone()))->format($format === 'mysql' ? 'Y-m-d H:i:s' : $format); }
function get_option($key, $default = false) {
    if (isset($GLOBALS['locks'][$key])) { return $GLOBALS['locks'][$key]; }
    if ($key === 'ttn_bays') { return array(
        'apex' => array('name' => 'Apex', 'type' => 'dual', 'location' => 'Main', 'premium' => false),
        'nexus' => array('name' => 'Nexus', 'type' => 'dual', 'location' => 'Main', 'premium' => true),
    ); }
    return $key === 'ttn_standard_hourly_price' ? 60 : ($key === 'ttn_premium_hourly_price' ? 80 : $default);
}
function update_option($key, $value) { return true; }
function add_option($key, $value, $unused = '', $autoload = false) {
    if (isset($GLOBALS['locks'][$key])) { return false; }
    $GLOBALS['locks'][$key] = $value; return true;
}
function delete_option($key) { unset($GLOBALS['locks'][$key]); return true; }
function get_post_meta($id, $key, $single = true) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function update_post_meta($id, $key, $value) { $GLOBALS['meta'][$id][$key] = $value; return true; }
function get_post($id) { return isset($GLOBALS['meta'][$id]) ? (object) array('ID' => $id, 'post_type' => 'ttn_booking', 'post_status' => 'publish') : null; }
function wp_insert_post($post) {
    if ($GLOBALS['insert_fail']) { return new WP_Error('insert_failed', 'Database failure'); }
    $id = $GLOBALS['next_id']++; $GLOBALS['meta'][$id] = array(); return $id;
}
function wp_update_post($post) { return $post['ID']; }
function wp_delete_post($id, $force) { unset($GLOBALS['meta'][$id]); }
function get_posts($args) {
    $ids = array();
    foreach ($GLOBALS['meta'] as $id => $values) {
        $queries = $args['meta_query'] ?? array();
        if (isset($args['meta_key'])) { $queries[] = array('key' => $args['meta_key'], 'value' => $args['meta_value']); }
        $matches = true;
        foreach ($queries as $query) {
            if (is_array($query) && (string) ($values[$query['key']] ?? '') !== (string) $query['value']) { $matches = false; }
        }
        if ($matches) { $ids[] = $id; }
    }
    return $ids;
}
function get_user_by($field, $value) { return ($field === 'id' && $value == 13) || ($field === 'email' && $value === 'member@example.test') ? new WP_User() : false; }
function golf_simulator_theme_get_user_membership_record($id) { return $GLOBALS['membership']; }
function wc_get_order($id) { return $GLOBALS['orders'][$id] ?? false; }
function wc_create_refund($args) {
    if ($GLOBALS['refund_fail']) { return new WP_Error('refund_failed', 'Gateway refused refund'); }
    $GLOBALS['orders'][$args['order_id']]->remaining -= $args['amount'];
    return (object) $args;
}
function wp_generate_password($length, $special = true) { return str_repeat('x', $length); }
function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = $value; }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function delete_transient($key) { unset($GLOBALS['transients'][$key]); }
function wp_unslash($value) { return $value; }
function wp_set_current_user($id) { $GLOBALS['current_user'] = $id; }
function wp_set_auth_cookie($id, $remember) {}
function wp_die($message) { throw new RuntimeException($message); }
function wp_safe_redirect($url) { throw new TestRedirect($url); }
function wc_load_cart() {}
function WC() { return $GLOBALS['wc']; }
function wc_get_checkout_url() { return 'https://example.test/checkout/'; }
function wc_get_products($args) { return array(new TestProduct()); }
function wc_get_product($id) { return new TestProduct(); }
function get_current_user_id() { return $GLOBALS['current_user'] ?? 13; }
function wc_add_notice($message, $type) { $GLOBALS['notices'][] = $message; }
function is_wp_error($value) { return $value instanceof WP_Error; }
class WP_Error {
    public $message;
    public function __construct($code, $message, $data = array()) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
class WP_User {
    public $ID = 13;
    public $user_email = 'member@example.test';
}
class WP_REST_Request {
    private $params;
    public function __construct($params = array()) { $this->params = $params; }
    public function get_param($key) { return $key === 'ttn_auth_user' ? ($this->params[$key] ?? new WP_User()) : ($this->params[$key] ?? null); }
    public function get_json_params() { return $this->params; }
}
class TestResponse {
    public $data;
    public $headers = array();
    public function __construct($data) { $this->data = $data; }
    public function header($key, $value) { $this->headers[$key] = $value; }
}
function rest_ensure_response($data) { return new TestResponse($data); }
class TestOrder {
    public $remaining;
    public $items;
    public $notes = array();
    public $meta = array();
    private $id;
    public function __construct($id, $amount = 60, $items = array(1)) { $this->id = $id; $this->remaining = $amount; $this->items = $items; }
    public function get_id() { return $this->id; }
    public function get_items() { return $this->items; }
    public function get_remaining_refund_amount() { return $this->remaining; }
    public function add_order_note($note) { $this->notes[] = $note; }
    public function get_meta($key) { return $this->meta[$key] ?? ''; }
    public function update_meta_data($key, $value) { $this->meta[$key] = $value; }
    public function get_customer_id() { return 13; }
    public function save() {}
    public function is_paid() { return true; }
}
class TestItem {
    private $meta;
    public function __construct($meta) { $this->meta = $meta; }
    public function get_meta($key) { return $this->meta[$key] ?? ''; }
}
class TestRedirect extends RuntimeException {}
class TestProduct {
    public function get_id() { return 88; }
    public function is_virtual() { return true; }
}
class WC_Product_Simple extends TestProduct {}
class WC_Cart {
    public $items = array();
    public $fail = false;
    public function empty_cart() { $this->items = array(); }
    public function add_to_cart($product, $quantity, $variation, $attributes, $data) {
        if ($this->fail) { return false; }
        $this->items[] = $data;
        return 'cart-key';
    }
    public function get_cart() { return $this->items; }
}
class TestErrors {
    public $errors = array();
    public function add($code, $message) { $this->errors[] = $message; }
}

require dirname(__DIR__, 3) . '/plugins/tee-time-nexus-bookings/tee-time-nexus-bookings.php';
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function fixture($date) {
    $GLOBALS['meta'] = array(42 => array(
        'ttn_booking_user_id' => 13, 'ttn_booking_email' => 'relay@example.test', 'ttn_booking_name' => 'Member',
        'ttn_booking_bay' => 'Apex', 'ttn_booking_date' => $date, 'ttn_booking_time' => '2:00 PM',
        'ttn_booking_duration' => 1, 'ttn_booking_players' => 4, 'ttn_booking_status' => 'confirmed',
        'ttn_booking_member_free_hours' => 1, 'ttn_booking_guest_charge' => 15, 'ttn_booking_total_price' => 15,
        'ttn_booking_order_id' => 99,
    ));
    $GLOBALS['orders'] = array(99 => new TestOrder(99, 15));
    $GLOBALS['locks'] = array();
    $GLOBALS['mail'] = array();
}
$date = (new DateTimeImmutable('now', wp_timezone()))->modify('+2 days')->format('Y-m-d');
$other_date = (new DateTimeImmutable('now', wp_timezone()))->modify('+3 days')->format('Y-m-d');
fixture($date);
$user = new WP_User();
$selection = array('bay' => 'Apex', 'date' => $date, 'time' => '3:00 PM', 'duration' => 2);
$quote = ttn_booking_prepare_schedule_change(42, $user, $selection);
check(!is_wp_error($quote) && $quote['member_free_hours'] === 2 && $quote['new_total_price'] === 30.0 && $quote['difference'] === 15.0, 'Same-day quote excludes the current booking from used hours and prices extra guests.');
$management = ttn_mobile_booking_route_management(new WP_REST_Request(array('id' => 42)));
check($management->data['can_manage'] && $management->headers['Cache-Control'] === 'private, no-store', 'Management returns live rules.');
$GLOBALS['meta'][42]['ttn_booking_user_id'] = 99;
check(is_wp_error(ttn_mobile_booking_route_management(new WP_REST_Request(array('id' => 42)))), 'Another owner cannot manage even if their email matches.');
fixture($date);
$GLOBALS['meta'][43] = array('ttn_booking_parent_id' => 42, 'ttn_booking_bay' => 'Apex', 'ttn_booking_date' => $date, 'ttn_booking_time' => '3:00 PM');
check(!is_wp_error(ttn_booking_prepare_schedule_change(42, $user, $selection)), 'Existing child slots are excluded from availability.');
check(is_wp_error(ttn_mobile_booking_route_management(new WP_REST_Request(array('id' => 43)))), 'Children cannot be managed as reservations.');
$GLOBALS['meta'][44] = array('ttn_booking_bay' => 'Apex', 'ttn_booking_date' => $date, 'ttn_booking_time' => '4:00 PM');
check(is_wp_error(ttn_booking_prepare_schedule_change(42, $user, $selection)), 'A conflicting slot rejects the whole duration.');
unset($GLOBALS['meta'][44]);
foreach (array('2026-02-30', (new DateTimeImmutable('now', wp_timezone()))->modify('+22 days')->format('Y-m-d')) as $invalid_date) {
    check(is_wp_error(ttn_booking_prepare_schedule_change(42, $user, array_merge($selection, array('date' => $invalid_date)))), 'Invalid dates and membership windows are enforced.');
}
check(is_wp_error(ttn_booking_prepare_schedule_change(42, $user, array_merge($selection, array('duration' => 9)))), 'Duration is validated, not clamped.');
$GLOBALS['meta'][42]['ttn_booking_date'] = current_time('Y-m-d');
$GLOBALS['meta'][42]['ttn_booking_time'] = '12:00 AM';
check(is_wp_error(ttn_booking_prepare_schedule_change(42, $user, $selection)), 'Past/within-24-hour reservations cannot change.');
check(ttn_crud_cancel_user_booking(42, $user->user_email)['success'] === false, 'Cancellation uses the same cutoff.');
fixture($date);
$GLOBALS['meta'][50] = array('ttn_booking_user_id' => 13, 'ttn_booking_date' => $other_date, 'ttn_booking_member_free_hours' => 2);
$quote = ttn_booking_prepare_schedule_change(42, $user, array_merge($selection, array('date' => $other_date, 'duration' => 1)));
check($quote['member_free_hours'] === 0 && $quote['new_total_price'] === 75.0, 'Moving to an exhausted date charges the bay and guest.');
$result = ttn_mobile_booking_route_change(new WP_REST_Request(array_merge($quote, array('id' => 42, 'action' => 'reschedule'))));
check($result->data['success'] && isset($result->data['bridge_url']), 'Additional payment gets a one-time checkout bridge.');
check($GLOBALS['meta'][42]['ttn_booking_date'] === $date, 'Original booking is unchanged before payment.');
check(count($GLOBALS['transients']) === 1 && !$GLOBALS['locks'], 'Bridge is stored and change lock is released.');
check(is_wp_error(ttn_mobile_booking_route_change(new WP_REST_Request(array_merge($quote, array('id' => 42, 'action' => 'reschedule', 'quote_id' => 'stale'))))), 'Unreviewed or stale price cannot submit.');
check(is_wp_error(ttn_mobile_booking_route_change(new WP_REST_Request(array('id' => 42, 'action' => 'cancel', 'revision' => 'stale')))), 'Stale cancellation cannot mutate.');
fixture($date);
$quote = ttn_booking_prepare_schedule_change(42, $user, array_merge($selection, array('duration' => 1)));
$result = ttn_mobile_booking_route_change(new WP_REST_Request(array_merge($quote, array('id' => 42, 'action' => 'reschedule'))));
check($result->data['success'] && $GLOBALS['meta'][42]['ttn_booking_time'] === '3:00 PM', 'No-payment changes apply natively.');
check($GLOBALS['meta'][42]['ttn_booking_member_free_hours'] === 1 && $GLOBALS['mail'][0] === 'relay@example.test', 'Free hours and original contact email are retained.');
fixture($date);
$GLOBALS['meta'][43] = array('ttn_booking_parent_id' => 42, 'ttn_booking_bay' => 'Apex', 'ttn_booking_date' => $date, 'ttn_booking_time' => '3:00 PM');
$GLOBALS['refund_fail'] = true;
$result = ttn_mobile_booking_route_change(new WP_REST_Request(array('id' => 42, 'action' => 'cancel', 'revision' => ttn_booking_management_revision(42))));
check(is_wp_error($result) && $GLOBALS['meta'][42]['ttn_booking_status'] === 'confirmed' && isset($GLOBALS['meta'][43]), 'Refund errors surface without cancelling or releasing slots.');
check(count($GLOBALS['orders'][99]->notes) === 1, 'Gateway errors are recorded on the order.');
$GLOBALS['refund_fail'] = false;
$result = ttn_mobile_booking_route_change(new WP_REST_Request(array('id' => 42, 'action' => 'cancel', 'revision' => ttn_booking_management_revision(42))));
check($result->data['success'] && $GLOBALS['meta'][42]['ttn_booking_status'] === 'cancelled' && !isset($GLOBALS['meta'][43]), 'Cancellation releases all slots.');
check(ttn_booking_get_member_free_hours_used(13, $date) === 0 && $GLOBALS['orders'][99]->remaining == 0, 'Cancellation restores free hours and refunds payment.');
check(is_wp_error(ttn_mobile_booking_route_change(new WP_REST_Request(array('id' => 42, 'action' => 'cancel', 'revision' => ttn_booking_management_revision(42))))), 'Repeat cancellation cannot refund twice.');
fixture($date);
$GLOBALS['orders'][99]->items = array(1, 2);
check(ttn_crud_cancel_user_booking(42, $user->user_email)['success'] === false && $GLOBALS['orders'][99]->remaining === 15, 'Shared orders must never refund another booking.');
fixture($date);
$GLOBALS['meta'][42]['ttn_booking_players'] = 1;
$GLOBALS['meta'][42]['ttn_booking_total_price'] = 60;
$GLOBALS['orders'][99]->remaining = 60;
$quote = ttn_booking_prepare_schedule_change(42, $user, array_merge($selection, array('duration' => 1)));
check($quote['difference'] === -60.0, 'Server reviews reduced prices.');
$result = ttn_mobile_booking_route_change(new WP_REST_Request(array_merge($quote, array('id' => 42, 'action' => 'reschedule'))));
check($result->data['success'] && $GLOBALS['orders'][99]->remaining == 0 && $GLOBALS['meta'][42]['ttn_booking_member_free_hours'] === 1, 'Lower-price reschedule refunds and updates free-hour use.');
fixture($date);
$quote = ttn_booking_prepare_schedule_change(42, $user, $selection);
$item_meta = array('_ttn_booking_extension_booking_id' => 42, '_ttn_booking_extension_price_difference' => $quote['difference']);
foreach (array('bay', 'date', 'time', 'duration', 'new_total_price', 'quote_id') as $key) {
    $item_meta['_ttn_booking_extension_' . $key] = $quote[$key];
}
$GLOBALS['orders'][1000] = new TestOrder(1000, 15, array(new TestItem($item_meta)));
ttn_booking_process_extension_wc_order(1000);
check($GLOBALS['meta'][42]['ttn_booking_duration'] === 2 && $GLOBALS['meta'][42]['ttn_booking_member_free_hours'] === 2, 'Paid reschedule updates duration and free-hour usage.');
check($GLOBALS['meta'][42]['ttn_booking_extension_order_ids'] === array(1000), 'Additional order stays linked for later cancellation/refund.');
check($GLOBALS['orders'][1000]->get_meta('_ttn_booking_extension_processed') === 'yes', 'Payment is applied only once.');
ttn_booking_process_extension_wc_order(1000);
check(count($GLOBALS['meta']) === 2, 'Repeated payment hooks do not duplicate child slots.');

fixture($date);
$quote = ttn_booking_prepare_schedule_change(42, $user, $selection);
$item_meta['_ttn_booking_extension_quote_id'] = $quote['quote_id'];
$GLOBALS['orders'][1000] = new TestOrder(1000, 15, array(new TestItem($item_meta)));
$GLOBALS['meta'][60] = array('ttn_booking_bay' => 'Apex', 'ttn_booking_date' => $date, 'ttn_booking_time' => '4:00 PM');
ttn_booking_process_extension_wc_order(1000);
check($GLOBALS['meta'][42]['ttn_booking_duration'] === 1 && $GLOBALS['orders'][1000]->remaining == 0, 'Availability is rechecked after payment; conflicts refund the new payment without moving the old booking.');
check($GLOBALS['orders'][1000]->get_meta('_ttn_booking_extension_failed') !== '', 'Failed paid changes have an explicit recorded reason.');

fixture($date);
$GLOBALS['insert_fail'] = true;
$result = ttn_apply_booking_schedule_update(42, $quote);
check(!$result['success'] && $GLOBALS['meta'][42]['ttn_booking_duration'] === 1, 'Failed child creation leaves the original reservation unchanged.');
$GLOBALS['insert_fail'] = false;

$before_cutoff = (new DateTimeImmutable('now', wp_timezone()))->modify('+23 hours');
$after_cutoff = (new DateTimeImmutable('now', wp_timezone()))->modify('+25 hours');
check(!ttn_booking_is_within_self_service_window($before_cutoff->format('Y-m-d'), $before_cutoff->format('g:00 A')), 'A start under 24 hours is blocked.');
check(ttn_booking_is_within_self_service_window($after_cutoff->format('Y-m-d'), $after_cutoff->format('g:00 A')), 'A start over 24 hours is allowed.');

fixture($date);
$quote = ttn_booking_prepare_schedule_change(42, $user, $selection);
$GLOBALS['wc'] = (object) array('cart' => new WC_Cart());
$GLOBALS['transients'] = array();
ttn_mobile_booking_route_change(new WP_REST_Request(array_merge($quote, array('id' => 42, 'action' => 'reschedule'))));
$_GET['ttn_mobile_bridge'] = str_repeat('x', 48);
try {
    ttn_mobile_handle_checkout_bridge();
    throw new RuntimeException('Checkout must redirect.');
} catch (TestRedirect $redirect) {
    check($redirect->getMessage() === 'https://example.test/checkout/', 'Bridge opens existing secure checkout.');
}
check(count($GLOBALS['wc']->cart->items) === 1 && isset($GLOBALS['wc']->cart->items[0]['ttn_booking_extension']), 'Reschedule bridge populates an extension cart, not a new booking cart.');
check($GLOBALS['wc']->cart->items[0]['ttn_booking_extension']['quote_id'] === $quote['quote_id'], 'Reviewed server price survives the checkout handoff.');
check(!$GLOBALS['transients'], 'Bridge token is single-use.');
$errors = new TestErrors();
ttn_booking_validate_reschedule_checkout(array(), $errors);
check(!$errors->errors, 'Valid extension checkout is accepted.');
$GLOBALS['meta'][44] = array('ttn_booking_bay' => 'Apex', 'ttn_booking_date' => $date, 'ttn_booking_time' => '4:00 PM');
ttn_booking_validate_reschedule_checkout(array(), $errors);
check(count($errors->errors) === 1, 'Checkout rejects availability changes before charging.');
echo "Mobile booking management tests passed.\n";
