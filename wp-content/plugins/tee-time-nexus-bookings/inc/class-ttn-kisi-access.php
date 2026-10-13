<?php

if (!defined('ABSPATH')) {
    exit;
}

function ttn_kisi_access_error($code, $message, $status = 503) {
    return new WP_Error($code, $message, array('status' => $status));
}

function ttn_kisi_config() {
    foreach (array('TTN_KISI_API_KEY', 'TTN_KISI_ORGANIZATION_ID', 'TTN_KISI_BOOKING_GROUP_ID', 'TTN_KISI_ENTRANCE_LOCK_ID') as $name) {
        if (!defined($name)) {
            return ttn_kisi_access_error('ttn_kisi_configuration', 'Door access is not configured. Please contact support.');
        }
    }
    if (!is_string(TTN_KISI_API_KEY) || trim(TTN_KISI_API_KEY) === '' || preg_match('/[\r\n]/', TTN_KISI_API_KEY)) {
        return ttn_kisi_access_error('ttn_kisi_configuration', 'Door access credentials are invalid. Please contact support.');
    }
    foreach (array(TTN_KISI_ORGANIZATION_ID, TTN_KISI_BOOKING_GROUP_ID, TTN_KISI_ENTRANCE_LOCK_ID) as $id) {
        if (!ctype_digit((string) $id) || (int) $id < 1) {
            return ttn_kisi_access_error('ttn_kisi_configuration', 'Door access IDs are invalid. Please contact support.');
        }
    }
    return true;
}

function ttn_kisi_request($method, $path, $body = null) {
    $configured = ttn_kisi_config();
    if (is_wp_error($configured)) {
        return $configured;
    }
    $options = array(
        'method' => $method,
        'timeout' => 15,
        'redirection' => 0,
        'sslverify' => true,
        'headers' => array('Authorization' => 'KISI-LOGIN ' . trim(TTN_KISI_API_KEY), 'Accept' => 'application/json', 'Content-Type' => 'application/json'),
    );
    if ($body !== null) {
        $options['body'] = wp_json_encode($body);
    }
    $response = wp_remote_request('https://api.kisi.io' . $path, $options);
    // Only retry explicit rate limits, never ambiguous timeouts or failed POSTs.
    if (!is_wp_error($response) && (int) wp_remote_retrieve_response_code($response) === 429) {
        usleep(1100000);
        $response = wp_remote_request('https://api.kisi.io' . $path, $options);
    }
    if (is_wp_error($response)) {
        return ttn_kisi_access_error('ttn_kisi_network', 'Could not reach the door access service. Please retry or contact support.');
    }
    $status = (int) wp_remote_retrieve_response_code($response);
    if ($method === 'DELETE' && $status === 404) {
        return array();
    }
    if ($status < 200 || $status >= 300) {
        return ttn_kisi_access_error('ttn_kisi_request', 'Door access service returned HTTP ' . $status . '. Please retry or contact support.');
    }
    $text = wp_remote_retrieve_body($response);
    if ($method === 'DELETE' && trim($text) === '') {
        return array();
    }
    $data = json_decode($text, true);
    if (!is_array($data)) {
        return ttn_kisi_access_error('ttn_kisi_response', 'The door access service returned an invalid response. Please contact support.');
    }
    return $data;
}

