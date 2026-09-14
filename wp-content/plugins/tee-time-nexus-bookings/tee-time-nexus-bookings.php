<?php
/**
 * Plugin Name: Tee Time Nexus Bookings
 * Description: Adds an hourly bay reservation workflow with availability checks, calendar visibility, and Stripe-ready checkout for Tee Time Nexus.
 * Version: 1.0.0
 * Author: Muhammad Rizwan
 */

if (!defined('ABSPATH')) {
    exit;
}

function ttn_booking_send_mail($to, $subject, $message) {
    $sender_name = static function () {
        return 'Tee Time Nexus';
    };

    add_filter('wp_mail_from_name', $sender_name);
    $content_type = strpos($message, '<!doctype html>') === 0
        ? 'Content-Type: text/html; charset=UTF-8'
        : 'Content-Type: text/plain; charset=UTF-8';
    $sent = wp_mail($to, $subject, $message, array($content_type));
    remove_filter('wp_mail_from_name', $sender_name);

    return $sent;
}

function ttn_booking_get_end_time_label($time_slots, $start_index, $duration) {
    $start_slot = isset($time_slots[$start_index]) ? $time_slots[$start_index] : null;
    if (!$start_slot) {
        return '';
    }

    $end_minutes = ((int) substr($start_slot['start'], 0, 2) * 60) + (int) substr($start_slot['start'], 3, 2) + ((int) $duration * 60);
    $end_hour = (int) floor($end_minutes / 60) % 24;
    $end_minute = $end_minutes % 60;
    return date('g:i A', mktime($end_hour, $end_minute));
}

function ttn_booking_get_account_login_url() {
    $account_page = get_page_by_path('my-account');
    $account_url = $account_page ? get_permalink($account_page) : home_url('/my-account/');

    if (function_exists('golf_simulator_theme_get_login_url')) {
        return golf_simulator_theme_get_login_url($account_url);
    }

    return wp_login_url($account_url);
}

function ttn_booking_generate_unique_username($email) {
    if (function_exists('golf_simulator_theme_generate_unique_username')) {
        return golf_simulator_theme_generate_unique_username($email);
    }

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
 * Logs the customer in or creates their account when they opted to set a
 * password during guest checkout. Returns true if the customer ends up
 * authenticated (existing session, fresh signup, or successful sign-in).
 */
function ttn_booking_maybe_create_account($email, $name, $password, $confirm_password) {
    if (is_user_logged_in()) {
        return true;
    }

    if ($password === '' || $password !== $confirm_password || strlen($password) < 6) {
        return false;
    }

    if (email_exists($email)) {
        $signon = wp_signon(array(
            'user_login' => $email,
            'user_password' => $password,
            'remember' => true,
        ), is_ssl());

        return !is_wp_error($signon);
    }

    $user_id = wp_insert_user(array(
        'user_login' => ttn_booking_generate_unique_username($email),
        'user_email' => $email,
        'user_pass' => $password,
        'display_name' => $name,
        'first_name' => $name,
        'role' => 'subscriber',
    ));

    if (is_wp_error($user_id)) {
        return false;
    }

    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);

    return true;
}

function ttn_booking_get_logo_url() {
    $logo_id = get_theme_mod('custom_logo');
    $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : '';
    return $logo_url ? $logo_url : get_site_icon_url(96);
}

function ttn_booking_render_email($title, $intro, $rows, $account_url, $use_customer_template = true) {
    if (function_exists('golf_simulator_theme_render_email_template')) {
        $details_table = '<table style="width:100%;border-collapse:collapse;margin:0 0 22px;font-size:15px;color:#4b5563;">';
        foreach ($rows as $label => $value) {
            $details_table .= '<tr><td style="padding:6px 0;"><strong>' . esc_html($label) . '</strong></td><td style="padding:6px 0;text-align:right;">' . esc_html($value) . '</td></tr>';
        }
        $details_table .= '</table>';

        $body_html = '<p style="margin:0 0 16px;color:#4b5563;font-size:15px;line-height:1.6;">' . esc_html($intro) . '</p>' . $details_table;
        
        $message = golf_simulator_theme_render_email_template(
            'Reservation Confirmation',
            $title,
            $body_html,
            'View My Bookings',
            $account_url ?: home_url('/my-account/')
        );

        return array(
            'subject' => $title . ' - Tee Time Nexus',
            'message' => $message,
        );
    }

    $logo_url = ttn_booking_get_logo_url();
    $details = array();

    foreach ($rows as $label => $value) {
        $details[] = $label . ': ' . $value;
    }

    $business_address = '2785 Charlotte Hwy Suites 11&12, Mooresville, NC 28117';
    $map_url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($business_address);
    $business_phone = '+19805033288';
    $business_email = 'sales@teetimenexus.com';

    $cta_html = $account_url
        ? '<p style="margin:20px 0;text-align:center;"><a href="' . esc_url($account_url) . '" style="display:inline-block;padding:13px 22px;background:#a1e04c;color:#101010;text-decoration:none;border-radius:8px;font-weight:800;">View My Bookings</a></p>'
        : '';

    $body_html = '<p style="margin:0 0 16px;color:#4b5563;font-size:15px;line-height:1.6;">' . esc_html($intro) . '</p>'
        . '<table style="width:100%;border-collapse:collapse;margin:0 0 22px;font-size:15px;color:#4b5563;">';
    foreach ($rows as $label => $value) {
        $body_html .= '<tr><td style="padding:6px 0;"><strong>' . esc_html($label) . '</strong></td><td style="padding:6px 0;text-align:right;">' . esc_html($value) . '</td></tr>';
    }
    $body_html .= '</table>' . $cta_html
        . '<div style="margin:28px 0 0;padding:18px;background:#f3f4f6;border-radius:10px;color:#4b5563;font-size:14px;line-height:1.7;">'
        . '<strong style="color:#111827;">Tee Time Nexus</strong><br>'
        . '<a href="' . esc_url($map_url) . '" target="_blank" rel="noopener" style="color:#1769aa;text-decoration:underline;">2785 Charlotte Hwy, Suites 11 &amp; 12<br>Mooresville, NC 28117</a><br>'
        . '<a href="tel:' . esc_attr($business_phone) . '" style="color:#1769aa;text-decoration:underline;">+1 (980) 503-3288</a><br>'
        . '<a href="mailto:' . esc_attr($business_email) . '" style="color:#1769aa;text-decoration:underline;">' . esc_html($business_email) . '</a>'
        . '</div>'
        . '<p style="margin:28px 0 0;color:#4b5563;font-size:15px;line-height:1.6;"><strong>See you on the tee!</strong><br><strong>Tee Time Nexus</strong></p>';

    $logo_html = $logo_url ? '<img src="' . esc_url($logo_url) . '" alt="Tee Time Nexus" style="display:block;max-width:180px;max-height:56px;margin:0 auto 16px;">' : '<div style="font-size:24px;font-weight:800;letter-spacing:.02em;margin-bottom:16px;">Tee Time Nexus</div>';

    return array(
        'subject' => $title . ' - Tee Time Nexus',
        'message' => '<!doctype html><html><body style="margin:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;">'
            . '<div style="padding:32px 12px;"><div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">'
            . '<div style="background:#07110b;padding:28px 24px;text-align:center;color:#ffffff;">' . $logo_html . '<div style="font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:#a1e04c;font-weight:700;">Reservation Confirmation</div></div>'
            . '<div style="padding:28px 28px 32px;"><h1 style="margin:0 0 12px;font-size:24px;line-height:1.2;color:#111827;">' . esc_html($title) . '</h1>'
            . $body_html
            . '</div></div></div></body></html>',
    );
}

function ttn_booking_get_customer_email($title, $intro, $rows, $account_url) {
    $email = ttn_booking_render_email($title, $intro, $rows, $account_url);
    return $email;
}

function ttn_booking_email_template_page() {
    if (!ttn_booking_can_manage()) {
        wp_die('Unauthorized');
    }

    $default_subject = 'Your Tee Time Nexus booking details';
    $default_body = "Hi,\n\n{{intro}}\n\n{{booking_details}}\n\nView your bookings: {{account_url}}\n\nQuestions? Reply to this email and our team will help.\n\nTee Time Nexus";

    if (isset($_POST['ttn_booking_email_template_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ttn_booking_email_template_nonce'])), 'ttn_booking_email_template')) {
        update_option('ttn_booking_email_subject', sanitize_text_field(wp_unslash($_POST['ttn_booking_email_subject'] ?? $default_subject)));
        update_option('ttn_booking_email_body', sanitize_textarea_field(wp_unslash($_POST['ttn_booking_email_body'] ?? $default_body)));
        echo '<div class="notice notice-success is-dismissible"><p>Booking email template saved.</p></div>';
    }

    $subject = get_option('ttn_booking_email_subject', $default_subject);
    $body = get_option('ttn_booking_email_body', $default_body);
    ?>
    <div class="wrap">
        <h1>Booking Email Template</h1>
        <p>This template controls customer booking emails. Use these placeholders: <code>{{title}}</code>, <code>{{intro}}</code>, <code>{{booking_details}}</code>, and <code>{{account_url}}</code>.</p>
        <form method="post">
            <?php wp_nonce_field('ttn_booking_email_template', 'ttn_booking_email_template_nonce'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="ttn_booking_email_subject">Subject</label></th>
                    <td><input name="ttn_booking_email_subject" id="ttn_booking_email_subject" type="text" class="regular-text" value="<?php echo esc_attr($subject); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ttn_booking_email_body">Message</label></th>
                    <td><textarea name="ttn_booking_email_body" id="ttn_booking_email_body" rows="16" class="large-text code"><?php echo esc_textarea($body); ?></textarea></td>
                </tr>
            </table>
            <p><button type="submit" class="button button-primary">Save Email Template</button></p>
        </form>
    </div>
    <?php
}

