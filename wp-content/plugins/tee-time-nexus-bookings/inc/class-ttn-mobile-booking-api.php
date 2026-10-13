<?php
/**
 * REST endpoints exposing booking data and account-owned reservation management.
 * Reuses the existing booking business logic — no duplicated pricing/availability rules.
 */

if (!defined('ABSPATH')) {
    exit;
}

function ttn_mobile_booking_route_bays() {
    $bays = array();
    foreach (ttn_booking_get_bay_configs() as $bay_key => $bay) {
        $bays[] = array(
            'key' => $bay_key,
            'name' => $bay['name'],
            'type' => $bay['type'],
            'location' => $bay['location'],
            'premium' => !empty($bay['premium']),
            'hourly_price' => ttn_booking_get_hourly_price($bay_key),
            'thumbnail_url' => ttn_booking_get_bay_thumbnail_url($bay_key, 'medium'),
        );
    }

    return rest_ensure_response($bays);
}

function ttn_mobile_booking_route_time_slots() {
    return rest_ensure_response(ttn_booking_get_public_time_slots());
}

function ttn_mobile_booking_route_member_time_slots(WP_REST_Request $request) {
    $user = $request->get_param('ttn_auth_user');
    if (!$user instanceof WP_User) {
        return new WP_Error('ttn_not_authenticated', 'You must be logged in.', array('status' => 401));
    }

    return rest_ensure_response(array(
        'slots' => ttn_booking_get_time_slots($user->ID),
        'min_booking_date' => current_time('Y-m-d'),
        'max_booking_date' => ttn_booking_get_membership_max_booking_date($user->ID),
        'booking_window_days' => ttn_booking_get_membership_booking_window_days($user->ID),
        'member_package' => ttn_booking_get_paid_membership_package_name($user->ID),
    ));
}

function ttn_mobile_booking_route_availability(WP_REST_Request $request) {
    $bay = sanitize_text_field($request->get_param('bay'));
    $date = sanitize_text_field($request->get_param('date'));

    if (!ttn_booking_get_bay_config($bay) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return new WP_Error('ttn_invalid_availability_request', 'A valid bay and date (YYYY-MM-DD) are required.', array('status' => 400));
    }

    return rest_ensure_response(array(
        'booked_times' => ttn_booking_get_booked_slots($bay, $date),
    ));
}

function ttn_mobile_booking_route_my_bookings(WP_REST_Request $request) {
    $user = $request->get_param('ttn_auth_user');
    if (!$user instanceof WP_User) {
        return new WP_Error('ttn_not_authenticated', 'You must be logged in.', array('status' => 401));
    }

    return rest_ensure_response(ttn_get_user_bookings($user->user_email, $user->ID));
}

// Same inputs and helpers as /checkout/start, so the app shows exactly what the WooCommerce cart will charge.
function ttn_mobile_booking_route_quote(WP_REST_Request $request) {
    $user = $request->get_param('ttn_auth_user');
    if (!$user instanceof WP_User) {
        return new WP_Error('ttn_not_authenticated', 'You must be logged in.', array('status' => 401));
    }

    $bay = sanitize_text_field($request->get_param('bay'));
    $date = sanitize_text_field($request->get_param('date'));
    $time = sanitize_text_field($request->get_param('time'));
    $duration = max(1, min(8, intval($request->get_param('duration'))));
    $players = max(1, min(4, intval($request->get_param('players'))));
    if (!ttn_booking_get_bay_config($bay) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !$time) {
        return new WP_Error('ttn_invalid_quote', 'A valid bay, date, and time are required.', array('status' => 400));
    }

    $hourly_price = (float) ttn_booking_get_hourly_price($bay);
    $free_hours = max(0, min($duration, (int) ttn_booking_get_member_free_hours($user->ID, $date, $time, $duration)));
    $guest_charge = (float) ttn_booking_get_member_guest_charge($user->ID, $players, $duration);
    $response = rest_ensure_response(array(
        'hourly_price' => $hourly_price,
        'duration' => $duration,
        'member_free_hours' => $free_hours,
        'member_guest_charge' => $guest_charge,
        'total' => round($hourly_price * ($duration - $free_hours) + $guest_charge, 2),
        'member_package' => ttn_booking_get_paid_membership_package_name($user->ID),
    ));
    $response->header('Cache-Control', 'private, no-store');
    return $response;
}