function ttn_kisi_booking_access($user, $booking_id, $now = null) {
    if (!$user instanceof WP_User) {
        return ttn_kisi_access_error('ttn_not_authenticated', 'You must be logged in.', 401);
    }
    if (!$booking_id || get_post_type($booking_id) !== 'ttn_booking' || get_post_status($booking_id) !== 'publish') {
        return ttn_kisi_access_error('ttn_booking_missing', 'This reservation is unavailable.', 404);
    }
    $owner = (int) get_post_meta($booking_id, 'ttn_booking_user_id', true);
    $email = (string) get_post_meta($booking_id, 'ttn_booking_email', true);
    if (($owner && $owner !== (int) $user->ID) || (!$owner && strcasecmp($email, $user->user_email) !== 0)) {
        return ttn_kisi_access_error('ttn_booking_owner', 'This reservation does not belong to your account.', 403);
    }
    $now = $now === null ? time() : $now;
    $state = array('status' => 'unavailable', 'message' => '', 'booking_id' => (int) $booking_id, 'server_time' => $now * 1000, 'valid_from' => null, 'valid_until' => null);
    if (!ttn_booking_get_paid_membership_package_name($user->ID)) {
        $state['message'] = 'App door access is available only to active, paid members. Non-members enter through staff during public hours.';
        return $state;
    }
    if (get_post_meta($booking_id, 'ttn_booking_parent_id', true)
        || strtolower((string) get_post_meta($booking_id, 'ttn_booking_status', true)) !== 'confirmed'
        || strtolower((string) get_post_meta($booking_id, 'ttn_booking_payment_status', true)) !== 'paid') {
        $state['message'] = 'Door access requires a confirmed, paid reservation.';
        return $state;
    }
    $order_id = (int) get_post_meta($booking_id, 'ttn_booking_order_id', true);
    if ($order_id) {
        $order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
        if (!$order || !$order->is_paid()) {
            $state['message'] = 'Door access requires a paid reservation order.';
            return $state;
        }
    }
    $date = (string) get_post_meta($booking_id, 'ttn_booking_date', true);
    $label = (string) get_post_meta($booking_id, 'ttn_booking_time', true);
    $slots = ttn_booking_get_all_time_slots();
    $index = array_search($label, array_column($slots, 'label'), true);
    $duration_value = get_post_meta($booking_id, 'ttn_booking_duration', true);
    $duration = (int) $duration_value;
    if ($index === false || !ctype_digit((string) $duration_value) || $duration < 1 || $duration > 8
        || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
        || !preg_match('/^\d{2}:\d{2}$/D', $slots[$index]['start'])) {
        return ttn_kisi_access_error('ttn_booking_time', 'The reservation time is invalid. Please contact support.');
    }
    $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $slots[$index]['start'], wp_timezone());
    if (!$start || $start->format('Y-m-d H:i') !== $date . ' ' . $slots[$index]['start']) {
        return ttn_kisi_access_error('ttn_booking_time', 'The reservation date is invalid. Please contact support.');
    }
    $from = $start->getTimestamp() - 15 * MINUTE_IN_SECONDS;
    $until = $start->modify('+' . $duration . ' hours')->getTimestamp();
    $membership_change = get_user_meta($user->ID, '_ttn_membership_change', true);
    if (is_array($membership_change) && isset($membership_change['effective_at'])) {
        $until = min($until, (int) $membership_change['effective_at']);
        if ($until <= $from) {
            $state['message'] = 'Your scheduled membership change takes effect before this reservation. Active paid membership is required for door access.';
            return $state;
        }
    }
    $test_access = defined('TTN_KISI_TEST_ACCESS_EMAIL') && is_string(TTN_KISI_TEST_ACCESS_EMAIL)
        && TTN_KISI_TEST_ACCESS_EMAIL !== '' && strcasecmp(TTN_KISI_TEST_ACCESS_EMAIL, $user->user_email) === 0;
    if ($test_access && $now < $until) {
        $key = 'ttn_kisi_test_access_started_' . (int) $user->ID;
        $test_start = get_option($key);
        if (!$test_start) {
            add_option($key, $now, '', false);
            $test_start = get_option($key);
        }
        if (!ctype_digit((string) $test_start) || (int) $test_start < 1) {
            return ttn_kisi_access_error('ttn_kisi_test_storage', 'Could not save the test access window. Please contact support.');
        }
        $from = min($from, (int) $test_start);
    }
    $state['valid_from'] = $from * 1000;
    $state['valid_until'] = $until * 1000;
    $state['status'] = $now < $from ? 'upcoming' : ($now >= $until ? 'expired' : 'ready');
    $state['message'] = $state['status'] === 'upcoming' ? 'Access starts 15 minutes before your reservation.'
        : ($state['status'] === 'expired' ? 'Door access for this reservation has expired.' : 'Door access is available for this member reservation.');
    if ($test_access && $state['status'] === 'ready') {
        $state['message'] = 'Account-only test access is enabled before the normal booking window. Reader proximity is still required. Access ends at the reservation end or scheduled membership change, whichever is earlier.';
    }
    return $state;
}

