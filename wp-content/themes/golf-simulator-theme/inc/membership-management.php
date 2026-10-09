<?php

if (!defined('ABSPATH')) {
    exit;
}

function golf_simulator_theme_membership_key($name) {
    $key = strtoupper(trim((string) $name));
    return 'EAGLE' === $key ? 'ALBATROSS' : $key;
}

function golf_simulator_theme_membership_revision($membership) {
    return hash('sha256', implode('|', array(
        $membership->id, $membership->package_name, $membership->status,
        $membership->payment_status, $membership->updated_at,
        $membership->next_billing_date, $membership->start_date,
    )));
}

function golf_simulator_theme_membership_period_end($membership) {
    if (empty($membership->next_billing_date)) {
        return false;
    }
    $end = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $membership->next_billing_date, wp_timezone());
    return $end && $end->format('Y-m-d H:i:s') === $membership->next_billing_date ? $end->getTimestamp() : false;
}

function golf_simulator_theme_membership_lock($user_id) {
    global $wpdb;
    $key = 'ttn_membership_change_lock_' . $user_id;
    $now = time();
    $existing = get_option($key);
    if ($existing && (int) $existing < $now - 120) {
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
            $key, (string) $existing
        ));
        wp_cache_delete($key, 'options');
    }
    return add_option($key, $now, '', false) ? $key : false;
}

function golf_simulator_theme_apply_due_membership_change($user_id, $membership) {
    $change = get_user_meta($user_id, '_ttn_membership_change', true);
    if (!$membership || !is_array($change) || (int) $change['effective_at'] > time()) {
        return $membership;
    }
    $lock = golf_simulator_theme_membership_lock($user_id);
    if (!$lock) {
        // Never authorize an expired, scheduled membership while reconciliation is in flight.
        $membership->status = 'pending';
        return $membership;
    }
    try {
        $membership = golf_simulator_theme_get_user_membership_record($user_id, false);
        $change = get_user_meta($user_id, '_ttn_membership_change', true);
        if (!$membership || !is_array($change) || (int) $change['effective_at'] > time()) {
            return $membership;
        }
        $packages = golf_simulator_theme_get_default_membership_packages();
        $key = 'cancel' === $change['action']
            ? golf_simulator_theme_membership_key($membership->package_name) : $change['package'];
        if (empty($packages[$key])) {
            error_log('TTN scheduled membership package unavailable for account ' . $user_id);
            $membership->status = 'pending';
            return $membership;
        }
        $data = (array) $membership;
        $data['package_name'] = $key;
        $data['package_slug'] = sanitize_title($key);
        $data['price'] = $packages[$key]['price'];
        $data['discount_price'] = $packages[$key]['discount_price'];
        $data['status'] = 'cancel' === $change['action'] ? 'cancelled' : 'pending';
        $data['payment_status'] = 'cancel' === $change['action'] ? 'cancelled' : 'pending';
        $data['cancel_date'] = 'cancel' === $change['action'] ? wp_date('Y-m-d H:i:s', $change['effective_at'], wp_timezone()) : '';
        $data['next_billing_date'] = '';
        if (!golf_simulator_theme_save_user_membership_record($data)) {
            $membership->status = 'pending';
            return $membership;
        }
        if (!delete_user_meta($user_id, '_ttn_membership_change')) {
            error_log('TTN scheduled membership cleanup failed for account ' . $user_id);
        }
        wp_clear_scheduled_hook('ttn_membership_change_due', array($user_id));
        return (object) array_merge($data, array('updated_at' => current_time('mysql')));
    } finally {
        delete_option($lock);
    }
}

function golf_simulator_theme_membership_change_due($user_id) {
    golf_simulator_theme_get_user_membership_record($user_id);
    $change = get_user_meta($user_id, '_ttn_membership_change', true);
    if (is_array($change) && (int) $change['effective_at'] <= time()) {
        $retry = wp_schedule_single_event(time() + 60, 'ttn_membership_change_due', array($user_id), true);
        if (is_wp_error($retry) || false === $retry) {
            error_log('TTN membership reconciliation retry could not be scheduled for account ' . $user_id);
        }
    }
}
add_action('ttn_membership_change_due', 'golf_simulator_theme_membership_change_due');

function golf_simulator_theme_validate_membership_upgrade_checkout($data, $errors) {
    if (!function_exists('WC') || !WC() || !WC()->cart) {
        return;
    }
    foreach (WC()->cart->get_cart() as $item) {
        $meta = $item['golf_simulator_membership'] ?? array();
        if (empty($meta['is_upgrade'])) {
            continue;
        }
        $user_id = get_current_user_id();
        $membership = golf_simulator_theme_get_user_membership_record($user_id);
        if (!$membership || 'active' !== $membership->status || 'paid' !== $membership->payment_status
            || golf_simulator_theme_membership_period_end($membership) <= time()
            || get_user_meta($user_id, '_ttn_membership_change', true)
            || empty($meta['membership_revision'])
            || !hash_equals(golf_simulator_theme_membership_revision($membership), (string) $meta['membership_revision'])) {
            $errors->add('membership_changed', 'Your membership changed or the paid period ended. Please start your upgrade again.');
        }
    }
}
add_action('woocommerce_after_checkout_validation', 'golf_simulator_theme_validate_membership_upgrade_checkout', 20, 2);

