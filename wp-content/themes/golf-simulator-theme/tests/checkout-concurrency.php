<?php
if (PHP_SAPI !== 'cli') {
    exit;
}

define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);

$options = array();
$post_meta = array();
$posts = array();
$next_post_id = 500;
$refunds_created = array();
$order_notes = array();

function add_action($hook, $callback, $priority = 10, $args = 1) {}
function add_filter($hook, $callback, $priority = 10, $args = 1) {}
function remove_filter($hook, $callback, $priority = 10) {}
function add_shortcode($tag, $callback) {}
function register_deactivation_hook($file, $callback) {}
function apply_filters($hook, $value) { return $value; }
function absint($value) { return abs((int) $value); }
function __($text, $domain = 'default') { return $text; }
function _e($text, $domain = 'default') { echo $text; }
function _n($single, $plural, $number, $domain = 'default') { return $number === 1 ? $single : $plural; }
function sanitize_key($key) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key)); }
function sanitize_text_field($val) { return trim((string) $val); }
function sanitize_title($title) { return sanitize_key($title); }
function sanitize_email($email) { return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : ''; }
function wp_unslash($val) { return $val; }
function wp_hash($val) { return md5($val); }
function is_wp_error($thing) { return $thing instanceof WP_Error; }
function is_email($email) { return (bool) filter_var($email, FILTER_VALIDATE_EMAIL); }
function email_exists($email) { return 1; }
function get_theme_mod($name, $default = false) { return $default; }
function get_site_icon_url() { return ''; }
function wp_get_attachment_image_src($id) { return false; }
function get_bloginfo($name) { return 'Tee Time Nexus'; }
function wp_mail($to, $subject, $message, $headers = '') { return true; }
function esc_html($val) { return htmlspecialchars((string) $val); }
function esc_attr($val) { return htmlspecialchars((string) $val, ENT_QUOTES); }
function esc_url($val) { return $val; }
function home_url($path = '') { return 'https://example.test' . $path; }
function add_query_arg($key, $value = null, $url = null) {
    if (is_array($key)) {
        return ($value ?: '') . '?' . http_build_query($key);
    }
    return ($url ?: '') . '?' . http_build_query(array($key => $value));
}
function wp_json_encode($val) { return json_encode($val); }
function wp_timezone() { return new DateTimeZone('America/New_York'); }
function current_time($format) {
    return (new DateTimeImmutable('now', wp_timezone()))->format($format === 'mysql' ? 'Y-m-d H:i:s' : $format);
}
function wp_generate_password($len = 12, $special = true) {
    return substr(bin2hex(random_bytes($len)), 0, $len);
}
function is_user_logged_in() { return !empty($GLOBALS['current_test_user_id']); }
function get_current_user_id() { return $GLOBALS['current_test_user_id'] ?? 0; }
function get_userdata($id) { return (object) array('ID' => $id, 'user_email' => 'user' . $id . '@example.com'); }
function get_user_by($field, $val) { return (object) array('ID' => 1, 'user_email' => 'user1@example.com'); }

function get_option($key, $default = false) {
    return $GLOBALS['options'][$key] ?? $default;
}
function add_option($key, $value, $deprecated = '', $autoload = false) {
    if (isset($GLOBALS['options'][$key])) {
        return false;
    }
    $GLOBALS['options'][$key] = $value;
    return true;
}
function update_option($key, $value) {
    $GLOBALS['options'][$key] = $value;
    return true;
}
function delete_option($key) {
    unset($GLOBALS['options'][$key]);
    return true;
}
function wp_cache_delete($key, $group = '') { return true; }

function get_post_meta($post_id, $key, $single = true) {
    return $GLOBALS['post_meta'][$post_id][$key] ?? ($single ? '' : array());
}
function update_post_meta($post_id, $key, $value) {
    $GLOBALS['post_meta'][$post_id][$key] = $value;
    return true;
}
function get_post($post_id) {
    return $GLOBALS['posts'][$post_id] ?? null;
}
function wp_insert_post($args, $wp_error = false) {
    $id = $GLOBALS['next_post_id']++;
    $GLOBALS['posts'][$id] = (object) array_merge(array('ID' => $id), $args);
    $GLOBALS['post_meta'][$id] = array();
    return $id;
}
function get_posts($args = array()) {
    $ids = array();
    foreach ($GLOBALS['posts'] as $id => $post) {
        if (!empty($args['meta_query'])) {
            $match = true;
            foreach ($args['meta_query'] as $q) {
                if (($GLOBALS['post_meta'][$id][$q['key']] ?? '') !== $q['value']) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                $ids[] = $id;
            }
        } else {
            $ids[] = $id;
        }
    }
    return !empty($args['fields']) && $args['fields'] === 'ids' ? $ids : array();
}

