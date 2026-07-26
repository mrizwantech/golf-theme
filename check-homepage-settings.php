<?php
require 'wp-load.php';

$show_on_front = get_option('show_on_front');
$page_on_front = get_option('page_on_front');
$front_page_id = get_option('page_on_front');

echo "show_on_front: " . $show_on_front . "\n";
echo "page_on_front: " . $page_on_front . "\n";

if ($front_page_id) {
    $page = get_post($front_page_id);
    if ($page) {
        echo "front_page_title: " . $page->post_title . "\n";
        echo "front_page_status: " . $page->post_status . "\n";
        echo "front_page_name: " . $page->post_name . "\n";
    }
}

// Ensure show_on_front is set to 'page'
update_option('show_on_front', 'page');
update_option('page_on_front', $front_page_id ? $front_page_id : 47);
echo "updated";
