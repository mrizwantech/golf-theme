<?php

function golf_simulator_theme_password_meets_policy($password) {
    if (function_exists('ttn_jwt_password_meets_policy')) {
        return ttn_jwt_password_meets_policy($password);
    }

    $password = (string) $password;
    return strlen($password) >= 8
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/[0-9]/', $password)
        && preg_match('/[^A-Za-z0-9\s]/', $password);
}

function golf_simulator_theme_password_policy_message() {
    return __('Password must be at least 8 characters and include an uppercase letter, a lowercase letter, a number, and a symbol.', 'golf-simulator-theme');
}

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

    if (!$name || !is_email($email) || !golf_simulator_theme_password_meets_policy($password)) {
        return __('Please enter your name, a valid email, and a password with at least 8 characters, uppercase and lowercase letters, a number, and a symbol.', 'golf-simulator-theme');
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

    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);

    wp_safe_redirect($redirect_to);
    exit;
}

/**
 * Sends the account welcome email for all new WordPress users, including
 * Apple/REST signups using wp_insert_user(). Existing-user logins do not fire
 * user_register. A successful wp_mail() result is not proof of inbox delivery.
 * Records the latest attempt and last successful handoff in the admin profile.
 */
function golf_simulator_theme_send_account_welcome_email($user_id) {
    $user = get_userdata($user_id);
    if (!$user || !is_email($user->user_email)) {
        if ($user) {
            golf_simulator_theme_record_welcome_email($user_id, 'skipped', $user->user_email, 'Missing or invalid email address.');
        }
        error_log(sprintf('TTN account welcome email skipped for user %d: missing user or invalid email.', $user_id));
        return false;
    }

    $display_name = $user->display_name ?: 'Golfer';
    $membership_url = home_url('/membership/');
    $booking_url = home_url('/book-a-bay/');

    $subject = 'Welcome to Tee Time Nexus';
    $body = '<p style="margin:0 0 16px;color:#4b5563;font-size:15px;line-height:1.6;">Your account is ready&mdash;and your next round just got better.</p>'
        . '<p style="margin:0 0 16px;color:#4b5563;font-size:15px;line-height:1.6;">Tee Time Nexus brings together advanced golf technology, immersive gameplay, and the freedom to play on your schedule.</p>'
        . '<p style="margin:0 0 16px;color:#4b5563;font-size:15px;line-height:1.6;">Experience world-class courses with precise shot tracking, detailed swing and ball-flight analysis, realistic playing conditions, and a moving swing platform that recreates changing lies and terrain.</p>'
        . '<p style="margin:0 0 22px;color:#111827;font-size:16px;line-height:1.6;"><strong>More than a simulator. A better way to experience golf.</strong></p>'
        . '<p style="margin:0 0 16px;color:#4b5563;font-size:15px;line-height:1.6;">As a member, you&rsquo;ll enjoy <strong>24/7 access</strong>&mdash;whether that means an early-morning practice session, a round after work, or late-night golf with friends. <a href="' . esc_url($membership_url) . '" style="color:#1769aa;text-decoration:underline;">Explore memberships</a>.</p>'
        . '<p style="margin:0 0 16px;color:#4b5563;font-size:15px;line-height:1.6;">We&rsquo;re putting the finishing touches on our Mooresville location and can&rsquo;t wait to welcome you.</p>'
        . '<p style="margin:0 0 22px;color:#111827;font-size:16px;line-height:1.6;"><strong>Your next round is waiting.</strong></p>';
    $message = golf_simulator_theme_render_email_template(
        'Account Confirmation',
        'Welcome to Tee Time Nexus, ' . $display_name . '!',
        $body,
        'Book a Bay',
        $booking_url,
        'See you at the Tee Time'
    );

    $mail_error = '';
    $capture_mail_error = static function ($error) use (&$mail_error) {
        if ($error instanceof WP_Error) {
            $mail_error = $error->get_error_message();
        }
    };
    add_action('wp_mail_failed', $capture_mail_error, 10, 1);
    $sent = wp_mail($user->user_email, $subject, $message, golf_simulator_theme_get_email_headers());
    remove_action('wp_mail_failed', $capture_mail_error, 10);

    $mail_error = $sent ? '' : ($mail_error ?: 'wp_mail returned false without an error message.');
    golf_simulator_theme_record_welcome_email($user_id, $sent ? 'accepted' : 'failed', $user->user_email, $mail_error);

    if (!$sent) {
        error_log(sprintf('TTN account welcome email failed for user %d: %s', $user_id, $mail_error));
    } else {
        error_log(sprintf('TTN account welcome email accepted by wp_mail for user %d; inbox delivery is not confirmed.', $user_id));
    }

    return $sent;
}
add_action('user_register', 'golf_simulator_theme_send_account_welcome_email', 10, 1);

