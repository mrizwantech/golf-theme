<?php

if (PHP_SAPI !== 'cli') {
    exit;
}

define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
$hooks = array();
$routes = array();
$calls = array();
$user_meta = array();
$integration_errors = array();
$delete_result = true;
$administrators = array(99);
$transients = array();

class WP_Error {
    public $code;
    public $message;
    public $data;
    public function __construct($code, $message, $data = array()) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }
}
class WP_User {
    public $user_pass = 'hash';
    public function __construct(public $ID) {}
}
class WP_REST_Request {
    public function __construct(private $params = array()) {}
    public function get_param($key) { return $this->params[$key] ?? null; }
}
class WP_REST_Response {
    public $headers = array();
    public function __construct(public $data) {}
    public function header($name, $value) { $this->headers[$name] = $value; }
}

function add_action($name, $callback) { $GLOBALS['hooks'][$name][] = $callback; }
function register_rest_route($namespace, $path, $args) { $GLOBALS['routes'][$path] = $args; }
function get_user_meta($id, $key, $single = true) { return $GLOBALS['user_meta'][$id][$key] ?? ''; }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key, $value, $expiration) { $GLOBALS['transients'][$key] = $value; }
function delete_transient($key) { unset($GLOBALS['transients'][$key]); }
function wp_check_password($password, $hash, $user_id) { return $password === 'correct'; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function rest_ensure_response($value) { return new WP_REST_Response($value); }
function ttn_kisi_invalidate_user_access($id) {
    $GLOBALS['calls'][] = 'kisi';
    return $GLOBALS['integration_errors']['kisi'] ?? true;
}
function ttn_push_delete_user_data($id) {
    $GLOBALS['calls'][] = 'push';
    return $GLOBALS['integration_errors']['push'] ?? true;
}
function ttn_passkey_delete_user_data($id) {
    $GLOBALS['calls'][] = 'passkeys';
    return $GLOBALS['integration_errors']['passkeys'] ?? true;
}
function get_users($args) {
    $GLOBALS['calls'][] = 'admins';
    return $GLOBALS['administrators'];
}
function wp_clear_scheduled_hook($hook, $args) { $GLOBALS['calls'][] = 'clear-schedule'; }
function wp_delete_user($user_id, $reassign) {
    $GLOBALS['calls'][] = array('delete', $user_id, $reassign);
    return $GLOBALS['delete_result'];
}
function check_deletion($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function deletion_request($user_id = 7, $confirm = true, $password = 'correct', $claims = array()) {
    $params = array();
    if ($user_id !== null) {
        $params['ttn_auth_user'] = new WP_User($user_id);
    }
    if ($confirm !== null) {
        $params['confirm'] = $confirm;
    }
    if ($password !== null) {
        $params['password'] = $password;
    }
    $params['ttn_auth_claims'] = $claims;
    return new WP_REST_Request($params);
}
function expect_deletion_error($result, $code) {
    check_deletion(is_wp_error($result) && $result->code === $code, 'Expected deletion error: ' . $code);
}
function reset_deletion_state() {
    $GLOBALS['calls'] = array();
    $GLOBALS['user_meta'] = array();
    $GLOBALS['integration_errors'] = array();
    $GLOBALS['delete_result'] = true;
    $GLOBALS['administrators'] = array(99);
    $GLOBALS['transients'] = array();
}

require dirname(__DIR__) . '/inc/account-deletion.php';
foreach ($hooks['rest_api_init'] as $callback) {
    $callback();
}

check_deletion($routes['/account']['methods'] === 'DELETE', 'Register account deletion as an authenticated DELETE route.');
check_deletion($routes['/account']['permission_callback'] === 'ttn_jwt_authenticate_request', 'Require the app JWT.');
check_deletion($routes['/account']['args']['password']['required'] === false, 'Allow password verification as a deletion credential.');
expect_deletion_error(golf_simulator_theme_mobile_delete_account(deletion_request(null)), 'account_delete_not_authenticated');
expect_deletion_error(golf_simulator_theme_mobile_delete_account(deletion_request(7, false)), 'account_delete_confirmation_required');
check_deletion($calls === array(), 'Reject unconfirmed requests before cleanup.');

reset_deletion_state();
expect_deletion_error(golf_simulator_theme_mobile_delete_account(deletion_request(7, true, 'wrong')), 'account_delete_invalid_password');
check_deletion($calls === array() && $transients['ttn_account_delete_fail_7'] === 1, 'Reject invalid passwords before cleanup and rate-limit attempts.');

reset_deletion_state();
expect_deletion_error(golf_simulator_theme_mobile_delete_account(deletion_request(7, true, null)), 'account_delete_reauthentication_required');
expect_deletion_error(golf_simulator_theme_mobile_delete_account(deletion_request(7, true, null, array(
    'auth_method' => 'passkey',
    'auth_at' => time() - 301,
))), 'account_delete_reauthentication_required');

reset_deletion_state();
check_deletion(golf_simulator_theme_mobile_delete_account(deletion_request(7, true, null, array(
    'auth_method' => 'apple',
    'auth_at' => time(),
))) instanceof WP_REST_Response, 'Allow a recently verified Apple identity to authorize deletion.');

reset_deletion_state();
$user_meta[7]['ttn_kisi_booking_grant'] = array('id' => 81);
$integration_errors['kisi'] = new WP_Error('kisi_unavailable', 'Could not revoke access.');
expect_deletion_error(golf_simulator_theme_mobile_delete_account(deletion_request()), 'kisi_unavailable');
check_deletion($calls === array('admins', 'kisi'), 'Do not delete an account if door access cannot be revoked.');

reset_deletion_state();
$integration_errors['push'] = new WP_Error('push_unavailable', 'Could not clear devices.');
expect_deletion_error(golf_simulator_theme_mobile_delete_account(deletion_request()), 'push_unavailable');
check_deletion($calls === array('admins', 'push'), 'Do not delete an account while push data cannot be removed.');

reset_deletion_state();
$integration_errors['passkeys'] = new WP_Error('passkeys_unavailable', 'Could not clear passkeys.');
expect_deletion_error(golf_simulator_theme_mobile_delete_account(deletion_request()), 'passkeys_unavailable');
check_deletion($calls === array('admins', 'push', 'passkeys'), 'Do not delete an account while saved passkeys remain.');

reset_deletion_state();
$user_meta[7]['ttn_kisi_booking_grant'] = array('id' => 81);
$result = golf_simulator_theme_mobile_delete_account(deletion_request());
check_deletion($result instanceof WP_REST_Response && $result->data === array('deleted' => true), 'Confirm deletion only after all cleanup and WordPress deletion succeed.');
check_deletion($result->headers['Cache-Control'] === 'private, no-store, max-age=0', 'Do not cache account-deletion responses.');
check_deletion($calls === array('admins', 'kisi', 'push', 'passkeys', array('delete', 7, 99), 'clear-schedule'), 'Revoke access and preserve authored records by reassigning them before deleting the account.');

reset_deletion_state();
$administrators = array();
expect_deletion_error(golf_simulator_theme_mobile_delete_account(deletion_request()), 'account_delete_reassignment_unavailable');
check_deletion($calls === array('admins'), 'Do not start deletion if authored records cannot be preserved.');

echo "Mobile account deletion checks passed.\n";
