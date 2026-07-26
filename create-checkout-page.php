<?php
require 'wp-load.php';

$page = get_page_by_path('booking-checkout');

if (!$page) {
    $id = wp_insert_post(array(
        'post_title' => 'Booking Checkout',
        'post_name' => 'booking-checkout',
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_content' => ''
    ));
    if (!is_wp_error($id)) {
        update_post_meta($id, '_wp_page_template', 'page-booking-checkout.php');
        echo 'created:' . $id;
    } else {
        echo 'error';
    }
} else {
    update_post_meta($page->ID, '_wp_page_template', 'page-booking-checkout.php');
    echo 'exists:' . $page->ID;
}
