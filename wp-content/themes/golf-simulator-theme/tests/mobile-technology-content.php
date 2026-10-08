<?php
if (PHP_SAPI !== 'cli') exit;
define('ABSPATH', __DIR__ . '/');
$saved = array('technology-multi-surface-play' => 1, 'technology-auto-tee' => 2, 'technology-shot-analysis' => 3);
$routes = array();
function absint($value) { return abs((int) $value); }
function get_page_by_path($path) { return (object) array('ID' => 20, 'post_status' => 'publish'); }
function get_post_meta($id, $key, $single) { global $saved; return $saved; }
function wp_get_attachment_url($id) {
    return array(1 => 'https://example.test/plate.gif', 2 => 'https://example.test/tee.mp4?version=2', 3 => false)[$id] ?? false;
}
function esc_url_raw($value, $protocols) { return $value; }
function wp_parse_url($value, $component) { return parse_url($value, $component); }
function rest_ensure_response($value) { return $value; }
function register_rest_route($namespace, $path, $args) { global $routes; $routes[$namespace . $path] = $args; }
function add_action($name, $callback) { $callback(); }
function check_mobile_technology($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
require dirname(__DIR__) . '/inc/golf-technology-content.php';
require dirname(__DIR__) . '/inc/mobile-technology-api.php';
$content = golf_simulator_theme_mobile_technology_content();
$sections = golf_simulator_theme_golf_technology_sections();
check_mobile_technology(count($content['panels']) === 13, 'All sections must be exported.');
check_mobile_technology(array_column($content['panels'], 'id') === array_keys($sections), 'Website order must be preserved.');
check_mobile_technology($content['panels'][0]['media_type'] === 'gif', 'Legacy GIF media must be preserved.');
check_mobile_technology($content['panels'][1]['media_type'] === 'video', 'Uploaded video with query string must be classified correctly.');
check_mobile_technology($content['panels'][1]['video_url'] === '', 'Uploaded media must take priority over YouTube.');
check_mobile_technology($content['panels'][3]['video_url'] === 'https://www.youtube.com/watch?v=f-atsI5iRdY', 'Missing attachment must use a YouTube preview.');
check_mobile_technology(count($content['panels'][5]['subsections']) === 3, 'All practice subsections must be included.');
foreach ($content['panels'] as $panel) {
    $source = $sections[$panel['id']];
    check_mobile_technology($panel['text'] === $source['text'] && $panel['title'] === $source['label'], 'Native and website copy must match.');
    check_mobile_technology(!isset($panel['actions']), 'Website CTA buttons must not be exported.');
}
check_mobile_technology($routes['ttn/v1/technology']['permission_callback'] === '__return_true', 'Technology must be public.');
echo "Mobile technology passed: 13 sections, shared copy, media precedence, video previews, public route, and no CTA buttons.\n";
