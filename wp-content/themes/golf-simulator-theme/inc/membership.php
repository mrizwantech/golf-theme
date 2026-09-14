<?php

function golf_simulator_theme_create_membership_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'user_memberships';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        package_name varchar(100) NOT NULL DEFAULT '',
        package_slug varchar(100) NOT NULL DEFAULT '',
        price varchar(50) DEFAULT '',
        discount_price varchar(50) DEFAULT '',
        payment_status varchar(30) NOT NULL DEFAULT 'pending',
        status varchar(30) NOT NULL DEFAULT 'pending',
        payment_date datetime NULL,
        next_billing_date datetime NULL,
        start_date datetime NULL,
        cancel_date datetime NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY package_name (package_name),
        KEY status (status)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
add_action('init', 'golf_simulator_theme_create_membership_table');

function golf_simulator_theme_create_membership_history_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'membership_history';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        action varchar(30) NOT NULL DEFAULT '',
        previous_package varchar(100) NOT NULL DEFAULT '',
        new_package varchar(100) NOT NULL DEFAULT '',
        amount varchar(50) NOT NULL DEFAULT '0.00',
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY action (action)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
add_action('init', 'golf_simulator_theme_create_membership_history_table');

function golf_simulator_theme_get_user_membership_record($user_id) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'user_memberships';

    return $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d ORDER BY id DESC LIMIT 1", absint($user_id))
    );
}

function golf_simulator_theme_get_membership_history($user_id) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'membership_history';
    // Excludes the initial 'signup' row so this only reflects actual past changes (upgrade/downgrade/pause/cancel).
    return $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d AND action != 'signup' ORDER BY created_at DESC, id DESC", absint($user_id))
    );
}

function golf_simulator_theme_save_user_membership_record($args = array()) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'user_memberships';
    $defaults = array(
        'user_id' => 0,
        'package_name' => '',
        'package_slug' => '',
        'price' => '',
        'discount_price' => '',
        'payment_status' => 'pending',
        'status' => 'pending',
        'payment_date' => '',
        'next_billing_date' => '',
        'start_date' => '',
        'cancel_date' => '',
    );

    $data = wp_parse_args($args, $defaults);

    $user_id = absint($data['user_id']);
    if (empty($user_id)) {
        return false;
    }

    $package_name = sanitize_text_field($data['package_name']);
    $package_slug = sanitize_title($data['package_slug'] ?: $package_name);
    $status = in_array($data['status'], array('active', 'pending', 'cancelled', 'paused', 'upgraded', 'downgraded'), true) ? $data['status'] : 'pending';
    $payment_status = in_array($data['payment_status'], array('paid', 'pending', 'failed', 'cancelled', 'refunded'), true) ? $data['payment_status'] : 'pending';
    $payment_date = !empty($data['payment_date']) ? $data['payment_date'] : current_time('mysql');
    $start_date = !empty($data['start_date']) ? $data['start_date'] : current_time('mysql');
    $next_billing_date = !empty($data['next_billing_date']) ? $data['next_billing_date'] : '';
    $cancel_date = !empty($data['cancel_date']) ? $data['cancel_date'] : '';

    $record = array(
        'user_id' => $user_id,
        'package_name' => $package_name,
        'package_slug' => $package_slug,
        'price' => sanitize_text_field($data['price']),
        'discount_price' => sanitize_text_field($data['discount_price']),
        'payment_status' => $payment_status,
        'status' => $status,
        'payment_date' => $payment_date,
        'next_billing_date' => $next_billing_date,
        'start_date' => $start_date,
        'cancel_date' => $cancel_date,
        'updated_at' => current_time('mysql'),
    );

    $existing = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d ORDER BY id DESC LIMIT 1", $user_id)
    );

    if ($existing) {
        $wpdb->update(
            $table_name,
            $record,
            array('id' => $existing->id),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );

        return $existing->id;
    }

    $wpdb->insert(
        $table_name,
        $record,
        array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
    );

    return $wpdb->insert_id;
}

function golf_simulator_theme_update_member_membership_status($user_id, $package_name, $status, $payment_status = 'paid', $payment_date = null, $next_billing_date = null, $cancel_date = null) {
    $package_data = golf_simulator_theme_get_default_membership_packages();
    $package = $package_data[$package_name] ?? array();

    return golf_simulator_theme_save_user_membership_record(array(
        'user_id' => $user_id,
        'package_name' => $package_name,
        'package_slug' => $package_name,
        'price' => $package['price'] ?? '',
        'discount_price' => $package['discount_price'] ?? '',
        'payment_status' => $payment_status,
        'status' => $status,
        'payment_date' => $payment_date ? $payment_date : current_time('mysql'),
        'next_billing_date' => $next_billing_date ?: '',
        'cancel_date' => $cancel_date ?: '',
        'start_date' => current_time('mysql'),
    ));
}

function golf_simulator_theme_calculate_prorated_upgrade_amount($membership, $new_package) {
    if (!$membership || empty($membership->next_billing_date)) {
        return 0.00;
    }

    $current_price = (float) (!empty($membership->discount_price) ? $membership->discount_price : $membership->price);
    $new_price = (float) (!empty($new_package['discount_price']) ? $new_package['discount_price'] : $new_package['price']);
    $price_difference = $new_price - $current_price;

    if ($price_difference <= 0) {
        return 0.00;
    }

    $now = current_time('timestamp');
    $next_billing_timestamp = mysql2date('U', $membership->next_billing_date, false);
    $start_timestamp = !empty($membership->start_date) ? mysql2date('U', $membership->start_date, false) : strtotime('-1 month', $next_billing_timestamp);
    $remaining_seconds = max(0, $next_billing_timestamp - $now);
    $billing_cycle_seconds = max(1, $next_billing_timestamp - $start_timestamp);
    $remaining_fraction = min(1, $remaining_seconds / $billing_cycle_seconds);

    return round($price_difference * $remaining_fraction, 2);
}

function golf_simulator_theme_calculate_prorated_refund_amount($membership, $new_package = null) {
    if (!$membership || empty($membership->next_billing_date)) {
        return 0.00;
    }

    $current_price = (float) (!empty($membership->discount_price) ? $membership->discount_price : $membership->price);
    $new_price = $new_package ? (float) (!empty($new_package['discount_price']) ? $new_package['discount_price'] : $new_package['price']) : 0.00;
    $refund_difference = $current_price - $new_price;

    if ($refund_difference <= 0) {
        return 0.00;
    }

    $now = current_time('timestamp');
    $next_billing_timestamp = mysql2date('U', $membership->next_billing_date, false);
    $start_timestamp = !empty($membership->start_date) ? mysql2date('U', $membership->start_date, false) : strtotime('-1 month', $next_billing_timestamp);
    $remaining_seconds = max(0, $next_billing_timestamp - $now);
    $billing_cycle_seconds = max(1, $next_billing_timestamp - $start_timestamp);

    return round($refund_difference * min(1, $remaining_seconds / $billing_cycle_seconds), 2);
}

