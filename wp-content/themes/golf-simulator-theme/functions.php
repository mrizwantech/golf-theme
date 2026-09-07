<?php

function golf_simulator_theme_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo');
    add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script'));

    register_nav_menus(array(
        'primary' => __('Primary Menu', 'golf-simulator-theme'),
    ));
}
add_action('after_setup_theme', 'golf_simulator_theme_setup');

require_once get_template_directory() . '/inc/membership.php';

function golf_simulator_theme_render_launch_screen() {
    $template = get_template_directory() . '/page-splash.php';
    if (!file_exists($template)) {
        return;
    }

    include $template;
}
add_action('wp_footer', 'golf_simulator_theme_render_launch_screen', 999);

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

function golf_simulator_theme_create_membership_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'user_memberships';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        package_name varchar(100) NOT NULL DEFAULT '',
        package_slug varchar(100) NOT NULL DEFAULT '',
        price varchar(50) DEFAULT '',
        discount_price varchar(50) DEFAULT '',
        payment_status varchar(30) NOT NULL DEFAULT 'pending',
        status varchar(30) NOT NULL DEFAULT 'pending',
        payment_date datetime NULL,
        next_billing_date datetime NULL,
        start_date datetime NULL,
        cancel_date datetime NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY package_name (package_name),
        KEY status (status)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
add_action('init', 'golf_simulator_theme_create_membership_table');

function golf_simulator_theme_get_user_membership_record($user_id) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'user_memberships';

    return $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d ORDER BY id DESC LIMIT 1", absint($user_id))
    );
}

function golf_simulator_theme_save_user_membership_record($args = array()) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'user_memberships';
    $defaults = array(
        'user_id' => 0,
        'package_name' => '',
        'package_slug' => '',
        'price' => '',
        'discount_price' => '',
        'payment_status' => 'pending',
        'status' => 'pending',
        'payment_date' => '',
        'next_billing_date' => '',
        'start_date' => '',
        'cancel_date' => '',
    );

    $data = wp_parse_args($args, $defaults);

    $user_id = absint($data['user_id']);
    if (empty($user_id)) {
        return false;
    }

    $package_name = sanitize_text_field($data['package_name']);
    $package_slug = sanitize_title($data['package_slug'] ?: $package_name);
    $status = in_array($data['status'], array('active', 'pending', 'cancelled', 'paused', 'upgraded', 'downgraded'), true) ? $data['status'] : 'pending';
    $payment_status = in_array($data['payment_status'], array('paid', 'pending', 'failed', 'cancelled'), true) ? $data['payment_status'] : 'pending';
    $payment_date = !empty($data['payment_date']) ? $data['payment_date'] : current_time('mysql');
    $start_date = !empty($data['start_date']) ? $data['start_date'] : current_time('mysql');
    $next_billing_date = !empty($data['next_billing_date']) ? $data['next_billing_date'] : '';
    $cancel_date = !empty($data['cancel_date']) ? $data['cancel_date'] : '';

    $record = array(
        'user_id' => $user_id,
        'package_name' => $package_name,
        'package_slug' => $package_slug,
        'price' => sanitize_text_field($data['price']),
        'discount_price' => sanitize_text_field($data['discount_price']),
        'payment_status' => $payment_status,
        'status' => $status,
        'payment_date' => $payment_date,
        'next_billing_date' => $next_billing_date,
        'start_date' => $start_date,
        'cancel_date' => $cancel_date,
        'updated_at' => current_time('mysql'),
    );

    $existing = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d ORDER BY id DESC LIMIT 1", $user_id)
    );

    if ($existing) {
        $wpdb->update(
            $table_name,
            $record,
            array('id' => $existing->id),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );

        return $existing->id;
    }

    $wpdb->insert(
        $table_name,
        $record,
        array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
    );

    return $wpdb->insert_id;
}

function golf_simulator_theme_update_member_membership_status($user_id, $package_name, $status, $payment_status = 'paid', $payment_date = null, $next_billing_date = null, $cancel_date = null) {
    $package_data = golf_simulator_theme_get_default_membership_packages();
    $package = $package_data[$package_name] ?? array();

    return golf_simulator_theme_save_user_membership_record(array(
        'user_id' => $user_id,
        'package_name' => $package_name,
        'package_slug' => $package_name,
        'price' => $package['price'] ?? '',
        'discount_price' => $package['discount_price'] ?? '',
        'payment_status' => $payment_status,
        'status' => $status,
        'payment_date' => $payment_date ? $payment_date : current_time('mysql'),
        'next_billing_date' => $next_billing_date ?: '',
        'cancel_date' => $cancel_date ?: '',
        'start_date' => current_time('mysql'),
    ));
}

