<?php
// Run: php wp-content/themes/golf-simulator-theme/tests/push-notifications.php
// Exercises the WordPress side of app push notifications and cross-checks every payload and
// signature against the real Firebase validator in tee-time-nexus-mobile/functions.

if (PHP_SAPI !== 'cli') {
    exit;
}
define('ABSPATH', __DIR__ . '/');
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
define('TTN_NOTIFY_WEBHOOK_URL', 'https://us-east1-example.cloudfunctions.net/wordpressEvents');
define('TTN_NOTIFY_WEBHOOK_SECRET', str_repeat('s3cr3t-', 6));

class WP_Error {
    public $code; public $message; public $data;
    public function __construct($code = '', $message = '', $data = array()) { $this->code = $code; $this->message = $message; $this->data = $data; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}
class WP_User { public $ID; public $user_email; public $user_login = 'admin'; }
class WP_REST_Request {
    public $params;
    public function __construct($params) { $this->params = $params; }
    public function get_param($key) { return $this->params[$key] ?? null; }
}
class WP_REST_Response { public $data; public function __construct($data) { $this->data = $data; } }

class Prepared { public $sql; public $args; public function __construct($sql, $args) { $this->sql = $sql; $this->args = $args; } }
class FakeWpdb {
    public $prefix = 'wp_';
    public $outbox = array();
    public $audit = array();
    private $next_id = 1;
    public function prepare($sql, ...$args) { return new Prepared($sql, count($args) === 1 && is_array($args[0]) ? $args[0] : $args); }
    public function get_charset_collate() { return ''; }
    public function query($q) {
        if (!$q instanceof Prepared) { return 0; }
        if (strpos($q->sql, 'INSERT IGNORE') === 0) {
            [$event_id, $type, $booking_id, $body, $status, $next] = $q->args;
            foreach ($this->outbox as $row) { if ($row->event_id === $event_id) { return 0; } }
            $id = $this->next_id++;
            $this->outbox[$id] = (object) (compact('id', 'event_id', 'body', 'status') + array('event_type' => $type, 'booking_id' => $booking_id, 'attempts' => 0, 'next_attempt_at' => $next, 'last_error' => '', 'updated_at' => ''));
            return 1;
        }
        if (strpos($q->sql, 'UPDATE') === 0) {
            [$lease, , $id, $now] = $q->args;
            $row = $this->outbox[$id] ?? null;
            if (!$row || !in_array($row->status, array('pending', 'sending'), true) || $row->next_attempt_at > $now) { return 0; }
            $row->status = 'sending';
            $row->next_attempt_at = $lease;
            return 1;
        }
        return 0;
    }
    public function get_results($q) {
        if (!$q instanceof Prepared) { return array_reverse($this->audit); }
        if (strpos($q->sql, 'event_id IN') !== false) {
            return array_values(array_map(function ($r) { return clone $r; }, array_filter($this->outbox, function ($r) use ($q) { return in_array($r->event_id, $q->args, true); })));
        }
        $now = $q->args[0];
        return array_values(array_map(function ($r) { return clone $r; }, array_filter($this->outbox, function ($r) use ($now) { return in_array($r->status, array('pending', 'sending'), true) && $r->next_attempt_at <= $now; })));
    }
    public function get_var($sql) { return count(array_filter($this->outbox, function ($r) { return $r->status === 'failed'; })); }
    public function update($table, $data, $where) { foreach ($data as $k => $v) { $this->outbox[$where['id']]->$k = $v; } }
    public function insert($table, $data) { $this->audit[] = (object) $data; }
}
$wpdb = new FakeWpdb();

function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function is_wp_error($v) { return $v instanceof WP_Error; }
function add_action($hook, $cb, $priority = 10, $args = 1) { $GLOBALS['hooks'][$hook][] = array($cb, $args); }
function add_filter($hook, $cb, $priority = 10, $args = 1) { add_action($hook, $cb, $priority, $args); }
function do_action($hook, ...$args) { foreach ($GLOBALS['hooks'][$hook] ?? array() as [$cb, $n]) { $cb(...array_slice($args, 0, $n)); } }
function get_option($k) { return $GLOBALS['options'][$k] ?? false; }
function update_option($k, $v, $autoload = null) { $GLOBALS['options'][$k] = $v; }
function dbDelta($sql) {}
function wp_json_encode($v, $flags = 0) { return json_encode($v, $flags); }
function current_time($type, $gmt = false) { return gmdate('Y-m-d H:i:s'); }
function wp_next_scheduled($hook) { return false; }
function wp_schedule_event($t, $s, $hook) {}
function error_log_capture($m) { $GLOBALS['errors'][] = $m; }
function wp_timezone() { return new DateTimeZone('America/Toronto'); }
function wp_timezone_string() { return 'America/Toronto'; }
function wp_strip_all_tags($v) { return strip_tags($v); }
function ttn_get_bay_display_name($bay) { return 'Bay ' . strtoupper($bay); }
function get_post_type($id) { return isset($GLOBALS['posts'][$id]) ? 'ttn_booking' : false; }
function get_post_meta($id, $key, $single = true) { return $GLOBALS['posts'][$id][$key] ?? ''; }
function update_post_meta($id, $key, $value) {
    $exists = array_key_exists($key, $GLOBALS['posts'][$id] ?? array());
    if ($exists && $GLOBALS['posts'][$id][$key] === $value) { return false; }
    $exists ? do_action('update_post_meta', 1, $id, $key, $value) : do_action('add_post_meta', $id, $key, $value);
    $GLOBALS['posts'][$id][$key] = $value;
    return true;
}
function get_user_by($field, $value) {
    foreach ($GLOBALS['users'] as $id => $email) {
        if (($field === 'id' && (int) $value === $id) || ($field === 'email' && $value === $email)) {
            $u = new WP_User(); $u->ID = $id; $u->user_email = $email; return $u;
        }
    }
    return false;
}
function get_userdata($id) { return get_user_by('id', $id); }
function is_email($v) { return (bool) filter_var($v, FILTER_VALIDATE_EMAIL); }
function sanitize_text_field($v) { return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags($v))); }
function sanitize_textarea_field($v) { return trim(strip_tags($v)); }
function rest_ensure_response($d) { return new WP_REST_Response($d); }
function wp_get_current_user() { return get_user_by('id', 7); }
function wp_remote_post($url, $args) {
    $GLOBALS['requests'][] = array('url' => $url) + $args;
    $code = array_shift($GLOBALS['responses']) ?? 200;
    $body = $GLOBALS['response_body'] ?? array('ok' => $code === 200, 'result' => array());
    return array('code' => $code, 'body' => json_encode($code === 200 ? $body : array('ok' => false, 'error' => 'processing_failed')));
}
function wp_remote_retrieve_response_code($r) { return $r['code']; }
function wp_remote_retrieve_body($r) { return $r['body']; }

