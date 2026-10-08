<?php

if (!defined('ABSPATH')) {
    exit;
}

final class TTN_Competitions_Post_Type {
    const POST_TYPE = 'ttn_competition';
    const TAXONOMY = 'ttn_competition_type';

    public static function register() {
        register_post_type(self::POST_TYPE, array(
            'labels' => array(
                'name' => __('Competitions', 'tee-time-nexus-competitions'),
                'singular_name' => __('Competition', 'tee-time-nexus-competitions'),
                'add_new_item' => __('Add New Competition', 'tee-time-nexus-competitions'),
                'edit_item' => __('Edit Competition', 'tee-time-nexus-competitions'),
                'new_item' => __('New Competition', 'tee-time-nexus-competitions'),
                'view_item' => __('View Competition', 'tee-time-nexus-competitions'),
                'search_items' => __('Search Competitions', 'tee-time-nexus-competitions'),
                'not_found' => __('No competitions found.', 'tee-time-nexus-competitions'),
            ),
            'public' => true,
            'show_in_rest' => true,
            'has_archive' => 'competitions',
            'rewrite' => array('slug' => 'competition', 'with_front' => false),
            'menu_icon' => 'dashicons-awards',
            'show_in_menu' => 'ttn-competitions',
            'supports' => array('title', 'editor', 'excerpt', 'thumbnail'),
            'taxonomies' => array(self::TAXONOMY),
            'capability_type' => array('ttn_competition', 'ttn_competitions'),
            'map_meta_cap' => true,
        ));

        register_taxonomy(self::TAXONOMY, array(self::POST_TYPE), array(
            'labels' => array(
                'name' => __('Competition Types', 'tee-time-nexus-competitions'),
                'singular_name' => __('Competition Type', 'tee-time-nexus-competitions'),
                'add_new_item' => __('Add Competition Type', 'tee-time-nexus-competitions'),
            ),
            'public' => true,
            'hierarchical' => false,
            'show_ui' => false,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'meta_box_cb' => false,
            'rewrite' => array('slug' => 'competition-type', 'with_front' => false),
            'capabilities' => array(
                'manage_terms' => 'manage_ttn_competitions',
                'edit_terms' => 'manage_ttn_competitions',
                'delete_terms' => 'manage_ttn_competitions',
                'assign_terms' => 'edit_ttn_competitions',
            ),
        ));

        if (!term_exists('league', self::TAXONOMY)) {
            wp_insert_term(__('League', 'tee-time-nexus-competitions'), self::TAXONOMY, array('slug' => 'league'));
        }
        if (!term_exists('tournament', self::TAXONOMY)) {
            wp_insert_term(__('Tournament', 'tee-time-nexus-competitions'), self::TAXONOMY, array('slug' => 'tournament'));
        }

        add_action('add_meta_boxes_' . self::POST_TYPE, array(__CLASS__, 'add_meta_boxes'));
        add_action('save_post_' . self::POST_TYPE, array(__CLASS__, 'save_meta'), 10, 2);
    }