function ttn_kisi_no_cache($data) {
    $response = rest_ensure_response($data);
    $response->header('Cache-Control', 'private, no-store, max-age=0');
    return $response;
}

function ttn_kisi_access_lock($user_id) {
    global $wpdb;
    $key = 'ttn_kisi_operation_' . (int) $user_id;
    $previous = get_option($key);
    if ($previous && (int) $previous < time() - 600) {
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $key, (string) $previous));
        wp_cache_delete($key, 'options');
    }
    if (!add_option($key, time(), '', false)) {
        return ttn_kisi_access_error('ttn_kisi_busy', 'Door access is being updated. Please retry shortly.', 409);
    }
    return $key;
}

function ttn_kisi_revoke_user_access($user_id) {
    $grant = get_user_meta($user_id, 'ttn_kisi_booking_grant', true);
    if (!is_array($grant) || empty($grant['id'])) {
        delete_user_meta($user_id, 'ttn_kisi_revoke_pending');
        return true;
    }
    $result = ttn_kisi_request('DELETE', '/role_assignments/' . (int) $grant['id']);
    if (is_wp_error($result)) {
        update_user_meta($user_id, 'ttn_kisi_revoke_pending', 1);
        update_option('ttn_kisi_access_notice', 'A booking door-access revocation failed. Retry the Kisi connection and contact support. Kisi expiry remains in effect.', false);
        if (!wp_next_scheduled('ttn_kisi_retry_revocation', array((int) $user_id))) {
            wp_schedule_single_event(time() + 60, 'ttn_kisi_retry_revocation', array((int) $user_id));
        }
        return $result;
    }
    delete_user_meta($user_id, 'ttn_kisi_booking_grant');
    delete_user_meta($user_id, 'ttn_kisi_revoke_pending');
    if (!get_users(array('meta_key' => 'ttn_kisi_revoke_pending', 'meta_value' => 1, 'fields' => 'ID', 'number' => 1))) {
        delete_option('ttn_kisi_access_notice');
    }
    return true;
}

function ttn_kisi_invalidate_user_access($user_id) {
    update_user_meta($user_id, 'ttn_kisi_revoke_pending', 1);
    $lock = ttn_kisi_access_lock($user_id);
    if (is_wp_error($lock)) {
        if (!wp_next_scheduled('ttn_kisi_retry_revocation', array((int) $user_id))) {
            wp_schedule_single_event(time() + 60, 'ttn_kisi_retry_revocation', array((int) $user_id));
        }
        return $lock;
    }
    try {
        return ttn_kisi_revoke_user_access($user_id);
    } finally {
        delete_option($lock);
    }
}

function ttn_kisi_managed_user($user) {
    $known_id = (int) get_user_meta($user->ID, 'ttn_kisi_user_id', true);
    $record = $known_id ? ttn_kisi_request('GET', '/users/' . $known_id)
        : ttn_kisi_request('GET', '/users?email=' . rawurlencode($user->user_email) . '&limit=2');
    if (is_wp_error($record)) {
        return $record;
    }
    if (!$known_id) {
        if (count($record) > 1) {
            return ttn_kisi_access_error('ttn_kisi_user', 'Multiple Kisi accounts match this member. Please contact support.');
        }
        $record = $record ? reset($record) : ttn_kisi_request('POST', '/users', array('user' => array(
            'email' => $user->user_email,
            'name' => $user->display_name,
            'confirm' => true,
            'send_emails' => false,
            'password_flow_enabled' => false,
            'access_enabled' => true,
            'metadata' => array('ttn_site' => home_url('/'), 'ttn_user_id' => (int) $user->ID),
        )));
    }
    if (is_wp_error($record)) {
        return $record;
    }
    if (!is_array($record) || empty($record['id']) || (int) ($record['organization_id'] ?? 0) !== (int) TTN_KISI_ORGANIZATION_ID
        || strcasecmp((string) ($record['email'] ?? ''), $user->user_email) !== 0
        || ($record['metadata']['ttn_site'] ?? '') !== home_url('/')
        || (int) ($record['metadata']['ttn_user_id'] ?? 0) !== (int) $user->ID
        || empty($record['access_enabled'])) {
        return ttn_kisi_access_error('ttn_kisi_user', 'This Kisi account is not a booking-managed member account. Please contact support to resolve existing access permissions.');
    }
    update_user_meta($user->ID, 'ttn_kisi_user_id', (int) $record['id']);
    return (int) $record['id'];
}

