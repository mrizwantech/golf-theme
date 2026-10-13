<?php
/**
 * Stateless JWT authentication for the mobile app (login/refresh/logout + Bearer token checks).
 * No external libraries required — tokens are standard HS256 JWTs signed with TTN_JWT_SECRET.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TTN_JWT_ACCESS_TTL', 15 * MINUTE_IN_SECONDS);
define('TTN_JWT_REFRESH_TTL', 30 * DAY_IN_SECONDS);
define('TTN_JWT_MAX_LOGIN_ATTEMPTS', 5);
define('TTN_JWT_LOGIN_LOCKOUT', 15 * MINUTE_IN_SECONDS);

function ttn_jwt_password_meets_policy($password) {
    $password = (string) $password;
    return strlen($password) >= 8
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/[0-9]/', $password)
        && preg_match('/[^A-Za-z0-9\s]/', $password);
}

function ttn_jwt_password_policy_message() {
    return __('Password must be at least 8 characters and include an uppercase letter, a lowercase letter, a number, and a symbol.', 'tee-time-nexus-bookings');
}

function ttn_jwt_validate_password_reset($errors, $user) {
    $password = (string) wp_unslash($_POST['pass1'] ?? '');
    if ($password !== '' && !ttn_jwt_password_meets_policy($password)) {
        $errors->add('ttn_password_policy', ttn_jwt_password_policy_message());
    }
}
add_action('validate_password_reset', 'ttn_jwt_validate_password_reset', 10, 2);

function ttn_jwt_validate_woocommerce_registration($errors, $username, $password) {
    if ($password !== '' && !ttn_jwt_password_meets_policy($password)) {
        $errors->add('ttn_password_policy', ttn_jwt_password_policy_message());
    }
    return $errors;
}
add_filter('woocommerce_registration_errors', 'ttn_jwt_validate_woocommerce_registration', 10, 3);

/**
 * Fetches the signing secret. Refuses to run with a weak/default/missing secret.
 */
function ttn_jwt_get_secret() {
    if (!defined('TTN_JWT_SECRET') || strlen(TTN_JWT_SECRET) < 32) {
        return new WP_Error(
            'ttn_jwt_misconfigured',
            'Server auth is misconfigured. Define a 32+ character TTN_JWT_SECRET in wp-config.php.',
            array('status' => 500)
        );
    }
    return TTN_JWT_SECRET;
}

function ttn_jwt_base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function ttn_jwt_base64url_decode($data) {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

/**
 * Builds and signs a JWT for the given claims.
 */
function ttn_jwt_encode(array $claims) {
    $secret = ttn_jwt_get_secret();
    if (is_wp_error($secret)) {
        return $secret;
    }

    $header = ttn_jwt_base64url_encode(wp_json_encode(array('alg' => 'HS256', 'typ' => 'JWT')));
    $payload = ttn_jwt_base64url_encode(wp_json_encode($claims));
    $signature = ttn_jwt_base64url_encode(hash_hmac('sha256', "$header.$payload", $secret, true));

    return "$header.$payload.$signature";
}

/**
 * Verifies signature + expiry and returns the decoded claims.
 */
function ttn_jwt_decode($token) {
    $secret = ttn_jwt_get_secret();
    if (is_wp_error($secret)) {
        return $secret;
    }

    $parts = is_string($token) ? explode('.', $token) : array();
    if (count($parts) !== 3) {
        return new WP_Error('ttn_jwt_invalid', 'Malformed token.', array('status' => 401));
    }

    list($header, $payload, $signature) = $parts;
    $expected_signature = ttn_jwt_base64url_encode(hash_hmac('sha256', "$header.$payload", $secret, true));

    if (!hash_equals($expected_signature, $signature)) {
        return new WP_Error('ttn_jwt_invalid', 'Invalid token signature.', array('status' => 401));
    }

    $claims = json_decode(ttn_jwt_base64url_decode($payload), true);
    if (!is_array($claims) || empty($claims['exp']) || empty($claims['sub'])) {
        return new WP_Error('ttn_jwt_invalid', 'Invalid token payload.', array('status' => 401));
    }

    if (time() >= (int) $claims['exp']) {
        return new WP_Error('ttn_jwt_expired', 'Token expired.', array('status' => 401));
    }

    return $claims;
}

/**
 * Reads the Bearer token from the Authorization header (falls back to getallheaders for servers that strip it).
 */
function ttn_jwt_get_bearer_token() {
    $auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? wp_unslash($_SERVER['HTTP_AUTHORIZATION']) : '';

    if (!$auth_header && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strtolower($name) === 'authorization') {
                $auth_header = $value;
                break;
            }
        }
    }

    if (preg_match('/Bearer\s+(\S+)/i', $auth_header, $matches)) {
        return $matches[1];
    }

    return '';
}

