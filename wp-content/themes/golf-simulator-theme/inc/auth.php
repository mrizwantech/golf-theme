<?php

function golf_simulator_theme_get_login_url($redirect_to = '', $tab = 'login') {
    $page = get_page_by_path('login');
    $url = $page ? get_permalink($page) : home_url('/login/');

    if ($tab === 'register') {
        $url = add_query_arg('tab', 'register', $url);
    }
    if ($redirect_to) {
        $url = add_query_arg('redirect_to', rawurlencode($redirect_to), $url);
    }

    return $url;
}

function golf_simulator_theme_redirect_native_login() {
    if (is_user_logged_in() || !isset($_SERVER['REQUEST_URI'])) {
        return;
    }

    $request_uri = wp_unslash($_SERVER['REQUEST_URI']);
    if (false === strpos($request_uri, 'wp-login.php')) {
        return;
    }

    $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : 'login';
    if (!in_array($action, array('login', 'register'), true)) {
        return;
    }

    $redirect_to = isset($_REQUEST['redirect_to']) ? esc_url_raw(wp_unslash($_REQUEST['redirect_to'])) : '';
    $tab = 'register' === $action ? 'register' : 'login';
    wp_safe_redirect(golf_simulator_theme_get_login_url($redirect_to, $tab));
    exit;
}
add_action('login_init', 'golf_simulator_theme_redirect_native_login');

function golf_simulator_theme_filter_login_url($login_url, $redirect, $force_reauth) {
    return golf_simulator_theme_get_login_url($redirect);
}
add_filter('login_url', 'golf_simulator_theme_filter_login_url', 10, 3);

function golf_simulator_theme_generate_unique_username($email) {
    $base = sanitize_user(current(explode('@', $email)), true);
    if ($base === '') {
        $base = 'golfer';
    }

    $username = $base;
    $suffix = 1;
    while (username_exists($username)) {
        $suffix++;
        $username = $base . $suffix;
    }

    return $username;
}

function golf_simulator_theme_process_login($redirect_to) {
    if (!isset($_POST['ttn_login_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ttn_login_nonce'])), 'ttn_user_login')) {
        return __('Security check failed. Please refresh the page and try again.', 'golf-simulator-theme');
    }

    $login_identifier = sanitize_text_field(wp_unslash($_POST['login_identifier'] ?? $_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $user = wp_signon(array(
        'user_login' => $login_identifier,
        'user_password' => $password,
        'remember' => true,
    ), is_ssl());

    if (is_wp_error($user)) {
        return __('Incorrect email or password. Please try again.', 'golf-simulator-theme');
    }

    wp_safe_redirect($redirect_to);
    exit;
}

function golf_simulator_theme_process_register($redirect_to) {
    if (!isset($_POST['ttn_register_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ttn_register_nonce'])), 'ttn_user_register')) {
        return __('Security check failed. Please refresh the page and try again.', 'golf-simulator-theme');
    }

    $name = sanitize_text_field(wp_unslash($_POST['ttn_name'] ?? ''));
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!$name || !is_email($email) || strlen($password) < 6) {
        return __('Please enter your name, a valid email, and a password of at least 6 characters.', 'golf-simulator-theme');
    }

    if (email_exists($email)) {
        return __('An account with that email already exists. Please log in instead.', 'golf-simulator-theme');
    }

    $user_id = wp_insert_user(array(
        'user_login' => golf_simulator_theme_generate_unique_username($email),
        'user_email' => $email,
        'user_pass' => $password,
        'display_name' => $name,
        'first_name' => $name,
        'role' => 'subscriber',
    ));

    if (is_wp_error($user_id)) {
        return __('We could not create your account. Please try again.', 'golf-simulator-theme');
    }

    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);

    wp_safe_redirect($redirect_to);
    exit;
}