function ttn_booking_register_cpt() {
    register_post_type('ttn_booking', array(
        'labels' => array(
            'name' => __('Bookings', 'tee-time-nexus-bookings'),
            'singular_name' => __('Booking', 'tee-time-nexus-bookings'),
        ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => 'ttn-bookings-dashboard',
        'menu_icon' => 'dashicons-calendar-alt',
        'supports' => array('title', 'editor'),
        'capability_type' => 'post',
    ));
}
add_action('init', 'ttn_booking_register_cpt');

// ===== HELPER FUNCTIONS =====

/**
 * Normalize a string by removing spaces and colons, convert to lowercase
 * Used for bay name comparison across the system
 */
function ttn_normalize_string($string) {
    return strtolower(str_replace(array(':', ' '), '', (string) $string));
}

/**
 * Normalize bay names to canonical values so old bookings remain compatible.
 */
function ttn_normalize_bay_name($bay_name) {
    $normalized = str_replace('-', '', ttn_normalize_string($bay_name));

    $mapping = array(
        'apex' => 'bay1',
        'apexbay' => 'bay1',
        'nexus' => 'bay2',
        'nexusbay' => 'bay2',
        'fairway' => 'bay3',
        'fairwaybay' => 'bay3',
        'pin' => 'bay4',
        'pinbay' => 'bay4',
        'tigerwoodsbay' => 'bay1',
        'jacknicklausbay' => 'bay2',
        'philmickelsonbay' => 'bay3',
        'rorymcilroybay' => 'bay4',
        'bay1' => 'bay1',
        'bay2' => 'bay2',
        'bay3' => 'bay3',
        'bay4' => 'bay4',
    );

    return isset($mapping[$normalized]) ? $mapping[$normalized] : $normalized;
}

function ttn_booking_get_default_bays() {
    return array(
        'bay-1' => array('name' => 'Apex', 'type' => 'dual', 'location' => 'front-right', 'premium' => false),
        'bay-2' => array('name' => 'Nexus', 'type' => 'dual', 'location' => 'front-left', 'premium' => false),
        'bay-3' => array('name' => 'Fairway', 'type' => 'right-handed', 'location' => 'back-right', 'premium' => false),
        'bay-4' => array('name' => 'Pin', 'type' => 'right-handed', 'location' => 'back-left', 'premium' => false),
    );
}

function ttn_booking_get_bay_configs() {
    $bays = get_option('ttn_bays', null);
    $needs_update = false;

    if (!is_array($bays) || empty($bays)) {
        $bays = ttn_booking_get_default_bays();
        $needs_update = true;
    } else {
        // Automatically upgrade legacy bay names (Bay 1, Bay 2, etc.) to new names
        $legacy_map = array(
            'bay-1' => 'Apex',
            'bay-2' => 'Nexus',
            'bay-3' => 'Fairway',
            'bay-4' => 'Pin',
        );
        $legacy_types = array(
            'bay-1' => 'dual',
            'bay-2' => 'dual',
            'bay-3' => 'right-handed',
            'bay-4' => 'right-handed',
        );
        foreach ($legacy_map as $k => $new_name) {
            if (isset($bays[$k]['name']) && preg_match('/^(Bay\s*[1-4]|tiger|jack|phil|rory)/i', $bays[$k]['name'])) {
                $bays[$k]['name'] = $new_name;
                $bays[$k]['type'] = $legacy_types[$k];
                $needs_update = true;
            }
        }
    }

    if ($needs_update) {
        update_option('ttn_bays', $bays);
        if (function_exists('ttn_booking_sync_bay_products')) {
            ttn_booking_sync_bay_products($bays);
        }
    }

    return $bays;
}

function ttn_booking_get_bay_config($bay_name) {
    $bays = ttn_booking_get_bay_configs();
    $normalized = ttn_normalize_bay_name($bay_name);

    foreach ($bays as $bay_key => $bay) {
        if ($normalized === ttn_normalize_bay_name($bay_key) || $normalized === ttn_normalize_bay_name($bay['name'])) {
            return array_merge(array('key' => $bay_key), $bay);
        }
    }

    return null;
}

function ttn_booking_get_hourly_price($bay_name) {
    $bay = ttn_booking_get_bay_config($bay_name);
    $standard_price = (float) get_option('ttn_standard_hourly_price', 50);
    $premium_price = (float) get_option('ttn_premium_hourly_price', 65);

    return $bay && !empty($bay['premium']) ? $premium_price : $standard_price;
}

function ttn_booking_sync_bay_products($bays) {
    if (!function_exists('wc_get_products') || !class_exists('WC_Product_Simple')) {
        return;
    }

    foreach ($bays as $bay_key => $bay) {
        $products = wc_get_products(array('sku' => $bay_key, 'limit' => 1, 'status' => 'any'));
        $product = !empty($products) ? $products[0] : new WC_Product_Simple();
        $product->set_name($bay['name'] . ' Rental');
        $product->set_sku($bay_key);
        $product->set_regular_price((string) ttn_booking_get_hourly_price($bay_key));
        $product->set_price((string) ttn_booking_get_hourly_price($bay_key));
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->save();
    }
}

/**
 * Convert any bay name format to the current display label.
 */
function ttn_get_bay_display_name($bay_name) {
    $bay = ttn_booking_get_bay_config($bay_name);
    return $bay ? $bay['name'] : (string) $bay_name;
}

/**
 * Get all bookings for a specific email address
 */
function ttn_get_user_bookings($email) {
    $user_bookings = array();
    $posts = get_posts(array(
        'post_type' => 'ttn_booking',
        'numberposts' => -1,
        'post_status' => 'publish',
        'fields' => 'ids',
    ));

    foreach ($posts as $post_id) {
        if (get_post_meta($post_id, 'ttn_booking_parent_id', true)) {
            continue;
        }
        $booking_email = get_post_meta($post_id, 'ttn_booking_email', true);
        if ($booking_email === $email) {
            $user_bookings[] = array(
                'ID' => $post_id,
                'bay' => ttn_get_bay_display_name(get_post_meta($post_id, 'ttn_booking_bay', true)),
                'date' => get_post_meta($post_id, 'ttn_booking_date', true),
                'time' => get_post_meta($post_id, 'ttn_booking_time', true),
                'duration' => intval(get_post_meta($post_id, 'ttn_booking_duration', true) ?: 1),
                'players' => intval(get_post_meta($post_id, 'ttn_booking_players', true) ?: 1),
                'phone' => get_post_meta($post_id, 'ttn_booking_phone', true),
                'name' => get_post_meta($post_id, 'ttn_booking_name', true),
                'status' => get_post_meta($post_id, 'ttn_booking_status', true) ?: 'confirmed',
                'updated_at' => get_post_meta($post_id, 'ttn_booking_updated_at', true) ?: get_the_modified_date('Y-m-d H:i:s', $post_id),
                'payment_status' => get_post_meta($post_id, 'ttn_booking_payment_status', true),
                'booking_reference' => 'TTN-' . str_pad((string) $post_id, 6, '0', STR_PAD_LEFT),
            );
        }
    }

    return $user_bookings;
}

/**
 * Save booking metadata for a post
 * @param int $post_id
 * @param array $booking_data Associative array with keys: name, phone, email, bay, date, time, duration, total_price, payment_status, stripe_token, parent_id
 */
function ttn_save_booking_metadata($post_id, $booking_data) {
    if (isset($booking_data['name'])) {
        update_post_meta($post_id, 'ttn_booking_name', $booking_data['name']);
    }
    if (isset($booking_data['phone'])) {
        update_post_meta($post_id, 'ttn_booking_phone', $booking_data['phone']);
    }
    if (isset($booking_data['email'])) {
        update_post_meta($post_id, 'ttn_booking_email', $booking_data['email']);
    }
    if (isset($booking_data['bay'])) {
        update_post_meta($post_id, 'ttn_booking_bay', $booking_data['bay']);
    }
    if (isset($booking_data['date'])) {
        update_post_meta($post_id, 'ttn_booking_date', $booking_data['date']);
    }
    if (isset($booking_data['time'])) {
        update_post_meta($post_id, 'ttn_booking_time', $booking_data['time']);
    }
    if (isset($booking_data['duration'])) {
        update_post_meta($post_id, 'ttn_booking_duration', $booking_data['duration']);
    }
    if (isset($booking_data['players'])) {
        update_post_meta($post_id, 'ttn_booking_players', $booking_data['players']);
    }
    if (isset($booking_data['total_price'])) {
        update_post_meta($post_id, 'ttn_booking_total_price', $booking_data['total_price']);
    }
    if (isset($booking_data['payment_status'])) {
        update_post_meta($post_id, 'ttn_booking_payment_status', $booking_data['payment_status']);
    }
    if (isset($booking_data['stripe_token'])) {
        update_post_meta($post_id, 'ttn_booking_stripe_token', $booking_data['stripe_token']);
    }
    if (isset($booking_data['parent_id'])) {
        update_post_meta($post_id, 'ttn_booking_parent_id', $booking_data['parent_id']);
    }
    if (!empty($booking_data['user_id'])) {
        update_post_meta($post_id, 'ttn_booking_user_id', (int) $booking_data['user_id']);
    }
    if (isset($booking_data['status'])) {
        update_post_meta($post_id, 'ttn_booking_status', $booking_data['status']);
    }
    if (isset($booking_data['updated_at'])) {
        update_post_meta($post_id, 'ttn_booking_updated_at', $booking_data['updated_at']);
    }
}

function ttn_booking_get_time_slots() {
    $slots = array(
        array('label' => '10:00 AM', 'start' => '10:00'),
        array('label' => '11:00 AM', 'start' => '11:00'),
        array('label' => '12:00 PM', 'start' => '12:00'),
        array('label' => '1:00 PM', 'start' => '13:00'),
        array('label' => '2:00 PM', 'start' => '14:00'),
        array('label' => '3:00 PM', 'start' => '15:00'),
        array('label' => '4:00 PM', 'start' => '16:00'),
        array('label' => '5:00 PM', 'start' => '17:00'),
        array('label' => '6:00 PM', 'start' => '18:00'),
        array('label' => '7:00 PM', 'start' => '19:00'),
        array('label' => '8:00 PM', 'start' => '20:00'),
        array('label' => '9:00 PM', 'start' => '21:00'),
    );
    return apply_filters('ttn_get_time_slots', $slots);
}

function ttn_booking_get_bays() {
    $bays = array();
    foreach (ttn_booking_get_bay_configs() as $bay_key => $bay) {
        $bays[$bay_key] = $bay['name'];
    }
    return $bays;
}

function ttn_booking_can_manage() {
    return current_user_can('manage_options') || current_user_can('manage_woocommerce');
}

function ttn_booking_bays_admin_page() {
    if (!ttn_booking_can_manage()) {
        wp_die('Unauthorized');
    }

    if (isset($_POST['ttn_bays_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ttn_bays_nonce'])), 'ttn_save_bays')) {
        $posted_bays = isset($_POST['bays']) && is_array($_POST['bays']) ? $_POST['bays'] : array();
        $bays = array();
        foreach ($posted_bays as $bay_key => $posted_bay) {
            $bay_key = sanitize_key($bay_key);
            $name = sanitize_text_field(wp_unslash($posted_bay['name'] ?? ''));
            if (!$bay_key || !$name) {
                continue;
            }
            $bays[$bay_key] = array(
                'name' => $name,
                'type' => sanitize_key($posted_bay['type'] ?? 'right-handed'),
                'location' => sanitize_key($posted_bay['location'] ?? 'front-right'),
                'premium' => !empty($posted_bay['premium']),
            );
        }

        if (isset($_POST['new_bay_name']) && trim(wp_unslash($_POST['new_bay_name'])) !== '') {
            $new_key = 'bay-' . wp_generate_password(8, false, false);
            $bays[$new_key] = array(
                'name' => sanitize_text_field(wp_unslash($_POST['new_bay_name'])),
                'type' => sanitize_key($_POST['new_bay_type'] ?? 'right-handed'),
                'location' => sanitize_key($_POST['new_bay_location'] ?? 'front-right'),
                'premium' => !empty($_POST['new_bay_premium']),
            );
        }

        update_option('ttn_bays', $bays);
        update_option('ttn_standard_hourly_price', max(0, (float) ($_POST['standard_hourly_price'] ?? 50)));
        update_option('ttn_premium_hourly_price', max(0, (float) ($_POST['premium_hourly_price'] ?? 65)));
        ttn_booking_sync_bay_products($bays);
        echo '<div class="notice notice-success is-dismissible"><p>Bay settings saved.</p></div>';
    }

    $bays = ttn_booking_get_bay_configs();
    $types = array('right-handed' => 'Right-handed', 'left-handed' => 'Left-handed', 'dual' => 'Dual');
    $locations = array('front-right' => 'Front right', 'front-left' => 'Front left', 'back-right' => 'Back right', 'back-left' => 'Back left');
    ?>
    <div class="wrap">
        <h1>Booking Bays</h1>
        <form method="post">
            <?php wp_nonce_field('ttn_save_bays', 'ttn_bays_nonce'); ?>
            <table class="widefat striped">
                <thead><tr><th>Name</th><th>Type</th><th>Location</th><th>Premium</th></tr></thead>
                <tbody>
                <?php foreach ($bays as $bay_key => $bay) : ?>
                    <tr>
                        <td><input class="regular-text" name="bays[<?php echo esc_attr($bay_key); ?>][name]" value="<?php echo esc_attr($bay['name']); ?>" required></td>
                        <td><select name="bays[<?php echo esc_attr($bay_key); ?>][type]"><?php foreach ($types as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected($bay['type'], $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></td>
                        <td><select name="bays[<?php echo esc_attr($bay_key); ?>][location]"><?php foreach ($locations as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected($bay['location'], $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></td>
                        <td><label><input type="checkbox" name="bays[<?php echo esc_attr($bay_key); ?>][premium]" value="1" <?php checked(!empty($bay['premium'])); ?>> Premium price</label></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td><input class="regular-text" name="new_bay_name" placeholder="New bay name"></td>
                    <td><select name="new_bay_type"><?php foreach ($types as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></td>
                    <td><select name="new_bay_location"><?php foreach ($locations as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></td>
                    <td><label><input type="checkbox" name="new_bay_premium" value="1"> Premium price</label></td>
                </tr>
                </tbody>
            </table>
            <h2>Hourly prices</h2>
            <p><label>Standard <input type="number" min="0" step="0.01" name="standard_hourly_price" value="<?php echo esc_attr(get_option('ttn_standard_hourly_price', 50)); ?>"></label>
            <label>Premium <input type="number" min="0" step="0.01" name="premium_hourly_price" value="<?php echo esc_attr(get_option('ttn_premium_hourly_price', 65)); ?>"></label></p>
            <p><button type="submit" class="button button-primary">Save Bay Settings</button></p>
        </form>
    </div>
    <?php
}

function ttn_booking_get_booking_records() {
    $posts = get_posts(array(
        'post_type' => 'ttn_booking',
        'numberposts' => -1,
        'post_status' => 'publish',
        'fields' => 'ids',
    ));

    $records = array();

    foreach ($posts as $post_id) {
        // Exclude cancelled bookings from blocking calendar slots
        if (get_post_meta($post_id, 'ttn_booking_status', true) === 'cancelled') {
            continue;
        }

        $stored_bay = get_post_meta($post_id, 'ttn_booking_bay', true);
        $records[] = array(
            'id' => $post_id,
            'bay' => ttn_get_bay_display_name($stored_bay),
            'bay_key' => ttn_booking_get_bay_config($stored_bay) ? ttn_booking_get_bay_config($stored_bay)['key'] : $stored_bay,
            'date' => get_post_meta($post_id, 'ttn_booking_date', true),
            'time' => get_post_meta($post_id, 'ttn_booking_time', true),
        );
    }

    return $records;
}

function ttn_booking_get_booked_slots($bay, $date) {
    $bookings = ttn_booking_get_booking_records();
    $booked = array();
    
    // Normalize the incoming bay name
    $bay_normalized = ttn_normalize_bay_name($bay);

    foreach ($bookings as $booking) {
        // Normalize stored bay name for comparison
        $stored_bay = ttn_normalize_bay_name(isset($booking['bay_key']) ? $booking['bay_key'] : $booking['bay']);
        if ($stored_bay === $bay_normalized && $booking['date'] === $date) {
            $booked[] = $booking['time'];
        }
    }

    return array_values(array_unique($booked));
}

function ttn_booking_is_slot_in_past($date, $time_start) {
    $timezone = wp_timezone();
    $slot_datetime = new DateTime($date . ' ' . $time_start, $timezone);
    $now = new DateTime('now', $timezone);

    return $slot_datetime < $now;
}

function ttn_booking_get_consecutive_slots($start_index, $duration, $time_slots) {
    // Get consecutive time slots starting from start_index
    $slots = array();
    for ($i = 0; $i < $duration; $i++) {
        if (isset($time_slots[$start_index + $i])) {
            $slots[] = $time_slots[$start_index + $i];
        }
    }
    return $slots;
}

function ttn_booking_check_availability_for_duration($bay, $date, $start_time_label, $duration, $time_slots, $bookings) {
    // Find the starting slot index
    $start_index = null;
    foreach ($time_slots as $index => $slot) {
        if ($slot['label'] === $start_time_label) {
            $start_index = $index;
            break;
        }
    }

    if ($start_index === null) {
        return false; // Invalid start time
    }

    // Check if we have enough slots for the requested duration
    if ($start_index + $duration > count($time_slots)) {
        return false; // Not enough slots remaining
    }

    // Normalize bay name for comparison
    $bay_normalized = ttn_normalize_bay_name($bay);

    // Check if ANY of the consecutive slots are booked
    for ($i = 0; $i < $duration; $i++) {
        $slot = $time_slots[$start_index + $i];
        
        // Check if this slot is booked
        foreach ($bookings as $booking) {
            if ($booking['date'] === $date) {
                $booking_bay = ttn_normalize_bay_name(isset($booking['bay_key']) ? $booking['bay_key'] : $booking['bay']);
                
                if ($booking_bay === $bay_normalized && $booking['time'] === $slot['label']) {
                    return false; // Slot is booked
                }
            }
        }
    }

    return true; // All slots are available
}

function ttn_booking_get_calendar_html($bay = '') {
    $month = current_time('m');
    $year = current_time('Y');
    $date = new DateTime($year . '-' . $month . '-01');
    $days_in_month = (int) $date->format('t');
    $first_day_offset = (int) $date->format('w');
    $month_name = $date->format('F Y');
    $bookings = ttn_booking_get_booking_records();
    $booked_dates = array();

    foreach ($bookings as $booking) {
        if ($bay && ttn_normalize_bay_name(isset($booking['bay_key']) ? $booking['bay_key'] : $booking['bay']) !== ttn_normalize_bay_name($bay)) {
            continue;
        }

        if (!empty($booking['date']) && substr($booking['date'], 0, 7) === $date->format('Y-m')) {
            $booked_dates[] = $booking['date'];
        }
    }

    $calendar = '<div class="booking-calendar"><div class="calendar-title">' . esc_html($month_name) . '</div><div class="calendar-weekdays"><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span></div><div class="calendar-days">';

    $day_counter = 1;
    $offset = $first_day_offset === 0 ? 6 : $first_day_offset - 1;

    for ($i = 0; $i < $offset; $i++) {
        $calendar .= '<span class="calendar-day empty"></span>';
    }

    for ($day = 1; $day <= $days_in_month; $day++) {
        $day_date = $year . '-' . $month . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
        $has_bookings = in_array($day_date, $booked_dates, true);
        $classes = 'calendar-day';

        if ($has_bookings) {
            $classes .= ' has-bookings';
        }

        $calendar .= '<span class="' . esc_attr($classes) . '"><strong>' . (int) $day . '</strong>' . ($has_bookings ? '<em>Booked</em>' : '') . '</span>';
        $day_counter++;
    }

    $calendar .= '</div></div>';

    return $calendar;
}

function ttn_booking_get_bay_product_id($bay_name) {
    if (!class_exists('WC_Product_Simple') || !function_exists('wc_get_products')) {
        return 0;
    }

    $bay_config = ttn_booking_get_bay_config($bay_name);
    $sku = $bay_config ? $bay_config['key'] : str_replace(' ', '-', strtolower($bay_name));
    $products = wc_get_products(array('sku' => $sku, 'limit' => 1, 'status' => 'any'));

    if (!empty($products) && isset($products[0])) {
        return $products[0]->get_id();
    }

    return 0;
}

function ttn_booking_add_to_cart_and_redirect($bay_name, $booking_data = array()) {
    if (!class_exists('WC_Cart') || !function_exists('wc_get_checkout_url') || !function_exists('WC')) {
        return false;
    }

    $product_id = ttn_booking_get_bay_product_id($bay_name);
    if (!$product_id) {
        return false;
    }

    try {
        $wc = WC();
        if (!$wc || !isset($wc->cart)) {
            return false;
        }

        $cart = $wc->cart;
        if (!$cart) {
            return false;
        }

        $cart->empty_cart();

        $cart_item_data = array();
        if (!empty($booking_data)) {
            $cart_item_data['ttn_booking'] = array(
                'bay' => isset($booking_data['bay']) ? $booking_data['bay'] : $bay_name,
                'date' => isset($booking_data['date']) ? $booking_data['date'] : '',
                'time' => isset($booking_data['time']) ? $booking_data['time'] : '',
                'name' => isset($booking_data['name']) ? $booking_data['name'] : '',
                'email' => isset($booking_data['email']) ? $booking_data['email'] : '',
            );
        }

        $cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);

        return wc_get_checkout_url();
    } catch (Exception $e) {
        return false;
    }
}

function ttn_booking_add_extension_to_cart_and_redirect($booking_id, $extension_data) {
    if (!class_exists('WC_Cart') || !function_exists('wc_get_checkout_url') || !function_exists('WC')) {
        return false;
    }

    $wc = WC();
    if (!$wc || !isset($wc->cart)) {
        return false;
    }

    $bay_name = $extension_data['bay'];
    $product_id = ttn_booking_get_bay_product_id($bay_name);
    if (!$product_id) {
        return false;
    }

    $wc->cart->empty_cart();

    $cart_item_data = array(
        'ttn_booking_extension' => array(
            'booking_id' => $booking_id,
            'bay' => $extension_data['bay'],
            'date' => $extension_data['date'],
            'time' => $extension_data['time'],
            'duration' => $extension_data['duration'],
            'price_difference' => $extension_data['price_difference'],
            'new_total_price' => $extension_data['new_total_price'],
        ),
    );

    $wc->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);

    return wc_get_checkout_url();
}

function ttn_booking_set_cart_item_price($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    foreach ($cart->get_cart() as $cart_item) {
        if (!empty($cart_item['ttn_booking_extension']['price_difference']) && isset($cart_item['data'])) {
            $cart_item['data']->set_price((float) $cart_item['ttn_booking_extension']['price_difference']);
        } elseif (!empty($cart_item['ttn_booking']['bay']) && isset($cart_item['data'])) {
            $cart_item['data']->set_price(ttn_booking_get_hourly_price($cart_item['ttn_booking']['bay']));
        }
    }
}
add_action('woocommerce_before_calculate_totals', 'ttn_booking_set_cart_item_price', 25);

function ttn_booking_render_cart_item_data($item_data, $cart_item) {
    if (!empty($cart_item['ttn_booking_extension'])) {
        $ext = $cart_item['ttn_booking_extension'];
        $summary = $ext['bay'] . ' • ' . $ext['date'] . ' • ' . $ext['time'] . ' (' . $ext['duration'] . 'h extension)';
        $item_data[] = array(
            'key' => __('Booking Extension', 'tee-time-nexus-bookings'),
            'value' => esc_html($summary),
        );
    } elseif (!empty($cart_item['ttn_booking'])) {
        $booking = $cart_item['ttn_booking'];
        $summary = $booking['bay'] . ' • ' . $booking['date'] . ' • ' . $booking['time'];

        $item_data[] = array(
            'key' => __('Reservation', 'tee-time-nexus-bookings'),
            'value' => esc_html($summary),
        );
    }

    return $item_data;
}
add_filter('woocommerce_get_item_data', 'ttn_booking_render_cart_item_data', 10, 2);

function ttn_booking_store_order_line_data($item, $cart_item_key, $values, $order) {
    if (!empty($values['ttn_booking_extension'])) {
        $ext = $values['ttn_booking_extension'];
        $item->add_meta_data('_ttn_booking_extension_booking_id', $ext['booking_id']);
        $item->add_meta_data('_ttn_booking_extension_bay', $ext['bay']);
        $item->add_meta_data('_ttn_booking_extension_date', $ext['date']);
        $item->add_meta_data('_ttn_booking_extension_time', $ext['time']);
        $item->add_meta_data('_ttn_booking_extension_duration', $ext['duration']);
        $item->add_meta_data('_ttn_booking_extension_price_difference', $ext['price_difference']);
        $item->add_meta_data('_ttn_booking_extension_new_total_price', $ext['new_total_price']);
    } elseif (!empty($values['ttn_booking'])) {
        $booking = $values['ttn_booking'];
        $item->add_meta_data('ttn_booking_bay', $booking['bay']);
        $item->add_meta_data('ttn_booking_date', $booking['date']);
        $item->add_meta_data('ttn_booking_time', $booking['time']);
        $item->add_meta_data('ttn_booking_name', $booking['name']);
        $item->add_meta_data('ttn_booking_email', $booking['email']);
    }
}
add_action('woocommerce_checkout_create_order_line_item', 'ttn_booking_store_order_line_data', 10, 4);

function ttn_booking_process_extension_wc_order($order_id) {
    if (!function_exists('wc_get_order')) {
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order || $order->get_meta('_ttn_booking_extension_processed') === 'yes') {
        return;
    }

    foreach ($order->get_items() as $item) {
        $booking_id = intval($item->get_meta('_ttn_booking_extension_booking_id'));
        if ($booking_id) {
            $bay = $item->get_meta('_ttn_booking_extension_bay');
            $date = $item->get_meta('_ttn_booking_extension_date');
            $time = $item->get_meta('_ttn_booking_extension_time');
            $duration = intval($item->get_meta('_ttn_booking_extension_duration'));
            $price_diff = floatval($item->get_meta('_ttn_booking_extension_price_difference'));
            $new_total_price = floatval($item->get_meta('_ttn_booking_extension_new_total_price'));

            ttn_apply_booking_schedule_update($booking_id, array(
                'bay' => $bay,
                'date' => $date,
                'time' => $time,
                'duration' => $duration,
                'new_total_price' => $new_total_price,
                'difference' => $price_diff,
            ));

            $order->update_meta_data('_ttn_booking_extension_processed', 'yes');
            $order->save();
        }
    }
}
add_action('woocommerce_order_status_completed', 'ttn_booking_process_extension_wc_order');
add_action('woocommerce_order_status_processing', 'ttn_booking_process_extension_wc_order');
add_action('woocommerce_payment_complete', 'ttn_booking_process_extension_wc_order');

function ttn_booking_extension_return_url($return_url, $order) {
    if ($order instanceof WC_Order) {
        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_ttn_booking_extension_booking_id')) {
                return add_query_arg('booking_updated', '1', home_url('/my-account/'));
            }
        }
    }
    return $return_url;
}
add_filter('woocommerce_get_return_url', 'ttn_booking_extension_return_url', 15, 2);

function ttn_booking_shortcode() {
    $time_slots = ttn_booking_get_time_slots();
    $bays = ttn_booking_get_bays();
    $default_date = current_time('Y-m-d');
    $confirmed = isset($_GET['booking']) && $_GET['booking'] === 'confirmed';
    $confirmed_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $confirmed_booking = null;

    if ($confirmed && $confirmed_id) {
        $confirmed_post = get_post($confirmed_id);
        if ($confirmed_post && $confirmed_post->post_type === 'ttn_booking') {
            $confirmed_booking = array(
                'id' => $confirmed_id,
                'reference' => 'TTN-' . str_pad((string) $confirmed_id, 6, '0', STR_PAD_LEFT),
                'name' => get_post_meta($confirmed_id, 'ttn_booking_name', true),
                'email' => get_post_meta($confirmed_id, 'ttn_booking_email', true),
                'phone' => get_post_meta($confirmed_id, 'ttn_booking_phone', true),
                'bay' => ttn_get_bay_display_name(get_post_meta($confirmed_id, 'ttn_booking_bay', true)),
                'date' => get_post_meta($confirmed_id, 'ttn_booking_date', true),
                'time' => get_post_meta($confirmed_id, 'ttn_booking_time', true),
                'duration' => intval(get_post_meta($confirmed_id, 'ttn_booking_duration', true) ?: 1),
                'players' => intval(get_post_meta($confirmed_id, 'ttn_booking_players', true) ?: 1),
                'total_price' => get_post_meta($confirmed_id, 'ttn_booking_total_price', true),
                'payment_status' => get_post_meta($confirmed_id, 'ttn_booking_payment_status', true),
            );
        }
    }

    // Get booking records using the centralized function
    $booking_records = ttn_booking_get_booking_records();

    ob_start();
    ?>
    <div class="booking-card">
        <?php if ($confirmed) : ?>
            <div class="booking-success-alert" id="successAlert">
                <div class="success-icon">✓</div>
                <div class="success-content">
                    <h2>Booking Confirmed!</h2>
                    <p>Your reservation has been successfully booked and paid.</p>

                    <?php if ($confirmed_booking) : ?>
                        <div class="success-booking-summary" style="margin: 18px 0; padding: 18px 20px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-soft); border-radius: 14px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid var(--border-soft); padding-bottom: 8px;">
                                <strong style="font-size: 1.15rem; color: var(--primary);">Booking Ref: <?php echo esc_html($confirmed_booking['reference']); ?></strong>
                                <span style="font-size: 0.85rem; color: var(--muted);"><?php echo esc_html($confirmed_booking['bay']); ?></span>
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; font-size: 0.95rem;">
                                <div>
                                    <span style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--muted); font-weight: 700;">Date</span>
                                    <strong><?php echo esc_html($confirmed_booking['date']); ?></strong>
                                </div>
                                <div>
                                    <span style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--muted); font-weight: 700;">Time</span>
                                    <strong><?php echo esc_html($confirmed_booking['time']); ?></strong>
                                </div>
                                <div>
                                    <span style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--muted); font-weight: 700;">Duration</span>
                                    <strong><?php echo esc_html($confirmed_booking['duration']); ?> <?php echo $confirmed_booking['duration'] === 1 ? 'Hour' : 'Hours'; ?></strong>
                                </div>
                                <div>
                                    <span style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--muted); font-weight: 700;">Players</span>
                                    <strong><?php echo esc_html($confirmed_booking['players']); ?></strong>
                                </div>
                            </div>
                        </div>

                        <?php if (!is_user_logged_in()) : ?>
                            <div class="guest-booking-notice" style="margin: 16px 0; padding: 14px 18px; background: rgba(161, 224, 76, 0.08); border: 1px solid rgba(161, 224, 76, 0.3); border-radius: 12px; font-size: 0.92rem; line-height: 1.6;">
                                <strong style="color: var(--primary);">Guest Reservation Note:</strong>
                                <p style="margin: 6px 0 0; color: #ffffff;">To modify or reschedule this reservation online, <a href="<?php echo esc_url(golf_simulator_theme_get_login_url(home_url('/my-account/'), 'register')); ?>" style="color: var(--primary); text-decoration: underline; font-weight: 700;">create an account using <?php echo esc_html($confirmed_booking['email']); ?></a>. Otherwise, changes can be made by calling us at <a href="tel:+19805033288" style="color: var(--primary); text-decoration: underline; font-weight: 700;">+1 (980) 503-3288</a> with your booking reference <strong><?php echo esc_html($confirmed_booking['reference']); ?></strong>.</p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="success-details">
                        <p>✓ Confirmation email sent to your inbox</p>
                        <p>✓ SMS notification if number provided</p>
                    </div>
                    <div class="success-actions">
                        <?php if (is_user_logged_in()) : ?>
                            <a href="<?php echo esc_url(home_url('/my-account/')); ?>" class="btn btn-small-white">View My Bookings</a>
                        <?php else : ?>
                            <a href="<?php echo esc_url(golf_simulator_theme_get_login_url(home_url('/my-account/'), 'register')); ?>" class="btn btn-small-white">Create Account &amp; Manage</a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(home_url('/book-a-bay/')); ?>" class="btn btn-small-white">Book Another Bay</a>
                    </div>
                </div>
                <button class="success-close" onclick="document.getElementById('successAlert').style.display='none';">×</button>
            </div>
        <?php endif; ?>

        <p class="booking-note">Select your simulator type, choose a bay, pick your date and time, then proceed to payment.</p>

        <div class="booking-section">
            <h3>Select Simulator Type</h3>
            <div class="bay-type-selector" id="ttn-bay-type-selector">
                <label class="bay-type-pill">
                    <input type="radio" name="bay_type" value="dual" checked />
                    <span>Dual (Left & Right Handed)</span>
                </label>
                <label class="bay-type-pill">
                    <input type="radio" name="bay_type" value="right-handed" />
                    <span>Right-Handed</span>
                </label>
            </div>
        </div>

        <div class="booking-section" id="ttn-bay-section">
            <h3>Select Bay</h3>
            <div class="bay-selector" id="ttn-bay-selector">
                <?php $bay_index = 0; ?>
                <?php foreach ($bays as $bay_key => $bay_label) : ?>
                    <?php $bay_config = ttn_booking_get_bay_config($bay_key); ?>
                    <label class="bay-pill" data-bay-type="<?php echo esc_attr($bay_config['type'] ?? 'right-handed'); ?>">
                        <input type="radio" name="bay" value="<?php echo esc_attr($bay_label); ?>" data-bay-key="<?php echo esc_attr($bay_key); ?>" data-bay-type="<?php echo esc_attr($bay_config['type'] ?? 'right-handed'); ?>" data-price="<?php echo esc_attr(ttn_booking_get_hourly_price($bay_key)); ?>" <?php checked($bay_index, 0); ?> />
                        <span><?php echo esc_html($bay_label); ?></span>
                    </label>
                    <?php $bay_index++; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="booking-section">
            <h3>Select Date</h3>
            <input type="date" id="ttn-date" value="<?php echo esc_attr($default_date); ?>" min="<?php echo esc_attr($default_date); ?>" required>
        </div>

        <div class="booking-section">
            <h3>Duration (Hours)</h3>
            <div class="duration-selector" id="ttn-duration-selector">
                <?php for ($h = 1; $h <= 8; $h++) : ?>
                    <label class="duration-pill">
                        <input type="radio" name="duration" value="<?php echo esc_attr($h); ?>" data-duration="<?php echo esc_attr($h); ?>" <?php checked($h, 1); ?> />
                        <span><?php echo esc_html($h); ?> <?php echo $h === 1 ? 'Hour' : 'Hours'; ?></span>
                    </label>
                <?php endfor; ?>
            </div>
        </div>

        <div class="booking-section">
            <h3>Players</h3>
            <div class="player-selector" id="ttn-player-selector">
                <?php for ($p = 1; $p <= 4; $p++) : ?>
                    <label class="player-pill">
                        <input type="radio" name="players" value="<?php echo esc_attr($p); ?>" data-players="<?php echo esc_attr($p); ?>" <?php checked($p, 1); ?> />
                        <span><?php echo esc_html($p); ?></span>
                    </label>
                <?php endfor; ?>
            </div>
        </div>

        <div class="booking-section">
            <h3>Select Start Time</h3>
            <div class="time-slots" id="ttn-time-slots">
                <?php foreach ($time_slots as $slot) : ?>
                    <button type="button" class="time-slot-pill" data-time="<?php echo esc_attr($slot['label']); ?>" data-start="<?php echo esc_attr($slot['start']); ?>">
                        <?php echo esc_html($slot['label']); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="booking-summary" id="ttn-selection-summary">
            Choose a bay, date, and time to continue.
        </div>

        <button class="btn btn-primary" id="ttn-proceed-to-payment" disabled>Proceed to Payment</button>
    </div>

    <script>
    (function () {
        const bookingRecords = <?php echo wp_json_encode($booking_records); ?>;
        const timeSlots = <?php echo wp_json_encode($time_slots); ?>;
        const baySelector = document.getElementById('ttn-bay-selector');
        const dateField = document.getElementById('ttn-date');
        const durationSelector = document.getElementById('ttn-duration-selector');
        const timeSlots_el = document.getElementById('ttn-time-slots');
        const summary = document.getElementById('ttn-selection-summary');
        const proceedBtn = document.getElementById('ttn-proceed-to-payment');

        let selectedBay = null;
        let selectedDate = null;
        let selectedTime = null;
        let selectedDuration = 1;
        let selectedPlayers = 1;

        function updateTimeSlots() {
            const bay = document.querySelector('input[name="bay"]:checked');
            if (!bay) {
                timeSlots_el.style.display = 'none';
                return;
            }

            timeSlots_el.style.display = 'flex';
            selectedBay = bay.value;
            selectedDate = dateField.value;
            selectedDuration = parseInt(document.querySelector('input[name="duration"]:checked')?.value || 1);

            const today = new Date();
            const selectedDateObj = new Date(selectedDate + 'T00:00:00');
            const todaysDateObj = new Date(today.getFullYear(), today.getMonth(), today.getDate());
            const isToday = selectedDateObj.getTime() === todaysDateObj.getTime();

            const bayCanonical = (selectedBay || '').toLowerCase().replace(/[\s-]+/g, '');
            const booked = bookingRecords
                .filter(item => {
                    const itemBayName = (item.bay || '').toLowerCase().replace(/[\s-]+/g, '');
                    const itemBayKey = (item.bay_key || '').toLowerCase().replace(/[\s-]+/g, '');
                    const matchesBay = (itemBayName === bayCanonical || itemBayKey === bayCanonical || item.bay === selectedBay);
                    return matchesBay && item.date === selectedDate;
                })
                .map(item => item.time);

            let availableSlotCount = 0;
            document.querySelectorAll('.time-slot-pill').forEach((btn, index) => {
                const slotTime = btn.getAttribute('data-time');
                const slotStart = btn.getAttribute('data-start');
                const slotStartObj = new Date(selectedDate + 'T' + slotStart);
                const isPast = isToday && slotStartObj < today;

                // Check if this slot OR any following slots for the duration are booked
                let isAvailable = true;
                if (isPast || index + selectedDuration > timeSlots.length) {
                    isAvailable = false;
                } else {
                    // Check all consecutive slots
                    for (let i = 0; i < selectedDuration; i++) {
                        const checkSlot = timeSlots[index + i];
                        if (checkSlot && booked.includes(checkSlot.label)) {
                            isAvailable = false;
                            break;
                        }
                    }
                }

                btn.classList.remove('selected', 'disabled');
                if (!isAvailable) {
                    btn.classList.add('disabled');
                    btn.disabled = true;
                } else {
                    btn.disabled = false;
                    availableSlotCount++;
                }
            });

            // If previously selected time is no longer available for this duration, reset it
            if (selectedTime) {
                const selectedBtn = document.querySelector('.time-slot-pill[data-time="' + selectedTime + '"]');
                if (!selectedBtn || selectedBtn.disabled) {
                    selectedTime = null;
                }
            }

            updateSelectedTimeRangeUI();
            updateSummary(availableSlotCount);
        }

        function calculateEndTimeLabel(startLabel, durationHours) {
            const slot = timeSlots.find(s => s.label === startLabel);
            if (!slot || !slot.start) {
                return startLabel;
            }
            const parts = slot.start.split(':');
            const startMinutes = (parseInt(parts[0], 10) * 60) + parseInt(parts[1], 10);
            const endMinutes = startMinutes + (parseInt(durationHours, 10) * 60);
            let endHour = Math.floor(endMinutes / 60) % 24;
            const endMinute = endMinutes % 60;
            const period = endHour >= 12 ? 'PM' : 'AM';
            let displayHour = endHour % 12;
            if (displayHour === 0) displayHour = 12;
            const displayMinute = endMinute < 10 ? '0' + endMinute : endMinute;
            return displayHour + ':' + displayMinute + ' ' + period;
        }

        function updateSelectedTimeRangeUI() {
            document.querySelectorAll('.time-slot-pill').forEach(btn => {
                const btnTime = btn.getAttribute('data-time');
                btn.classList.toggle('selected', selectedTime && btnTime === selectedTime);
            });
        }

        function updateSummary(availableSlotCount) {
            const bayInput = document.querySelector('input[name="bay"]:checked');
            const bay = bayInput ? bayInput.value : 'No bay selected';
            const date = dateField.value;
            const duration = document.querySelector('input[name="duration"]:checked')?.value || 1;
            const players = document.querySelector('input[name="players"]:checked')?.value || 1;
            const time = selectedTime || 'No time selected';
            const hourlyPrice = parseFloat(bayInput?.getAttribute('data-price') || 0);
            const totalPrice = parseInt(duration) * hourlyPrice;

            if (selectedTime) {
                const endTime = calculateEndTimeLabel(selectedTime, duration);
                
                summary.innerHTML = `<strong>${bay}</strong><br/>${date} • ${time} - ${endTime} (${duration}h) • ${players}<br/><strong>Total: $${totalPrice}</strong>`;
            } else {
                if (typeof availableSlotCount !== 'undefined' && availableSlotCount === 0) {
                    summary.innerHTML = `<strong>${bay}</strong><br/>${date} • No ${duration}-hour time blocks available on this date. Please choose another date or fewer hours.`;
                } else {
                    summary.innerHTML = '<strong>' + bay + '</strong><br/>' + date + ' • No time selected • ' + players + '<br/><strong>$' + totalPrice + '</strong>';
                }
            }

            if (selectedBay && selectedDate && selectedTime) {
                proceedBtn.disabled = false;
            } else {
                proceedBtn.disabled = true;
            }
        }

        function updateBayTypeSelectionUI() {
            document.querySelectorAll('.bay-type-pill').forEach(pill => {
                pill.classList.remove('selected');
            });

            const checkedType = document.querySelector('input[name="bay_type"]:checked');
            if (checkedType) {
                const selectedPill = checkedType.closest('.bay-type-pill');
                if (selectedPill) {
                    selectedPill.classList.add('selected');
                }
            }
        }

        function updateBaySelectionUI() {
            document.querySelectorAll('.bay-pill').forEach(pill => {
                pill.classList.remove('selected');
            });

            const checkedBay = document.querySelector('input[name="bay"]:checked');
            if (checkedBay) {
                const selectedPill = checkedBay.closest('.bay-pill');
                if (selectedPill) {
                    selectedPill.classList.add('selected');
                }
            }
        }

        function updateDurationSelectionUI() {
            document.querySelectorAll('.duration-pill').forEach(pill => {
                pill.classList.remove('selected');
            });

            const checkedDuration = document.querySelector('input[name="duration"]:checked');
            if (checkedDuration) {
                const selectedPill = checkedDuration.closest('.duration-pill');
                if (selectedPill) {
                    selectedPill.classList.add('selected');
                }
            }
        }

        function updatePlayersSelectionUI() {
            document.querySelectorAll('.player-pill').forEach(pill => {
                pill.classList.remove('selected');
            });

            const checkedPlayers = document.querySelector('input[name="players"]:checked');
            if (checkedPlayers) {
                const selectedPill = checkedPlayers.closest('.player-pill');
                if (selectedPill) {
                    selectedPill.classList.add('selected');
                }
            }
        }

        function filterBaysByType() {
            const checkedType = document.querySelector('input[name="bay_type"]:checked')?.value;
            const bayPills = document.querySelectorAll('.bay-pill');

            if (!checkedType) {
                bayPills.forEach(pill => {
                    pill.style.display = 'none';
                });
                return;
            }

            let currentlySelectedBayStillVisible = false;
            bayPills.forEach(pill => {
                const pillType = pill.getAttribute('data-bay-type');
                if (pillType === checkedType) {
                    pill.style.display = 'inline-flex';
                    const radio = pill.querySelector('input[name="bay"]');
                    if (radio && radio.checked) {
                        currentlySelectedBayStillVisible = true;
                    }
                } else {
                    pill.style.display = 'none';
                    const radio = pill.querySelector('input[name="bay"]');
                    if (radio) {
                        radio.checked = false;
                    }
                }
            });

            if (!currentlySelectedBayStillVisible) {
                const firstVisibleRadio = document.querySelector('.bay-pill[data-bay-type="' + checkedType + '"] input[name="bay"]');
                if (firstVisibleRadio) {
                    firstVisibleRadio.checked = true;
                }
            }

            updateBaySelectionUI();
            selectedTime = null;
            document.querySelectorAll('.time-slot-pill').forEach(btn => btn.classList.remove('selected'));
            updateTimeSlots();
        }

        document.querySelectorAll('input[name="bay_type"]').forEach(radio => {
            radio.addEventListener('change', () => {
                updateBayTypeSelectionUI();
                filterBaysByType();
            });
            radio.addEventListener('click', () => {
                updateBayTypeSelectionUI();
                filterBaysByType();
            });
        });

        document.querySelectorAll('input[name="bay"]').forEach(radio => {
            radio.addEventListener('change', () => {
                updateBaySelectionUI();
                selectedTime = null;
                document.querySelectorAll('.time-slot-pill').forEach(btn => btn.classList.remove('selected'));
                updateTimeSlots();
            });

            // Clicking an already checked radio (common after browser back) won't fire change.
            // This ensures Bay 1 can be used immediately when state is restored.
            radio.addEventListener('click', () => {
                updateBaySelectionUI();
                updateTimeSlots();
            });
        });

        document.querySelectorAll('input[name="duration"]').forEach(radio => {
            radio.addEventListener('change', () => {
                updateDurationSelectionUI();
                const nextSelectedTime = selectedTime;
                if (nextSelectedTime) {
                    updateSelectedTimeRangeUI();
                } else {
                    document.querySelectorAll('.time-slot-pill').forEach(btn => btn.classList.remove('selected'));
                }
                updateTimeSlots();
            });
        });

        document.querySelectorAll('input[name="players"]').forEach(radio => {
            radio.addEventListener('change', () => {
                updatePlayersSelectionUI();
                selectedPlayers = parseInt(radio.value, 10) || 1;
                updateSummary();
            });
        });

        dateField.addEventListener('click', function() {
            if (typeof this.showPicker === 'function') {
                try {
                    this.showPicker();
                } catch (err) {}
            }
        });

        dateField.addEventListener('change', () => {
            selectedTime = null;
            document.querySelectorAll('.time-slot-pill').forEach(btn => btn.classList.remove('selected'));
            updateTimeSlots();
        });

        document.querySelectorAll('.time-slot-pill').forEach(btn => {
            btn.addEventListener('click', (e) => {
                if (btn.disabled) return;
                selectedTime = btn.getAttribute('data-time');
                updateSelectedTimeRangeUI();
                updateSummary();
            });
        });

        proceedBtn.addEventListener('click', () => {
            const checkoutUrl = new URL('<?php echo esc_url(home_url('/booking-checkout/')); ?>');
            checkoutUrl.searchParams.set('bay', encodeURIComponent(selectedBay));
            checkoutUrl.searchParams.set('date', selectedDate);
            checkoutUrl.searchParams.set('time', encodeURIComponent(selectedTime));
            checkoutUrl.searchParams.set('duration', document.querySelector('input[name="duration"]:checked').value);
            checkoutUrl.searchParams.set('players', document.querySelector('input[name="players"]:checked').value);
            window.location.href = checkoutUrl.toString();
        });

        // Initialize from browser-restored state (e.g., when user navigates back).
        const checkedBayInitial = document.querySelector('input[name="bay"]:checked');
        if (checkedBayInitial) {
            const initialType = checkedBayInitial.getAttribute('data-bay-type');
            const matchingTypeRadio = document.querySelector('input[name="bay_type"][value="' + initialType + '"]');
            if (matchingTypeRadio) {
                matchingTypeRadio.checked = true;
            }
        }
        updateBayTypeSelectionUI();
        filterBaysByType();
        updateBaySelectionUI();
        updateDurationSelectionUI();
        updatePlayersSelectionUI();
        if (document.querySelector('input[name="bay"]:checked')) {
            updateTimeSlots();
        } else {
            timeSlots_el.style.display = 'none';
        }
    })();
    </script>
    <?php if ($confirmed) : ?>
    <script>
    (function() {
        const successAlert = document.getElementById('successAlert');
        const countdownEl = document.getElementById('countdown');
        let seconds = 60;

        if (successAlert) {
            const timer = setInterval(() => {
                seconds--;
                if (countdownEl) {
                    countdownEl.textContent = seconds;
                }
                if (seconds <= 0) {
                    clearInterval(timer);
                    window.location.href = '<?php echo esc_url(home_url('/book-a-bay/')); ?>';
                }
            }, 1000);
        }
    })();
    </script>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}