/**
 * Reusable permission_callback for protected REST routes that require a valid access token.
 * On success, sets the current WP user so capability checks and current_user_can() keep working.
 */
function ttn_jwt_authenticate_request(WP_REST_Request $request) {
    $token = ttn_jwt_get_bearer_token();
    if (!$token) {
        return new WP_Error('ttn_jwt_missing', 'Authorization Bearer token required.', array('status' => 401));
    }

    $claims = ttn_jwt_decode($token);
    if (is_wp_error($claims)) {
        return $claims;
    }

    if (($claims['type'] ?? '') !== 'access') {
        return new WP_Error('ttn_jwt_invalid', 'A valid access token is required.', array('status' => 401));
    }

    $user = get_user_by('id', (int) $claims['sub']);
    if (!$user) {
        return new WP_Error('ttn_jwt_invalid', 'User no longer exists.', array('status' => 401));
    }

    wp_set_current_user($user->ID);
    $request->set_param('ttn_auth_user', $user);

    return true;
}

function ttn_jwt_rate_limit_key($identifier) {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    return 'ttn_jwt_fail_' . md5(strtolower($identifier) . '|' . $ip);
}

function ttn_jwt_is_rate_limited($identifier) {
    $count = (int) get_transient(ttn_jwt_rate_limit_key($identifier));
    return $count >= TTN_JWT_MAX_LOGIN_ATTEMPTS;
}

function ttn_jwt_register_failed_attempt($identifier) {
    $key = ttn_jwt_rate_limit_key($identifier);
    $count = (int) get_transient($key);
    set_transient($key, $count + 1, TTN_JWT_LOGIN_LOCKOUT);
}

function ttn_jwt_clear_failed_attempts($identifier) {
    delete_transient(ttn_jwt_rate_limit_key($identifier));
}

function ttn_apple_der_length($length) {
    if ($length < 0x80) {
        return chr($length);
    }

    $bytes = ltrim(pack('N', $length), "\x00");
    return chr(0x80 | strlen($bytes)) . $bytes;
}

function ttn_apple_der_integer($value) {
    $value = ltrim($value, "\x00");
    if ($value === '') {
        return "\x02\x01\x00";
    }
    if ((ord($value[0]) & 0x80) !== 0) {
        $value = "\x00" . $value;
    }

    return "\x02" . ttn_apple_der_length(strlen($value)) . $value;
}