function golf_simulator_theme_record_welcome_email($user_id, $status, $recipient, $error) {
    $previous = get_user_meta($user_id, '_ttn_account_welcome_email', true);
    $previous = is_array($previous) ? $previous : array();
    $now = time();
    $record = array(
        'status' => $status,
        'attempted_at' => $now,
        'accepted_at' => $status === 'accepted' ? $now : ($previous['accepted_at'] ?? 0),
        'attempts' => (int) ($previous['attempts'] ?? 0) + 1,
        'recipient' => $recipient,
        'error' => $error,
    );
    if (false === update_user_meta($user_id, '_ttn_account_welcome_email', $record)) {
        error_log(sprintf('TTN account welcome email status could not be saved for user %d.', $user_id));
    }
}

function golf_simulator_theme_welcome_email_status_label($record) {
    $labels = array(
        'accepted' => __('Accepted by wp_mail (delivery not confirmed)', 'golf-simulator-theme'),
        'failed' => __('Send failed', 'golf-simulator-theme'),
        'skipped' => __('Send skipped: invalid email address', 'golf-simulator-theme'),
    );
    return $labels[$record['status'] ?? ''] ?? __('No recorded attempt (older emails may not have been tracked)', 'golf-simulator-theme');
}

function golf_simulator_theme_admin_welcome_email_profile($user) {
    if (!current_user_can('edit_users') || !current_user_can('edit_user', $user->ID)) {
        return;
    }

    $record = get_user_meta($user->ID, '_ttn_account_welcome_email', true);
    $record = is_array($record) ? $record : array();
    $status = golf_simulator_theme_welcome_email_status_label($record);
    ?>
    <h2><?php echo esc_html(__('Welcome Email', 'golf-simulator-theme')); ?></h2>
    <table class="form-table" role="presentation">
        <tr>
            <th><?php echo esc_html(__('Latest result', 'golf-simulator-theme')); ?></th>
            <td>
                <p><strong><?php echo esc_html($status); ?></strong></p>
                <?php if (!empty($record['attempted_at'])) : ?>
                    <p><?php echo esc_html(sprintf(__('Last attempt: %s', 'golf-simulator-theme'), wp_date('Y-m-d H:i:s T', $record['attempted_at']))); ?></p>
                    <p><?php echo esc_html(sprintf(__('Recipient: %s', 'golf-simulator-theme'), $record['recipient'] ?? '')); ?></p>
                    <p><?php echo esc_html(sprintf(__('Recorded attempts: %d', 'golf-simulator-theme'), $record['attempts'] ?? 0)); ?></p>
                <?php endif; ?>
                <?php if (!empty($record['accepted_at'])) : ?>
                    <p><?php echo esc_html(sprintf(__('Last successful handoff: %s', 'golf-simulator-theme'), wp_date('Y-m-d H:i:s T', $record['accepted_at']))); ?></p>
                <?php endif; ?>
                <?php if (!empty($record['error'])) : ?>
                    <p><?php echo esc_html(sprintf(__('Send error: %s', 'golf-simulator-theme'), $record['error'])); ?></p>
                <?php endif; ?>
                <p class="description"><?php echo esc_html(__('A successful handoff does not confirm inbox delivery. Check your mail provider logs for bounces or Apple relay rejections.', 'golf-simulator-theme')); ?></p>
            </td>
        </tr>
        <tr>
            <th><label for="ttn_resend_welcome_email"><?php echo esc_html(__('Resend welcome email', 'golf-simulator-theme')); ?></label></th>
            <td>
                <?php wp_nonce_field('ttn_resend_welcome_email_' . $user->ID, 'ttn_resend_welcome_nonce'); ?>
                <label><input type="checkbox" name="ttn_resend_welcome_email" id="ttn_resend_welcome_email" value="1" /> <?php echo esc_html(__('Send again when this profile is saved.', 'golf-simulator-theme')); ?></label>
                <p class="description"><?php echo esc_html(__('Click Update User (or Update Profile) to save changes and send to the saved email address. The latest result will appear above.', 'golf-simulator-theme')); ?></p>
            </td>
        </tr>
    </table>
    <?php
}
add_action('show_user_profile', 'golf_simulator_theme_admin_welcome_email_profile');
add_action('edit_user_profile', 'golf_simulator_theme_admin_welcome_email_profile');

function golf_simulator_theme_check_welcome_email_resend_permission($user_id) {
    if (!current_user_can('edit_users') || !current_user_can('edit_user', $user_id)) {
        wp_die(__('You do not have permission to resend this welcome email.', 'golf-simulator-theme'), '', array('response' => 403));
    }
    check_admin_referer('ttn_resend_welcome_email_' . $user_id, 'ttn_resend_welcome_nonce');
}