function golf_simulator_theme_membership_management_record($user_id) {
    $membership = golf_simulator_theme_get_user_membership_record($user_id);
    if (!$membership) {
        return null;
    }
    $data = (array) $membership;
    // Nullable DB columns must still reach the app as strings, or it rejects the whole record.
    foreach (array('price', 'discount_price', 'status', 'payment_status') as $field) {
        $data[$field] = (string) ($data[$field] ?? '');
    }
    foreach (array('next_billing_date', 'cancel_date') as $field) {
        $data[$field] = isset($data[$field]) && $data[$field] !== '' ? (string) $data[$field] : null;
    }
    $data['package_key'] = golf_simulator_theme_membership_key($membership->package_name);
    $data['package_name'] = golf_simulator_theme_get_membership_package_display_name($membership->package_name);
    $data['revision'] = golf_simulator_theme_membership_revision($membership);
    $data['period_end'] = golf_simulator_theme_membership_period_end($membership) ?: null;
    $data['can_manage'] = 'paid' === $membership->payment_status && 'active' === $membership->status;
    $data['management_notice'] = (string) get_user_meta($user_id, '_ttn_membership_payment_notice', true);
    $change = get_user_meta($user_id, '_ttn_membership_change', true);
    $data['scheduled_change'] = is_array($change) ? array(
        'action' => $change['action'],
        'package_name' => golf_simulator_theme_get_membership_package_display_name($change['package']),
        'effective_at' => (int) $change['effective_at'],
    ) : null;
    return $data;
}

function golf_simulator_theme_manage_membership($user_id, $action, $package_name, $revision) {
    $membership = golf_simulator_theme_get_user_membership_record($user_id);
    if (!$membership || 'paid' !== $membership->payment_status || 'active' !== $membership->status) {
        return new WP_Error('membership_not_active', 'An active paid membership is required. Refresh your membership.', array('status' => 409));
    }
    $lock = golf_simulator_theme_membership_lock($user_id);
    if (!$lock) {
        return new WP_Error('membership_busy', 'A membership change is already in progress. Please try again.', array('status' => 409));
    }
    try {
        $membership = golf_simulator_theme_get_user_membership_record($user_id);
        if (!$membership || !hash_equals(golf_simulator_theme_membership_revision($membership), (string) $revision)) {
            return new WP_Error('membership_changed', 'Your membership changed. Refresh before trying again.', array('status' => 409));
        }
        if (!in_array($action, array('change', 'cancel', 'undo'), true)) {
            return new WP_Error('membership_invalid_action', 'Choose a valid membership action.', array('status' => 400));
        }
        $pending = get_user_meta($user_id, '_ttn_membership_change', true);
        if ('undo' === $action) {
            if ($pending && !delete_user_meta($user_id, '_ttn_membership_change')) {
                return new WP_Error('membership_save_failed', 'Unable to remove the scheduled change. Please try again.', array('status' => 503));
            }
            wp_clear_scheduled_hook('ttn_membership_change_due', array($user_id));
            do_action('ttn_membership_change_scheduled', $user_id);
            return array('message' => 'Scheduled change removed. Your current membership continues.');
        }
        if ($pending) {
            return new WP_Error('membership_change_pending', 'Remove your scheduled change before making another change.', array('status' => 409));
        }
        $end = golf_simulator_theme_membership_period_end($membership);
        if (!$end || $end <= time()) {
            return new WP_Error('membership_period_unknown', 'Your paid-period end date needs verification. Please contact support before changing your membership.', array('status' => 409));
        }
        $packages = golf_simulator_theme_get_default_membership_packages();
        $current_key = golf_simulator_theme_membership_key($membership->package_name);
        $key = 'cancel' === $action ? $current_key : golf_simulator_theme_membership_key($package_name);
        if (!isset($packages[$key], $packages[$current_key]) || ('change' === $action && $key === $current_key)) {
            return new WP_Error('membership_invalid_package', 'Choose a different available membership.', array('status' => 400));
        }
        $keys = array_keys($packages);
        if ('change' === $action && array_search($key, $keys, true) > array_search($current_key, $keys, true)) {
            $allowed = golf_simulator_theme_require_contact_email($user_id);
            if (is_wp_error($allowed)) {
                return $allowed;
            }
            $amount = golf_simulator_theme_calculate_prorated_upgrade_amount($membership, $packages[$key]);
            if ($amount <= 0) {
                return new WP_Error('membership_upgrade_quote', 'A prorated upgrade payment could not be calculated. Please contact support.', array('status' => 409));
            }
            return array('checkout' => array('package' => $key, 'is_upgrade' => true, 'custom_price' => $amount, 'revision' => $revision));
        }
        $change = array('action' => 'cancel' === $action ? 'cancel' : 'downgrade', 'package' => $key, 'effective_at' => $end);
        $scheduled = wp_schedule_single_event($end, 'ttn_membership_change_due', array($user_id), true);
        if (is_wp_error($scheduled) || false === $scheduled) {
            return new WP_Error('membership_schedule_failed', 'Unable to schedule your membership change. Please try again.', array('status' => 503));
        }
        if (!update_user_meta($user_id, '_ttn_membership_change', $change)) {
            wp_clear_scheduled_hook('ttn_membership_change_due', array($user_id));
            return new WP_Error('membership_save_failed', 'Unable to save your membership change. Please try again.', array('status' => 503));
        }
        do_action('ttn_membership_change_scheduled', $user_id);
        $date = wp_date('F j, Y', $end, wp_timezone());
        $message = 'cancel' === $action
            ? 'Cancellation scheduled. Your current benefits continue until the paid period ends. No refund is issued.'
            : 'Downgrade scheduled. Your current benefits continue until the paid period ends. Pay for the new tier after that date to activate it.';
        if (!golf_simulator_theme_send_membership_confirmation($user_id, $key, 0, 'paid', $message . ' Effective ' . $date . '.')) {
            error_log('TTN scheduled membership confirmation email failed for account ' . $user_id);
            $message .= ' Your change was saved, but the confirmation email could not be sent.';
        }
        return array('message' => $message);
    } finally {
        delete_option($lock);
    }
}