add_shortcode('ttn_booking_form', 'ttn_booking_shortcode');

function ttn_booking_submit() {
    if (!isset($_POST['ttn_booking_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ttn_booking_nonce'])), 'ttn_booking_submit')) {
        wp_die(__('Security check failed.', 'tee-time-nexus-bookings'));
    }

    $name = sanitize_text_field(wp_unslash($_POST['name']));
    $phone = sanitize_text_field(wp_unslash($_POST['phone']));
    $email = sanitize_email(wp_unslash($_POST['email']));
    $bay = sanitize_text_field(wp_unslash($_POST['bay']));
    $date = sanitize_text_field(wp_unslash($_POST['date']));
    $time = sanitize_text_field(wp_unslash($_POST['time']));
    $notes = sanitize_textarea_field(wp_unslash($_POST['notes']));
    $players = isset($_POST['players']) ? max(1, min(4, intval($_POST['players']))) : 1;

    $time_slots = ttn_booking_get_time_slots();
    $selected_slot = null;
    foreach ($time_slots as $slot) {
        if ($slot['label'] === $time) {
            $selected_slot = $slot;
            break;
        }
    }

    if (!$selected_slot) {
        wp_die(__('Please choose a valid time slot.', 'tee-time-nexus-bookings'));
    }

    if (ttn_booking_is_slot_in_past($date, $selected_slot['start'])) {
        wp_die(__('Please choose a future time slot.', 'tee-time-nexus-bookings'));
    }

    $booked_slots = ttn_booking_get_booked_slots($bay, $date);
    if (in_array($time, $booked_slots, true)) {
        wp_die(__('That time slot is no longer available. Please choose another time.', 'tee-time-nexus-bookings'));
    }

    $post_id = wp_insert_post(array(
        'post_type' => 'ttn_booking',
        'post_status' => 'publish',
        'post_title' => $name . ' - ' . $date,
        'post_content' => sprintf(
            "Bay: %s\nDate: %s\nTime: %s\nPhone: %s\nEmail: %s\nNotes: %s",
            $bay,
            $date,
            $time,
            $phone,
            $email,
            $notes
        ),
    ), true);

    if (!is_wp_error($post_id)) {
        update_post_meta($post_id, 'ttn_booking_name', $name);
        update_post_meta($post_id, 'ttn_booking_phone', $phone);
        update_post_meta($post_id, 'ttn_booking_email', $email);
        update_post_meta($post_id, 'ttn_booking_bay', $bay);
        update_post_meta($post_id, 'ttn_booking_date', $date);
        update_post_meta($post_id, 'ttn_booking_time', $time);
        update_post_meta($post_id, 'ttn_booking_players', $players);
        update_post_meta($post_id, 'ttn_booking_notes', $notes);

        $admin_email = get_option('admin_email');
        $subject = 'New Tee Time Nexus Booking Request';
        $message = sprintf(
            "New booking request from %s.\n\nBay: %s\nDate: %s\nTime: %s\nPhone: %s\nEmail: %s\nNotes: %s",
            $name,
            $bay,
            $date,
            $time,
            $phone,
            $email,
            $notes
        );

        ttn_booking_send_mail($admin_email, $subject, $message);
        $customer_email = ttn_booking_get_customer_email(
            'Reservation request received',
            'Hi ' . $name . ', we received your reservation request. Please complete checkout to secure your booking.',
            array(
                'Bay' => ttn_get_bay_display_name($bay),
                'Date' => $date,
                'Time' => $time,
                'Players' => (string) $players,
            ),
            ttn_booking_get_account_login_url()
        );
        ttn_booking_send_mail($email, $customer_email['subject'], $customer_email['message']);
    }

    $checkout_url = ttn_booking_add_to_cart_and_redirect($bay, array(
        'bay' => $bay,
        'date' => $date,
        'time' => $time,
        'name' => $name,
        'email' => $email,
    ));
    if ($checkout_url) {
        wp_safe_redirect($checkout_url);
        exit;
    }

    wp_safe_redirect(home_url('/book-a-bay/?booking=success'));
    exit;
}
add_action('admin_post_ttn_booking_submit', 'ttn_booking_submit');
add_action('admin_post_nopriv_ttn_booking_submit', 'ttn_booking_submit');

