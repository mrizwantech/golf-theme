<?php
require 'wp-load.php';

$front_page = get_page_by_path('');

if (!$front_page) {
    $page_id = wp_insert_post(array(
        'post_title' => 'Home',
        'post_name' => 'home',
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_content' => 'Welcome to Tee Time Nexus'
    ));
    
    if (!is_wp_error($page_id)) {
        update_option('page_on_front', $page_id);
        update_option('show_on_front', 'page');
        echo 'created-and-set:' . $page_id;
    } else {
        echo 'error-creating';
    }
} else {
    update_option('page_on_front', $front_page->ID);
    update_option('show_on_front', 'page');
    echo 'already-exists:' . $front_page->ID;
}
