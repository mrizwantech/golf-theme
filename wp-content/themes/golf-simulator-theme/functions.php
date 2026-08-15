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

function golf_simulator_theme_get_seo_description() {
    $default = 'Indoor golf simulator experience with premium bay rentals, coaching, leagues, and private events for players of all levels.';

    if (is_front_page()) {
        return $default;
    }

    if (is_singular()) {
        $post = get_post();
        if ($post) {
            if (has_excerpt($post)) {
                return wp_trim_words(wp_strip_all_tags($post->post_excerpt), 24, '...');
            }

            $content = wp_strip_all_tags($post->post_content);
            if (!empty($content)) {
                return wp_trim_words($content, 24, '...');
            }
        }
    }

    return get_bloginfo('description') ?: $default;
}

function golf_simulator_theme_get_seo_title() {
    $site_name = get_bloginfo('name');

    if (is_front_page()) {
        if (get_bloginfo('description')) {
            return $site_name . ' | ' . get_bloginfo('description');
        }

        return $site_name . ' | Premium Indoor Golf Simulator Experience';
    }

    if (is_singular()) {
        return get_the_title() . ' | ' . $site_name;
    }

    if (is_archive()) {
        return get_the_archive_title() . ' | ' . $site_name;
    }

    return wp_title('|', false, 'right') . $site_name;
}

function golf_simulator_theme_render_seo_meta() {
    global $wp;

    $site_name = get_bloginfo('name');
    $current_url = home_url(add_query_arg(array(), $wp->request));
    $title = wp_strip_all_tags(golf_simulator_theme_get_seo_title());
    $description = wp_strip_all_tags(golf_simulator_theme_get_seo_description());
    $description = preg_replace('/\s+/', ' ', $description);
    $image_url = get_theme_mod('golf_simulator_og_image', 'https://images.unsplash.com/photo-1535131749006-b7f58c99034b?auto=format&fit=crop&w=1600&q=80');

    echo "<meta name=\"description\" content=\"" . esc_attr($description) . "\" />\n";
    echo "<meta name=\"robots\" content=\"index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1\" />\n";
    echo "<link rel=\"canonical\" href=\"" . esc_url($current_url) . "\" />\n";

    echo "<meta property=\"og:locale\" content=\"" . esc_attr(str_replace('_', '-', get_locale())) . "\" />\n";
    echo "<meta property=\"og:type\" content=\"website\" />\n";
    echo "<meta property=\"og:title\" content=\"" . esc_attr($title) . "\" />\n";
    echo "<meta property=\"og:description\" content=\"" . esc_attr($description) . "\" />\n";
    echo "<meta property=\"og:url\" content=\"" . esc_url($current_url) . "\" />\n";
    echo "<meta property=\"og:site_name\" content=\"" . esc_attr($site_name) . "\" />\n";
    echo "<meta property=\"og:image\" content=\"" . esc_url($image_url) . "\" />\n";
    echo "<meta property=\"og:image:alt\" content=\"" . esc_attr($title) . "\" />\n";

    echo "<meta name=\"twitter:card\" content=\"summary_large_image\" />\n";
    echo "<meta name=\"twitter:title\" content=\"" . esc_attr($title) . "\" />\n";
    echo "<meta name=\"twitter:description\" content=\"" . esc_attr($description) . "\" />\n";
    echo "<meta name=\"twitter:image\" content=\"" . esc_url($image_url) . "\" />\n";
    echo "<meta name=\"twitter:site\" content=\"@teetimenexus\" />\n";
    echo "<meta name=\"twitter:creator\" content=\"@teetimenexus\" />\n";
}
add_action('wp_head', 'golf_simulator_theme_render_seo_meta', 1);

function golf_simulator_theme_render_local_business_schema() {
    if (!is_front_page() && !is_singular()) {
        return;
    }

    $schema = array(
        '@context' => 'https://schema.org',
        '@type' => 'SportsActivityLocation',
        'name' => get_bloginfo('name'),
        'description' => golf_simulator_theme_get_seo_description(),
        'url' => home_url('/'),
        'telephone' => '+1-555-123-4567',
        'email' => 'hello@teetimenexus.com',
        'address' => array(
            '@type' => 'PostalAddress',
            'streetAddress' => '123 Golf Lane',
            'addressLocality' => 'Your City',
            'addressRegion' => 'TX',
            'postalCode' => '75001',
            'addressCountry' => 'US',
        ),
        'openingHours' => 'Mo-Su 10:00-22:00',
        'sameAs' => array(
            'https://www.facebook.com/',
            'https://www.instagram.com/',
        ),
    );

    echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>' . "\n";
}
add_action('wp_head', 'golf_simulator_theme_render_local_business_schema', 2);

