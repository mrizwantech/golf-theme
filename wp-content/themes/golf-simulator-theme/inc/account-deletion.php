<?php

if (!defined('ABSPATH')) {
    exit;
}

function golf_simulator_theme_mobile_delete_account(WP_REST_Request $request) {
    $user = $request->get_param('ttn_auth_user');
    if (!$user instanceof WP_User) {
        return new WP_Error('account_delete_not_authenticated', 'You must be logged in.', array('status' => 401));
    }
    if ($request->get_param('confirm') !== true) {
        return new WP_Error('account_delete_confirmation_required', 'Confirm account deletion to continue.', array('status' => 400));
    }

    $user_id = (int) $user->ID;
    $password = (string) $request->get_param('password');
    $claims = $request->get_param('ttn_auth_claims');
    if ($password !== '') {
        $attempt_key = 'ttn_account_delete_fail_' . $user_id;
        $failed_attempts = (int) get_transient($attempt_key);
        if ($failed_attempts >= 5) {
            return new WP_Error('account_delete_too_many_attempts', 'Too many incorrect password attempts. Please try again later.', array('status' => 429));
        }
        if (!wp_check_password($password, $user->user_pass, $user_id)) {
            set_transient($attempt_key, $failed_attempts + 1, 15 * MINUTE_IN_SECONDS);
            return new WP_Error('account_delete_invalid_password', 'Incorrect account password. Please try again.', array('status' => 403));
        }
        delete_transient($attempt_key);
    } else {
        $auth_method = is_array($claims) ? ($claims['auth_method'] ?? '') : '';
        $auth_at = is_array($claims) ? (int) ($claims['auth_at'] ?? 0) : 0;
        if (!in_array($auth_method, array('apple', 'passkey'), true)
            || $auth_at < time() - 300
            || $auth_at > time() + 30) {
            return new WP_Error('account_delete_reauthentication_required', 'Verify your password, Apple account, or passkey before deleting your account.', array('status' => 401));
        }
    }

    $administrators = get_users(array(
        'role' => 'administrator',
        'exclude' => array($user_id),
        'orderby' => 'ID',
        'order' => 'ASC',
        'number' => 1,
        'fields' => 'ID',
    ));
    if (!$administrators) {
        return new WP_Error('account_delete_reassignment_unavailable', 'Account deletion is temporarily unavailable. Please try again later.', array('status' => 503));
    }

    $kisi_grant = get_user_meta($user_id, 'ttn_kisi_booking_grant', true);
    if (!empty($kisi_grant)) {
        if (!function_exists('ttn_kisi_invalidate_user_access')) {
            return new WP_Error('account_delete_access_unavailable', 'Account deletion is temporarily unavailable. Please try again later.', array('status' => 503));
        }
        $revoked = ttn_kisi_invalidate_user_access($user_id);
        if (is_wp_error($revoked)) {
            return $revoked;
        }
    }

    if (!function_exists('ttn_push_delete_user_data')) {
        return new WP_Error('account_delete_notifications_unavailable', 'Account deletion is temporarily unavailable. Please try again later.', array('status' => 503));
    }
    $push_cleanup = ttn_push_delete_user_data($user_id);
    if (is_wp_error($push_cleanup)) {
        return $push_cleanup;
    }

    if (!function_exists('ttn_passkey_delete_user_data')) {
        return new WP_Error('account_delete_passkeys_unavailable', 'Account deletion is temporarily unavailable. Please try again later.', array('status' => 503));
    }
    $passkey_cleanup = ttn_passkey_delete_user_data($user_id);
    if (is_wp_error($passkey_cleanup)) {
        return $passkey_cleanup;
    }

    if (!function_exists('wp_delete_user')) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }
    if (!wp_delete_user($user_id, (int) $administrators[0])) {
        return new WP_Error('account_delete_failed', 'Your account could not be deleted. Please try again.', array('status' => 500));
    }
    wp_clear_scheduled_hook('ttn_membership_change_due', array($user_id));

    $response = rest_ensure_response(array('deleted' => true));
    $response->header('Cache-Control', 'private, no-store, max-age=0');
    return $response;
}

add_action('rest_api_init', static function () {
    register_rest_route('ttn/v1', '/account', array(
        'methods' => 'DELETE',
        'callback' => 'golf_simulator_theme_mobile_delete_account',
        'permission_callback' => 'ttn_jwt_authenticate_request',
        'args' => array(
            'confirm' => array('required' => true, 'type' => 'boolean'),
            'password' => array('required' => false, 'type' => 'string'),
        ),
    ));
});