/**
 * WooCommerce Product Sync & Checkout Helpers for Membership Packages
 */

function golf_simulator_theme_get_membership_product_id($package_name) {
    if (!class_exists('WC_Product_Simple') || !function_exists('wc_get_products')) {
        return 0;
    }

    $sku = 'membership-' . sanitize_title($package_name);
    $products = wc_get_products(array(
        'sku' => $sku,
        'limit' => 1,
        'status' => 'any',
    ));

    if (!empty($products) && isset($products[0])) {
        return $products[0]->get_id();
    }

    return 0;
}

function golf_simulator_theme_sync_membership_products() {
    if (!function_exists('wc_get_products') || !class_exists('WC_Product_Simple')) {
        return;
    }

    $packages = golf_simulator_theme_get_default_membership_packages();

    foreach ($packages as $package_key => $package) {
        $sku = 'membership-' . sanitize_title($package_key);
        $products = wc_get_products(array('sku' => $sku, 'limit' => 1, 'status' => 'any'));
        $product = !empty($products) ? $products[0] : new WC_Product_Simple();

        $regular_price = (string) (float) ($package['price'] ?? 0);
        $discount_price = !empty($package['discount_price']) && (float) $package['discount_price'] < (float) $regular_price
            ? (string) (float) $package['discount_price']
            : '';
        $active_price = $discount_price !== '' ? $discount_price : $regular_price;

        $product->set_name($package['title'] . ' Membership');
        $product->set_sku($sku);
        $product->set_regular_price($regular_price);
        if ($discount_price !== '') {
            $product->set_sale_price($discount_price);
        } else {
            $product->set_sale_price('');
        }
        $product->set_price($active_price);
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->set_virtual(true);
        $product->set_sold_individually(true);
        $product->save();
    }
}
add_action('init', 'golf_simulator_theme_sync_membership_products', 20);

function golf_simulator_theme_add_membership_to_cart_and_redirect($package_name, $is_upgrade = false, $custom_price = null) {
    if (!class_exists('WC_Cart') || !function_exists('wc_get_checkout_url') || !function_exists('WC')) {
        return false;
    }

    $wc = WC();
    if (!$wc || !isset($wc->cart)) {
        return false;
    }

    golf_simulator_theme_sync_membership_products();
    $product_id = golf_simulator_theme_get_membership_product_id($package_name);

    if (!$product_id) {
        return false;
    }

    $wc->cart->empty_cart();

    $cart_item_data = array(
        'golf_simulator_membership' => array(
            'package_name' => $package_name,
            'is_upgrade' => $is_upgrade,
            'custom_price' => ($custom_price !== null && (float) $custom_price >= 0) ? (float) $custom_price : null,
        ),
    );

    $wc->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);

    wp_safe_redirect(wc_get_checkout_url());
    exit;
}

function golf_simulator_theme_set_membership_cart_item_price($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    foreach ($cart->get_cart() as $cart_item) {
        if (!empty($cart_item['golf_simulator_membership']) && isset($cart_item['data'])) {
            $custom_price = $cart_item['golf_simulator_membership']['custom_price'] ?? null;
            if ($custom_price !== null && is_numeric($custom_price) && (float) $custom_price >= 0) {
                $cart_item['data']->set_price((float) $custom_price);
            }
        }
    }
}
add_action('woocommerce_before_calculate_totals', 'golf_simulator_theme_set_membership_cart_item_price', 20);

function golf_simulator_theme_render_membership_cart_item_data($item_data, $cart_item) {
    if (!empty($cart_item['golf_simulator_membership'])) {
        $meta = $cart_item['golf_simulator_membership'];
        $package_name = $meta['package_name'] ?? '';
        $is_upgrade = !empty($meta['is_upgrade']);
        $item_data[] = array(
            'key' => $is_upgrade ? __('Membership Upgrade', 'golf-simulator-theme') : __('Membership Tier', 'golf-simulator-theme'),
            'value' => esc_html($package_name),
        );
    }
    return $item_data;
}
add_filter('woocommerce_get_item_data', 'golf_simulator_theme_render_membership_cart_item_data', 20, 2);

function golf_simulator_theme_store_membership_order_line_data($item, $cart_item_key, $values, $order) {
    if (!empty($values['golf_simulator_membership'])) {
        $meta = $values['golf_simulator_membership'];
        $item->add_meta_data('_membership_package_name', $meta['package_name']);
        $item->add_meta_data('_is_membership_upgrade', !empty($meta['is_upgrade']) ? 'yes' : 'no');
        if (isset($meta['custom_price']) && $meta['custom_price'] !== null) {
            $item->add_meta_data('_membership_custom_price', (string) $meta['custom_price']);
        }
    }
}
add_action('woocommerce_checkout_create_order_line_item', 'golf_simulator_theme_store_membership_order_line_data', 20, 4);

function golf_simulator_theme_process_membership_wc_order($order_id) {
    if (!function_exists('wc_get_order')) {
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    if ($order->get_meta('_membership_processed') === 'yes') {
        return;
    }

    $user_id = $order->get_user_id();
    if (!$user_id) {
        $billing_email = $order->get_billing_email();
        if ($billing_email) {
            $user = get_user_by('email', $billing_email);
            if ($user) {
                $user_id = $user->ID;
            }
        }
    }

    if (!$user_id) {
        return;
    }

    $has_membership = false;

    foreach ($order->get_items() as $item) {
        $package_name = $item->get_meta('_membership_package_name');
        if (!$package_name) {
            $product = $item->get_product();
            if ($product) {
                $sku = (string) $product->get_sku();
                if (strpos($sku, 'membership-') === 0) {
                    $package_slug = substr($sku, 11);
                    $packages = golf_simulator_theme_get_default_membership_packages();
                    foreach ($packages as $k => $p) {
                        if (sanitize_title($k) === $package_slug || sanitize_title($p['title']) === $package_slug) {
                            $package_name = $k;
                            break;
                        }
                    }
                }
            }
        }

        if ($package_name) {
            $has_membership = true;
            $packages = golf_simulator_theme_get_default_membership_packages();
            $package = $packages[$package_name] ?? array();
            $is_upgrade = $item->get_meta('_is_membership_upgrade') === 'yes';
            $current_membership = golf_simulator_theme_get_user_membership_record($user_id);
            $start_date = ($is_upgrade && $current_membership && !empty($current_membership->start_date))
                ? $current_membership->start_date
                : current_time('mysql');

            golf_simulator_theme_save_user_membership_record(array(
                'user_id' => $user_id,
                'package_name' => $package_name,
                'package_slug' => sanitize_title($package_name),
                'price' => $package['price'] ?? '',
                'discount_price' => $package['discount_price'] ?? '',
                'payment_status' => 'paid',
                'status' => 'active',
                'payment_date' => current_time('mysql'),
                'next_billing_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
                'start_date' => $start_date,
            ));

            global $wpdb;
            $wpdb->insert(
                $wpdb->prefix . 'membership_history',
                array(
                    'user_id' => $user_id,
                    'action' => $is_upgrade ? 'upgrade' : 'signup',
                    'previous_package' => ($current_membership && $is_upgrade) ? $current_membership->package_name : '',
                    'new_package' => $package_name,
                    'amount' => number_format((float) $order->get_total(), 2, '.', ''),
                    'created_at' => current_time('mysql'),
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s')
            );

            update_user_meta($user_id, '_membership_last_wc_order_id', $order_id);
            delete_user_meta($user_id, '_membership_upgrade_balance');

            golf_simulator_theme_send_membership_confirmation(
                $user_id,
                $package_name,
                $order->get_total(),
                'paid',
                $is_upgrade ? 'Membership upgrade payment confirmed' : 'Membership signup confirmed'
            );
        }
    }

    if ($has_membership) {
        $order->update_meta_data('_membership_processed', 'yes');
        $order->save();
    }
}
add_action('woocommerce_order_status_completed', 'golf_simulator_theme_process_membership_wc_order');
add_action('woocommerce_order_status_processing', 'golf_simulator_theme_process_membership_wc_order');
add_action('woocommerce_payment_complete', 'golf_simulator_theme_process_membership_wc_order');

function golf_simulator_theme_membership_return_url($return_url, $order) {
    if ($order instanceof WC_Order) {
        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_membership_package_name')) {
                return add_query_arg('membership_joined', '1', home_url('/my-account/'));
            }
        }
    }
    return $return_url;
}
add_filter('woocommerce_get_return_url', 'golf_simulator_theme_membership_return_url', 10, 2);

