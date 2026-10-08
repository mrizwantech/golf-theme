<?php

if (!defined('ABSPATH')) {
    exit;
}

final class TTN_Competitions_Frontend {
    public static function enqueue_assets() {
        if (
            is_singular(TTN_Competitions_Post_Type::POST_TYPE)
            || is_post_type_archive(TTN_Competitions_Post_Type::POST_TYPE)
            || self::page_has_shortcode()
        ) {
            $path = TTN_COMPETITIONS_DIR . 'assets/css/competitions.css';
            wp_enqueue_style(
                'ttn-competitions',
                plugins_url('assets/css/competitions.css', TTN_COMPETITIONS_FILE),
                array(),
                file_exists($path) ? (string) filemtime($path) : TTN_COMPETITIONS_VERSION
            );
        }
    }

    public static function register_shortcodes() {
        add_shortcode('ttn_competitions', array(__CLASS__, 'render_shortcode'));
    }

    public static function template_include($template) {
        if (is_singular(TTN_Competitions_Post_Type::POST_TYPE)) {
            return TTN_COMPETITIONS_DIR . 'templates/single-competition.php';
        }
        if (is_post_type_archive(TTN_Competitions_Post_Type::POST_TYPE)) {
            return TTN_COMPETITIONS_DIR . 'templates/archive-competition.php';
        }
        return $template;
    }

    public static function render_shortcode($attributes) {
        $attributes = shortcode_atts(array(
            'view' => 'list',
            'type' => 'all',
        ), $attributes, 'ttn_competitions');

        $view = sanitize_key($attributes['view']);
        $type = sanitize_key($attributes['type']);
        if (!in_array($view, array('landing', 'list'), true) || !in_array($type, array('all', 'league', 'tournament'), true)) {
            return '';
        }

        ob_start();
        include TTN_COMPETITIONS_DIR . 'templates/competition-list.php';
        return ob_get_clean();
    }

    public static function get_posts($type = 'all', $limit = 12) {
        $today = current_time('Y-m-d');
        $args = array(
            'post_type' => TTN_Competitions_Post_Type::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => max(1, min(50, absint($limit))),
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

        if ($type !== 'all') {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => TTN_Competitions_Post_Type::TAXONOMY,
                    'field' => 'slug',
                    'terms' => $type,
                ),
            );
        }

        return get_posts($args);
    }

    public static function render_card($post) {
        $type_terms = get_the_terms($post, TTN_Competitions_Post_Type::TAXONOMY);
        $type_label = is_array($type_terms) && !empty($type_terms) ? $type_terms[0]->name : __('Competition', 'tee-time-nexus-competitions');
        $start_date = get_post_meta($post->ID, '_ttn_start_date', true);
        $end_date = get_post_meta($post->ID, '_ttn_end_date', true);
        $fee = get_post_meta($post->ID, '_ttn_entry_fee', true);
        $format = get_post_meta($post->ID, '_ttn_format', true);
        $image = get_the_post_thumbnail($post, 'large', array('class' => 'ttn-competition-card-image'));
        ?>
        <article class="ttn-competition-card">
            <?php if ($image) : ?><a class="ttn-competition-image-link" href="<?php echo esc_url(get_permalink($post)); ?>"><?php echo wp_kses_post($image); ?></a><?php endif; ?>
            <div class="ttn-competition-card-content">
                <p class="ttn-competition-type"><?php echo esc_html($type_label); ?></p>
                <h3><a href="<?php echo esc_url(get_permalink($post)); ?>"><?php echo esc_html(get_the_title($post)); ?></a></h3>
                <?php if ($start_date) : ?>
                    <p class="ttn-competition-date"><?php echo esc_html(self::format_date_range($start_date, $end_date)); ?></p>
                <?php endif; ?>
                <?php if ($format) : ?><p class="ttn-competition-format"><?php echo esc_html($format); ?></p><?php endif; ?>
                <p class="ttn-competition-description"><?php echo esc_html(get_the_excerpt($post)); ?></p>
                <p class="ttn-competition-fee"><?php echo $fee === '' ? esc_html__('Entry details coming soon', 'tee-time-nexus-competitions') : esc_html($fee <= 0 ? __('Free entry', 'tee-time-nexus-competitions') : sprintf(__('Entry fee: %s', 'tee-time-nexus-competitions'), function_exists('wc_price') ? wp_strip_all_tags(wc_price((float) $fee)) : '$' . number_format((float) $fee, 2))); ?></p>
                <a class="ttn-competition-link" href="<?php echo esc_url(get_permalink($post)); ?>"><?php esc_html_e('View event details', 'tee-time-nexus-competitions'); ?> <span aria-hidden="true">&rarr;</span></a>
            </div>
        </article>
        <?php
    }

    public static function format_date_range($start_date, $end_date) {
        $start = self::date_timestamp($start_date);
        $end = self::date_timestamp($end_date);
        if (!$start) {
            return __('Date to be announced', 'tee-time-nexus-competitions');
        }
        if (!$end || $end_date === $start_date) {
            return wp_date(get_option('date_format'), $start);
        }
        return wp_date(get_option('date_format'), $start) . ' – ' . wp_date(get_option('date_format'), $end);
    }

    private static function date_timestamp($date) {
        if (!$date) {
            return 0;
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());
        return $parsed && $parsed->format('Y-m-d') === $date ? $parsed->getTimestamp() : 0;
    }

    private static function page_has_shortcode() {
        if (!is_singular()) {
            return false;
        }
        $post = get_post();
        return $post instanceof WP_Post && has_shortcode($post->post_content, 'ttn_competitions');
    }
}
