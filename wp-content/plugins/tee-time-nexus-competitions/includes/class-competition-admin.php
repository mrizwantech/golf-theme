<?php

if (!defined('ABSPATH')) {
    exit;
}

final class TTN_Competitions_Admin {
    public function __construct() {
        add_action('admin_menu', array($this, 'register_menu'), 9);
        add_action('admin_post_ttn_competitions_create_pages', array($this, 'create_listing_pages'));
    }

    public function register_menu() {
        add_menu_page(
            __('Golf Competitions', 'tee-time-nexus-competitions'),
            __('Golf Competitions', 'tee-time-nexus-competitions'),
            'manage_ttn_competitions',
            'ttn-competitions',
            array($this, 'render_dashboard'),
            'dashicons-awards',
            27
        );
        add_submenu_page(
            'ttn-competitions',
            __('Registrations', 'tee-time-nexus-competitions'),
            __('Registrations', 'tee-time-nexus-competitions'),
            'manage_ttn_competitions',
            'ttn-competition-registrations',
            array($this, 'render_registrations')
        );
    }

    public function create_listing_pages() {
        if (
            !current_user_can('manage_ttn_competitions')
            || !current_user_can('edit_pages')
            || !current_user_can('publish_pages')
        ) {
            wp_die(esc_html__('You are not allowed to manage competitions.', 'tee-time-nexus-competitions'));
        }
        check_admin_referer('ttn_competitions_create_pages');

        $pages = array(
            'leagues-tournaments' => array(__('Leagues & Tournaments', 'tee-time-nexus-competitions'), '[ttn_competitions view="landing"]'),
            'golf-leagues' => array(__('Golf Leagues', 'tee-time-nexus-competitions'), '[ttn_competitions type="league"]'),
            'golf-tournaments' => array(__('Golf Tournaments', 'tee-time-nexus-competitions'), '[ttn_competitions type="tournament"]'),
        );
        $created = 0;
        $failed = false;

        foreach ($pages as $slug => $page) {
            if (get_page_by_path($slug, OBJECT, 'page')) {
                continue;
            }
            $page_id = wp_insert_post(array(
                'post_title' => $page[0],
                'post_name' => $slug,
                'post_content' => $page[1],
                'post_status' => 'publish',
                'post_type' => 'page',
            ), true);
            if (is_wp_error($page_id)) {
                $failed = true;
            } else {
                $created++;
            }
        }

        $result = $failed ? 'error' : ($created ? 'created' : 'existing');
        wp_safe_redirect(add_query_arg(array(
            'page' => 'ttn-competitions',
            'ttn_pages' => $result,
        ), admin_url('admin.php')));
        exit;
    }

    public function render_dashboard() {
        if (!current_user_can('manage_ttn_competitions')) {
            wp_die(esc_html__('You are not allowed to manage competitions.', 'tee-time-nexus-competitions'));
        }
        $counts = wp_count_posts(TTN_Competitions_Post_Type::POST_TYPE);
        $published = isset($counts->publish) ? (int) $counts->publish : 0;
        $drafts = isset($counts->draft) ? (int) $counts->draft : 0;
        $woocommerce_ready = function_exists('WC') && class_exists('WC_Product_Simple');
        $this->render_notice();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Golf Competitions', 'tee-time-nexus-competitions'); ?></h1>
            <p><?php esc_html_e('Create and publish league and tournament listings, accept individual player registrations, and collect entry fees through WooCommerce.', 'tee-time-nexus-competitions'); ?></p>
            <p>
                <a class="button button-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=' . TTN_Competitions_Post_Type::POST_TYPE)); ?>"><?php esc_html_e('Add Competition', 'tee-time-nexus-competitions'); ?></a>
                <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=' . TTN_Competitions_Post_Type::POST_TYPE)); ?>"><?php esc_html_e('Manage Competitions', 'tee-time-nexus-competitions'); ?></a>
            </p>
            <div class="card">
                <h2><?php esc_html_e('Registration and payments', 'tee-time-nexus-competitions'); ?></h2>
                <p><?php echo $woocommerce_ready ? esc_html__('WooCommerce checkout is available. Check that at least one payment method is enabled under WooCommerce → Settings → Payments.', 'tee-time-nexus-competitions') : esc_html__('WooCommerce is not available. Free competitions can still accept registrations, but paid registration requires WooCommerce to be active.', 'tee-time-nexus-competitions'); ?></p>
                <p><?php esc_html_e('Players need a WordPress account. Entry fees use the configured checkout and coupons. Pending payments temporarily reserve a place; teams, schedules, scoring, and leaderboards are future phases.', 'tee-time-nexus-competitions'); ?></p>
            </div>
            <div class="card">
                <h2><?php esc_html_e('Overview', 'tee-time-nexus-competitions'); ?></h2>
                <p><?php echo esc_html(sprintf(_n('%d published competition', '%d published competitions', $published, 'tee-time-nexus-competitions'), $published)); ?> &middot; <?php echo esc_html(sprintf(_n('%d draft', '%d drafts', $drafts, 'tee-time-nexus-competitions'), $drafts)); ?></p>
            </div>
            <div class="card">
                <h2><?php esc_html_e('Website listings', 'tee-time-nexus-competitions'); ?></h2>
                <p><?php esc_html_e('Create the three listing pages if they do not already exist. Existing pages are left untouched. Add the pages to the site navigation from Appearance → Menus.', 'tee-time-nexus-competitions'); ?></p>
                <?php if (current_user_can('edit_pages') && current_user_can('publish_pages')) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="ttn_competitions_create_pages">
                        <?php wp_nonce_field('ttn_competitions_create_pages'); ?>
                        <?php submit_button(__('Create Missing Listing Pages', 'tee-time-nexus-competitions'), 'secondary', 'submit', false); ?>
                    </form>
                <?php else : ?>
                    <p><?php esc_html_e('Ask a site administrator to create the pages; this account can still manage competition listings.', 'tee-time-nexus-competitions'); ?></p>
                <?php endif; ?>
                <p><code>[ttn_competitions view="landing"]</code> <code>[ttn_competitions type="league"]</code> <code>[ttn_competitions type="tournament"]</code></p>
            </div>
            <div class="card">
                <h2><?php esc_html_e('Mobile app feed', 'tee-time-nexus-competitions'); ?></h2>
                <p><code><?php echo esc_html(rest_url('ttn/v1/competitions')); ?></code></p>
                <p><?php esc_html_e('Public read-only JSON. Supports ?type=league, ?type=tournament, and ?limit=1-50.', 'tee-time-nexus-competitions'); ?></p>
            </div>
        </div>
        <?php
    }

