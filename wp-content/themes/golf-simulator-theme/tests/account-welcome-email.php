<?php

if (PHP_SAPI !== 'cli') {
    exit;
}
define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
define('TTN_JWT_SECRET', 'test-only-signing-secret-at-least-32-characters');
define('TTN_APPLE_CLIENT_ID', 'test.tee.time.nexus');

$actions = array();
$users = array();
$mail_calls = array();
$mail_result = true;
$mail_failure = '';
$user_meta = array();
$can_edit_users = true;
$can_edit_user = true;

class WP_Error {
    private $message;
    public function __construct($message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
class WelcomeTestRedirect extends RuntimeException {}
class WelcomeTestDenied extends RuntimeException {}
class WP_User extends stdClass {}
class WP_REST_Request {
    private $params;
    public function __construct($params) { $this->params = $params; }
    public function get_param($key) { return $this->params[$key] ?? null; }
}
class WP_REST_Response {
    public $data;
    public function __construct($data, $status = 200) { $this->data = $data; }
}

function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
    $GLOBALS['actions'][$hook][$priority][] = array($callback, $accepted_args);
}
function remove_action($hook, $callback, $priority = 10) {
    foreach ($GLOBALS['actions'][$hook][$priority] ?? array() as $key => $entry) {
        if ($entry[0] === $callback) {
            unset($GLOBALS['actions'][$hook][$priority][$key]);
        }
    }
}
function do_action($hook, ...$args) {
    $priorities = $GLOBALS['actions'][$hook] ?? array();
    ksort($priorities);
    foreach ($priorities as $callbacks) {
        foreach ($callbacks as $entry) {
            call_user_func_array($entry[0], array_slice($args, 0, $entry[1]));
        }
    }
}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
function has_action($hook, $callback) {
    foreach ($GLOBALS['actions'][$hook] ?? array() as $priority => $callbacks) {
        foreach ($callbacks as $entry) {
            if ($entry[0] === $callback) return $priority;
        }
    }
    return false;
}
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key, $value, $ttl) {}
function delete_transient($key) { unset($GLOBALS['transients'][$key]); }
function user_can($user, $capability) { return false; }
function rest_ensure_response($value) { return $value; }
function get_users($args) {
    return array_values(array_filter($GLOBALS['users'], static function ($user) use ($args) {
        return get_user_meta($user->ID, $args['meta_key'], true) === $args['meta_value'];
    }));
}
function rest_sanitize_boolean($value) { return (bool) $value; }
function wp_json_encode($value) { return json_encode($value); }
function get_user_by($field, $value) { return $field === 'id' ? get_userdata($value) : false; }
function get_userdata($id) { return $GLOBALS['users'][$id] ?? false; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function is_email($email) { return filter_var($email, FILTER_VALIDATE_EMAIL) !== false; }
function home_url($path) { return 'https://example.test' . $path; }
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $value)); }
function add_query_arg($key, $value, $url) { return $url . '?' . http_build_query(array($key => $value)); }
function get_current_screen() { return (object) array('id' => $GLOBALS['screen_id'] ?? 'users'); }
function get_theme_mod($key) { return $GLOBALS['theme_mods'][$key] ?? false; }
function wp_get_attachment_image_url($id, $size) { return 'https://example.test/logo.png'; }
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function __($value, $domain) { return $value; }
function wp_unslash($value) { return $value; }
function sanitize_text_field($value) { return trim($value); }
function sanitize_email($value) { return trim($value); }
function sanitize_user($value, $strict = false) { return $value; }
function wp_verify_nonce($nonce, $action) { return $nonce === 'valid'; }
function email_exists($email) {
    foreach ($GLOBALS['users'] as $id => $user) {
        if ($user->user_email === $email) {
            return $id;
        }
    }
    return false;
}
function username_exists($username) { return false; }
function wp_insert_user($data) {
    $id = count($GLOBALS['users']) + 1;
    $data['ID'] = $id;
    $GLOBALS['users'][$id] = new WP_User();
    foreach ($data as $key => $value) {
        $GLOBALS['users'][$id]->$key = $value;
    }
    do_action('user_register', $id, $data);
    return $id;
}
function update_user_meta($id, $key, $value) {
    $GLOBALS['user_meta'][$id][$key] = $value;
    return true;
}
function get_user_meta($id, $key, $single = false) { return $GLOBALS['user_meta'][$id][$key] ?? ''; }
function current_user_can($capability, ...$args) {
    return $capability === 'edit_users' ? $GLOBALS['can_edit_users'] : $GLOBALS['can_edit_user'];
}
function wp_date($format, $timestamp) { return gmdate($format, $timestamp); }
function wp_nonce_field($action, $name) {
    echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($action) . '" />';
}
function wp_die($message, $title = '', $args = array()) { throw new WelcomeTestDenied($message); }
function check_admin_referer($action, $name) {
    if (($_POST[$name] ?? '') !== $action) {
        wp_die('Invalid nonce');
    }
}
function wp_set_current_user($id) { $GLOBALS['current_user'] = $id; }
function wp_set_auth_cookie($id, $remember) { $GLOBALS['auth_user'] = $id; }
function wp_safe_redirect($url) { throw new WelcomeTestRedirect($url); }
function wp_mail($to, $subject, $message, $headers) {
    $GLOBALS['mail_calls'][] = compact('to', 'subject', 'message', 'headers');
    if (!$GLOBALS['mail_result'] && $GLOBALS['mail_failure']) {
        do_action('wp_mail_failed', new WP_Error($GLOBALS['mail_failure']));
    }
    return $GLOBALS['mail_result'];
}
function check_welcome($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

require dirname(__DIR__) . '/inc/email-template.php';
require dirname(__DIR__) . '/inc/auth.php';
require dirname(__DIR__, 3) . '/plugins/tee-time-nexus-bookings/inc/class-ttn-jwt-auth.php';

$log_file = tempnam(sys_get_temp_dir(), 'ttn-welcome-');
$original_error_log = ini_get('error_log');
ini_set('error_log', $log_file);

try {
    $GLOBALS['theme_mods']['custom_logo'] = 42;
    $apple_id = wp_insert_user(array(
        'user_email' => 'golfer@privaterelay.appleid.com',
        'display_name' => 'Apple Golfer',
    ));
    check_welcome(count($mail_calls) === 1, 'New Apple relay accounts must trigger one welcome email.');
    check_welcome($mail_calls[0]['to'] === 'golfer@privaterelay.appleid.com', 'Send to the stored relay address without rewriting it.');
    check_welcome($mail_calls[0]['subject'] === 'Welcome to Tee Time Nexus', 'Preserve the welcome subject.');
    check_welcome(strpos($mail_calls[0]['message'], 'Apple Golfer') !== false, 'Preserve personalized HTML.');
    check_welcome(in_array('From: Tee Time Nexus <sales@teetimenexus.com>', $mail_calls[0]['headers'], true), 'Preserve the configured sender.');
    $email_html = $mail_calls[0]['message'];
    $email_text = html_entity_decode(strip_tags($email_html), ENT_QUOTES, 'UTF-8');
    foreach (array(
        'Your account is ready—and your next round just got better.',
        'Tee Time Nexus brings together advanced golf technology, immersive gameplay, and the freedom to play on your schedule.',
        'Experience world-class courses with precise shot tracking, detailed swing and ball-flight analysis, realistic playing conditions, and a moving swing platform that recreates changing lies and terrain.',
        'More than a simulator. A better way to experience golf.',
        'As a member, you’ll enjoy 24/7 access—whether that means an early-morning practice session, a round after work, or late-night golf with friends.',
        'We’re putting the finishing touches on our Mooresville location and can’t wait to welcome you.',
        'Your next round is waiting.',
        'See you at the Tee Time',
    ) as $copy) {
        check_welcome(strpos($email_text, $copy) !== false, 'Include the requested welcome copy: ' . $copy);
    }
    check_welcome(strpos($email_html, 'href="https://example.test/membership/"') !== false && strpos($email_html, '>Explore memberships</a>') !== false, 'Link to membership options beside the member benefits.');
    check_welcome(strpos($email_html, 'href="https://example.test/book-a-bay/"') !== false, 'Preserve the booking call to action.');
    check_welcome(strpos($email_html, 'src="https://example.test/logo.png"') !== false, 'Include the configured logo.');
    check_welcome(strpos($email_html, 'mailto:sales@teetimenexus.com') !== false && strpos($email_html, 'tel:+19805033288') !== false, 'Include clickable email and phone contacts.');
    $map_url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode('2785 Charlotte Hwy Suites 11&12, Mooresville, NC 28117');
    check_welcome(strpos($email_html, 'href="' . esc_url($map_url) . '"') !== false && strpos($email_text, '2785 Charlotte Hwy, Suites 11 & 12') !== false, 'Include the address and its Google Maps link.');
    check_welcome(strpos($email_html, 'Experience Golf Differently') === false && strpos($email_html, 'See you on the tee!') === false, 'Remove superseded welcome copy and signoff.');
    $default_email = golf_simulator_theme_render_email_template('Test', 'Test', '<p>Test</p>');
    check_welcome(strpos($default_email, 'See you on the tee!') !== false, 'Keep the existing signoff for other emails.');
    check_welcome(strpos(golf_simulator_theme_render_email_template('Test', 'Test', '', '', '', '<script>'), '&lt;script&gt;') !== false, 'Escape customized signoffs.');
    check_welcome(strpos(file_get_contents($log_file), 'accepted by wp_mail for user ' . $apple_id) !== false, 'Log successful handoff without claiming delivery.');
    $record = get_user_meta($apple_id, '_ttn_account_welcome_email', true);
    check_welcome($record['status'] === 'accepted' && $record['attempts'] === 1, 'Persist successful send status and attempt count.');
    check_welcome($record['accepted_at'] > 0 && $record['attempted_at'] > 0, 'Persist handoff and attempt timestamps.');
    check_welcome($record['recipient'] === 'golfer@privaterelay.appleid.com' && $record['error'] === '', 'Persist recipient without a stale error.');

    do_action('wp_login', 'apple-golfer', get_userdata($apple_id));
    do_action('profile_update', $apple_id);
    check_welcome(count($mail_calls) === 1, 'Existing-user login and updates must not send a welcome email.');

    $_POST = array(
        'ttn_register_nonce' => 'valid',
        'ttn_name' => 'Website Golfer',
        'email' => 'website@example.test',
        'password' => 'StrongPass1!',
    );
    try {
        golf_simulator_theme_process_register('/my-account/');
        throw new RuntimeException('Registration must redirect after success.');
    } catch (WelcomeTestRedirect $redirect) {
        check_welcome($redirect->getMessage() === '/my-account/', 'Preserve the registration redirect.');
    }
    check_welcome(count($mail_calls) === 2, 'Website registration must not send a duplicate welcome email.');
    check_welcome($GLOBALS['current_user'] === 2 && $GLOBALS['auth_user'] === 2, 'Preserve automatic website sign-in.');
    check_welcome(is_string(golf_simulator_theme_process_register('/my-account/')), 'Duplicate registration must still return an error.');
    check_welcome(count($mail_calls) === 2, 'Rejected registration must not send an email.');

    $mail_result = false;
    $mail_failure = 'SMTP rejected the recipient';
    $failed_id = wp_insert_user(array('user_email' => 'failed@example.test', 'display_name' => 'Failed Golfer'));
    check_welcome(get_userdata($failed_id) !== false, 'Mail failure must not undo successful account creation.');
    check_welcome(strpos(file_get_contents($log_file), 'failed for user ' . $failed_id . ': SMTP rejected the recipient') !== false, 'Log transport errors with the user ID.');
    $record = get_user_meta($failed_id, '_ttn_account_welcome_email', true);
    check_welcome($record['status'] === 'failed' && $record['accepted_at'] === 0 && $record['error'] === $mail_failure, 'Persist failure without claiming successful handoff.');
    check_welcome(empty($actions['wp_mail_failed'][10]), 'Remove the temporary failure listener.');

    $mail_failure = '';
    check_welcome(golf_simulator_theme_send_account_welcome_email($failed_id) === false, 'Return false when the mail transport fails without an error event.');
    check_welcome(strpos(file_get_contents($log_file), 'wp_mail returned false without an error message') !== false, 'Log failures even without a transport error.');

    $before_invalid = count($mail_calls);
    wp_insert_user(array('user_email' => '', 'display_name' => 'No Email'));
    check_welcome(golf_simulator_theme_send_account_welcome_email(999) === false, 'Missing users must not send mail.');
    check_welcome(count($mail_calls) === $before_invalid, 'Invalid recipients must not reach wp_mail.');
    check_welcome(strpos(file_get_contents($log_file), 'missing user or invalid email') !== false, 'Log skipped invalid recipients.');
    check_welcome(get_user_meta(4, '_ttn_account_welcome_email', true)['status'] === 'skipped', 'Persist skipped sends for invalid addresses.');
    check_welcome(get_user_meta(999, '_ttn_account_welcome_email', true) === '', 'Do not create metadata for nonexistent users.');

    ob_start();
    golf_simulator_theme_admin_welcome_email_profile(get_userdata($apple_id));
    $profile_html = ob_get_clean();
    check_welcome(strpos($profile_html, 'Accepted by wp_mail (delivery not confirmed)') !== false, 'Admin profile must distinguish handoff from delivery.');
    check_welcome(strpos($profile_html, 'ttn_resend_welcome_email') !== false && strpos($profile_html, 'ttn_resend_welcome_email_1') !== false, 'Show a user-bound resend nonce and checkbox.');

    $users[5] = (object) array('ID' => 5, 'user_email' => 'older@example.test', 'display_name' => 'Older User');
    ob_start();
    golf_simulator_theme_admin_welcome_email_profile($users[5]);
    $profile_html = ob_get_clean();
    check_welcome(strpos($profile_html, 'No recorded attempt (older emails may not have been tracked)') !== false, 'Do not invent a send result for older accounts.');

    $_POST = array('ttn_resend_welcome_email' => '1', 'ttn_resend_welcome_nonce' => 'ttn_resend_welcome_email_1');
    $mail_result = true;
    $users[$apple_id]->user_email = 'updated@example.test';
    do_action('profile_update', $apple_id);
    $record = get_user_meta($apple_id, '_ttn_account_welcome_email', true);
    check_welcome($record['attempts'] === 2 && $record['status'] === 'accepted', 'Explicit admin resend must update status and count.');
    check_welcome(end($mail_calls)['to'] === 'updated@example.test', 'Resend must use the newly saved email address.');
    $accepted_at = $record['accepted_at'];
    $mail_result = false;
    $mail_failure = '<script>alert("mail error")</script>';
    do_action('profile_update', $apple_id);
    $record = get_user_meta($apple_id, '_ttn_account_welcome_email', true);
    check_welcome($record['status'] === 'failed' && $record['accepted_at'] === $accepted_at && $record['attempts'] === 3, 'Failed resend must preserve the prior successful handoff.');
    ob_start();
    golf_simulator_theme_admin_welcome_email_profile(get_userdata($apple_id));
    $profile_html = ob_get_clean();
    check_welcome(strpos($profile_html, 'Send failed') !== false && strpos($profile_html, '&lt;script&gt;') !== false && strpos($profile_html, '<script>') === false, 'Display and escape persistent resend errors.');

    $before_denied = count($mail_calls);
    foreach (array('missing_nonce', 'wrong_user_nonce', 'no_edit_users', 'no_edit_user') as $case) {
        $_POST = array('ttn_resend_welcome_email' => '1', 'ttn_resend_welcome_nonce' => 'ttn_resend_welcome_email_1');
        $can_edit_users = $case !== 'no_edit_users';
        $can_edit_user = $case !== 'no_edit_user';
        if ($case === 'missing_nonce') {
            unset($_POST['ttn_resend_welcome_nonce']);
        } elseif ($case === 'wrong_user_nonce') {
            $_POST['ttn_resend_welcome_nonce'] = 'ttn_resend_welcome_email_2';
        }
        try {
            do_action('profile_update', $apple_id);
            throw new RuntimeException('Unauthorized resend must be rejected: ' . $case);
        } catch (WelcomeTestDenied $denied) {
            check_welcome(count($mail_calls) === $before_denied, 'Rejected resend must not send mail.');
        }
    }
    ob_start();
    golf_simulator_theme_admin_welcome_email_profile(get_userdata($apple_id));
    check_welcome(ob_get_clean() === '', 'Hide email controls from users without permission.');
    $can_edit_users = true;
    $can_edit_user = true;
    $_POST = array();
    do_action('profile_update', $apple_id);
    check_welcome(count($mail_calls) === $before_denied, 'Ordinary profile saves must not resend mail.');

    $columns = golf_simulator_theme_welcome_email_users_columns(array('username' => 'Username'));
    check_welcome(isset($columns['username'], $columns['ttn_welcome_email']), 'Add the welcome column without removing existing columns.');
    $column = golf_simulator_theme_welcome_email_users_column('', 'ttn_welcome_email', $apple_id);
    check_welcome(strpos($column, 'Send failed') !== false && strpos($column, '&lt;script&gt;') !== false, 'Users list must display escaped send results.');
    check_welcome(strpos($column, 'form="ttn-welcome-resend-1"') !== false && strpos($column, '<form') === false, 'Resend button must reference an external form, not nest a form in the Users table.');
    check_welcome(golf_simulator_theme_welcome_email_users_column('existing', 'username', 1) === 'existing', 'Leave other user columns untouched.');
    ob_start();
    do_action('admin_footer-users.php');
    $forms = ob_get_clean();
    check_welcome(strpos($forms, 'id="ttn-welcome-resend-1"') !== false && strpos($forms, 'method="post"') !== false, 'Render an independent POST form for the row button.');
    check_welcome(strpos($forms, 'name="user_id" value="1"') !== false && strpos($forms, 'ttn_resend_welcome_email_1') !== false, 'Bind the resend form to its user and nonce.');
    check_welcome(strpos($forms, 'golf_simulator_resend_welcome_email') !== false, 'Submit to the dedicated resend handler.');

    foreach (array('GET', 'missing_nonce', 'wrong_user_nonce', 'no_edit_users', 'no_edit_user', 'missing_user', 'invalid_user') as $case) {
        $_SERVER['REQUEST_METHOD'] = $case === 'GET' ? 'GET' : 'POST';
        $_POST = array('user_id' => '1', 'ttn_resend_welcome_nonce' => 'ttn_resend_welcome_email_1');
        $can_edit_users = $case !== 'no_edit_users';
        $can_edit_user = $case !== 'no_edit_user';
        if ($case === 'missing_nonce') {
            unset($_POST['ttn_resend_welcome_nonce']);
        } elseif ($case === 'wrong_user_nonce') {
            $_POST['ttn_resend_welcome_nonce'] = 'ttn_resend_welcome_email_2';
        } elseif ($case === 'missing_user') {
            $_POST['user_id'] = '999';
            $_POST['ttn_resend_welcome_nonce'] = 'ttn_resend_welcome_email_999';
        } elseif ($case === 'invalid_user') {
            $_POST['user_id'] = array('1');
            $_POST['ttn_resend_welcome_nonce'] = 'ttn_resend_welcome_email_0';
        }
        try {
            golf_simulator_theme_handle_welcome_email_list_resend();
            throw new RuntimeException('Invalid Users list resend must be rejected: ' . $case);
        } catch (WelcomeTestDenied $denied) {
            check_welcome(count($mail_calls) === $before_denied, 'Rejected list resend must not send mail.');
        }
    }
    $can_edit_users = true;
    $can_edit_user = true;
    foreach (array(true, false) as $result) {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = array('user_id' => '1', 'ttn_resend_welcome_nonce' => 'ttn_resend_welcome_email_1');
        $mail_result = $result;
        try {
            do_action('admin_post_golf_simulator_resend_welcome_email');
            throw new RuntimeException('List resend must redirect.');
        } catch (WelcomeTestRedirect $redirect) {
            check_welcome($redirect->getMessage() === admin_url('users.php') . '?ttn_welcome_email_result=' . ($result ? 'accepted' : 'failed'), 'Redirect back to Users with the correct send result.');
        }
        check_welcome(count($mail_calls) === ++$before_denied, 'Each list resend must send exactly once.');
        $_GET['ttn_welcome_email_result'] = $result ? 'accepted' : 'failed';
        ob_start();
        golf_simulator_theme_welcome_email_users_notice();
        $notice = ob_get_clean();
        check_welcome(strpos($notice, $result ? 'notice-success' : 'notice-error') !== false, 'Show appropriate list resend feedback.');
    }
    $can_edit_users = false;
    check_welcome(!isset(golf_simulator_theme_welcome_email_users_columns(array())['ttn_welcome_email']), 'Hide the column without admin permission.');
    check_welcome(golf_simulator_theme_welcome_email_users_column('', 'ttn_welcome_email', 1) === '', 'Hide resend controls without permission.');
    ob_start();
    golf_simulator_theme_welcome_email_users_notice();
    check_welcome(ob_get_clean() === '', 'Hide resend notices without permission.');
    $can_edit_users = true;
    $GLOBALS['screen_id'] = 'dashboard';
    ob_start();
    golf_simulator_theme_welcome_email_users_notice();
    check_welcome(ob_get_clean() === '', 'Do not show Users resend notices on other screens.');

    $mail_result = true;
    $before_mobile_signup = count($mail_calls);
    $result = ttn_jwt_route_register(new WP_REST_Request(array(
        'name' => 'Mobile Golfer',
        'email' => 'mobile@example.test',
        'password' => 'StrongPass1!',
    )));
    check_welcome($result instanceof WP_REST_Response && $result->data['welcome_email_sent'] === true, 'Mobile signup must report the creation-hook send result.');
    check_welcome(count($mail_calls) === $before_mobile_signup + 1, 'Mobile signup must not send a duplicate welcome email.');
    $mobile_user_id = $result->data['user']['id'];
    update_user_meta($mobile_user_id, 'ttn_apple_subject', 'linked-apple-subject');
    update_user_meta($mobile_user_id, '_ttn_verified_contact_email', 'mobile@example.test');
    $key = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
    $details = openssl_pkey_get_details($key);
    $GLOBALS['transients']['ttn_apple_signing_keys'] = array(array(
        'kid' => 'test-key',
        'kty' => 'RSA',
        'n' => ttn_jwt_base64url_encode($details['rsa']['n']),
        'e' => ttn_jwt_base64url_encode($details['rsa']['e']),
    ));
    $nonce = 'test-nonce-with-at-least-16-characters';
    $header = ttn_jwt_base64url_encode(json_encode(array('alg' => 'RS256', 'kid' => 'test-key')));
    $payload = ttn_jwt_base64url_encode(json_encode(array(
        'iss' => 'https://appleid.apple.com',
        'aud' => TTN_APPLE_CLIENT_ID,
        'sub' => 'linked-apple-subject',
        'email' => 'private@privaterelay.appleid.com',
        'email_verified' => 'true',
        'is_private_email' => 'true',
        'iat' => time(),
        'exp' => time() + 60,
        'nonce' => hash('sha256', $nonce),
    )));
    openssl_sign($header . '.' . $payload, $signature, $key, OPENSSL_ALGO_SHA256);
    $apple_login = ttn_jwt_route_apple_login(new WP_REST_Request(array(
        'identity_token' => $header . '.' . $payload . '.' . ttn_jwt_base64url_encode($signature),
        'nonce' => $nonce,
        'name' => '',
        'given_name' => '',
        'family_name' => '',
    )));
    check_welcome(!is_wp_error($apple_login) && $apple_login['user']['id'] === $mobile_user_id, 'Apple login must still resolve the linked account after contact email replacement.');
    check_welcome($apple_login['user']['email'] === 'mobile@example.test', 'Subsequent Apple login must not overwrite the verified contact email with the relay address.');
    check_welcome(get_user_meta($mobile_user_id, '_ttn_contact_email_required', true) === '1', 'Signed Apple private-email claim must record the contact-email requirement.');
    check_welcome(count($mail_calls) === $before_mobile_signup + 1, 'Returning Apple sign-in must not send a new welcome email.');
} finally {
    ini_set('error_log', $original_error_log);
    unlink($log_file);
}

echo "Account welcome email passed: signup, persistent status, profile and Users list rendering, protected resends, and failure logging.\n";