function wc_create_refund($args) {
    $GLOBALS['refunds_created'][] = $args;
    return (object) array('id' => 999);
}

class TestWcItem {
    private $meta = array();
    public function __construct($meta) { $this->meta = $meta; }
    public function get_meta($key) { return $this->meta[$key] ?? ''; }
    public function get_subtotal() { return 60.00; }
    public function get_subtotal_tax() { return 0.00; }
    public function get_total() { return 60.00; }
    public function get_total_tax() { return 0.00; }
}

class WC_Order {}

class TestWcOrder extends WC_Order {
    private $id;
    private $items = array();
    private $meta = array();
    private $paid = true;
    public function __construct($id, $items, $paid = true) {
        $this->id = $id;
        $this->items = $items;
        $this->paid = $paid;
    }
    public function get_id() { return $this->id; }
    public function get_order_number() { return $this->id; }
    public function get_order_key() { return 'order_key_' . $this->id; }
    public function get_items() { return $this->items; }
    public function is_paid() { return $this->paid; }
    public function get_meta($key) { return $this->meta[$key] ?? ''; }
    public function update_meta_data($key, $val) { $this->meta[$key] = $val; }
    public function save() {}
    public function add_order_note($note) { $GLOBALS['order_notes'][$this->id][] = $note; }
    public function get_remaining_refund_amount() { return 120.00; }
    public function get_billing_first_name() { return 'John'; }
    public function get_billing_last_name() { return 'Doe'; }
    public function get_billing_email() { return 'john@example.com'; }
    public function get_billing_phone() { return '555-1234'; }
    public function get_formatted_billing_full_name() { return 'John Doe'; }
    public function get_customer_id() { return 1; }
    public function set_customer_id($id) {}
    public function get_coupon_codes() { return array(); }
}

$test_orders = array();
function wc_get_order($id) {
    return $GLOBALS['test_orders'][$id] ?? null;
}

require_once dirname(__DIR__, 3) . '/plugins/tee-time-nexus-bookings/tee-time-nexus-bookings.php';

function assert_true($condition, $message) {
    if (!$condition) {
        throw new RuntimeException("Assertion failed: $message");
    }
}
function assert_false($condition, $message) {
    if ($condition) {
        throw new RuntimeException("Assertion failed: $message");
    }
}
function assert_equal($actual, $expected, $message) {
    if ($actual !== $expected) {
        throw new RuntimeException("Assertion failed: $message. Expected " . var_export($expected, true) . ", got " . var_export($actual, true));
    }
}

echo "Running payment-time concurrency and smart alternatives tests...\n";

$test_date_1 = (new DateTimeImmutable('+7 days', wp_timezone()))->format('Y-m-d');
$test_date_2 = (new DateTimeImmutable('+14 days', wp_timezone()))->format('Y-m-d');

// Test 1: Competing checkout order processing - Order A wins the slot
$item_a = new TestWcItem(array(
    'ttn_booking_bay' => 'Apex',
    'ttn_booking_date' => $test_date_1,
    'ttn_booking_time' => '2:00 PM',
    'ttn_booking_duration' => 1,
    'ttn_booking_players' => 2,
));
$order_a = new TestWcOrder(101, array($item_a), true);
$GLOBALS['test_orders'][101] = $order_a;
ttn_booking_process_wc_order(101);

assert_equal($order_a->get_meta('_ttn_booking_processed'), 'yes', 'Order A processed successfully');
$created_posts = ttn_booking_get_booking_records();
assert_true(count($created_posts) >= 1, 'Booking record created for Order A');

// Test 2: Order B attempts the exact same slot atomically at payment completion
$item_b = new TestWcItem(array(
    'ttn_booking_bay' => 'Apex',
    'ttn_booking_date' => $test_date_1,
    'ttn_booking_time' => '2:00 PM',
    'ttn_booking_duration' => 1,
    'ttn_booking_players' => 1,
));
$order_b = new TestWcOrder(102, array($item_b), true);
$GLOBALS['test_orders'][102] = $order_b;
ttn_booking_process_wc_order(102);

