<?php

if (!defined('ABSPATH')) {
    exit;
}

function golf_simulator_theme_is_apple_relay_email($email) {
    return strtolower(substr(strrchr((string) $email, '@') ?: '', 1)) === 'privaterelay.appleid.com';
}

/**
 * Apple relay accounts retain their sign-in identity but must verify a non-relay
 * WordPress email before membership checkout. Recheck this guard when adding
 * Kisi provisioning/unlock endpoints; email verification alone grants no access.
 */
function golf_simulator_theme_contact_email_status($user_id) {
    $user = get_userdata($user_id);
    if (!$user) {
        return new WP_Error('contact_email_user_missing', 'Account not found.', array('status' => 404));
    }
    $required = get_user_meta($user_id, '_ttn_contact_email_required', true) === '1';
    if (!$required && golf_simulator_theme_is_apple_relay_email($user->user_email)
        && get_user_meta($user_id, 'ttn_apple_subject', true)) {
        $required = true;
        if (false === update_user_meta($user_id, '_ttn_contact_email_required', '1')) {
            error_log('TTN contact email requirement could not be saved for user ' . $user_id);
            return new WP_Error('contact_email_storage_failed', 'Unable to load email verification requirements. Please try again.', array('status' => 500));
        }
    }
    $verified_email = get_user_meta($user_id, '_ttn_verified_contact_email', true);
    $verified = is_email($user->user_email)
        && !golf_simulator_theme_is_apple_relay_email($user->user_email)
        && strtolower($user->user_email) === strtolower((string) $verified_email);
    $pending = get_user_meta($user_id, '_ttn_contact_email_pending', true);
    return array(
        'required' => $required && !$verified,
        'verified' => $verified,
        'email' => $user->user_email,
        'pending_email' => is_array($pending) && ($pending['expires_at'] ?? 0) > time() ? $pending['email'] : null,
    );
}

function golf_simulator_theme_require_contact_email($user_id) {
    $status = golf_simulator_theme_contact_email_status($user_id);
    if (is_wp_error($status)) {
        return $status;
    }
    if ($status['required']) {
        return new WP_Error('contact_email_verification_required',
            'Before continuing with membership or door access, add and verify a non-relay contact email in the app under Profile. You can continue signing in with Apple.',
            array('status' => 403));
    }
    return true;
}

function golf_simulator_theme_contact_email_user(WP_REST_Request $request) {
    $user = $request->get_param('ttn_auth_user');
    return $user instanceof WP_User ? $user : new WP_Error('contact_email_not_authenticated', 'You must be logged in.', array('status' => 401));
}

function golf_simulator_theme_contact_email_get(WP_REST_Request $request) {
    $user = golf_simulator_theme_contact_email_user($request);
    if (is_wp_error($user)) {
        return $user;
    }
    $status = golf_simulator_theme_contact_email_status($user->ID);
    return is_wp_error($status) ? $status : rest_ensure_response($status);
}

function golf_simulator_theme_contact_email_send(WP_REST_Request $request) {
    $user = golf_simulator_theme_contact_email_user($request);
    if (is_wp_error($user)) {
        return $user;
    }
    $status = golf_simulator_theme_contact_email_status($user->ID);
    if (is_wp_error($status)) {
        return $status;
    }
    $email = trim((string) $request->get_param('email'));
    if (!is_email($email) || golf_simulator_theme_is_apple_relay_email($email)) {
        return new WP_Error('contact_email_invalid', 'Enter a valid non-relay contact email address.', array('status' => 400));
    }
    $owner = email_exists($email);
    if ($owner && (int) $owner !== (int) $user->ID) {
        return new WP_Error('contact_email_in_use', 'This email cannot be used for this account. Contact support if you already have another account.', array('status' => 409));
    }
    $rate_key = 'ttn_contact_email_send_' . $user->ID;
    $last_sent = (int) get_transient($rate_key);
    if ($last_sent && time() - $last_sent < 60) {
        return new WP_Error('contact_email_rate_limited', 'Please wait one minute before requesting another code.', array('status' => 429));
    }
    set_transient($rate_key, time(), MINUTE_IN_SECONDS);
    $code = (string) random_int(100000, 999999);
    $pending = array(
        'email' => $email,
        'hash' => wp_hash($user->ID . '|' . strtolower($email) . '|' . $code),
        'expires_at' => time() + 10 * MINUTE_IN_SECONDS,
        'attempts' => 0,
    );
    if (false === update_user_meta($user->ID, '_ttn_contact_email_pending', $pending)) {
        error_log('TTN contact email verification could not be saved for user ' . $user->ID);
        return new WP_Error('contact_email_storage_failed', 'Unable to start email verification. Please try again.', array('status' => 500));
    }
    $body = '<p>Use this code to verify your contact email:</p><p style="font-size:28px;font-weight:700;">' . esc_html($code) . '</p>'
        . '<p>This code expires in 10 minutes. If you did not request this change, ignore this email.</p>';
    $sent = wp_mail($email, 'Verify your Tee Time Nexus contact email',
        golf_simulator_theme_render_email_template('Email Verification', 'Verify your contact email', $body),
        golf_simulator_theme_get_email_headers());
    if (!$sent) {
        delete_user_meta($user->ID, '_ttn_contact_email_pending');
        error_log('TTN contact email verification send failed for user ' . $user->ID);
        return new WP_Error('contact_email_send_failed', 'Unable to send the verification email. Please try again later or contact support.', array('status' => 502));
    }
    $status = golf_simulator_theme_contact_email_status($user->ID);
    return is_wp_error($status) ? $status : rest_ensure_response($status);
}

