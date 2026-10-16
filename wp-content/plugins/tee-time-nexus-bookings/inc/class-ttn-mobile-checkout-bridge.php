<?php
/**
 * Mobile-app signature/terms verification and checkout handoff.
 * Reuses the same storage/cart logic as the website flow — no duplicated business rules.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TTN_MOBILE_BRIDGE_TTL', 2 * MINUTE_IN_SECONDS);

function ttn_mobile_get_checkout_contact_defaults($user_id) {
    $user = get_userdata($user_id);
    if (!$user) {
        return array();
    }
    return array(
        'billing_first_name' => (string) get_user_meta($user_id, 'first_name', true),
        'billing_last_name' => (string) get_user_meta($user_id, 'last_name', true),
        'billing_email' => $user->user_email,
        'billing_phone' => (string) get_user_meta($user_id, 'phone_number', true),
    );
}

function ttn_mobile_prefill_checkout_contact($value, $input) {
    if ($value !== null && $value !== '') {
        return $value;
    }
    $user_id = get_current_user_id();
    if (!$user_id) {
        return $value;
    }
    $defaults = ttn_mobile_get_checkout_contact_defaults($user_id);
    if (!isset($defaults[$input])) {
        return $value;
    }
    $saved = (string) get_user_meta($user_id, $input, true);
    return $saved !== '' ? $saved : ($defaults[$input] !== '' ? $defaults[$input] : $value);
}
add_filter('woocommerce_checkout_get_value', 'ttn_mobile_prefill_checkout_contact', 10, 2);

function ttn_mobile_route_verification_status(WP_REST_Request $request) {
    return rest_ensure_response(array(
        'verified' => true,
    ));
}

/**
 * Deletes a previously stored secure document, guarding against path traversal.
 */
function ttn_mobile_delete_secure_file($filename) {
    if (!$filename || strpos($filename, '/') !== false || strpos($filename, '..') !== false) {
        return;
    }

    $secure_dir = ttn_booking_get_secure_upload_dir();
    $path = realpath(trailingslashit($secure_dir) . $filename);
    if ($path && strpos($path, realpath($secure_dir)) === 0 && is_file($path)) {
        @unlink($path);
    }
}

function ttn_mobile_route_submit_verification(WP_REST_Request $request) {
    $user = $request->get_param('ttn_auth_user');
    if (!$user instanceof WP_User) {
        return new WP_Error('ttn_not_authenticated', 'You must be logged in.', array('status' => 401));
    }

    // Throttle repeated verification submissions per account.
    $rate_key = 'ttn_verify_attempts_' . $user->ID;
    $attempts = (int) get_transient($rate_key);
    if ($attempts >= 5) {
        return new WP_Error('ttn_too_many_attempts', 'Too many verification attempts. Please try again later.', array('status' => 429));
    }
    set_transient($rate_key, $attempts + 1, HOUR_IN_SECONDS);

    if (empty($request->get_param('terms_accepted'))) {
        return new WP_Error('ttn_terms_required', 'You must accept the Terms and Conditions.', array('status' => 400));
    }

    $signature_filename = ttn_booking_store_signature_image($request->get_param('signature'));
    if (is_wp_error($signature_filename)) {
        return $signature_filename;
    }

    // Replace (not accumulate) any signature previously on file for this user.
    ttn_mobile_delete_secure_file(get_user_meta($user->ID, 'ttn_user_signature_file', true));

    update_user_meta($user->ID, 'ttn_user_signature_file', $signature_filename);
    update_user_meta($user->ID, 'ttn_user_terms_accepted_at', current_time('mysql'));

    return rest_ensure_response(array('verified' => true));
}

