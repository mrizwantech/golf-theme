<?php
if (!defined('ABSPATH')) {
    exit;
}

function ttn_passkey_error($message, $status = 400) {
    return new WP_Error('ttn_passkey_error', $message, array('status' => $status));
}

function ttn_passkey_encode($bytes) {
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

function ttn_passkey_decode($value, $max = 16384) {
    if (!is_string($value) || $value === '' || strlen($value) > $max ||
        !preg_match('/^[A-Za-z0-9_-]+$/D', $value)) {
        throw new InvalidArgumentException('Invalid passkey response. Please try again.');
    }
    $bytes = base64_decode(strtr($value, '-_', '+/'), true);
    if ($bytes === false || ttn_passkey_encode($bytes) !== $value) {
        throw new InvalidArgumentException('Invalid passkey encoding. Please try again.');
    }
    return $bytes;
}

function ttn_passkey_android_fingerprints() {
    $values = defined('TTN_PASSKEY_ANDROID_SHA256') ? TTN_PASSKEY_ANDROID_SHA256 : array();
    if (!is_array($values)) {
        throw new RuntimeException('TTN_PASSKEY_ANDROID_SHA256 must be an array of signing fingerprints.');
    }
    $fingerprints = array();
    foreach ($values as $value) {
        if (!is_string($value) || !preg_match('/^(?:[A-Fa-f0-9]{2}:){31}[A-Fa-f0-9]{2}$/D', $value)) {
            throw new RuntimeException('An Android passkey signing fingerprint is invalid.');
        }
        $fingerprints[] = strtoupper($value);
    }
    return array_values(array_unique($fingerprints));
}

function ttn_passkey_android_hashes() {
    return array_map(static function ($value) {
        return ttn_passkey_encode(hex2bin(str_replace(':', '', $value)));
    }, ttn_passkey_android_fingerprints());
}

function ttn_passkey_apple_app_id() {
    $team = defined('TTN_PASSKEY_APPLE_TEAM_ID') ? TTN_PASSKEY_APPLE_TEAM_ID : '437X3V3YF8';
    if (!is_string($team) || !preg_match('/^[A-Z0-9]{10}$/D', $team)) {
        throw new RuntimeException('The Apple passkey Team ID is invalid.');
    }
    return $team . '.com.teetimenexus.app';
}

function ttn_passkey_verifier($platform) {
    if ($platform !== 'ios' && $platform !== 'android') {
        throw new InvalidArgumentException('Passkeys require the iOS or Android app.');
    }
    if ($platform === 'android' && !ttn_passkey_android_hashes()) {
        throw new RuntimeException('Android passkeys are not enabled yet. Please use your password or sign in with Apple where available.');
    }
    if ($platform === 'ios') {
        ttn_passkey_apple_app_id();
    }
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (PHP_VERSION_ID < 80000 || !extension_loaded('openssl') || !extension_loaded('mbstring') ||
        !function_exists('openssl_verify') || !function_exists('openssl_get_curve_names') ||
        !in_array('prime256v1', openssl_get_curve_names(), true) ||
        !file_exists($autoload)) {
        throw new RuntimeException('Passkeys are unavailable on the server. Please use another sign-in method.');
    }
    require_once $autoload;
    $verifier = new \lbuchs\WebAuthn\WebAuthn('Tee Time Nexus', 'teetimenexus.com', array('none'), true);
    $verifier->addAndroidKeyHashes(ttn_passkey_android_hashes());
    return $verifier;
}

function ttn_passkey_tables() {
    global $wpdb;
    return array($wpdb->prefix . 'ttn_passkeys', $wpdb->prefix . 'ttn_passkey_challenges');
}

function ttn_passkey_install() {
    if (get_option('ttn_passkey_schema') === '1') {
        return;
    }
    global $wpdb;
    list($keys, $challenges) = ttn_passkey_tables();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $collate = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE $keys (
        id_hash char(64) NOT NULL,
        credential_id text NOT NULL,
        user_id bigint(20) unsigned NOT NULL,
        slot tinyint unsigned NOT NULL,
        user_handle varchar(86) NOT NULL,
        public_key text NOT NULL,
        sign_count bigint(20) unsigned NOT NULL DEFAULT 0,
        backup_eligible tinyint unsigned NOT NULL DEFAULT 0,
        label varchar(80) NOT NULL,
        created_at bigint(20) unsigned NOT NULL,
        last_used_at bigint(20) unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY  (id_hash),
        UNIQUE KEY user_slot (user_id,slot)
    ) $collate;");
    dbDelta("CREATE TABLE $challenges (
        id_hash char(64) NOT NULL,
        challenge varchar(128) NOT NULL,
        purpose varchar(16) NOT NULL,
        platform varchar(8) NOT NULL,
        user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        user_handle varchar(86) NOT NULL DEFAULT '',
        expires_at bigint(20) unsigned NOT NULL,
        PRIMARY KEY  (id_hash),
        KEY expires_at (expires_at)
    ) $collate;");
    foreach (array($keys, $challenges) as $table) {
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) {
            throw new RuntimeException('Passkey storage could not be initialized.');
        }
    }
    update_option('ttn_passkey_schema', '1', false);
}