function ttn_apple_jwk_to_pem($modulus, $exponent) {
    $modulus_bytes = ttn_jwt_base64url_decode($modulus);
    $exponent_bytes = ttn_jwt_base64url_decode($exponent);
    if (!is_string($modulus_bytes) || !is_string($exponent_bytes) || $modulus_bytes === '' || $exponent_bytes === '') {
        return new WP_Error('ttn_apple_key_invalid', 'Apple signing key is invalid.', array('status' => 503));
    }

    $rsa_sequence = ttn_apple_der_integer($modulus_bytes) . ttn_apple_der_integer($exponent_bytes);
    $rsa_sequence = "\x30" . ttn_apple_der_length(strlen($rsa_sequence)) . $rsa_sequence;
    $algorithm_identifier = hex2bin('300d06092a864886f70d0101010500');
    $bit_string = "\x03" . ttn_apple_der_length(strlen($rsa_sequence) + 1) . "\x00" . $rsa_sequence;
    $subject_public_key_info = $algorithm_identifier . $bit_string;
    $subject_public_key_info = "\x30" . ttn_apple_der_length(strlen($subject_public_key_info)) . $subject_public_key_info;

    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($subject_public_key_info), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

function ttn_apple_get_signing_key($key_id) {
    $keys = get_transient('ttn_apple_signing_keys');
    if (!is_array($keys)) {
        $response = wp_remote_get('https://appleid.apple.com/auth/keys', array('timeout' => 8));
        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            return new WP_Error('ttn_apple_keys_unavailable', 'Apple sign-in verification is temporarily unavailable.', array('status' => 503));
        }

        $key_set = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($key_set['keys']) || !is_array($key_set['keys'])) {
            return new WP_Error('ttn_apple_keys_invalid', 'Apple signing keys could not be read.', array('status' => 503));
        }

        $keys = $key_set['keys'];
        set_transient('ttn_apple_signing_keys', $keys, 6 * HOUR_IN_SECONDS);
    }

    foreach ($keys as $key) {
        if (($key['kid'] ?? '') === $key_id && ($key['kty'] ?? '') === 'RSA' && isset($key['n'], $key['e'])) {
            return ttn_apple_jwk_to_pem($key['n'], $key['e']);
        }
    }

    delete_transient('ttn_apple_signing_keys');
    return new WP_Error('ttn_apple_key_not_found', 'Apple signing key was not found.', array('status' => 401));
}

function ttn_apple_verify_identity_token($identity_token, $nonce) {
    if (!defined('TTN_APPLE_CLIENT_ID') || !TTN_APPLE_CLIENT_ID) {
        return new WP_Error('ttn_apple_misconfigured', 'Apple sign-in is not configured on this server.', array('status' => 500));
    }
    if (!function_exists('openssl_verify')) {
        return new WP_Error('ttn_apple_crypto_unavailable', 'Apple sign-in verification is unavailable on this server.', array('status' => 500));
    }

    $parts = explode('.', (string) $identity_token);
    if (count($parts) !== 3 || strlen($identity_token) > 16384 || strlen($nonce) < 16 || strlen($nonce) > 256) {
        return new WP_Error('ttn_apple_token_invalid', 'Apple sign-in response is invalid.', array('status' => 401));
    }

    $header = json_decode(ttn_jwt_base64url_decode($parts[0]), true);
    $claims = json_decode(ttn_jwt_base64url_decode($parts[1]), true);
    if (!is_array($header) || !is_array($claims) || ($header['alg'] ?? '') !== 'RS256' || empty($header['kid'])) {
        return new WP_Error('ttn_apple_token_invalid', 'Apple sign-in response is invalid.', array('status' => 401));
    }

    $key = ttn_apple_get_signing_key($header['kid']);
    if (is_wp_error($key)) {
        return $key;
    }

    $signature = ttn_jwt_base64url_decode($parts[2]);
    if (!is_string($signature) || 1 !== openssl_verify($parts[0] . '.' . $parts[1], $signature, $key, OPENSSL_ALGO_SHA256)) {
        return new WP_Error('ttn_apple_signature_invalid', 'Apple sign-in token could not be verified.', array('status' => 401));
    }

    $audiences = is_array($claims['aud'] ?? null) ? $claims['aud'] : array($claims['aud'] ?? '');
    $now = time();
    if (($claims['iss'] ?? '') !== 'https://appleid.apple.com'
        || !in_array(TTN_APPLE_CLIENT_ID, $audiences, true)
        || empty($claims['sub'])
        || empty($claims['exp'])
        || (int) $claims['exp'] <= $now
        || (int) ($claims['iat'] ?? 0) > $now + 300
        || empty($claims['nonce'])
        || !hash_equals(hash('sha256', $nonce), (string) $claims['nonce'])) {
        return new WP_Error('ttn_apple_claims_invalid', 'Apple sign-in token is expired or does not match this app session.', array('status' => 401));
    }

    return $claims;
}