function ttn_mobile_route_start_checkout(WP_REST_Request $request) {
    $user = $request->get_param('ttn_auth_user');
    if (!$user instanceof WP_User) {
        return new WP_Error('ttn_not_authenticated', 'You must be logged in.', array('status' => 401));
    }

    if (!ttn_booking_user_can_make_reservations($user->ID)) {
        return new WP_Error('ttn_booking_test_mode', 'Booking is currently limited to approved testers.', array('status' => 403));
    }

    $bay = sanitize_text_field($request->get_param('bay'));
    $date = sanitize_text_field($request->get_param('date'));
    $time = sanitize_text_field($request->get_param('time'));
    $duration = max(1, min(8, intval($request->get_param('duration'))));
    $players = max(1, min(4, intval($request->get_param('players'))));

    if (!ttn_booking_get_bay_config($bay) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !$time || !ttn_booking_is_date_within_membership_window($user->ID, $date)) {
        return new WP_Error('ttn_invalid_booking', 'Invalid bay, date, or time selection.', array('status' => 400));
    }

    $time_slots = ttn_booking_get_time_slots($user->ID);
    $bookings = ttn_booking_get_booking_records();
    $slot_index = array_search($time, array_column($time_slots, 'label'), true);
    if ($slot_index === false || ttn_booking_is_slot_in_past($date, $time_slots[$slot_index]['start']) || !ttn_booking_check_availability_for_duration($bay, $date, $time, $duration, $time_slots, $bookings)) {
        return new WP_Error('ttn_slot_unavailable', 'That time is no longer available. Please choose another time.', array('status' => 409));
    }

    if (!function_exists('WC') || !function_exists('wc_load_cart') || !function_exists('wc_get_checkout_url')) {
        return new WP_Error('ttn_checkout_unavailable', 'Checkout is currently unavailable. Please try again shortly.', array('status' => 503));
    }

    $booking_data = array(
        'bay' => $bay,
        'date' => $date,
        'time' => $time,
        'duration' => $duration,
        'players' => $players,
        'club_preference' => 'own-clubs',
        'member_free_hours' => ttn_booking_get_member_free_hours($user->ID, $date, $time, $duration),
        'member_guest_charge' => ttn_booking_get_member_guest_charge($user->ID, $players, $duration),
        'member_overnight' => (bool) ttn_booking_get_paid_membership_package_name($user->ID),
        'signature' => get_user_meta($user->ID, 'ttn_user_signature_file', true),
        'terms_accepted_at' => get_user_meta($user->ID, 'ttn_user_terms_accepted_at', true),
    );

    $raw_token = wp_generate_password(48, false);
    set_transient('ttn_mobile_bridge_' . hash('sha256', $raw_token), array(
        'user_id' => $user->ID,
        'type' => 'booking',
        'data' => $booking_data,
    ), TTN_MOBILE_BRIDGE_TTL);

    return rest_ensure_response(array(
        'bridge_url' => add_query_arg('ttn_mobile_bridge', $raw_token, home_url('/')),
    ));
}

/**
 * Consumes a one-time mobile bridge token: logs the browser session in as the same user whose
 * cart was just populated via the REST checkout call, then hands off to the normal WooCommerce
 * checkout page. This is the only place a JWT-authenticated app session is converted into a
 * regular WordPress cookie session, and the token is single-use and short-lived (2 minutes).
 */