function ttn_booking_checkout() {
    if (!isset($_POST['ttn_checkout_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ttn_checkout_nonce'])), 'ttn_booking_checkout')) {
        wp_die(__('Security check failed.', 'tee-time-nexus-bookings'));
    }

    $name = sanitize_text_field(wp_unslash($_POST['name']));
    $email = sanitize_email(wp_unslash($_POST['email']));
    $phone = sanitize_text_field(wp_unslash($_POST['phone']));
    $bay = sanitize_text_field(wp_unslash($_POST['bay']));
    $date = sanitize_text_field(wp_unslash($_POST['date']));
    $time = trim(sanitize_text_field(wp_unslash($_POST['time'])));
    $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 1;
    $players = isset($_POST['players']) ? max(1, min(4, intval($_POST['players']))) : 1;
    $stripe_token = isset($_POST['stripeToken']) ? sanitize_text_field(wp_unslash($_POST['stripeToken'])) : '';
    $new_password = isset($_POST['create_account_password']) ? (string) wp_unslash($_POST['create_account_password']) : '';
    $new_password_confirm = isset($_POST['create_account_password_confirm']) ? (string) wp_unslash($_POST['create_account_password_confirm']) : '';

    if (!ttn_booking_get_bay_config($bay)) {
        wp_die(__('Please choose a valid bay.', 'tee-time-nexus-bookings'));
    }

    if (!$stripe_token) {
        wp_die(__('Payment token is missing.', 'tee-time-nexus-bookings'));
    }

    if (!$time || $time === 'null' || $time === 'undefined') {
        wp_die(__('Please select a valid time slot and try again.', 'tee-time-nexus-bookings'));
    }

    $time_slots = ttn_booking_get_time_slots();
    $selected_slot = null;
    $start_index = null;
    
    // Normalize time for comparison
    $time_normalized = ttn_normalize_string($time);
    
    foreach ($time_slots as $index => $slot) {
        $slot_normalized = ttn_normalize_string($slot['label']);
        if ($slot_normalized === $time_normalized) {
            $selected_slot = $slot;
            $start_index = $index;
            break;
        }
    }

    if (!$selected_slot) {
        // Debug: show what we received vs what we expected
        $available_times = array_map(function($s) { return $s['label']; }, $time_slots);
        $error_msg = sprintf(
            'Invalid time slot. Received: "%s" (length: %d). Available: %s',
            $time,
            strlen($time),
            implode(', ', $available_times)
        );
        wp_die(__($error_msg, 'tee-time-nexus-bookings'));
    }

    // Check if all consecutive slots are available
    if ($start_index + $duration > count($time_slots)) {
        wp_die(__('Not enough consecutive hours available for the selected time.', 'tee-time-nexus-bookings'));
    }

    // Check all consecutive slots for conflicts
    $bookings = ttn_booking_get_booking_records();
    for ($i = 0; $i < $duration; $i++) {
        $check_slot = $time_slots[$start_index + $i];
        if (ttn_booking_is_slot_in_past($date, $check_slot['start'])) {
            wp_die(__('Cannot book a time slot in the past.', 'tee-time-nexus-bookings'));
        }
        
        // Check if booked
        foreach ($bookings as $booking) {
            $booking_bay = ttn_normalize_bay_name($booking['bay']);
            $check_bay = ttn_normalize_bay_name($bay);
            
            if ($booking_bay === $check_bay && $booking['date'] === $date && $booking['time'] === $check_slot['label']) {
                wp_die(__('One or more of the requested time slots are no longer available.', 'tee-time-nexus-bookings'));
            }
        }
    }

    // Create booking entries for each hour
    $total_price = $duration * ttn_booking_get_hourly_price($bay);
    $parent_booking_id = null;
    $account_created = ttn_booking_maybe_create_account($email, $name, $new_password, $new_password_confirm);
    $booking_user_id = get_current_user_id();

    for ($i = 0; $i < $duration; $i++) {
        $hour_slot = $time_slots[$start_index + $i];
        $hour_time_label = $hour_slot['label'];

        $post_id = wp_insert_post(array(
            'post_type' => 'ttn_booking',
            'post_status' => 'publish',
            'post_title' => $name . ' - ' . $bay . ' - ' . $date,
            'post_content' => sprintf(
                "Bay: %s\nDate: %s\nTime: %s\nDuration: %d hours\nPlayers: %d\nName: %s\nPhone: %s\nEmail: %s\nStripe Token: %s",
                $bay,
                $date,
                $hour_time_label,
                $duration,
                $players,
                $name,
                $phone,
                $email,
                $stripe_token
            ),
        ), true);

        if (!is_wp_error($post_id)) {
            // Save booking metadata using centralized helper
            ttn_save_booking_metadata($post_id, array(
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'bay' => $bay,
                'date' => $date,
                'time' => $hour_time_label,
                'duration' => $duration,
                'players' => $players,
                'total_price' => $total_price,
                'payment_status' => 'Payment submitted - transaction verification required',
                'stripe_token' => $stripe_token,
                'parent_id' => ($i > 0) ? $parent_booking_id : null,
                'user_id' => $booking_user_id,
            ));

            // Keep track of parent booking
            if ($i === 0) {
                $parent_booking_id = $post_id;
            }
        }
    }

    // Send confirmation email (only once, from first booking)
    if ($parent_booking_id) {
        $end_time_label = ttn_booking_get_end_time_label($time_slots, $start_index, $duration);
        $account_url = ttn_booking_get_account_login_url();
        $booking_reference = 'TTN-' . str_pad((string) $parent_booking_id, 6, '0', STR_PAD_LEFT);
        $payment_status = 'Payment submitted - transaction verification required';

        $intro = 'Hi ' . $name . ', your reservation details are below. Keep this email for your records.';
        if ($account_created && $new_password !== '') {
            $intro .= ' We also set up your account so you can log in with this email to manage future bookings.';
        }
        $customer_email = ttn_booking_render_email(
            'Reservation received',
            $intro,
            array(
                'Booking reference' => $booking_reference,
                'Bay' => ttn_get_bay_display_name($bay),
                'Date' => $date,
                'Time' => $selected_slot['label'] . ' - ' . $end_time_label,
                'Duration' => $duration . ($duration === 1 ? ' hour' : ' hours'),
                'Players' => (string) $players,
                'Amount' => '$' . number_format($total_price, 2),
                'Payment status' => $payment_status,
            ),
            $account_url,
            false
        );
        ttn_booking_send_mail($email, $customer_email['subject'], $customer_email['message']);

        // Send admin notification
        $admin_email = get_option('admin_email');
        $admin_subject = 'New Booking: ' . $name . ' - ' . $bay;
        $admin_email_message = ttn_booking_render_email(
            'New booking received',
            'A new reservation was submitted through the Tee Time Nexus booking form.',
            array(
                'Booking reference' => $booking_reference,
                'Customer' => $name,
                'Email' => $email,
                'Phone' => $phone,
                'Bay' => ttn_get_bay_display_name($bay),
                'Date' => $date,
                'Time' => $selected_slot['label'] . ' - ' . $end_time_label,
                'Duration' => $duration . ($duration === 1 ? ' hour' : ' hours'),
                'Players' => (string) $players,
                'Amount' => '$' . number_format($total_price, 2),
                'Payment status' => $payment_status,
            ),
            $account_url
        );
        ttn_booking_send_mail($admin_email, $admin_subject, $admin_email_message['message']);
    }

    wp_safe_redirect(home_url('/book-a-bay/?booking=confirmed&id=' . $parent_booking_id));
    exit;
}
add_action('admin_post_ttn_booking_checkout', 'ttn_booking_checkout');
add_action('admin_post_nopriv_ttn_booking_checkout', 'ttn_booking_checkout');

