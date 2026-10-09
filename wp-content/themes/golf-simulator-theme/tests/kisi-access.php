<?php

if (PHP_SAPI !== 'cli') {
    exit;
}
define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
define('TTN_KISI_API_KEY', 'test-only-placeholder');
define('TTN_KISI_ORGANIZATION_ID', 100);
define('TTN_KISI_BOOKING_GROUP_ID', 200);
define('TTN_KISI_ENTRANCE_LOCK_ID', 300);

class WP_Error {
    public $code;
    public $message;
    public $data;
    public function __construct($code, $message, $data = array()) { $this->code = $code; $this->message = $message; $this->data = $data; }
}
class WP_User {
    public $ID = 1;
    public $user_email = 'member@example.test';
    public $display_name = 'Test Member';
}
class WP_REST_Request {
    public $params;
    public function __construct($params) { $this->params = $params; }
    public function get_param($key) { return $this->params[$key] ?? null; }
}
class WP_REST_Response {
    public $data;
    public $headers = array();
    public function __construct($data) { $this->data = $data; }
    public function header($name, $value) { $this->headers[$name] = $value; }
}
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function is_wp_error($value) { return $value instanceof WP_Error; }
function add_action($hook, $callback, $priority = 10, $accepted = 1) { $GLOBALS['hooks'][$hook][] = $callback; }
function add_filter($hook, $callback) { add_action($hook, $callback); }
function do_action($hook, ...$args) { foreach ($GLOBALS['hooks'][$hook] ?? array() as $callback) { $callback(...$args); } }
function get_post_type($id) { return isset($GLOBALS['bookings'][$id]) ? 'ttn_booking' : ''; }
function get_post_status($id) { return $GLOBALS['bookings'][$id]['post_status'] ?? 'publish'; }
function get_post_meta($id, $key, $single = true) { return $GLOBALS['bookings'][$id][$key] ?? ''; }
function update_post_meta($id, $key, $value) {
    $GLOBALS['bookings'][$id][$key] = $value;
    do_action('updated_post_meta', 0, $id, $key, $value);
}
function get_user_meta($id, $key, $single = true) { return $GLOBALS['user_meta'][$id][$key] ?? ''; }
function update_user_meta($id, $key, $value) { $GLOBALS['user_meta'][$id][$key] = $value; }
function delete_user_meta($id, $key) { unset($GLOBALS['user_meta'][$id][$key]); }
function get_users($args) {
    $ids = array();
    foreach ($GLOBALS['user_meta'] as $id => $meta) {
        if (!empty($meta[$args['meta_key']]) && (!isset($args['meta_value']) || $meta[$args['meta_key']] == $args['meta_value'])) {
            $ids[] = $id;
        }
    }
    return isset($args['number']) ? array_slice($ids, 0, $args['number']) : $ids;
}
function get_option($key) { return $GLOBALS['options'][$key] ?? false; }
function add_option($key, $value, $deprecated = '', $autoload = false) {
    if (isset($GLOBALS['options'][$key])) { return false; }
    $GLOBALS['options'][$key] = $value;
    return true;
}
function update_option($key, $value, $autoload = false) { $GLOBALS['options'][$key] = $value; }
function delete_option($key) { unset($GLOBALS['options'][$key]); }
function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = $value; }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function wp_next_scheduled($hook, $args = array()) { return $GLOBALS['scheduled'][$hook . json_encode($args)] ?? false; }
function wp_schedule_single_event($time, $hook, $args = array()) { $GLOBALS['scheduled'][$hook . json_encode($args)] = $time; }
function wp_schedule_event($time, $schedule, $hook) { wp_schedule_single_event($time, $hook); }
function wp_timezone() { return new DateTimeZone('America/Toronto'); }
function home_url($path = '/') { return 'https://example.test' . $path; }
function absint($value) { return abs((int) $value); }
function get_userdata($id) { $user = new WP_User(); $user->ID = $id; return $user; }
function rest_ensure_response($data) { return new WP_REST_Response($data); }
function register_rest_route($namespace, $path, $args) { $GLOBALS['routes'][$path] = $args; }
function ttn_booking_get_paid_membership_package_name($id) { return $GLOBALS['paid_membership'] ? 'PAR' : ''; }
function ttn_booking_get_all_time_slots() {
    $slots = array();
    for ($hour = 0; $hour < 24; $hour++) {
        $slots[] = array('label' => ($hour % 12 ?: 12) . ':00 ' . ($hour < 12 ? 'AM' : 'PM'), 'start' => sprintf('%02d:00', $hour));
    }
    return $slots;
}
function wp_json_encode($value) { return json_encode($value); }
function wp_cache_delete($key, $group) {}
function wp_remote_retrieve_response_code($response) { return $response['status']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function wc_get_order($id) { return new class {
    public function is_paid() { return $GLOBALS['order_paid']; }
}; }
function wp_remote_request($url, $options) {
    check(strpos($url, 'https://api.kisi.io/') === 0, 'Requests must use the fixed Kisi host.');
    check($options['redirection'] === 0 && $options['sslverify'] === true, 'TLS and redirect policy must be secure.');
    check($options['headers']['Authorization'] === 'KISI-LOGIN test-only-placeholder', 'Only the test server token is used in provisioning.');
    $path = substr($url, strlen('https://api.kisi.io'));
    $method = $options['method'];
    $body = isset($options['body']) ? json_decode($options['body'], true) : null;
    $GLOBALS['requests'][] = array($method, $path, $body);
    if ($GLOBALS['transport_error']) { return new WP_Error('network', 'Sensitive detail must not be returned'); }
    if ($GLOBALS['http_error']) { return array('status' => $GLOBALS['http_error'], 'body' => 'Sensitive API body'); }
    if ($method === 'DELETE') {
        if ($GLOBALS['delete_fails']) { return array('status' => 500, 'body' => 'Sensitive failure'); }
        $id = (int) basename($path);
        unset($GLOBALS['roles'][$id]);
        return array('status' => 204, 'body' => '');
    }
    if ($path === '/users' && $method === 'POST') {
        $data = array_merge($body['user'], array('id' => 400, 'organization_id' => 100));
        $GLOBALS['kisi_user'] = $data;
    } elseif (strpos($path, '/users') === 0) {
        $data = strpos($path, '?') !== false ? ($GLOBALS['kisi_user'] ? array($GLOBALS['kisi_user']) : array()) : $GLOBALS['kisi_user'];
    } elseif (strpos($path, '/group_locks?') === 0) {
        $data = $GLOBALS['group_links'];
    } elseif ($path === '/groups/200') {
        $data = $GLOBALS['group_settings'];
    } elseif (strpos($path, '/team_memberships?') === 0) {
        $data = $GLOBALS['teams'];
    } elseif ($path === '/role_assignments' && $method === 'POST') {
        $data = array_merge($body['role_assignment'], array('id' => ++$GLOBALS['role_sequence']));
        if ($GLOBALS['incorrect_bounds']) { $data['valid_until'] = '2099-01-01T00:00:00Z'; }
        $GLOBALS['roles'][$data['id']] = $data;
    } elseif (strpos($path, '/role_assignments?') === 0) {
        $data = array_values($GLOBALS['roles']);
    } elseif ($path === '/logins' && $method === 'POST') {
        check($body['login']['type'] === 'device' && $body['login']['email'] === 'member@example.test', 'Device login must target the authenticated member.');
        $data = array('id' => 500, 'user_id' => 400, 'type' => 'device', 'secret' => 'member-test-only', 'scram_credentials' => array('phone_key' => 'test-key', 'online_certificate' => 'test-certificate'));
        if ($GLOBALS['during_login']) { ($GLOBALS['during_login'])(); }
    } else {
        throw new RuntimeException('Unexpected endpoint: ' . $path);
    }
    return array('status' => 200, 'body' => json_encode($data));
}

$GLOBALS['hooks'] = array();
$GLOBALS['user_meta'] = array();
$GLOBALS['bookings'] = array();
$GLOBALS['options'] = array();
$GLOBALS['transients'] = array();
$GLOBALS['scheduled'] = array();
$GLOBALS['requests'] = array();
$GLOBALS['roles'] = array();
$GLOBALS['kisi_user'] = null;
$GLOBALS['group_links'] = array(array('group_id' => 200, 'lock_id' => 300));
$GLOBALS['group_settings'] = array('id' => 200, 'reader_restriction_enabled' => true, 'login_enabled' => true, 'tap_to_access_restriction_enabled' => false);
$GLOBALS['teams'] = array();
$GLOBALS['role_sequence'] = 600;
$GLOBALS['paid_membership'] = true;
$GLOBALS['order_paid'] = true;
$GLOBALS['delete_fails'] = false;
$GLOBALS['transport_error'] = false;
$GLOBALS['http_error'] = 0;
$GLOBALS['incorrect_bounds'] = false;
$GLOBALS['during_login'] = null;
require dirname(__DIR__, 3) . '/plugins/tee-time-nexus-bookings/inc/class-ttn-kisi-access.php';

check(ttn_kisi_validate_group_settings($GLOBALS['group_settings']) === true, 'Correct boolean group permissions must pass.');
foreach (array(
    'reader_restriction_enabled' => array('Reader proximity restriction', false),
    'login_enabled' => array('Allow app access', false),
    'tap_to_access_restriction_enabled' => array('Reader tap to access restriction', true),
) as $field => $setting) {
    $group = $GLOBALS['group_settings'];
    $group[$field] = $setting[1];
    $error = ttn_kisi_validate_group_settings($group);
    check(is_wp_error($error) && strpos($error->message, $setting[0] . ': ' . ($setting[1] ? 'ON' : 'OFF')) !== false, 'Diagnostic must name the exact mismatched permission.');
    unset($group[$field]);
    check(strpos(ttn_kisi_validate_group_settings($group)->message, 'missing from API response') !== false, 'Missing fields must not be treated as disabled or safe.');
    $group[$field] = 'false';
    check(strpos(ttn_kisi_validate_group_settings($group)->message, 'invalid API value') !== false, 'Invalid types must remain explicit and fail closed.');
}

function fixture($date = '2026-12-31', $time = '11:00 PM', $duration = 2) {
    return array('ttn_booking_user_id' => 1, 'ttn_booking_email' => 'billing@example.test',
        'ttn_booking_status' => 'confirmed', 'ttn_booking_payment_status' => 'Paid',
        'ttn_booking_date' => $date, 'ttn_booking_time' => $time, 'ttn_booking_duration' => $duration, 'ttn_booking_order_id' => 77);
}
function issue_without_throttling($request) {
    unset($GLOBALS['transients']['ttn_kisi_device_1']);
    return ttn_kisi_route_credentials($request);
}
$user = new WP_User();
$GLOBALS['bookings'][42] = fixture();
$start = (new DateTimeImmutable('2026-12-31 23:00:00', wp_timezone()))->getTimestamp();
check(ttn_kisi_booking_access($user, 42, $start - 901)['status'] === 'upcoming', 'No access before the 15-minute boundary.');
check(ttn_kisi_booking_access($user, 42, $start - 900)['status'] === 'ready', 'Access starts exactly 15 minutes before the booking.');
check(ttn_kisi_booking_access($user, 42, $start + 7199)['status'] === 'ready', 'Overnight booking remains valid just before its end.');
check(ttn_kisi_booking_access($user, 42, $start + 7200)['status'] === 'expired', 'Access ends exclusively, including across midnight/year.');
check(ttn_kisi_booking_access($user, 42, $start)['valid_until'] === ($start + 7200) * 1000, 'Window uses the website timezone.');
$GLOBALS['user_meta'][1]['_ttn_membership_change'] = array('action' => 'cancel', 'effective_at' => $start + 1800);
check(ttn_kisi_booking_access($user, 42, $start)['valid_until'] === ($start + 1800) * 1000, 'Door grant must not outlive a scheduled paid-period cancellation.');
check(ttn_kisi_booking_access($user, 42, $start + 1800)['status'] === 'expired', 'Cancellation deadline expires door access exclusively.');
$GLOBALS['user_meta'][1]['_ttn_membership_change']['effective_at'] = $start - 901;
check(ttn_kisi_booking_access($user, 42, $start - 1000)['status'] === 'unavailable', 'Bookings after membership ends cannot provision malformed or unauthorized windows.');
unset($GLOBALS['user_meta'][1]['_ttn_membership_change']);
foreach (array('2026-03-08', '2026-11-01') as $date) {
    $GLOBALS['bookings'][45] = fixture($date, '1:00 AM', 2);
    $dst_start = new DateTimeImmutable($date . ' 01:00:00', wp_timezone());
    $dst_state = ttn_kisi_booking_access($user, 45, $dst_start->getTimestamp());
    check($dst_state['valid_until'] === $dst_start->modify('+2 hours')->getTimestamp() * 1000, 'DST boundaries must match website calendar-hour booking slots.');
}
check(is_wp_error(ttn_kisi_booking_access(null, 42, $start)), 'Guests cannot query member bookings.');
check(is_wp_error(ttn_kisi_booking_access($user, 999, $start)), 'Missing bookings fail.');
$other = new WP_User(); $other->ID = 2;
check(is_wp_error(ttn_kisi_booking_access($other, 42, $start)), 'Account ID prevents billing-email ownership confusion.');
$GLOBALS['bookings'][43] = fixture();
$GLOBALS['bookings'][43]['ttn_booking_user_id'] = '';
$GLOBALS['bookings'][43]['ttn_booking_email'] = strtoupper($user->user_email);
check(ttn_kisi_booking_access($user, 43, $start)['status'] === 'ready', 'Legacy email ownership remains supported.');
$GLOBALS['paid_membership'] = false;
check(ttn_kisi_booking_access($user, 42, $start)['status'] === 'unavailable', 'Inactive, unpaid, and non-member access is denied.');
$GLOBALS['paid_membership'] = true;
foreach (array('ttn_booking_status' => 'cancelled', 'ttn_booking_payment_status' => 'unpaid', 'ttn_booking_parent_id' => 41, 'post_status' => 'trash') as $key => $value) {
    $GLOBALS['bookings'][44] = fixture();
    $GLOBALS['bookings'][44][$key] = $value;
    $result = ttn_kisi_booking_access($user, 44, $start);
    check(is_wp_error($result) || $result['status'] === 'unavailable', 'Unpaid, cancelled, child, and trashed records must not grant access.');
}
$GLOBALS['order_paid'] = false;
check(ttn_kisi_booking_access($user, 42, $start)['status'] === 'unavailable', 'Refunded/unpaid WooCommerce orders fail even if metadata says Paid.');
$GLOBALS['order_paid'] = true;
foreach (array('ttn_booking_date' => '2026-02-30', 'ttn_booking_time' => 'invalid', 'ttn_booking_duration' => 0) as $key => $value) {
    $GLOBALS['bookings'][44] = fixture(); $GLOBALS['bookings'][44][$key] = $value;
    check(is_wp_error(ttn_kisi_booking_access($user, 44, $start)), 'Malformed reservation times fail explicitly.');
}

$today = new DateTimeImmutable('now', wp_timezone());
$GLOBALS['bookings'][50] = fixture($today->format('Y-m-d'), ($today->format('G') % 12 ?: 12) . ':00 ' . $today->format('A'), 2);
$GLOBALS['bookings'][51] = $GLOBALS['bookings'][50];
$GLOBALS['bookings'][51]['ttn_booking_duration'] = 1;
$request = new WP_REST_Request(array('ttn_auth_user' => $user, 'booking_id' => 50, 'platform' => 'ios'));
$result = ttn_kisi_route_credentials($request);
check($result instanceof WP_REST_Response, 'Eligible member receives a credential.');
check($result->headers['Cache-Control'] === 'private, no-store, max-age=0', 'Credentials must never be cached publicly.');
check($result->data['credential']['secret'] === 'member-test-only', 'Only the member device secret is returned.');
check(count($GLOBALS['roles']) === 1, 'Exactly one bounded group role is created.');
$first = get_user_meta(1, 'ttn_kisi_booking_grant', true);
check(is_wp_error(ttn_kisi_route_credentials($request)), 'Repeated credential issuance is rate-limited.');
unset($GLOBALS['transients']['ttn_kisi_device_1']);
check(ttn_kisi_route_credentials($request) instanceof WP_REST_Response, 'Credential retry can reuse the same role after throttling.');
check(get_user_meta(1, 'ttn_kisi_booking_grant', true)['id'] === $first['id'], 'Idempotent enable must not duplicate or widen the grant.');
unset($GLOBALS['transients']['ttn_kisi_device_1']);
$second_request = new WP_REST_Request(array('ttn_auth_user' => $user, 'booking_id' => 51, 'platform' => 'android'));
check(ttn_kisi_route_credentials($second_request) instanceof WP_REST_Response, 'An overlapping current booking can replace the selected grant.');
$second = get_user_meta(1, 'ttn_kisi_booking_grant', true);
check(count($GLOBALS['roles']) === 1 && $second['booking_id'] === 51 && $second['valid_until'] < $first['valid_until'], 'Switching bookings never merges windows or broadens access.');
$future = $today->modify('+4 hours');
$GLOBALS['bookings'][52] = fixture($future->format('Y-m-d'), ($future->format('G') % 12 ?: 12) . ':00 ' . $future->format('A'), 1);
check(is_wp_error(ttn_kisi_route_credentials(new WP_REST_Request(array('ttn_auth_user' => $user, 'booking_id' => 52, 'platform' => 'ios')))), 'Disjoint future booking cannot provision access or bridge the gap.');
check(get_user_meta(1, 'ttn_kisi_booking_grant', true)['id'] === $second['id'], 'Rejected future booking leaves current bounded access unchanged.');
$GLOBALS['user_meta'][1]['_ttn_membership_change'] = array('action' => 'downgrade', 'effective_at' => time() + 60);
do_action('ttn_membership_change_scheduled', 1);
check(count($GLOBALS['roles']) === 0, 'Scheduling a membership change reconciles any previously issued longer door grant.');
unset($GLOBALS['user_meta'][1]['_ttn_membership_change']);
check(issue_without_throttling($second_request) instanceof WP_REST_Response, 'A retained paid membership can re-enable its eligible booking.');

$GLOBALS['delete_fails'] = true;
update_post_meta(51, 'ttn_booking_status', 'cancelled');
check(get_user_meta(1, 'ttn_kisi_revoke_pending', true) === 1, 'Failed cancellation revocation is persisted.');
check(get_option('ttn_kisi_access_notice') && wp_next_scheduled('ttn_kisi_retry_revocation', array(1)), 'Revocation failures are visible and retried.');
check(is_wp_error(ttn_kisi_route_status($second_request)), 'Failed revocation cannot look like available access.');
$GLOBALS['delete_fails'] = false;
do_action('ttn_kisi_retry_revocation', 1);
check(!get_user_meta(1, 'ttn_kisi_booking_grant', true) && !get_option('ttn_kisi_access_notice') && count($GLOBALS['roles']) === 0, 'Retry removes the grant and clears resolved notices.');
unset($GLOBALS['transients']['ttn_kisi_device_1']);
check(ttn_kisi_route_credentials($request) instanceof WP_REST_Response, 'Remaining valid booking can be enabled after revocation.');
do_action('ttn_kisi_retry_revocation', 1);
check(count($GLOBALS['roles']) === 1, 'A stale retry must not revoke a new, valid booking.');
$GLOBALS['paid_membership'] = false;
do_action('ttn_membership_record_saved', 1);
check(count($GLOBALS['roles']) === 0, 'Membership deactivation synchronously revokes access.');
$GLOBALS['paid_membership'] = true;
unset($GLOBALS['transients']['ttn_kisi_device_1']);
check(ttn_kisi_route_credentials($request) instanceof WP_REST_Response, 'Member can re-enable only while still eligible.');
update_post_meta(50, 'ttn_booking_duration', 3);
check(count($GLOBALS['roles']) === 0, 'Rescheduling/extension revokes old access before re-enabling.');

unset($GLOBALS['transients']['ttn_kisi_device_1']);
$GLOBALS['group_settings']['reader_restriction_enabled'] = false;
$error = issue_without_throttling($request);
check(is_wp_error($error) && $error->code === 'ttn_kisi_group_restrictions', 'Kisi itself must enforce reader proximity; a mobile-only check is not enough.');
$GLOBALS['group_settings']['reader_restriction_enabled'] = true;
$GLOBALS['teams'] = array(array('id' => 1, 'member_id' => 400));
$error = issue_without_throttling($request);
check(is_wp_error($error) && $error->code === 'ttn_kisi_teams', 'Inherited team access cannot bypass booking expiry.');
$GLOBALS['teams'] = array();
$GLOBALS['group_links'][] = array('group_id' => 200, 'lock_id' => 301);
$error = issue_without_throttling($request);
check(is_wp_error($error) && $error->code === 'ttn_kisi_group', 'Booking group cannot expose unrelated doors.');
array_pop($GLOBALS['group_links']);
$GLOBALS['roles'][900] = array('id' => 900, 'role_id' => 'organization_owner');
$error = issue_without_throttling($request);
check(is_wp_error($error) && $error->code === 'ttn_kisi_roles', 'Permanent external permissions cannot bypass the booking window.');
unset($GLOBALS['roles'][900]);
$GLOBALS['incorrect_bounds'] = true;
$error = issue_without_throttling($request);
check(is_wp_error($error) && $error->code === 'ttn_kisi_grant' && count($GLOBALS['roles']) === 0, 'Malformed Kisi grant bounds are rejected and revoked.');
$GLOBALS['incorrect_bounds'] = false;
$GLOBALS['during_login'] = function () { update_post_meta(50, 'ttn_booking_status', 'cancelled'); };
$error = issue_without_throttling($request);
check(is_wp_error($error) && $error->code === 'ttn_kisi_changed' && count($GLOBALS['roles']) === 0, 'Cancellation while creating credentials must not return successful access.');
$GLOBALS['during_login'] = null;
update_post_meta(50, 'ttn_booking_status', 'confirmed');
$GLOBALS['transport_error'] = true;
$error = issue_without_throttling($request);
check(is_wp_error($error) && strpos($error->message, 'Sensitive') === false, 'Transport errors never expose secrets or raw diagnostics.');
$GLOBALS['transport_error'] = false;
$GLOBALS['http_error'] = 429;
$error = issue_without_throttling($request);
check(is_wp_error($error) && strpos($error->message, 'HTTP 429') !== false && strpos($error->message, 'Sensitive') === false, 'Upstream errors are explicit without raw response bodies.');
$GLOBALS['http_error'] = 0;
check(issue_without_throttling($request) instanceof WP_REST_Response, 'Access can recover after a service error.');
do_action('woocommerce_order_status_changed', 77);
check(count($GLOBALS['roles']) === 0, 'Order changes synchronously revoke booking access.');
unset($GLOBALS['transients']['ttn_kisi_device_1']);
check(ttn_kisi_route_credentials($request) instanceof WP_REST_Response, 'Still-paid order can re-enable only after another eligibility check.');
do_action('before_delete_post', 50);
check(count($GLOBALS['roles']) === 0, 'Deleting a booking revokes its Kisi grant.');
$lock = ttn_kisi_access_lock(1);
check(is_string($lock) && is_wp_error(ttn_kisi_access_lock(1)), 'Concurrent provisioning cannot acquire the same member lock.');
delete_option($lock);
do_action('rest_api_init');
check($GLOBALS['routes']['/door-access']['permission_callback'] === 'ttn_jwt_authenticate_request'
    && $GLOBALS['routes']['/door-access/credentials']['permission_callback'] === 'ttn_jwt_authenticate_request', 'Both endpoints require server JWT authentication.');
define('TTN_KISI_TEST_ACCESS_EMAIL', 'member@example.test');
$GLOBALS['bookings'][80] = fixture();
$early = $start - 5 * HOUR_IN_SECONDS;
$test_state = ttn_kisi_booking_access($user, 80, $early);
check($test_state['status'] === 'ready' && $test_state['valid_from'] === $early * 1000, 'Configured account can test earlier than the normal window.');
check(strpos($test_state['message'], 'test access') !== false, 'The override is explicit in the app.');
check(ttn_kisi_booking_access($user, 80, $early + 60)['valid_from'] === $test_state['valid_from'], 'Refresh must retain a stable grant start, not recreate a moving window.');
check(ttn_kisi_booking_access($user, 80, $start + 7200)['status'] === 'expired', 'Test access never bypasses booking expiry.');
$not_test = new WP_User();
$not_test->ID = 2;
$not_test->user_email = 'another@example.test';
$GLOBALS['bookings'][81] = fixture();
$GLOBALS['bookings'][81]['ttn_booking_user_id'] = 2;
check(ttn_kisi_booking_access($not_test, 81, $early)['status'] === 'upcoming', 'Other members retain the 15-minute restriction.');
check(is_wp_error(ttn_kisi_booking_access($user, 81, $early)), 'Test account cannot access someone else’s booking.');
$GLOBALS['paid_membership'] = false;
check(ttn_kisi_booking_access($user, 80, $early)['status'] === 'unavailable', 'Test override still requires active paid membership.');
$GLOBALS['paid_membership'] = true;
$GLOBALS['bookings'][80]['ttn_booking_status'] = 'cancelled';
check(ttn_kisi_booking_access($user, 80, $early)['status'] === 'unavailable', 'Test override cannot bypass cancellation.');
echo "Kisi booking access, provisioning, revocation, concurrency and error tests passed.\n";
