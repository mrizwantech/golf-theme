<?php

if (!defined('ABSPATH')) {
    exit;
}

function golf_simulator_theme_get_home_content() {
    $slide_defaults = array(
        array(
            'image' => 'https://images.unsplash.com/photo-1535131749006-b7f58c99034b?auto=format&fit=crop&w=1600&q=80',
            'kicker' => 'Coming Soon',
            'heading' => 'Grand Opening TBD',
            'text' => 'We are preparing something special for golfers in the area. Check back soon for updates, launch dates, and opening details.',
            'button_1' => 'Stay Tuned',
            'button_1_url' => home_url('/welcome'),
        ),
        array(
            'image' => 'https://images.unsplash.com/photo-1593111774278-0b6b02b7961c?auto=format&fit=crop&w=1600&q=80',
            'kicker' => 'Early Bird Memberships',
            'heading' => 'Lock In Your Early Bird Rate',
            'text' => 'Be among the first to join Tee Time Nexus and become a Founding Member. Unlock exclusive Early Bird membership benefits before we open. Become a Founding Member — Early Bird memberships available.',
            'button_1' => 'Follow Updates',
            'button_1_url' => home_url('/'),
        ),
        array(
            'image' => 'https://images.unsplash.com/photo-1517466787929-bc90951d0974?auto=format&fit=crop&w=1600&q=80',
            'kicker' => 'NEXT-LEVEL INDOOR GOLF',
            'heading' => 'Technology That Makes Every Shot Feel Real.',
            'text' => 'Experience advanced golf simulation with realistic course conditions designed for a more immersive indoor golf experience.',
            'button_1' => 'Watch for Launch',
            'button_1_url' => home_url('/'),
        ),
    );
    $slides = array();
    foreach ($slide_defaults as $index => $defaults) {
        $slide = array('id' => 'slide-' . ($index + 1));
        foreach ($defaults as $key => $default) {
            $slide[$key] = get_theme_mod('golf_simulator_slide_' . ($index + 1) . '_' . $key, $default);
        }
        // These are the actions actually displayed on the website's front page.
        $slide['actions'] = array(
            array('label' => 'Become a Member', 'url' => home_url('/membership/'), 'route' => '/membership'),
            array('label' => 'Book a Bay', 'url' => home_url('/book-a-bay/'), 'route' => '/book'),
        );
        $slides[] = $slide;
    }

    $feature_defaults = array(
        array('Auto Tee', 'The ball automatically tees up after every shot. Spend less time resetting and more time focused on your game, with a smooth and consistent tee-up experience from shot to shot.'),
        array('Dynamic Swing Plate', 'Experience a more realistic golf swing with our dynamic swing plate. The platform moves with the terrain and shot conditions, simulating uneven lies such as uphill, downhill, and sidehill shots. Adjust your stance naturally and experience a more challenging, true-to-life round of golf.'),
        array('High-Speed Swing Sensors', 'Advanced high-speed sensors capture every shot with precision, tracking key ball and club data in real time. Get fast, accurate feedback on your swing, ball flight, speed, launch, and shot performance to help you understand and improve your game.'),
        array('Realistic Bunker Play', 'Take your short game to the next level with realistic bunker conditions that recreate the feel of playing from the sand. Experience authentic shot response, changing ball flight, and the challenge of getting up and down.'),
        array('Precision Putting', 'Dial in your putting with realistic green surfaces designed to replicate the feel of the course. Read the break, control your speed, and build confidence on every putt with accurate roll and natural ball response.'),
        array('Network Play', 'Play together, compete, and enjoy a connected golf experience with friends and other players. Join the same round, track scores in real time, and experience the excitement of head-to-head competition across connected simulator bays.'),
    );
    $panels = array();
    foreach ($feature_defaults as $index => $defaults) {
        $prefix = 'golf_simulator_feature_' . ($index + 1) . '_';
        $media_url = get_theme_mod($prefix . 'media', '');
        $media_path = (string) wp_parse_url($media_url, PHP_URL_PATH);
        $panels[] = array(
            'id' => 'feature-' . ($index + 1),
            'title' => get_theme_mod($prefix . 'title', '') ?: $defaults[0],
            'text' => get_theme_mod($prefix . 'text', '') ?: $defaults[1],
            'media' => $media_url,
            'media_type' => preg_match('/\.(mp4|webm|ogg)$/i', $media_path) ? 'video' : (preg_match('/\.gif$/i', $media_path) ? 'gif' : 'image'),
        );
    }

    return array(
        'slides' => $slides,
        'section' => array(
            'title' => 'WHERE GOLF MEETS TECHNOLOGY',
            'subtitle' => 'Experience the ultimate fusion of cutting-edge golf technology and immersive gameplay.',
        ),
        'panels' => $panels,
    );
}

function golf_simulator_theme_mobile_home_content() {
    $content = golf_simulator_theme_get_home_content();
    foreach ($content['slides'] as &$slide) {
        foreach (array('image', 'button_1_url') as $key) {
            $slide[$key] = esc_url_raw($slide[$key], array('http', 'https'));
        }
        foreach (array('kicker', 'heading', 'text', 'button_1') as $key) {
            $slide[$key] = html_entity_decode(wp_strip_all_tags($slide[$key]), ENT_QUOTES, 'UTF-8');
        }
    }
    unset($slide);
    foreach ($content['panels'] as &$panel) {
        $panel['media'] = esc_url_raw($panel['media'], array('http', 'https'));
        foreach (array('title', 'text') as $key) {
            $panel[$key] = html_entity_decode(wp_strip_all_tags($panel[$key]), ENT_QUOTES, 'UTF-8');
        }
    }
    unset($panel);
    return rest_ensure_response($content);
}

add_action('rest_api_init', static function () {
    register_rest_route('ttn/v1', '/home', array(
        'methods' => 'GET',
        'callback' => 'golf_simulator_theme_mobile_home_content',
        'permission_callback' => '__return_true',
    ));
});