function ttn_kisi_validate_group_settings($group) {
    if ((int) ($group['id'] ?? 0) !== (int) TTN_KISI_BOOKING_GROUP_ID) {
        return ttn_kisi_access_error('ttn_kisi_group_restrictions', 'Kisi returned a different booking group than the configured ID. Please contact support.');
    }
    $settings = array(
        'reader_restriction_enabled' => array('Reader proximity restriction', true),
        'login_enabled' => array('Allow app access', true),
        'tap_to_access_restriction_enabled' => array('Reader tap to access restriction', false),
    );
    $mismatches = array();
    foreach ($settings as $field => $setting) {
        if (!array_key_exists($field, $group)) {
            $actual = 'missing from API response';
        } elseif (!is_bool($group[$field])) {
            $actual = 'invalid API value';
        } else {
            $actual = $group[$field] ? 'ON' : 'OFF';
        }
        if (!array_key_exists($field, $group) || $group[$field] !== $setting[1]) {
            $mismatches[] = $setting[0] . ': ' . $actual . ' (required ' . ($setting[1] ? 'ON' : 'OFF') . ')';
        }
    }
    if ($mismatches) {
        return ttn_kisi_access_error('ttn_kisi_group_restrictions', 'Kisi API settings for booking group '
            . (int) TTN_KISI_BOOKING_GROUP_ID . ' do not match: ' . implode('; ', $mismatches)
            . '. Save the group permissions in Kisi and retry. Missing or invalid API fields require support; access has not been granted.');
    }
    return true;
}

