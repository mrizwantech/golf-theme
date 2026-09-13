<?php

// Auto-create the branded login/register pages so a fresh or staging
// environment never falls back to a non-existent /login/ URL, which is
// what causes the wp-login.php <-> /login/ redirect loop.
function golf_simulator_theme_ensure_auth_pages() {
    foreach (array('login' => 'Login', 'register' => 'Register') as $slug => $title) {
        $page = get_page_by_path($slug);

        if (!$page) {
            $page_id = wp_insert_post(array(
                'post_title' => $title,
                'post_name' => $slug,
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_content' => '',
            ));
        } else {
            $page_id = $page->ID;
        }

        if ($page_id && !is_wp_error($page_id) && get_post_meta($page_id, '_wp_page_template', true) !== 'page-login.php') {
            update_post_meta($page_id, '_wp_page_template', 'page-login.php');
        }
    }
}
add_action('init', 'golf_simulator_theme_ensure_auth_pages');

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
    $target_url = golf_simulator_theme_get_login_url($redirect_to, $tab);

    // Guard against looping back to wp-login.php if no branded page exists yet.
    if (false !== strpos($target_url, 'wp-login.php')) {
        return;
    }

    wp_safe_redirect($target_url);
    exit;
}
add_action('login_init', 'golf_simulator_theme_redirect_native_login');

function golf_simulator_theme_filter_login_url($login_url, $redirect, $force_reauth) {
    return golf_simulator_theme_get_login_url($redirect);
}
add_filter('login_url', 'golf_simulator_theme_filter_login_url', 10, 3);

// Runs after WooCommerce's wc_lostpassword_url() filter so lost-password links
// go to the native wp-login.php flow instead of the unused /my-account/lost-password/ endpoint.
function golf_simulator_theme_filter_lostpassword_url($lostpassword_url, $redirect) {
    // Use the raw wp-login.php URL directly; wp_login_url() would loop back through
    // our own 'login_url' filter and return the branded /login/ page instead.
    $url = site_url('wp-login.php', 'login');
    $args = array('action' => 'lostpassword');
    if ($redirect) {
        $args['redirect_to'] = $redirect;
    }

    return add_query_arg($args, $url);
}
add_filter('lostpassword_url', 'golf_simulator_theme_filter_lostpassword_url', 20, 2);

// Brands the native wp-login.php screens (e.g. lost password) with our logo instead of the WordPress logo.
function golf_simulator_theme_login_logo_css() {
    $site_logo = get_theme_mod('golf_simulator_site_logo');
    if (!$site_logo && has_custom_logo()) {
        $logo_id = get_theme_mod('custom_logo');
        $site_logo = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : '';
    }

    if (!$site_logo) {
        return;
    }
    ?>
    <style>
        #login h1 a {
            background-image: url('<?php echo esc_url($site_logo); ?>');
            background-size: contain;
            background-position: center;
            width: 100%;
            max-width: 320px;
            height: 80px;
        }
    </style>
    <?php
}
add_action('login_enqueue_scripts', 'golf_simulator_theme_login_logo_css');

function golf_simulator_theme_login_logo_url() {
    return home_url('/');
}
add_filter('login_headerurl', 'golf_simulator_theme_login_logo_url');

function golf_simulator_theme_login_logo_title() {
    return get_bloginfo('name');
}
add_filter('login_headertext', 'golf_simulator_theme_login_logo_title');

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

    $existing_user = is_email($login_identifier)
        ? get_user_by('email', $login_identifier)
        : get_user_by('login', $login_identifier);

    if (!$existing_user) {
        $register_url = golf_simulator_theme_get_login_url($redirect_to, 'register');
        return sprintf(
            /* translators: %s: register page link */
            __('We couldn\'t find an account for that email. <a href="%s">Create one now</a>?', 'golf-simulator-theme'),
            esc_url($register_url)
        );
    }

    $user = wp_signon(array(
        'user_login' => $login_identifier,
        'user_password' => $password,
        'remember' => true,
    ), is_ssl());

    if (is_wp_error($user)) {
        return __('Incorrect password. Please try again.', 'golf-simulator-theme');
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
    $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
    $sms_opt_in = !empty($_POST['sms_opt_in']);
    $promo_opt_in = !empty($_POST['promo_opt_in']);

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

    if ($phone) {
        update_user_meta($user_id, 'phone_number', $phone);
    }
    update_user_meta($user_id, 'sms_opt_in', $sms_opt_in ? '1' : '0');
    update_user_meta($user_id, 'promo_opt_in', $promo_opt_in ? '1' : '0');

    golf_simulator_theme_send_account_welcome_email($user_id);

    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);

    wp_safe_redirect($redirect_to);
    exit;
}

