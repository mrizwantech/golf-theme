<?php

if (!defined('ABSPATH')) {
    exit;
}

function golf_simulator_theme_mobile_technology_content() {
    $page = get_page_by_path('golf-technology');
    $saved_media = $page && 'publish' === $page->post_status
        ? get_post_meta($page->ID, '_golf_technology_feature_gifs', true) : array();
    $panels = array();
    foreach (golf_simulator_theme_golf_technology_sections() as $id => $feature) {
        $attachment_id = golf_simulator_theme_golf_technology_media_id($saved_media, $id);
        $media = $attachment_id ? wp_get_attachment_url($attachment_id) : '';
        $media = esc_url_raw($media ?: '', array('http', 'https'));
        $path = (string) wp_parse_url($media, PHP_URL_PATH);
        $subsections = array();
        foreach ($feature['subsections'] ?? array() as $title => $text) {
            $subsections[] = array('title' => $title, 'text' => $text);
        }
        $video_id = $feature['video_id'] ?? '';
        $video_url = '';
        if (!$media && preg_match('/^[A-Za-z0-9_-]{11}$/', $video_id)) {
            $media = 'https://i.ytimg.com/vi/' . $video_id . '/hqdefault.jpg';
            $video_url = 'https://www.youtube.com/watch?v=' . $video_id;
        }
        $panels[] = array(
            'id' => $id,
            'number' => $feature['number'],
            'title' => $feature['label'],
            'text' => $feature['text'],
            'details' => array_values($feature['details'] ?? array()),
            'subsections' => $subsections,
            'after' => $feature['after'] ?? '',
            'media' => $media,
            'media_type' => preg_match('/\.(mp4|webm|ogg)$/i', $path) ? 'video' : (preg_match('/\.gif$/i', $path) ? 'gif' : 'image'),
            'video_url' => $video_url,
        );
    }
    return rest_ensure_response(array(
        'section' => array(
            'title' => 'Practice Smarter. Play Better.',
            'subtitle' => 'Advanced practice tools, realistic gameplay, and detailed performance data help you understand your game, sharpen every shot, and make every practice session count.',
        ),
        'panels' => $panels,
        'disclaimer' => 'Simulator capabilities are based on GOLFZON TwoVision NX product information. Features and availability may vary depending on Tee Time Nexus facility configuration.',
    ));
}

add_action('rest_api_init', static function () {
    register_rest_route('ttn/v1', '/technology', array(
        'methods' => 'GET',
        'callback' => 'golf_simulator_theme_mobile_technology_content',
        'permission_callback' => '__return_true',
    ));
});