function ttn_kisi_provision_access($user, $state) {
    $configured = ttn_kisi_config();
    if (is_wp_error($configured)) {
        return $configured;
    }
    $kisi_id = ttn_kisi_managed_user($user);
    if (is_wp_error($kisi_id)) {
        return $kisi_id;
    }
    $teams = ttn_kisi_request('GET', '/team_memberships?member_type=User&member_id=' . $kisi_id . '&limit=1');
    if (is_wp_error($teams)) {
        return $teams;
    }
    if ($teams) {
        return ttn_kisi_access_error('ttn_kisi_teams', 'Booking-managed Kisi members must not inherit team access. Please contact support to remove unrelated permissions.');
    }
    $group = ttn_kisi_request('GET', '/groups/' . (int) TTN_KISI_BOOKING_GROUP_ID);
    if (is_wp_error($group)) {
        return $group;
    }
    $group_settings = ttn_kisi_validate_group_settings($group);
    if (is_wp_error($group_settings)) {
        return $group_settings;
    }
    $links = ttn_kisi_request('GET', '/group_locks?group_id=' . (int) TTN_KISI_BOOKING_GROUP_ID . '&limit=2');
    if (is_wp_error($links)) {
        return $links;
    }
    if (count($links) !== 1 || (int) ($links[0]['lock_id'] ?? 0) !== (int) TTN_KISI_ENTRANCE_LOCK_ID) {
        return ttn_kisi_access_error('ttn_kisi_group', 'The booking access group must contain only the configured entrance door. Please contact support.');
    }
    $roles = ttn_kisi_request('GET', '/role_assignments?assignee_type=User&assignee_id=' . $kisi_id . '&limit=100');
    if (is_wp_error($roles)) {
        return $roles;
    }
    $saved = get_user_meta($user->ID, 'ttn_kisi_booking_grant', true);
    foreach ($roles as $role) {
        if (!is_array($role) || (int) ($role['id'] ?? 0) !== (int) ($saved['id'] ?? 0)) {
            return ttn_kisi_access_error('ttn_kisi_roles', 'This Kisi member has access outside the booking integration. Please contact support; permanent access must not bypass booking expiry.');
        }
    }
    if (is_array($saved) && (int) $saved['booking_id'] === $state['booking_id']
        && (int) $saved['valid_from'] === $state['valid_from'] && (int) $saved['valid_until'] === $state['valid_until']
        && !get_user_meta($user->ID, 'ttn_kisi_revoke_pending', true) && count($roles) === 1
        && ($roles[0]['role_id'] ?? '') === 'group_basic'
        && ($roles[0]['applies_to_type'] ?? '') === 'Group'
        && (int) ($roles[0]['applies_to_id'] ?? 0) === (int) TTN_KISI_BOOKING_GROUP_ID
        && ($roles[0]['assignee_type'] ?? '') === 'User' && (int) ($roles[0]['assignee_id'] ?? 0) === $kisi_id
        && strtotime($roles[0]['valid_from'] ?? '') * 1000 === $state['valid_from']
        && strtotime($roles[0]['valid_until'] ?? '') * 1000 === $state['valid_until']) {
        return $kisi_id;
    }
    $revoked = ttn_kisi_revoke_user_access($user->ID);
    if (is_wp_error($revoked)) {
        return $revoked;
    }
    $grant = ttn_kisi_request('POST', '/role_assignments', array('role_assignment' => array(
        'applies_to_type' => 'Group', 'applies_to_id' => (int) TTN_KISI_BOOKING_GROUP_ID,
        'assignee_type' => 'User', 'assignee_id' => $kisi_id, 'role_id' => 'group_basic', 'notify' => false,
        'valid_from' => gmdate('Y-m-d\TH:i:s\Z', $state['valid_from'] / 1000),
        'valid_until' => gmdate('Y-m-d\TH:i:s\Z', $state['valid_until'] / 1000),
    )));
    if (is_wp_error($grant)) {
        return $grant;
    }
    if (empty($grant['id'])) {
        return ttn_kisi_access_error('ttn_kisi_grant', 'Kisi did not return an access grant. Please contact support.');
    }
    update_user_meta($user->ID, 'ttn_kisi_booking_grant', array(
        'id' => (int) $grant['id'], 'booking_id' => $state['booking_id'], 'valid_from' => $state['valid_from'], 'valid_until' => $state['valid_until'],
    ));
    if (($grant['role_id'] ?? '') !== 'group_basic' || ($grant['applies_to_type'] ?? '') !== 'Group'
        || (int) ($grant['applies_to_id'] ?? 0) !== (int) TTN_KISI_BOOKING_GROUP_ID
        || ($grant['assignee_type'] ?? '') !== 'User' || (int) ($grant['assignee_id'] ?? 0) !== $kisi_id
        || strtotime($grant['valid_from'] ?? '') * 1000 !== $state['valid_from']
        || strtotime($grant['valid_until'] ?? '') * 1000 !== $state['valid_until']) {
        ttn_kisi_revoke_user_access($user->ID);
        return ttn_kisi_access_error('ttn_kisi_grant', 'Kisi did not confirm the restricted booking access window. Please contact support.');
    }
    wp_schedule_single_event((int) ($state['valid_until'] / 1000) + 1, 'ttn_kisi_reconcile_access', array((int) $user->ID));
    return $kisi_id;
}

function ttn_kisi_route_status(WP_REST_Request $request) {
    $user = $request->get_param('ttn_auth_user');
    $state = ttn_kisi_booking_access($user, absint($request->get_param('booking_id')));
    if (!is_wp_error($state)) {
        $grant = get_user_meta($user->ID, 'ttn_kisi_booking_grant', true);
        if (is_array($grant) && (int) $grant['booking_id'] === $state['booking_id']
            && ($state['status'] !== 'ready' || $state['valid_from'] !== (int) $grant['valid_from'] || $state['valid_until'] !== (int) $grant['valid_until'])) {
            $revoked = ttn_kisi_invalidate_user_access($user->ID);
            if (is_wp_error($revoked)) {
                return $revoked;
            }
        }
        if (get_user_meta($user->ID, 'ttn_kisi_revoke_pending', true)) {
            return ttn_kisi_access_error('ttn_kisi_revocation', 'Door access is being revoked or updated. Please retry shortly or contact support.');
        }
    }
    return is_wp_error($state) ? $state : ttn_kisi_no_cache($state);
}

