<?php

function golf_simulator_theme_create_welcome_table() {
    global $wpdb;

    $signup_table = $wpdb->prefix . 'welcome_signups';
    $sms_table = $wpdb->prefix . 'welcome_sms_signups';
    $charset_collate = $wpdb->get_charset_collate();

    $signup_sql = "CREATE TABLE IF NOT EXISTS $signup_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        full_name varchar(255) DEFAULT '',
        email varchar(255) NOT NULL,
        phone varchar(50) DEFAULT '',
        source varchar(100) DEFAULT 'homepage',
        channel varchar(50) DEFAULT 'email',
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY email_unique (email)
    ) $charset_collate;";

    $sms_sql = "CREATE TABLE IF NOT EXISTS $sms_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        full_name varchar(255) DEFAULT '',
        email varchar(255) DEFAULT '',
        phone varchar(50) NOT NULL,
        source varchar(100) DEFAULT 'homepage',
        status varchar(50) DEFAULT 'pending',
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY phone_unique (phone)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($signup_sql);
    dbDelta($sms_sql);
}
add_action('init', 'golf_simulator_theme_create_welcome_table');

function golf_simulator_theme_send_opening_signup_email($email, $full_name = '') {
    if (empty($email) || !is_email($email)) {
        return;
    }

    $name = trim($full_name);
    $display_name = !empty($name) ? $name : 'Friend';
    $membership_url = home_url('/membership/');
    $subject = 'Tee Time Nexus Opening Signup';
    $body = '<p style="margin:0 0 16px;color:#4b5563;font-size:15px;line-height:1.6;"><strong>Thanks for signing up, ' . esc_html($display_name) . '!</strong></p>'
        . '<p style="margin:0 0 16px;color:#4b5563;font-size:15px;line-height:1.6;">You are officially on the <strong>Tee Time Nexus Opening List</strong> for our upcoming launch in <strong>Mooresville, NC</strong>, and we are getting closer!</p>'
        . '<p style="margin:0 0 22px;color:#4b5563;font-size:15px;line-height:1.6;">As one of our early supporters, we are excited to offer a <strong>special early-opening membership deal</strong> for a limited time.</p>'
        . '<h2 style="margin:0 0 10px;color:#111827;font-size:18px;">Early Membership Special</h2>'
        . '<p style="margin:0 0 18px;color:#4b5563;font-size:15px;line-height:1.6;">Lock in our special launch membership pricing before we officially open.</p>';
    $body_after_cta = '<p style="margin:0 0 16px;color:#4b5563;font-size:15px;line-height:1.6;">This is your chance to become a Tee Time Nexus member early and take advantage of <strong>exclusive launch pricing and member benefits</strong>.</p>'
        . '<p style="margin:0 0 8px;color:#4b5563;font-size:15px;line-height:1.6;">We will also keep you updated with:</p>'
        . '<ul style="margin:0 0 22px;padding-left:22px;color:#4b5563;font-size:15px;line-height:1.8;"><li>Grand Opening announcements</li><li>Early booking opportunities</li><li>Special launch events</li><li>Exclusive member offers</li></ul>'
        . '<p style="margin:0 0 22px;color:#4b5563;font-size:15px;line-height:1.6;">We cannot wait to welcome you and show you what <strong>Tee Time Nexus</strong> is all about!</p>'
        . '<h2 style="margin:0 0 10px;color:#111827;font-size:18px;">Find Us</h2>';

    $message = golf_simulator_theme_render_email_template(
        'Opening Signup',
        'Welcome to Tee Time Nexus!',
        $body,
        'Check Out Our Membership Special',
        $membership_url
    );
    // Insert the remaining paragraphs right after the CTA button, before the location card.
    $message = str_replace(
        '<div style="margin:28px 0 0;padding:18px;background:#f3f4f6;border-radius:10px;',
        $body_after_cta . '<div style="margin:28px 0 0;padding:18px;background:#f3f4f6;border-radius:10px;',
        $message
    );

    return wp_mail($email, $subject, $message, golf_simulator_theme_get_email_headers());
}

function golf_simulator_theme_register_signup_admin_page() {
    add_menu_page(
        'Launch Signups',
        'Launch Signups',
        'manage_options',
        'golf-simulator-signups',
        'golf_simulator_theme_render_signup_admin_page',
        'dashicons-email-alt',
        26
    );
}
add_action('admin_menu', 'golf_simulator_theme_register_signup_admin_page');

