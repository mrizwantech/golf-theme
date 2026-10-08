<?php

if (!defined('ABSPATH')) {
    exit;
}

final class TTN_Competitions_Registrations {
    const CART_KEY = 'ttn_competition_registration';
    const HOLD_HOOK = 'ttn_competitions_expire_registrations';

    private static $allow_product_add = false;

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'ttn_competition_registrations';
    }

    public static function register_hooks() {
        add_filter('cron_schedules', array(__CLASS__, 'add_cron_schedule'));
        add_filter('wp_privacy_personal_data_exporters', array(__CLASS__, 'register_privacy_exporter'));
        add_filter('wp_privacy_personal_data_erasers', array(__CLASS__, 'register_privacy_eraser'));
        add_action('init', array(__CLASS__, 'schedule_expiry'));
        add_action('ttn_competitions_expire_registrations', array(__CLASS__, 'expire_pending_registrations'));
        add_action('admin_post_ttn_competition_register', array(__CLASS__, 'handle_registration'));
        add_action('admin_post_nopriv_ttn_competition_register', array(__CLASS__, 'require_login'));
        add_action('admin_post_ttn_competition_resume', array(__CLASS__, 'resume_registration_payment'));
        add_action('admin_post_nopriv_ttn_competition_resume', array(__CLASS__, 'require_login'));
        add_filter('woocommerce_is_purchasable', array(__CLASS__, 'allow_cart_product'), 10, 2);
        add_filter('woocommerce_cart_item_is_purchasable', array(__CLASS__, 'allow_session_cart_item'), 10, 4);
        add_action('woocommerce_before_calculate_totals', array(__CLASS__, 'set_cart_item_price'), 30);
        add_action('woocommerce_check_cart_items', array(__CLASS__, 'validate_cart_items'));
        add_filter('woocommerce_add_to_cart_validation', array(__CLASS__, 'validate_cart_add'), 10, 5);
        add_filter('woocommerce_get_item_data', array(__CLASS__, 'render_cart_item_data'), 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', array(__CLASS__, 'store_order_item_data'), 20, 4);
        add_action('woocommerce_checkout_order_processed', array(__CLASS__, 'attach_order_to_registrations'), 20, 1);
        add_action('woocommerce_order_status_processing', array(__CLASS__, 'confirm_paid_order'));
        add_action('woocommerce_order_status_completed', array(__CLASS__, 'confirm_paid_order'));
        add_action('woocommerce_payment_complete', array(__CLASS__, 'confirm_paid_order'));
        add_action('woocommerce_order_status_failed', array(__CLASS__, 'release_order_registrations'));
        add_action('woocommerce_order_status_cancelled', array(__CLASS__, 'release_order_registrations'));
        add_action('woocommerce_order_status_refunded', array(__CLASS__, 'release_refunded_registrations'));
    }

    public static function maybe_upgrade() {
        if (get_option('ttn_competitions_schema_version') !== TTN_COMPETITIONS_SCHEMA_VERSION) {
            self::install_schema();
        }
    }

    public static function install_schema() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            competition_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned NULL,
            status varchar(24) NOT NULL DEFAULT 'pending_payment',
            player_name varchar(190) NOT NULL DEFAULT '',
            player_email varchar(190) NOT NULL DEFAULT '',
            player_phone varchar(40) NOT NULL DEFAULT '',
            handicap varchar(20) NOT NULL DEFAULT '',
            golfzon_username varchar(100) NOT NULL DEFAULT '',
            waiver_accepted_at datetime NULL,
            fee decimal(10,2) NOT NULL DEFAULT 0.00,
            expires_at datetime NULL,
            registered_at datetime NULL,
            confirmation_sent_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY competition_status (competition_id,status),
            KEY competition_user (competition_id,user_id),
            KEY user_status (user_id,status),
            KEY order_id (order_id),
            KEY status_expires (status,expires_at)
        ) $charset_collate ENGINE=InnoDB;";

        $wpdb->last_error = '';
        dbDelta($sql);
        if ($wpdb->last_error) {
            error_log('TTN competitions registration table migration failed: ' . $wpdb->last_error);
            return false;
        }
        update_option('ttn_competitions_schema_version', TTN_COMPETITIONS_SCHEMA_VERSION, false);
        return true;
    }

    public static function add_cron_schedule($schedules) {
        $schedules['ttn_competitions_15_minutes'] = array(
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display' => __('Every 15 minutes', 'tee-time-nexus-competitions'),
        );
        return $schedules;
    }

    public static function register_privacy_exporter($exporters) {
        $exporters['ttn-competition-registrations'] = array(
            'exporter_friendly_name' => __('Tee Time Nexus Competition Registrations', 'tee-time-nexus-competitions'),
            'callback' => array(__CLASS__, 'export_personal_data'),
        );
        return $exporters;
    }

    public static function register_privacy_eraser($erasers) {
        $erasers['ttn-competition-registrations'] = array(
            'eraser_friendly_name' => __('Tee Time Nexus Competition Registrations', 'tee-time-nexus-competitions'),
            'callback' => array(__CLASS__, 'erase_personal_data'),
        );
        return $erasers;
    }

    public static function export_personal_data($email_address, $page = 1) {
        global $wpdb;
        $page = max(1, absint($page));
        $limit = 100;
        $table = self::table_name();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE player_email = %s ORDER BY id ASC LIMIT %d OFFSET %d",
            sanitize_email($email_address),
            $limit,
            ($page - 1) * $limit
        ));
        $data = array();
        foreach ($rows as $row) {
            $data[] = array(
                'group_id' => 'ttn-competition-registration',
                'group_label' => __('Competition Registrations', 'tee-time-nexus-competitions'),
                'item_id' => 'registration-' . (int) $row->id,
                'data' => array(
                    array('name' => __('Competition', 'tee-time-nexus-competitions'), 'value' => get_the_title($row->competition_id)),
                    array('name' => __('Player name', 'tee-time-nexus-competitions'), 'value' => $row->player_name),
                    array('name' => __('Email', 'tee-time-nexus-competitions'), 'value' => $row->player_email),
                    array('name' => __('Phone', 'tee-time-nexus-competitions'), 'value' => $row->player_phone),
                    array('name' => __('Handicap', 'tee-time-nexus-competitions'), 'value' => $row->handicap),
                    array('name' => __('GOLFZON username', 'tee-time-nexus-competitions'), 'value' => $row->golfzon_username),
                    array('name' => __('Waiver accepted at', 'tee-time-nexus-competitions'), 'value' => $row->waiver_accepted_at),
                    array('name' => __('Registration status', 'tee-time-nexus-competitions'), 'value' => $row->status),
                    array('name' => __('Entry fee', 'tee-time-nexus-competitions'), 'value' => $row->fee),
                    array('name' => __('WooCommerce order ID', 'tee-time-nexus-competitions'), 'value' => $row->order_id),
                    array('name' => __('Registration date', 'tee-time-nexus-competitions'), 'value' => $row->created_at),
                ),
            );
        }

        return array(
            'data' => $data,
            'done' => count($rows) < $limit,
        );
    }

    public static function erase_personal_data($email_address, $page = 1) {
        global $wpdb;
        $table = self::table_name();
        $email_address = sanitize_email($email_address);
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE player_email = %s",
            $email_address
        ));
        $updated = $wpdb->update(
            $table,
            array(
                'user_id' => 0,
                'player_name' => __('Anonymized player', 'tee-time-nexus-competitions'),
                'player_email' => '',
                'player_phone' => '',
                'handicap' => '',
                'golfzon_username' => '',
                'waiver_accepted_at' => null,
                'updated_at' => current_time('mysql'),
            ),
            array('player_email' => $email_address),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
            array('%s')
        );

        if ($updated === false) {
            return array(
                'items_removed' => false,
                'items_retained' => $count > 0,
                'messages' => array(__('Competition registration data could not be anonymized. Please try again or contact support.', 'tee-time-nexus-competitions')),
                'done' => true,
            );
        }
        return array(
            'items_removed' => $count > 0,
            'items_retained' => false,
            'messages' => array(),
            'done' => true,
        );
    }

    public static function schedule_expiry() {
        if (!wp_next_scheduled(self::HOLD_HOOK)) {
            wp_schedule_event(time() + 5 * MINUTE_IN_SECONDS, 'ttn_competitions_15_minutes', self::HOLD_HOOK);
        }
    }

    public static function render_registration_box($competition_id) {
        $competition = get_post($competition_id);
        if (!$competition || $competition->post_type !== TTN_Competitions_Post_Type::POST_TYPE || $competition->post_status !== 'publish') {
            return '';
        }

        $fee = get_post_meta($competition_id, '_ttn_entry_fee', true);
        $status_message = self::registration_query_message();
        $html = '<section class="ttn-registration-box" aria-labelledby="ttn-registration-heading">';
        $html .= '<h2 id="ttn-registration-heading">' . esc_html__('Competition Registration', 'tee-time-nexus-competitions') . '</h2>';
        if ($status_message) {
            $html .= $status_message;
        }

        if ($fee === '') {
            $html .= '<p>' . esc_html__('Registration details are not available yet. Please check back or contact Tee Time Nexus.', 'tee-time-nexus-competitions') . '</p></section>';
            return $html;
        }

        if (!is_user_logged_in()) {
            $availability = self::get_availability_error($competition_id);
            if ($availability) {
                $html .= '<p>' . esc_html($availability) . '</p></section>';
                return $html;
            }
            $return_url = get_permalink($competition_id) . '#ttn-registration-heading';
            $login_url = self::login_url($return_url);
            $html .= '<p>' . esc_html__('Sign in to your Tee Time Nexus account to register for this event.', 'tee-time-nexus-competitions') . '</p>';
            $html .= '<a class="ttn-registration-button" href="' . esc_url($login_url) . '">' . esc_html__('Sign in to register', 'tee-time-nexus-competitions') . '</a></section>';
            return $html;
        }

        $existing = self::get_active_registration($competition_id, get_current_user_id());
        if ($existing) {
            if ($existing->status === 'registered') {
                $html .= '<p class="ttn-registration-success">' . esc_html__('You are registered for this competition.', 'tee-time-nexus-competitions') . '</p>';
            } elseif ($existing->order_id && function_exists('wc_get_order')) {
                $order = wc_get_order($existing->order_id);
                if ($order && $order->needs_payment()) {
                    $html .= '<p>' . esc_html__('Your registration is waiting for payment.', 'tee-time-nexus-competitions') . '</p>';
                    $html .= '<a class="ttn-registration-button" href="' . esc_url($order->get_checkout_payment_url()) . '">' . esc_html__('Continue payment', 'tee-time-nexus-competitions') . '</a>';
                } else {
                    $html .= '<p>' . esc_html__('Your registration is being processed. Please check your email for an update.', 'tee-time-nexus-competitions') . '</p>';
                }
            } else {
                if ($existing->expires_at && $existing->expires_at <= current_time('mysql')) {
                    if (self::cancel_registration((int) $existing->id, 'cancelled')) {
                        $html .= '<p class="ttn-registration-error">' . esc_html__('Your previous payment hold expired. You can register again below.', 'tee-time-nexus-competitions') . '</p>';
                        $existing = false;
                    } else {
                        $html .= '<p class="ttn-registration-error">' . esc_html__('Your previous payment hold expired, but its place could not be released. Please contact Tee Time Nexus.', 'tee-time-nexus-competitions') . '</p></section>';
                        return $html;
                    }
                } else {
                    $html .= '<p>' . esc_html__('Your registration is waiting to continue to payment.', 'tee-time-nexus-competitions') . '</p>';
                    $html .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                    $html .= '<input type="hidden" name="action" value="ttn_competition_resume">';
                    $html .= '<input type="hidden" name="registration_id" value="' . esc_attr((string) $existing->id) . '">';
                    $html .= '<input type="hidden" name="competition_id" value="' . esc_attr((string) $competition_id) . '">';
                    $html .= wp_nonce_field('ttn_competition_resume_' . $existing->id, 'ttn_competition_resume_nonce', true, false);
                    $html .= '<button class="ttn-registration-button" type="submit">' . esc_html__('Continue to payment', 'tee-time-nexus-competitions') . '</button>';
                    $html .= '</form>';
                }
            }
            if ($existing) {
                $html .= '</section>';
                return $html;
            }
        }

        $availability = self::get_availability_error($competition_id);
        if ($availability) {
            $html .= '<p>' . esc_html($availability) . '</p></section>';
            return $html;
        }

        $user = wp_get_current_user();
        if (!$user->exists() || !is_email($user->user_email)) {
            $html .= '<p>' . esc_html__('Add a valid email address to your account before registering.', 'tee-time-nexus-competitions') . '</p></section>';
            return $html;
        }

        $amount = (float) $fee;
        $html .= '<p class="ttn-registration-price">' . esc_html($amount > 0
            ? sprintf(__('Entry fee: %s', 'tee-time-nexus-competitions'), function_exists('wc_price') ? wp_strip_all_tags(wc_price($amount)) : '$' . number_format($amount, 2))
            : __('Free entry', 'tee-time-nexus-competitions')) . '</p>';
        if ($amount > 0 && !self::woocommerce_available()) {
            $html .= '<p>' . esc_html__('Online registration is temporarily unavailable. Please contact Tee Time Nexus.', 'tee-time-nexus-competitions') . '</p></section>';
            return $html;
        }

        $html .= '<form class="ttn-registration-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        $html .= '<input type="hidden" name="action" value="ttn_competition_register">';
        $html .= '<input type="hidden" name="competition_id" value="' . esc_attr((string) $competition_id) . '">';
        $html .= wp_nonce_field('ttn_competition_register_' . $competition_id, 'ttn_competition_register_nonce', true, false);
        $html .= '<p><strong>' . esc_html__('Player', 'tee-time-nexus-competitions') . '</strong><br>' . esc_html($user->display_name) . '</p>';
        $html .= '<p><strong>' . esc_html__('Account email', 'tee-time-nexus-competitions') . '</strong><br>' . esc_html($user->user_email) . '</p>';
        $html .= '<p><label for="ttn_registration_phone">' . esc_html__('Phone number', 'tee-time-nexus-competitions') . '</label><input type="tel" id="ttn_registration_phone" name="player_phone" maxlength="40" autocomplete="tel"></p>';
        $html .= '<p><label for="ttn_registration_handicap">' . esc_html__('Golf handicap (optional)', 'tee-time-nexus-competitions') . '</label><input type="text" id="ttn_registration_handicap" name="handicap" maxlength="20"></p>';
        $html .= '<p><label for="ttn_registration_golfzon">' . esc_html__('GOLFZON username (optional)', 'tee-time-nexus-competitions') . '</label><input type="text" id="ttn_registration_golfzon" name="golfzon_username" maxlength="100"></p>';
        $html .= '<p class="ttn-registration-consent"><label><input required type="checkbox" name="waiver_accepted" value="1"> ' . esc_html__('I have read and agree to the event description, rules, and waiver information above.', 'tee-time-nexus-competitions') . '</label></p>';
        $html .= '<button class="ttn-registration-button" type="submit">' . esc_html($amount > 0 ? __('Continue to secure payment', 'tee-time-nexus-competitions') : __('Register for free', 'tee-time-nexus-competitions')) . '</button>';
        $html .= '</form></section>';
        return $html;
    }

    public static function handle_registration() {
        if (!is_user_logged_in()) {
            self::require_login();
        }

        $competition_id = isset($_POST['competition_id']) ? absint($_POST['competition_id']) : 0;
        $nonce = isset($_POST['ttn_competition_register_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['ttn_competition_register_nonce']))
            : '';
        if (!$competition_id || !wp_verify_nonce($nonce, 'ttn_competition_register_' . $competition_id)) {
            wp_die(esc_html__('This registration form expired. Return to the competition page and try again.', 'tee-time-nexus-competitions'), '', array('response' => 403));
        }
        if (empty($_POST['waiver_accepted'])) {
            self::redirect_with_message($competition_id, 'rules');
        }

        $user = wp_get_current_user();
        if (!$user->exists() || !is_email($user->user_email)) {
            self::redirect_with_message($competition_id, 'account');
        }

        $data = array(
            'competition_id' => $competition_id,
            'user_id' => $user->ID,
            'player_name' => sanitize_text_field($user->display_name),
            'player_email' => sanitize_email($user->user_email),
            'player_phone' => isset($_POST['player_phone']) ? sanitize_text_field(wp_unslash($_POST['player_phone'])) : '',
            'handicap' => isset($_POST['handicap']) ? sanitize_text_field(wp_unslash($_POST['handicap'])) : '',
            'golfzon_username' => isset($_POST['golfzon_username']) ? sanitize_text_field(wp_unslash($_POST['golfzon_username'])) : '',
            'waiver_accepted_at' => current_time('mysql'),
        );

        $fee = get_post_meta($competition_id, '_ttn_entry_fee', true);
        if ($fee === '' || !is_numeric($fee) || (float) $fee < 0) {
            self::redirect_with_message($competition_id, 'unavailable');
        }
        if ((float) $fee > 0 && !self::woocommerce_available()) {
            self::redirect_with_message($competition_id, 'checkout');
        }

        $registration_id = self::reserve_registration($data, (float) $fee);
        if (is_wp_error($registration_id)) {
            $code = $registration_id->get_error_code();
            self::redirect_with_message($competition_id, $code);
        }

        if ((float) $fee === 0.0) {
            if (!self::confirm_free_registration($registration_id)) {
                self::cancel_registration($registration_id, 'cancelled');
                self::redirect_with_message($competition_id, 'registration');
            }
            wp_safe_redirect(add_query_arg('registration', 'confirmed', get_permalink($competition_id)) . '#ttn-registration-heading');
            exit;
        }

        $checkout = self::prepare_payment_cart($registration_id, $competition_id, (float) $fee);
        if (is_wp_error($checkout)) {
            self::cancel_registration($registration_id, 'cancelled');
            self::redirect_with_message($competition_id, 'checkout');
        }

        wp_safe_redirect(wc_get_checkout_url());
        exit;
    }

    public static function resume_registration_payment() {
        if (!is_user_logged_in()) {
            self::require_login();
        }

        $registration_id = isset($_POST['registration_id']) ? absint($_POST['registration_id']) : 0;
        $competition_id = isset($_POST['competition_id']) ? absint($_POST['competition_id']) : 0;
        $nonce = isset($_POST['ttn_competition_resume_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['ttn_competition_resume_nonce']))
            : '';
        if (
            !$registration_id
            || !$competition_id
            || !wp_verify_nonce($nonce, 'ttn_competition_resume_' . $registration_id)
        ) {
            wp_die(esc_html__('This payment link expired. Return to the competition page and try again.', 'tee-time-nexus-competitions'), '', array('response' => 403));
        }

        $registration = self::get_registration($registration_id);
        if (
            !$registration
            || (int) $registration->competition_id !== $competition_id
            || (int) $registration->user_id !== get_current_user_id()
            || $registration->status !== 'pending_payment'
        ) {
            self::redirect_with_message($competition_id, 'registration');
        }
        if ($registration->expires_at && $registration->expires_at <= current_time('mysql')) {
            self::cancel_registration($registration_id, 'cancelled');
            self::redirect_with_message($competition_id, 'expired');
        }
        if (!self::woocommerce_available()) {
            self::redirect_with_message($competition_id, 'checkout');
        }
        if ($registration->order_id) {
            $order = wc_get_order($registration->order_id);
            if ($order && $order->needs_payment() && (int) $order->get_customer_id() === get_current_user_id()) {
                wp_safe_redirect($order->get_checkout_payment_url());
                exit;
            }
            self::redirect_with_message($competition_id, 'registration');
        }

        $checkout = self::prepare_payment_cart($registration_id, $competition_id, (float) $registration->fee);
        if (is_wp_error($checkout)) {
            self::redirect_with_message($competition_id, 'checkout');
        }

        wp_safe_redirect(wc_get_checkout_url());
        exit;
    }

    private static function prepare_payment_cart($registration_id, $competition_id, $fee) {
        wc_load_cart();
        $woocommerce = WC();
        if (!$woocommerce || !$woocommerce->cart || !$woocommerce->session) {
            return new WP_Error('checkout', __('The payment session could not be initialized.', 'tee-time-nexus-competitions'));
        }

        $product_id = self::get_or_create_product($competition_id, $fee);
        if (!$product_id) {
            return new WP_Error('checkout', __('The registration payment product could not be saved.', 'tee-time-nexus-competitions'));
        }

        // Admin-post requests must save the cart before redirecting to the storefront.
        $woocommerce->cart->empty_cart(true);
        self::$allow_product_add = true;
        try {
            $cart_key = $woocommerce->cart->add_to_cart($product_id, 1, 0, array(), array(
                self::CART_KEY => array(
                    'registration_id' => $registration_id,
                    'competition_id' => $competition_id,
                    'fee' => $fee,
                ),
            ));
        } finally {
            self::$allow_product_add = false;
        }

        if (!$cart_key) {
            return new WP_Error('checkout', __('The registration could not be added to the payment cart.', 'tee-time-nexus-competitions'));
        }

        $woocommerce->cart->calculate_totals();
        $cart_session = new WC_Cart_Session($woocommerce->cart);
        $cart_session->set_session();
        $woocommerce->session->set_customer_session_cookie(true);
        $woocommerce->session->save_data();
        return true;
    }

    public static function require_login() {
        $competition_id = isset($_REQUEST['competition_id']) ? absint($_REQUEST['competition_id']) : 0;
        $return_url = $competition_id ? get_permalink($competition_id) . '#ttn-registration-heading' : home_url('/');
        wp_safe_redirect(self::login_url($return_url));
        exit;
    }

    public static function allow_cart_product($purchasable, $product) {
        if ($product && absint($product->get_meta('_ttn_competition_product_for'))) {
            return self::$allow_product_add || self::cart_contains_registration_product($product->get_id());
        }
        return $purchasable;
    }

    public static function allow_session_cart_item($purchasable, $cart_key, $values, $product) {
        if (!$product || !absint($product->get_meta('_ttn_competition_product_for'))) {
            return $purchasable;
        }
        return self::is_valid_payment_item($values, $product->get_id());
    }

    public static function validate_cart_add($passed, $product_id, $quantity, $variation_id = 0, $variations = array()) {
        $competition_id = absint(get_post_meta($product_id, '_ttn_competition_product_for', true));
        if (!$competition_id) {
            return $passed;
        }
        if (!self::$allow_product_add || !is_user_logged_in()) {
            wc_add_notice(__('Competition payments must be started from the competition registration form.', 'tee-time-nexus-competitions'), 'error');
            return false;
        }
        if ((int) $quantity !== 1) {
            wc_add_notice(__('A competition registration is for one player per account.', 'tee-time-nexus-competitions'), 'error');
            return false;
        }
        return $passed;
    }

    public static function set_cart_item_price($cart) {
        if (!is_object($cart) || !method_exists($cart, 'get_cart')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item) {
            if (empty($cart_item[self::CART_KEY]['registration_id']) || empty($cart_item['data'])) {
                continue;
            }
            $registration = self::get_registration(absint($cart_item[self::CART_KEY]['registration_id']));
            if ($registration && $registration->status === 'pending_payment' && (int) $registration->user_id === get_current_user_id()) {
                $cart_item['data']->set_price((float) $registration->fee);
            }
        }
    }

    public static function validate_cart_items() {
        if (!function_exists('WC') || !WC()->cart) {
            return;
        }
        foreach (WC()->cart->get_cart() as $cart_key => $cart_item) {
            if (empty($cart_item[self::CART_KEY]['registration_id'])) {
                continue;
            }
            $registration = self::get_registration(absint($cart_item[self::CART_KEY]['registration_id']));
            if (
                !$registration
                || $registration->status !== 'pending_payment'
                || (int) $registration->user_id !== get_current_user_id()
                || ($registration->expires_at && $registration->expires_at <= current_time('mysql'))
            ) {
                WC()->cart->remove_cart_item($cart_key);
                wc_add_notice(__('Your competition registration payment hold has expired. Please return to the event page and register again.', 'tee-time-nexus-competitions'), 'error');
            }
        }
    }

    public static function render_cart_item_data($item_data, $cart_item) {
        if (empty($cart_item[self::CART_KEY]['registration_id'])) {
            return $item_data;
        }
        $registration = self::get_registration(absint($cart_item[self::CART_KEY]['registration_id']));
        if ($registration) {
            $item_data[] = array(
                'key' => __('Competition', 'tee-time-nexus-competitions'),
                'value' => esc_html(get_the_title($registration->competition_id)),
            );
            $item_data[] = array(
                'key' => __('Player', 'tee-time-nexus-competitions'),
                'value' => esc_html($registration->player_name),
            );
        }
        return $item_data;
    }

    public static function store_order_item_data($item, $cart_item_key, $values, $order) {
        if (empty($values[self::CART_KEY]['registration_id'])) {
            return;
        }
        $registration = self::get_registration(absint($values[self::CART_KEY]['registration_id']));
        if (!$registration) {
            return;
        }
        $item->add_meta_data('_ttn_competition_registration_id', (int) $registration->id, true);
        $item->add_meta_data('_ttn_competition_id', (int) $registration->competition_id, true);
        $item->add_meta_data('_ttn_competition_player', $registration->player_name, true);
    }

    public static function attach_order_to_registrations($order_id) {
        if (!function_exists('wc_get_order')) {
            return;
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        foreach ($order->get_items() as $item) {
            $registration_id = absint($item->get_meta('_ttn_competition_registration_id'));
            if (!$registration_id) {
                continue;
            }
            self::attach_order($registration_id, $order);
        }
    }

    public static function confirm_paid_order($order_id) {
        if (!function_exists('wc_get_order')) {
            return;
        }
        $order = wc_get_order($order_id);
        if (!$order || !$order->is_paid()) {
            return;
        }
        foreach ($order->get_items() as $item) {
            $registration_id = absint($item->get_meta('_ttn_competition_registration_id'));
            if ($registration_id) {
                self::confirm_paid_registration($registration_id, $order, $item);
            }
        }
    }

    public static function release_order_registrations($order_id) {
        if (!function_exists('wc_get_order')) {
            return;
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        foreach ($order->get_items() as $item) {
            $registration_id = absint($item->get_meta('_ttn_competition_registration_id'));
            if ($registration_id && !self::cancel_registration($registration_id, 'cancelled')) {
                $order->add_order_note(__('The competition registration could not be released after this order was cancelled. Please review it in Golf Competitions.', 'tee-time-nexus-competitions'));
            }
        }
    }

    public static function release_refunded_registrations($order_id) {
        if (!function_exists('wc_get_order')) {
            return;
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        foreach ($order->get_items() as $item) {
            $registration_id = absint($item->get_meta('_ttn_competition_registration_id'));
            if ($registration_id && !self::cancel_registration($registration_id, 'refunded')) {
                $order->add_order_note(__('The competition registration could not be updated after this order was refunded. Please review it in Golf Competitions.', 'tee-time-nexus-competitions'));
            }
        }
    }

    public static function expire_pending_registrations() {
        global $wpdb;
        $table = self::table_name();
        $now = current_time('mysql');
        $expired = $wpdb->get_results($wpdb->prepare(
            "SELECT id, order_id FROM $table WHERE status = %s AND expires_at IS NOT NULL AND expires_at <= %s LIMIT 100",
            'pending_payment',
            $now
        ));

        foreach ($expired as $row) {
            $order = false;
            if ($row->order_id && function_exists('wc_get_order')) {
                $order = wc_get_order($row->order_id);
                if ($order && $order->is_paid()) {
                    foreach ($order->get_items() as $item) {
                        if (absint($item->get_meta('_ttn_competition_registration_id')) === (int) $row->id) {
                            self::confirm_paid_registration((int) $row->id, $order, $item);
                        }
                    }
                    continue;
                }
                if ($order && !in_array($order->get_status(), array('pending', 'on-hold', 'failed', 'cancelled'), true)) {
                    continue;
                }
            }
            $released = self::cancel_registration((int) $row->id, 'cancelled');
            if ($row->order_id && $order) {
                $order->add_order_note($released
                    ? __('Unpaid competition registration hold expired and the place was released.', 'tee-time-nexus-competitions')
                    : __('The competition payment hold expired, but its registration could not be released. Please review it in Golf Competitions.', 'tee-time-nexus-competitions'));
            }
        }
    }

    public static function confirm_free_registration($registration_id) {
        global $wpdb;
        $table = self::table_name();
        $registration = self::get_registration($registration_id);
        if (!$registration) {
            return;
        }

        $updated = $wpdb->update(
            $table,
            array(
                'status' => 'registered',
                'registered_at' => current_time('mysql'),
                'expires_at' => null,
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $registration_id, 'status' => 'pending_payment'),
            array('%s', '%s', '%s', '%s'),
            array('%d', '%s')
        );
        if ($updated !== 1) {
            return;
        }
        self::send_confirmation($registration_id);
        return true;
    }

    private static function reserve_registration($data, $fee) {
        global $wpdb;

        $competition = get_post($data['competition_id']);
        if (!$competition || $competition->post_status !== 'publish') {
            return new WP_Error('unavailable', __('This competition is no longer available.', 'tee-time-nexus-competitions'));
        }
        if (get_post_meta($data['competition_id'], '_ttn_entry_fee', true) === '') {
            return new WP_Error('unavailable', __('Registration is not available for this competition.', 'tee-time-nexus-competitions'));
        }

        $table = self::table_name();
        $now = current_time('mysql');
        if ($wpdb->query('START TRANSACTION') === false) {
            return new WP_Error('registration', __('We could not reserve a place. Please try again.', 'tee-time-nexus-competitions'));
        }
        $wpdb->last_error = '';
        $locked = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE ID = %d AND post_type = %s AND post_status = %s FOR UPDATE",
            $data['competition_id'],
            TTN_Competitions_Post_Type::POST_TYPE,
            'publish'
        ));
        if (!$locked) {
            $wpdb->query('ROLLBACK');
            if ($wpdb->last_error) {
                error_log('TTN competitions registration lock failed: ' . $wpdb->last_error);
                return new WP_Error('registration', __('We could not reserve a place. Please try again.', 'tee-time-nexus-competitions'));
            }
            return new WP_Error('unavailable', __('This competition is no longer available.', 'tee-time-nexus-competitions'));
        }

        $window_error = self::get_availability_error($data['competition_id']);
        if ($window_error) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('closed', $window_error);
        }

        $wpdb->last_error = '';
        $duplicate = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE competition_id = %d AND user_id = %d AND status IN (%s,%s) LIMIT 1 FOR UPDATE",
            $data['competition_id'],
            $data['user_id'],
            'pending_payment',
            'registered'
        ));
        if ($wpdb->last_error) {
            $wpdb->query('ROLLBACK');
            error_log('TTN competitions duplicate-registration check failed: ' . $wpdb->last_error);
            return new WP_Error('registration', __('We could not verify your registration. Please try again.', 'tee-time-nexus-competitions'));
        }
        if ($duplicate) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('duplicate', __('You already have a registration or payment in progress for this competition.', 'tee-time-nexus-competitions'));
        }

        $capacity = absint(get_post_meta($data['competition_id'], '_ttn_capacity', true));
        if ($capacity > 0) {
            $wpdb->last_error = '';
            $occupied = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE competition_id = %d AND status IN (%s,%s)",
                $data['competition_id'],
                'pending_payment',
                'registered'
            ));
            if ($wpdb->last_error) {
                $wpdb->query('ROLLBACK');
                error_log('TTN competitions capacity check failed: ' . $wpdb->last_error);
                return new WP_Error('registration', __('We could not verify event capacity. Please try again.', 'tee-time-nexus-competitions'));
            }
            if ($occupied >= $capacity) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('full', __('This competition is full.', 'tee-time-nexus-competitions'));
            }
        }

        $expires_at = $fee > 0 ? self::expiry_time(self::hold_minutes()) : null;
        $inserted = $wpdb->insert($table, array(
            'competition_id' => $data['competition_id'],
            'user_id' => $data['user_id'],
            'status' => 'pending_payment',
            'player_name' => $data['player_name'],
            'player_email' => $data['player_email'],
            'player_phone' => $data['player_phone'],
            'handicap' => $data['handicap'],
            'golfzon_username' => $data['golfzon_username'],
            'waiver_accepted_at' => $data['waiver_accepted_at'],
            'fee' => number_format($fee, 2, '.', ''),
            'expires_at' => $expires_at,
            'created_at' => $now,
            'updated_at' => $now,
        ), array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'));

        if (!$inserted) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('registration', __('We could not save your registration. Please try again.', 'tee-time-nexus-competitions'));
        }

        $registration_id = (int) $wpdb->insert_id;
        if ($wpdb->query('COMMIT') === false) {
            error_log('TTN competitions registration transaction could not commit: ' . $wpdb->last_error);
            return new WP_Error('registration', __('We could not reserve a place. Please try again.', 'tee-time-nexus-competitions'));
        }
        return $registration_id;
    }

    private static function confirm_paid_registration($registration_id, $order, $item) {
        global $wpdb;
        $table = self::table_name();
        if ($wpdb->query('START TRANSACTION') === false) {
            $order->add_order_note(__('Competition payment succeeded, but registration processing could not start. Please review it in Golf Competitions.', 'tee-time-nexus-competitions'));
            return;
        }
        $registration = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d FOR UPDATE", $registration_id));
        if (!$registration) {
            $wpdb->query('ROLLBACK');
            $order->add_order_note(__('Competition payment succeeded, but its registration record was not found. Please review this order.', 'tee-time-nexus-competitions'));
            return;
        }
        if (!$registration->order_id && (int) $registration->user_id === (int) $order->get_customer_id()) {
            $linked = $wpdb->update(
                $table,
                array('order_id' => $order->get_id(), 'updated_at' => current_time('mysql')),
                array('id' => $registration_id),
                array('%d', '%s'),
                array('%d')
            );
            if ($linked === false) {
                $wpdb->query('ROLLBACK');
                $order->add_order_note(__('Competition payment succeeded, but registration could not be linked. Please review this order.', 'tee-time-nexus-competitions'));
                return;
            }
            $registration->order_id = $order->get_id();
        }
        if ((int) $registration->order_id !== (int) $order->get_id()) {
            $wpdb->query('ROLLBACK');
            $order->add_order_note(__('Competition payment was not linked to its registration. Please review it in Golf Competitions.', 'tee-time-nexus-competitions'));
            return;
        }
        if ($registration->status === 'registered') {
            $wpdb->query('COMMIT');
            return;
        }
        if ($registration->status !== 'pending_payment') {
            $wpdb->query('COMMIT');
            self::refund_late_payment($registration_id, $order, $item);
            return;
        }

        $updated = $wpdb->update(
            $table,
            array(
                'status' => 'registered',
                'registered_at' => current_time('mysql'),
                'expires_at' => null,
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $registration_id, 'status' => 'pending_payment'),
            array('%s', '%s', '%s', '%s'),
            array('%d', '%s')
        );
        if ($updated === false) {
            $wpdb->query('ROLLBACK');
            $order->add_order_note(__('Competition payment succeeded, but the registration could not be updated. Please review it in Golf Competitions.', 'tee-time-nexus-competitions'));
            return;
        }
        $wpdb->query('COMMIT');
        if (!self::send_confirmation($registration_id)) {
            $order->add_order_note(__('Registration was confirmed, but its confirmation email could not be sent. Please contact the player.', 'tee-time-nexus-competitions'));
        }
    }

    private static function refund_late_payment($registration_id, $order, $item) {
        $meta_key = '_ttn_competition_late_refund_' . $registration_id;
        if ($order->get_meta($meta_key)) {
            return;
        }
        $taxes = $item->get_taxes();
        $refund_tax = isset($taxes['total']) && is_array($taxes['total']) ? $taxes['total'] : array();
        $refund_amount = (float) $item->get_total() + array_sum(array_map('floatval', $refund_tax));
        if ($refund_amount <= 0) {
            $order->update_meta_data($meta_key, 'not_required');
            $order->save();
            return;
        }
        if (!function_exists('wc_create_refund')) {
            $order->add_order_note(__('A competition payment arrived after its place was released. A manual refund is required.', 'tee-time-nexus-competitions'));
            return;
        }

        $refund = wc_create_refund(array(
            'amount' => $refund_amount,
            'reason' => __('Competition registration expired before payment completed.', 'tee-time-nexus-competitions'),
            'order_id' => $order->get_id(),
            'refund_payment' => true,
            'restock_items' => false,
            'line_items' => array(
                $item->get_id() => array(
                    'qty' => (int) $item->get_quantity(),
                    'refund_total' => (float) $item->get_total(),
                    'refund_tax' => $refund_tax,
                ),
            ),
        ));
        if (is_wp_error($refund)) {
            $order->add_order_note(sprintf(
                /* translators: %s: refund error */
                __('A late competition payment could not be refunded automatically: %s', 'tee-time-nexus-competitions'),
                $refund->get_error_message()
            ));
            return;
        }
        $order->update_meta_data($meta_key, 'refunded');
        $order->save();
    }

    private static function attach_order($registration_id, $order) {
        global $wpdb;
        $table = self::table_name();
        $registration = self::get_registration($registration_id);
        if (!$registration || (int) $registration->user_id !== (int) $order->get_customer_id()) {
            $order->add_order_note(__('The competition registration could not be linked because the checkout customer did not match the registered player.', 'tee-time-nexus-competitions'));
            return;
        }
        if ((int) $registration->order_id === (int) $order->get_id()) {
            return;
        }
        $minutes = self::hold_minutes();
        $updated = $wpdb->update(
            $table,
            array(
                'order_id' => $order->get_id(),
                'expires_at' => self::expiry_time($minutes),
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $registration_id, 'user_id' => $order->get_customer_id(), 'status' => 'pending_payment'),
            array('%d', '%s', '%s'),
            array('%d', '%d', '%s')
        );
        if ($updated !== 1) {
            $order->add_order_note(__('The competition registration could not be linked to this order. Please contact support before changing the order.', 'tee-time-nexus-competitions'));
        }
    }

    private static function cancel_registration($registration_id, $status) {
        global $wpdb;
        $table = self::table_name();
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE $table SET status = %s, expires_at = NULL, updated_at = %s
            WHERE id = %d AND status IN (%s, %s)",
            $status,
            current_time('mysql'),
            $registration_id,
            'pending_payment',
            'registered'
        ));
        if ($updated === false) {
            error_log('TTN competitions registration could not be released: ' . $wpdb->last_error);
            return false;
        }
        return true;
    }

    private static function send_confirmation($registration_id) {
        global $wpdb;
        $table = self::table_name();
        if ($wpdb->query('START TRANSACTION') === false) {
            return;
        }
        $registration = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d FOR UPDATE", $registration_id));
        if (!$registration || $registration->status !== 'registered') {
            $wpdb->query('ROLLBACK');
            return false;
        }
        if ($registration->confirmation_sent_at) {
            $wpdb->query('COMMIT');
            return true;
        }
        $claimed = $wpdb->update(
            $table,
            array('confirmation_sent_at' => current_time('mysql'), 'updated_at' => current_time('mysql')),
            array('id' => $registration_id),
            array('%s', '%s'),
            array('%d')
        );
        if ($claimed === false || $claimed === 0) {
            $wpdb->query('ROLLBACK');
            return false;
        }
        $wpdb->query('COMMIT');

        $title = get_the_title($registration->competition_id);
        $subject = sprintf(__('Registration confirmed: %s', 'tee-time-nexus-competitions'), $title);
        $message = sprintf(
            /* translators: 1: player name, 2: competition title */
            __("Hi %1\$s,\n\nYour registration for %2\$s is confirmed.\n\nWe will send event updates to this email address. For questions, contact Tee Time Nexus.\n", 'tee-time-nexus-competitions'),
            $registration->player_name,
            $title
        );
        $mail_error = '';
        $capture_mail_error = static function ($error) use (&$mail_error) {
            if (is_wp_error($error)) {
                $mail_error = $error->get_error_message();
            }
        };
        add_action('wp_mail_failed', $capture_mail_error, 10, 1);
        $sent = wp_mail($registration->player_email, $subject, $message);
        remove_action('wp_mail_failed', $capture_mail_error, 10);
        if (!$sent) {
            $wpdb->update(
                $table,
                array('confirmation_sent_at' => null, 'updated_at' => current_time('mysql')),
                array('id' => $registration_id),
                array('%s', '%s'),
                array('%d')
            );
            error_log('TTN competition registration confirmation email failed: ' . ($mail_error ?: 'wp_mail returned false without an error message.'));
            return false;
        }
        return true;
    }

    private static function get_or_create_product($competition_id, $fee) {
        if (!class_exists('WC_Product_Simple')) {
            return 0;
        }
        $product_id = absint(get_post_meta($competition_id, '_ttn_wc_product_id', true));
        $product = $product_id ? wc_get_product($product_id) : false;
        if (!$product || absint($product->get_meta('_ttn_competition_product_for')) !== $competition_id) {
            $product = new WC_Product_Simple();
            $product->set_status('publish');
            $product->set_catalog_visibility('hidden');
            $product->set_virtual(true);
            $product->set_sold_individually(true);
            $product->set_name(sprintf(
                /* translators: %s: competition title */
                __('Competition registration: %s', 'tee-time-nexus-competitions'),
                get_the_title($competition_id)
            ));
            $product->set_regular_price(number_format($fee, 2, '.', ''));
            $product->set_price(number_format($fee, 2, '.', ''));
            $product->update_meta_data('_ttn_competition_product_for', $competition_id);
            $product_id = $product->save();
            if (!$product_id) {
                return 0;
            }
            update_post_meta($competition_id, '_ttn_wc_product_id', $product_id);
            return $product_id;
        }

        $product->set_name(sprintf(
            /* translators: %s: competition title */
            __('Competition registration: %s', 'tee-time-nexus-competitions'),
            get_the_title($competition_id)
        ));
        $product->set_regular_price(number_format($fee, 2, '.', ''));
        $product->set_price(number_format($fee, 2, '.', ''));
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        return (int) $product->save();
    }

    private static function get_registration($registration_id) {
        global $wpdb;
        $table = self::table_name();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $registration_id));
    }

    private static function login_url($return_url) {
        if (function_exists('golf_simulator_theme_get_login_url')) {
            return golf_simulator_theme_get_login_url($return_url, 'login');
        }
        if (function_exists('wc_get_page_permalink')) {
            $account_url = wc_get_page_permalink('myaccount');
            if ($account_url) {
                return add_query_arg('redirect', $return_url, $account_url);
            }
        }
        return wp_login_url($return_url);
    }

    private static function cart_contains_registration_product($product_id) {
        if (!function_exists('WC') || !WC()->cart || !is_user_logged_in()) {
            return false;
        }
        // get_cart() would load the session again while WooCommerce checks purchasability.
        $items = WC()->cart->get_cart_contents();
        $saved_items = WC()->session ? WC()->session->get('cart', array()) : array();
        foreach (array_merge($items, is_array($saved_items) ? $saved_items : array()) as $item) {
            if (self::is_valid_payment_item($item, $product_id)) {
                return true;
            }
        }
        return false;
    }

    private static function is_valid_payment_item($item, $product_id) {
        if (
            !is_user_logged_in()
            || empty($item[self::CART_KEY]['registration_id'])
            || empty($item['product_id'])
            || absint($item['product_id']) !== absint($product_id)
            || !isset($item['quantity'])
            || (float) $item['quantity'] !== 1.0
        ) {
            return false;
        }
        $registration = self::get_registration(absint($item[self::CART_KEY]['registration_id']));
        return $registration
            && $registration->status === 'pending_payment'
            && (int) $registration->user_id === get_current_user_id()
            && (int) $registration->competition_id === absint(get_post_meta($product_id, '_ttn_competition_product_for', true))
            && (!$registration->expires_at || $registration->expires_at > current_time('mysql'));
    }

    private static function get_active_registration($competition_id, $user_id) {
        global $wpdb;
        $table = self::table_name();
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE competition_id = %d AND user_id = %d AND status IN (%s,%s) ORDER BY id DESC LIMIT 1",
            $competition_id,
            $user_id,
            'pending_payment',
            'registered'
        ));
    }

    private static function get_availability_error($competition_id) {
        $post = get_post($competition_id);
        if (!$post || $post->post_type !== TTN_Competitions_Post_Type::POST_TYPE || $post->post_status !== 'publish') {
            return __('This competition is not available.', 'tee-time-nexus-competitions');
        }
        $fee = get_post_meta($competition_id, '_ttn_entry_fee', true);
        if ($fee === '') {
            return __('Registration details are not available yet.', 'tee-time-nexus-competitions');
        }

        $today = current_time('Y-m-d');
        $registration_start = get_post_meta($competition_id, '_ttn_registration_start', true);
        $registration_end = get_post_meta($competition_id, '_ttn_registration_end', true);
        $event_start = get_post_meta($competition_id, '_ttn_start_date', true);
        if ($registration_start && $today < $registration_start) {
            return __('Registration has not opened yet.', 'tee-time-nexus-competitions');
        }
        $close_date = $registration_end ?: $event_start;
        if ($close_date && $today > $close_date) {
            return __('Registration for this competition is closed.', 'tee-time-nexus-competitions');
        }
        $capacity = absint(get_post_meta($competition_id, '_ttn_capacity', true));
        if ($capacity > 0) {
            $active_count = self::active_count($competition_id);
            if ($active_count === null) {
                return __('Registration availability could not be checked. Please try again shortly.', 'tee-time-nexus-competitions');
            }
            if ($active_count >= $capacity) {
                return __('This competition is full.', 'tee-time-nexus-competitions');
            }
        }
        return '';
    }

    private static function active_count($competition_id) {
        global $wpdb;
        $table = self::table_name();
        $wpdb->last_error = '';
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE competition_id = %d AND status IN (%s,%s)",
            $competition_id,
            'pending_payment',
            'registered'
        ));
        if ($wpdb->last_error || $count === null) {
            error_log('TTN competitions active-registration count failed: ' . $wpdb->last_error);
            return null;
        }
        return (int) $count;
    }

    private static function registration_query_message() {
        if (empty($_GET['registration'])) {
            return '';
        }
        $result = sanitize_key(wp_unslash($_GET['registration']));
        if ($result === 'confirmed') {
            return '<p class="ttn-registration-success">' . esc_html__('Your free registration is confirmed. Check your email for details.', 'tee-time-nexus-competitions') . '</p>';
        }
        if ($result !== 'error') {
            return '';
        }
        $messages = array(
            'rules' => __('Accept the event rules and waiver information to continue.', 'tee-time-nexus-competitions'),
            'account' => __('Add a valid email address to your account before registering.', 'tee-time-nexus-competitions'),
            'unavailable' => __('Registration details are not available for this competition.', 'tee-time-nexus-competitions'),
            'checkout' => __('Checkout is unavailable right now. Please try again or contact us.', 'tee-time-nexus-competitions'),
            'expired' => __('Your previous payment hold expired. Review the form below to register again.', 'tee-time-nexus-competitions'),
            'closed' => __('Registration for this competition is not currently open.', 'tee-time-nexus-competitions'),
            'full' => __('This competition is full.', 'tee-time-nexus-competitions'),
            'duplicate' => __('You already have a registration or payment in progress for this competition.', 'tee-time-nexus-competitions'),
            'registration' => __('We could not save your registration. Please try again.', 'tee-time-nexus-competitions'),
        );
        $code = isset($_GET['code']) ? sanitize_key(wp_unslash($_GET['code'])) : '';
        $message = isset($messages[$code]) ? $messages[$code] : __('We could not complete your registration. Please try again.', 'tee-time-nexus-competitions');
        return '<p class="ttn-registration-error">' . esc_html($message) . '</p>';
    }

    private static function redirect_with_message($competition_id, $code) {
        wp_safe_redirect(add_query_arg(array('registration' => 'error', 'code' => sanitize_key($code)), get_permalink($competition_id)) . '#ttn-registration-heading');
        exit;
    }

    private static function woocommerce_available() {
        return function_exists('WC')
            && function_exists('wc_load_cart')
            && function_exists('wc_get_checkout_url')
            && class_exists('WC_Product_Simple');
    }

    private static function hold_minutes() {
        $minutes = absint(get_option('woocommerce_hold_stock_minutes', 60));
        return $minutes ? max(5, $minutes) : 30;
    }

    private static function expiry_time($minutes) {
        $now = new DateTimeImmutable(current_time('mysql'), wp_timezone());
        return $now->modify('+' . absint($minutes) . ' minutes')->format('Y-m-d H:i:s');
    }
}
