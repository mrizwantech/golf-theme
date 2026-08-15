<?php
// One-time script: point the existing Login/Register pages at the branded
// Account Access template (page-login.php) and clear old plugin shortcodes.

require_once __DIR__ . '/wp-load.php';

function ttn_setup_auth_page($slug, $default_tab) {
    $page = get_page_by_path($slug);

    if (!$page) {
        echo "Page with slug '$slug' not found.\n";
        return;
    }

    wp_update_post(array(
        'ID' => $page->ID,
        'post_content' => '',
    ));
    update_post_meta($page->ID, '_wp_page_template', 'page-login.php');

    echo "Updated '$slug' page (ID {$page->ID}) to use the Account Access template.\n";
    echo "URL: " . get_permalink($page->ID) . ($default_tab === 'register' ? '?tab=register' : '') . "\n";
}

ttn_setup_auth_page('login', 'login');
ttn_setup_auth_page('register', 'register');
