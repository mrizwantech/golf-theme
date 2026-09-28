<?php

if (!defined('ABSPATH')) {
    exit;
}

function golf_simulator_theme_mobile_membership_packages() {
    $packages = golf_simulator_theme_get_default_membership_packages();
    $response = array();

    foreach ($packages as $key => $package) {
        $response[] = array(
            'slug' => sanitize_title($key),
            'title' => $package['title'],
            'price' => (float) $package['price'],
            'discount_price' => $package['discount_price'] !== '' ? (float) $package['discount_price'] : null,
            'billing' => $package['billing'],
            'featured' => !empty($package['featured']),
            'features' => array_values(array_map('sanitize_text_field', (array) $package['features'])),
        );
    }

    return rest_ensure_response($response);
}

function golf_simulator_theme_mobile_membership_current(WP_REST_Request $request) {
    $user = $request->get_param('ttn_auth_user');
    if (!$user instanceof WP_User) {
        return new WP_Error('membership_not_authenticated', 'You must be logged in.', array('status' => 401));
    }

    $membership = golf_simulator_theme_get_user_membership_record($user->ID);
    return rest_ensure_response($membership ? (array) $membership : null);
}

function golf_simulator_theme_mobile_membership_checkout(WP_REST_Request $request) {
    $user = $request->get_param('ttn_auth_user');
    if (!$user instanceof WP_User) {
        return new WP_Error('membership_not_authenticated', 'You must be logged in.', array('status' => 401));
    }

    $package = strtoupper(sanitize_key($request->get_param('package')));
    $packages = golf_simulator_theme_get_default_membership_packages();
    if (empty($packages[$package])) {
        return new WP_Error('membership_invalid_package', 'That membership package is unavailable.', array('status' => 400));
    }

    if (!function_exists('WC') || !function_exists('wc_load_cart') || !function_exists('wc_get_checkout_url')) {
        return new WP_Error('membership_checkout_unavailable', 'Checkout is currently unavailable.', array('status' => 503));
    }

    wc_load_cart();
    $wc = WC();
    if (!$wc || empty($wc->cart)) {
        return new WP_Error('membership_cart_unavailable', 'Unable to start checkout.', array('status' => 503));
    }

    golf_simulator_theme_sync_membership_products();
    $product_id = golf_simulator_theme_get_membership_product_id($package);
    if (!$product_id) {
        return new WP_Error('membership_product_missing', 'That membership product is unavailable.', array('status' => 503));
    }

    $wc->cart->empty_cart();
    $wc->cart->add_to_cart($product_id, 1, 0, array(), array(
        'golf_simulator_membership' => array(
            'package_name' => $package,
            'is_upgrade' => false,
            'custom_price' => null,
        ),
    ));

    $bridge_token = wp_generate_password(48, false);
    set_transient('ttn_mobile_membership_bridge_' . hash('sha256', $bridge_token), array(
        'user_id' => $user->ID,
        'package' => $package,
    ), 2 * MINUTE_IN_SECONDS);

    return rest_ensure_response(array(
        'bridge_url' => add_query_arg('ttn_mobile_membership_bridge', $bridge_token, home_url('/')),
    ));
}

function golf_simulator_theme_mobile_membership_guest_checkout(WP_REST_Request $request) {
    $email = sanitize_email($request->get_param('email'));
    $password = (string) $request->get_param('password');
    $display_name = sanitize_text_field($request->get_param('display_name'));

    if (!is_email($email) || strlen($password) < 8) {
        return new WP_Error('membership_account_invalid', 'Enter a valid email and a password of at least 8 characters.', array('status' => 400));
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
            $wc->cart->add_to_cart($product_id, 1, 0, array(), array(
                'golf_simulator_membership' => array(
                    'package_name' => $bridge['package'],
                    'is_upgrade' => false,
                    'custom_price' => null,
                ),
            ));
        }
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