function golf_simulator_theme_contact_email_verify(WP_REST_Request $request) {
    $user = golf_simulator_theme_contact_email_user($request);
    if (is_wp_error($user)) {
        return $user;
    }
    $pending = get_user_meta($user->ID, '_ttn_contact_email_pending', true);
    if (!is_array($pending) || ($pending['expires_at'] ?? 0) <= time() || ($pending['attempts'] ?? 0) >= 5) {
        delete_user_meta($user->ID, '_ttn_contact_email_pending');
        return new WP_Error('contact_email_code_expired', 'The verification code has expired or too many attempts were made. Request a new code.', array('status' => 400));
    }
    $code = trim((string) $request->get_param('code'));
    if (!preg_match('/^[0-9]{6}$/', $code)
        || !hash_equals($pending['hash'], wp_hash($user->ID . '|' . strtolower($pending['email']) . '|' . $code))) {
        $pending['attempts']++;
        if (false === update_user_meta($user->ID, '_ttn_contact_email_pending', $pending)) {
            delete_user_meta($user->ID, '_ttn_contact_email_pending');
            error_log('TTN contact email attempt count could not be saved for user ' . $user->ID);
            return new WP_Error('contact_email_storage_failed', 'Unable to check verification. Please request a new code.', array('status' => 500));
        }
        return new WP_Error('contact_email_code_invalid', 'Incorrect verification code. Please try again.', array('status' => 400));
    }
    $owner = email_exists($pending['email']);
    if ($owner && (int) $owner !== (int) $user->ID) {
        return new WP_Error('contact_email_in_use', 'This email can no longer be used for this account. Request a code for another address.', array('status' => 409));
    }
    // Retain the requirement after replacing the relay address so later unverified changes cannot bypass it.
    $status = golf_simulator_theme_contact_email_status($user->ID);
    if (is_wp_error($status)) {
        return $status;
    }
    $previous_email = $user->user_email;
    $updated = wp_update_user(array('ID' => $user->ID, 'user_email' => $pending['email']));
    if (is_wp_error($updated)) {
        return $updated;
    }
    $billing_email = get_user_meta($user->ID, 'billing_email', true);
    if ($billing_email !== $pending['email'] && (!$billing_email || golf_simulator_theme_is_apple_relay_email($billing_email)
        || strtolower((string) $billing_email) === strtolower($previous_email))) {
        if (false === update_user_meta($user->ID, 'billing_email', $pending['email'])) {
            error_log('TTN contact billing email could not be saved for user ' . $user->ID);
            return new WP_Error('contact_email_storage_failed', 'Unable to save your contact email. Please try verification again.', array('status' => 500));
        }
    }
    update_user_meta($user->ID, '_ttn_verified_contact_email', $pending['email']);
    delete_user_meta($user->ID, '_ttn_contact_email_pending');
    $status = golf_simulator_theme_contact_email_status($user->ID);
    if (is_wp_error($status) || !$status['verified']) {
        error_log('TTN verified contact email could not be saved for user ' . $user->ID);
        return new WP_Error('contact_email_storage_failed', 'Unable to save verification. Please request a new code.', array('status' => 500));
    }
    return rest_ensure_response($status);
}

add_action('rest_api_init', function () {
    foreach (array(
        '/account/contact-email' => array('GET', 'golf_simulator_theme_contact_email_get'),
        '/account/contact-email/send' => array('POST', 'golf_simulator_theme_contact_email_send'),
        '/account/contact-email/verify' => array('POST', 'golf_simulator_theme_contact_email_verify'),
    ) as $route => $handler) {
        register_rest_route('ttn/v1', $route, array(
            'methods' => $handler[0],
            'callback' => $handler[1],
            'permission_callback' => 'ttn_jwt_authenticate_request',
            'args' => $handler[0] === 'POST' ? array(
                $handler[1] === 'golf_simulator_theme_contact_email_send' ? 'email' : 'code' => array('required' => true, 'type' => 'string'),
            ) : array(),
        ));
    }
});

function golf_simulator_theme_contact_email_checkout_validation($data, $errors) {
    $user_id = get_current_user_id();
    if (!$user_id || !function_exists('WC') || !WC() || !WC()->cart) {
        return;
    }
    foreach (WC()->cart->get_cart() as $item) {
        $sku = isset($item['data']) ? $item['data']->get_sku() : '';
        if (!empty($item['golf_simulator_membership']) || strpos((string) $sku, 'membership-') === 0) {
            $allowed = golf_simulator_theme_require_contact_email($user_id);
            if (is_wp_error($allowed)) {
                $errors->add($allowed->get_error_code(), $allowed->get_error_message());
            }
            break;
        }
    }
}
add_action('woocommerce_after_checkout_validation', 'golf_simulator_theme_contact_email_checkout_validation', 10, 2);