function golf_simulator_theme_send_welcome_email($email, $full_name = '') {
    if (empty($email) || !is_email($email)) {
        return;
    }

    $name = trim($full_name);
    $display_name = !empty($name) ? $name : 'Friend';
    $site_name = get_bloginfo('name');

    $subject = 'Welcome to ' . $site_name;
    $message = "Hello $display_name,\r\n\r\nThank you for joining our welcome list for the launch of Tee Time Nexus in Mooresville, NC. We will keep you updated on opening news, special offers, and early access details.\r\n\r\nBest,\r\n" . $site_name;

    wp_mail($email, $subject, $message);
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

        golf_simulator_theme_send_welcome_email($email, $full_name);
    }

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

function golf_simulator_theme_get_seo_description() {
    $default = 'Indoor golf simulator experience with premium bay rentals, coaching, leagues, and private events for players of all levels.';

    if (is_front_page()) {
        return $default;
    }

    if (is_singular()) {
        $post = get_post();
        if ($post) {
            if (has_excerpt($post)) {
                return wp_trim_words(wp_strip_all_tags($post->post_excerpt), 24, '...');
            }

            $content = wp_strip_all_tags($post->post_content);
            if (!empty($content)) {
                return wp_trim_words($content, 24, '...');
            }
        }
    }

    return get_bloginfo('description') ?: $default;
}

function golf_simulator_theme_get_seo_title() {
    $site_name = get_bloginfo('name');

    if (is_front_page()) {
        if (get_bloginfo('description')) {
            return $site_name . ' | ' . get_bloginfo('description');
        }

        return $site_name . ' | Premium Indoor Golf Simulator Experience';
    }

    if (is_singular()) {
        return get_the_title() . ' | ' . $site_name;
    }

    if (is_archive()) {
        return get_the_archive_title() . ' | ' . $site_name;
    }

    return wp_title('|', false, 'right') . $site_name;
}

function golf_simulator_theme_render_seo_meta() {
    global $wp;

    $site_name = get_bloginfo('name');
    $current_url = home_url(add_query_arg(array(), $wp->request));
    $title = wp_strip_all_tags(golf_simulator_theme_get_seo_title());
    $description = wp_strip_all_tags(golf_simulator_theme_get_seo_description());
    $description = preg_replace('/\s+/', ' ', $description);
    $image_url = get_theme_mod('golf_simulator_og_image', 'https://images.unsplash.com/photo-1535131749006-b7f58c99034b?auto=format&fit=crop&w=1600&q=80');

    echo "<meta name=\"description\" content=\"" . esc_attr($description) . "\" />\n";
    echo "<meta name=\"robots\" content=\"index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1\" />\n";
    echo "<link rel=\"canonical\" href=\"" . esc_url($current_url) . "\" />\n";

    echo "<meta property=\"og:locale\" content=\"" . esc_attr(str_replace('_', '-', get_locale())) . "\" />\n";
    echo "<meta property=\"og:type\" content=\"website\" />\n";
    echo "<meta property=\"og:title\" content=\"" . esc_attr($title) . "\" />\n";
    echo "<meta property=\"og:description\" content=\"" . esc_attr($description) . "\" />\n";
    echo "<meta property=\"og:url\" content=\"" . esc_url($current_url) . "\" />\n";
    echo "<meta property=\"og:site_name\" content=\"" . esc_attr($site_name) . "\" />\n";
    echo "<meta property=\"og:image\" content=\"" . esc_url($image_url) . "\" />\n";
    echo "<meta property=\"og:image:alt\" content=\"" . esc_attr($title) . "\" />\n";

    echo "<meta name=\"twitter:card\" content=\"summary_large_image\" />\n";
    echo "<meta name=\"twitter:title\" content=\"" . esc_attr($title) . "\" />\n";
    echo "<meta name=\"twitter:description\" content=\"" . esc_attr($description) . "\" />\n";
    echo "<meta name=\"twitter:image\" content=\"" . esc_url($image_url) . "\" />\n";
    echo "<meta name=\"twitter:site\" content=\"@teetimenexus\" />\n";
    echo "<meta name=\"twitter:creator\" content=\"@teetimenexus\" />\n";
}
add_action('wp_head', 'golf_simulator_theme_render_seo_meta', 1);