$GLOBALS['options']['ttn_push_db_version'] = '1';
$GLOBALS['users'] = array(7 => 'golfer@example.test', 8 => 'other@example.test');
require dirname(__DIR__, 3) . '/plugins/tee-time-nexus-bookings/inc/class-ttn-push-notifications.php';
do_action('init');

function reset_requests() { $GLOBALS['requests'] = array(); $GLOBALS['responses'] = array(); }
function shutdown() { do_action('shutdown'); }
function sent_types() { return array_map(function ($r) { return json_decode($r['body'], true)['type']; }, $GLOBALS['requests']); }
function last_body() { return json_decode(end($GLOBALS['requests'])['body'], true); }
function paid_booking($id, $date, $time, $extra = array()) {
    $GLOBALS['posts'][$id] = array();
    foreach (array_merge(array(
        'ttn_booking_email' => 'golfer@example.test', 'ttn_booking_bay' => 'bay-1', 'ttn_booking_date' => $date,
        'ttn_booking_time' => $time, 'ttn_booking_duration' => 2, 'ttn_booking_payment_status' => 'Paid',
        'ttn_booking_user_id' => 7, 'ttn_booking_status' => 'confirmed',
    ), $extra) as $key => $value) {
        update_post_meta($id, $key, $value);
    }
}

// Signature matches the Firebase format: v1=<hex HMAC-SHA256(secret, "<timestamp>.<body>")>.
check(ttn_push_signature('k', '1700000000', '{"a":1}') === 'v1=' . hash_hmac('sha256', '1700000000.{"a":1}', 'k'), 'signature format');