function ttn_passkey_save_challenge($verifier, $purpose, $platform, $user_id, $user_handle = '') {
    global $wpdb;
    list(, $table) = ttn_passkey_tables();
    $token = ttn_passkey_encode(random_bytes(32));
    if ($wpdb->query($wpdb->prepare("DELETE FROM $table WHERE expires_at < %d", time())) === false ||
        $wpdb->insert($table, array(
            'id_hash' => hash('sha256', $token),
            'challenge' => ttn_passkey_encode($verifier->getChallenge()->getBinaryString()),
            'purpose' => $purpose,
            'platform' => $platform,
            'user_id' => $user_id,
            'user_handle' => $user_handle,
            'expires_at' => time() + 120,
        )) !== 1) {
        throw new RuntimeException('Unable to start passkey verification.');
    }
    return $token;
}

function ttn_passkey_consume_challenge($token, $purpose, $user_id) {
    global $wpdb;
    ttn_passkey_decode($token, 43);
    list(, $table) = ttn_passkey_tables();
    $hash = hash('sha256', $token);
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id_hash = %s", $hash), ARRAY_A);
    if ($wpdb->last_error) {
        throw new RuntimeException('Unable to read passkey verification.');
    }
    // Only one request can delete the challenge successfully, including parallel replays.
    if (!$row || $wpdb->delete($table, array('id_hash' => $hash)) !== 1 ||
        (int) $row['expires_at'] <= time() || $row['purpose'] !== $purpose ||
        (int) $row['user_id'] !== $user_id) {
        throw new InvalidArgumentException('Passkey verification expired or was already used. Please try again.');
    }
    return $row;
}

function ttn_passkey_user_handle($user_id) {
    $handle = get_user_meta($user_id, 'ttn_passkey_handle', true);
    if (!$handle) {
        $new_handle = ttn_passkey_encode(random_bytes(32));
        add_user_meta($user_id, 'ttn_passkey_handle', $new_handle, true);
        $handle = get_user_meta($user_id, 'ttn_passkey_handle', true);
    }
    if (!is_string($handle) || strlen(ttn_passkey_decode($handle, 43)) !== 32) {
        throw new RuntimeException('Unable to initialize the account passkey handle.');
    }
    return $handle;
}