function golf_simulator_theme_render_local_business_schema() {
    if (!is_front_page() && !is_singular()) {
        return;
    }

    $schema = array(
        '@context' => 'https://schema.org',
        '@type' => 'SportsActivityLocation',
        'name' => get_bloginfo('name'),
        'description' => golf_simulator_theme_get_seo_description(),
        'url' => home_url('/'),
        'telephone' => '+1-555-123-4567',
        'email' => 'hello@teetimenexus.com',
        'address' => array(
            '@type' => 'PostalAddress',
            'streetAddress' => '123 Golf Lane',
            'addressLocality' => 'Your City',
            'addressRegion' => 'TX',
            'postalCode' => '75001',
            'addressCountry' => 'US',
        ),
        'openingHours' => 'Mo-Su 10:00-22:00',
        'sameAs' => array(
            'https://www.facebook.com/',
            'https://www.instagram.com/',
        ),
    );

    echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>' . "\n";
}
add_action('wp_head', 'golf_simulator_theme_render_local_business_schema', 2);

function golf_simulator_theme_enqueue_assets() {
    $theme_version = wp_get_theme()->get('Version');
    $style_version = file_exists(get_stylesheet_directory() . '/style.css') ? filemtime(get_stylesheet_directory() . '/style.css') : $theme_version;

    wp_enqueue_style('golf-simulator-theme-style', get_stylesheet_uri(), array(), $style_version);
    wp_enqueue_script(
        'golf-simulator-theme-slider',
        get_template_directory_uri() . '/assets/js/slider.js',
        array(),
        $theme_version,
        true
    );
}
add_action('wp_enqueue_scripts', 'golf_simulator_theme_enqueue_assets');

function golf_simulator_theme_sanitize_color_theme($value) {
    $allowed = array('dark-green', 'light', 'dark-cyan');
    return in_array($value, $allowed, true) ? $value : 'dark-green';
}

function golf_simulator_theme_get_color_theme() {
    return golf_simulator_theme_sanitize_color_theme(get_theme_mod('golf_simulator_color_theme', 'dark-green'));
}

function golf_simulator_theme_body_class($classes) {
    $classes[] = 'theme-' . golf_simulator_theme_get_color_theme();
    return $classes;
}
add_filter('body_class', 'golf_simulator_theme_body_class');

/**
 * Overrides the base :root palette per admin-selected theme; light swaps to white/black,
 * dark-cyan keeps the dark palette but swaps the accent color to #23D5EA.
 */
function golf_simulator_theme_render_color_theme_css() {
    $theme = golf_simulator_theme_get_color_theme();

    if ($theme === 'light') {
        $vars = array(
            '--primary' => '#0f5132',
            '--secondary' => '#0f5132',
            '--primary-rgb' => '15, 81, 50',
            '--primary-hover' => '#147a45',
            '--primary-contrast' => '#ffffff',
            '--bg' => '#ffffff',
            '--text' => '#101010',
            '--muted' => '#4b5563',
            '--shadow' => '0 18px 45px rgba(0, 0, 0, 0.12)',
            '--surface' => '#ffffff',
            '--surface-strong' => '#ffffff',
            '--surface-header' => '#ffffff',
            '--heading' => '#101010',
            '--border-soft' => 'rgba(0, 0, 0, 0.1)',
            '--border-soft-strong' => 'rgba(0, 0, 0, 0.16)',
            '--panel-input-bg' => 'rgba(0, 0, 0, 0.04)',
            '--panel-input-border' => 'rgba(0, 0, 0, 0.14)',
            '--btn-secondary-border' => 'rgba(0, 0, 0, 0.35)',
            '--footer-bg' => '#f3f4f6',
            '--footer-text' => '#101010',
        );
    } elseif ($theme === 'dark-cyan') {
        $vars = array(
            '--primary' => '#23D5EA',
            '--secondary' => '#23D5EA',
            '--primary-rgb' => '35, 213, 234',
            '--primary-hover' => '#5be3f3',
            '--primary-contrast' => '#0a1a1d',
        );
    } else {
        return;
    }

    echo '<style id="golf-simulator-theme-color-overrides">:root{';
    foreach ($vars as $property => $value) {
        echo esc_attr($property) . ':' . esc_attr($value) . ';';
    }
    echo '}</style>' . "\n";
}
// Priority 100 ensures this prints after the enqueued stylesheet's own :root block.
add_action('wp_head', 'golf_simulator_theme_render_color_theme_css', 100);