// profile_update runs after the new email address has been saved.
function golf_simulator_theme_admin_resend_welcome_email($user_id) {
    if (empty($_POST['ttn_resend_welcome_email'])) {
        return;
    }
    golf_simulator_theme_check_welcome_email_resend_permission($user_id);
    golf_simulator_theme_send_account_welcome_email($user_id);
}
add_action('profile_update', 'golf_simulator_theme_admin_resend_welcome_email', 10, 1);

function golf_simulator_theme_welcome_email_users_columns($columns) {
    if (current_user_can('edit_users')) {
        $columns['ttn_welcome_email'] = __('Welcome Email', 'golf-simulator-theme');
    }
    return $columns;
}
add_filter('manage_users_columns', 'golf_simulator_theme_welcome_email_users_columns');

function golf_simulator_theme_welcome_email_users_column($output, $column_name, $user_id) {
    if ($column_name !== 'ttn_welcome_email' || !current_user_can('edit_users') || !current_user_can('edit_user', $user_id)) {
        return $output;
    }
    $record = get_user_meta($user_id, '_ttn_account_welcome_email', true);
    $record = is_array($record) ? $record : array();
    $output = '<p>' . esc_html(golf_simulator_theme_welcome_email_status_label($record)) . '</p>';
    if (!empty($record['attempted_at'])) {
        $output .= '<p>' . esc_html(sprintf(__('Last attempt: %s', 'golf-simulator-theme'), wp_date('Y-m-d H:i:s T', $record['attempted_at']))) . '</p>';
    }
    if (!empty($record['error'])) {
        $output .= '<p>' . esc_html(sprintf(__('Send error: %s', 'golf-simulator-theme'), $record['error'])) . '</p>';
    }
    $GLOBALS['golf_simulator_welcome_email_resend_users'][$user_id] = $user_id;
    $output .= '<button type="submit" class="button button-small" form="ttn-welcome-resend-' . esc_attr($user_id) . '">'
        . esc_html(__('Resend welcome email', 'golf-simulator-theme')) . '</button>';
    return $output;
}
add_filter('manage_users_custom_column', 'golf_simulator_theme_welcome_email_users_column', 10, 3);

// Keep resend forms outside the Users table's bulk-action form.
function golf_simulator_theme_welcome_email_users_forms() {
    foreach ($GLOBALS['golf_simulator_welcome_email_resend_users'] ?? array() as $user_id) {
        if (!current_user_can('edit_users') || !current_user_can('edit_user', $user_id)) {
            continue;
        }
        ?>
        <form id="ttn-welcome-resend-<?php echo esc_attr($user_id); ?>" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
            <input type="hidden" name="action" value="golf_simulator_resend_welcome_email" />
            <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>" />
            <?php wp_nonce_field('ttn_resend_welcome_email_' . $user_id, 'ttn_resend_welcome_nonce'); ?>
        </form>
        <?php
    }
}
add_action('admin_footer-users.php', 'golf_simulator_theme_welcome_email_users_forms');

function golf_simulator_theme_handle_welcome_email_list_resend() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        wp_die(__('Use the resend button on the Users list.', 'golf-simulator-theme'), '', array('response' => 405));
    }
    $user_id = isset($_POST['user_id']) && is_scalar($_POST['user_id']) ? absint($_POST['user_id']) : 0;
    golf_simulator_theme_check_welcome_email_resend_permission($user_id);
    if (!$user_id || !get_userdata($user_id)) {
        wp_die(__('User not found.', 'golf-simulator-theme'), '', array('response' => 404));
    }
    $sent = golf_simulator_theme_send_account_welcome_email($user_id);
    wp_safe_redirect(add_query_arg('ttn_welcome_email_result', $sent ? 'accepted' : 'failed', admin_url('users.php')));
    exit;
}
add_action('admin_post_golf_simulator_resend_welcome_email', 'golf_simulator_theme_handle_welcome_email_list_resend');

function golf_simulator_theme_welcome_email_users_notice() {
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'users' || !current_user_can('edit_users')) {
        return;
    }
    $result = isset($_GET['ttn_welcome_email_result']) && is_string($_GET['ttn_welcome_email_result'])
        ? sanitize_key(wp_unslash($_GET['ttn_welcome_email_result'])) : '';
    if (!in_array($result, array('accepted', 'failed'), true)) {
        return;
    }
    $message = $result === 'accepted'
        ? __('Welcome email accepted by wp_mail. Inbox delivery is not confirmed.', 'golf-simulator-theme')
        : __('Welcome email could not be sent. Check the user\'s Welcome Email status for details.', 'golf-simulator-theme');
    echo '<div class="notice ' . ($result === 'accepted' ? 'notice-success' : 'notice-error') . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
}
add_action('admin_notices', 'golf_simulator_theme_welcome_email_users_notice');

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