function ttn_mobile_booking_route_free_hours(WP_REST_Request $request) {
    $user = $request->get_param('ttn_auth_user');
    if (!$user instanceof WP_User) {
        return new WP_Error('ttn_not_authenticated', 'You must be logged in.', array('status' => 401));
    }
    $date = sanitize_text_field((string) ($request->get_param('date') ?: current_time('Y-m-d')));
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        return new WP_Error('ttn_invalid_date', 'A valid date (YYYY-MM-DD) is required.', array('status' => 400));
    }
    $allowance = (int) ttn_booking_get_member_daily_free_hours($user->ID);
    $used = $allowance ? (int) ttn_booking_get_member_free_hours_used($user->ID, $date) : 0;
    $response = rest_ensure_response(array(
        'date' => $date,
        'daily_allowance' => $allowance,
        'used' => $used,
        'remaining' => max(0, $allowance - $used),
    ));
    $response->header('Cache-Control', 'private, no-store');
    return $response;
}

function ttn_booking_management_revision($booking_id) {
    $values = array();
    foreach (array('bay', 'date', 'time', 'duration', 'players', 'status', 'updated_at', 'total_price', 'member_free_hours', 'order_id', 'extension_order_ids') as $key) {
        $values[$key] = get_post_meta($booking_id, 'ttn_booking_' . $key, true);
    }
    return hash('sha256', wp_json_encode($values));
}

function ttn_booking_management_error($message, $status = 409) {
    return new WP_Error('ttn_booking_management', $message, array('status' => $status));
}

function ttn_booking_management_booking($booking_id, $user) {
    if (!$user instanceof WP_User) {
        return ttn_booking_management_error('You must be logged in.', 401);
    }
    $post = get_post($booking_id);
    if (!$post || $post->post_type !== 'ttn_booking'
        || $post->post_status !== 'publish'
        || get_post_meta($booking_id, 'ttn_booking_parent_id', true)
        || !ttn_booking_belongs_to_user($booking_id, $user->user_email, $user->ID)) {
        return ttn_booking_management_error('Reservation not found.', 404);
    }
    return array(
        'ID' => (int) $booking_id,
        'bay' => (string) get_post_meta($booking_id, 'ttn_booking_bay', true),
        'date' => (string) get_post_meta($booking_id, 'ttn_booking_date', true),
        'time' => (string) get_post_meta($booking_id, 'ttn_booking_time', true),
        'duration' => max(1, (int) get_post_meta($booking_id, 'ttn_booking_duration', true)),
        'players' => max(1, (int) get_post_meta($booking_id, 'ttn_booking_players', true)),
        'status' => (string) get_post_meta($booking_id, 'ttn_booking_status', true),
    );
}

function ttn_booking_management_allowed($booking) {
    if ($booking['status'] === 'cancelled') {
        return ttn_booking_management_error('This reservation is already cancelled.');
    }
    if (!ttn_booking_is_within_self_service_window($booking['date'], $booking['time'])) {
        return ttn_booking_management_error('Changes are available until 24 hours before your reservation. Please call +1 (980) 503-3288 for help with this booking.');
    }
    return true;
}