/**
 * Issues a fresh access + refresh token pair for the given user and stores the refresh token hash.
 */
function ttn_jwt_issue_tokens(WP_User $user) {
    $now = time();

    $access_token = ttn_jwt_encode(array(
        'sub' => $user->ID,
        'type' => 'access',
        'iat' => $now,
        'exp' => $now + TTN_JWT_ACCESS_TTL,
    ));
    if (is_wp_error($access_token)) {
        return $access_token;
    }

    $refresh_token = ttn_jwt_base64url_encode(random_bytes(32));
    $refresh_hash = hash('sha256', $refresh_token);

    $stored = get_user_meta($user->ID, 'ttn_refresh_tokens', true);
    $stored = is_array($stored) ? $stored : array();

    // Prune expired tokens so this meta value doesn't grow unbounded.
    $stored = array_filter($stored, static function ($entry) use ($now) {
        return isset($entry['exp']) && $entry['exp'] > $now;
    });

    $stored[] = array('hash' => $refresh_hash, 'exp' => $now + TTN_JWT_REFRESH_TTL);
    update_user_meta($user->ID, 'ttn_refresh_tokens', array_values($stored));

    return array(
        'access_token' => $access_token,
        'refresh_token' => $refresh_token,
        'expires_in' => TTN_JWT_ACCESS_TTL,
        'token_type' => 'Bearer',
    );
}

function ttn_jwt_route_apple_login(WP_REST_Request $request) {
    $rate_limit_identifier = 'apple-signin';
    if (ttn_jwt_is_rate_limited($rate_limit_identifier)) {
        return new WP_Error('ttn_jwt_too_many_attempts', 'Too many sign-in attempts. Try again later.', array('status' => 429));
    }

    $identity_token = trim((string) $request->get_param('identity_token'));
    $nonce = sanitize_text_field((string) $request->get_param('nonce'));
    if (!$identity_token || !$nonce) {
        return new WP_Error('ttn_apple_bad_request', 'identity_token and nonce are required.', array('status' => 400));
    }

    $claims = ttn_apple_verify_identity_token($identity_token, $nonce);
    if (is_wp_error($claims)) {
        ttn_jwt_register_failed_attempt($rate_limit_identifier);
        return $claims;
    }

    $apple_subject = sanitize_text_field($claims['sub']);
    $linked_users = get_users(array(
        'meta_key' => 'ttn_apple_subject',
        'meta_value' => $apple_subject,
        'number' => 1,
        'fields' => 'all',
    ));
    $user = !empty($linked_users) ? $linked_users[0] : null;
    $created_user = false;

    if (!$user) {
        $email = sanitize_email($claims['email'] ?? '');
        $email_verified = in_array($claims['email_verified'] ?? false, array(true, 'true', 1, '1'), true);
        if (!is_email($email) || !$email_verified) {
            ttn_jwt_register_failed_attempt($rate_limit_identifier);
            return new WP_Error('ttn_apple_email_required', 'Apple must provide a verified email the first time you sign in.', array('status' => 400));
        }

        $user = get_user_by('email', $email);
        if (!$user) {
            $base_username = sanitize_user(current(explode('@', $email)), true) ?: 'apple-user';
            $username = $base_username;
            $suffix = 1;
            while (username_exists($username)) {
                $suffix++;
                $username = $base_username . $suffix;
            }

            $name = sanitize_text_field($request->get_param('name'));
            $user_id = wp_insert_user(array(
                'user_login' => $username,
                'user_email' => $email,
                'user_pass' => wp_generate_password(32, true, true),
                'display_name' => $name ?: $username,
                'first_name' => sanitize_text_field((string) $request->get_param('given_name')) ?: $name,
                'last_name' => sanitize_text_field((string) $request->get_param('family_name')),
                'role' => 'subscriber',
            ));

            if (is_wp_error($user_id)) {
                return new WP_Error('ttn_apple_account_failed', 'We could not create your account. Please try again.', array('status' => 400));
            }
            $user = get_user_by('id', $user_id);
            $created_user = true;
        }
    }

    if (!$user instanceof WP_User || user_can($user, 'manage_options') || user_can($user, 'manage_woocommerce')) {
        return new WP_Error('ttn_apple_account_not_allowed', 'This account cannot sign in through the customer mobile app.', array('status' => 403));
    }

    foreach (array('given_name' => 'first_name', 'family_name' => 'last_name') as $param => $meta_key) {
        $value = sanitize_text_field((string) $request->get_param($param));
        if ($value !== '' && get_user_meta($user->ID, $meta_key, true) === '') {
            update_user_meta($user->ID, $meta_key, $value);
        }
    }
    update_user_meta($user->ID, 'ttn_apple_subject', $apple_subject);
    if (in_array($claims['is_private_email'] ?? false, array(true, 'true', 1, '1'), true)
        && get_user_meta($user->ID, '_ttn_contact_email_required', true) !== '1'
        && false === update_user_meta($user->ID, '_ttn_contact_email_required', '1')) {
        error_log('TTN Apple contact email requirement could not be saved for user ' . $user->ID);
        return new WP_Error('ttn_apple_contact_email_storage_failed', 'Unable to save contact email requirements. Please try again.', array('status' => 500));
    }
    ttn_jwt_clear_failed_attempts($rate_limit_identifier);

    $tokens = ttn_jwt_issue_tokens($user);
    if (is_wp_error($tokens)) {
        return $tokens;
    }

    return rest_ensure_response(array_merge($tokens, array(
        'is_new_user' => $created_user,
        'user' => array(
            'id' => $user->ID,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
        ),
    )));
}

