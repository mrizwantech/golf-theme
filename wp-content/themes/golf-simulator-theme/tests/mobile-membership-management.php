<?php

if (PHP_SAPI !== 'cli') {
    exit;
}
define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
$options = array();
$meta = array();
$events = array();
$routes = array();
$hooks = array();
$save_fails = false;
$schedule_fails = false;
$meta_fails = false;
$mail = array();
$transients = array();

class WP_User {
    public $ID;
    public $user_email = 'member@example.test';
    public $display_name = 'Test Member';
    public function __construct($id) { $this->ID = $id; }
}
class WP_Error {
    public $code;
    public function __construct($code, $message, $data = array()) { $this->code = $code; }
    public function add($code, $message) { $this->code = $code; }
}
class WP_REST_Request {
    private $params;
    public function __construct($params) { $this->params = $params; }
    public function get_param($key) { return $this->params[$key] ?? null; }
}
class TestResponse {
    public $data;
    public $headers = array();
    public function __construct($data) { $this->data = $data; }
    public function header($key, $value) { $this->headers[$key] = $value; }
}
class TestDatabase {
    public $prefix = 'wp_';
    public $options = 'wp_options';
    public $rows = array();
    public $insert_id = 1;
    public function prepare($sql, ...$args) { return $args; }
    public function get_row($args) { return isset($this->rows[$args[0]]) ? clone $this->rows[$args[0]] : null; }
    public function update($table, $data, $where, $formats, $where_formats) {
        if ($GLOBALS['save_fails']) return false;
        $this->rows[$data['user_id']] = (object) array_merge(array('id' => $where['id']), $data);
        return 1;
    }
    public function insert($table, $data, $formats) { return !$GLOBALS['save_fails']; }
    public function query($args) { unset($GLOBALS['options'][$args[0]]); return 1; }
}
$wpdb = new TestDatabase();