function golf_simulator_theme_render_signup_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;

    $signup_table = $wpdb->prefix . 'welcome_signups';
    $sms_table = $wpdb->prefix . 'welcome_sms_signups';

    $signups = $wpdb->get_results("SELECT * FROM $signup_table ORDER BY created_at DESC LIMIT 200");
    $sms_signups = $wpdb->get_results("SELECT * FROM $sms_table ORDER BY created_at DESC LIMIT 200");

    echo '<div class="wrap">';
    echo '<h1>Launch Signups</h1>';

    echo '<h2>Email Signups</h2>';
    echo '<table class="widefat striped">';
    echo '<thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Source</th><th>Date</th></tr></thead>';
    echo '<tbody>';

    if (!empty($signups)) {
        foreach ($signups as $signup) {
            echo '<tr>';
            echo '<td>' . esc_html($signup->id) . '</td>';
            echo '<td>' . esc_html($signup->full_name) . '</td>';
            echo '<td>' . esc_html($signup->email) . '</td>';
            echo '<td>' . esc_html($signup->phone) . '</td>';
            echo '<td>' . esc_html($signup->source) . '</td>';
            echo '<td>' . esc_html($signup->created_at) . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="6">No email signups yet.</td></tr>';
    }

    echo '</tbody></table>';

    echo '<h2>SMS Signups</h2>';
    echo '<table class="widefat striped">';
    echo '<thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Date</th></tr></thead>';
    echo '<tbody>';

    if (!empty($sms_signups)) {
        foreach ($sms_signups as $signup) {
            echo '<tr>';
            echo '<td>' . esc_html($signup->id) . '</td>';
            echo '<td>' . esc_html($signup->full_name) . '</td>';
            echo '<td>' . esc_html($signup->email) . '</td>';
            echo '<td>' . esc_html($signup->phone) . '</td>';
            echo '<td>' . esc_html($signup->status) . '</td>';
            echo '<td>' . esc_html($signup->created_at) . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="6">No SMS signups yet.</td></tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}

function golf_simulator_theme_ensure_welcome_page() {
    $page_slug = 'welcome';
    $page = get_page_by_path($page_slug);

    if ($page) {
        return;
    }

    $page_id = wp_insert_post(array(
        'post_title' => 'Welcome',
        'post_name' => $page_slug,
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_content' => '',
    ));

    if ($page_id && !is_wp_error($page_id)) {
        update_post_meta($page_id, '_wp_page_template', 'page-welcome.php');
    }

    if ($page_id && !is_wp_error($page_id)) {
        update_post_meta($page_id, '_wp_page_template', 'default');
    }
}
add_action('init', 'golf_simulator_theme_ensure_welcome_page');

function golf_simulator_theme_render_welcome_signup_form() {
    if (isset($_GET['success'])) {
        return '<div class="welcome-success"><strong>Thanks!</strong> You are on the welcome list for launch updates.</div>';
    }

    if (isset($_GET['error'])) {
        return '<div class="welcome-error">Please enter both your email address and phone number.</div>';
    }

    ob_start();
    ?>
    <div class="welcome-signup-wrap">
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="welcome-signup-form">
            <input type="hidden" name="action" value="golf_simulator_welcome_signup">
            <?php wp_nonce_field('golf_simulator_welcome_signup', 'golf_simulator_welcome_nonce'); ?>

            <div class="welcome-field">
                <label for="welcome_name">Full Name</label>
                <input id="welcome_name" type="text" name="full_name" placeholder="Full Name" />
            </div>

            <div class="welcome-field">
                <label for="welcome_email">Email Address</label>
                <input id="welcome_email" type="email" name="email" placeholder="Email Address" required />
            </div>

            <div class="welcome-field">
                <label for="welcome_phone">Phone Number</label>
                <input id="welcome_phone" type="tel" name="phone" placeholder="Phone Number" required />
            </div>

            <button type="submit" class="btn btn-primary">GET EARLY ACCESS</button>
        </form>
        <p class="welcome-footnote">We’ll only contact you with Tee Time Nexus updates and launch information.</p>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('welcome_signup_form', 'golf_simulator_theme_render_welcome_signup_form');

function golf_simulator_theme_process_welcome_signup() {
    if (!isset($_POST['golf_simulator_welcome_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['golf_simulator_welcome_nonce'])), 'golf_simulator_welcome_signup')) {
        wp_die('Security check failed.');
    }

    $full_name = sanitize_text_field(wp_unslash($_POST['full_name'] ?? ''));
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));

    if (empty($email) || empty($phone)) {
        wp_safe_redirect(home_url('/welcome?error=1'));
        exit;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'welcome_signups';
    $sms_table = $wpdb->prefix . 'welcome_sms_signups';

    $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE email = %s LIMIT 1", $email));

    if ($existing) {
        $wpdb->update(
            $table_name,
            array(
                'full_name' => $full_name,
                'phone' => $phone,
                'source' => 'homepage_waitlist',
                'channel' => 'email',
            ),
            array('id' => $existing),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );
    } else {
        $wpdb->insert(
            $table_name,
            array(
                'full_name' => $full_name,
                'email' => $email,
                'phone' => $phone,
                'source' => 'homepage_waitlist',
                'channel' => 'email',
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
    }

    golf_simulator_theme_send_opening_signup_email($email, $full_name);

    if (!empty($phone)) {
        $sms_existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $sms_table WHERE phone = %s LIMIT 1", $phone));

        if ($sms_existing) {
            $wpdb->update(
                $sms_table,
                array(
                    'full_name' => $full_name,
                    'email' => $email,
                    'source' => 'homepage_waitlist',
                    'status' => 'pending',
                ),
                array('id' => $sms_existing),
                array('%s', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            $wpdb->insert(
                $sms_table,
                array(
                    'full_name' => $full_name,
                    'email' => $email,
                    'phone' => $phone,
                    'source' => 'homepage_waitlist',
                    'status' => 'pending',
                ),
                array('%s', '%s', '%s', '%s', '%s')
            );
        }
    }

    wp_safe_redirect(home_url('/welcome?success=1'));
    exit;
}
add_action('admin_post_nopriv_golf_simulator_welcome_signup', 'golf_simulator_theme_process_welcome_signup');
add_action('admin_post_golf_simulator_welcome_signup', 'golf_simulator_theme_process_welcome_signup');

function golf_simulator_theme_register_mobile_welcome_signup_route() {
    register_rest_route('ttn/v1', '/welcome-signup', array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'golf_simulator_theme_process_mobile_welcome_signup',
        'permission_callback' => '__return_true',
        'args' => array(
            'full_name' => array('required' => false, 'sanitize_callback' => 'sanitize_text_field'),
            'email' => array('required' => true, 'sanitize_callback' => 'sanitize_email'),
            'phone' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
        ),
    ));
}
add_action('rest_api_init', 'golf_simulator_theme_register_mobile_welcome_signup_route');

/**
 * Simple per-IP rate limit for the public welcome-signup endpoint (max 5 submissions/hour).
 */
function golf_simulator_theme_welcome_signup_is_rate_limited() {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $key = 'ttn_welcome_signup_' . md5($ip);
    $count = (int) get_transient($key);

    if ($count >= 5) {
        return true;
    }

    set_transient($key, $count + 1, HOUR_IN_SECONDS);
    return false;
}

function golf_simulator_theme_process_mobile_welcome_signup(WP_REST_Request $request) {
    if (golf_simulator_theme_welcome_signup_is_rate_limited()) {
        return new WP_Error('too_many_requests', 'Too many signup attempts. Please try again later.', array('status' => 429));
    }

    $full_name = sanitize_text_field($request->get_param('full_name'));
    $email = sanitize_email($request->get_param('email'));
    $phone = sanitize_text_field($request->get_param('phone'));

    if (!is_email($email) || empty($phone)) {
        return new WP_Error('invalid_signup', 'Please enter a valid email address and phone number.', array('status' => 400));
    }

    if (!preg_match('/^[0-9()+\-.\s]{7,20}$/', $phone)) {
        return new WP_Error('invalid_signup', 'Please enter a valid phone number.', array('status' => 400));
    }

    global $wpdb;
    $signup_table = $wpdb->prefix . 'welcome_signups';
    $sms_table = $wpdb->prefix . 'welcome_sms_signups';
    $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $signup_table WHERE email = %s LIMIT 1", $email));

    $signup_data = array('full_name' => $full_name, 'email' => $email, 'phone' => $phone, 'source' => 'mobile_app', 'channel' => 'email');
    if ($existing) {
        $wpdb->update($signup_table, $signup_data, array('id' => $existing), array('%s', '%s', '%s', '%s', '%s'), array('%d'));
    } else {
        $wpdb->insert($signup_table, $signup_data, array('%s', '%s', '%s', '%s', '%s'));
    }

    golf_simulator_theme_send_opening_signup_email($email, $full_name);

    $sms_existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $sms_table WHERE phone = %s LIMIT 1", $phone));
    $sms_data = array('full_name' => $full_name, 'email' => $email, 'phone' => $phone, 'source' => 'mobile_app', 'status' => 'pending');
    if ($sms_existing) {
        $wpdb->update($sms_table, $sms_data, array('id' => $sms_existing), array('%s', '%s', '%s', '%s', '%s'), array('%d'));
    } else {
        $wpdb->insert($sms_table, $sms_data, array('%s', '%s', '%s', '%s', '%s'));
    }

    return new WP_REST_Response(array('success' => true), 201);
}