// Order B must NOT create duplicate booking, must trigger automatic refund, and save alternatives
assert_equal($order_b->get_meta('_ttn_booking_processed'), 'failed', 'Order B marked failed');
assert_equal($order_b->get_meta('_ttn_booking_failed'), 'overbooked', 'Order B flagged overbooked');
assert_equal(count($GLOBALS['refunds_created']), 1, 'Automatic refund created for Order B');
assert_equal($GLOBALS['refunds_created'][0]['order_id'], 102, 'Refund created specifically for Order B');
assert_true(!empty($order_b->get_meta('_ttn_booking_alternatives')), 'Smart alternatives calculated and saved on order');
assert_true(!empty($order_b->get_meta('_ttn_booking_alternatives_message')), 'User-facing alternatives message saved');

// Test 3: Return URL redirects overbooked orders to booking_failed=1 with alternative message
$return_url = ttn_booking_extension_return_url('https://example.test/checkout/order-received/102/', $order_b);
assert_true(strpos($return_url, 'booking_failed=1') !== false, 'Return URL includes booking_failed=1');
assert_true(strpos($return_url, 'alt_msg=') !== false, 'Return URL passes alt_msg parameter');

// Test 4: Pre-charge checkout validation (woocommerce_after_checkout_validation)
class TestWcErrors {
    public $errors = array();
    public function add($code, $message) { $this->errors[$code] = $message; }
    public function has_errors() { return !empty($this->errors); }
}
class TestWcCart {
    private $cart = array();
    public function __construct($cart = array()) { $this->cart = $cart; }
    public function get_cart() { return $this->cart; }
}
class TestWooCommerce {
    public $cart;
    public function __construct($cart = null) {
        $this->cart = $cart ?: new TestWcCart();
    }
}
function WC() { return $GLOBALS['test_wc']; }

// Case 4a: Cart contains slot that was already booked (Apex at 2:00 PM on test_date_1)
$GLOBALS['test_wc'] = new TestWooCommerce(new TestWcCart(array(
    array('ttn_booking' => array(
        'bay' => 'Apex',
        'date' => $test_date_1,
        'time' => '2:00 PM',
        'duration' => 1,
    )),
)));
$errors_booked = new TestWcErrors();
ttn_booking_validate_cart_checkout(array('billing_email' => 'other@example.com'), $errors_booked);
assert_true(isset($errors_booked->errors['ttn_slot_unavailable']), 'Validation stops pre-charge when slot is already booked');
assert_true(strpos($errors_booked->errors['ttn_slot_unavailable'], 'quicker than you') !== false, 'Message states someone was quicker');
assert_true(strpos($errors_booked->errors['ttn_slot_unavailable'], 'Good news') !== false || strpos($errors_booked->errors['ttn_slot_unavailable'], 'Or choose') !== false, 'Smart alternatives suggested in error message');

// Case 4b: Cart contains an available slot - validation succeeds
$GLOBALS['test_wc'] = new TestWooCommerce(new TestWcCart(array(
    array('ttn_booking' => array(
        'bay' => 'Nexus',
        'date' => $test_date_1,
        'time' => '2:00 PM',
        'duration' => 1,
    )),
)));
$errors_available = new TestWcErrors();
ttn_booking_validate_cart_checkout(array('billing_email' => 'valid@example.com'), $errors_available);
assert_false($errors_available->has_errors(), 'Available slot passes validation with no errors');

// Test 5: ttn_booking_find_alternative_slots function unit tests
$alts = ttn_booking_find_alternative_slots('Apex', $test_date_1, '2:00 PM', 1, 0);
assert_true(!empty($alts['bay_alternatives']), 'Finds other bays available at 2:00 PM');
$other_bays = array_column($alts['bay_alternatives'], 'bay');
assert_true(in_array('Nexus', $other_bays, true) || in_array('Fairway', $other_bays, true), 'Nexus or Fairway suggested for 2:00 PM');

$alts_time = ttn_booking_find_alternative_slots('Apex', $test_date_1, '2:00 PM', 1, 0);
assert_true(!empty($alts_time['time_alternatives']), 'Finds alternative times in Apex');
$alt_times = array_column($alts_time['time_alternatives'], 'time');
assert_false(in_array('2:00 PM', $alt_times, true), 'Booked time 2:00 PM is NOT in alternative times');

$formatted_msg = ttn_booking_format_alternatives_message($alts);
assert_true(strpos($formatted_msg, 'available at the same time') !== false, 'Formatted message mentions other bays at same time');

echo "All payment-time concurrency and smart alternative tests passed successfully!\n";