function golf_simulator_theme_handle_membership_join_redirect() {
    if (is_admin()) {
        return;
    }

    $package = '';
    if (isset($_GET['join_membership'])) {
        $package = sanitize_text_field(wp_unslash($_GET['join_membership']));
    } elseif (isset($_GET['package']) && is_page('membership')) {
        $package = sanitize_text_field(wp_unslash($_GET['package']));
    }

    if ($package) {
        $package = strtoupper($package);
        $packages = golf_simulator_theme_get_default_membership_packages();
        if (isset($packages[$package])) {
            golf_simulator_theme_add_membership_to_cart_and_redirect($package);
        }
    }
}
add_action('template_redirect', 'golf_simulator_theme_handle_membership_join_redirect');

function golf_simulator_theme_register_membership_package_post_type() {
    register_post_type('membership_package', array(
        'labels' => array(
            'name' => __('Membership Packages', 'golf-simulator-theme'),
            'singular_name' => __('Membership Package', 'golf-simulator-theme'),
            'add_new_item' => __('Add New Membership Package', 'golf-simulator-theme'),
            'edit_item' => __('Edit Membership Package', 'golf-simulator-theme'),
            'new_item' => __('New Membership Package', 'golf-simulator-theme'),
            'view_item' => __('View Membership Package', 'golf-simulator-theme'),
            'search_items' => __('Search Membership Packages', 'golf-simulator-theme'),
            'not_found' => __('No membership packages found.', 'golf-simulator-theme'),
        ),
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => false,
        'menu_icon' => 'dashicons-groups',
        'supports' => array('title'),
        'has_archive' => false,
        'rewrite' => array('slug' => 'membership-package'),
        'show_in_rest' => false,
        'menu_position' => 25,
    ));
}
add_action('init', 'golf_simulator_theme_register_membership_package_post_type');

function golf_simulator_theme_membership_admin_assets($hook) {
    $allowed_hooks = array(
        'edit.php',
        'post.php',
        'post-new.php',
        'toplevel_page_membership-packages',
        'membership-packages_page_membership-package-settings',
    );

    if (!in_array($hook, $allowed_hooks, true)) {
        return;
    }

    wp_enqueue_style(
        'golf-simulator-membership-admin',
        get_stylesheet_directory_uri() . '/style.css',
        array(),
        wp_get_theme()->get('Version')
    );

    wp_add_inline_style('golf-simulator-membership-admin', "
        .membership-package-admin-box {
            display: grid;
            gap: 18px;
            padding: 16px;
            background: #f7faf9;
            border: 1px solid rgba(15, 81, 50, 0.12);
            border-radius: 14px;
            box-shadow: 0 8px 18px rgba(15, 81, 50, 0.04);
        }
        .membership-package-admin-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(220px, 1fr));
            gap: 16px;
        }
        .membership-package-admin-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .membership-package-admin-field label {
            font-weight: 700;
            color: #123b31;
        }
        .membership-package-admin-field input,
        .membership-package-admin-field textarea {
            width: 100%;
            max-width: 100%;
            border: 1px solid rgba(15, 81, 50, 0.2);
            border-radius: 10px;
            padding: 10px 12px;
            background: #fff;
            color: #21352d;
        }
        .membership-package-admin-field textarea {
            min-height: 130px;
        }
        .membership-package-admin-actions {
            display: flex;
            justify-content: flex-start;
            gap: 10px;
            align-items: center;
            margin-top: 8px;
        }
        .membership-package-admin-actions .button {
            border-radius: 8px;
        }
        .membership-package-settings-wrapper {
            max-width: 980px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 10px 26px rgba(17, 25, 40, 0.04);
        }
        .membership-package-settings-wrapper h2 {
            margin-top: 0;
        }
        @media (max-width: 767px) {
            .membership-package-admin-grid {
                grid-template-columns: 1fr;
            }
        }
    ");
}
add_action('admin_enqueue_scripts', 'golf_simulator_theme_membership_admin_assets');