function add_action($key, $callback, $priority = 10, $args = 1) { $GLOBALS['hooks'][$key][] = $callback; }
function add_filter($key, $callback, $priority = 10, $args = 1) {}
function add_shortcode($key, $callback) {}
function do_action($key, ...$args) { $GLOBALS['fired'][] = array($key, $args); }
function absint($value) { return abs((int) $value); }
function wp_parse_args($args, $defaults) { return array_merge($defaults, $args); }
function sanitize_text_field($value) { return strip_tags($value); }
function sanitize_title($value) { return strtolower($value); }
function sanitize_key($value) { return strtolower($value); }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function add_option($key, $value, $deprecated = '', $autoload = false) {
    if (isset($GLOBALS['options'][$key])) return false;
    $GLOBALS['options'][$key] = $value;
    return true;
}
function delete_option($key) { unset($GLOBALS['options'][$key]); return true; }
function wp_cache_delete($key, $group) {}
function get_user_meta($id, $key, $single) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function update_user_meta($id, $key, $value) {
    if ($GLOBALS['meta_fails']) return false;
    $GLOBALS['meta'][$id][$key] = $value;
    return true;
}
function delete_user_meta($id, $key) { unset($GLOBALS['meta'][$id][$key]); return true; }
function wp_schedule_single_event($time, $hook, $args, $wp_error = false) {
    if ($GLOBALS['schedule_fails']) return new WP_Error('cron_failed', 'Failed');
    $GLOBALS['events'][$args[0]] = $time;
    return true;
}
function wp_clear_scheduled_hook($hook, $args) { unset($GLOBALS['events'][$args[0]]); return 1; }
function wp_timezone() { return new DateTimeZone('America/Toronto'); }
function wp_date($format, $time, $timezone) { return (new DateTimeImmutable('@' . $time))->setTimezone($timezone)->format($format); }
function current_time($format) { return 'timestamp' === $format ? time() + 3600 * (int) date('I') - 5 * 3600 : wp_date('Y-m-d H:i:s', time(), wp_timezone()); }
function mysql2date($format, $value, $translate = false) { return (new DateTimeImmutable($value, wp_timezone()))->format($format); }
function get_userdata($id) { return new WP_User($id); }
function is_email($value) { return true; }
function esc_html($value) { return htmlspecialchars($value); }
function home_url($path) { return 'https://example.test' . $path; }
function golf_simulator_theme_render_email_template($eyebrow, $heading, $body, $label, $url) { return $body; }
function golf_simulator_theme_get_email_headers() { return array(); }
function wp_mail($email, $subject, $body, $headers) { $GLOBALS['mail'][] = $body; return true; }
function golf_simulator_theme_require_contact_email($id) { return true; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function rest_ensure_response($value) { return new TestResponse($value); }
function register_rest_route($namespace, $path, $args) { $GLOBALS['routes'][$path] = $args; }
function WC() { return $GLOBALS['wc'] ?? null; }
function get_current_user_id() { return 1; }
function wc_get_order($id) { return $GLOBALS['order']; }
function wc_load_cart() {}
function wc_get_checkout_url() { return 'https://example.test/checkout'; }
function wp_generate_password($length, $special) { return str_repeat('x', $length); }
function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = $value; return true; }
function add_query_arg($key, $value, $url) { return $url . '?' . $key . '=' . $value; }
function check_management($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function expect_error($value, $code) { check_management($value instanceof WP_Error && $value->code === $code, 'Expected error: ' . $code); }
function reset_member($key = 'ALBATROSS') {
    $packages = golf_simulator_theme_get_default_membership_packages();
    $GLOBALS['meta'] = array();
    $GLOBALS['events'] = array();
    $GLOBALS['options'] = array();
    $GLOBALS['save_fails'] = $GLOBALS['schedule_fails'] = $GLOBALS['meta_fails'] = false;
    $GLOBALS['wpdb']->rows[1] = (object) array(
        'id' => 10, 'user_id' => 1, 'package_name' => $key, 'package_slug' => strtolower($key),
        'price' => $packages[$key]['price'], 'discount_price' => $packages[$key]['discount_price'],
        'status' => 'active', 'payment_status' => 'paid',
        'start_date' => wp_date('Y-m-d H:i:s', time() - 10 * 86400, wp_timezone()),
        'next_billing_date' => wp_date('Y-m-d H:i:s', time() + 20 * 86400, wp_timezone()),
        'payment_date' => current_time('mysql'), 'cancel_date' => '', 'updated_at' => current_time('mysql'),
    );
    return golf_simulator_theme_membership_revision($GLOBALS['wpdb']->rows[1]);
}

require dirname(__DIR__) . '/inc/membership.php';
require dirname(__DIR__) . '/inc/mobile-membership-api.php';
$old_log = ini_get('error_log');
ini_set('error_log', 'php://stderr');
try {
    $revision = reset_member();
    $current = golf_simulator_theme_mobile_membership_current(new WP_REST_Request(array('ttn_auth_user' => new WP_User(1))));
    check_management($current->data['package_name'] === 'EAGLE' && $current->data['package_key'] === 'ALBATROSS', 'Current membership must expose display name separately from canonical key.');
    check_management($current->headers['Cache-Control'] === 'private, no-store', 'Account data must not be cached.');
    expect_error(golf_simulator_theme_mobile_membership_manage(new WP_REST_Request(array('user_id' => 1))), 'membership_not_authenticated');
    expect_error(golf_simulator_theme_manage_membership(1, 'change', 'invalid', $revision), 'membership_invalid_package');
    expect_error(golf_simulator_theme_manage_membership(1, 'change', 'EAGLE', $revision), 'membership_invalid_package');
    expect_error(golf_simulator_theme_manage_membership(1, 'cancel', '', 'old'), 'membership_changed');
    $GLOBALS['options']['ttn_membership_change_lock_1'] = time();
    expect_error(golf_simulator_theme_manage_membership(1, 'cancel', '', $revision), 'membership_busy');
    $GLOBALS['options']['ttn_membership_change_lock_1'] = time() - 121;
    $result = golf_simulator_theme_manage_membership(1, 'cancel', '', $revision);
    check_management(!is_wp_error($result), 'A stale lock must recover.');
    check_management($wpdb->rows[1]->status === 'active' && $wpdb->rows[1]->payment_status === 'paid', 'Cancellation must retain paid benefits until period end.');
    check_management($meta[1]['_ttn_membership_change']['effective_at'] === golf_simulator_theme_membership_period_end($wpdb->rows[1]), 'Use website timezone and exact billing date.');
    check_management(strpos(end($mail), 'EAGLE') !== false && strpos(end($mail), 'ALBATROSS') === false, 'Emails must use EAGLE.');
    expect_error(golf_simulator_theme_manage_membership(1, 'change', 'PAR', $revision), 'membership_change_pending');
    check_management(!is_wp_error(golf_simulator_theme_manage_membership(1, 'undo', '', $revision)) && !$meta[1], 'Scheduled cancellation can be removed.');
    golf_simulator_theme_manage_membership(1, 'cancel', '', $revision);
    $meta[1]['_ttn_membership_change']['effective_at'] = time() - 1;
    $cancelled = golf_simulator_theme_get_user_membership_record(1);
    check_management($cancelled->status === 'cancelled' && $cancelled->payment_status === 'cancelled', 'A late cron must not allow cancelled membership on read.');
    check_management(!$meta[1] && !isset($events[1]), 'Applied changes must clear scheduling metadata.');
    check_management(in_array('ttn_membership_record_saved', array_column($GLOBALS['fired'], 0), true), 'Cancellation must trigger existing Kisi revocation hook.');

    $revision = reset_member();
    golf_simulator_theme_manage_membership(1, 'change', 'PAR', $revision);
    check_management($wpdb->rows[1]->package_name === 'ALBATROSS', 'Downgrade must retain current tier before period end.');
    $meta[1]['_ttn_membership_change']['effective_at'] = time() - 1;
    $downgraded = golf_simulator_theme_get_user_membership_record(1);
    check_management($downgraded->package_name === 'PAR' && $downgraded->status === 'pending' && $downgraded->payment_status === 'pending', 'Downgrade must never grant an unpaid next month.');

    $revision = reset_member('PAR');
    $before = clone $wpdb->rows[1];
    $upgrade = golf_simulator_theme_manage_membership(1, 'change', 'EAGLE', $revision);
    $expected = golf_simulator_theme_calculate_prorated_upgrade_amount($before, golf_simulator_theme_get_default_membership_packages()['ALBATROSS']);
    check_management($upgrade['checkout']['custom_price'] === $expected && $expected > 0, 'Upgrade amount must use existing prorated helper.');
    check_management(abs($expected - round((399 - 200) * 20 / 30, 2)) <= 0.01, 'Proration must use real timestamps, not timezone-shifted pseudo-timestamps.');
    check_management($wpdb->rows[1] == $before && !$meta, 'Starting checkout must not activate unpaid benefits.');
    $response = golf_simulator_theme_mobile_membership_manage(new WP_REST_Request(array('ttn_auth_user' => new WP_User(1), 'action' => 'change', 'package' => 'EAGLE', 'revision' => $revision)));
    $bridge = end($transients);
    check_management($bridge['package'] === 'ALBATROSS' && $bridge['is_upgrade'] && $bridge['custom_price'] === $expected, 'Mobile bridge must retain the real upgrade flags and quote.');
    expect_error(golf_simulator_theme_mobile_membership_checkout(new WP_REST_Request(array('ttn_auth_user' => new WP_User(1), 'package' => 'PAR'))), 'membership_already_active');
    $GLOBALS['wc'] = (object) array('cart' => new class($revision) {
        private $revision;
        public function __construct($revision) { $this->revision = $revision; }
        public function get_cart() { return array(array('golf_simulator_membership' => array('is_upgrade' => true, 'membership_revision' => $this->revision))); }
    });
    $errors = new WP_Error('', '');
    golf_simulator_theme_validate_membership_upgrade_checkout(array(), $errors);
    check_management($errors->code === '', 'Valid upgrade checkout must remain available.');
    $meta[1]['_ttn_membership_change'] = array('action' => 'cancel', 'package' => 'PAR', 'effective_at' => time() + 1000);
    golf_simulator_theme_validate_membership_upgrade_checkout(array(), $errors);
    expect_error($errors, 'membership_changed');
    unset($meta[1]['_ttn_membership_change']);
    $GLOBALS['order'] = new class($revision) {
        public $meta = array();
        public $notes = array();
        private $revision;
        public function __construct($revision) { $this->revision = $revision; }
        public function get_meta($key) { return $this->meta[$key] ?? ''; }
        public function get_user_id() { return 1; }
        public function get_total() { return 132.67; }
        public function get_items() {
            return array(new class($this->revision) {
                private $revision;
                public function __construct($revision) { $this->revision = $revision; }
                public function get_meta($key) {
                    return array('_membership_package_name' => 'ALBATROSS', '_is_membership_upgrade' => 'yes', '_membership_revision' => $this->revision)[$key] ?? '';
                }
            });
        }
        public function update_meta_data($key, $value) { $this->meta[$key] = $value; }
        public function add_order_note($message) { $this->notes[] = $message; }
        public function save() {}
    };
    golf_simulator_theme_process_membership_wc_order(123);
    check_management($wpdb->rows[1]->package_name === 'ALBATROSS' && $wpdb->rows[1]->status === 'active', 'Only successful payment activates the upgrade.');
    check_management($wpdb->rows[1]->start_date === $before->start_date && $wpdb->rows[1]->next_billing_date === $before->next_billing_date, 'Prorated upgrades must preserve the original paid cycle.');
    check_management($GLOBALS['order']->meta['_membership_processed'] === 'yes', 'Successful payment must be idempotently recorded.');
    $paid = clone $wpdb->rows[1];
    golf_simulator_theme_process_membership_wc_order(123);
    check_management($wpdb->rows[1] == $paid, 'Repeated payment hooks must not reset the period.');
    unset($GLOBALS['order']->meta['_membership_processed']);
    $wpdb->rows[1]->next_billing_date = '2020-01-01 00:00:00';
    golf_simulator_theme_process_membership_wc_order(123);
    check_management($GLOBALS['order']->meta['_membership_review_required'] === 'yes'
        && golf_simulator_theme_membership_management_record(1)['management_notice'] !== '', 'Late/changed upgrade payments must surface support review instead of granting an unpaid period.');

    $revision = reset_member();
    $schedule_fails = true;
    expect_error(golf_simulator_theme_manage_membership(1, 'cancel', '', $revision), 'membership_schedule_failed');
    check_management(!$meta, 'Failed cron must not save a success-shaped scheduled change.');
    $schedule_fails = false;
    $meta_fails = true;
    expect_error(golf_simulator_theme_manage_membership(1, 'cancel', '', $revision), 'membership_save_failed');
    check_management(!isset($events[1]), 'Failed persistence must remove the cron event.');
    $meta_fails = false;
    golf_simulator_theme_manage_membership(1, 'cancel', '', $revision);
    $meta[1]['_ttn_membership_change']['effective_at'] = time() - 1;
    $save_fails = true;
    check_management(golf_simulator_theme_get_user_membership_record(1)->status === 'pending', 'Failed due persistence must fail closed for membership access.');
    golf_simulator_theme_membership_change_due(1);
    check_management(isset($events[1]) && $events[1] > time(), 'Failed cron reconciliation must schedule a retry.');
    $save_fails = false;
    check_management(golf_simulator_theme_get_user_membership_record(1)->status === 'cancelled', 'Due persistence must recover.');
    reset_member();
    $wpdb->rows[1]->next_billing_date = '2020-01-01 00:00:00';
    expect_error(golf_simulator_theme_manage_membership(1, 'cancel', '', golf_simulator_theme_membership_revision($wpdb->rows[1])), 'membership_period_unknown');
    reset_member();
    $wpdb->rows[1]->price = null;
    $wpdb->rows[1]->discount_price = null;
    $wpdb->rows[1]->cancel_date = '';
    $record = golf_simulator_theme_membership_management_record(1);
    check_management($record['price'] === '' && $record['discount_price'] === '' && $record['cancel_date'] === null
        && is_string($record['status']) && is_string($record['payment_status']), 'Nullable membership columns must reach the app as strings or null dates.');
    foreach ($hooks['rest_api_init'] as $callback) { $callback(); }
    check_management($routes['/membership/manage']['permission_callback'] === 'ttn_jwt_authenticate_request', 'All changes require JWT authentication.');
} finally {
    ini_set('error_log', $old_log);
}
echo "Membership management passed: EAGLE naming, authenticated actions, delayed cancellation/downgrade, paid upgrade quote, undo, cron recovery, access revocation, and persistence failures.\n";
