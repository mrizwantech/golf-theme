<?php
if (PHP_SAPI !== 'cli') exit;
define('ABSPATH', __DIR__ . '/');
$routes = array();
function rest_ensure_response($value) { return $value; }
function register_rest_route($namespace, $path, $args) { global $routes; $routes[$namespace . $path] = $args; }
function add_action($name, $callback) { $callback(); }
function get_header() {}
function get_footer() {}
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_url($value) { return $value; }
function home_url($path) { return 'https://example.test' . $path; }
function check_mobile_hours($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
require dirname(__DIR__) . '/inc/hours-content.php';
$content = golf_simulator_theme_hours_content();
check_mobile_hours(golf_simulator_theme_mobile_hours_content() === $content, 'API must return the shared website content.');
check_mobile_hours(array_column($content['sections'], 'id') === array('member', 'public'), 'Both schedules must be exported in website order.');
check_mobile_hours($content['sections'][0]['time'] === '24/7', 'Member access must stay 24/7.');
check_mobile_hours($content['sections'][1]['time'] === '10:00 AM to 10:00 PM', 'Public hours must be preserved.');
check_mobile_hours($content['sections'][0]['action']['route'] === '/membership', 'Membership action must be native.');
check_mobile_hours($content['sections'][1]['action']['route'] === '/book', 'Booking action must be native.');
$route = $routes['ttn/v1/hours'];
check_mobile_hours($route['methods'] === 'GET' && $route['permission_callback'] === '__return_true', 'Hours endpoint must be public and read-only.');
check_mobile_hours(call_user_func($route['callback']) === $content, 'Registered callback must return shared content.');
ob_start();
require dirname(__DIR__) . '/page-hours.php';
$html = ob_get_clean();
check_mobile_hours(strpos($html, esc_html($content['title'])) !== false, 'Website must render the shared heading.');
foreach ($content['sections'] as $section) {
    foreach (array('title', 'note') as $key) {
        check_mobile_hours(strpos($html, esc_html($section[$key])) !== false, 'Website must render the shared schedule copy.');
    }
    check_mobile_hours(strpos($html, esc_html($section['action']['label'])) !== false, 'Website must render the shared action label.');
}
check_mobile_hours(strpos($html, '10:00 AM <span>to</span> 10:00 PM') !== false, 'Public time markup must be preserved.');
check_mobile_hours(strpos($html, 'https://example.test/membership/') !== false && strpos($html, 'https://example.test/book-a-bay/') !== false, 'Website actions must keep their original URLs.');
echo "Mobile hours passed: shared website/API copy, both schedules, native routes, public endpoint, and website rendering.\n";