// ===== ADMIN BOOKING DASHBOARD =====

function ttn_add_admin_menu() {
    add_menu_page(
        'Booking Management',
        'Bookings Manager',
        'manage_woocommerce',
        'ttn-bookings-dashboard',
        'ttn_render_booking_dashboard',
        'dashicons-calendar-alt',
        25
    );

    add_submenu_page(
        'ttn-bookings-dashboard',
        'Email Template',
        'Email Template',
        'manage_woocommerce',
        'ttn-booking-email-template',
        'ttn_booking_email_template_page'
    );
}
add_action('admin_menu', 'ttn_add_admin_menu');

function ttn_render_booking_dashboard() {
    if (!ttn_booking_can_manage()) {
        wp_die('Unauthorized');
    }

    // Handle actions
    if (isset($_GET['action']) && isset($_GET['booking_id'])) {
        $booking_id = intval($_GET['booking_id']);
        $action = sanitize_text_field($_GET['action']);
        $nonce = isset($_GET['nonce']) ? sanitize_text_field($_GET['nonce']) : '';

        if (!wp_verify_nonce($nonce, 'ttn_booking_action')) {
            wp_die('Nonce verification failed');
        }

        if ($action === 'delete') {
            wp_delete_post($booking_id);
            echo '<div class="notice notice-success is-dismissible"><p>Booking deleted successfully.</p></div>';
        } elseif ($action === 'send_reminder') {
            ttn_send_booking_reminder($booking_id);
            echo '<div class="notice notice-success is-dismissible"><p>Reminder email sent to customer.</p></div>';
        }
    }

    // Handle edit/update
    if (isset($_POST['ttn_update_booking']) && check_admin_referer('ttn_update_booking_nonce')) {
        $booking_id = intval($_POST['booking_id']);
        $bay = sanitize_text_field($_POST['bay']);
        $date = sanitize_text_field($_POST['date']);
        $time = sanitize_text_field($_POST['time']);
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);

        ttn_save_booking_metadata($booking_id, array(
            'bay' => $bay,
            'date' => $date,
            'time' => $time,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        ));

        wp_update_post(array(
            'ID' => $booking_id,
            'post_title' => $name . ' - ' . $bay . ' - ' . $date,
        ));

        echo '<div class="notice notice-success is-dismissible"><p>Booking updated successfully.</p></div>';
    }

    // Check if editing
    $edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
    $booking_to_edit = null;

    if ($edit_id) {
        $booking_to_edit = array(
            'ID' => $edit_id,
            'name' => get_post_meta($edit_id, 'ttn_booking_name', true),
            'email' => get_post_meta($edit_id, 'ttn_booking_email', true),
            'phone' => get_post_meta($edit_id, 'ttn_booking_phone', true),
            'bay' => ttn_get_bay_display_name(get_post_meta($edit_id, 'ttn_booking_bay', true)),
            'date' => get_post_meta($edit_id, 'ttn_booking_date', true),
            'time' => get_post_meta($edit_id, 'ttn_booking_time', true),
        );
    }

    $bays = ttn_booking_get_bays();
    $time_slots = ttn_booking_get_time_slots();

    $all_posts = get_posts(array(
        'post_type' => 'ttn_booking',
        'numberposts' => -1,
        'post_status' => 'publish',
        'orderby' => 'ID',
        'order' => 'DESC',
    ));

    $all_bookings_admin = array();
    foreach ($all_posts as $p) {
        if (get_post_meta($p->ID, 'ttn_booking_parent_id', true)) {
            continue; // Only show parent/main reservations
        }
        $stored_bay = get_post_meta($p->ID, 'ttn_booking_bay', true);
        $all_bookings_admin[] = array(
            'ID' => $p->ID,
            'reference' => 'TTN-' . str_pad((string) $p->ID, 6, '0', STR_PAD_LEFT),
            'name' => get_post_meta($p->ID, 'ttn_booking_name', true) ?: '—',
            'email' => get_post_meta($p->ID, 'ttn_booking_email', true) ?: '—',
            'phone' => get_post_meta($p->ID, 'ttn_booking_phone', true) ?: '—',
            'bay' => ttn_get_bay_display_name($stored_bay),
            'date' => get_post_meta($p->ID, 'ttn_booking_date', true),
            'time' => get_post_meta($p->ID, 'ttn_booking_time', true),
            'status' => get_post_meta($p->ID, 'ttn_booking_status', true) ?: 'confirmed',
            'updated_at' => get_post_meta($p->ID, 'ttn_booking_updated_at', true) ?: '',
        );
    }
    ?>
    <div class="wrap">
        <h1>Booking Management Dashboard</h1>

        <?php if ($booking_to_edit) : ?>
        <div style="background: #f8f9fa; padding: 20px; margin-bottom: 20px; border-radius: 5px;">
            <h2>Edit Booking</h2>
            <form method="post">
                <?php wp_nonce_field('ttn_update_booking_nonce'); ?>
                <input type="hidden" name="ttn_update_booking" value="1">
                <input type="hidden" name="booking_id" value="<?php echo esc_attr($booking_to_edit['ID']); ?>">
                
                <table class="form-table">
                    <tr>
                        <th><label for="name">Customer Name</label></th>
                        <td><input type="text" id="name" name="name" value="<?php echo esc_attr($booking_to_edit['name']); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="email">Email</label></th>
                        <td><input type="email" id="email" name="email" value="<?php echo esc_attr($booking_to_edit['email']); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="phone">Phone</label></th>
                        <td><input type="tel" id="phone" name="phone" value="<?php echo esc_attr($booking_to_edit['phone']); ?>"></td>
                    </tr>
                    <tr>
                        <th><label>Bay</label></th>
                        <td>
                            <strong><?php echo esc_html($booking_to_edit['bay']); ?></strong>
                            <input type="hidden" name="bay" value="<?php echo esc_attr($booking_to_edit['bay']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="date">Date</label></th>
                        <td><input type="date" id="date" name="date" value="<?php echo esc_attr($booking_to_edit['date']); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="time">Time</label></th>
                        <td>
                            <select id="time" name="time" required>
                                <?php foreach ($time_slots as $slot) : ?>
                                    <option value="<?php echo esc_attr($slot['label']); ?>" <?php selected($booking_to_edit['time'], $slot['label']); ?>>
                                        <?php echo esc_html($slot['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="submit" class="button button-primary">Update Booking</button>
                    <a href="?page=ttn-bookings-dashboard" class="button">Cancel</a>
                </p>
            </form>
        </div>
        <?php endif; ?>

        <h2>All Bookings</h2>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th>Ref</th>
                    <th>Customer Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Bay</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th>Updated / Cancelled</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($all_bookings_admin)) : ?>
                    <?php foreach ($all_bookings_admin as $b_admin) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($b_admin['reference']); ?></strong></td>
                            <td><?php echo esc_html($b_admin['name']); ?></td>
                            <td><?php echo esc_html($b_admin['email']); ?></td>
                            <td><?php echo esc_html($b_admin['phone']); ?></td>
                            <td><?php echo esc_html($b_admin['bay']); ?></td>
                            <td><?php echo esc_html($b_admin['date']); ?></td>
                            <td><?php echo esc_html($b_admin['time']); ?></td>
                            <td>
                                <?php if ($b_admin['status'] === 'cancelled') : ?>
                                    <span style="color: #d63638; font-weight: 700;">Cancelled</span>
                                <?php elseif ($b_admin['status'] === 'updated') : ?>
                                    <span style="color: #007017; font-weight: 700;">Updated</span>
                                <?php else : ?>
                                    <span style="color: #2271b1; font-weight: 700;">Confirmed</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($b_admin['updated_at'])) : ?>
                                    <small><?php echo esc_html(mysql2date('M j, Y g:i A', $b_admin['updated_at'])); ?></small>
                                <?php else : ?>
                                    <small>—</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $b_id = $b_admin['ID'];
                                $edit_url = wp_nonce_url(admin_url('admin.php?page=ttn-bookings-dashboard&edit=' . $b_id), 'ttn_booking_action', 'nonce');
                                $reminder_url = wp_nonce_url(admin_url('admin.php?page=ttn-bookings-dashboard&action=send_reminder&booking_id=' . $b_id), 'ttn_booking_action', 'nonce');
                                $delete_url = wp_nonce_url(admin_url('admin.php?page=ttn-bookings-dashboard&action=delete&booking_id=' . $b_id), 'ttn_booking_action', 'nonce');
                                ?>
                                <?php if ($b_admin['status'] !== 'cancelled') : ?>
                                    <a href="<?php echo esc_url($edit_url); ?>" class="button button-small">Edit</a>
                                    <a href="<?php echo esc_url($reminder_url); ?>" class="button button-small">Send Reminder</a>
                                <?php endif; ?>
                                <a href="<?php echo esc_url($delete_url); ?>" class="button button-small button-link-delete" onclick="return confirm('Are you sure you want to permanently delete this record?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="10">No bookings found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function ttn_send_booking_reminder($booking_id) {
    $name = get_post_meta($booking_id, 'ttn_booking_name', true);
    $email = get_post_meta($booking_id, 'ttn_booking_email', true);
    $bay = ttn_get_bay_display_name(get_post_meta($booking_id, 'ttn_booking_bay', true));
    $date = get_post_meta($booking_id, 'ttn_booking_date', true);
    $time = get_post_meta($booking_id, 'ttn_booking_time', true);

    $customer_email = ttn_booking_get_customer_email(
        'Upcoming reservation reminder',
        'Hi ' . $name . ', this is a friendly reminder about your upcoming reservation.',
        array(
            'Bay' => $bay,
            'Date' => $date,
            'Time' => $time,
        ),
        ttn_booking_get_account_login_url()
    );

    ttn_booking_send_mail($email, $customer_email['subject'], $customer_email['message']);
}