// 1. A paid checkout sends exactly one confirmation, with facility-local times and an offset.
reset_requests();
paid_booking(100, '2026-10-08', '6:00 PM');
shutdown();
check(sent_types() === array('booking.confirmed'), 'paid booking sends a confirmation');
$body = last_body();
check($body['data']['booking'] === array('booking_id' => 100, 'customer_id' => 7, 'start_at' => '2026-10-08T18:00:00-04:00', 'end_at' => '2026-10-08T20:00:00-04:00', 'timezone' => 'America/Toronto', 'bay' => 'Bay BAY-1'), 'booking payload: ' . json_encode($body['data']['booking']));
check(preg_match('/^booking\.100\.confirmed\.[0-9a-f]{16}$/', $body['event_id']) === 1, 'deterministic confirmation id');
$request = $GLOBALS['requests'][0];
check($request['url'] === TTN_NOTIFY_WEBHOOK_URL && $request['redirection'] === 0, 'posts to the configured URL without redirects');
check($request['headers']['X-TTN-Signature'] === ttn_push_signature(TTN_NOTIFY_WEBHOOK_SECRET, $request['headers']['X-TTN-Timestamp'], $request['body']), 'request is signed');
$GLOBALS['captured'][] = $request;

// 2. Saving the same booking again sends nothing.
reset_requests();
update_post_meta(100, 'ttn_booking_status', 'confirmed');
update_post_meta(100, 'ttn_booking_bay', 'bay-1');
shutdown();
check(sent_types() === array(), 'unchanged booking is silent');

// 3. Unpaid requests and hourly child posts never notify.
reset_requests();
paid_booking(200, '2026-10-08', '7:00 PM', array('ttn_booking_payment_status' => '', 'ttn_booking_status' => ''));
paid_booking(101, '2026-10-08', '7:00 PM', array('ttn_booking_parent_id' => 100));
shutdown();
check(sent_types() === array(), 'unpaid and child bookings are silent');

// 4. Rescheduling (customer edit, admin edit or extension) sends one rescheduled event.
reset_requests();
update_post_meta(100, 'ttn_booking_time', '8:00 PM');
update_post_meta(100, 'ttn_booking_status', 'updated');
shutdown();
check(sent_types() === array('booking.rescheduled'), 'reschedule detected');
check(last_body()['data']['booking']['start_at'] === '2026-10-08T20:00:00-04:00', 'rescheduled start');
$GLOBALS['captured'][] = end($GLOBALS['requests']);

// 4b. An extension rewrites the payment status to "Paid ($x)"; the booking stays active.
reset_requests();
update_post_meta(100, 'ttn_booking_payment_status', 'Paid ($150.00)');
update_post_meta(100, 'ttn_booking_duration', 3);
shutdown();
check(sent_types() === array('booking.rescheduled'), 'extension with "Paid ($x)" is a reschedule, not a removal: ' . json_encode(sent_types()));
check(last_body()['data']['booking']['end_at'] === '2026-10-08T23:00:00-04:00', 'extended end time');
reset_requests();
paid_booking(201, '2026-10-08', '9:00 PM', array('ttn_booking_payment_status' => 'Refunded'));
paid_booking(202, '2026-10-08', '9:00 PM', array('ttn_booking_payment_status' => 'Paidout'));
shutdown();
check(sent_types() === array(), 'refunded and look-alike statuses stay inactive');

// 5. A booking that existed before this feature still reports a reschedule from its prior values.
reset_requests();
$GLOBALS['posts'][300] = array('ttn_booking_email' => 'golfer@example.test', 'ttn_booking_bay' => 'bay-2', 'ttn_booking_date' => '2026-11-01', 'ttn_booking_time' => '10:00 AM', 'ttn_booking_duration' => 1, 'ttn_booking_payment_status' => 'Paid', 'ttn_booking_status' => 'confirmed');
update_post_meta(300, 'ttn_booking_date', '2026-11-02');
shutdown();
check(sent_types() === array('booking.rescheduled'), 'legacy booking reschedule');
check(last_body()['data']['booking']['customer_id'] === 7 && last_body()['data']['booking']['start_at'] === '2026-11-02T10:00:00-05:00', 'legacy booking falls back to the email owner and handles DST');

// 6. Cancelling sends a cancellation; trashing an active booking silently removes its reminder.
reset_requests();
update_post_meta(100, 'ttn_booking_status', 'cancelled');
shutdown();
check(sent_types() === array('booking.cancelled'), 'cancellation detected');
$GLOBALS['captured'][] = end($GLOBALS['requests']);
reset_requests();
do_action('wp_trash_post', 300);
shutdown();
check(sent_types() === array('booking.removed'), 'trash sends a silent removal');
$GLOBALS['captured'][] = end($GLOBALS['requests']);
reset_requests();
do_action('wp_trash_post', 300);
shutdown();
check(sent_types() === array(), 'second trash is silent');