function ttn_passkey_client_data($credential, $platform) {
    if (!is_array($credential) || !is_array($credential['response'] ?? null) ||
        (($credential['type'] ?? 'public-key') !== 'public-key')) {
        throw new InvalidArgumentException('Invalid passkey response.');
    }
    $json = ttn_passkey_decode($credential['response']['clientDataJSON'] ?? null, 8192);
    $data = json_decode($json, true);
    $origins = $platform === 'ios' ? array('https://teetimenexus.com') :
        array_map(static function ($hash) { return 'android:apk-key-hash:' . $hash; }, ttn_passkey_android_hashes());
    if (!is_array($data) || !in_array($data['origin'] ?? null, $origins, true) ||
        (array_key_exists('crossOrigin', $data) && $data['crossOrigin'] !== false) ||
        array_key_exists('topOrigin', $data)) {
        throw new InvalidArgumentException('The passkey app or website association could not be verified.');
    }
    return $json;
}

function ttn_passkey_credential_id($credential) {
    $id = ttn_passkey_decode($credential['id'] ?? null, 2048);
    if (isset($credential['rawId']) && !hash_equals($id, ttn_passkey_decode($credential['rawId'], 2048))) {
        throw new InvalidArgumentException('Invalid passkey credential ID.');
    }
    return $id;
}

function ttn_passkey_records($user_id) {
    global $wpdb;
    list($table) = ttn_passkey_tables();
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d ORDER BY created_at, slot", $user_id), ARRAY_A);
    if (!is_array($rows) || $wpdb->last_error) {
        throw new RuntimeException('Unable to load your passkeys.');
    }
    return $rows;
}

function ttn_passkey_options($request, $purpose) {
    $platform = $request->get_param('platform');
    $verifier = ttn_passkey_verifier($platform);
    ttn_passkey_install();
    $user = $purpose === 'register' ? $request->get_param('ttn_auth_user') : null;
    $user_id = $user ? (int) $user->ID : 0;
    $user_handle = $user ? ttn_passkey_user_handle($user_id) : '';
    if ($user) {
        $rows = ttn_passkey_records($user_id);
        if (count($rows) >= 10) {
            return ttn_passkey_error('You can save up to 10 passkeys. Remove one before adding another.', 409);
        }
        $exclude = array_map(static function ($row) { return ttn_passkey_decode($row['credential_id'], 2048); }, $rows);
        $options = $verifier->getCreateArgs(
            ttn_passkey_decode($user_handle, 43),
            $user->user_email, $user->display_name, 60, true, true, false, $exclude
        )->publicKey;
        $options->pubKeyCredParams = array((object) array('type' => 'public-key', 'alg' => -7));
    } else {
        $options = $verifier->getGetArgs(array(), 60, true, true, true, true, true, true)->publicKey;
    }
    return array(
        'challenge_id' => ttn_passkey_save_challenge($verifier, $purpose, $platform, $user_id, $user_handle),
        'publicKey' => $options,
    );
}