function ttn_booking_prepare_schedule_change($booking_id, $user, $selection) {
    $booking = ttn_booking_management_booking($booking_id, $user);
    if (is_wp_error($booking)) {
        return $booking;
    }
    $allowed = ttn_booking_management_allowed($booking);
    if (is_wp_error($allowed)) {
        return $allowed;
    }
    $bay = sanitize_text_field($selection['bay'] ?? '');
    $date = sanitize_text_field($selection['date'] ?? '');
    $time = sanitize_text_field($selection['time'] ?? '');
    $duration = filter_var($selection['duration'] ?? null, FILTER_VALIDATE_INT);
    $parsed_date = DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());
    if (!ttn_booking_get_bay_config($bay) || !$parsed_date || $parsed_date->format('Y-m-d') !== $date
        || !ttn_booking_is_date_within_membership_window($user->ID, $date)
        || $duration === false || $duration < 1 || $duration > 8) {
        return ttn_booking_management_error('Choose a valid bay, date within your booking window, and duration from 1 to 8 hours.', 400);
    }
    $slots = ttn_booking_get_time_slots($user->ID);
    $index = array_search($time, array_column($slots, 'label'), true);
    if ($index === false || $index + $duration > count($slots) || ttn_booking_is_slot_in_past($date, $slots[$index]['start'])) {
        return ttn_booking_management_error('Choose an available future start time with enough consecutive hours.', 400);
    }
    $records = array_values(array_filter(ttn_booking_get_booking_records(), function ($record) use ($booking_id) {
        return (int) $record['id'] !== (int) $booking_id
            && (int) get_post_meta($record['id'], 'ttn_booking_parent_id', true) !== (int) $booking_id;
    }));
    if (!ttn_booking_check_availability_for_duration($bay, $date, $time, $duration, $slots, $records)) {
        return ttn_booking_management_error('That time is no longer available. Please choose another time.');
    }
    $old_free = get_post_meta($booking_id, 'ttn_booking_member_free_hours', true);
    if ($old_free === '') {
        $old_free = ttn_booking_backfill_member_free_hours($booking_id);
    }
    $used = ttn_booking_get_member_free_hours_used($user->ID, $date);
    if ($date === $booking['date']) {
        $used = max(0, $used - (int) $old_free);
    }
    $free = max(0, min($duration, ttn_booking_get_member_daily_free_hours($user->ID) - $used));
    $guest_charge = ttn_booking_get_member_guest_charge($user->ID, $booking['players'], $duration);
    $new_total = round(ttn_booking_get_hourly_price($bay) * ($duration - $free) + $guest_charge, 2);
    $stored_total = get_post_meta($booking_id, 'ttn_booking_total_price', true);
    $old_total = $stored_total !== '' ? (float) $stored_total : $booking['duration'] * ttn_booking_get_hourly_price($booking['bay']);
    $data = array(
        'booking_id' => (int) $booking_id, 'bay' => $bay, 'date' => $date, 'time' => $time,
        'duration' => $duration, 'member_free_hours' => $free, 'guest_charge' => $guest_charge,
        'new_total_price' => $new_total, 'difference' => round($new_total - $old_total, 2),
        'revision' => ttn_booking_management_revision($booking_id),
    );
    $data['quote_id'] = hash('sha256', wp_json_encode($data));
    return $data;
}

function ttn_mobile_booking_management_response($data) {
    $response = rest_ensure_response($data);
    $response->header('Cache-Control', 'private, no-store');
    return $response;
}

function ttn_mobile_booking_route_management(WP_REST_Request $request) {
    $booking_id = absint($request->get_param('id'));
    $user = $request->get_param('ttn_auth_user');
    $booking = ttn_booking_management_booking($booking_id, $user);
    if (is_wp_error($booking)) {
        return $booking;
    }
    $allowed = ttn_booking_management_allowed($booking);
    return ttn_mobile_booking_management_response(array(
        'booking' => $booking,
        'can_manage' => !is_wp_error($allowed),
        'reason' => is_wp_error($allowed) ? $allowed->get_error_message() : '',
        'revision' => ttn_booking_management_revision($booking_id),
    ));
}

function ttn_mobile_booking_route_reschedule_quote(WP_REST_Request $request) {
    $data = ttn_booking_prepare_schedule_change(absint($request->get_param('id')), $request->get_param('ttn_auth_user'), $request->get_json_params() ?: array());
    return is_wp_error($data) ? $data : ttn_mobile_booking_management_response($data);
}

function ttn_mobile_booking_route_change(WP_REST_Request $request) {
    $booking_id = absint($request->get_param('id'));
    $user = $request->get_param('ttn_auth_user');
    $booking = ttn_booking_management_booking($booking_id, $user);
    if (is_wp_error($booking)) {
        return $booking;
    }
    if (!ttn_booking_acquire_change_lock($booking_id)) {
        return ttn_booking_management_error('This reservation is being changed. Please try again shortly.');
    }
    try {
        $allowed = ttn_booking_management_allowed($booking);
        if (is_wp_error($allowed)) {
            return $allowed;
        }
        if (!hash_equals(ttn_booking_management_revision($booking_id), (string) $request->get_param('revision'))) {
            return ttn_booking_management_error('Your reservation has changed. Reload it before continuing.');
        }
        if ($request->get_param('action') === 'cancel') {
            $result = ttn_crud_cancel_user_booking($booking_id, $user->user_email);
        } else {
            $data = ttn_booking_prepare_schedule_change($booking_id, $user, $request->get_json_params() ?: array());
            if (is_wp_error($data)) {
                return $data;
            }
            if (!hash_equals($data['quote_id'], (string) $request->get_param('quote_id'))) {
                return ttn_booking_management_error('The price or availability has changed. Review the new price before confirming.');
            }
            if ($data['difference'] > 0) {
                $token = wp_generate_password(48, false);
                set_transient('ttn_mobile_bridge_' . hash('sha256', $token), array(
                    'user_id' => $user->ID, 'type' => 'reschedule', 'data' => $data,
                ), TTN_MOBILE_BRIDGE_TTL);
                return ttn_mobile_booking_management_response(array(
                    'success' => true, 'message' => 'Complete payment to change your reservation.',
                    'bridge_url' => add_query_arg('ttn_mobile_bridge', $token, home_url('/')),
                ));
            }
            $result = ttn_apply_booking_schedule_update($booking_id, $data);
        }
        return empty($result['success'])
            ? ttn_booking_management_error($result['message'])
            : ttn_mobile_booking_management_response($result);
    } finally {
        ttn_booking_release_change_lock($booking_id);
    }
}