function golf_simulator_theme_send_account_welcome_email($user_id) {
    $user = get_userdata($user_id);
    if (!$user || !is_email($user->user_email)) {
        return false;
    }

    $display_name = $user->display_name ?: 'Golfer';
    $account_url = home_url('/my-account/');
    $booking_url = home_url('/book-a-bay/');
    $logo_id = get_theme_mod('custom_logo');
    $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : '';
    $logo_html = $logo_url
        ? '<img src="' . esc_url($logo_url) . '" alt="Tee Time Nexus" style="display:block;max-width:220px;max-height:64px;margin:0 auto 18px;">'
        : '<div style="font-size:26px;font-weight:800;margin-bottom:18px;">Tee Time Nexus</div>';

    $subject = 'Welcome to Tee Time Nexus - Your Account is Ready';
    $message = '<!doctype html><html><body style="margin:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;">'
        . '<div style="padding:32px 12px;"><div style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;">'
        . '<div style="padding:30px 24px;text-align:center;background:#07110b;color:#ffffff;">' . $logo_html . '<div style="font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:#a1e04c;font-weight:700;">Account Confirmation</div></div>'
        . '<div style="padding:30px 28px 34px;"><h1 style="margin:0 0 16px;color:#111827;font-size:24px;">Welcome, ' . esc_html($display_name) . '!</h1>'
        . '<p style="margin:0 0 16px;color:#4b5563;font-size:15px;line-height:1.6;">Your Tee Time Nexus account has been created successfully. You can now manage bookings, track your membership, and book bays faster.</p>'
        . '<p style="margin:0 0 22px;text-align:center;"><a href="' . esc_url($booking_url) . '" style="display:inline-block;padding:13px 20px;background:#a1e04c;color:#101010;text-decoration:none;border-radius:8px;font-weight:800;">Book a Bay</a></p>'
        . '<p style="margin:0 0 16px;color:#4b5563;font-size:15px;line-height:1.6;">You can review or update your account and communication preferences anytime from <a href="' . esc_url($account_url) . '" style="color:#1769aa;text-decoration:underline;">My Account</a>.</p>'
        . '<p style="margin:28px 0 0;color:#4b5563;font-size:15px;line-height:1.6;"><strong>See you on the tee!</strong><br><strong>Tee Time Nexus</strong></p>'
        . '</div></div></div></body></html>';
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: Tee Time Nexus <sales@teetimenexus.com>',
    );

    return wp_mail($user->user_email, $subject, $message, $headers);
}

function golf_simulator_theme_process_profile_update() {
    if (!is_user_logged_in()) {
        wp_safe_redirect(golf_simulator_theme_get_login_url());
        exit;
    }

    if (!isset($_POST['ttn_profile_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ttn_profile_nonce'])), 'ttn_profile_update')) {
        wp_safe_redirect(add_query_arg('profile_error', rawurlencode(__('Security check failed. Please try again.', 'golf-simulator-theme')), home_url('/my-account/')));
        exit;
    }

    $user_id = get_current_user_id();
    $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
    $sms_opt_in = !empty($_POST['sms_opt_in']);
    $promo_opt_in = !empty($_POST['promo_opt_in']);

    update_user_meta($user_id, 'phone_number', $phone);
    update_user_meta($user_id, 'sms_opt_in', $sms_opt_in ? '1' : '0');
    update_user_meta($user_id, 'promo_opt_in', $promo_opt_in ? '1' : '0');

    wp_safe_redirect(add_query_arg('profile_updated', '1', home_url('/my-account/')));
    exit;
}
add_action('admin_post_golf_simulator_profile_update', 'golf_simulator_theme_process_profile_update');