    public function render_registrations() {
        if (!current_user_can('manage_ttn_competitions')) {
            wp_die(esc_html__('You are not allowed to view registrations.', 'tee-time-nexus-competitions'));
        }

        global $wpdb;
        $table = TTN_Competitions_Registrations::table_name();
        $rows = $wpdb->get_results(
            "SELECT r.*, p.post_title
            FROM $table r
            LEFT JOIN {$wpdb->posts} p ON p.ID = r.competition_id
            ORDER BY r.created_at DESC
            LIMIT 200"
        );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Competition Registrations', 'tee-time-nexus-competitions'); ?></h1>
            <p><?php esc_html_e('Showing the latest 200 registrations. Use the linked WooCommerce order to review or refund a paid registration.', 'tee-time-nexus-competitions'); ?></p>
            <table class="widefat striped">
                <thead><tr>
                    <th><?php esc_html_e('Competition', 'tee-time-nexus-competitions'); ?></th>
                    <th><?php esc_html_e('Player', 'tee-time-nexus-competitions'); ?></th>
                    <th><?php esc_html_e('Contact', 'tee-time-nexus-competitions'); ?></th>
                    <th><?php esc_html_e('Fee', 'tee-time-nexus-competitions'); ?></th>
                    <th><?php esc_html_e('Registration', 'tee-time-nexus-competitions'); ?></th>
                    <th><?php esc_html_e('Order', 'tee-time-nexus-competitions'); ?></th>
                    <th><?php esc_html_e('Registered', 'tee-time-nexus-competitions'); ?></th>
                </tr></thead>
                <tbody>
                    <?php if (!$rows) : ?>
                        <tr><td colspan="7"><?php esc_html_e('No registrations yet.', 'tee-time-nexus-competitions'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($rows as $row) : ?>
                            <?php $order = $row->order_id && function_exists('wc_get_order') ? wc_get_order($row->order_id) : false; ?>
                            <tr>
                                <td><a href="<?php echo esc_url(get_edit_post_link($row->competition_id)); ?>"><?php echo esc_html($row->post_title ?: __('Deleted competition', 'tee-time-nexus-competitions')); ?></a></td>
                                <td><?php echo esc_html($row->player_name); ?><br><small><?php echo esc_html(sprintf(__('User #%d', 'tee-time-nexus-competitions'), (int) $row->user_id)); ?><?php if ($row->handicap) : ?> · <?php echo esc_html(sprintf(__('Handicap %s', 'tee-time-nexus-competitions'), $row->handicap)); ?><?php endif; ?><?php if ($row->golfzon_username) : ?><br><?php echo esc_html(sprintf(__('GOLFZON: %s', 'tee-time-nexus-competitions'), $row->golfzon_username)); ?><?php endif; ?></small></td>
                                <td><a href="mailto:<?php echo esc_attr($row->player_email); ?>"><?php echo esc_html($row->player_email); ?></a><?php if ($row->player_phone) : ?><br><?php echo esc_html($row->player_phone); ?><?php endif; ?></td>
                                <td><?php echo esc_html(function_exists('wc_price') ? wp_strip_all_tags(wc_price((float) $row->fee)) : '$' . number_format((float) $row->fee, 2)); ?></td>
                                <td><?php echo esc_html(ucwords(str_replace('_', ' ', $row->status))); ?></td>
                                <td><?php if ($order) : ?><a href="<?php echo esc_url($order->get_edit_order_url()); ?>"><?php echo esc_html(sprintf(__('Order #%s (%s)', 'tee-time-nexus-competitions'), $order->get_order_number(), wc_get_order_status_name($order->get_status()))); ?></a><?php else : ?>&mdash;<?php endif; ?></td>
                                <td><?php echo esc_html($row->registered_at ? $row->registered_at : $row->created_at); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_notice() {
        if (empty($_GET['ttn_pages'])) {
            return;
        }

        $result = sanitize_key(wp_unslash($_GET['ttn_pages']));
        if ($result === 'created') {
            $message = __('Missing listing pages were created. Add them to your navigation when ready.', 'tee-time-nexus-competitions');
            $class = 'notice-success';
        } elseif ($result === 'existing') {
            $message = __('The listing pages already exist; no pages were changed.', 'tee-time-nexus-competitions');
            $class = 'notice-info';
        } else {
            $message = __('One or more listing pages could not be created. Check that your account can create pages and try again.', 'tee-time-nexus-competitions');
            $class = 'notice-error';
        }
        echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }
}