// 7. Failures are retried with the identical body and a fresh signature; 400s are not retried.
reset_requests();
$GLOBALS['responses'] = array(500);
paid_booking(400, '2026-12-01', '1:00 PM');
shutdown();
$row = $wpdb->outbox[max(array_keys($wpdb->outbox))];
check($row->status === 'pending' && $row->attempts === 1 && $row->next_attempt_at > time(), 'failed send is scheduled for retry');
$first = $GLOBALS['requests'][0];
$row->next_attempt_at = time() - 1;
do_action('ttn_push_process_outbox');
check($GLOBALS['requests'][1]['body'] === $first['body'], 'retry resends the identical body');
check($row->status === 'sent' && $row->attempts === 2, 'retry succeeded');
ttn_push_process_outbox();
check(count($GLOBALS['requests']) === 2, 'sent events are not resent');

reset_requests();
$GLOBALS['responses'] = array(400);
paid_booking(401, '2026-12-01', '3:00 PM');
shutdown();
check($wpdb->outbox[max(array_keys($wpdb->outbox))]->status === 'failed', '400 is a permanent failure');

reset_requests();
$GLOBALS['responses'] = array_fill(0, TTN_PUSH_MAX_ATTEMPTS, 503);
paid_booking(402, '2026-12-01', '5:00 PM');
shutdown();
$row = $wpdb->outbox[max(array_keys($wpdb->outbox))];
for ($i = 1; $i < TTN_PUSH_MAX_ATTEMPTS; $i++) { $row->next_attempt_at = 0; ttn_push_process_outbox(); }
check($row->status === 'failed' && $row->attempts === TTN_PUSH_MAX_ATTEMPTS, 'retries stop after the maximum');

// 8. Re-queuing an identical change is ignored by the outbox.
check(ttn_push_enqueue($row->event_id, 'booking.confirmed', 402, $row->body) === false, 'duplicate event ids are ignored');

// 9. App routes: device registration and preferences.
reset_requests();
$response = ttn_push_rest_register_device(new WP_REST_Request(array('ttn_auth_user' => get_user_by('id', 7), 'token' => str_repeat('a', 40) . ':APA91b', 'platform' => 'ios')));
check($response->data === array('registered' => true) && last_body()['data'] === array('user_id' => 7, 'token' => str_repeat('a', 40) . ':APA91b', 'platform' => 'ios'), 'device registration');
$GLOBALS['captured'][] = end($GLOBALS['requests']);
check(is_wp_error(ttn_push_rest_register_device(new WP_REST_Request(array('token' => 'short', 'platform' => 'ios')))), 'invalid token rejected');
check(is_wp_error(ttn_push_rest_register_device(new WP_REST_Request(array('token' => str_repeat('a', 40), 'platform' => 'web')))), 'invalid platform rejected');
$GLOBALS['response_body'] = array('ok' => true, 'result' => array('registered' => false, 'reason' => 'invalid_token'));
$response = ttn_push_rest_register_device(new WP_REST_Request(array('ttn_auth_user' => get_user_by('id', 7), 'token' => str_repeat('c', 40), 'platform' => 'ios')));
check($response->data === array('registered' => false, 'reason' => 'invalid_token'), 'stale token tells the app to fetch a fresh one');
$GLOBALS['response_body'] = array('ok' => true, 'result' => array('preferences' => array('bookingUpdates' => true, 'bookingReminders' => false, 'marketing' => true)));
$response = ttn_push_rest_set_preferences(new WP_REST_Request(array('ttn_auth_user' => get_user_by('id', 7), 'booking_reminders' => false, 'marketing' => true)));
check($response->data['preferences'] === array('booking_updates' => true, 'booking_reminders' => false, 'marketing' => true), 'preferences mapped to snake_case');
check(last_body()['data']['preferences'] === array('booking_reminders' => false, 'marketing' => true), 'only provided preferences are sent');
$GLOBALS['captured'][] = end($GLOBALS['requests']);
check(is_wp_error(ttn_push_rest_set_preferences(new WP_REST_Request(array('marketing' => 'yes')))), 'non-boolean preference rejected');
check(is_wp_error(ttn_push_rest_set_preferences(new WP_REST_Request(array()))), 'empty preferences rejected');
unset($GLOBALS['response_body']);
$GLOBALS['responses'] = array(502);
$failed = ttn_push_rest_register_device(new WP_REST_Request(array('ttn_auth_user' => get_user_by('id', 7), 'token' => str_repeat('b', 40), 'platform' => 'android')));
check(is_wp_error($failed) && $failed->get_error_data()['status'] === 502, 'Firebase outage surfaces as 502');

