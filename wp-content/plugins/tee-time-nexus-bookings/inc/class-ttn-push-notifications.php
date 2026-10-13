<?php
/**
 * Personalized app push notifications.
 *
 * WordPress never talks to Firebase Cloud Messaging directly and never holds Firebase credentials.
 * Every event is signed with TTN_NOTIFY_WEBHOOK_SECRET and posted to the Firebase function at
 * TTN_NOTIFY_WEBHOOK_URL, which owns device tokens, preferences, delivery and the 15-minute reminders.
 * Both constants live in wp-config.php; without them this module does nothing.
 *
 * Kisi door access is deliberately independent: nothing here reads or sends access credentials.
 */

if (!defined('ABSPATH')) {
    exit;
}

const TTN_PUSH_DB_VERSION = '1';
const TTN_PUSH_MAX_ATTEMPTS = 6;
const TTN_PUSH_TITLE_MAX = 65;
const TTN_PUSH_BODY_MAX = 240;
const TTN_PUSH_BROADCAST_CONFIRMATION = 'SEND TO ALL';
const TTN_PUSH_ROUTES = array('/', '/book', '/membership', '/reservations', '/account', '/notifications');
const TTN_PUSH_WATCHED_META = array(
    'ttn_booking_date', 'ttn_booking_time', 'ttn_booking_duration', 'ttn_booking_bay', 'ttn_booking_status',
    'ttn_booking_payment_status', 'ttn_booking_user_id', 'ttn_booking_email', 'ttn_booking_parent_id',
);

function ttn_push_is_configured() {
    return defined('TTN_NOTIFY_WEBHOOK_URL') && defined('TTN_NOTIFY_WEBHOOK_SECRET')
        && strpos((string) TTN_NOTIFY_WEBHOOK_URL, 'https://') === 0
        && strlen((string) TTN_NOTIFY_WEBHOOK_SECRET) >= 32;
}

// ===== Wire protocol (must match functions/src/signature.ts and validate.ts) =====

function ttn_push_signature($secret, $timestamp, $body) {
    return 'v1=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
}

function ttn_push_iso_now() {
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.uP');
}

function ttn_push_random_event_id($prefix) {
    return $prefix . '.' . bin2hex(random_bytes(12));
}

