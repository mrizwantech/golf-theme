<?php

if (!defined('ABSPATH')) {
    exit;
}

function golf_simulator_theme_mobile_membership_packages() {
    $packages = golf_simulator_theme_get_default_membership_packages();
    $response = array();
    $package_posts = get_posts(array(
        'post_type' => 'membership_package',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'menu_order',
        'order' => 'ASC',
    ));

    foreach ($packages as $key => $package) {
        $thumbnail_id = absint($package['thumbnail_id'] ?? 0);
        foreach ($package_posts as $package_post) {
            $default_key = get_post_meta($package_post->ID, '_membership_default_key', true);
            $title = get_the_title($package_post->ID);
            if ($default_key === $key || (!$default_key && ($title === $key || ('ALBATROSS' === $key && 'EAGLE' === $title)))) {
                $thumbnail_id = absint(get_post_meta($package_post->ID, '_membership_thumbnail_id', true)) ?: $thumbnail_id;
                break;
            }
        }
        $thumbnail_url = $thumbnail_id ? wp_get_attachment_image_url($thumbnail_id, 'large') : '';
        $response[] = array(
            'slug' => sanitize_title($key),
            'title' => $package['title'],
            'price' => (float) $package['price'],
            'discount_price' => $package['discount_price'] !== '' ? (float) $package['discount_price'] : null,
            'billing' => $package['billing'],
            'featured' => !empty($package['featured']),
            'features' => array_values(array_map('sanitize_text_field', (array) $package['features'])),
            'thumbnail_url' => $thumbnail_url ? esc_url_raw($thumbnail_url) : null,
        );
    }

    return rest_ensure_response($response);
}

function golf_simulator_theme_mobile_membership_current(WP_REST_Request $request) {
    $user = $request->get_param('ttn_auth_user');
    if (!$user instanceof WP_User) {
        return new WP_Error('membership_not_authenticated', 'You must be logged in.', array('status' => 401));
    }

    $response = rest_ensure_response(golf_simulator_theme_membership_management_record($user->ID));
    $response->header('Cache-Control', 'private, no-store');
    return $response;
}

function golf_simulator_theme_mobile_membership_manage(WP_REST_Request $request) {
    $user = $request->get_param('ttn_auth_user');
    if (!$user instanceof WP_User) {
        return new WP_Error('membership_not_authenticated', 'You must be logged in.', array('status' => 401));
    }
    $result = golf_simulator_theme_manage_membership(
        $user->ID, (string) $request->get_param('action'),
        (string) $request->get_param('package'), (string) $request->get_param('revision')
    );
    if (is_wp_error($result)) {
        return $result;
    }
    if (!empty($result['checkout'])) {
        return golf_simulator_theme_mobile_membership_checkout_bridge($user->ID, $result['checkout']);
    }
    $response = rest_ensure_response(array('message' => $result['message'], 'membership' => golf_simulator_theme_membership_management_record($user->ID)));
    $response->header('Cache-Control', 'private, no-store');
    return $response;
}

function golf_simulator_theme_mobile_membership_checkout_bridge($user_id, $checkout) {
    if (!function_exists('WC') || !function_exists('wc_load_cart') || !function_exists('wc_get_checkout_url')) {
        return new WP_Error('membership_checkout_unavailable', 'Checkout is currently unavailable.', array('status' => 503));
    }
    $bridge_token = wp_generate_password(48, false);
    if (!set_transient('ttn_mobile_membership_bridge_' . hash('sha256', $bridge_token), array_merge($checkout, array(
        'user_id' => $user_id,
    )), 2 * MINUTE_IN_SECONDS)) {
        return new WP_Error('membership_checkout_failed', 'Unable to start checkout. Please try again.', array('status' => 503));
    }
    $response = rest_ensure_response(array('bridge_url' => add_query_arg('ttn_mobile_membership_bridge', $bridge_token, home_url('/'))));
    $response->header('Cache-Control', 'private, no-store');
    return $response;
}

function golf_simulator_theme_mobile_membership_checkout(WP_REST_Request $request) {
    $user = $request->get_param('ttn_auth_user');
    if (!$user instanceof WP_User) {
        return new WP_Error('membership_not_authenticated', 'You must be logged in.', array('status' => 401));
    }
    $allowed = golf_simulator_theme_require_contact_email($user->ID);
    if (is_wp_error($allowed)) {
        return $allowed;
    }

    $package = golf_simulator_theme_membership_key(sanitize_key($request->get_param('package')));
    $packages = golf_simulator_theme_get_default_membership_packages();
    if (empty($packages[$package])) {
        return new WP_Error('membership_invalid_package', 'That membership package is unavailable.', array('status' => 400));
    }

    $membership = golf_simulator_theme_get_user_membership_record($user->ID);
    if ($membership && 'active' === $membership->status && 'paid' === $membership->payment_status) {
        return new WP_Error('membership_already_active', 'Use Change Plan to manage your active membership.', array('status' => 409));
    }
    return golf_simulator_theme_mobile_membership_checkout_bridge($user->ID, array('package' => $package, 'is_upgrade' => false, 'custom_price' => null));
}

