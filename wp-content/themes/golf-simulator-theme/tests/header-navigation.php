<?php

if (PHP_SAPI !== 'cli') {
    exit;
}

$logged_in = false;
function language_attributes() { echo 'lang="en"'; }
function bloginfo($key) { echo 'UTF-8'; }
function wp_head() {}
function body_class() {}
function wp_body_open() {}
function get_theme_mod($key) { return false; }
function has_custom_logo() { return false; }
function home_url($path = '') { return 'https://example.test' . $path; }
function esc_url($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_url($value); }
function esc_html__($value, $domain) { return esc_url($value); }
function is_user_logged_in() { return $GLOBALS['logged_in']; }
function wp_logout_url($url) { return 'https://example.test/logout'; }
function golf_simulator_theme_get_login_url() { return 'https://example.test/login/'; }
function golf_simulator_theme_menu() {
    echo '<nav class="site-nav"><ul><li><a href="#home">Home</a></li><li><a href="#about">About</a><ul class="sub-menu"><li><a href="#technology">Golf Technology</a></li></ul></li><li><a href="#membership">Membership</a></li></ul></nav>';
}
function render_header() {
    ob_start();
    require dirname(__DIR__) . '/header.php';
    return ob_get_clean();
}
function check_header($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$guest = render_header();
check_header(strpos($guest, 'aria-controls="header-navigation" aria-expanded="false" hidden') !== false, 'Toggle must start collapsed and hidden until JavaScript initializes.');
check_header(strpos($guest, 'id="header-navigation"') !== false, 'Toggle must control the navigation panel.');
check_header(strpos($guest, 'https://example.test/login/') !== false, 'Keep guest login inside the navigation.');
$logged_in = true;
$member = render_header();
check_header(strpos($member, 'My Account') !== false && strpos($member, 'Logout') !== false, 'Keep member account and logout links.');
check_header(strpos($member, 'https://example.test/login/') === false, 'Do not display guest login to members.');

if (in_array('--preview', $argv, true)) {
    $base = dirname(__DIR__);
    $css = file_get_contents($base . '/style.css');
    $js = file_get_contents($base . '/assets/js/navigation.js');
    echo str_replace('</head>', '<style>' . $css . '</style>', $guest);
    echo '<main class="container"><h1 id="home">Navigation preview</h1><button id="outside">Outside header</button></main><script>' . $js . '</script></body></html>';
} else {
    echo "Header navigation passed: accessible toggle, navigation panel, guest and member account links.\n";
}