function ttn_passkey_register($request) {
    global $wpdb;
    $user = $request->get_param('ttn_auth_user');
    $user_id = (int) $user->ID;
    $challenge = ttn_passkey_consume_challenge($request->get_param('challenge_id'), 'register', $user_id);
    $verifier = ttn_passkey_verifier($challenge['platform']);
    $credential = $request->get_param('credential');
    $json = ttn_passkey_client_data($credential, $challenge['platform']);
    $id = ttn_passkey_credential_id($credential);
    $data = $verifier->processCreate(
        $json, ttn_passkey_decode($credential['response']['attestationObject'] ?? null, 32768),
        ttn_passkey_decode($challenge['challenge']), true, true
    );
    if (!hash_equals($id, $data->credentialId) || !$data->userVerified ||
        ($data->isBackedUp && !$data->isBackupEligible)) {
        throw new InvalidArgumentException('The passkey could not be verified.');
    }
    $key = openssl_pkey_get_public($data->credentialPublicKey);
    $details = $key ? openssl_pkey_get_details($key) : false;
    if (!$details || $details['type'] !== OPENSSL_KEYTYPE_EC ||
        ($details['ec']['curve_name'] ?? '') !== 'prime256v1') {
        throw new InvalidArgumentException('This passkey uses an unsupported algorithm.');
    }
    $label = $request->get_param('label');
    if (!is_string($label)) {
        throw new InvalidArgumentException('Enter a passkey name of 1 to 80 characters.');
    }
    $label = sanitize_text_field($label);
    if (mb_strlen($label) < 1 || mb_strlen($label) > 80) {
        throw new InvalidArgumentException('Enter a passkey name of 1 to 80 characters.');
    }
    $rows = ttn_passkey_records($user_id);
    $slots = array_map(static function ($row) { return (int) $row['slot']; }, $rows);
    $available = array_values(array_diff(range(1, 10), $slots));
    if (!$available) {
        return ttn_passkey_error('Remove a passkey before adding another.', 409);
    }
    list($table) = ttn_passkey_tables();
    $previous = $wpdb->suppress_errors(true);
    $inserted = $wpdb->insert($table, array(
        'id_hash' => hash('sha256', $id),
        'credential_id' => ttn_passkey_encode($id),
        'user_id' => $user_id,
        'slot' => $available[0],
        'user_handle' => $challenge['user_handle'],
        'public_key' => $data->credentialPublicKey,
        'sign_count' => (int) $data->signatureCounter,
        'backup_eligible' => $data->isBackupEligible ? 1 : 0,
        'label' => $label,
        'created_at' => time(),
        'last_used_at' => 0,
    ));
    $wpdb->suppress_errors($previous);
    if ($inserted !== 1) {
        $conflict = $wpdb->get_var($wpdb->prepare(
            "SELECT id_hash FROM $table WHERE id_hash = %s OR (user_id = %d AND slot = %d)",
            hash('sha256', $id), $user_id, $available[0]
        ));
        if ($conflict) {
            return ttn_passkey_error('This passkey is already registered or your passkey list changed. Reload before retrying.', 409);
        }
        throw new RuntimeException('Unable to save your passkey. Please try again.');
    }
    return array('registered' => true);
}

function ttn_passkey_login($request) {
    global $wpdb;
    $challenge = ttn_passkey_consume_challenge($request->get_param('challenge_id'), 'login', 0);
    $verifier = ttn_passkey_verifier($challenge['platform']);
    $credential = $request->get_param('credential');
    $json = ttn_passkey_client_data($credential, $challenge['platform']);
    $id = ttn_passkey_credential_id($credential);
    list($table) = ttn_passkey_tables();
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id_hash = %s", hash('sha256', $id)), ARRAY_A);
    if ($wpdb->last_error) {
        throw new RuntimeException('Unable to load the passkey.');
    }
    if (!$row || !hash_equals(ttn_passkey_decode($row['user_handle'], 43),
        ttn_passkey_decode($credential['response']['userHandle'] ?? null, 86))) {
        throw new InvalidArgumentException('This passkey is not recognized. Please use another sign-in method.');
    }
    $auth_data = ttn_passkey_decode($credential['response']['authenticatorData'] ?? null);
    if (strlen($auth_data) < 37) {
        throw new InvalidArgumentException('Invalid passkey authenticator response.');
    }
    $flags = ord($auth_data[32]);
    $backup_eligible = ($flags & 0x08) !== 0;
    if ($backup_eligible !== (bool) $row['backup_eligible'] || (($flags & 0x10) && !$backup_eligible)) {
        throw new InvalidArgumentException('Invalid passkey backup state.');
    }
    // Synced passkeys can keep a zero/non-monotonic counter. Device-bound keys cannot.
    $verifier->processGet(
        $json, $auth_data, ttn_passkey_decode($credential['response']['signature'] ?? null, 2048),
        $row['public_key'], ttn_passkey_decode($challenge['challenge']),
        $backup_eligible ? null : (int) $row['sign_count'], true, true
    );
    $user = get_user_by('id', (int) $row['user_id']);
    if (!$user) {
        throw new InvalidArgumentException('The passkey account no longer exists.');
    }
    $counter = max((int) $row['sign_count'], (int) $verifier->getSignatureCounter());
    $now = time();
    $updated = $wpdb->query($wpdb->prepare(
        "UPDATE $table SET sign_count = %d, last_used_at = %d WHERE id_hash = %s AND sign_count = %d",
        $counter, $now, $row['id_hash'], (int) $row['sign_count']
    ));
    if ($updated === false) {
        throw new RuntimeException('Unable to update your passkey. Please try again.');
    }
    if ($updated === 0) {
        // MySQL reports zero for an unchanged counter used twice in the same second.
        $unchanged = $counter === (int) $row['sign_count'] && $wpdb->get_var($wpdb->prepare(
            "SELECT id_hash FROM $table WHERE id_hash = %s AND sign_count = %d AND last_used_at = %d",
            $row['id_hash'], $counter, $now
        ));
        if ($wpdb->last_error) {
            throw new RuntimeException('Unable to read your passkey. Please try again.');
        }
        if (!$unchanged) {
            throw new InvalidArgumentException('The passkey changed or was removed. Please try again.');
        }
    }
    $tokens = ttn_jwt_issue_tokens($user);
    if (is_wp_error($tokens)) {
        return $tokens;
    }
    $tokens['user'] = array('id' => $user->ID, 'email' => $user->user_email, 'display_name' => $user->display_name);
    return $tokens;
}