function golf_simulator_theme_save_membership_package_defaults() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (!isset($_POST['golf_simulator_membership_defaults_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['golf_simulator_membership_defaults_nonce'])), 'golf_simulator_membership_defaults')) {
        return;
    }

    $templates = golf_simulator_theme_get_default_membership_package_templates();
    $saved = array();

    foreach ($templates as $package_key => $template) {
        $price = isset($_POST['golf_simulator_membership_defaults'][$package_key]['price']) ? sanitize_text_field(wp_unslash($_POST['golf_simulator_membership_defaults'][$package_key]['price'])) : $template['price'];
        $discount_price = isset($_POST['golf_simulator_membership_defaults'][$package_key]['discount_price']) ? sanitize_text_field(wp_unslash($_POST['golf_simulator_membership_defaults'][$package_key]['discount_price'])) : $template['discount_price'];
        $price_error = golf_simulator_theme_validate_membership_prices($price, $discount_price);

        if (is_wp_error($price_error)) {
            $redirect = add_query_arg(
                array(
                    'page' => 'membership-package-settings',
                    'membership_error' => $price_error->get_error_message(),
                ),
                admin_url('admin.php')
            );

            wp_safe_redirect($redirect);
            exit;
        }

        $saved[$package_key] = array(
            'title' => isset($_POST['golf_simulator_membership_defaults'][$package_key]['title']) ? sanitize_text_field(wp_unslash($_POST['golf_simulator_membership_defaults'][$package_key]['title'])) : $template['title'],
            'price' => $price,
            'discount_price' => $discount_price,
            'billing' => isset($_POST['golf_simulator_membership_defaults'][$package_key]['billing']) ? sanitize_text_field(wp_unslash($_POST['golf_simulator_membership_defaults'][$package_key]['billing'])) : $template['billing'],
            'featured' => !empty($_POST['golf_simulator_membership_defaults'][$package_key]['featured']) ? true : false,
            'features' => isset($_POST['golf_simulator_membership_defaults'][$package_key]['features']) ? wp_unslash($_POST['golf_simulator_membership_defaults'][$package_key]['features']) : implode("\n", $template['features']),
        );
    }

    update_option('golf_simulator_membership_package_defaults', $saved);

    foreach ($saved as $package) {
        $package_posts = get_posts(array(
            'post_type' => 'membership_package',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'suppress_filters' => false,
        ));

        foreach ($package_posts as $package_post) {
            if (sanitize_title($package_post->post_title) !== sanitize_title($package['title'])) {
                continue;
            }

            update_post_meta($package_post->ID, '_membership_price', $package['price']);
            update_post_meta($package_post->ID, '_membership_discount_price', $package['discount_price']);
            update_post_meta($package_post->ID, '_membership_billing', $package['billing']);
            update_post_meta($package_post->ID, '_membership_featured', $package['featured'] ? '1' : '0');
            update_post_meta($package_post->ID, '_membership_features', $package['features']);
        }
    }

    $redirect = add_query_arg(
        array(
            'updated' => '1',
        ),
        admin_url('admin.php?page=membership-package-settings')
    );

    wp_safe_redirect($redirect);
    exit;
}
add_action('admin_post_golf_simulator_membership_defaults_save', 'golf_simulator_theme_save_membership_package_defaults');

function golf_simulator_theme_membership_package_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $defaults = golf_simulator_theme_get_default_membership_packages();
    ?>
    <div class="wrap membership-package-settings-wrapper">
        <h2><?php esc_html_e('Membership Package Settings', 'golf-simulator-theme'); ?></h2>
        <p><?php esc_html_e('These default values are used whenever a package is restored or newly created. You can edit them here and save the changes once.', 'golf-simulator-theme'); ?></p>

        <div class="membership-package-admin-actions" style="margin: 0 0 20px;">
            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=membership_package')); ?>" class="button button-primary">
                <?php esc_html_e('Add Membership Package', 'golf-simulator-theme'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=membership_package')); ?>" class="button button-secondary">
                <?php esc_html_e('Manage Packages', 'golf-simulator-theme'); ?>
            </a>
        </div>

        <?php if (isset($_GET['membership_error']) && $_GET['membership_error']) : ?>
            <div class="notice notice-error is-dismissible"><p><?php echo esc_html(wp_unslash($_GET['membership_error'])); ?></p></div>
        <?php elseif (isset($_GET['updated']) && '1' === $_GET['updated']) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Membership package defaults were updated.', 'golf-simulator-theme'); ?></p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('golf_simulator_membership_defaults', 'golf_simulator_membership_defaults_nonce'); ?>
            <input type="hidden" name="action" value="golf_simulator_membership_defaults_save" />

            <div class="membership-package-admin-box">
                <?php foreach ($defaults as $key => $package) : ?>
                    <div class="membership-package-admin-grid" style="border:1px solid rgba(15,81,50,0.12); border-radius:12px; padding:16px; background:#fff;">
                        <div class="membership-package-admin-field">
                            <label><strong><?php echo esc_html($key); ?> <?php esc_html_e('Tier Name', 'golf-simulator-theme'); ?></strong></label>
                            <input type="text" name="golf_simulator_membership_defaults[<?php echo esc_attr($key); ?>][title]" value="<?php echo esc_attr($package['title']); ?>" />
                        </div>

                        <div class="membership-package-admin-field">
                            <label><strong><?php esc_html_e('Regular Price', 'golf-simulator-theme'); ?></strong></label>
                            <input type="text" name="golf_simulator_membership_defaults[<?php echo esc_attr($key); ?>][price]" value="<?php echo esc_attr($package['price']); ?>" />
                        </div>

                        <div class="membership-package-admin-field">
                            <label><strong><?php esc_html_e('Discounted Price', 'golf-simulator-theme'); ?></strong></label>
                            <input type="text" name="golf_simulator_membership_defaults[<?php echo esc_attr($key); ?>][discount_price]" value="<?php echo esc_attr($package['discount_price']); ?>" />
                        </div>

                        <div class="membership-package-admin-field" style="grid-column: 1 / -1;">
                            <label><strong><?php esc_html_e('Package Features', 'golf-simulator-theme'); ?></strong></label>
                            <textarea name="golf_simulator_membership_defaults[<?php echo esc_attr($key); ?>][features]" rows="6"><?php echo esc_textarea(is_array($package['features']) ? implode("\n", $package['features']) : $package['features']); ?></textarea>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="membership-package-admin-actions">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Save default package settings', 'golf-simulator-theme'); ?></button>
                </div>
            </div>
        </form>
    </div>
    <?php
}

function golf_simulator_theme_register_membership_package_settings_page() {
    add_menu_page(
        __('Membership Packages', 'golf-simulator-theme'),
        __('Membership Packages', 'golf-simulator-theme'),
        'manage_options',
        'membership-packages',
        'golf_simulator_theme_membership_package_settings_page',
        'dashicons-groups',
        26
    );

    add_submenu_page(
        'membership-packages',
        __('Settings', 'golf-simulator-theme'),
        __('Settings', 'golf-simulator-theme'),
        'manage_options',
        'membership-package-settings',
        'golf_simulator_theme_membership_package_settings_page'
    );

    add_submenu_page(
        'membership-packages',
        __('Manage Packages', 'golf-simulator-theme'),
        __('Manage Packages', 'golf-simulator-theme'),
        'manage_options',
        'edit.php?post_type=membership_package'
    );

    add_submenu_page(
        'membership-packages',
        __('Members', 'golf-simulator-theme'),
        __('Members', 'golf-simulator-theme'),
        'manage_options',
        'membership-customers',
        'golf_simulator_theme_render_membership_admin_page'
    );
}
add_action('admin_menu', 'golf_simulator_theme_register_membership_package_settings_page');

function golf_simulator_theme_render_membership_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;

    $table_name = $wpdb->prefix . 'user_memberships';
    $members = $wpdb->get_results("SELECT * FROM $table_name ORDER BY updated_at DESC LIMIT 200");
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Membership Members', 'golf-simulator-theme'); ?></h1>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('User', 'golf-simulator-theme'); ?></th>
                    <th><?php esc_html_e('Package', 'golf-simulator-theme'); ?></th>
                    <th><?php esc_html_e('Status', 'golf-simulator-theme'); ?></th>
                    <th><?php esc_html_e('Payment', 'golf-simulator-theme'); ?></th>
                    <th><?php esc_html_e('Paid Date', 'golf-simulator-theme'); ?></th>
                    <th><?php esc_html_e('Next Billing', 'golf-simulator-theme'); ?></th>
                    <th><?php esc_html_e('Cancelled', 'golf-simulator-theme'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($members)) : ?>
                    <?php foreach ($members as $member) : ?>
                        <?php $user = get_userdata((int) $member->user_id); ?>
                        <tr>
                            <td><?php echo $user ? esc_html($user->display_name . ' (' . $user->user_email . ')') : esc_html('#' . $member->user_id); ?></td>
                            <td><?php echo esc_html($member->package_name ?: '—'); ?></td>
                            <td><?php echo esc_html(ucfirst($member->status)); ?></td>
                            <td><?php echo esc_html(ucfirst($member->payment_status)); ?></td>
                            <td><?php echo esc_html($member->payment_date ?: '—'); ?></td>
                            <td><?php echo esc_html($member->next_billing_date ?: '—'); ?></td>
                            <td><?php echo esc_html($member->cancel_date ?: '—'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="7"><?php esc_html_e('No members yet.', 'golf-simulator-theme'); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function golf_simulator_theme_get_default_membership_package_templates() {
    return array(
        'PAR' => array(
            'title' => 'PAR',
            'price' => '250',
            'discount_price' => '200',
            'billing' => '/Month',
            'featured' => false,
            'features' => array(
                'Valid Hours: Mon–Fri | 6:00 AM–5:00 PM',
                '1 Hour Per Day During Off-Peak Hours',
                'Bring Up To 3 Guests Free',
                'Free Standard Club Rentals',
                'Premium Club Rentals for $25',
            ),
        ),
        'BIRDIE' => array(
            'title' => 'BIRDIE',
            'price' => '399',
            'discount_price' => '325',
            'billing' => '/Month',
            'featured' => true,
            'features' => array(
                'Valid Hours: Anytime',
                '1 Hour Per Day Anytime',
                'Bring Up To 3 Guests Free',
                'Free Standard Club Rentals',
                'Free Premium Club Rentals',
                'Reservations Available 14 Days in Advance',
                '10% Off Merchandise Purchases',
            ),
        ),
        'ALBATROSS' => array(
            'title' => 'ALBATROSS',
            'price' => '499',
            'discount_price' => '399',
            'billing' => '/Month',
            'featured' => false,
            'features' => array(
                'Valid Hours: Anytime',
                '2 Hours Per Day Anytime',
                'Bring Up To 3 Guests Free',
                'Free Standard Club Rentals',
                'Free Premium Club Rentals',
                'Reservations Available 30 Days in Advance',
                '20% Off Merchandise Purchases',
                'Annual Membership: $5,000 — Save $1,000',
            ),
        ),
    );
}

function golf_simulator_theme_get_default_membership_packages() {
    $saved_defaults = get_option('golf_simulator_membership_package_defaults', array());
    $defaults = golf_simulator_theme_get_default_membership_package_templates();

    if (empty($saved_defaults) || !is_array($saved_defaults)) {
        return $defaults;
    }

    foreach ($defaults as $key => $package) {
        if (!isset($saved_defaults[$key]) || !is_array($saved_defaults[$key])) {
            continue;
        }

        $defaults[$key] = array_merge($package, $saved_defaults[$key]);

        if (!empty($saved_defaults[$key]['features']) && is_string($saved_defaults[$key]['features'])) {
            $defaults[$key]['features'] = preg_split('/\r\n|\r|\n/', trim($saved_defaults[$key]['features']));
            $defaults[$key]['features'] = array_values(array_filter(array_map('trim', $defaults[$key]['features'])));
        }
    }

    return $defaults;
}

function golf_simulator_theme_validate_membership_prices($regular_price, $discount_price) {
    $regular_price = trim((string) $regular_price);
    $discount_price = trim((string) $discount_price);

    if ('' === $discount_price) {
        return true;
    }

    if (!is_numeric($regular_price) || !is_numeric($discount_price) || (float) $discount_price >= (float) $regular_price) {
        return new WP_Error(
            'invalid_membership_discount',
            __('The discounted price must be less than the regular price.', 'golf-simulator-theme')
        );
    }

    return true;
}

function golf_simulator_theme_ensure_default_membership_packages() {
    if (!post_type_exists('membership_package')) {
        return;
    }

    $defaults = golf_simulator_theme_get_default_membership_packages();

    $existing_posts = get_posts(array(
        'post_type' => 'membership_package',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ));

    $known_titles = array_keys($defaults);

    foreach ($existing_posts as $existing_post) {
        if (!in_array($existing_post->post_title, $known_titles, true)) {
            wp_delete_post($existing_post->ID, true);
        }
    }

    foreach ($defaults as $package) {
        $package_query = get_posts(array(
            'post_type' => 'membership_package',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'title' => $package['title'],
            'suppress_filters' => false,
        ));

        $post_id = !empty($package_query) ? $package_query[0]->ID : wp_insert_post(array(
            'post_type' => 'membership_package',
            'post_status' => 'publish',
            'post_title' => $package['title'],
            'post_content' => '',
        ));

        if (!is_wp_error($post_id) && $post_id) {
            $existing_price = get_post_meta($post_id, '_membership_price', true);
            $existing_discount = get_post_meta($post_id, '_membership_discount_price', true);
            $existing_billing = get_post_meta($post_id, '_membership_billing', true);
            $existing_featured = get_post_meta($post_id, '_membership_featured', true);
            $existing_features = get_post_meta($post_id, '_membership_features', true);

            if (!metadata_exists('post', $post_id, '_membership_price')) {
                update_post_meta($post_id, '_membership_price', $package['price']);
            }

            if (!metadata_exists('post', $post_id, '_membership_discount_price')) {
                update_post_meta($post_id, '_membership_discount_price', $package['discount_price']);
            }

            if (!metadata_exists('post', $post_id, '_membership_billing')) {
                update_post_meta($post_id, '_membership_billing', $package['billing']);
            }

            if (!metadata_exists('post', $post_id, '_membership_featured')) {
                update_post_meta($post_id, '_membership_featured', $package['featured'] ? '1' : '0');
            }

            if (!metadata_exists('post', $post_id, '_membership_features')) {
                update_post_meta($post_id, '_membership_features', implode("\n", $package['features']));
            }
        }
    }
}
add_action('init', 'golf_simulator_theme_ensure_default_membership_packages');

function golf_simulator_theme_membership_price_markup($post_id) {
    $title = get_the_title($post_id);
    $regular_price = get_post_meta($post_id, '_membership_price', true);
    $discount_price = get_post_meta($post_id, '_membership_discount_price', true);
    $billing = get_post_meta($post_id, '_membership_billing', true) ?: '/Month';

    $defaults = golf_simulator_theme_get_default_membership_packages();
    $default_package = $defaults[$title] ?? array();

    $regular_price_value = !empty($regular_price) ? '$' . esc_html($regular_price) : (isset($default_package['price']) ? '$' . esc_html($default_package['price']) : '$0');
    $default_discount = isset($default_package['discount_price']) ? trim((string) $default_package['discount_price']) : null;

    if ('' === $default_discount) {
        $discount_price = '';
    }

    $discount_price_value = !empty($discount_price) ? '$' . esc_html($discount_price) : (metadata_exists('post', $post_id, '_membership_discount_price') ? '' : (!empty($default_discount) ? '$' . esc_html($default_discount) : ''));

    if (empty($discount_price_value)) {
        return '<div class="membership-price"><span class="membership-regular-price">' . esc_html($regular_price_value) . '<span>' . esc_html($billing) . '</span></span></div>';
    }

    return '<div class="membership-price"><span class="membership-regular-price"><s>' . esc_html($regular_price_value) . '</s><span>' . esc_html($billing) . '</span></span><span class="membership-discount-price">Early signup ' . esc_html($discount_price_value) . '</span></div>';
}

function golf_simulator_theme_membership_package_meta_box() {
    add_meta_box(
        'golf_simulator_membership_package_meta',
        __('Membership Package Details', 'golf-simulator-theme'),
        'golf_simulator_theme_render_membership_package_meta_box',
        'membership_package',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'golf_simulator_theme_membership_package_meta_box');

function golf_simulator_theme_render_membership_package_meta_box($post) {
    wp_nonce_field('golf_simulator_membership_package_meta', 'golf_simulator_membership_package_nonce');

    $price = get_post_meta($post->ID, '_membership_price', true);
    $discount_price = get_post_meta($post->ID, '_membership_discount_price', true);
    $billing = get_post_meta($post->ID, '_membership_billing', true);
    $featured = get_post_meta($post->ID, '_membership_featured', true);
    $features = get_post_meta($post->ID, '_membership_features', true);
    $defaults = golf_simulator_theme_get_default_membership_packages();
    $default_package = $defaults[get_the_title($post->ID)] ?? array();
    $has_discount_meta = metadata_exists('post', $post->ID, '_membership_discount_price');
    ?>
    <div class="membership-package-admin-box">
        <div class="membership-package-admin-grid">
            <div class="membership-package-admin-field">
                <label for="post_title"><strong><?php esc_html_e('Membership Tier', 'golf-simulator-theme'); ?></strong></label>
                <input type="text" id="post_title" name="post_title" value="<?php echo esc_attr(get_the_title($post->ID)); ?>" placeholder="PAR" />
            </div>
            <div class="membership-package-admin-field">
                <label for="membership_price"><strong><?php esc_html_e('Regular Price', 'golf-simulator-theme'); ?></strong></label>
                <input type="text" id="membership_price" name="membership_price" value="<?php echo esc_attr($price !== '' ? $price : ($default_package['price'] ?? '')); ?>" placeholder="250" />
            </div>
            <div class="membership-package-admin-field">
                <label for="membership_discount_price"><strong><?php esc_html_e('Discounted Price', 'golf-simulator-theme'); ?></strong></label>
                <input type="text" id="membership_discount_price" name="membership_discount_price" value="<?php echo esc_attr($has_discount_meta ? $discount_price : ($default_package['discount_price'] ?? '')); ?>" placeholder="200" />
            </div>
        </div>
        <div class="membership-package-admin-field">
            <label for="membership_features"><strong><?php esc_html_e('Package Features', 'golf-simulator-theme'); ?></strong></label>
            <textarea id="membership_features" name="membership_features" rows="8" placeholder="Add one feature per line"><?php echo esc_textarea($features !== '' ? $features : implode("\n", $default_package['features'] ?? array())); ?></textarea>
        </div>
        <div class="membership-package-admin-actions">
            <button type="submit" name="reset_membership_defaults" value="1" class="button button-secondary">
                <?php esc_html_e('Restore default package values', 'golf-simulator-theme'); ?>
            </button>
        </div>
    </div>
    <?php
}

function golf_simulator_theme_save_membership_package_meta($post_id) {
    if (!isset($_POST['golf_simulator_membership_package_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['golf_simulator_membership_package_nonce'])), 'golf_simulator_membership_package_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['reset_membership_defaults']) && '1' === $_POST['reset_membership_defaults']) {
        $defaults = golf_simulator_theme_get_default_membership_packages();
        $title = get_the_title($post_id);

        if (isset($defaults[$title])) {
            $package = $defaults[$title];
            update_post_meta($post_id, '_membership_price', $package['price']);
            update_post_meta($post_id, '_membership_discount_price', $package['discount_price']);
            update_post_meta($post_id, '_membership_billing', $package['billing']);
            update_post_meta($post_id, '_membership_featured', $package['featured'] ? '1' : '0');
            update_post_meta($post_id, '_membership_features', implode("\n", $package['features']));
        }
        return;
    }

    $regular_price = isset($_POST['membership_price']) ? sanitize_text_field(wp_unslash($_POST['membership_price'])) : '';
    $discount_price = isset($_POST['membership_discount_price']) ? sanitize_text_field(wp_unslash($_POST['membership_discount_price'])) : '';
    $price_error = golf_simulator_theme_validate_membership_prices($regular_price, $discount_price);

    if (is_wp_error($price_error)) {
        wp_die(esc_html($price_error->get_error_message()), esc_html__('Invalid membership price', 'golf-simulator-theme'), array('back_link' => true));
    }

    if (isset($_POST['membership_price'])) {
        update_post_meta($post_id, '_membership_price', $regular_price);
    }

    if (isset($_POST['membership_discount_price'])) {
        update_post_meta($post_id, '_membership_discount_price', $discount_price);
    }

    if (isset($_POST['membership_billing'])) {
        update_post_meta($post_id, '_membership_billing', sanitize_text_field(wp_unslash($_POST['membership_billing'])));
    }

    $featured = isset($_POST['membership_featured']) ? '1' : '0';
    update_post_meta($post_id, '_membership_featured', $featured);

    if (isset($_POST['membership_features'])) {
        update_post_meta($post_id, '_membership_features', wp_unslash($_POST['membership_features']));
    }
}
add_action('save_post_membership_package', 'golf_simulator_theme_save_membership_package_meta');

function golf_simulator_theme_get_membership_package_data($post_id) {
    $title = get_the_title($post_id);
    $defaults = golf_simulator_theme_get_default_membership_packages();
    $default_package = $defaults[$title] ?? array();

    $price = get_post_meta($post_id, '_membership_price', true);
    $discount_price = get_post_meta($post_id, '_membership_discount_price', true);
    $billing = get_post_meta($post_id, '_membership_billing', true);
    $featured = (bool) get_post_meta($post_id, '_membership_featured', true);
    $features = get_post_meta($post_id, '_membership_features', true);
    $has_discount_meta = metadata_exists('post', $post_id, '_membership_discount_price');

    if (empty($features)) {
        $features = $default_package['features'] ?? array();
    } else {
        $features = preg_split('/\r\n|\r|\n/', (string) $features);
        $features = array_values(array_filter(array_map('trim', $features)));
    }

    return array(
        'title' => $title,
        'price' => metadata_exists('post', $post_id, '_membership_price') ? $price : ($default_package['price'] ?? '0'),
        'discount_price' => $has_discount_meta ? $discount_price : ($default_package['discount_price'] ?? ''),
        'billing' => $billing !== '' && $billing !== false ? $billing : ($default_package['billing'] ?? '/mo'),
        'featured' => $featured || ($default_package['featured'] ?? false),
        'features' => $features,
        'link' => add_query_arg('package', rawurlencode($title), home_url('/membership')),
    );
}

function golf_simulator_theme_render_membership_manager() {
    if (!is_user_logged_in()) {
        return '<p>' . esc_html__('Please log in to manage your membership.', 'golf-simulator-theme') . '</p>';
    }

    $current_user = wp_get_current_user();
    $membership = golf_simulator_theme_get_user_membership_record($current_user->ID);
    $default_packages = golf_simulator_theme_get_default_membership_packages();
    $selected_package = $membership ? $membership->package_name : 'PAR';

    ob_start();
    ?>
    <div class="membership-management-panel">
        <h3><?php esc_html_e('Your Membership', 'golf-simulator-theme'); ?></h3>

        <?php if ($membership) : ?>
            <p>
                <strong><?php esc_html_e('Current package:', 'golf-simulator-theme'); ?></strong>
                <?php echo esc_html($membership->package_name); ?>
                <br>
                <strong><?php esc_html_e('Status:', 'golf-simulator-theme'); ?></strong>
                <?php echo esc_html(ucfirst($membership->status)); ?>
                <br>
                <strong><?php esc_html_e('Payment:', 'golf-simulator-theme'); ?></strong>
                <?php echo esc_html(ucfirst($membership->payment_status)); ?>
                <?php if (!empty($membership->payment_date)) : ?>
                    <br>
                    <strong><?php esc_html_e('Paid on:', 'golf-simulator-theme'); ?></strong>
                    <?php echo esc_html(mysql2date(get_option('date_format'), $membership->payment_date)); ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="golf_simulator_membership_manage">
            <?php wp_nonce_field('golf_simulator_membership_manage', 'golf_simulator_membership_nonce'); ?>

            <div class="membership-package-admin-field">
                <label for="membership_package_select"><strong><?php esc_html_e('Choose Membership', 'golf-simulator-theme'); ?></strong></label>
                <select id="membership_package_select" name="membership_package">
                    <?php foreach ($default_packages as $key => $package) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($selected_package, $key); ?>>
                            <?php echo esc_html($package['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="membership-package-admin-field">
                <label for="membership_action_select"><strong><?php esc_html_e('Action', 'golf-simulator-theme'); ?></strong></label>
                <select id="membership_action_select" name="membership_action">
                    <option value="upgrade"><?php esc_html_e('Upgrade', 'golf-simulator-theme'); ?></option>
                    <option value="downgrade"><?php esc_html_e('Downgrade', 'golf-simulator-theme'); ?></option>
                    <option value="pause"><?php esc_html_e('Pause', 'golf-simulator-theme'); ?></option>
                    <option value="cancel"><?php esc_html_e('Cancel', 'golf-simulator-theme'); ?></option>
                </select>
            </div>

            <div class="membership-package-admin-actions">
                <button type="submit" class="button button-primary"><?php esc_html_e('Update Membership', 'golf-simulator-theme'); ?></button>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('golf_simulator_membership_manager', 'golf_simulator_theme_render_membership_manager');

function golf_simulator_theme_process_membership_management() {
    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/membership'));
        exit;
    }

    if (!isset($_POST['golf_simulator_membership_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['golf_simulator_membership_nonce'])), 'golf_simulator_membership_manage')) {
        wp_die('Security check failed.');
    }

    $user_id = get_current_user_id();
    $package_name = sanitize_text_field(wp_unslash($_POST['membership_package'] ?? 'PAR'));
    $action = sanitize_text_field(wp_unslash($_POST['membership_action'] ?? 'upgrade'));

    $package_data = golf_simulator_theme_get_default_membership_packages();
    $package = $package_data[$package_name] ?? $package_data['PAR'];
    $current_membership = golf_simulator_theme_get_user_membership_record($user_id);
    $prorated_upgrade_amount = 'upgrade' === $action ? golf_simulator_theme_calculate_prorated_upgrade_amount($current_membership, $package) : 0.00;
    $membership_start_date = $current_membership && !empty($current_membership->start_date) ? $current_membership->start_date : current_time('mysql');

    if ('upgrade' === $action && $prorated_upgrade_amount > 0) {
        golf_simulator_theme_add_membership_to_cart_and_redirect($package_name, true, $prorated_upgrade_amount);
        return;
    }

    if ('cancel' === $action) {
        $status = 'cancelled';
        $payment_status = 'cancelled';
        $cancel_date = current_time('mysql');
        $next_billing_date = '';
    } elseif ('pause' === $action) {
        $status = 'paused';
        $payment_status = 'pending';
        $cancel_date = '';
        $next_billing_date = date('Y-m-d H:i:s', strtotime('+1 month'));
    } else {
        $status = 'active';
        $payment_status = 'paid';
        $cancel_date = '';
        $next_billing_date = date('Y-m-d H:i:s', strtotime('+1 month'));
    }

    if ('downgrade' === $action) {
        $status = 'downgraded';
    }

    if ('upgrade' === $action) {
        $status = 'upgraded';
    }

    golf_simulator_theme_save_user_membership_record(array(
        'user_id' => $user_id,
        'package_name' => $package_name,
        'package_slug' => $package_name,
        'price' => $package['price'] ?? '',
        'discount_price' => $package['discount_price'] ?? '',
        'payment_status' => $payment_status,
        'status' => $status,
        'payment_date' => current_time('mysql'),
        'next_billing_date' => $next_billing_date,
        'cancel_date' => $cancel_date,
        'start_date' => $membership_start_date,
    ));

    global $wpdb;
    $history_table = $wpdb->prefix . 'membership_history';
    $wpdb->insert(
        $history_table,
        array(
            'user_id' => $user_id,
            'action' => $action,
            'previous_package' => $current_membership ? $current_membership->package_name : '',
            'new_package' => $package_name,
            'amount' => '0.00',
            'created_at' => current_time('mysql'),
        ),
        array('%d', '%s', '%s', '%s', '%s', '%s')
    );

    $action_labels = array(
        'downgrade' => 'Membership downgrade confirmed',
        'pause' => 'Membership paused',
        'cancel' => 'Membership cancellation confirmed',
        'upgrade' => 'Membership upgrade confirmed',
    );
    $action_subject = $action_labels[$action] ?? 'Membership updated';
    golf_simulator_theme_send_membership_confirmation($user_id, $package_name, 0, $payment_status, $action_subject);

    wp_safe_redirect(add_query_arg(array('membership_updated' => '1'), home_url('/my-account/')));
    exit;
}
add_action('admin_post_golf_simulator_membership_manage', 'golf_simulator_theme_process_membership_management');

function golf_simulator_theme_send_membership_confirmation($user_id, $package_name, $amount, $payment_status, $subject = 'Membership signup received') {
    $user = get_userdata($user_id);
    if (!$user || !is_email($user->user_email)) {
        return false;
    }

    $account_url = home_url('/my-account/');
    $body = '<p style="margin:0 0 16px;color:#4b5563;font-size:15px;line-height:1.6;">Hi ' . esc_html($user->display_name) . ', your Tee Time Nexus membership update has been received.</p>'
        . '<table style="width:100%;border-collapse:collapse;margin:0 0 22px;font-size:15px;color:#4b5563;">'
        . '<tr><td style="padding:6px 0;"><strong>Membership</strong></td><td style="padding:6px 0;text-align:right;">' . esc_html($package_name) . '</td></tr>'
        . '<tr><td style="padding:6px 0;"><strong>Amount</strong></td><td style="padding:6px 0;text-align:right;">$' . esc_html(number_format((float) $amount, 2)) . '</td></tr>'
        . '<tr><td style="padding:6px 0;"><strong>Payment status</strong></td><td style="padding:6px 0;text-align:right;">' . esc_html(ucfirst($payment_status)) . '</td></tr>'
        . '</table>';
    $message = golf_simulator_theme_render_email_template(
        'Membership Update',
        'Your Membership Has Been Updated',
        $body,
        'View My Membership',
        $account_url
    );

    return wp_mail($user->user_email, $subject, $message, golf_simulator_theme_get_email_headers());
}

function golf_simulator_theme_process_membership_signup() {
    if (!isset($_POST['golf_simulator_membership_signup_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['golf_simulator_membership_signup_nonce'])), 'golf_simulator_membership_signup')) {
        wp_die(__('Security check failed. Please refresh the page and try again.', 'golf-simulator-theme'));
    }

    $package_name = sanitize_text_field(wp_unslash($_POST['package_name'] ?? ''));
    if ($package_name) {
        golf_simulator_theme_add_membership_to_cart_and_redirect($package_name);
    }

    wp_safe_redirect(home_url('/membership/'));
    exit;
}
add_action('admin_post_golf_simulator_membership_signup', 'golf_simulator_theme_process_membership_signup');
add_action('admin_post_nopriv_golf_simulator_membership_signup', 'golf_simulator_theme_process_membership_signup');

function golf_simulator_theme_render_membership_signup($package_name) {
    $packages = golf_simulator_theme_get_default_membership_packages();
    $package = $packages[$package_name] ?? null;

    if (!$package) {
        return '<p>' . esc_html__('That membership package is not available. Please choose another package.', 'golf-simulator-theme') . '</p>';
    }

    $price = !empty($package['discount_price']) ? $package['discount_price'] : $package['price'];
    ob_start();
    ?>
    <article class="entry-content membership-signup-card">
        <div class="kicker">Membership Tier</div>
        <h1>Join <?php echo esc_html($package['title']); ?></h1>
        <p class="membership-signup-summary">Selected Plan: <strong><?php echo esc_html($package['title']); ?></strong> · <strong>$<?php echo esc_html($price); ?></strong><?php echo esc_html($package['billing']); ?></p>

        <?php if (!empty($package['features'])) : ?>
            <ul style="margin: 0 0 24px; padding-left: 20px; line-height: 1.8;">
                <?php foreach ((array) $package['features'] as $feature) : ?>
                    <li><?php echo esc_html($feature); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="golf_simulator_membership_signup">
            <input type="hidden" name="package_name" value="<?php echo esc_attr($package_name); ?>">
            <?php wp_nonce_field('golf_simulator_membership_signup', 'golf_simulator_membership_signup_nonce'); ?>
            <p style="margin-bottom: 20px;">You will complete your secure payment and account setup on the next screen.</p>
            <button class="btn btn-primary" type="submit">Proceed to Secure Checkout ($<?php echo esc_html($price); ?>)</button>
        </form>
    </article>
    <?php
    return ob_get_clean();
}

function golf_simulator_theme_render_membership_packages() {
    $packages = get_posts(array(
        'post_type' => 'membership_package',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'menu_order',
        'order' => 'ASC',
    ));

    if (empty($packages)) {
        golf_simulator_theme_ensure_default_membership_packages();
        $packages = get_posts(array(
            'post_type' => 'membership_package',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ));
    }

    if (empty($packages)) {
        return '<p>No membership packages available yet.</p>';
    }

    ob_start();
    echo '<div class="membership-grid">';

    foreach ($packages as $package) {
        $data = golf_simulator_theme_get_membership_package_data($package->ID);
        $card_class = $data['featured'] ? 'membership-card featured' : 'membership-card';
        $button_class = 'btn btn-primary';

        echo '<article class="' . esc_attr($card_class) . '">';
        echo '<div class="tier-badge">' . esc_html($data['title']) . '</div>';
        echo '<h3>' . esc_html($data['title']) . '</h3>';
        echo golf_simulator_theme_membership_price_markup($package->ID);

        if (!empty($data['features'])) {
            echo '<ul>';
            foreach ($data['features'] as $feature) {
                echo '<li>' . esc_html($feature) . '</li>';
            }
            echo '</ul>';
        }

        echo '<a href="' . esc_url($data['link']) . '" class="' . esc_attr($button_class) . '">' . esc_html__('Join', 'golf-simulator-theme') . ' ' . esc_html($data['title']) . '</a>';
        echo '</article>';
    }

    echo '</div>';
    return ob_get_clean();
}
add_shortcode('golf_simulator_membership_packages', 'golf_simulator_theme_render_membership_packages');

function golf_simulator_theme_ensure_membership_page() {
    $page_slug = 'membership';
    $page = get_page_by_path($page_slug);

    if ($page) {
        return;
    }

    $page_id = wp_insert_post(array(
        'post_title' => 'Membership',
        'post_name' => $page_slug,
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_content' => '[golf_simulator_membership_packages]',
    ));

    if ($page_id && !is_wp_error($page_id)) {
        update_post_meta($page_id, '_wp_page_template', 'page-membership.php');
    }
}
add_action('init', 'golf_simulator_theme_ensure_membership_page');
