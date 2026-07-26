<?php
require 'wp-load.php';

$page = get_page_by_path('book-a-bay');

if ($page) {
    wp_update_post(array(
        'ID' => $page->ID,
        'post_status' => 'publish',
        'post_title' => 'Book a Bay',
        'post_name' => 'book-a-bay',
        'post_content' => ''
    ));
    update_post_meta($page->ID, '_wp_page_template', 'page-booking.php');
    echo 'updated:' . $page->ID;
} else {
    $id = wp_insert_post(array(
        'post_title' => 'Book a Bay',
        'post_name' => 'book-a-bay',
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_content' => ''
    ));
    if (!is_wp_error($id)) {
        update_post_meta($id, '_wp_page_template', 'page-booking.php');
        echo 'created:' . $id;
    } else {
        echo 'error';
    }
}
