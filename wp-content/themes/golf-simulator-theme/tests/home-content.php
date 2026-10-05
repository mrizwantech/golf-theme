<?php

if (PHP_SAPI !== 'cli') {
    exit;
}

define('ABSPATH', __DIR__ . '/');
$theme_mods = array();
$rest_routes = array();

function get_theme_mod($key, $default = '') {
    global $theme_mods;
    return array_key_exists($key, $theme_mods) ? $theme_mods[$key] : $default;
}

function home_url($path) {
    return 'https://example.test' . $path;
}

function wp_parse_url($url, $component) {
    return parse_url($url, $component);
}

function esc_url_raw($url, $protocols) {
    return in_array(parse_url($url, PHP_URL_SCHEME), $protocols, true) ? $url : '';
}

function wp_strip_all_tags($value) {
    return strip_tags($value);
}

function rest_ensure_response($value) {
    return $value;
}

function add_action($hook, $callback) {
    if ($hook === 'rest_api_init') {
        $callback();
    }
}

function register_rest_route($namespace, $path, $options) {
    global $rest_routes;
    $rest_routes[$namespace . $path] = $options;
}

function get_header() {}
function get_footer() {}
function is_user_logged_in() { return false; }
function esc_url($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }

function check_home_content($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

require dirname(__DIR__) . '/inc/home-content.php';

$defaults = golf_simulator_theme_get_home_content();
check_home_content(count($defaults['slides']) === 3, 'Expected three hero slides.');
check_home_content(count($defaults['panels']) === 6, 'Expected six technology panels.');
check_home_content(array_column($defaults['panels'], 'title') === array('Auto Tee', 'Dynamic Swing Plate', 'High-Speed Swing Sensors', 'Realistic Bunker Play', 'Precision Putting', 'Network Play'), 'Panel order changed.');
check_home_content($defaults['slides'][0]['heading'] === 'Grand Opening TBD', 'Hero default changed.');
check_home_content(array_column($defaults['slides'][0]['actions'], 'route') === array('/membership', '/book'), 'Native actions do not match the website.');
check_home_content($rest_routes['ttn/v1/home']['methods'] === 'GET', 'Expected a read-only route.');
check_home_content($rest_routes['ttn/v1/home']['permission_callback'] === '__return_true', 'Guest access must be public.');

$theme_mods = array(
    'golf_simulator_slide_1_image' => 'https://example.test/hero.png?width=1200',
    'golf_simulator_slide_1_heading' => 'Custom heading',
    'golf_simulator_slide_1_text' => 'Custom slide description',
    'golf_simulator_feature_1_title' => 'Custom Auto Tee',
    'golf_simulator_feature_1_text' => 'Custom panel description',
    'golf_simulator_feature_1_media' => 'https://example.test/autotee.MP4?v=2',
    'golf_simulator_feature_2_media' => 'https://example.test/motion.webm',
    'golf_simulator_feature_3_media' => 'https://example.test/sensors.GIF?v=3',
    'golf_simulator_feature_4_media' => 'https://example.test/bunker.png',
    'golf_simulator_feature_5_title' => '',
    'golf_simulator_feature_5_text' => '',
);
$content = golf_simulator_theme_mobile_home_content();
check_home_content($content['slides'][0]['image'] === $theme_mods['golf_simulator_slide_1_image'], 'Hero media override lost.');
check_home_content($content['slides'][0]['heading'] === 'Custom heading', 'Hero heading override lost.');
check_home_content($content['panels'][0]['title'] === 'Custom Auto Tee', 'Panel title override lost.');
check_home_content($content['panels'][0]['text'] === 'Custom panel description', 'Panel text override lost.');
check_home_content(array_column($content['panels'], 'media_type') === array('video', 'video', 'gif', 'image', 'image', 'image'), 'Media classification must handle uppercase extensions and query strings.');
check_home_content($content['panels'][4]['title'] === $defaults['panels'][4]['title'], 'Empty panel title must preserve website fallback.');
check_home_content($content['panels'][4]['text'] === $defaults['panels'][4]['text'], 'Empty panel text must preserve website fallback.');
check_home_content($content['panels'][5]['media'] === '', 'Unset optional media must remain empty.');

ob_start();
require dirname(__DIR__) . '/front-page.php';
$html = ob_get_clean();
check_home_content(substr_count($html, '<article class="hero-slide') === 3, 'Website must render three slides.');
check_home_content(substr_count($html, '<div class="card has-media"') === 6, 'Website must render six panels.');
check_home_content(strpos($html, 'Custom heading') !== false && strpos($html, 'Custom Auto Tee') !== false, 'Website and API must use the same settings.');
check_home_content(strpos($html, esc_url($content['panels'][0]['media'])) !== false, 'Website and API must share video URL.');
check_home_content(substr_count($html, 'Become a Member</a>') === 3 && substr_count($html, 'Book a Bay</a>') === 3, 'Website hero actions changed.');

$theme_mods['golf_simulator_slide_1_heading'] = '<b>Heading &amp; More</b>';
$theme_mods['golf_simulator_feature_1_media'] = 'javascript:alert(1)';
$sanitized = golf_simulator_theme_mobile_home_content();
check_home_content($sanitized['slides'][0]['heading'] === 'Heading & More', 'Native text must strip HTML and decode entities.');
check_home_content($sanitized['panels'][0]['media'] === '', 'Unsupported media protocols must not be exposed.');

echo "Home content contract and guest website rendering passed.\n";
