<?php
// Shared branded HTML wrapper so every outgoing email (signup, welcome, membership, etc.) looks the same.

function golf_simulator_theme_get_email_logo_html() {
    $logo_id = get_theme_mod('custom_logo');
    $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : '';

    return $logo_url
        ? '<img src="' . esc_url($logo_url) . '" alt="Tee Time Nexus" style="display:block;max-width:220px;max-height:64px;margin:0 auto 18px;">'
        : '<div style="font-size:26px;font-weight:800;margin-bottom:18px;">Tee Time Nexus</div>';
}

/**
 * Wraps email content in the shared Tee Time Nexus branded template.
 *
 * @param string $eyebrow    Small uppercase label under the logo, e.g. "Account Confirmation".
 * @param string $heading    Main heading inside the white content area.
 * @param string $body_html  Pre-built HTML for the body paragraphs/lists.
 * @param string $cta_text   Optional button label.
 * @param string $cta_url    Optional button URL.
 */
function golf_simulator_theme_render_email_template($eyebrow, $heading, $body_html, $cta_text = '', $cta_url = '') {
    $cta_html = '';
    if ($cta_text && $cta_url) {
        $cta_html = '<p style="margin:0 0 22px;text-align:center;"><a href="' . esc_url($cta_url) . '" style="display:inline-block;padding:13px 20px;background:#a1e04c;color:#101010;text-decoration:none;border-radius:8px;font-weight:800;">' . esc_html($cta_text) . '</a></p>';
    }

    $business_address = '2785 Charlotte Hwy Suites 11&12, Mooresville, NC 28117';
    $map_url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($business_address);
    $business_phone = '+19805033288';
    $business_email = 'sales@teetimenexus.com';

    return '<!doctype html><html><body style="margin:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;">'
        . '<div style="padding:32px 12px;"><div style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;">'
        . '<div style="padding:30px 24px;text-align:center;background:#07110b;color:#ffffff;">' . golf_simulator_theme_get_email_logo_html() . '<div style="font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:#a1e04c;font-weight:700;">' . esc_html($eyebrow) . '</div></div>'
        . '<div style="padding:30px 28px 34px;"><h1 style="margin:0 0 16px;color:#111827;font-size:24px;">' . esc_html($heading) . '</h1>'
        . $body_html
        . $cta_html
        . '<div style="margin:28px 0 0;padding:18px;background:#f3f4f6;border-radius:10px;color:#4b5563;font-size:14px;line-height:1.7;">'
        . '<strong style="color:#111827;">Tee Time Nexus</strong><br>'
        . '<a href="' . esc_url($map_url) . '" target="_blank" rel="noopener" style="color:#1769aa;text-decoration:underline;">2785 Charlotte Hwy, Suites 11 &amp; 12<br>Mooresville, NC 28117</a><br>'
        . '<a href="tel:' . esc_attr($business_phone) . '" style="color:#1769aa;text-decoration:underline;">+1 (980) 503-3288</a><br>'
        . '<a href="mailto:' . esc_attr($business_email) . '" style="color:#1769aa;text-decoration:underline;">' . esc_html($business_email) . '</a>'
        . '</div>'
        . '<p style="margin:28px 0 0;color:#4b5563;font-size:15px;line-height:1.6;"><strong>See you on the tee!</strong><br><strong>Tee Time Nexus</strong></p>'
        . '</div></div></div></body></html>';
}

function golf_simulator_theme_get_email_headers() {
    return array(
        'Content-Type: text/html; charset=UTF-8',
        'From: Tee Time Nexus <sales@teetimenexus.com>',
    );
}

// Replaces the default "WordPress" sender name (password resets, new user notices, etc.) site-wide.
function golf_simulator_theme_mail_from_name($name) {
    return 'Tee Time Nexus';
}
add_filter('wp_mail_from_name', 'golf_simulator_theme_mail_from_name');

function golf_simulator_theme_mail_from($email) {
    return 'sales@teetimenexus.com';
}
add_filter('wp_mail_from', 'golf_simulator_theme_mail_from');

// Catches any remaining plain-text email (password reset, new-account notice, etc.) and
// wraps it in the same branded template so every email we send looks consistent.
function golf_simulator_theme_brand_plain_emails($args) {
    $message = (string) ($args['message'] ?? '');
    $headers = $args['headers'] ?? array();
    $headers_string = is_array($headers) ? implode("\n", $headers) : (string) $headers;

    // Already HTML (our own templates, WooCommerce, etc.) - leave it alone.
    if (stripos($message, '<!doctype html') !== false || stripos($message, '<html') !== false || stripos($headers_string, 'text/html') !== false) {
        return $args;
    }

    $subject = (string) ($args['subject'] ?? 'Tee Time Nexus');
    $body_html = '<div style="color:#4b5563;font-size:15px;line-height:1.7;">' . nl2br(esc_html($message)) . '</div>';

    $args['message'] = golf_simulator_theme_render_email_template('Tee Time Nexus', $subject, $body_html);
    $headers = is_array($headers) ? $headers : array_filter(explode("\n", $headers));
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $args['headers'] = $headers;

    return $args;
}
add_filter('wp_mail', 'golf_simulator_theme_brand_plain_emails', 20);
