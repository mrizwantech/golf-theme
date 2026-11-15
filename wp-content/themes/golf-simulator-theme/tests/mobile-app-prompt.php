<?php

if (PHP_SAPI !== 'cli') {
    exit;
}

$hooks = array();
$scripts = array();
function add_action($hook, $callback) { $GLOBALS['hooks'][$hook][] = $callback; }
function get_template_directory() { return dirname(__DIR__); }
function get_template_directory_uri() { return 'https://example.test/theme'; }
function wp_enqueue_script(...$args) { $GLOBALS['scripts'][] = $args; }
function esc_url($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_attr__($value, $domain) { return esc_url($value); }
function esc_html_e($value, $domain) { echo esc_url($value); }
function check_prompt($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

require dirname(__DIR__) . '/inc/mobile-app-prompt.php';
check_prompt(in_array('golf_simulator_theme_render_mobile_app_prompt', $hooks['wp_footer'], true), 'Render the popup across the site.');
golf_simulator_theme_enqueue_mobile_app_prompt();
check_prompt($scripts[0][1] === 'https://example.test/theme/assets/js/mobile-app-prompt.js', 'Enqueue the popup script.');
check_prompt($scripts[0][3] === filemtime(dirname(__DIR__) . '/assets/js/mobile-app-prompt.js') && $scripts[0][4] === true, 'Version the script and load it in the footer.');
ob_start();
golf_simulator_theme_render_mobile_app_prompt();
$html = ob_get_clean();
check_prompt(strpos($html, '<dialog ') !== false && strpos($html, ' open') === false, 'Use an initially closed native dialog.');
check_prompt(strpos($html, 'aria-labelledby="ttn-mobile-app-title" aria-describedby="ttn-mobile-app-description"') !== false, 'Provide accessible labels.');
check_prompt(strpos(html_entity_decode($html), 'https://play.google.com/store/apps/details?id=com.teetimenexus.app&hl=en_US') !== false, 'Use the exact Android store URL.');
check_prompt(strpos($html, 'apps.apple.com') === false, 'Do not advertise an unavailable iOS listing.');
check_prompt(substr_count($html, 'data-app-dismiss') === 2, 'Include close and continue-on-website buttons.');

if (in_array('--preview', $argv, true)) {
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>';
    echo file_get_contents(dirname(__DIR__) . '/style.css');
    echo '</style></head><body><main><h1>Tee Time Nexus</h1><button id="outside">Website content</button></main>';
    echo $html . '<script>' . file_get_contents(dirname(__DIR__) . '/assets/js/mobile-app-prompt.js') . '</script></body></html>';
} else {
    echo "Mobile app prompt PHP tests passed.\n";
}