    public static function add_meta_boxes() {
        add_action('admin_notices', array(__CLASS__, 'render_save_notice'));
        add_meta_box(
            'ttn_competition_details',
            __('Competition Details', 'tee-time-nexus-competitions'),
            array(__CLASS__, 'render_details_meta_box'),
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public static function render_details_meta_box($post) {
        wp_nonce_field('ttn_save_competition_details', 'ttn_competition_details_nonce');

        $terms = get_the_terms($post->ID, self::TAXONOMY);
        $selected_type = is_array($terms) && !empty($terms) ? $terms[0]->slug : '';
        echo '<p><label for="ttn_competition_type"><strong>' . esc_html__('Competition type', 'tee-time-nexus-competitions') . '</strong></label><br>';
        echo '<select required id="ttn_competition_type" name="ttn_competition_type">';
        echo '<option value="">' . esc_html__('Select a type', 'tee-time-nexus-competitions') . '</option>';
        foreach (array('league' => __('League', 'tee-time-nexus-competitions'), 'tournament' => __('Tournament', 'tee-time-nexus-competitions')) as $slug => $label) {
            echo '<option value="' . esc_attr($slug) . '"' . selected($selected_type, $slug, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></p>';

        $fields = array(
            'start_date' => array(__('Start date', 'tee-time-nexus-competitions'), 'date'),
            'end_date' => array(__('End date', 'tee-time-nexus-competitions'), 'date'),
            'registration_start' => array(__('Registration opens', 'tee-time-nexus-competitions'), 'date'),
            'registration_end' => array(__('Registration closes', 'tee-time-nexus-competitions'), 'date'),
            'entry_fee' => array(__('Entry fee (leave blank if not announced; enter 0 for free)', 'tee-time-nexus-competitions'), 'number'),
            'capacity' => array(__('Maximum participants (0 for no stated limit)', 'tee-time-nexus-competitions'), 'number'),
            'format' => array(__('Format', 'tee-time-nexus-competitions'), 'text'),
            'course' => array(__('Golf course', 'tee-time-nexus-competitions'), 'text'),
        );

        echo '<div class="ttn-competition-fields">';
        foreach ($fields as $key => $field) {
            $value = get_post_meta($post->ID, '_ttn_' . $key, true);
            $input_attributes = $field[1] === 'number' && $key === 'entry_fee'
                ? ' min="0" step="0.01"'
                : ($field[1] === 'number' ? ' min="0" step="1"' : '');

            echo '<p><label for="ttn_' . esc_attr($key) . '"><strong>' . esc_html($field[0]) . '</strong></label><br>';
            echo '<input class="regular-text" type="' . esc_attr($field[1]) . '" id="ttn_' . esc_attr($key) . '" name="ttn_competition[' . esc_attr($key) . ']" value="' . esc_attr($value) . '"' . $input_attributes . '></p>';
        }
        echo '<p class="description">' . esc_html__('Registration requires a signed-in account. Paid events use the configured WooCommerce checkout; no payment settings or gateway are configured here.', 'tee-time-nexus-competitions') . '</p>';
        echo '</div>';
    }

    public static function save_meta($post_id, $post) {
        if (
            !isset($_POST['ttn_competition_details_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ttn_competition_details_nonce'])), 'ttn_save_competition_details')
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || wp_is_post_revision($post_id)
            || !current_user_can('edit_post', $post_id)
            || $post->post_type !== self::POST_TYPE
        ) {
            return;
        }

        $posted = isset($_POST['ttn_competition']) && is_array($_POST['ttn_competition'])
            ? wp_unslash($_POST['ttn_competition'])
            : array();

        $dates = array();
        foreach (array('start_date', 'end_date', 'registration_start', 'registration_end') as $key) {
            $value = isset($posted[$key]) ? sanitize_text_field($posted[$key]) : '';
            if ($value !== '' && !self::is_valid_date($value)) {
                self::set_save_error();
                return;
            }
            $dates[$key] = $value;
        }
        if (
            ($post->post_status === 'publish' && !$dates['start_date'])
            || ($dates['start_date'] && $dates['end_date'] && $dates['end_date'] < $dates['start_date'])
            || ($dates['registration_start'] && $dates['registration_end'] && $dates['registration_end'] < $dates['registration_start'])
        ) {
            self::set_save_error();
            return;
        }

        $type = isset($_POST['ttn_competition_type']) ? sanitize_key(wp_unslash($_POST['ttn_competition_type'])) : '';
        if ($type !== '' && !in_array($type, array('league', 'tournament'), true)) {
            self::set_save_error();
            return;
        }
        if ($post->post_status === 'publish' && $type === '') {
            self::set_save_error();
            return;
        }

        if (isset($posted['entry_fee']) && $posted['entry_fee'] !== '' && (!is_numeric($posted['entry_fee']) || (float) $posted['entry_fee'] < 0)) {
            self::set_save_error();
            return;
        }
        if (isset($posted['capacity']) && $posted['capacity'] !== '' && (
            !is_numeric($posted['capacity'])
            || (float) $posted['capacity'] < 0
            || floor((float) $posted['capacity']) !== (float) $posted['capacity']
        )) {
            self::set_save_error();
            return;
        }

        if ($type !== '') {
            $term = get_term_by('slug', $type, self::TAXONOMY);
            if (!$term || is_wp_error($term)) {
                self::set_save_error();
                return;
            }
            $assigned = wp_set_object_terms($post_id, array((int) $term->term_id), self::TAXONOMY, false);
            if (is_wp_error($assigned)) {
                self::set_save_error();
                return;
            }
        }

        foreach ($dates as $key => $value) {
            self::update_or_delete_meta($post_id, '_ttn_' . $key, $value);
        }

        if (isset($posted['entry_fee']) && $posted['entry_fee'] !== '' && is_numeric($posted['entry_fee'])) {
            $fee = max(0, (float) $posted['entry_fee']);
            update_post_meta($post_id, '_ttn_entry_fee', number_format($fee, 2, '.', ''));
        } elseif (!isset($posted['entry_fee']) || $posted['entry_fee'] === '') {
            delete_post_meta($post_id, '_ttn_entry_fee');
        }

        if (isset($posted['capacity']) && is_numeric($posted['capacity'])) {
            update_post_meta($post_id, '_ttn_capacity', (string) max(0, absint($posted['capacity'])));
        } elseif (!isset($posted['capacity']) || $posted['capacity'] === '') {
            delete_post_meta($post_id, '_ttn_capacity');
        }

        foreach (array('format', 'course') as $key) {
            $value = isset($posted[$key]) ? sanitize_text_field($posted[$key]) : '';
            self::update_or_delete_meta($post_id, '_ttn_' . $key, $value);
        }
    }

    public static function render_save_notice() {
        $user_id = get_current_user_id();
        if (!$user_id || !get_transient('ttn_competition_save_error_' . $user_id)) {
            return;
        }

        delete_transient('ttn_competition_save_error_' . $user_id);
        echo '<div class="notice notice-error"><p>' . esc_html__('Competition details were not saved. Select a competition type, enter valid dates and non-negative fees/capacity, and make sure each end date is on or after its start date.', 'tee-time-nexus-competitions') . '</p></div>';
    }

    private static function set_save_error() {
        set_transient('ttn_competition_save_error_' . get_current_user_id(), 1, MINUTE_IN_SECONDS);
    }

    private static function update_or_delete_meta($post_id, $key, $value) {
        if ($value === '') {
            delete_post_meta($post_id, $key);
            return;
        }

        update_post_meta($post_id, $key, $value);
    }

    private static function is_valid_date($value) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value;
    }
}
