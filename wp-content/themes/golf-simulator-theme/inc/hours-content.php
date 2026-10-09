<?php

if (!defined('ABSPATH')) {
    exit;
}

function golf_simulator_theme_hours_content() {
    return array(
        'title' => 'Hours & Access',
        'sections' => array(
            array(
                'id' => 'member',
                'title' => 'Member Access',
                'time' => '24/7',
                'note' => 'Active members have secure facility access 24 hours a day, 7 days a week through the Tee Time Nexus mobile app.',
                'action' => array('label' => 'Explore Memberships', 'route' => '/membership'),
            ),
            array(
                'id' => 'public',
                'title' => 'Public Hours',
                'time' => '10:00 AM to 10:00 PM',
                'note' => 'Open 7 days a week for public bookings.',
                'action' => array('label' => 'Book Public Hours', 'route' => '/book'),
            ),
        ),
    );
}

function golf_simulator_theme_mobile_hours_content() {
    return rest_ensure_response(golf_simulator_theme_hours_content());
}

add_action('rest_api_init', static function () {
    register_rest_route('ttn/v1', '/hours', array(
        'methods' => 'GET',
        'callback' => 'golf_simulator_theme_mobile_hours_content',
        'permission_callback' => '__return_true',
    ));
});