function ttn_jwt_route_login(WP_REST_Request $request) {
    $username = sanitize_user($request->get_param('username'));
    $password = (string) $request->get_param('password');

    if (!$username || !$password) {
        return new WP_Error('ttn_jwt_bad_request', 'Username and password are required.', array('status' => 400));
    }

    if (ttn_jwt_is_rate_limited($username)) {
        return new WP_Error('ttn_jwt_too_many_attempts', 'Too many failed login attempts. Try again later.', array('status' => 429));
    }

    $user = wp_authenticate($username, $password);
    if (is_wp_error($user)) {
        ttn_jwt_register_failed_attempt($username);
        return new WP_Error('ttn_jwt_invalid_credentials', 'Invalid username or password.', array('status' => 401));
    }

    ttn_jwt_clear_failed_attempts($username);

    $tokens = ttn_jwt_issue_tokens($user);
    if (is_wp_error($tokens)) {
        return $tokens;
    }

    return rest_ensure_response(array_merge($tokens, array(
        'user' => array(
            'id' => $user->ID,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
        ),
    )));
}

function ttn_jwt_route_register(WP_REST_Request $request) {
    $name = sanitize_text_field($request->get_param('name'));
    $email = sanitize_email($request->get_param('email'));
    $password = (string) $request->get_param('password');
    $phone = sanitize_text_field($request->get_param('phone'));
    $sms_opt_in = rest_sanitize_boolean($request->get_param('sms_opt_in'));
    $promo_opt_in = rest_sanitize_boolean($request->get_param('promo_opt_in'));

    if (!$name || !is_email($email) || !ttn_jwt_password_meets_policy($password)) {
        return new WP_Error('ttn_registration_invalid', 'Enter your name, a valid email, and a password with at least 8 characters, uppercase and lowercase letters, a number, and a symbol.', array('status' => 400));
    }

    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
    $rate_key = 'ttn_register_ip_' . md5($ip);
    $attempts = (int) get_transient($rate_key);
    if ($attempts >= 5) {
        return new WP_Error('ttn_registration_rate_limited', 'Too many signup attempts. Please try again later.', array('status' => 429));
    }
    set_transient($rate_key, $attempts + 1, HOUR_IN_SECONDS);

    if (email_exists($email)) {
        return new WP_Error('ttn_registration_email_exists', 'An account with that email already exists. Please log in instead.', array('status' => 409));
    }

    $base_username = sanitize_user(current(explode('@', $email)), true);
    $base_username = $base_username ?: 'golfer';
    $username = $base_username;
    $suffix = 1;
    while (username_exists($username)) {
        $suffix++;
        $username = $base_username . $suffix;
    }

    $user_id = wp_insert_user(array(
        'user_login' => $username,
        'user_email' => $email,
        'user_pass' => $password,
        'display_name' => $name,
        'first_name' => $name,
        'role' => 'subscriber',
    ));

    if (is_wp_error($user_id)) {
        return new WP_Error('ttn_registration_failed', 'We could not create your account. Please try again.', array('status' => 400));
    }

    if ($phone !== '') {
        update_user_meta($user_id, 'phone_number', $phone);
    }
    update_user_meta($user_id, 'sms_opt_in', $sms_opt_in ? '1' : '0');
    update_user_meta($user_id, 'promo_opt_in', $promo_opt_in ? '1' : '0');
    // The theme now sends on user_register; report that result without a second send.
    if (false !== has_action('user_register', 'golf_simulator_theme_send_account_welcome_email')) {
        $welcome_record = get_user_meta($user_id, '_ttn_account_welcome_email', true);
        $welcome_email_sent = is_array($welcome_record) && ($welcome_record['status'] ?? '') === 'accepted';
    } else {
        $welcome_email_sent = function_exists('golf_simulator_theme_send_account_welcome_email')
            ? (bool) golf_simulator_theme_send_account_welcome_email($user_id)
            : false;
    }

    if (!$welcome_email_sent) {
        error_log('TTN account welcome email could not be sent for user ID ' . (int) $user_id);
    }
    $user = get_user_by('id', $user_id);
    $tokens = ttn_jwt_issue_tokens($user);
    if (is_wp_error($tokens)) {
        return $tokens;
    }

    return new WP_REST_Response(array_merge($tokens, array(
        'welcome_email_sent' => $welcome_email_sent,
        'user' => array(
            'id' => $user->ID,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
        ),
    )), 201);
}