// 10. Admin message validation.
$valid = ttn_push_validate_admin_message(array('target' => 'user', 'customer' => 'golfer@example.test', 'title' => 'Hi', 'body' => "Line one\nline two", 'route' => '/reservations'));
check(!is_wp_error($valid) && $valid['user_id'] === 7 && $valid['body'] === 'Line one line two', 'customer message accepted');
check(is_wp_error(ttn_push_validate_admin_message(array('target' => 'user', 'customer' => 'nobody@example.test', 'title' => 'Hi', 'body' => 'x', 'route' => '/'))), 'unknown customer rejected');
check(is_wp_error(ttn_push_validate_admin_message(array('target' => 'user', 'customer' => '7', 'title' => str_repeat('x', 66), 'body' => 'x', 'route' => '/'))), 'long title rejected');
check(is_wp_error(ttn_push_validate_admin_message(array('target' => 'user', 'customer' => '7', 'title' => 'Hi', 'body' => str_repeat('😀', 121), 'route' => '/'))), 'body length uses UTF-16 units');
check(is_wp_error(ttn_push_validate_admin_message(array('target' => 'user', 'customer' => '7', 'title' => 'Hi', 'body' => 'x', 'route' => 'https://evil.example'))), 'unknown route rejected');
check(is_wp_error(ttn_push_validate_admin_message(array('target' => 'broadcast', 'title' => 'Hi', 'body' => 'x', 'route' => '/', 'confirmation' => 'yes'))), 'broadcast needs typed confirmation');
check(!is_wp_error(ttn_push_validate_admin_message(array('target' => 'broadcast', 'title' => 'Hi', 'body' => 'x', 'route' => '/', 'confirmation' => 'SEND TO ALL'))), 'confirmed broadcast accepted');
check(ttn_push_summarize_delivery(array('delivery' => array('skipped' => 'not_allowlisted'))) === 'Skipped: this customer is not on the Firebase test allowlist.', 'allowlist skip explained');

// 11. Every captured request passes the real Firebase signature check and validator.
$functions = getenv('TTN_FUNCTIONS_DIR') ?: '/Users/farnexus/tee-time-nexus-mobile/functions';
if (is_dir($functions . '/src') && trim((string) shell_exec('command -v node'))) {
    $fixture = tempnam(sys_get_temp_dir(), 'ttn-push');
    file_put_contents($fixture, json_encode(array_map(function ($r) { return array('body' => $r['body'], 'timestamp' => $r['headers']['X-TTN-Timestamp'], 'signature' => $r['headers']['X-TTN-Signature']); }, $GLOBALS['captured'])));
    $script = sprintf(
        'import {readFileSync} from "node:fs"; import {verifySignature} from %s; import {parseEvent} from %s;'
        . 'const secret=%s; let bad=0; for (const r of JSON.parse(readFileSync(%s,"utf8"))) {'
        . 'const s=verifySignature({secret,timestampHeader:r.timestamp,signatureHeader:r.signature,rawBody:r.body,nowSeconds:Math.floor(Date.now()/1000)}); const p=parseEvent(JSON.parse(r.body));'
        . 'if(!s.ok||!p.ok){bad++;console.error(r.body,JSON.stringify(s),JSON.stringify(p));}} process.exit(bad?1:0);',
        json_encode('file://' . $functions . '/src/signature.ts'), json_encode('file://' . $functions . '/src/validate.ts'),
        json_encode(TTN_NOTIFY_WEBHOOK_SECRET), json_encode($fixture)
    );
    exec('node --input-type=module -e ' . escapeshellarg($script) . ' 2>&1', $output, $status);
    unlink($fixture);
    check($status === 0, "Firebase rejected a WordPress payload:\n" . implode("\n", $output));
    echo 'Cross-checked ' . count($GLOBALS['captured']) . " payloads against the Firebase validator.\n";
} else {
    echo "Skipped Firebase cross-check (functions source or node not found).\n";
}

echo "Push notification tests passed.\n";