function ttn_push_build_body($event_id, $type, array $data, $occurred_at = null) {
    return wp_json_encode(array(
        'event_id' => $event_id,
        'type' => $type,
        'occurred_at' => $occurred_at ?: ttn_push_iso_now(),
        'data' => $data,
    ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * Sends one already-encoded body. Retries must resend the identical body (with a fresh timestamp)
 * so Firebase recognises the event and never delivers it twice.
 *
 * @return array{code:int, data:array, error:string}
 */
function ttn_push_post($body) {
    if (!ttn_push_is_configured()) {
        return array('code' => 0, 'data' => array(), 'error' => 'not_configured');
    }
    $timestamp = (string) time();
    $response = wp_remote_post(TTN_NOTIFY_WEBHOOK_URL, array(
        'timeout' => 8,
        'redirection' => 0,
        'headers' => array(
            'Content-Type' => 'application/json',
            'X-TTN-Timestamp' => $timestamp,
            'X-TTN-Signature' => ttn_push_signature(TTN_NOTIFY_WEBHOOK_SECRET, $timestamp, $body),
        ),
        'body' => $body,
        'data_format' => 'body',
    ));
    if (is_wp_error($response)) {
        return array('code' => 0, 'data' => array(), 'error' => $response->get_error_code());
    }
    $code = (int) wp_remote_retrieve_response_code($response);
    $data = json_decode((string) wp_remote_retrieve_body($response), true);
    $data = is_array($data) ? $data : array();
    return array('code' => $code, 'data' => $data, 'error' => $code === 200 ? '' : (string) ($data['error'] ?? 'http_' . $code));
}

function ttn_push_is_retryable($code) {
    return $code === 0 || $code === 401 || $code === 409 || $code === 429 || $code >= 500;
}

/** Sends a synchronous event (devices, preferences, admin messages) and returns Firebase's result. */
function ttn_push_send_now($prefix, $type, array $data) {
    $result = ttn_push_post(ttn_push_build_body(ttn_push_random_event_id($prefix), $type, $data));
    if ($result['code'] !== 200) {
        return new WP_Error('ttn_push_unavailable', 'Notifications are temporarily unavailable.', array('status' => $result['code'] === 0 && $result['error'] === 'not_configured' ? 503 : 502, 'reason' => $result['error']));
    }
    return is_array($result['data']['result'] ?? null) ? $result['data']['result'] : array();
}

// ===== Storage =====

function ttn_push_outbox_table() {
    global $wpdb;
    return $wpdb->prefix . 'ttn_push_outbox';
}

function ttn_push_audit_table() {
    global $wpdb;
    return $wpdb->prefix . 'ttn_push_audit';
}

function ttn_push_install_tables() {
    if (get_option('ttn_push_db_version') === TTN_PUSH_DB_VERSION) {
        return;
    }
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    dbDelta('CREATE TABLE ' . ttn_push_outbox_table() . " (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event_id varchar(128) NOT NULL,
        event_type varchar(40) NOT NULL,
        booking_id bigint(20) unsigned NOT NULL DEFAULT 0,
        body longtext NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        attempts smallint(5) unsigned NOT NULL DEFAULT 0,
        next_attempt_at bigint(20) unsigned NOT NULL DEFAULT 0,
        last_error varchar(190) NOT NULL DEFAULT '',
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY event_id (event_id),
        KEY status_next (status,next_attempt_at)
    ) $charset;");
    dbDelta('CREATE TABLE ' . ttn_push_audit_table() . " (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        created_at datetime NOT NULL,
        admin_user_id bigint(20) unsigned NOT NULL,
        target varchar(40) NOT NULL,
        title varchar(100) NOT NULL,
        body text NOT NULL,
        route varchar(40) NOT NULL,
        outcome varchar(190) NOT NULL,
        PRIMARY KEY  (id),
        KEY created_at (created_at)
    ) $charset;");
    update_option('ttn_push_db_version', TTN_PUSH_DB_VERSION, false);
}
add_action('init', 'ttn_push_install_tables');

/** Returns true if the event was newly queued; identical event ids are ignored. */
function ttn_push_enqueue($event_id, $type, $booking_id, $body) {
    global $wpdb;
    $now = current_time('mysql', true);
    $inserted = $wpdb->query($wpdb->prepare(
        'INSERT IGNORE INTO ' . ttn_push_outbox_table() . ' (event_id, event_type, booking_id, body, status, attempts, next_attempt_at, created_at, updated_at) VALUES (%s, %s, %d, %s, %s, 0, %d, %s, %s)',
        $event_id, $type, $booking_id, $body, 'pending', time(), $now, $now
    ));
    if ($inserted === false) {
        error_log(sprintf('[TTN push] Could not queue %s for booking %d: %s', $type, $booking_id, $wpdb->last_error));
        // Let the next request retry creating the queue table.
        delete_option('ttn_push_db_version');
    }
    return (bool) $inserted;
}

function ttn_push_backoff_seconds($attempts) {
    $schedule = array(60, 120, 300, 900, 1800, 3600);
    return $schedule[min(max(0, $attempts - 1), count($schedule) - 1)];
}

/** Atomically claims a row so the cron runner and an immediate send never post it at the same time. */
function ttn_push_claim_row($id) {
    global $wpdb;
    $now = time();
    return (bool) $wpdb->query($wpdb->prepare(
        'UPDATE ' . ttn_push_outbox_table() . " SET status = 'sending', next_attempt_at = %d, updated_at = %s WHERE id = %d AND ((status = 'pending' AND next_attempt_at <= %d) OR (status = 'sending' AND next_attempt_at <= %d))",
        $now + 120, current_time('mysql', true), $id, $now, $now
    ));
}

function ttn_push_deliver_row($row) {
    global $wpdb;
    if (!ttn_push_claim_row((int) $row->id)) {
        return;
    }
    $result = ttn_push_post($row->body);
    $attempts = (int) $row->attempts + 1;
    if ($result['code'] === 200) {
        $status = 'sent';
        $next = 0;
    } elseif (ttn_push_is_retryable($result['code']) && $attempts < TTN_PUSH_MAX_ATTEMPTS) {
        $status = 'pending';
        $next = time() + ttn_push_backoff_seconds($attempts);
    } else {
        $status = 'failed';
        $next = 0;
        error_log(sprintf('[TTN push] Event %s (%s) failed permanently: %s', $row->event_id, $row->event_type, $result['error']));
    }
    $wpdb->update(ttn_push_outbox_table(), array(
        'status' => $status,
        'attempts' => $attempts,
        'next_attempt_at' => $next,
        'last_error' => substr($result['error'], 0, 190),
        'updated_at' => current_time('mysql', true),
    ), array('id' => (int) $row->id));
}

function ttn_push_process_outbox($event_ids = null, $limit = 20) {
    if (!ttn_push_is_configured()) {
        return;
    }
    global $wpdb;
    $table = ttn_push_outbox_table();
    if (is_array($event_ids)) {
        if (!$event_ids) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($event_ids), '%s'));
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE event_id IN ($placeholders)", $event_ids));
    } else {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE status IN ('pending', 'sending') AND next_attempt_at <= %d ORDER BY id ASC LIMIT %d",
            time(), $limit
        ));
    }
    foreach ((array) $rows as $row) {
        ttn_push_deliver_row($row);
    }
}

