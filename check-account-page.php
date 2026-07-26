<?php
require_once __DIR__ . '/wp-load.php';

$page = get_page_by_path('my-account');
if ($page) {
    $template = get_post_meta($page->ID, '_wp_page_template', true);
    echo "✓ My Account Page Found\n";
    echo "ID: " . $page->ID . "\n";
    echo "Template: " . ($template ? $template : "page-account.php (auto-assigned)\n");
    echo "URL: " . get_permalink($page->ID) . "\n";
    
    // Force template assignment
    if (!$template || $template !== 'page-account.php') {
        update_post_meta($page->ID, '_wp_page_template', 'page-account.php');
        echo "✓ Template updated to page-account.php\n";
    }
} else {
    echo "✗ My Account page not found\n";
}
?>
