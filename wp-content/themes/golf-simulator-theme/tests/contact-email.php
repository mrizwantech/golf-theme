<?php

if (PHP_SAPI !== 'cli') {
    exit;
}

define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
$users = array();
$meta = array();
$transients = array();
$actions = array();
$mail_calls = array();
$mail_result = true;
$storage_failure = '';
$update_failure = false;
$current_user_id = 1;

class WP_User {
    public $ID;
    public $user_email;
    public $display_name;
    public function __construct($id, $email) {
        $this->ID = $id;
        $this->user_email = $email;
        $this->display_name = 'Test Golfer';
    }
}
class WP_Error {
    private $code;
    private $message;
    public function __construct($code, $message, $data = array()) { $this->code = $code; $this->message = $message; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function add($code, $message) { $this->code = $code; $this->message = $message; }
}
class WP_REST_Request {
    private $params;
    public function __construct($params = array()) { $this->params = $params; }
    public function get_param($key) { return $this->params[$key] ?? null; }
    public function set_param($key, $value) { $this->params[$key] = $value; }
}
function add_action($hook, $callback, $priority = 10, $args = 1) { $GLOBALS['actions'][$hook][] = $callback; }
function add_filter($hook, $callback, $priority = 10, $args = 1) {}
function get_userdata($id) { return $GLOBALS['users'][$id] ?? false; }
function get_user_meta($id, $key, $single) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function update_user_meta($id, $key, $value) {
    if ($GLOBALS['storage_failure'] === $key) { return false; }
    $GLOBALS['meta'][$id][$key] = $value;
    return true;
}
function delete_user_meta($id, $key) { unset($GLOBALS['meta'][$id][$key]); }
function is_email($email) { return filter_var($email, FILTER_VALIDATE_EMAIL) !== false; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function rest_ensure_response($value) { return $value; }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = $value; }
function email_exists($email) {
    foreach ($GLOBALS['users'] as $id => $user) {
        if (strtolower($user->user_email) === strtolower($email)) { return $id; }
    }
    return false;
}
function wp_update_user($data) {
    if ($GLOBALS['update_failure']) { return new WP_Error('update_failed', 'Unable to update account.'); }
    $GLOBALS['users'][$data['ID']]->user_email = $data['user_email'];
    return $data['ID'];
}
function wp_hash($value) { return hash_hmac('sha256', $value, 'test-only-secret'); }
function wp_mail($to, $subject, $body, $headers) {
    $GLOBALS['mail_calls'][] = compact('to', 'subject', 'body', 'headers');
    return $GLOBALS['mail_result'];
}
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function golf_simulator_theme_render_email_template($eyebrow, $heading, $body) { return $body; }
function golf_simulator_theme_get_email_headers() { return array('Content-Type: text/html; charset=UTF-8'); }
function get_current_user_id() { return $GLOBALS['current_user_id']; }
function WC() { return $GLOBALS['wc']; }
function register_rest_route($namespace, $path, $args) { $GLOBALS['routes'][$path] = $args; }
function check_contact($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
function expect_contact_error($value, $code) {
    check_contact(is_wp_error($value) && $value->get_error_code() === $code, 'Expected error: ' . $code);
}
function contact_request($id, $params = array()) {
    return new WP_REST_Request(array_merge(array('ttn_auth_user' => get_userdata($id)), $params));
}
function request_code($id, $email) {
    unset($GLOBALS['transients']['ttn_contact_email_send_' . $id]);
    $result = golf_simulator_theme_contact_email_send(contact_request($id, array('email' => $email)));
    check_contact(!is_wp_error($result), 'Verification email must be requested.');
    preg_match('/>([0-9]{6})</', end($GLOBALS['mail_calls'])['body'], $match);
    check_contact(isset($match[1]), 'Verification email must contain a six-digit code.');
    return $match[1];
}

require dirname(__DIR__) . '/inc/contact-email.php';
require dirname(__DIR__) . '/inc/mobile-membership-api.php';

$log_file = tempnam(sys_get_temp_dir(), 'ttn-contact-');
$old_log = ini_get('error_log');
ini_set('error_log', $log_file);
try {
    $users[1] = new WP_User(1, 'private@privaterelay.appleid.com');
    $users[2] = new WP_User(2, 'normal@example.test');
    $meta[1]['ttn_apple_subject'] = 'apple-subject-1';
    $status = golf_simulator_theme_contact_email_status(1);
    check_contact($status['required'] && !$status['verified'], 'Existing relay Apple accounts must require verification.');
    check_contact($meta[1]['_ttn_contact_email_required'] === '1', 'Persist the requirement before changing the account email.');
    check_contact(!golf_simulator_theme_contact_email_status(2)['required'], 'Do not gate regular accounts.');
    check_contact(golf_simulator_theme_is_apple_relay_email('PRIVATE@PRIVATERELAY.APPLEID.COM'), 'Relay detection must be case-insensitive.');
    check_contact(!golf_simulator_theme_is_apple_relay_email('private@privaterelay.appleid.com.example.test'), 'Do not misidentify lookalike domains.');
    expect_contact_error(golf_simulator_theme_contact_email_get(new WP_REST_Request()), 'contact_email_not_authenticated');
    expect_contact_error(golf_simulator_theme_contact_email_send(new WP_REST_Request()), 'contact_email_not_authenticated');
    expect_contact_error(golf_simulator_theme_contact_email_verify(new WP_REST_Request()), 'contact_email_not_authenticated');
    foreach (array('bad-email', 'PRIVATE@PRIVATERELAY.APPLEID.COM') as $email) {
        expect_contact_error(golf_simulator_theme_contact_email_send(contact_request(1, array('email' => $email))), 'contact_email_invalid');
    }
    expect_contact_error(golf_simulator_theme_contact_email_send(contact_request(1, array('email' => 'normal@example.test'))), 'contact_email_in_use');
    expect_contact_error(golf_simulator_theme_require_contact_email(1), 'contact_email_verification_required');
    expect_contact_error(golf_simulator_theme_mobile_membership_checkout(contact_request(1)), 'contact_email_verification_required');

    $code = request_code(1, 'contact@example.test');
    check_contact(end($mail_calls)['to'] === 'contact@example.test', 'Send the code to the requested non-relay address.');
    check_contact($meta[1]['_ttn_contact_email_pending']['hash'] !== $code && !isset($meta[1]['_ttn_contact_email_pending']['code']), 'Do not store plaintext codes.');
    expect_contact_error(golf_simulator_theme_contact_email_send(contact_request(1, array('email' => 'contact@example.test'))), 'contact_email_rate_limited');
    check_contact($users[1]->user_email === 'private@privaterelay.appleid.com', 'Do not change account email before verification.');
    expect_contact_error(golf_simulator_theme_contact_email_verify(contact_request(1, array('code' => 'not-a-code'))), 'contact_email_code_invalid');
    $result = golf_simulator_theme_contact_email_verify(contact_request(1, array('code' => $code)));
    check_contact($result['verified'] && !$result['required'] && $result['pending_email'] === null, 'Successful verification must clear the gate and pending code.');
    check_contact($users[1]->user_email === 'contact@example.test' && $meta[1]['ttn_apple_subject'] === 'apple-subject-1', 'Update account email without changing Apple identity.');
    check_contact($meta[1]['billing_email'] === 'contact@example.test', 'Use the verified email for future checkout contact defaults.');
    check_contact(golf_simulator_theme_require_contact_email(1) === true, 'Verified contact must pass the access prerequisite.');
    expect_contact_error(golf_simulator_theme_contact_email_verify(contact_request(1, array('code' => $code))), 'contact_email_code_expired');
    $users[1]->user_email = 'unverified@example.test';
    check_contact(golf_simulator_theme_contact_email_status(1)['required'], 'Changing an email later must invalidate its verified status.');

    $code = request_code(1, 'next@example.test');
    for ($attempt = 0; $attempt < 5; $attempt++) {
        expect_contact_error(golf_simulator_theme_contact_email_verify(contact_request(1, array('code' => '000000'))), 'contact_email_code_invalid');
    }
    expect_contact_error(golf_simulator_theme_contact_email_verify(contact_request(1, array('code' => $code))), 'contact_email_code_expired');
    check_contact(golf_simulator_theme_contact_email_status(1)['required'], 'Exhausted guesses must not clear the gate.');
    $code = request_code(1, 'next@example.test');
    $meta[1]['_ttn_contact_email_pending']['expires_at'] = time() - 1;
    expect_contact_error(golf_simulator_theme_contact_email_verify(contact_request(1, array('code' => $code))), 'contact_email_code_expired');

    $old_code = request_code(1, 'next@example.test');
    $code = request_code(1, 'another@example.test');
    while ($code === $old_code) {
        $code = request_code(1, 'another@example.test');
    }
    expect_contact_error(golf_simulator_theme_contact_email_verify(contact_request(1, array('code' => $old_code))), 'contact_email_code_invalid');
    $users[3] = new WP_User(3, 'another@example.test');
    expect_contact_error(golf_simulator_theme_contact_email_verify(contact_request(1, array('code' => $code))), 'contact_email_in_use');
    unset($users[3]);
    $update_failure = true;
    expect_contact_error(golf_simulator_theme_contact_email_verify(contact_request(1, array('code' => $code))), 'update_failed');
    $update_failure = false;
    $storage_failure = '_ttn_verified_contact_email';
    expect_contact_error(golf_simulator_theme_contact_email_verify(contact_request(1, array('code' => $code))), 'contact_email_storage_failed');
    check_contact(golf_simulator_theme_contact_email_status(1)['required'], 'Failed verification persistence must fail closed.');
    $storage_failure = '';
    unset($transients['ttn_contact_email_send_1']);
    $mail_result = false;
    expect_contact_error(golf_simulator_theme_contact_email_send(contact_request(1, array('email' => 'retry@example.test'))), 'contact_email_send_failed');
    check_contact(!isset($meta[1]['_ttn_contact_email_pending']), 'Failed sends must not leave a usable code.');
    $mail_result = true;
    unset($transients['ttn_contact_email_send_1']);
    $storage_failure = '_ttn_contact_email_pending';
    expect_contact_error(golf_simulator_theme_contact_email_send(contact_request(1, array('email' => 'retry@example.test'))), 'contact_email_storage_failed');
    $storage_failure = '';

    $wc = (object) array('cart' => new class {
        public function get_cart() { return array(array('golf_simulator_membership' => array('package_name' => 'PAR'))); }
    });
    $errors = new WP_Error('', '');
    golf_simulator_theme_contact_email_checkout_validation(array(), $errors);
    check_contact($errors->get_error_code() === 'contact_email_verification_required', 'Website membership checkout must enforce the same gate.');
    $current_user_id = 2;
    $errors = new WP_Error('', '');
    golf_simulator_theme_contact_email_checkout_validation(array(), $errors);
    check_contact($errors->get_error_code() === '', 'Regular-account checkout must remain unchanged.');

    foreach ($actions['rest_api_init'] as $callback) { $callback(); }
    foreach (array('/account/contact-email', '/account/contact-email/send', '/account/contact-email/verify') as $path) {
        check_contact($GLOBALS['routes'][$path]['permission_callback'] === 'ttn_jwt_authenticate_request', 'All verification routes must require authenticated JWTs.');
    }
} finally {
    ini_set('error_log', $old_log);
    unlink($log_file);
}
echo "Contact email passed: relay detection, verified email replacement, expiry, guessing/send limits, failure handling, authenticated routes, and membership checkout gates.\n";