function golf_simulator_theme_customize_register($wp_customize) {
    $wp_customize->add_section('golf_simulator_branding_section', array(
        'title' => __('Theme Branding', 'golf-simulator-theme'),
        'priority' => 20,
    ));

    $wp_customize->add_setting('golf_simulator_site_logo', array(
        'default' => '',
        'sanitize_callback' => 'esc_url_raw',
    ));
    $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'golf_simulator_site_logo', array(
        'label' => __('Header Logo', 'golf-simulator-theme'),
        'section' => 'golf_simulator_branding_section',
        'settings' => 'golf_simulator_site_logo',
    )));

    $wp_customize->add_setting('golf_simulator_color_theme', array(
        'default' => 'dark-green',
        'sanitize_callback' => 'golf_simulator_theme_sanitize_color_theme',
    ));
    $wp_customize->add_control('golf_simulator_color_theme', array(
        'label' => __('Color Theme', 'golf-simulator-theme'),
        'description' => __('Choose the site-wide color palette.', 'golf-simulator-theme'),
        'section' => 'golf_simulator_branding_section',
        'type' => 'select',
        'choices' => array(
            'dark-green' => __('Dark Green (default)', 'golf-simulator-theme'),
            'light' => __('Light', 'golf-simulator-theme'),
            'dark-cyan' => __('Dark Cyan (#23D5EA)', 'golf-simulator-theme'),
        ),
    ));

    $wp_customize->add_section('golf_simulator_slider_section', array(
        'title' => __('Homepage Slider', 'golf-simulator-theme'),
        'priority' => 30,
    ));

    $slides = array(
        1 => array(
            'label' => __('Slide 1', 'golf-simulator-theme'),
            'default_image' => 'https://images.unsplash.com/photo-1535131749006-b7f58c99034b?auto=format&fit=crop&w=1600&q=80',
            'default_kicker' => 'Tee Time Nexus • Far Nexes LLC',
            'default_heading' => 'Indoor golf that feels like your next championship round.',
            'default_text' => 'Welcome to Tee Time Nexus, your modern golf simulator destination for practice, entertainment, leagues, and business events.',
            'default_button_1' => 'View Packages',
            'default_button_1_url' => '#packages',
            'default_button_2' => 'Book a Session',
            'default_button_2_url' => '#contact',
        ),
        2 => array(
            'label' => __('Slide 2', 'golf-simulator-theme'),
            'default_image' => 'https://images.unsplash.com/photo-1593111774278-0b6b02b7961c?auto=format&fit=crop&w=1600&q=80',
            'default_kicker' => 'Practice. Play. Perform.',
            'default_heading' => 'Train smarter with high-performance simulator sessions.',
            'default_text' => 'Use Tee Time Nexus for coaching, private play, and feature-packed bay rentals that keep every visit exciting.',
            'default_button_1' => 'Explore Services',
            'default_button_1_url' => '#services',
            'default_button_2' => 'Reserve a Bay',
            'default_button_2_url' => '#contact',
        ),
        3 => array(
            'label' => __('Slide 3', 'golf-simulator-theme'),
            'default_image' => 'https://images.unsplash.com/photo-1517466787929-bc90951d0974?auto=format&fit=crop&w=1600&q=80',
            'default_kicker' => 'Book Your Next Session',
            'default_heading' => 'Built for new customers, leagues, and premium events.',
            'default_text' => 'Launch your local golf simulator business with a polished landing page that highlights fast bookings and simple pricing.',
            'default_button_1' => 'See Pricing',
            'default_button_1_url' => '#packages',
            'default_button_2' => 'Contact Us',
            'default_button_2_url' => '#contact',
        ),
    );

    foreach ($slides as $number => $slide) {
        $wp_customize->add_setting('golf_simulator_slide_' . $number . '_image', array(
            'default' => $slide['default_image'],
            'sanitize_callback' => 'esc_url_raw',
        ));
        $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'golf_simulator_slide_' . $number . '_image', array(
            'label' => $slide['label'] . ' Image',
            'section' => 'golf_simulator_slider_section',
            'settings' => 'golf_simulator_slide_' . $number . '_image',
        )));

        $wp_customize->add_setting('golf_simulator_slide_' . $number . '_kicker', array(
            'default' => $slide['default_kicker'],
            'sanitize_callback' => 'sanitize_text_field',
        ));
        $wp_customize->add_control('golf_simulator_slide_' . $number . '_kicker', array(
            'label' => $slide['label'] . ' Kicker',
            'section' => 'golf_simulator_slider_section',
            'type' => 'text',
        ));

        $wp_customize->add_setting('golf_simulator_slide_' . $number . '_heading', array(
            'default' => $slide['default_heading'],
            'sanitize_callback' => 'sanitize_text_field',
        ));
        $wp_customize->add_control('golf_simulator_slide_' . $number . '_heading', array(
            'label' => $slide['label'] . ' Heading',
            'section' => 'golf_simulator_slider_section',
            'type' => 'text',
        ));

        $wp_customize->add_setting('golf_simulator_slide_' . $number . '_text', array(
            'default' => $slide['default_text'],
            'sanitize_callback' => 'sanitize_textarea_field',
        ));
        $wp_customize->add_control('golf_simulator_slide_' . $number . '_text', array(
            'label' => $slide['label'] . ' Text',
            'section' => 'golf_simulator_slider_section',
            'type' => 'textarea',
        ));

        $wp_customize->add_setting('golf_simulator_slide_' . $number . '_button_1', array(
            'default' => $slide['default_button_1'],
            'sanitize_callback' => 'sanitize_text_field',
        ));
        $wp_customize->add_control('golf_simulator_slide_' . $number . '_button_1', array(
            'label' => $slide['label'] . ' Button 1 Text',
            'section' => 'golf_simulator_slider_section',
            'type' => 'text',
        ));

        $wp_customize->add_setting('golf_simulator_slide_' . $number . '_button_1_url', array(
            'default' => $slide['default_button_1_url'],
            'sanitize_callback' => 'esc_url_raw',
        ));
        $wp_customize->add_control('golf_simulator_slide_' . $number . '_button_1_url', array(
            'label' => $slide['label'] . ' Button 1 Link',
            'section' => 'golf_simulator_slider_section',
            'type' => 'text',
        ));

        $wp_customize->add_setting('golf_simulator_slide_' . $number . '_button_2', array(
            'default' => $slide['default_button_2'],
            'sanitize_callback' => 'sanitize_text_field',
        ));
        $wp_customize->add_control('golf_simulator_slide_' . $number . '_button_2', array(
            'label' => $slide['label'] . ' Button 2 Text',
            'section' => 'golf_simulator_slider_section',
            'type' => 'text',
        ));

        $wp_customize->add_setting('golf_simulator_slide_' . $number . '_button_2_url', array(
            'default' => $slide['default_button_2_url'],
            'sanitize_callback' => 'esc_url_raw',
        ));
        $wp_customize->add_control('golf_simulator_slide_' . $number . '_button_2_url', array(
            'label' => $slide['label'] . ' Button 2 Link',
            'section' => 'golf_simulator_slider_section',
            'type' => 'text',
        ));
    }
}
add_action('customize_register', 'golf_simulator_theme_customize_register');