function ttn_booking_acquire_change_lock($booking_id) {
    $key = 'ttn_booking_change_' . $booking_id;
    $token = wp_generate_password(32, false);
    $value = wp_json_encode(array('token' => $token, 'expires' => time() + 300));
    if (!add_option($key, $value, '', false)) {
        $old = get_option($key);
        $decoded = json_decode((string) $old, true);
        if (!is_array($decoded) || ($decoded['expires'] ?? PHP_INT_MAX) > time()) {
            return false;
        }
        global $wpdb;
        // Compare-and-delete prevents an expired lock cleanup from removing a newer lock.
        if (!$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $key, $old))) {
            return false;
        }
        wp_cache_delete($key, 'options');
        if (!add_option($key, $value, '', false)) {
            return false;
        }
    }
    $GLOBALS['ttn_booking_change_locks'][$booking_id] = $value;
    return true;
}

function ttn_booking_release_change_lock($booking_id) {
    $key = 'ttn_booking_change_' . $booking_id;
    $value = $GLOBALS['ttn_booking_change_locks'][$booking_id] ?? null;
    if ($value !== null && get_option($key) === $value) {
        delete_option($key);
    }
    unset($GLOBALS['ttn_booking_change_locks'][$booking_id]);
}

add_action('rest_api_init', function () {
    register_rest_route('ttn/v1', '/bookings/(?P<id>\d+)/manage', array(
        'methods' => 'GET', 'callback' => 'ttn_mobile_booking_route_management',
        'permission_callback' => 'ttn_jwt_authenticate_request',
    ));
    register_rest_route('ttn/v1', '/bookings/(?P<id>\d+)/reschedule-quote', array(
        'methods' => 'POST', 'callback' => 'ttn_mobile_booking_route_reschedule_quote',
        'permission_callback' => 'ttn_jwt_authenticate_request',
    ));
    register_rest_route('ttn/v1', '/bookings/(?P<id>\d+)/(?P<action>cancel|reschedule)', array(
        'methods' => 'POST', 'callback' => 'ttn_mobile_booking_route_change',
        'permission_callback' => 'ttn_jwt_authenticate_request',
    ));
    register_rest_route('ttn/v1', '/member/free-hours', array(
        'methods' => 'GET',
        'callback' => 'ttn_mobile_booking_route_free_hours',
        'permission_callback' => 'ttn_jwt_authenticate_request',
    ));
    register_rest_route('ttn/v1', '/booking-quote', array(
        'methods' => 'GET',
        'callback' => 'ttn_mobile_booking_route_quote',
        'permission_callback' => 'ttn_jwt_authenticate_request',
    ));

    register_rest_route('ttn/v1', '/bays', array(
        'methods' => 'GET',
        'callback' => 'ttn_mobile_booking_route_bays',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('ttn/v1', '/time-slots', array(
        'methods' => 'GET',
        'callback' => 'ttn_mobile_booking_route_time_slots',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('ttn/v1', '/member/time-slots', array(
        'methods' => 'GET',
        'callback' => 'ttn_mobile_booking_route_member_time_slots',
        'permission_callback' => 'ttn_jwt_authenticate_request',
    ));

    register_rest_route('ttn/v1', '/availability', array(
        'methods' => 'GET',
        'callback' => 'ttn_mobile_booking_route_availability',
        'permission_callback' => '__return_true',
        'args' => array(
            'bay' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
            'date' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
        ),
    ));

    register_rest_route('ttn/v1', '/bookings/me', array(
        'methods' => 'GET',
        'callback' => 'ttn_mobile_booking_route_my_bookings',
        'permission_callback' => 'ttn_jwt_authenticate_request',
    ));
});
