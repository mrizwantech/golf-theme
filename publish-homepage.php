<?php
require 'wp-load.php';

$page_id = 47;
$page = get_post($page_id);

if ($page) {
    wp_update_post(array(
        'ID' => $page_id,
        'post_status' => 'publish',
        'post_title' => 'Home',
        'post_content' => ''
    ));
    echo 'published:' . $page_id;
} else {
    echo 'page-not-found';
}
