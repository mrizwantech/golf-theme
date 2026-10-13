<?php
if (PHP_SAPI !== 'cli') { exit; }
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
$rate = array();
$transients = array();
$meta = array();
$hooks = array();
$routes = array();
$issued = 0;
function add_action($name, $callback, $priority = 10) { $GLOBALS['hooks'][$name][] = $callback; }
function add_filter($name, $callback, $priority = 10, $args = 1) { $GLOBALS['hooks'][$name][] = $callback; }
function register_rest_route($namespace, $path, $options) { $GLOBALS['routes'][$path] = $options; }
function get_option($name) { return $name === 'ttn_passkey_schema' ? '1' : false; }
function get_user_meta($id, $name, $single = true) { return $GLOBALS['meta'][$id][$name] ?? ''; }
function add_user_meta($id, $name, $value, $unique) {
    if (isset($GLOBALS['meta'][$id][$name])) { return false; }
    $GLOBALS['meta'][$id][$name] = $value; return true;
}
function sanitize_text_field($value) { return strip_tags(trim($value)); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function get_user_by($field, $id) { return in_array((int) $id, array(13, 14), true) ? new WP_User((int) $id) : false; }
function ttn_jwt_issue_tokens($user) {
    $GLOBALS['issued']++;
    return array('access_token' => 'test-access', 'refresh_token' => 'test-refresh', 'expires_in' => 900, 'token_type' => 'Bearer');
}
function ttn_jwt_is_rate_limited($key) { return ($GLOBALS['rate'][$key] ?? 0) >= 5; }
function ttn_jwt_register_failed_attempt($key) { $GLOBALS['rate'][$key] = ($GLOBALS['rate'][$key] ?? 0) + 1; }
function ttn_jwt_clear_failed_attempts($key) { unset($GLOBALS['rate'][$key]); }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = $value; }
class WP_Error {
    public function __construct(public $code, public $message, public $data = array()) {}
}
class WP_User {
    public $user_email;
    public $display_name;
    public function __construct(public $ID = 13) {
        $this->user_email = "member$ID@example.test"; $this->display_name = "Member $ID";
    }
}
class WP_REST_Request {
    public function __construct(public $params = array()) {}
    public function get_param($name) { return $this->params[$name] ?? null; }
    public function get_body() { return json_encode($this->params); }
}
class PasskeyTestDB {
    public $prefix = 'wp_';
    public $last_error = '';
    public $suppressed = false;
    public $before_update = null;
    public $force_noop = false;
    public $db;
    public function __construct() {
        $this->db = new PDO('sqlite::memory:');
        $this->db->exec('CREATE TABLE wp_ttn_passkeys (
            id_hash TEXT PRIMARY KEY, credential_id TEXT, user_id INTEGER, slot INTEGER, user_handle TEXT,
            public_key TEXT, sign_count INTEGER, backup_eligible INTEGER, label TEXT, created_at INTEGER,
            last_used_at INTEGER, UNIQUE(user_id, slot))');
        $this->db->exec('CREATE TABLE wp_ttn_passkey_challenges (
            id_hash TEXT PRIMARY KEY, challenge TEXT, purpose TEXT, platform TEXT, user_id INTEGER, user_handle TEXT, expires_at INTEGER)');
    }
    public function prepare($sql, ...$values) {
        $index = 0;
        return preg_replace_callback('/%[sd]/', function ($match) use ($values, &$index) {
            $value = $values[$index++]; return $match[0] === '%d' ? (string) (int) $value : $this->db->quote((string) $value);
        }, $sql);
    }
    public function execute($sql) {
        $this->last_error = '';
        try { return $this->db->query($sql); }
        catch (PDOException $error) { $this->last_error = $error->getMessage(); return false; }
    }
    public function query($sql) {
        if (str_starts_with($sql, 'UPDATE wp_ttn_passkeys') && $this->before_update) {
            $callback = $this->before_update; $this->before_update = null; $callback();
        }
        if (str_starts_with($sql, 'UPDATE wp_ttn_passkeys') && $this->force_noop) {
            $this->force_noop = false; return 0;
        }
        $statement = $this->execute($sql); return $statement ? $statement->rowCount() : false;
    }
    public function get_row($sql, $mode) { $statement = $this->execute($sql); return $statement ? $statement->fetch(PDO::FETCH_ASSOC) : null; }
    public function get_var($sql) { $statement = $this->execute($sql); return $statement ? $statement->fetchColumn() : null; }
    public function get_results($sql, $mode) { $statement = $this->execute($sql); return $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : null; }
    public function insert($table, $data) {
        $sql = "INSERT INTO $table (" . implode(',', array_keys($data)) . ') VALUES (' .
            implode(',', array_map(fn($value) => $this->db->quote((string) $value), array_values($data))) . ')';
        return $this->query($sql);
    }
    public function delete($table, $where) {
        $clauses = array();
        foreach ($where as $key => $value) { $clauses[] = "$key = " . $this->db->quote((string) $value); }
        return $this->query("DELETE FROM $table WHERE " . implode(' AND ', $clauses));
    }
    public function suppress_errors($value) { $previous = $this->suppressed; $this->suppressed = $value; return $previous; }
}
if (($argv[1] ?? '') === '--dependencies-missing') {
    require dirname(__DIR__, 3) . '/plugins/tee-time-nexus-bookings/inc/class-ttn-passkey-auth.php';
    try {
        ttn_passkey_verifier('ios');
        throw new Exception('Expected missing-extension error');
    } catch (RuntimeException $error) {
        if (!str_contains($error->getMessage(), 'unavailable on the server')) { throw $error; }
    }
    echo "Missing passkey extensions fail explicitly without failing plugin load.\n";
    exit;
}
$wpdb = new PasskeyTestDB();
require dirname(__DIR__, 3) . '/plugins/tee-time-nexus-bookings/inc/class-ttn-passkey-auth.php';