function ttn_passkey_list($request) {
    ttn_passkey_install();
    return array('passkeys' => array_map(static function ($row) {
        return array(
            'id' => $row['id_hash'],
            'label' => $row['label'],
            'created_at' => gmdate('c', (int) $row['created_at']),
            'last_used_at' => $row['last_used_at'] ? gmdate('c', (int) $row['last_used_at']) : null,
        );
    }, ttn_passkey_records((int) $request->get_param('ttn_auth_user')->ID)));
}

function ttn_passkey_remove($request) {
    global $wpdb;
    ttn_passkey_install();
    $id = $request->get_param('id');
    if (!is_string($id) || !preg_match('/^[a-f0-9]{64}$/D', $id)) {
        throw new InvalidArgumentException('Invalid passkey ID.');
    }
    list($table) = ttn_passkey_tables();
    $removed = $wpdb->delete($table, array('id_hash' => $id, 'user_id' => (int) $request->get_param('ttn_auth_user')->ID));
    if ($removed === false) {
        throw new RuntimeException('Unable to remove the passkey.');
    }
    if ($removed !== 1) {
        return ttn_passkey_error('This passkey was not found on your account.', 404);
    }
    return array('removed' => true);
}

function ttn_passkey_rate_scope($request, $operation) {
    $register = strpos($operation, 'register') === 0;
    $user = $register ? $request->get_param('ttn_auth_user') : null;
    return ($register ? 'register-' . (int) $user->ID : 'login');
}

function ttn_passkey_allow_options($scope) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $key = 'ttn_passkey_options_v2_' . md5($scope . '|' . $ip);
    $now = time();
    $window = get_transient($key);
    if (!is_array($window) || ($window['expires_at'] ?? 0) <= $now) {
        $window = array('count' => 0, 'expires_at' => $now + 60);
    }
    if ($window['count'] >= 30) {
        return false;
    }
    $window['count']++;
    set_transient($key, $window, max(1, $window['expires_at'] - $now));
    return true;
}