function ttn_mobile_handle_checkout_bridge() {
    if (empty($_GET['ttn_mobile_bridge'])) {
        return;
    }

    $raw_token = sanitize_text_field(wp_unslash($_GET['ttn_mobile_bridge']));
    $key = 'ttn_mobile_bridge_' . hash('sha256', $raw_token);
    $bridge = get_transient($key);

    if (!is_array($bridge) || empty($bridge['user_id'])) {
        wp_die(__('This checkout link has expired. Please start your booking again in the app.', 'tee-time-nexus-bookings'));
    }

    delete_transient($key);
    $user_id = (int) $bridge['user_id'];
    if ($bridge['type'] === 'reschedule') {
        $data = ttn_booking_prepare_schedule_change($bridge['data']['booking_id'], get_user_by('id', $user_id), $bridge['data']);
        if (is_wp_error($data) || !hash_equals($data['quote_id'], (string) $bridge['data']['quote_id'])) {
            wp_die(is_wp_error($data) ? esc_html($data->get_error_message()) : 'The price or availability changed. Return to the app and review your reservation.');
        }
    }
    if (function_exists('golf_simulator_theme_mark_app_checkout')) {
        golf_simulator_theme_mark_app_checkout();
    }
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);

    if ($bridge['type'] === 'booking') {
        $data = $bridge['data'];
        $time_slots = !empty($data['member_overnight']) ? ttn_booking_get_all_time_slots() : ttn_booking_get_public_time_slots();
        $bookings = ttn_booking_get_booking_records();
        if (!ttn_booking_check_availability_for_duration($data['bay'], $data['date'], $data['time'], $data['duration'], $time_slots, $bookings)) {
            wp_die(__('This tee time was just booked by another customer. Please return to the app and choose another time.', 'tee-time-nexus-bookings'));
        }
    }

    if (function_exists('wc_load_cart') && function_exists('WC')) {
        wc_load_cart();
        $wc = WC();
        if ($wc && isset($wc->customer) && method_exists($wc->customer, 'set_id')) {
            $wc->customer = new WC_Customer($user_id);
            foreach (ttn_mobile_get_checkout_contact_defaults($user_id) as $field => $value) {
                $getter = 'get_' . $field;
                $setter = 'set_' . $field;
                if ($value !== '' && $wc->customer->$getter() === '') {
                    $wc->customer->$setter($value);
                }
            }
            $wc->customer->save();
        }
        if ($wc && !empty($wc->cart) && $bridge['type'] === 'booking') {
            $data = $bridge['data'];
            $product_id = ttn_booking_get_bay_product_id($data['bay']);
            if ($product_id) {
                $wc->cart->empty_cart();
                $wc->cart->add_to_cart($product_id, 1, 0, array(), array('ttn_booking' => $data));
            }
        }
    }

    if ($bridge['type'] === 'reschedule') {
        $data = $bridge['data'];
        $checkout_url = ttn_booking_add_extension_to_cart_and_redirect($data['booking_id'], array_merge($data, array('price_difference' => $data['difference'])));
        if (!$checkout_url) {
            wp_die('Unable to start payment. Your reservation has not changed. Please return to the app and try again.');
        }
        if (function_exists('golf_simulator_theme_app_checkout_url')) {
            $checkout_url = golf_simulator_theme_app_checkout_url($checkout_url);
        } else {
            $checkout_url = add_query_arg('ttn_app_checkout', '1', $checkout_url);
        }
        wp_safe_redirect($checkout_url);
        exit;
    }
    $checkout_url = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/');
    if (function_exists('golf_simulator_theme_app_checkout_url')) {
        $checkout_url = golf_simulator_theme_app_checkout_url($checkout_url);
    } else {
        $checkout_url = add_query_arg('ttn_app_checkout', '1', $checkout_url);
    }
    wp_safe_redirect($checkout_url);
    exit;
}
add_action('init', 'ttn_mobile_handle_checkout_bridge');

add_action('rest_api_init', function () {
    register_rest_route('ttn/v1', '/verification-status', array(
        'methods' => 'GET',
        'callback' => 'ttn_mobile_route_verification_status',
        'permission_callback' => 'ttn_jwt_authenticate_request',
    ));

    register_rest_route('ttn/v1', '/verification', array(
        'methods' => 'POST',
        'callback' => 'ttn_mobile_route_submit_verification',
        'permission_callback' => 'ttn_jwt_authenticate_request',
    ));

    register_rest_route('ttn/v1', '/checkout/start', array(
        'methods' => 'POST',
        'callback' => 'ttn_mobile_route_start_checkout',
        'permission_callback' => 'ttn_jwt_authenticate_request',
        'args' => array(
            'bay' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
            'date' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
            'time' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
            'duration' => array('required' => true),
            'players' => array('required' => true),
        ),
    ));
});