function ttn_booking_get_stripe_secret_key() {
    if (class_exists('WC_Stripe_API') && method_exists('WC_Stripe_API', 'get_secret_key')) {
        return WC_Stripe_API::get_secret_key();
    }
    $settings = get_option('woocommerce_stripe_settings', array());
    $is_test = !empty($settings['testmode']) && 'yes' === $settings['testmode'];
    return $is_test ? ($settings['test_secret_key'] ?? '') : ($settings['secret_key'] ?? '');
}

function ttn_booking_refund_stripe_amount($charge_id, $amount) {
    $secret_key = ttn_booking_get_stripe_secret_key();
    if (!$secret_key || !$charge_id || $amount <= 0) {
        return false;
    }

    $response = wp_remote_post('https://api.stripe.com/v1/refunds', array(
        'timeout' => 30,
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode($secret_key . ':'),
        ),
        'body' => array(
            'charge' => $charge_id,
            'amount' => (int) round($amount * 100),
        ),
    ));

    if (is_wp_error($response)) {
        return false;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (wp_remote_retrieve_response_code($response) < 300 && !empty($body['id'])) {
        return $body['id'];
    }

    return false;
}

function ttn_apply_booking_schedule_update($booking_id, $data) {
    $name = get_post_meta($booking_id, 'ttn_booking_name', true);
    $email = get_post_meta($booking_id, 'ttn_booking_email', true);
    $phone = get_post_meta($booking_id, 'ttn_booking_phone', true);
    $players = intval(get_post_meta($booking_id, 'ttn_booking_players', true) ?: 1);
    $user_id = get_post_meta($booking_id, 'ttn_booking_user_id', true);

    $bay = $data['bay'];
    $date = $data['date'];
    $time = $data['time'];
    $duration = intval($data['duration']);
    $new_total_price = floatval($data['new_total_price']);
    $difference = floatval($data['difference'] ?? 0);

    // 1. Delete all existing child posts for this parent booking
    $child_posts = get_posts(array(
        'post_type' => 'ttn_booking',
        'meta_key' => 'ttn_booking_parent_id',
        'meta_value' => $booking_id,
        'numberposts' => -1,
        'fields' => 'ids',
    ));
    foreach ($child_posts as $child_id) {
        wp_delete_post($child_id, true);
    }

    // 2. Handle refund if duration was reduced ($difference < 0)
    $refund_issued = false;
    $refund_amount = abs($difference);
    $refund_note = '';

    if ($difference < 0 && $refund_amount > 0) {
        $charge_id = get_post_meta($booking_id, 'ttn_booking_stripe_charge_id', true)
            ?: get_post_meta($booking_id, 'ttn_booking_stripe_token', true);

        if ($charge_id && strpos($charge_id, 'ch_') === 0 || strpos($charge_id, 'py_') === 0) {
            $refund_id = ttn_booking_refund_stripe_amount($charge_id, $refund_amount);
            if ($refund_id) {
                $refund_issued = true;
                update_post_meta($booking_id, 'ttn_booking_refund_id', $refund_id);
                $refund_note = sprintf('Refund of $%s issued.', number_format($refund_amount, 2));
            }
        }

        if (!$refund_issued) {
            $refund_note = sprintf('Refund of $%s difference recorded.', number_format($refund_amount, 2));
        }
    }

    // 3. Create new consecutive child posts if duration > 1
    $time_slots = ttn_booking_get_time_slots();
    $start_index = 0;
    foreach ($time_slots as $idx => $slot) {
        if ($slot['label'] === $time) {
            $start_index = $idx;
            break;
        }
    }

    for ($i = 1; $i < $duration; $i++) {
        $hour_slot = $time_slots[$start_index + $i] ?? null;
        if (!$hour_slot) {
            continue;
        }

        $child_id = wp_insert_post(array(
            'post_type' => 'ttn_booking',
            'post_status' => 'publish',
            'post_title' => $name . ' - ' . $bay . ' - ' . $date,
            'post_content' => sprintf("Bay: %s\nDate: %s\nTime: %s\nDuration: %d hours\nParent: %d", $bay, $date, $hour_slot['label'], $duration, $booking_id),
        ));

        if (!is_wp_error($child_id) && $child_id) {
            ttn_save_booking_metadata($child_id, array(
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'bay' => $bay,
                'date' => $date,
                'time' => $hour_slot['label'],
                'duration' => $duration,
                'players' => $players,
                'total_price' => $new_total_price,
                'parent_id' => $booking_id,
                'user_id' => $user_id,
            ));
        }
    }

    // 4. Update parent post
    $payment_status = $difference < 0
        ? sprintf('Paid ($%s) - %s', number_format($new_total_price, 2), $refund_note)
        : sprintf('Paid ($%s)', number_format($new_total_price, 2));

    ttn_save_booking_metadata($booking_id, array(
        'bay' => $bay,
        'date' => $date,
        'time' => $time,
        'duration' => $duration,
        'total_price' => $new_total_price,
        'payment_status' => $payment_status,
        'status' => 'updated',
        'updated_at' => current_time('mysql'),
    ));

    wp_update_post(array(
        'ID' => $booking_id,
        'post_title' => $name . ' - ' . $bay . ' - ' . $date,
    ));

    // 5. Send updated confirmation email
    $end_time_label = ttn_booking_get_end_time_label($time_slots, $start_index, $duration);
    $booking_reference = 'TTN-' . str_pad((string) $booking_id, 6, '0', STR_PAD_LEFT);
    $email_rows = array(
        'Booking reference' => $booking_reference,
        'Bay' => ttn_get_bay_display_name($bay),
        'Date' => $date,
        'Time' => $time . ($end_time_label ? ' - ' . $end_time_label : ''),
        'Duration' => $duration . ($duration === 1 ? ' hour' : ' hours'),
        'Players' => (string) $players,
        'Updated Total' => '$' . number_format($new_total_price, 2),
    );
    if ($difference < 0) {
        $email_rows['Refund Credit'] = '$' . number_format($refund_amount, 2);
    }

    $customer_email = ttn_booking_render_email(
        'Reservation updated',
        'Hi ' . $name . ', your reservation has been updated successfully.' . ($difference < 0 ? ' ' . $refund_note : ''),
        $email_rows,
        home_url('/my-account/'),
        false
    );
    ttn_booking_send_mail($email, $customer_email['subject'], $customer_email['message']);

    // Admin notification
    $admin_email = get_option('admin_email');
    $admin_email_message = ttn_booking_render_email(
        'Booking Modified',
        'A reservation was modified by the customer.' . ($difference < 0 ? ' ' . $refund_note : ''),
        array_merge(array('Customer' => $name, 'Email' => $email, 'Phone' => $phone), $email_rows),
        home_url('/my-account/')
    );
    ttn_booking_send_mail($admin_email, 'Booking Modified: ' . $name . ' - ' . $bay, $admin_email_message['message']);

    $msg = 'Booking updated successfully.';
    if ($difference < 0) {
        $msg = sprintf('Booking duration reduced to %d %s ($%s). %s', $duration, $duration === 1 ? 'hour' : 'hours', number_format($new_total_price, 2), $refund_note);
    } elseif ($difference > 0) {
        $msg = sprintf('Booking extended to %d %s ($%s). Payment confirmed.', $duration, $duration === 1 ? 'hour' : 'hours', number_format($new_total_price, 2));
    }

    return array('success' => true, 'message' => $msg);
}