function golf_simulator_theme_enqueue_assets() {
    $theme_version = wp_get_theme()->get('Version');
    $style_version = file_exists(get_stylesheet_directory() . '/style.css') ? filemtime(get_stylesheet_directory() . '/style.css') : $theme_version;

    wp_enqueue_style('golf-simulator-theme-style', get_stylesheet_uri(), array(), $style_version);
    wp_enqueue_script(
        'golf-simulator-theme-slider',
        get_template_directory_uri() . '/assets/js/slider.js',
        array(),
        $theme_version,
        true
    );
}
add_action('wp_enqueue_scripts', 'golf_simulator_theme_enqueue_assets');

function golf_simulator_theme_sanitize_color_theme($value) {
    $allowed = array('dark-green', 'light', 'dark-cyan');
    return in_array($value, $allowed, true) ? $value : 'dark-green';
}

function golf_simulator_theme_get_color_theme() {
    return golf_simulator_theme_sanitize_color_theme(get_theme_mod('golf_simulator_color_theme', 'dark-green'));
}

function golf_simulator_theme_body_class($classes) {
    $classes[] = 'theme-' . golf_simulator_theme_get_color_theme();
    return $classes;
}
add_filter('body_class', 'golf_simulator_theme_body_class');

/**
 * Overrides the base :root palette per admin-selected theme; light swaps to white/black,
 * dark-cyan keeps the dark palette but swaps the accent color to #23D5EA.
 */
function golf_simulator_theme_render_color_theme_css() {
    $theme = golf_simulator_theme_get_color_theme();

    if ($theme === 'light') {
        $vars = array(
            '--primary' => '#0f5132',
            '--secondary' => '#0f5132',
            '--bg' => '#ffffff',
            '--text' => '#101010',
            '--muted' => '#4b5563',
            '--shadow' => '0 18px 45px rgba(0, 0, 0, 0.12)',
            '--surface' => '#ffffff',
            '--surface-strong' => '#ffffff',
            '--surface-header' => '#ffffff',
            '--heading' => '#101010',
            '--border-soft' => 'rgba(0, 0, 0, 0.1)',
            '--border-soft-strong' => 'rgba(0, 0, 0, 0.16)',
            '--panel-input-bg' => 'rgba(0, 0, 0, 0.04)',
            '--panel-input-border' => 'rgba(0, 0, 0, 0.14)',
            '--btn-secondary-border' => 'rgba(0, 0, 0, 0.35)',
            '--footer-bg' => '#f3f4f6',
            '--footer-text' => '#101010',
        );
    } elseif ($theme === 'dark-cyan') {
        $vars = array(
            '--primary' => '#23D5EA',
            '--secondary' => '#23D5EA',
        );
    } else {
        return;
    }

    echo '<style id="golf-simulator-theme-color-overrides">:root{';
    foreach ($vars as $property => $value) {
        echo esc_attr($property) . ':' . esc_attr($value) . ';';
    }
    echo '}</style>' . "\n";
}
add_action('wp_head', 'golf_simulator_theme_render_color_theme_css', 5);

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

    $wp_customize->add_setting('golf_simulator_color_theme', array(
        'default' => 'dark-green',
        'sanitize_callback' => 'golf_simulator_theme_sanitize_color_theme',
    ));
    $wp_customize->add_control('golf_simulator_color_theme', array(
        'label' => __('Color Theme', 'golf-simulator-theme'),
        'description' => __('Choose the site-wide color palette.', 'golf-simulator-theme'),
        'section' => 'golf_simulator_branding_section',
        'type' => 'select',
        'choices' => array(
            'dark-green' => __('Dark Green (default)', 'golf-simulator-theme'),
            'light' => __('Light', 'golf-simulator-theme'),
            'dark-cyan' => __('Dark Cyan (#23D5EA)', 'golf-simulator-theme'),
        ),
    ));

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