function ttn_jwt_route_refresh(WP_REST_Request $request) {
    $refresh_token = (string) $request->get_param('refresh_token');
    if (!$refresh_token) {
        return new WP_Error('ttn_jwt_bad_request', 'refresh_token is required.', array('status' => 400));
    }

    $refresh_hash = hash('sha256', $refresh_token);
    $now = time();

    $matched_user = null;
    $users = get_users(array(
        'meta_key' => 'ttn_refresh_tokens',
        'fields' => 'all',
    ));

    foreach ($users as $candidate) {
        $stored = get_user_meta($candidate->ID, 'ttn_refresh_tokens', true);
        $stored = is_array($stored) ? $stored : array();

        foreach ($stored as $entry) {
            if (isset($entry['hash'], $entry['exp']) && hash_equals($entry['hash'], $refresh_hash) && $entry['exp'] > $now) {
                $matched_user = $candidate;
                break 2;
            }
        }
    }

    if (!$matched_user) {
        return new WP_Error('ttn_jwt_invalid_refresh', 'Refresh token is invalid or expired.', array('status' => 401));
    }

    // Rotate: revoke the used refresh token and issue a brand new pair.
    $stored = get_user_meta($matched_user->ID, 'ttn_refresh_tokens', true);
    $stored = is_array($stored) ? $stored : array();
    $stored = array_values(array_filter($stored, static function ($entry) use ($refresh_hash) {
        return !hash_equals($entry['hash'], $refresh_hash);
    }));
    update_user_meta($matched_user->ID, 'ttn_refresh_tokens', $stored);

    $tokens = ttn_jwt_issue_tokens($matched_user);
    if (is_wp_error($tokens)) {
        return $tokens;
    }

    return rest_ensure_response($tokens);
}