function golf_simulator_theme_menu() {
    if (has_nav_menu('primary')) {
        wp_nav_menu(array(
            'theme_location' => 'primary',
            'container'      => 'nav',
            'container_class'=> 'site-nav',
            'menu_class'     => '',
            'fallback_cb'    => false,
        ));
    } else {
        echo '<nav class="site-nav"><ul><li><a href="' . esc_url(home_url('/')) . '">Home</a></li><li><a href="' . esc_url(home_url('/about-us/')) . '">About</a></li><li><a href="' . esc_url(home_url('/contact/')) . '">Contact</a></li></ul></nav>';
    }
}

/* ==========================================================================
   Branded login / register
   Replaces the default wp-login.php with an on-brand page (Template Name:
   Account Access) and a matching set of admin-post handlers.
   ========================================================================== */

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

/**
 * Processes the login form on the same request/page (no redirect-based
 * messaging) so the result is never at the mercy of page caching or a
 * transient that failed to persist. Returns an error string, or redirects
 * and exits on success.
 */
function golf_simulator_theme_process_login($redirect_to) {
    if (!isset($_POST['ttn_login_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ttn_login_nonce'])), 'ttn_user_login')) {
        return __('Security check failed. Please refresh the page and try again.', 'golf-simulator-theme');
    }

    $email = sanitize_text_field(wp_unslash($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $user = wp_signon(array(
        'user_login' => $email,
        'user_password' => $password,
        'remember' => true,
    ), is_ssl());

    if (is_wp_error($user)) {
        return __('Incorrect email or password. Please try again.', 'golf-simulator-theme');
    }

    wp_safe_redirect($redirect_to);
    exit;
}

/**
 * Processes the register form on the same request/page. See
 * golf_simulator_theme_process_login() for why this avoids redirects.
 */
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


