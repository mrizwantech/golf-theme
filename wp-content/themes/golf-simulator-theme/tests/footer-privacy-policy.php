<?php

if (PHP_SAPI !== 'cli') {
    exit;
}

define('ABSPATH', __DIR__ . '/');

$options = array();
$post_meta = array();
$posts_db = array();

function home_url($path = '') {
    return 'https://example.test' . $path;
}

function esc_url($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function esc_html($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function esc_attr($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function wp_footer() {}

function get_privacy_policy_url() {
    global $options;
    return $options['wp_page_for_privacy_policy_url'] ?? '';
}

function get_option($key, $default = false) {
    global $options;
    return array_key_exists($key, $options) ? $options[$key] : $default;
}

function update_option($key, $value) {
    global $options;
    $options[$key] = $value;
    return true;
}

function get_page_by_path($slug) {
    global $posts_db;
    foreach ($posts_db as $post) {
        if ($post->post_name === $slug) {
            return $post;
        }
    }
    return null;
}

function get_post($post_id = null) {
    global $posts_db, $post;
    return $post_id !== null ? ($posts_db[$post_id] ?? null) : $post;
}

function wp_insert_post($args) {
    global $posts_db;
    $id = count($posts_db) + 1;
    $post = (object) array(
        'ID' => $id,
        'post_title' => $args['post_title'] ?? '',
        'post_name' => $args['post_name'] ?? '',
        'post_status' => $args['post_status'] ?? 'publish',
        'post_content' => $args['post_content'] ?? '',
    );
    $posts_db[$id] = $post;
    return $id;
}

function is_wp_error($thing) {
    return false;
}

function get_post_meta($post_id, $key, $single = false) {
    global $post_meta;
    return $post_meta[$post_id][$key] ?? '';
}

function update_post_meta($post_id, $key, $value) {
    global $post_meta;
    $post_meta[$post_id][$key] = $value;
    return true;
}

function have_posts() {
    return false;
}

function the_post() {}
function the_title() { echo 'Privacy Policy'; }
function the_content() {}
function get_the_title() { return 'Privacy Policy'; }
function get_the_content() { return ''; }
function get_the_modified_date($format = '') { return 'October 12, 2026'; }
function get_header() {}
function get_footer() {}
function get_bloginfo($show = '') { return 'Tee Time Nexus'; }
function is_front_page() { return false; }
function is_singular() { return true; }
function is_archive() { return false; }
function is_search() { return false; }
function is_404() { return false; }
function add_theme_support() {}
function register_nav_menus() {}
function add_action($tag, $callback, $priority = 10) {}
function add_filter($tag, $callback, $priority = 10) {}
function add_shortcode($tag, $callback) {}
function register_activation_hook($file, $callback) {}
function add_meta_box() {}
function get_template_directory() { return dirname(__DIR__); }
function get_theme_mod($key = '', $default = '') { return $default; }
function wp_parse_url($url, $component) { return parse_url($url, $component); }

function check_test($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

// 1. Test footer rendering with default fallback url
ob_start();
require dirname(__DIR__) . '/footer.php';
$footer_html = ob_get_clean();

check_test(strpos($footer_html, 'Privacy Policy') !== false, 'Footer must contain the text "Privacy Policy".');
check_test(strpos($footer_html, 'class="footer-privacy-link"') !== false, 'Footer must use class "footer-privacy-link".');
check_test(strpos($footer_html, 'href="https://example.test/privacy-policy/"') !== false, 'Footer must link to /privacy-policy/.');
check_test(strpos($footer_html, 'class="footer-legal"') !== false, 'Footer must have .footer-legal container.');
check_test(strpos($footer_html, 'Tee Time Nexus. All rights reserved.') !== false, 'Footer must preserve copyright notice.');

// 2. Test footer rendering when WordPress get_privacy_policy_url() returns custom url
$options['wp_page_for_privacy_policy_url'] = 'https://example.test/custom-privacy/';
ob_start();
require dirname(__DIR__) . '/footer.php';
$footer_custom = ob_get_clean();
check_test(strpos($footer_custom, 'href="https://example.test/custom-privacy/"') !== false, 'Footer must respect get_privacy_policy_url() when configured.');

// 3. Test page-privacy-policy.php rendering
ob_start();
require dirname(__DIR__) . '/page-privacy-policy.php';
$page_html = ob_get_clean();

check_test(strpos($page_html, 'class="privacy-policy-page container"') !== false, 'Page must render .privacy-policy-page container.');
check_test(strpos($page_html, '<h1>Privacy Policy</h1>') !== false, 'Page must display H1 Privacy Policy.');
check_test(strpos($page_html, 'Far Nexus LLC') !== false, 'Page must mention legal entity Far Nexus LLC.');
check_test(strpos($page_html, 'Mooresville, NC') !== false, 'Page must mention Mooresville, NC facility location.');
check_test(strpos($page_html, 'Bluetooth and nearby devices') !== false, 'Page must cover Bluetooth and app permissions.');
check_test(strpos($page_html, 'Kisi') !== false, 'Page must detail Kisi smart lock access integration.');
check_test(strpos($page_html, 'sales@teetimenexus.com') !== false, 'Page must list sales contact email.');
check_test(strpos($page_html, '+1 (980) 503-3288') !== false, 'Page must list contact phone number.');

// 4. Test SEO functions in functions.php
require dirname(__DIR__) . '/functions.php';

$privacy_post = (object) array(
    'ID' => 10,
    'post_name' => 'privacy-policy',
    'post_title' => 'Privacy Policy',
    'post_content' => '',
);
$GLOBALS['post'] = $privacy_post;
$seo_desc = golf_simulator_theme_get_seo_description();
check_test(strpos($seo_desc, 'Tee Time Nexus Privacy Policy') !== false, 'SEO description must describe privacy policy.');

$seo_title = golf_simulator_theme_get_seo_title();
check_test($seo_title === 'Privacy Policy | Tee Time Nexus', 'SEO title must match "Privacy Policy | Tee Time Nexus", got: ' . $seo_title);

// 5. Test golf_simulator_theme_ensure_privacy_policy_page creates page and sets template/option
golf_simulator_theme_ensure_privacy_policy_page();
$ensured_page = get_page_by_path('privacy-policy');
check_test($ensured_page !== null, 'ensure_privacy_policy_page must create privacy-policy page.');
check_test($ensured_page->post_title === 'Privacy Policy', 'ensured page title must be "Privacy Policy".');
check_test(get_post_meta($ensured_page->ID, '_wp_page_template', true) === 'page-privacy-policy.php', 'template must be page-privacy-policy.php');
check_test(get_option('wp_page_for_privacy_policy') === $ensured_page->ID, 'wp_page_for_privacy_policy option must be set');

echo "Footer and Privacy Policy tests passed successfully.\n";