function expect($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function dispatch($operation, $params, $reset = true) {
    if ($reset) { $GLOBALS['rate'] = array(); $GLOBALS['transients'] = array(); }
    return ttn_passkey_dispatch(new WP_REST_Request($params), $operation);
}
function rejected($result, $message, $status = 400) {
    expect(is_wp_error($result) && $result->data['status'] === $status, "$message: expected HTTP $status");
}
function options($purpose = 'login', $user = 13, $platform = 'ios') {
    $result = dispatch("$purpose-options", array('platform' => $platform, 'ttn_auth_user' => new WP_User($user)));
    expect(!is_wp_error($result), 'Could not create ceremony');
    return json_decode(json_encode($result), true);
}
class FixtureBytes { public function __construct(public $bytes) {} }
function cbor_head($major, $value) {
    if ($value < 24) { return chr(($major << 5) | $value); }
    if ($value < 256) { return chr(($major << 5) | 24) . chr($value); }
    return chr(($major << 5) | 25) . pack('n', $value);
}
function cbor($value) {
    if ($value instanceof FixtureBytes) { return cbor_head(2, strlen($value->bytes)) . $value->bytes; }
    if (is_int($value)) { return $value >= 0 ? cbor_head(0, $value) : cbor_head(1, -1 - $value); }
    if (is_string($value)) { return cbor_head(3, strlen($value)) . $value; }
    $map = (array) $value; $encoded = cbor_head(5, count($map));
    foreach ($map as $key => $item) { $encoded .= cbor($key) . cbor($item); }
    return $encoded;
}
function fixture($backup = true) {
    $key = openssl_pkey_new(array('private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'));
    return array('key' => $key, 'id' => random_bytes(32), 'backup' => $backup);
}
function client_data($options, $type, $overrides = array()) {
    return json_encode(array_merge(array('type' => $type, 'challenge' => $options['publicKey']['challenge'],
        'origin' => 'https://teetimenexus.com', 'crossOrigin' => false), $overrides));
}
function creation($fixture, $options, $overrides = array(), $flags = null, $rp = 'teetimenexus.com') {
    $details = openssl_pkey_get_details($fixture['key']);
    $cose = cbor(array(1 => 2, 3 => -7, -1 => 1, -2 => new FixtureBytes($details['ec']['x']), -3 => new FixtureBytes($details['ec']['y'])));
    $flags = $flags ?? ($fixture['backup'] ? 0x5d : 0x45);
    $auth = hash('sha256', $rp, true) . chr($flags) . pack('N', 0) . str_repeat("\0", 16) .
        pack('n', strlen($fixture['id'])) . $fixture['id'] . $cose;
    return array('id' => ttn_passkey_encode($fixture['id']), 'rawId' => ttn_passkey_encode($fixture['id']), 'type' => 'public-key',
        'response' => array(
            'clientDataJSON' => ttn_passkey_encode(client_data($options, 'webauthn.create', $overrides)),
            'attestationObject' => ttn_passkey_encode(cbor(array('fmt' => 'none', 'attStmt' => new stdClass(), 'authData' => new FixtureBytes($auth)))),
        ));
}
function assertion($fixture, $options, $user = 13, $counter = 0, $overrides = array(), $flags = null, $rp = 'teetimenexus.com') {
    $flags = $flags ?? ($fixture['backup'] ? 0x1d : 0x05);
    $auth = hash('sha256', $rp, true) . chr($flags) . pack('N', $counter);
    $client = client_data($options, 'webauthn.get', $overrides);
    openssl_sign($auth . hash('sha256', $client, true), $signature, $fixture['key'], OPENSSL_ALGO_SHA256);
    return array('id' => ttn_passkey_encode($fixture['id']), 'rawId' => ttn_passkey_encode($fixture['id']), 'type' => 'public-key',
        'response' => array('clientDataJSON' => ttn_passkey_encode($client), 'authenticatorData' => ttn_passkey_encode($auth),
            'signature' => ttn_passkey_encode($signature), 'userHandle' => ttn_passkey_user_handle($user)));
}
function verify_login($fixture, $counter = 0, $overrides = array(), $flags = null, $rp = 'teetimenexus.com') {
    $options = options();
    return dispatch('login', array('challenge_id' => $options['challenge_id'],
        'credential' => assertion($fixture, $options, 13, $counter, $overrides, $flags, $rp)));
}
function enroll($fixture, $user = 13) {
    $options = options('register', $user);
    return dispatch('register', array('ttn_auth_user' => new WP_User($user), 'challenge_id' => $options['challenge_id'],
        'credential' => creation($fixture, $options), 'label' => 'Test passkey'));
}

$hooks['rest_api_init'][0]();
expect(count($routes) === 6, 'All routes registered');
foreach ($routes as $path => $route) {
    expect($route['permission_callback'] === (str_contains($path, '/login/') ? '__return_true' : 'ttn_jwt_authenticate_request'), "Wrong permission: $path");
}
expect(ttn_passkey_association('/.well-known/apple-app-site-association')['webcredentials']['apps'] === array('437X3V3YF8.com.teetimenexus.app'), 'Apple team association');
expect(ttn_passkey_association('/.well-known/assetlinks.json') === array(), 'Android disabled without signer');
rejected(dispatch('login-options', array('platform' => 'android')), 'Unconfigured Android', 503);
rejected(dispatch('login-options', array('platform' => 'web')), 'Unsupported platform');
$create = options('register');
expect($create['publicKey']['authenticatorSelection']['userVerification'] === 'required' &&
    $create['publicKey']['authenticatorSelection']['residentKey'] === 'required' &&
    $create['publicKey']['pubKeyCredParams'][0]['alg'] === -7, 'Strong registration options');
$synced = fixture();
expect(enroll($synced) === array('registered' => true), 'Real ES256/CBOR enrollment');
rejected(enroll($synced), 'Duplicate credential', 409);
rejected(enroll($synced, 14), 'Credential cannot belong to two accounts', 409);
$listed = dispatch('list', array('ttn_auth_user' => new WP_User()));
expect(count($listed['passkeys']) === 1 && !isset($listed['passkeys'][0]['public_key']), 'List hides key material');
expect(count(dispatch('list', array('ttn_auth_user' => new WP_User(14)))['passkeys']) === 0, 'List ownership');
$id = $listed['passkeys'][0]['id'];
rejected(dispatch('remove', array('id' => $id, 'ttn_auth_user' => new WP_User(14))), 'Revoke ownership', 404);
$login_options = options();
$credential = assertion($synced, $login_options);
$params = array('challenge_id' => $login_options['challenge_id'], 'credential' => $credential);
$result = dispatch('login', $params);
expect(!is_wp_error($result) && $result['user']['id'] === 13 && $result['token_type'] === 'Bearer', 'Verified login issues existing session tokens');
rejected(dispatch('login', $params), 'Challenge replay');
expect(!is_wp_error(verify_login($synced, 10)) && !is_wp_error(verify_login($synced, 1)) &&
    !is_wp_error(verify_login($synced, 0)), 'Synced counter regression/zero allowed with valid signatures');
$unknown = fixture();
rejected(verify_login($unknown), 'Unknown credential');
foreach (array(
    array('origin' => 'https://teetimenexus.com.evil.test'),
    array('origin' => 'https://sub.teetimenexus.com'),
    array('origin' => 'http://teetimenexus.com'),
    array('origin' => 'https://teetimenexus.com:443'),
    array('crossOrigin' => true), array('topOrigin' => 'https://teetimenexus.com'),
    array('challenge' => ttn_passkey_encode(random_bytes(32))), array('type' => 'webauthn.create'),
) as $override) { rejected(verify_login($synced, 0, $override), 'Reject client-data tampering'); }
rejected(verify_login($synced, 0, array(), 0x19), 'User verification required');
rejected(verify_login($synced, 0, array(), 0x1c), 'User presence required');
rejected(verify_login($synced, 0, array(), 0x05), 'Backup eligibility cannot change');
rejected(verify_login($synced, 0, array(), null, 'evil.test'), 'RP ID required');
foreach (array('missing-handle', 'wrong-handle', 'signature', 'rawId', 'truncated', 'bad-encoding') as $case) {
    $options = options(); $credential = assertion($synced, $options);
    if ($case === 'missing-handle') { unset($credential['response']['userHandle']); }
    if ($case === 'wrong-handle') { $credential['response']['userHandle'] = ttn_passkey_user_handle(14); }
    if ($case === 'signature') { $credential['response']['signature'] = ttn_passkey_encode(random_bytes(64)); }
    if ($case === 'rawId') { $credential['rawId'] = ttn_passkey_encode(random_bytes(32)); }
    if ($case === 'truncated') { $credential['response']['authenticatorData'] = 'AA'; }
    if ($case === 'bad-encoding') { $credential['response']['clientDataJSON'] = 'a==='; }
    rejected(dispatch('login', array('challenge_id' => $options['challenge_id'], 'credential' => $credential)), $case);
}
$options = options(); $wpdb->query('UPDATE wp_ttn_passkey_challenges SET expires_at = 1');
rejected(dispatch('login', array('challenge_id' => $options['challenge_id'], 'credential' => assertion($synced, $options))), 'Expired challenge');
$options = options('register');
rejected(dispatch('register', array('ttn_auth_user' => new WP_User(14), 'challenge_id' => $options['challenge_id'],
    'credential' => creation(fixture(), $options), 'label' => 'Wrong account')), 'Challenge account binding');
$options = options('register');
rejected(dispatch('login', array('challenge_id' => $options['challenge_id'], 'credential' => assertion($synced, $options))), 'Challenge purpose binding');
foreach (array('uv', 'rp', 'id', 'attestation', 'label') as $case) {
    $options = options('register'); $fixture = fixture();
    $credential = creation($fixture, $options, array(), $case === 'uv' ? 0x59 : null, $case === 'rp' ? 'evil.test' : 'teetimenexus.com');
    if ($case === 'id') { $credential['id'] = $credential['rawId'] = ttn_passkey_encode(random_bytes(32)); }
    if ($case === 'attestation') { $credential['response']['attestationObject'] = 'AA'; }
    rejected(dispatch('register', array('ttn_auth_user' => new WP_User(), 'challenge_id' => $options['challenge_id'],
        'credential' => $credential, 'label' => $case === 'label' ? '' : 'Test')), "Registration $case");
}
$device = fixture(false);
expect(!is_wp_error(enroll($device)) && !is_wp_error(verify_login($device, 1)), 'Device-bound counter accepted');
rejected(verify_login($device, 1), 'Device-bound counter replay');
rejected(verify_login($device, 0), 'Device-bound counter reset');
$wpdb->before_update = function () use ($wpdb, $device) {
    $wpdb->query($wpdb->prepare('UPDATE wp_ttn_passkeys SET sign_count = 2, last_used_at = %d WHERE id_hash = %s',
        time(), hash('sha256', $device['id'])));
};
rejected(verify_login($device, 2), 'Parallel counter conflict rejected');
expect(dispatch('remove', array('ttn_auth_user' => new WP_User(), 'id' => $id)) === array('removed' => true), 'Owned revoke');
rejected(verify_login($synced), 'Revoked credential cannot log in');
rejected(dispatch('remove', array('ttn_auth_user' => new WP_User(), 'id' => $id)), 'Missing revoke', 404);
$zero = fixture();
expect(!is_wp_error(enroll($zero)), 'Zero counter enrollment');
expect(!is_wp_error(verify_login($zero)), 'First zero-counter login');
$wpdb->force_noop = true;
expect(!is_wp_error(verify_login($zero)), 'MySQL unchanged row still exists');
$GLOBALS['rate'] = array();
$GLOBALS['transients'] = array();
for ($i = 0; $i < 30; $i++) {
    expect(!is_wp_error(dispatch('login-options', array('platform' => 'ios'), false)), 'Prompt opening/cancellation is not a failed login');
}
rejected(dispatch('login-options', array('platform' => 'ios'), false), 'Challenge issuance rate limit', 429);
expect($GLOBALS['rate'] === array(), 'Options do not increment failed verifications');
foreach ($GLOBALS['transients'] as &$window) { $window['expires_at'] = time() - 1; }
unset($window);
expect(!is_wp_error(dispatch('login-options', array('platform' => 'ios'), false)), 'Options lock expires after one minute');
$GLOBALS['rate'] = array('passkey-options' => 5, 'passkey-verify' => 5);
expect(!is_wp_error(dispatch('login-options', array('platform' => 'ios'), false)), 'Legacy setup lock does not block new options');
$fresh = dispatch('login-options', array('platform' => 'ios'), false);
expect(!is_wp_error(dispatch('login', array('challenge_id' => $fresh['challenge_id'],
    'credential' => assertion($zero, json_decode(json_encode($fresh), true))), false)), 'Legacy setup lock does not block verified login');
$GLOBALS['rate'] = array();
for ($i = 0; $i < 5; $i++) { dispatch('login', array('challenge_id' => 'invalid'), false); }
rejected(dispatch('login', array('challenge_id' => 'invalid'), false), 'Verification rate limit', 429);
expect(!is_wp_error(dispatch('register-options', array('platform' => 'ios', 'ttn_auth_user' => new WP_User(14)), false)),
    'Login verification lock does not block signed-in enrollment');
$GLOBALS['rate'] = array();
$GLOBALS['transients'] = array();
$fresh = dispatch('login-options', array('platform' => 'ios'), false);
dispatch('login', array('challenge_id' => 'invalid'), false);
expect(!is_wp_error(dispatch('login', array('challenge_id' => $fresh['challenge_id'],
    'credential' => assertion($zero, json_decode(json_encode($fresh), true))), false)), 'Successful verification clears prior failures');
expect(!isset($GLOBALS['rate']['passkey-verification-v2-login']), 'Success clears only verification failures');
expect(array_values($GLOBALS['transients'])[0]['count'] === 1, 'Success does not reset issuance abuse limit');

define('TTN_PASSKEY_ANDROID_SHA256', array(implode(':', array_fill(0, 32, 'AB'))));
$association = ttn_passkey_association('/.well-known/assetlinks.json');
expect($association[0]['target']['package_name'] === 'com.teetimenexus.app' &&
    $association[0]['relation'] === array('delegate_permission/common.get_login_creds'), 'Android credential association');
$options = options('login', 13, 'android');
$credential = assertion($zero, $options, 13, 0, array('origin' => 'android:apk-key-hash:' . ttn_passkey_android_hashes()[0]));
expect(!is_wp_error(dispatch('login', array('challenge_id' => $options['challenge_id'], 'credential' => $credential))), 'Configured Android origin accepted');
$options = options('login', 13, 'android');
rejected(dispatch('login', array('challenge_id' => $options['challenge_id'], 'credential' => assertion($zero, $options))), 'Platform binding');
$options = options('login', 13, 'android');
rejected(dispatch('login', array('challenge_id' => $options['challenge_id'], 'credential' => assertion($zero, $options, 13, 0,
    array('origin' => 'android:apk-key-hash:' . ttn_passkey_encode(random_bytes(32)))))), 'Unapproved Android signer');
while (count(ttn_passkey_records(13)) < 10) { expect(!is_wp_error(enroll(fixture())), 'Fill allowed passkeys'); }
rejected(dispatch('register-options', array('platform' => 'ios', 'ttn_auth_user' => new WP_User())), 'Credential limit', 409);
echo "Mobile passkeys: cryptographic enrollment/login, replay/expiry/binding, origin/RP/UV/signature, counters, ownership/revocation, limits and associations passed.\n";
