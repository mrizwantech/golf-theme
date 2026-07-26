<?php
// Script to create /my-account/ page with correct template

require_once __DIR__ . '/wp-load.php';

// Check if page already exists
$existing_page = get_page_by_title('My Account', OBJECT, 'page');

if (!$existing_page) {
    $page_id = wp_insert_post(array(
        'post_type' => 'page',
        'post_title' => 'My Account',
        'post_name' => 'my-account',
        'post_status' => 'publish',
        'post_content' => '[ttn_user_dashboard]',
        'meta_input' => array(
            '_wp_page_template' => 'page-account.php',
        ),
    ));

    if ($page_id) {
        update_post_meta($page_id, '_wp_page_template', 'page-account.php');
        echo "✓ My Account page created successfully!\n";
        echo "URL: " . home_url('/my-account/') . "\n";
    } else {
        echo "✗ Failed to create My Account page.\n";
    }
} else {
    echo "✓ My Account page already exists.\n";
    echo "URL: " . get_permalink($existing_page->ID) . "\n";
}
?>
