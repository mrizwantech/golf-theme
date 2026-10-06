<?php

if (PHP_SAPI !== 'cli') {
    exit;
}

define('ABSPATH', __DIR__ . '/');
function absint($value) { return abs((int) $value); }
function get_header() {}
function get_footer() {}
function home_url($path) { return 'https://example.test' . $path; }
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }

function check_technology($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

require dirname(__DIR__) . '/inc/golf-technology-content.php';

$sections = golf_simulator_theme_golf_technology_sections();
check_technology(count($sections) === 13, 'Expected exactly 13 sections.');
check_technology(array_column($sections, 'number') === array_map(static function ($number) {
    return sprintf('%02d', $number);
}, range(1, 13)), 'Section numbering must be sequential.');
check_technology(count(array_filter($sections, static function ($section) {
    return !empty($section['featured']);
})) === 7, 'Expected seven prominent features.');

$all_ids = array_keys($sections);
foreach ($sections as $id => $section) {
    foreach ($section['aliases'] ?? array() as $alias) {
        $all_ids[] = $alias;
        check_technology(golf_simulator_theme_golf_technology_media_id(array($alias => 42), $id) === 42, 'Merged feature media must remain available: ' . $alias);
        check_technology(golf_simulator_theme_golf_technology_media_id(array($alias => 42, $id => 99), $id) === 99, 'Canonical media must override legacy media.');
    }
}
check_technology(count($all_ids) === count(array_unique($all_ids)), 'Canonical and legacy anchor IDs must not collide.');
check_technology(golf_simulator_theme_golf_technology_media_id(null, 'technology-auto-tee') === 0, 'Missing media must remain optional.');
check_technology(golf_simulator_theme_golf_technology_media_id(array(), 'unknown') === 0, 'Unknown features must not resolve media.');
check_technology(count($sections['technology-driving-range']['subsections']) === 3, 'Practice modes must stay grouped.');
check_technology(count($sections['technology-touchscreen']['subsections']) === 3, 'Player controls must stay grouped.');
check_technology(count($sections['technology-shot-analysis']['details']) === 15, 'Shot measurement list is incomplete.');
check_technology(count($sections['technology-arcade-plus']['details']) === 7, 'Arcade game list is incomplete.');
check_technology(count(array_filter($sections, static function ($section) {
    return !empty($section['video_id']) && !empty($section['video_title']);
})) === 13, 'Every section must have a titled video.');

function golf_simulator_theme_render_golf_technology_feature_gif($feature_id) {
    return '<figure data-feature="' . esc_attr($feature_id) . '"></figure>';
}

ob_start();
require dirname(__DIR__) . '/page-golf-technology.php';
$html = ob_get_clean();
check_technology(substr_count($html, '<article class="about-feature') === 13, 'Rendered page must have 13 feature sections.');
preg_match_all('/<a href="#([^"]+)">/', $html, $tabs);
check_technology($tabs[1] === array_keys($sections), 'Tabs must match section order exactly.');
foreach ($all_ids as $id) {
    check_technology(substr_count($html, 'id="' . $id . '"') === 1, 'Each existing feature anchor must resolve once: ' . $id);
}
check_technology(strpos($html, '<h1>Practice Smarter. Play Better.</h1>') !== false, 'Hero heading must use the approved copy.');
check_technology(strpos($html, 'Advanced practice tools, realistic gameplay, and detailed performance data help you understand your game, sharpen every shot, and make every practice session count.') !== false, 'Hero supporting copy must use the approved text.');
check_technology(strpos($html, '>Zero-Latency Gameplay<') !== false && strpos($html, 'designed to minimize the delay') !== false, 'Gameplay copy must retain the qualified latency wording.');
check_technology(substr_count($html, 'class="golf-tech-video-frame"') === 13, 'Every section must render an embedded video.');
foreach ($sections as $section) {
    check_technology(strpos($html, 'https://www.youtube-nocookie.com/embed/' . $section['video_id']) !== false, 'Section video embed is missing: ' . $section['video_id']);
}
check_technology(substr_count($html, '<iframe ') === 14, 'Hero and feature videos must all be embedded.');
check_technology(strpos($html, 'youtube.com/watch') === false, 'The page must not send visitors to YouTube.');
check_technology(strpos($html, 'depending on Tee Time Nexus facility configuration') !== false, 'Facility disclaimer is missing.');
check_technology(substr_count($html, 'Book a Bay') === 2, 'Existing booking calls to action must remain.');

echo "Technology content passed: 13 sections, seven highlights, video links, media fallback, legacy anchors, and rendering.\n";
