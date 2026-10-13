<?php

if (!defined('ABSPATH')) {
    exit;
}

function ttn_kisi_test_connection() {
    if (!defined('TTN_KISI_API_KEY') || !is_string(TTN_KISI_API_KEY) || trim(TTN_KISI_API_KEY) === '') {
        return new WP_Error('ttn_kisi_missing_key', 'TTN_KISI_API_KEY is missing from the WordPress server configuration.');
    }
    if (!defined('TTN_KISI_ENTRANCE_LOCK_ID') || !ctype_digit((string) TTN_KISI_ENTRANCE_LOCK_ID) || (int) TTN_KISI_ENTRANCE_LOCK_ID < 1) {
        return new WP_Error('ttn_kisi_missing_lock', 'TTN_KISI_ENTRANCE_LOCK_ID must be a positive integer in the WordPress server configuration.');
    }
    $key = trim(TTN_KISI_API_KEY);
    if (preg_match('/[\r\n]/', $key)) {
        return new WP_Error('ttn_kisi_invalid_key', 'TTN_KISI_API_KEY contains invalid line breaks. Check the server configuration.');
    }

    $response = wp_remote_get('https://api.kisi.io/locks/' . (int) TTN_KISI_ENTRANCE_LOCK_ID, array(
        'timeout' => 15,
        'redirection' => 0,
        'sslverify' => true,
        'headers' => array(
            'Authorization' => 'KISI-LOGIN ' . $key,
            'Accept' => 'application/json',
        ),
    ));
    if (is_wp_error($response)) {
        return new WP_Error('ttn_kisi_network_error', 'Could not reach Kisi. Check the server network connection and TLS configuration, then retry.');
    }
    $status = (int) wp_remote_retrieve_response_code($response);
    if ($status !== 200) {
        $messages = array(
            401 => 'Kisi rejected the API key. Verify that it is a valid Kisi API login secret.',
            403 => 'The Kisi API login does not have permission to read this door.',
            404 => 'The configured entrance lock was not found or is not visible to this API login.',
            429 => 'Kisi rate-limited the request. Wait before retrying.',
        );
        $message = $messages[$status] ?? 'Kisi returned an unexpected response. Check the Kisi service and server configuration.';
        return new WP_Error('ttn_kisi_http_error', 'HTTP ' . $status . ': ' . $message);
    }
    $lock = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($lock) || !isset($lock['id']) || !is_numeric($lock['id']) || (int) $lock['id'] !== (int) TTN_KISI_ENTRANCE_LOCK_ID) {
        return new WP_Error('ttn_kisi_invalid_response', 'HTTP 200 received, but Kisi did not return the configured lock. Connection validation failed.');
    }
    return true;
}

function ttn_kisi_connection_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    $result = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_admin_referer('ttn_kisi_test_connection');
        $result = ttn_kisi_test_connection();
    }
    ?>
    <div class="wrap">
        <h1>Kisi Connection</h1>
        <p>Test the configured entrance door with a read-only Kisi API request. This does not unlock the door or create access rights. Credentials and API response bodies are not displayed or stored.</p>
        <?php if ($result !== null) : ?>
            <div class="notice <?php echo is_wp_error($result) ? 'notice-error' : 'notice-success'; ?>">
                <p><?php echo esc_html(is_wp_error($result) ? $result->get_error_message() : 'HTTP 200: Kisi authenticated successfully and returned the configured entrance lock. Door unlocking and booking access have not been tested.'); ?></p>
            </div>
        <?php endif; ?>
        <form method="post">
            <?php wp_nonce_field('ttn_kisi_test_connection'); ?>
            <?php submit_button('Test Kisi Connection'); ?>
        </form>
    </div>
    <?php
}

add_action('admin_menu', function () {
    add_submenu_page(
        'ttn-bookings-dashboard',
        'Kisi Connection',
        'Kisi Connection',
        'manage_options',
        'ttn-kisi-connection',
        'ttn_kisi_connection_admin_page'
    );
}, 20);