function ttn_jwt_route_me(WP_REST_Request $request) {
    $user = $request->get_param('ttn_auth_user');
    if (!$user instanceof WP_User) {
        return new WP_Error('ttn_jwt_invalid', 'Not authenticated.', array('status' => 401));
    }

    return rest_ensure_response(array(
        'id' => $user->ID,
        'email' => $user->user_email,
        'display_name' => $user->display_name,
    ));
}

function ttn_jwt_route_logout(WP_REST_Request $request) {
    $user = $request->get_param('ttn_auth_user');
    $refresh_token = (string) $request->get_param('refresh_token');

    if ($refresh_token && $user instanceof WP_User) {
        $refresh_hash = hash('sha256', $refresh_token);
        $stored = get_user_meta($user->ID, 'ttn_refresh_tokens', true);
        $stored = is_array($stored) ? $stored : array();
        $stored = array_values(array_filter($stored, static function ($entry) use ($refresh_hash) {
            return !hash_equals($entry['hash'], $refresh_hash);
        }));
        update_user_meta($user->ID, 'ttn_refresh_tokens', $stored);
    } elseif ($user instanceof WP_User) {
        // No specific refresh token supplied: revoke every session for this user.
        delete_user_meta($user->ID, 'ttn_refresh_tokens');
    }

    return rest_ensure_response(array('success' => true));
}

add_action('rest_api_init', function () {
    register_rest_route('ttn/v1', '/auth/apple', array(
        'methods' => 'POST',
        'callback' => 'ttn_jwt_route_apple_login',
        'permission_callback' => '__return_true',
        'args' => array(
            'identity_token' => array('required' => true, 'type' => 'string'),
            'nonce' => array('required' => true, 'type' => 'string'),
            'name' => array('required' => false, 'type' => 'string'),
            'given_name' => array('required' => false, 'type' => 'string'),
            'family_name' => array('required' => false, 'type' => 'string'),
        ),
    ));

    register_rest_route('ttn/v1', '/auth/login', array(
        'methods' => 'POST',
        'callback' => 'ttn_jwt_route_login',
        'permission_callback' => '__return_true',
        'args' => array(
            'username' => array('required' => true, 'type' => 'string'),
            'password' => array('required' => true, 'type' => 'string'),
        ),
    ));

    register_rest_route('ttn/v1', '/auth/register', array(
        'methods' => 'POST',
        'callback' => 'ttn_jwt_route_register',
        'permission_callback' => '__return_true',
        'args' => array(
            'name' => array('required' => true, 'type' => 'string'),
            'email' => array('required' => true, 'type' => 'string'),
            'password' => array('required' => true, 'type' => 'string'),
            'phone' => array('required' => false, 'type' => 'string'),
            'sms_opt_in' => array('required' => false),
            'promo_opt_in' => array('required' => false),
        ),
    ));

    register_rest_route('ttn/v1', '/auth/refresh', array(
        'methods' => 'POST',
        'callback' => 'ttn_jwt_route_refresh',
        'permission_callback' => '__return_true',
        'args' => array(
            'refresh_token' => array('required' => true, 'type' => 'string'),
        ),
    ));

    register_rest_route('ttn/v1', '/auth/logout', array(
        'methods' => 'POST',
        'callback' => 'ttn_jwt_route_logout',
        'permission_callback' => 'ttn_jwt_authenticate_request',
    ));

    register_rest_route('ttn/v1', '/auth/me', array(
        'methods' => 'GET',
        'callback' => 'ttn_jwt_route_me',
        'permission_callback' => 'ttn_jwt_authenticate_request',
    ));
});