add_filter('cron_schedules', function ($schedules) {
    $schedules['ttn_push_every_minute'] = array('interval' => 60, 'display' => 'Tee Time Nexus push outbox');
    return $schedules;
});
add_action('init', function () {
    if (ttn_push_is_configured() && !wp_next_scheduled('ttn_push_process_outbox')) {
        wp_schedule_event(time() + 60, 'ttn_push_every_minute', 'ttn_push_process_outbox');
    }
});
add_action('ttn_push_process_outbox', function () {
    ttn_push_process_outbox();
    global $wpdb;
    $wpdb->query($wpdb->prepare(
        'DELETE FROM ' . ttn_push_outbox_table() . " WHERE status IN ('sent', 'failed') AND updated_at < %s",
        gmdate('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS)
    ));
});

// ===== Booking lifecycle =====

/** Facility timezone as an IANA name; offset-only WordPress settings map to Etc/GMT zones. */
function ttn_push_facility_timezone() {
    $name = wp_timezone_string();
    if (in_array($name, timezone_identifiers_list(), true)) {
        return $name;
    }
    $offset = wp_timezone()->getOffset(new DateTimeImmutable('now'));
    if ($offset === 0) {
        return 'UTC';
    }
    if ($offset % 3600 === 0) {
        $hours = (int) ($offset / 3600);
        return 'Etc/GMT' . ($hours > 0 ? '-' : '+') . abs($hours);
    }
    return 'UTC';
}

function ttn_push_booking_customer_id($booking_id) {
    $user_id = (int) get_post_meta($booking_id, 'ttn_booking_user_id', true);
    if ($user_id > 0) {
        return $user_id;
    }
    $email = (string) get_post_meta($booking_id, 'ttn_booking_email', true);
    $user = $email ? get_user_by('email', $email) : false;
    return $user ? (int) $user->ID : 0;
}

/**
 * Describes what a customer should know about a booking right now. Only paid, confirmed parent
 * bookings are "active"; unpaid requests and hourly child posts never produce notifications.
 */
function ttn_push_booking_snapshot($booking_id) {
    $status = strtolower((string) get_post_meta($booking_id, 'ttn_booking_status', true));
    // Extensions store e.g. "Paid ($80.00)"; refunds/cancellations use other words.
    $paid = preg_match('/^paid\b/i', trim((string) get_post_meta($booking_id, 'ttn_booking_payment_status', true))) === 1;
    $is_child = (bool) get_post_meta($booking_id, 'ttn_booking_parent_id', true);
    $snapshot = array('active' => false, 'cancelled' => $status === 'cancelled', 'booking' => null);
    if ($is_child || !$paid || !in_array($status, array('confirmed', 'updated'), true)) {
        return $snapshot;
    }
    $date = (string) get_post_meta($booking_id, 'ttn_booking_date', true);
    $time = (string) get_post_meta($booking_id, 'ttn_booking_time', true);
    $duration = max(1, (int) get_post_meta($booking_id, 'ttn_booking_duration', true));
    $bay = (string) get_post_meta($booking_id, 'ttn_booking_bay', true);
    $start = DateTimeImmutable::createFromFormat('!Y-m-d g:i A', $date . ' ' . $time, wp_timezone());
    if (!$start || $start->format('Y-m-d') !== $date) {
        return $snapshot;
    }
    $bay_name = function_exists('ttn_get_bay_display_name') ? ttn_get_bay_display_name($bay) : $bay;
    $bay_name = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', wp_strip_all_tags((string) $bay_name)));
    $snapshot['active'] = true;
    $snapshot['booking'] = array(
        'booking_id' => (int) $booking_id,
        'customer_id' => ttn_push_booking_customer_id($booking_id),
        'start_at' => $start->format(DATE_ATOM),
        'end_at' => $start->setTimestamp($start->getTimestamp() + $duration * HOUR_IN_SECONDS)->format(DATE_ATOM),
        'timezone' => ttn_push_facility_timezone(),
        'bay' => function_exists('mb_substr') ? mb_substr($bay_name ?: 'Bay', 0, 80) : substr($bay_name ?: 'Bay', 0, 80),
    );
    return $snapshot;
}

/** Decides which event (if any) moves the customer's app from $before to $after. */
function ttn_push_booking_transition($before, $after) {
    if (!$before['active'] && $after['active']) {
        return 'booking.confirmed';
    }
    if ($before['active'] && !$after['active']) {
        return $after['cancelled'] ? 'booking.cancelled' : 'booking.removed';
    }
    if ($before['active'] && $after['active']) {
        $keys = array('start_at', 'end_at', 'bay', 'customer_id');
        foreach ($keys as $key) {
            if ($before['booking'][$key] !== $after['booking'][$key]) {
                return 'booking.rescheduled';
            }
        }
    }
    return '';
}

/** Event ids are derived from the booking content so a replayed change is deduplicated everywhere. */
function ttn_push_booking_event_id($type, array $booking) {
    $fingerprint = substr(hash('sha256', wp_json_encode(array($booking['customer_id'], $booking['start_at'], $booking['end_at'], $booking['bay']))), 0, 16);
    return sprintf('booking.%d.%s.%s', $booking['booking_id'], substr($type, 8), $fingerprint);
}

function ttn_push_queue_booking_event($type, array $booking) {
    if ($booking['customer_id'] <= 0) {
        return '';
    }
    $event_id = ttn_push_booking_event_id($type, $booking);
    ttn_push_enqueue($event_id, $type, $booking['booking_id'], ttn_push_build_body($event_id, $type, array('booking' => $booking)));
    return $event_id;
}

function ttn_push_stored_state($booking_id) {
    $state = get_post_meta($booking_id, '_ttn_push_state', true);
    return is_array($state) && isset($state['active']) ? $state : null;
}

/** Records the pre-change state once per request, before the first watched meta write lands. */
function ttn_push_capture_booking($booking_id, $key) {
    if (!in_array($key, TTN_PUSH_WATCHED_META, true) || get_post_type($booking_id) !== 'ttn_booking') {
        return;
    }
    $booking_id = (int) $booking_id;
    if (!isset($GLOBALS['ttn_push_touched'][$booking_id])) {
        $GLOBALS['ttn_push_touched'][$booking_id] = ttn_push_stored_state($booking_id) ?: ttn_push_booking_snapshot($booking_id);
    }
}
add_action('update_post_meta', function ($meta_id, $post_id, $key) { ttn_push_capture_booking($post_id, $key); }, 10, 3);
add_action('add_post_meta', function ($post_id, $key) { ttn_push_capture_booking($post_id, $key); }, 10, 2);
add_action('delete_post_meta', function ($meta_ids, $post_id, $key) { ttn_push_capture_booking($post_id, $key); }, 10, 3);

/** Compares every booking touched in this request with its captured state and sends what changed. */
function ttn_push_flush_booking_changes() {
    $touched = $GLOBALS['ttn_push_touched'] ?? array();
    $GLOBALS['ttn_push_touched'] = array();
    $queued = array();
    foreach ($touched as $booking_id => $before) {
        if (get_post_type($booking_id) !== 'ttn_booking') {
            continue;
        }
        $after = ttn_push_booking_snapshot($booking_id);
        $type = ttn_push_booking_transition($before, $after);
        if ($type) {
            $booking = $after['booking'] ?: $before['booking'];
            $event_id = ttn_push_queue_booking_event($type, $booking);
            if ($event_id) {
                $queued[] = $event_id;
            }
        }
        if ($after['active'] || $before['active']) {
            update_post_meta($booking_id, '_ttn_push_state', $after);
        }
    }
    ttn_push_process_outbox($queued);
}
add_action('shutdown', 'ttn_push_flush_booking_changes', 5);

/** Deleted or trashed bookings silently cancel their reminder. */
function ttn_push_booking_removed($post_id) {
    if (get_post_type($post_id) !== 'ttn_booking') {
        return;
    }
    $state = ttn_push_stored_state($post_id) ?: ttn_push_booking_snapshot($post_id);
    unset($GLOBALS['ttn_push_touched'][(int) $post_id]);
    if ($state['active'] && $state['booking']) {
        $event_id = ttn_push_queue_booking_event('booking.removed', $state['booking']);
        if ($event_id) {
            $GLOBALS['ttn_push_removed'][] = $event_id;
        }
        update_post_meta($post_id, '_ttn_push_state', array('active' => false, 'cancelled' => false, 'booking' => null));
    }
}
add_action('before_delete_post', 'ttn_push_booking_removed');
add_action('wp_trash_post', 'ttn_push_booking_removed');
add_action('shutdown', function () {
    ttn_push_process_outbox($GLOBALS['ttn_push_removed'] ?? array());
    $GLOBALS['ttn_push_removed'] = array();
}, 6);

// ===== App REST routes (authenticated with the app's JWT) =====

function ttn_push_rest_user(WP_REST_Request $request) {
    $user = $request->get_param('ttn_auth_user');
    return $user instanceof WP_User ? $user : wp_get_current_user();
}

function ttn_push_valid_token($token) {
    return is_string($token) && preg_match('/^[\x21-\x7e]{20,4096}$/', $token) === 1;
}

function ttn_push_rest_register_device(WP_REST_Request $request) {
    $token = $request->get_param('token');
    $platform = $request->get_param('platform');
    if (!ttn_push_valid_token($token) || !in_array($platform, array('ios', 'android'), true)) {
        return new WP_Error('ttn_push_invalid_device', 'A valid device token and platform are required.', array('status' => 400));
    }
    $result = ttn_push_send_now('device.register', 'device.register', array(
        'user_id' => (int) ttn_push_rest_user($request)->ID, 'token' => $token, 'platform' => $platform,
    ));
    if (is_wp_error($result)) {
        return $result;
    }
    // Firebase rejects tokens Google no longer accepts; the app then fetches a fresh one.
    if (($result['registered'] ?? true) === false) {
        return rest_ensure_response(array('registered' => false, 'reason' => 'invalid_token'));
    }
    return rest_ensure_response(array('registered' => true));
}

function ttn_push_rest_unregister_device(WP_REST_Request $request) {
    $token = $request->get_param('token');
    if (!ttn_push_valid_token($token)) {
        return new WP_Error('ttn_push_invalid_device', 'A valid device token is required.', array('status' => 400));
    }
    $result = ttn_push_send_now('device.unregister', 'device.unregister', array(
        'user_id' => (int) ttn_push_rest_user($request)->ID, 'token' => $token,
    ));
    return is_wp_error($result) ? $result : rest_ensure_response(array('unregistered' => true));
}

function ttn_push_preferences_response($result) {
    if (is_wp_error($result)) {
        return $result;
    }
    $preferences = is_array($result['preferences'] ?? null) ? $result['preferences'] : array();
    return rest_ensure_response(array('preferences' => array(
        'booking_updates' => (bool) ($preferences['bookingUpdates'] ?? true),
        'booking_reminders' => (bool) ($preferences['bookingReminders'] ?? true),
        'marketing' => (bool) ($preferences['marketing'] ?? false),
    )));
}

function ttn_push_rest_get_preferences(WP_REST_Request $request) {
    return ttn_push_preferences_response(ttn_push_send_now('preferences.get', 'preferences.get', array('user_id' => (int) ttn_push_rest_user($request)->ID)));
}

function ttn_push_rest_set_preferences(WP_REST_Request $request) {
    $changes = array();
    foreach (array('booking_updates', 'booking_reminders', 'marketing') as $key) {
        $value = $request->get_param($key);
        if ($value !== null) {
            if (!is_bool($value)) {
                return new WP_Error('ttn_push_invalid_preferences', $key . ' must be true or false.', array('status' => 400));
            }
            $changes[$key] = $value;
        }
    }
    if (!$changes) {
        return new WP_Error('ttn_push_invalid_preferences', 'Include at least one preference.', array('status' => 400));
    }
    return ttn_push_preferences_response(ttn_push_send_now('preferences.set', 'preferences.set', array(
        'user_id' => (int) ttn_push_rest_user($request)->ID, 'preferences' => $changes,
    )));
}

add_action('rest_api_init', function () {
    $auth = 'ttn_jwt_authenticate_request';
    register_rest_route('ttn/v1', '/push/devices', array(
        'methods' => 'POST', 'callback' => 'ttn_push_rest_register_device', 'permission_callback' => $auth,
    ));
    register_rest_route('ttn/v1', '/push/devices/unregister', array(
        'methods' => 'POST', 'callback' => 'ttn_push_rest_unregister_device', 'permission_callback' => $auth,
    ));
    register_rest_route('ttn/v1', '/push/preferences', array(
        array('methods' => 'GET', 'callback' => 'ttn_push_rest_get_preferences', 'permission_callback' => $auth),
        array('methods' => 'POST', 'callback' => 'ttn_push_rest_set_preferences', 'permission_callback' => $auth),
    ));
});

// ===== Admin: Send app notification =====

/** Length in UTF-16 code units, matching the JavaScript limits enforced by Firebase. */
function ttn_push_text_length($value) {
    return function_exists('mb_convert_encoding') ? intdiv(strlen(mb_convert_encoding($value, 'UTF-16LE', 'UTF-8')), 2) : strlen($value);
}

/**
 * Validates an admin message. Returns the normalized message or a WP_Error.
 *
 * @return array{target:string, user_id:int, title:string, body:string, route:string}|WP_Error
 */
function ttn_push_validate_admin_message(array $input) {
    $title = trim(sanitize_text_field((string) ($input['title'] ?? '')));
    $body = trim(sanitize_textarea_field((string) ($input['body'] ?? '')));
    $body = trim(preg_replace('/\s+/u', ' ', $body));
    $route = (string) ($input['route'] ?? '/');
    $target = ($input['target'] ?? '') === 'broadcast' ? 'broadcast' : 'user';
    if ($title === '' || ttn_push_text_length($title) > TTN_PUSH_TITLE_MAX) {
        return new WP_Error('title', sprintf('Title is required and must be at most %d characters.', TTN_PUSH_TITLE_MAX));
    }
    if ($body === '' || ttn_push_text_length($body) > TTN_PUSH_BODY_MAX) {
        return new WP_Error('body', sprintf('Message is required and must be at most %d characters.', TTN_PUSH_BODY_MAX));
    }
    if (!in_array($route, TTN_PUSH_ROUTES, true)) {
        return new WP_Error('route', 'Choose a valid app screen.');
    }
    $user_id = 0;
    if ($target === 'user') {
        $customer = trim((string) ($input['customer'] ?? ''));
        $user = ctype_digit($customer) ? get_user_by('id', (int) $customer) : (is_email($customer) ? get_user_by('email', $customer) : false);
        if (!$user) {
            return new WP_Error('customer', 'No customer matches that email or user ID.');
        }
        $user_id = (int) $user->ID;
    } elseif (trim((string) ($input['confirmation'] ?? '')) !== TTN_PUSH_BROADCAST_CONFIRMATION) {
        return new WP_Error('confirmation', sprintf('Type %s to confirm a broadcast.', TTN_PUSH_BROADCAST_CONFIRMATION));
    }
    return array('target' => $target, 'user_id' => $user_id, 'title' => $title, 'body' => $body, 'route' => $route);
}

function ttn_push_summarize_delivery($result) {
    if (is_wp_error($result)) {
        return 'Not sent: ' . (string) ($result->get_error_data()['reason'] ?? $result->get_error_code());
    }
    $delivery = is_array($result['delivery'] ?? null) ? $result['delivery'] : array();
    if (!empty($delivery['skipped'])) {
        $reasons = array(
            'not_allowlisted' => 'Skipped: this customer is not on the Firebase test allowlist.',
            'preference_off' => 'Skipped: the customer turned this notification type off.',
            'no_devices' => 'Skipped: the customer has no registered app devices.',
        );
        return $reasons[$delivery['skipped']] ?? 'Skipped.';
    }
    $summary = sprintf('Sent to %d device(s), %d failed', (int) ($delivery['sent'] ?? 0), (int) ($delivery['failed'] ?? 0));
    if (isset($delivery['recipients'])) {
        $summary .= sprintf(', %d opted-in recipient(s)', (int) $delivery['recipients']);
    }
    return $summary . '.';
}

function ttn_push_admin_menu() {
    add_submenu_page('ttn-bookings-dashboard', 'Send App Notification', 'App Notifications', 'manage_woocommerce', 'ttn-push-notifications', 'ttn_push_admin_page');
}
add_action('admin_menu', 'ttn_push_admin_menu', 20);

function ttn_push_admin_page() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('You do not have permission to send app notifications.', 'tee-time-nexus-bookings'));
    }
    global $wpdb;
    $notice = null;
    $input = array('target' => 'user', 'customer' => '', 'title' => '', 'body' => '', 'route' => '/');
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ttn_push_send'])) {
        check_admin_referer('ttn_push_send_notification');
        $input = array_merge($input, wp_unslash(array_intersect_key($_POST, array_flip(array('target', 'customer', 'title', 'body', 'route', 'confirmation')))));
        $message = ttn_push_validate_admin_message($input);
        if (is_wp_error($message)) {
            $notice = array('error', $message->get_error_message());
        } elseif (!ttn_push_is_configured()) {
            $notice = array('error', 'Notifications are not configured. Add TTN_NOTIFY_WEBHOOK_URL and TTN_NOTIFY_WEBHOOK_SECRET to wp-config.php.');
        } else {
            $payload = array('title' => $message['title'], 'body' => $message['body'], 'route' => $message['route']);
            $result = $message['target'] === 'broadcast'
                ? ttn_push_send_now('admin.broadcast', 'message.broadcast', $payload)
                : ttn_push_send_now('admin.message', 'message.user', array('user_id' => $message['user_id']) + $payload);
            $outcome = ttn_push_summarize_delivery($result);
            $wpdb->insert(ttn_push_audit_table(), array(
                'created_at' => current_time('mysql', true),
                'admin_user_id' => get_current_user_id(),
                'target' => $message['target'] === 'broadcast' ? 'broadcast' : 'user:' . $message['user_id'],
                'title' => $message['title'],
                'body' => $message['body'],
                'route' => $message['route'],
                'outcome' => substr($outcome, 0, 190),
            ));
            $notice = array(is_wp_error($result) ? 'error' : 'success', $outcome);
            if (!is_wp_error($result)) {
                $input = array('target' => 'user', 'customer' => '', 'title' => '', 'body' => '', 'route' => '/');
            }
        }
    }
    $log = $wpdb->get_results('SELECT * FROM ' . ttn_push_audit_table() . ' ORDER BY id DESC LIMIT 25');
    $failed = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . ttn_push_outbox_table() . " WHERE status = 'failed'");
    $outbox_ready = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', ttn_push_outbox_table())) === ttn_push_outbox_table();
    $booking_events = $outbox_ready ? $wpdb->get_results('SELECT event_type, booking_id, status, attempts, last_error, created_at FROM ' . ttn_push_outbox_table() . ' ORDER BY id DESC LIMIT 20') : array();
    $route_labels = array('/' => 'Home', '/book' => 'Book a bay', '/reservations' => 'Reservations', '/membership' => 'Membership', '/account' => 'Account', '/notifications' => 'Notifications');
    ?>
    <div class="wrap">
        <h1>Send App Notification</h1>
        <?php if (!ttn_push_is_configured()) : ?>
            <div class="notice notice-warning"><p>Notifications are off until <code>TTN_NOTIFY_WEBHOOK_URL</code> and <code>TTN_NOTIFY_WEBHOOK_SECRET</code> are defined in wp-config.php.</p></div>
        <?php endif; ?>
        <?php if ($failed) : ?>
            <div class="notice notice-warning"><p><?php echo esc_html(sprintf('%d booking notification(s) could not be delivered after retries. Check the PHP error log for details.', $failed)); ?></p></div>
        <?php endif; ?>
        <?php if ($notice) : ?>
            <div class="notice notice-<?php echo esc_attr($notice[0]); ?>"><p><?php echo esc_html($notice[1]); ?></p></div>
        <?php endif; ?>
        <p>While the app is in testing, Firebase only delivers to the test accounts listed in <code>TEST_WP_USER_IDS</code>. Messages to one customer always arrive (they are account messages); broadcasts reach only customers who opted into offers and news.</p>
        <form method="post">
            <?php wp_nonce_field('ttn_push_send_notification'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Send to</th>
                    <td>
                        <label><input type="radio" name="target" value="user" <?php checked($input['target'] !== 'broadcast'); ?>> One customer</label><br>
                        <label><input type="radio" name="target" value="broadcast" <?php checked($input['target'] === 'broadcast'); ?>> Everyone opted into offers and news</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ttn-push-customer">Customer email or user ID</label></th>
                    <td><input type="text" id="ttn-push-customer" name="customer" class="regular-text" value="<?php echo esc_attr($input['customer']); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ttn-push-title">Title</label></th>
                    <td><input type="text" id="ttn-push-title" name="title" class="regular-text" maxlength="<?php echo (int) TTN_PUSH_TITLE_MAX; ?>" required value="<?php echo esc_attr($input['title']); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ttn-push-body">Message</label></th>
                    <td><textarea id="ttn-push-body" name="body" class="large-text" rows="3" maxlength="<?php echo (int) TTN_PUSH_BODY_MAX; ?>" required><?php echo esc_textarea($input['body']); ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ttn-push-route">Opens</label></th>
                    <td><select id="ttn-push-route" name="route">
                        <?php foreach ($route_labels as $route => $label) : ?>
                            <option value="<?php echo esc_attr($route); ?>" <?php selected($input['route'], $route); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ttn-push-confirmation">Broadcast confirmation</label></th>
                    <td><input type="text" id="ttn-push-confirmation" name="confirmation" class="regular-text" autocomplete="off">
                        <p class="description">Only for broadcasts: type <strong><?php echo esc_html(TTN_PUSH_BROADCAST_CONFIRMATION); ?></strong>.</p></td>
                </tr>
            </table>
            <?php submit_button('Send notification', 'primary', 'ttn_push_send'); ?>
        </form>
        <h2>Recent messages</h2>
        <table class="widefat striped">
            <thead><tr><th>Sent (UTC)</th><th>By</th><th>To</th><th>Title</th><th>Message</th><th>Result</th></tr></thead>
            <tbody>
            <?php if (!$log) : ?>
                <tr><td colspan="6">No messages sent yet.</td></tr>
            <?php endif; ?>
            <?php foreach ((array) $log as $entry) : $admin = get_userdata((int) $entry->admin_user_id); ?>
                <tr>
                    <td><?php echo esc_html($entry->created_at); ?></td>
                    <td><?php echo esc_html($admin ? $admin->user_login : '#' . $entry->admin_user_id); ?></td>
                    <td><?php echo esc_html($entry->target); ?></td>
                    <td><?php echo esc_html($entry->title); ?></td>
                    <td><?php echo esc_html($entry->body); ?></td>
                    <td><?php echo esc_html($entry->outcome); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <h2>Automatic booking notifications</h2>
        <p>Booking confirmations, changes and cancellations queued after payment. Reminders are scheduled by Firebase from these events.</p>
        <?php if (!$outbox_ready) : ?>
            <div class="notice notice-error inline"><p>The booking notification queue table is missing, so automatic booking notifications cannot be sent. Deactivate and reactivate the bookings plugin, or delete the <code>ttn_push_db_version</code> option, to recreate it.</p></div>
        <?php endif; ?>
        <table class="widefat striped">
            <thead><tr><th>Queued (UTC)</th><th>Event</th><th>Booking</th><th>Status</th><th>Attempts</th><th>Last error</th></tr></thead>
            <tbody>
            <?php if (!$booking_events) : ?>
                <tr><td colspan="6">No booking notifications queued yet.</td></tr>
            <?php endif; ?>
            <?php foreach ((array) $booking_events as $event) : ?>
                <tr>
                    <td><?php echo esc_html($event->created_at); ?></td>
                    <td><?php echo esc_html($event->event_type); ?></td>
                    <td><?php echo esc_html('TTN-' . str_pad((string) $event->booking_id, 6, '0', STR_PAD_LEFT)); ?></td>
                    <td><?php echo esc_html($event->status); ?></td>
                    <td><?php echo esc_html((string) $event->attempts); ?></td>
                    <td><?php echo esc_html($event->last_error ?: '—'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