function ttn_passkey_dispatch($request, $operation) {
    $is_options = strpos($operation, 'options') !== false;
    $is_verify = $operation === 'login' || $operation === 'register';
    $rate_key = 'passkey-verification-v2-' . ttn_passkey_rate_scope($request, $operation);
    if ($is_options && !ttn_passkey_allow_options(ttn_passkey_rate_scope($request, $operation))) {
        return ttn_passkey_error('Too many passkey requests. Wait one minute before trying again, or use another sign-in method.', 429);
    }
    if ($is_verify) {
        if (ttn_jwt_is_rate_limited($rate_key)) {
            return ttn_passkey_error('Too many failed passkey verifications. Try again in 15 minutes or use another sign-in method.', 429);
        }
    }
    try {
        if (strlen($request->get_body()) > 48000) {
            throw new InvalidArgumentException('The passkey response is too large.');
        }
        if ($operation === 'register-options' || $operation === 'login-options') {
            $result = ttn_passkey_options($request, $operation === 'register-options' ? 'register' : 'login');
        } else {
            $callback = 'ttn_passkey_' . $operation;
            $result = $callback($request);
        }
        if ($is_verify && !is_wp_error($result)) {
            ttn_jwt_clear_failed_attempts($rate_key);
        }
        return $result;
    } catch (\lbuchs\WebAuthn\WebAuthnException $error) {
        if ($is_verify) ttn_jwt_register_failed_attempt($rate_key);
        return ttn_passkey_error('Passkey verification failed. Please try again or use another sign-in method.');
    } catch (InvalidArgumentException $error) {
        if ($is_verify) ttn_jwt_register_failed_attempt($rate_key);
        return ttn_passkey_error($error->getMessage());
    } catch (RuntimeException $error) {
        error_log('TTN passkeys: ' . $error->getMessage());
        return ttn_passkey_error($error->getMessage(), 503);
    }
}

add_action('rest_api_init', static function () {
    $routes = array(
        '/auth/passkeys/register/options' => array('POST', 'register-options', true),
        '/auth/passkeys/register/verify' => array('POST', 'register', true),
        '/auth/passkeys/login/options' => array('POST', 'login-options', false),
        '/auth/passkeys/login/verify' => array('POST', 'login', false),
        '/auth/passkeys' => array('GET', 'list', true),
        '/auth/passkeys/(?P<id>[a-f0-9]{64})' => array('DELETE', 'remove', true),
    );
    foreach ($routes as $path => $route) {
        register_rest_route('ttn/v1', $path, array(
            'methods' => $route[0],
            'permission_callback' => $route[2] ? 'ttn_jwt_authenticate_request' : '__return_true',
            'callback' => static function ($request) use ($route) {
                return ttn_passkey_dispatch($request, $route[1]);
            },
        ));
    }
});

add_filter('rest_post_dispatch', static function ($response, $server, $request) {
    if (strpos($request->get_route(), '/ttn/v1/auth/passkeys') === 0) {
        $response->header('Cache-Control', 'private, no-store');
        $response->header('Vary', 'Authorization');
    }
    return $response;
}, 10, 3);

function ttn_passkey_association($path) {
    if ($path === '/.well-known/apple-app-site-association') {
        return array('webcredentials' => array('apps' => array(ttn_passkey_apple_app_id())));
    }
    if ($path === '/.well-known/assetlinks.json') {
        $fingerprints = ttn_passkey_android_fingerprints();
        return $fingerprints ? array(array(
            'relation' => array('delegate_permission/common.get_login_creds'),
            'target' => array(
                'namespace' => 'android_app',
                'package_name' => 'com.teetimenexus.app',
                'sha256_cert_fingerprints' => $fingerprints,
            ),
        )) : array();
    }
    return null;
}

// Run before canonical redirects; the host must forward .well-known requests to WordPress.
add_action('init', static function () {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (!in_array($path, array('/.well-known/apple-app-site-association', '/.well-known/assetlinks.json'), true)) {
        return;
    }
    try {
        $association = ttn_passkey_association($path);
        status_header(200);
        header('Content-Type: application/json');
        header('Cache-Control: public, max-age=3600');
        echo wp_json_encode($association);
    } catch (RuntimeException $error) {
        error_log('TTN passkey association: ' . $error->getMessage());
        status_header(503);
        header('Content-Type: application/json');
        echo wp_json_encode(array('message' => 'Passkey association is not configured correctly.'));
    }
    exit;
}, 0);
