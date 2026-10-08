<?php

if (!defined('ABSPATH')) {
    exit;
}

final class TTN_Competitions_API {
    public static function register_routes() {
        register_rest_route('ttn/v1', '/competitions', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'get_competitions'),
            'permission_callback' => '__return_true',
            'args' => array(
                'type' => array(
                    'default' => 'all',
                    'sanitize_callback' => 'sanitize_key',
                    'validate_callback' => array(__CLASS__, 'validate_type'),
                ),
                'limit' => array(
                    'default' => 30,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => array(__CLASS__, 'validate_limit'),
                ),
            ),
        ));
    }

    public static function validate_type($value) {
        return in_array($value, array('all', 'league', 'tournament'), true);
    }

    public static function validate_limit($value) {
        return is_numeric($value) && (int) $value >= 1 && (int) $value <= 50;
    }

    public static function get_competitions(WP_REST_Request $request) {
        $today = current_time('Y-m-d');
        $query_args = array(
            'post_type' => TTN_Competitions_Post_Type::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => (int) $request->get_param('limit'),
            'meta_key' => '_ttn_start_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_query' => array(
                array(
                    'key' => '_ttn_start_date',
                    'value' => $today,
                    'compare' => '>=',
                    'type' => 'DATE',
                ),
            ),
            'no_found_rows' => true,
        );

        $type = $request->get_param('type');
        if ($type !== 'all') {
            $query_args['tax_query'] = array(
                array(
                    'taxonomy' => TTN_Competitions_Post_Type::TAXONOMY,
                    'field' => 'slug',
                    'terms' => $type,
                ),
            );
        }

        $posts = get_posts($query_args);
        $items = array();
        foreach ($posts as $post) {
            $item = self::serialize_competition($post);
            if ($item) {
                $items[] = $item;
            }
        }

        return rest_ensure_response(array(
            'items' => $items,
            'count' => count($items),
        ));
    }

    public static function serialize_competition($post) {
        $terms = get_the_terms($post, TTN_Competitions_Post_Type::TAXONOMY);
        $type = is_array($terms) && !empty($terms) ? $terms[0]->slug : '';
        if (!in_array($type, array('league', 'tournament'), true)) {
            return null;
        }
        $fee = get_post_meta($post->ID, '_ttn_entry_fee', true);
        $image_url = get_the_post_thumbnail_url($post, 'large');

        return array(
            'id' => (int) $post->ID,
            'title' => get_the_title($post),
            'type' => $type,
            'description' => wp_strip_all_tags(get_the_excerpt($post), true),
            'start_date' => get_post_meta($post->ID, '_ttn_start_date', true),
            'end_date' => get_post_meta($post->ID, '_ttn_end_date', true),
            'registration_start' => get_post_meta($post->ID, '_ttn_registration_start', true),
            'registration_end' => get_post_meta($post->ID, '_ttn_registration_end', true),
            'entry_fee' => $fee === '' ? null : (float) $fee,
            'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
            'capacity' => (int) get_post_meta($post->ID, '_ttn_capacity', true),
            'format' => get_post_meta($post->ID, '_ttn_format', true),
            'course' => get_post_meta($post->ID, '_ttn_course', true),
            'image_url' => $image_url ? esc_url_raw($image_url) : '',
            'url' => esc_url_raw(get_permalink($post)),
        );
    }
}