function ttn_kisi_route_credentials(WP_REST_Request $request) {
    $user = $request->get_param('ttn_auth_user');
    $state = ttn_kisi_booking_access($user, absint($request->get_param('booking_id')));
    if (is_wp_error($state)) {
        return $state;
    }
    if ($state['status'] !== 'ready') {
        return ttn_kisi_access_error('ttn_kisi_ineligible', $state['message'], 403);
    }
    $platform = $request->get_param('platform');
    if (!in_array($platform, array('ios', 'android'), true)) {
        return ttn_kisi_access_error('ttn_kisi_platform', 'Door access requires an iOS or Android device.', 400);
    }
    $rate_key = 'ttn_kisi_device_' . $user->ID;
    if (get_transient($rate_key)) {
        return ttn_kisi_access_error('ttn_kisi_rate', 'Please wait one minute before retrying door access.', 429);
    }
    $lock = ttn_kisi_access_lock($user->ID);
    if (is_wp_error($lock)) {
        return $lock;
    }
    try {
        set_transient($rate_key, 1, MINUTE_IN_SECONDS);
        $kisi_id = ttn_kisi_provision_access($user, $state);
        if (is_wp_error($kisi_id)) {
            return $kisi_id;
        }
        $login = ttn_kisi_request('POST', '/logins', array('login' => array(
            'type' => 'device', 'name' => 'Tee Time Nexus mobile', 'email' => $user->user_email, 'expire' => true,
            'os_name' => $platform === 'ios' ? 'iOS' : 'Android',
            'device_brand' => $platform === 'ios' ? 'Apple' : 'Android', 'device_model' => 'Tee Time Nexus mobile',
        ), 'user' => new stdClass()));
        if (is_wp_error($login)) {
            return $login;
        }
        if ((int) ($login['user_id'] ?? 0) !== $kisi_id || ($login['type'] ?? '') !== 'device'
            || empty($login['id']) || empty($login['secret'])
            || empty($login['scram_credentials']['phone_key']) || empty($login['scram_credentials']['online_certificate'])) {
            return ttn_kisi_access_error('ttn_kisi_login', 'Kisi did not issue valid member device credentials. Please contact support.');
        }
        wp_cache_delete($state['booking_id'], 'post_meta');
        wp_cache_delete($user->ID, 'user_meta');
        $latest = ttn_kisi_booking_access($user, $state['booking_id']);
        if (is_wp_error($latest) || $latest['status'] !== 'ready' || $latest['valid_until'] !== $state['valid_until'] || $latest['valid_from'] !== $state['valid_from']
            || get_user_meta($user->ID, 'ttn_kisi_revoke_pending', true)) {
            ttn_kisi_revoke_user_access($user->ID);
            return ttn_kisi_access_error('ttn_kisi_changed', 'Your booking access changed. Please retry.', 409);
        }
        return ttn_kisi_no_cache(array_merge($latest, array(
            'lock_id' => (int) TTN_KISI_ENTRANCE_LOCK_ID,
            'credential' => array(
                'organizationId' => (int) TTN_KISI_ORGANIZATION_ID, 'loginId' => (int) $login['id'],
                'secret' => $login['secret'], 'phoneKey' => $login['scram_credentials']['phone_key'],
                'onlineCertificate' => $login['scram_credentials']['online_certificate'],
                'validFrom' => $state['valid_from'], 'validUntil' => $state['valid_until'],
            ),
        )));
    } finally {
        delete_option($lock);
    }
}