// ===== USER-FACING CRUD HANDLERS (BUSINESS LOGIC) =====

/**
 * Update a user booking (CRUD: Update)
 * Returns: array with 'success' boolean and 'message' string
 */
function ttn_crud_update_user_booking($booking_id, $user_email, $booking_data) {
    // Verify booking belongs to user
    $booking_email = get_post_meta($booking_id, 'ttn_booking_email', true);
    if ($booking_email !== $user_email) {
        return array('success' => false, 'message' => 'You do not have permission to edit this booking.');
    }

    // Validate required fields
    if (empty($booking_data['date']) || empty($booking_data['time']) || empty($booking_data['duration'])) {
        return array('success' => false, 'message' => 'Missing required booking information.');
    }

    // Validate date is not in past
    $time_slots = ttn_booking_get_time_slots();
    $selected_slot = null;
    $start_index = null;
    $time_normalized = ttn_normalize_string($booking_data['time']);

    foreach ($time_slots as $index => $slot) {
        if (ttn_normalize_string($slot['label']) === $time_normalized) {
            $selected_slot = $slot;
            $start_index = $index;
            break;
        }
    }

    if (!$selected_slot) {
        return array('success' => false, 'message' => 'Invalid time slot.');
    }

    if (ttn_booking_is_slot_in_past($booking_data['date'], $selected_slot['start'])) {
        return array('success' => false, 'message' => 'Cannot book a time slot in the past.');
    }

    $new_duration = intval($booking_data['duration']);
    if ($start_index + $new_duration > count($time_slots)) {
        return array('success' => false, 'message' => 'Not enough consecutive hours available for the selected time.');
    }

    $new_bay = !empty($booking_data['bay']) ? $booking_data['bay'] : get_post_meta($booking_id, 'ttn_booking_bay', true);
    $new_date = $booking_data['date'];

    // Check availability excluding current booking & its child posts
    $bookings = ttn_booking_get_booking_records();
    $existing_child_ids = get_posts(array(
        'post_type' => 'ttn_booking',
        'meta_key' => 'ttn_booking_parent_id',
        'meta_value' => $booking_id,
        'numberposts' => -1,
        'fields' => 'ids',
    ));
    $ignored_ids = array_merge(array($booking_id), (array) $existing_child_ids);

    for ($i = 0; $i < $new_duration; $i++) {
        $check_slot = $time_slots[$start_index + $i];
        foreach ($bookings as $b) {
            if (in_array((int) ($b['id'] ?? 0), $ignored_ids, true)) {
                continue;
            }
            $b_bay = ttn_normalize_bay_name($b['bay']);
            $target_bay = ttn_normalize_bay_name($new_bay);
            if ($b_bay === $target_bay && $b['date'] === $new_date && $b['time'] === $check_slot['label']) {
                return array('success' => false, 'message' => 'One or more of the requested time slots are already booked. Please choose another time.');
            }
        }
    }

    $old_duration = intval(get_post_meta($booking_id, 'ttn_booking_duration', true) ?: 1);
    $old_bay = get_post_meta($booking_id, 'ttn_booking_bay', true);
    $old_hourly_price = ttn_booking_get_hourly_price($old_bay);
    $old_total_price = floatval(get_post_meta($booking_id, 'ttn_booking_total_price', true) ?: ($old_duration * $old_hourly_price));

    $new_hourly_price = ttn_booking_get_hourly_price($new_bay);
    $new_total_price = $new_duration * $new_hourly_price;
    $difference = $new_total_price - $old_total_price;

    // Case 1: Increase in Duration (Redirect to WooCommerce Checkout for remaining balance)
    if ($difference > 0) {
        $checkout_url = ttn_booking_add_extension_to_cart_and_redirect($booking_id, array(
            'booking_id' => $booking_id,
            'bay' => $new_bay,
            'date' => $new_date,
            'time' => $selected_slot['label'],
            'duration' => $new_duration,
            'price_difference' => $difference,
            'new_total_price' => $new_total_price,
            'user_email' => $user_email,
        ));

        if ($checkout_url) {
            return array(
                'success' => true,
                'redirect' => $checkout_url,
                'message' => sprintf('Please complete checkout for the additional $%s balance.', number_format($difference, 2)),
            );
        }
    }

    // Case 2 & 3: Decrease or Same Duration
    return ttn_apply_booking_schedule_update($booking_id, array(
        'bay' => $new_bay,
        'date' => $new_date,
        'time' => $selected_slot['label'],
        'duration' => $new_duration,
        'new_total_price' => $new_total_price,
        'difference' => $difference,
    ));
}

