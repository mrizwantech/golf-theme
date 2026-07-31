<?php

function golf_simulator_theme_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo');
    add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script'));

    register_nav_menus(array(
        'primary' => __('Primary Menu', 'golf-simulator-theme'),
    ));
}
add_action('after_setup_theme', 'golf_simulator_theme_setup');

function golf_simulator_theme_enqueue_assets() {
    wp_enqueue_style('golf-simulator-theme-style', get_stylesheet_uri(), array(), wp_get_theme()->get('Version'));
    wp_enqueue_script(
        'golf-simulator-theme-slider',
        get_template_directory_uri() . '/assets/js/slider.js',
        array(),
        wp_get_theme()->get('Version'),
        true
    );
}
add_action('wp_enqueue_scripts', 'golf_simulator_theme_enqueue_assets');

function golf_simulator_theme_customize_register($wp_customize) {
    $wp_customize->add_section('golf_simulator_branding_section', array(
        'title' => __('Theme Branding', 'golf-simulator-theme'),
        'priority' => 20,
    ));

    $wp_customize->add_setting('golf_simulator_site_logo', array(
        'default' => '',
        'sanitize_callback' => 'esc_url_raw',
    ));
    $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'golf_simulator_site_logo', array(
        'label' => __('Header Logo', 'golf-simulator-theme'),
        'section' => 'golf_simulator_branding_section',
        'settings' => 'golf_simulator_site_logo',
    )));

    $wp_customize->add_section('golf_simulator_slider_section', array(
        'title' => __('Homepage Slider', 'golf-simulator-theme'),
        'priority' => 30,
    ));

    $slides = array(
        1 => array(
            'label' => __('Slide 1', 'golf-simulator-theme'),
            'default_image' => 'https://images.unsplash.com/photo-1535131749006-b7f58c99034b?auto=format&fit=crop&w=1600&q=80',
            'default_kicker' => 'Tee Time Nexus • Far Nexes LLC',
            'default_heading' => 'Indoor golf that feels like your next championship round.',
            'default_text' => 'Welcome to Tee Time Nexus, your modern golf simulator destination for practice, entertainment, leagues, and business events.',
            'default_button_1' => 'View Packages',
            'default_button_1_url' => '#packages',
            'default_button_2' => 'Book a Session',
            'default_button_2_url' => '#contact',
        ),
        2 => array(
            'label' => __('Slide 2', 'golf-simulator-theme'),
            'default_image' => 'https://images.unsplash.com/photo-1593111774278-0b6b02b7961c?auto=format&fit=crop&w=1600&q=80',
            'default_kicker' => 'Practice. Play. Perform.',
            'default_heading' => 'Train smarter with high-performance simulator sessions.',
            'default_text' => 'Use Tee Time Nexus for coaching, private play, and feature-packed bay rentals that keep every visit exciting.',
            'default_button_1' => 'Explore Services',
            'default_button_1_url' => '#services',
            'default_button_2' => 'Reserve a Bay',
            'default_button_2_url' => '#contact',
        ),
        3 => array(
            'label' => __('Slide 3', 'golf-simulator-theme'),
            'default_image' => 'https://images.unsplash.com/photo-1517466787929-bc90951d0974?auto=format&fit=crop&w=1600&q=80',
            'default_kicker' => 'Book Your Next Session',
            'default_heading' => 'Built for new customers, leagues, and premium events.',
            'default_text' => 'Launch your local golf simulator business with a polished landing page that highlights fast bookings and simple pricing.',
            'default_button_1' => 'See Pricing',
            'default_button_1_url' => '#packages',
            'default_button_2' => 'Contact Us',
            'default_button_2_url' => '#contact',
        ),
    );

    foreach ($slides as $number => $slide) {
        $wp_customize->add_setting('golf_simulator_slide_' . $number . '_image', array(
            'default' => $slide['default_image'],
            'sanitize_callback' => 'esc_url_raw',
        ));
        $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'golf_simulator_slide_' . $number . '_image', array(
            'label' => $slide['label'] . ' Image',
            'section' => 'golf_simulator_slider_section',
            'settings' => 'golf_simulator_slide_' . $number . '_image',
        )));

        $wp_customize->add_setting('golf_simulator_slide_' . $number . '_kicker', array(
            'default' => $slide['default_kicker'],
            'sanitize_callback' => 'sanitize_text_field',
        ));
        $wp_customize->add_control('golf_simulator_slide_' . $number . '_kicker', array(
            'label' => $slide['label'] . ' Kicker',
            'section' => 'golf_simulator_slider_section',
            'type' => 'text',
        ));

        $wp_customize->add_setting('golf_simulator_slide_' . $number . '_heading', array(
            'default' => $slide['default_heading'],
            'sanitize_callback' => 'sanitize_text_field',
        ));
        $wp_customize->add_control('golf_simulator_slide_' . $number . '_heading', array(
            'label' => $slide['label'] . ' Heading',
            'section' => 'golf_simulator_slider_section',
            'type' => 'text',
        ));

        $wp_customize->add_setting('golf_simulator_slide_' . $number . '_text', array(
            'default' => $slide['default_text'],
            'sanitize_callback' => 'sanitize_textarea_field',
        ));
        $wp_customize->add_control('golf_simulator_slide_' . $number . '_text', array(
            'label' => $slide['label'] . ' Text',
            'section' => 'golf_simulator_slider_section',
            'type' => 'textarea',
        ));

        $wp_customize->add_setting('golf_simulator_slide_' . $number . '_button_1', array(
            'default' => $slide['default_button_1'],
            'sanitize_callback' => 'sanitize_text_field',
        ));
        $wp_customize->add_control('golf_simulator_slide_' . $number . '_button_1', array(
            'label' => $slide['label'] . ' Button 1 Text',
            'section' => 'golf_simulator_slider_section',
            'type' => 'text',
        ));

        $wp_customize->add_setting('golf_simulator_slide_' . $number . '_button_1_url', array(
            'default' => $slide['default_button_1_url'],
            'sanitize_callback' => 'esc_url_raw',
        ));
        $wp_customize->add_control('golf_simulator_slide_' . $number . '_button_1_url', array(
            'label' => $slide['label'] . ' Button 1 Link',
            'section' => 'golf_simulator_slider_section',
            'type' => 'text',
        ));

        $wp_customize->add_setting('golf_simulator_slide_' . $number . '_button_2', array(
            'default' => $slide['default_button_2'],
            'sanitize_callback' => 'sanitize_text_field',
        ));
        $wp_customize->add_control('golf_simulator_slide_' . $number . '_button_2', array(
            'label' => $slide['label'] . ' Button 2 Text',
            'section' => 'golf_simulator_slider_section',
            'type' => 'text',
        ));

        $wp_customize->add_setting('golf_simulator_slide_' . $number . '_button_2_url', array(
            'default' => $slide['default_button_2_url'],
            'sanitize_callback' => 'esc_url_raw',
        ));
        $wp_customize->add_control('golf_simulator_slide_' . $number . '_button_2_url', array(
            'label' => $slide['label'] . ' Button 2 Link',
            'section' => 'golf_simulator_slider_section',
            'type' => 'text',
        ));
    }
}
add_action('customize_register', 'golf_simulator_theme_customize_register');

function golf_simulator_theme_menu() {
    if (has_nav_menu('primary')) {
        wp_nav_menu(array(
            'theme_location' => 'primary',
            'container'      => 'nav',
            'container_class'=> 'site-nav',
            'menu_class'     => '',
            'fallback_cb'    => false,
        ));
    } else {
        echo '<nav class="site-nav"><ul><li><a href="' . esc_url(home_url('/')) . '">Home</a></li><li><a href="' . esc_url(home_url('/about-us/')) . '">About</a></li><li><a href="' . esc_url(home_url('/contact/')) . '">Contact</a></li></ul></nav>';
    }
}