function ttn_kisi_booking_changed($meta_id, $post_id, $key, $value) {
    if (get_post_type($post_id) !== 'ttn_booking' || !in_array($key, array(
        'ttn_booking_date', 'ttn_booking_time', 'ttn_booking_duration', 'ttn_booking_status',
        'ttn_booking_payment_status', 'ttn_booking_user_id', 'ttn_booking_email', 'ttn_booking_order_id',
        'ttn_booking_parent_id',
    ), true)) {
        return;
    }
    $users = get_users(array('meta_key' => 'ttn_kisi_booking_grant', 'fields' => 'ID'));
    foreach ($users as $id) {
        $grant = get_user_meta($id, 'ttn_kisi_booking_grant', true);
        if (is_array($grant) && (int) $grant['booking_id'] === (int) $post_id) {
            ttn_kisi_invalidate_user_access($id);
        }
    }
}
add_action('updated_post_meta', 'ttn_kisi_booking_changed', 10, 4);
add_action('added_post_meta', 'ttn_kisi_booking_changed', 10, 4);
add_action('deleted_post_meta', 'ttn_kisi_booking_changed', 10, 4);
add_action('before_delete_post', function ($id) { ttn_kisi_booking_changed(0, $id, 'ttn_booking_status', 'deleted'); });
add_action('wp_trash_post', function ($id) { ttn_kisi_booking_changed(0, $id, 'ttn_booking_status', 'trashed'); });
add_action('transition_post_status', function ($new, $old, $post) {
    if ($new !== 'publish' && $post->post_type === 'ttn_booking') {
        ttn_kisi_booking_changed(0, $post->ID, 'ttn_booking_status', $new);
    }
}, 10, 3);
add_action('ttn_kisi_retry_revocation', function ($user_id) {
    if (get_user_meta($user_id, 'ttn_kisi_revoke_pending', true)) {
        ttn_kisi_invalidate_user_access($user_id);
    }
});
add_action('ttn_membership_record_saved', function ($user_id) {
    if (!ttn_booking_get_paid_membership_package_name($user_id)) {
        ttn_kisi_invalidate_user_access($user_id);
    }
});
add_action('ttn_kisi_reconcile_access', function ($user_id) {
    $grant = get_user_meta($user_id, 'ttn_kisi_booking_grant', true);
    if (!is_array($grant)) {
        return;
    }
    $state = ttn_kisi_booking_access(get_userdata($user_id), (int) $grant['booking_id']);
    if (is_wp_error($state) || $state['status'] !== 'ready' || $state['valid_from'] !== (int) $grant['valid_from']
        || $state['valid_until'] !== (int) $grant['valid_until']) {
        ttn_kisi_invalidate_user_access($user_id);
    }
});
add_action('ttn_membership_change_scheduled', function ($user_id) {
    do_action('ttn_kisi_reconcile_access', $user_id);
});
add_filter('cron_schedules', function ($schedules) {
    $schedules['ttn_kisi_five_minutes'] = array('interval' => 300, 'display' => 'Kisi access reconciliation');
    return $schedules;
});
add_action('init', function () {
    if (!wp_next_scheduled('ttn_kisi_reconcile_all')) {
        wp_schedule_event(time() + 300, 'ttn_kisi_five_minutes', 'ttn_kisi_reconcile_all');
    }
});
add_action('ttn_kisi_reconcile_all', function () {
    foreach (get_users(array('meta_key' => 'ttn_kisi_booking_grant', 'fields' => 'ID')) as $user_id) {
        do_action('ttn_kisi_reconcile_access', $user_id);
    }
});
add_action('woocommerce_order_status_changed', function ($order_id) {
    $users = get_users(array('meta_key' => 'ttn_kisi_booking_grant', 'fields' => 'ID'));
    foreach ($users as $id) {
        $grant = get_user_meta($id, 'ttn_kisi_booking_grant', true);
        if (is_array($grant) && (int) get_post_meta($grant['booking_id'], 'ttn_booking_order_id', true) === (int) $order_id) {
            ttn_kisi_invalidate_user_access($id);
        }
    }
});
add_action('admin_notices', function () {
    $notice = get_option('ttn_kisi_access_notice');
    if ($notice && current_user_can('manage_options')) {
        echo '<div class="notice notice-error"><p>' . esc_html($notice) . '</p></div>';
    }
});
add_action('rest_api_init', function () {
    $args = array('booking_id' => array('required' => true, 'validate_callback' => function ($value) {
        return ctype_digit((string) $value) && (int) $value > 0;
    }));
    register_rest_route('ttn/v1', '/door-access', array(
        'methods' => 'GET', 'callback' => 'ttn_kisi_route_status', 'permission_callback' => 'ttn_jwt_authenticate_request', 'args' => $args,
    ));
    register_rest_route('ttn/v1', '/door-access/credentials', array(
        'methods' => 'POST', 'callback' => 'ttn_kisi_route_credentials', 'permission_callback' => 'ttn_jwt_authenticate_request', 'args' => $args,
    ));
});