function golf_simulator_theme_mobile_membership_guest_checkout(WP_REST_Request $request) {
    $email = sanitize_email($request->get_param('email'));
    $password = (string) $request->get_param('password');
    $display_name = sanitize_text_field($request->get_param('display_name'));

    if (!is_email($email) || !golf_simulator_theme_password_meets_policy($password)) {
        return new WP_Error('membership_account_invalid', 'Enter a valid email and a password with at least 8 characters, uppercase and lowercase letters, a number, and a symbol.', array('status' => 400));
    }

    if (email_exists($email)) {
        return new WP_Error('membership_account_exists', 'An account already exists for this email. Please log in first.', array('status' => 409));
    }

    $base_username = sanitize_user(current(explode('@', $email)), true) ?: 'golfer';
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
        'display_name' => $display_name ?: $username,
        'role' => 'subscriber',
    ));

    if (is_wp_error($user_id)) {
        return new WP_Error('membership_account_failed', 'We could not create your account. Please try again.', array('status' => 400));
    }

    $request->set_param('ttn_auth_user', get_user_by('id', $user_id));
    return golf_simulator_theme_mobile_membership_checkout($request);
}

function golf_simulator_theme_mobile_membership_bridge() {
    if (empty($_GET['ttn_mobile_membership_bridge'])) {
        return;
    }

    $token = sanitize_text_field(wp_unslash($_GET['ttn_mobile_membership_bridge']));
    $key = 'ttn_mobile_membership_bridge_' . hash('sha256', $token);
    $bridge = get_transient($key);
    if (!is_array($bridge) || empty($bridge['user_id'])) {
        wp_die(__('This membership checkout link has expired. Please start again in the app.', 'golf-simulator-theme'));
    }

    delete_transient($key);
    $user_id = (int) $bridge['user_id'];
    $allowed = golf_simulator_theme_require_contact_email($user_id);
    if (is_wp_error($allowed)) {
        wp_die(esc_html($allowed->get_error_message()), '', array('response' => 403));
    }
    if (!empty($bridge['is_upgrade'])) {
        $membership = golf_simulator_theme_get_user_membership_record($user_id);
        if (!$membership || !hash_equals(golf_simulator_theme_membership_revision($membership), (string) $bridge['revision'])
            || get_user_meta($user_id, '_ttn_membership_change', true)
            || 'active' !== $membership->status || 'paid' !== $membership->payment_status
            || golf_simulator_theme_membership_period_end($membership) <= time()) {
            wp_die('Your membership changed. Please start the upgrade again.', '', array('response' => 409));
        }
    }
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);

    if (function_exists('wc_load_cart') && function_exists('WC') && function_exists('golf_simulator_theme_get_membership_product_id')) {
        wc_load_cart();
        $wc = WC();
        if ($wc && isset($wc->customer) && method_exists($wc->customer, 'set_id')) {
            $wc->customer->set_id($user_id);
        }
        $product_id = golf_simulator_theme_get_membership_product_id($bridge['package']);
        if ($wc && !empty($wc->cart) && $product_id) {
            $wc->cart->empty_cart();
            $added = $wc->cart->add_to_cart($product_id, 1, 0, array(), array(
                'golf_simulator_membership' => array(
                    'package_name' => $bridge['package'],
                    'is_upgrade' => !empty($bridge['is_upgrade']),
                    'custom_price' => $bridge['custom_price'] ?? null,
                    'membership_revision' => $bridge['revision'] ?? '',
                ),
            ));
            if (!$added) {
                wp_die('Unable to add your membership to checkout. Please try again.', '', array('response' => 503));
            }
        } else {
            wp_die('Membership checkout is unavailable. Please try again.', '', array('response' => 503));
        }
    } else {
        wp_die('Membership checkout is unavailable. Please try again.', '', array('response' => 503));
    }
    wp_safe_redirect(wc_get_checkout_url());
    exit;
}
add_action('init', 'golf_simulator_theme_mobile_membership_bridge');

add_action('rest_api_init', function () {
    register_rest_route('ttn/v1', '/membership/packages', array(
        'methods' => 'GET',
        'callback' => 'golf_simulator_theme_mobile_membership_packages',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('ttn/v1', '/membership/current', array(
        'methods' => 'GET',
        'callback' => 'golf_simulator_theme_mobile_membership_current',
        'permission_callback' => 'ttn_jwt_authenticate_request',
    ));

    register_rest_route('ttn/v1', '/membership/checkout', array(
        'methods' => 'POST',
        'callback' => 'golf_simulator_theme_mobile_membership_checkout',
        'permission_callback' => 'ttn_jwt_authenticate_request',
        'args' => array(
            'package' => array('required' => true, 'sanitize_callback' => 'sanitize_key'),
        ),
    ));

    register_rest_route('ttn/v1', '/membership/manage', array(
        'methods' => 'POST',
        'callback' => 'golf_simulator_theme_mobile_membership_manage',
        'permission_callback' => 'ttn_jwt_authenticate_request',
        'args' => array(
            'action' => array('required' => true, 'sanitize_callback' => 'sanitize_key'),
            'revision' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
            'package' => array('required' => false, 'sanitize_callback' => 'sanitize_key'),
        ),
    ));

    register_rest_route('ttn/v1', '/membership/checkout-guest', array(
        'methods' => 'POST',
        'callback' => 'golf_simulator_theme_mobile_membership_guest_checkout',
        'permission_callback' => '__return_true',
        'args' => array(
            'email' => array('required' => true, 'sanitize_callback' => 'sanitize_email'),
            'password' => array('required' => true),
            'display_name' => array('required' => false, 'sanitize_callback' => 'sanitize_text_field'),
            'package' => array('required' => true, 'sanitize_callback' => 'sanitize_key'),
        ),
    ));
});