/**
 * Cancel a user booking (CRUD: Mark Cancelled)
 * Returns: array with 'success' boolean and 'message' string
 */
function ttn_crud_cancel_user_booking($booking_id, $user_email) {
    // Verify booking belongs to user
    $booking_email = get_post_meta($booking_id, 'ttn_booking_email', true);
    if ($booking_email !== $user_email) {
        return array('success' => false, 'message' => 'You do not have permission to cancel this booking.');
    }

    // Verify booking exists
    $booking = get_post($booking_id);
    if (!$booking || $booking->post_type !== 'ttn_booking') {
        return array('success' => false, 'message' => 'Booking not found.');
    }

    // Delete child slot reservations so the time slots immediately open up on the calendar
    $child_posts = get_posts(array(
        'post_type' => 'ttn_booking',
        'meta_key' => 'ttn_booking_parent_id',
        'meta_value' => $booking_id,
        'numberposts' => -1,
        'fields' => 'ids',
    ));
    foreach ($child_posts as $child_id) {
        wp_delete_post($child_id, true);
    }

    // Attempt Stripe refund for the full booking amount if paid
    $refund_issued = false;
    $paid_total = floatval(get_post_meta($booking_id, 'ttn_booking_total_price', true) ?: 0);
    $charge_id = get_post_meta($booking_id, 'ttn_booking_stripe_charge_id', true)
        ?: get_post_meta($booking_id, 'ttn_booking_stripe_token', true);

    if ($charge_id && (strpos($charge_id, 'ch_') === 0 || strpos($charge_id, 'py_') === 0) && $paid_total > 0) {
        $refund_id = ttn_booking_refund_stripe_amount($charge_id, $paid_total);
        if ($refund_id) {
            $refund_issued = true;
            update_post_meta($booking_id, 'ttn_booking_refund_id', $refund_id);
        }
    }

    $cancel_time = current_time('mysql');
    update_post_meta($booking_id, 'ttn_booking_status', 'cancelled');
    update_post_meta($booking_id, 'ttn_booking_updated_at', $cancel_time);
    update_post_meta($booking_id, 'ttn_booking_payment_status', $refund_issued ? 'Refunded' : 'Cancelled');

    // Notify Customer
    $name = get_post_meta($booking_id, 'ttn_booking_name', true);
    $bay = get_post_meta($booking_id, 'ttn_booking_bay', true);
    $date = get_post_meta($booking_id, 'ttn_booking_date', true);
    $time = get_post_meta($booking_id, 'ttn_booking_time', true);
    $booking_reference = 'TTN-' . str_pad((string) $booking_id, 6, '0', STR_PAD_LEFT);

    $customer_email = ttn_booking_render_email(
        'Reservation Cancelled',
        'Hi ' . $name . ', your reservation (' . $booking_reference . ') has been cancelled.' . ($refund_issued ? ' A full refund of $' . number_format($paid_total, 2) . ' has been processed.' : ''),
        array(
            'Booking reference' => $booking_reference,
            'Bay' => ttn_get_bay_display_name($bay),
            'Date' => $date,
            'Time' => $time,
            'Status' => 'Cancelled',
        ),
        home_url('/my-account/'),
        false
    );
    ttn_booking_send_mail($booking_email, $customer_email['subject'], $customer_email['message']);

    // Notify Admin
    $admin_email = get_option('admin_email');
    $admin_email_message = ttn_booking_render_email(
        'Booking Cancelled by Customer',
        'Customer ' . $name . ' cancelled reservation ' . $booking_reference . '.' . ($refund_issued ? ' Refund processed.' : ''),
        array(
            'Customer' => $name,
            'Email' => $booking_email,
            'Booking reference' => $booking_reference,
            'Bay' => ttn_get_bay_display_name($bay),
            'Date' => $date,
            'Time' => $time,
            'Status' => 'Cancelled',
            'Refund' => $refund_issued ? '$' . number_format($paid_total, 2) : 'No charge ID',
        ),
        home_url('/my-account/')
    );
    ttn_booking_send_mail($admin_email, 'Booking Cancelled: ' . $name . ' - ' . $booking_reference, $admin_email_message['message']);

    return array('success' => true, 'message' => 'Booking cancelled successfully.' . ($refund_issued ? ' Refund processed.' : ''));
}

/**
 * Handle user update booking via POST (calls CRUD function)
 */
function ttn_handle_user_update_booking() {
    if (!is_user_logged_in()) {
        wp_die('You must be logged in to update a booking.');
    }

    if (!isset($_POST['booking_id']) || !check_admin_referer('ttn_update_user_booking_nonce')) {
        wp_die('Security check failed.');
    }

    $current_user = wp_get_current_user();
    $booking_id = intval($_POST['booking_id']);
    $user_email = $current_user->user_email;
    $password = (string) ($_POST['account_password'] ?? '');

    // Security check: verify account password before applying update
    if (empty($password) || !wp_check_password($password, $current_user->user_pass, $current_user->ID)) {
        set_transient('ttn_user_booking_message_' . $user_email, array(
            'success' => false,
            'message' => __('Incorrect account password. Please enter your valid account password to confirm changes.', 'tee-time-nexus-bookings'),
        ), 30);
        wp_safe_redirect(home_url('/my-account/?action=edit&booking_id=' . $booking_id));
        exit;
    }
    
    $booking_data = array(
        'bay' => sanitize_text_field(wp_unslash($_POST['bay'] ?? '')),
        'date' => sanitize_text_field(wp_unslash($_POST['date'] ?? '')),
        'time' => sanitize_text_field(wp_unslash($_POST['time'] ?? '')),
        'duration' => intval($_POST['duration'] ?? 1),
    );

    $result = ttn_crud_update_user_booking($booking_id, $user_email, $booking_data);

    if (!empty($result['redirect'])) {
        wp_safe_redirect($result['redirect']);
        exit;
    }
    
    // Store result in transient for display on redirect
    set_transient('ttn_user_booking_message_' . $user_email, $result, 30);
    
    wp_safe_redirect(home_url('/my-account/'));
    exit;
}
add_action('admin_post_ttn_update_user_booking', 'ttn_handle_user_update_booking');
add_action('admin_post_nopriv_ttn_update_user_booking', 'ttn_handle_user_update_booking');

/**
 * Handle user cancel booking via GET/POST (calls CRUD function)
 */
function ttn_handle_user_cancel_booking() {
    if (!is_user_logged_in()) {
        wp_die('You must be logged in to cancel a booking.');
    }

    $nonce = $_POST['_wpnonce'] ?? $_POST['ttn_cancel_booking_nonce'] ?? $_GET['_wpnonce'] ?? '';
    if (!wp_verify_nonce($nonce, 'ttn_cancel_booking_nonce')) {
        wp_die('Security check failed.');
    }

    $current_user = wp_get_current_user();
    $booking_id = intval($_POST['ttn_cancel_booking_id'] ?? $_GET['ttn_cancel_booking_id'] ?? 0);
    $user_email = $current_user->user_email;
    $password = (string) ($_POST['account_password'] ?? $_GET['account_password'] ?? '');

    // Security check: verify account password before processing cancellation
    if (empty($password) || !wp_check_password($password, $current_user->user_pass, $current_user->ID)) {
        set_transient('ttn_user_booking_message_' . $user_email, array(
            'success' => false,
            'message' => __('Incorrect account password. Booking cancellation was not processed.', 'tee-time-nexus-bookings'),
        ), 30);
        wp_safe_redirect(home_url('/my-account/'));
        exit;
    }

    $result = ttn_crud_cancel_user_booking($booking_id, $user_email);
    
    // Store result in transient for display on redirect
    set_transient('ttn_user_booking_message_' . $user_email, $result, 30);
    
    wp_safe_redirect(home_url('/my-account/'));
    exit;
}
add_action('admin_post_ttn_cancel_user_booking', 'ttn_handle_user_cancel_booking');
add_action('admin_post_nopriv_ttn_cancel_user_booking', 'ttn_handle_user_cancel_booking');

