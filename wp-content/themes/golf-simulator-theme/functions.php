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

require_once get_template_directory() . '/inc/email-template.php';
require_once get_template_directory() . '/inc/membership.php';
require_once get_template_directory() . '/inc/auth.php';
require_once get_template_directory() . '/inc/contact-email.php';
require_once get_template_directory() . '/inc/welcome-signup.php';
require_once get_template_directory() . '/inc/mobile-membership-api.php';
require_once get_template_directory() . '/inc/home-content.php';
require_once get_template_directory() . '/inc/golf-technology-content.php';
require_once get_template_directory() . '/inc/mobile-technology-api.php';
require_once get_template_directory() . '/inc/hours-content.php';

function golf_simulator_theme_ensure_my_bookings_page() {
    $page = get_page_by_path('my-bookings');
    if (!$page) {
        $page_id = wp_insert_post(array(
            'post_title' => 'My Bookings',
            'post_name' => 'my-bookings',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_content' => '',
        ));
        if (is_wp_error($page_id) || !$page_id) {
            return;
        }
        $page = get_post($page_id);
    }

    if ($page && get_post_meta($page->ID, '_wp_page_template', true) !== 'page-my-bookings.php') {
        update_post_meta($page->ID, '_wp_page_template', 'page-my-bookings.php');
    }
}
add_action('init', 'golf_simulator_theme_ensure_my_bookings_page', 20);

function golf_simulator_theme_ensure_about_page() {
    $page = get_page_by_path('about-us');
    if (!$page) {
        $page_id = wp_insert_post(array(
            'post_title' => 'About Us',
            'post_name' => 'about-us',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_content' => '',
        ));
        if (is_wp_error($page_id) || !$page_id) {
            return;
        }
        $page = get_post($page_id);
    }

    if ($page && get_post_meta($page->ID, '_wp_page_template', true) !== 'page-about-us.php') {
        update_post_meta($page->ID, '_wp_page_template', 'page-about-us.php');
    }
}
add_action('init', 'golf_simulator_theme_ensure_about_page', 20);

function golf_simulator_theme_ensure_golf_technology_page() {
    $page = get_page_by_path('golf-technology');
    if (!$page) {
        $technology_pages = get_posts(array(
            'post_type' => 'page',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'meta_key' => '_wp_page_template',
            'meta_value' => 'page-golf-technology.php',
        ));
        $page = $technology_pages[0] ?? null;
    }

    if (!$page) {
        $page_id = wp_insert_post(array(
            'post_title' => 'Golf Technology',
            'post_name' => 'golf-technology',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_content' => '',
        ));
        if (is_wp_error($page_id) || !$page_id) {
            return;
        }
        $page = get_post($page_id);
    }

    if ($page && get_post_meta($page->ID, '_wp_page_template', true) !== 'page-golf-technology.php') {
        update_post_meta($page->ID, '_wp_page_template', 'page-golf-technology.php');
    }
}
add_action('init', 'golf_simulator_theme_ensure_golf_technology_page', 20);

function golf_simulator_theme_is_golf_technology_page($post) {
    return $post instanceof WP_Post
        && 'page' === $post->post_type
        && ('golf-technology' === $post->post_name || 'page-golf-technology.php' === get_post_meta($post->ID, '_wp_page_template', true));
}

function golf_simulator_theme_golf_technology_features() {
    return golf_simulator_theme_golf_technology_sections();
}

function golf_simulator_theme_add_golf_technology_feature_gifs_box($post) {
    if (!golf_simulator_theme_is_golf_technology_page($post)) {
        return;
    }

    add_meta_box(
        'golf_simulator_technology_feature_gifs',
        __('Feature GIFs', 'golf-simulator-theme'),
        'golf_simulator_theme_render_golf_technology_feature_gifs_box',
        'page',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes_page', 'golf_simulator_theme_add_golf_technology_feature_gifs_box');

function golf_simulator_theme_render_golf_technology_feature_gifs_box($post) {
    wp_nonce_field('golf_simulator_technology_feature_gifs', 'golf_simulator_technology_feature_gifs_nonce');
    $saved_gifs = get_post_meta($post->ID, '_golf_technology_feature_gifs', true);
    $saved_gifs = is_array($saved_gifs) ? $saved_gifs : array();
    ?>
    <p><?php esc_html_e('Choose a GIF or image for each of the 13 feature sections. Existing media from merged features is reused when the section has no image. Remove clears that section and its legacy media choices.', 'golf-simulator-theme'); ?></p>
    <div class="golf-technology-feature-gif-fields">
        <?php foreach (golf_simulator_theme_golf_technology_features() as $feature_id => $feature) : ?>
            <?php
            $attachment_id = golf_simulator_theme_golf_technology_media_id($saved_gifs, $feature_id);
            $image_url = $attachment_id ? wp_get_attachment_url($attachment_id) : '';
            $field_id = 'golf-technology-gif-' . sanitize_html_class($feature_id);
            ?>
            <div class="golf-technology-feature-gif-field" data-feature="<?php echo esc_attr($feature_id); ?>">
                <strong><?php echo esc_html($feature['number'] . ' - ' . $feature['label']); ?></strong>
                <input type="hidden" id="<?php echo esc_attr($field_id); ?>" name="golf_technology_feature_gifs[<?php echo esc_attr($feature_id); ?>]" value="<?php echo esc_attr($attachment_id); ?>">
                <div class="golf-technology-feature-gif-preview">
                    <?php if ($image_url) : ?>
                        <img src="<?php echo esc_url($image_url); ?>" alt="">
                    <?php endif; ?>
                </div>
                <button type="button" class="button golf-technology-feature-gif-select" data-field="<?php echo esc_attr($field_id); ?>"><?php esc_html_e('Choose GIF / Image', 'golf-simulator-theme'); ?></button>
                <button type="button" class="button-link golf-technology-feature-gif-remove" data-field="<?php echo esc_attr($field_id); ?>"><?php esc_html_e('Remove', 'golf-simulator-theme'); ?></button>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

function golf_simulator_theme_save_golf_technology_feature_gifs($post_id) {
    $post = get_post($post_id);
    if (!golf_simulator_theme_is_golf_technology_page($post)) {
        return;
    }

    if (!isset($_POST['golf_simulator_technology_feature_gifs_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['golf_simulator_technology_feature_gifs_nonce'])), 'golf_simulator_technology_feature_gifs')) {
        return;
    }

    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) {
        return;
    }

    $submitted_gifs = isset($_POST['golf_technology_feature_gifs']) && is_array($_POST['golf_technology_feature_gifs'])
        ? wp_unslash($_POST['golf_technology_feature_gifs'])
        : array();
    $saved_gifs = get_post_meta($post_id, '_golf_technology_feature_gifs', true);
    $saved_gifs = is_array($saved_gifs) ? $saved_gifs : array();

    foreach (golf_simulator_theme_golf_technology_features() as $feature_id => $feature_label) {
        if (!array_key_exists($feature_id, $submitted_gifs)) {
            continue;
        }
        $attachment_id = absint($submitted_gifs[$feature_id] ?? 0);
        unset($saved_gifs[$feature_id]);
        if (!$attachment_id) {
            foreach ($feature_label['aliases'] ?? array() as $alias) {
                unset($saved_gifs[$alias]);
            }
        }
        if ($attachment_id && wp_attachment_is_image($attachment_id)) {
            $saved_gifs[$feature_id] = $attachment_id;
        }
    }

    update_post_meta($post_id, '_golf_technology_feature_gifs', $saved_gifs);
}
add_action('save_post_page', 'golf_simulator_theme_save_golf_technology_feature_gifs');

function golf_simulator_theme_golf_technology_feature_gif_admin_assets($hook) {
    if (!in_array($hook, array('post.php', 'post-new.php'), true)) {
        return;
    }

    $post_id = absint($_GET['post'] ?? 0);
    $post = $post_id ? get_post($post_id) : null;
    if (!golf_simulator_theme_is_golf_technology_page($post)) {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_script('jquery');
}
add_action('admin_enqueue_scripts', 'golf_simulator_theme_golf_technology_feature_gif_admin_assets');

function golf_simulator_theme_golf_technology_feature_gif_admin_script() {
    $post_id = absint($_GET['post'] ?? 0);
    $post = $post_id ? get_post($post_id) : null;
    if (!golf_simulator_theme_is_golf_technology_page($post)) {
        return;
    }
    ?>
    <script>
        jQuery(function ($) {
            $(document).on('click', '.golf-technology-feature-gif-select', function (event) {
                event.preventDefault();
                const button = $(this);
                const frame = wp.media({
                    title: 'Choose feature GIF or image',
                    button: { text: 'Use this media' },
                    library: { type: 'image' },
                    multiple: false
                });
                frame.on('select', function () {
                    const attachment = frame.state().get('selection').first().toJSON();
                    $('#' + button.data('field')).val(attachment.id);
                    button.siblings('.golf-technology-feature-gif-preview').html($('<img>', { src: attachment.url, alt: '' }));
                });
                frame.open();
            });

            $(document).on('click', '.golf-technology-feature-gif-remove', function (event) {
                event.preventDefault();
                const button = $(this);
                $('#' + button.data('field')).val('');
                button.siblings('.golf-technology-feature-gif-preview').empty();
            });
        });
    </script>
    <?php
}
add_action('admin_footer-post.php', 'golf_simulator_theme_golf_technology_feature_gif_admin_script');

function golf_simulator_theme_render_golf_technology_feature_gif($feature_id) {
    $features = golf_simulator_theme_golf_technology_features();
    if (!isset($features[$feature_id])) {
        return '';
    }

    $saved_gifs = get_post_meta(get_the_ID(), '_golf_technology_feature_gifs', true);
    $attachment_id = golf_simulator_theme_golf_technology_media_id($saved_gifs, $feature_id);
    $image_url = $attachment_id ? wp_get_attachment_url($attachment_id) : '';
    if (!$image_url) {
        return '';
    }

    return '<figure class="golf-technology-feature-gif"><img src="' . esc_url($image_url) . '" alt="' . esc_attr($features[$feature_id]['label'] . ' feature GIF') . '" loading="lazy"></figure>';
}

function golf_simulator_theme_ensure_hours_page() {
    $page = get_page_by_path('hours');
    if (!$page) {
        $page_id = wp_insert_post(array(
            'post_title' => 'Hours & Access',
            'post_name' => 'hours',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_content' => '',
        ));
        if (is_wp_error($page_id) || !$page_id) {
            return;
        }
        $page = get_post($page_id);
    }

    if ($page && get_post_meta($page->ID, '_wp_page_template', true) !== 'page-hours.php') {
        update_post_meta($page->ID, '_wp_page_template', 'page-hours.php');
    }
}
add_action('init', 'golf_simulator_theme_ensure_hours_page', 20);

function golf_simulator_theme_ensure_contact_page() {
    $page = get_page_by_path('contact');
    if (!$page) {
        $page_id = wp_insert_post(array(
            'post_title' => 'Contact Us',
            'post_name' => 'contact',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_content' => '',
        ));
        if (is_wp_error($page_id) || !$page_id) {
            return;
        }
        $page = get_post($page_id);
    }

    if ($page && get_post_meta($page->ID, '_wp_page_template', true) !== 'page-contact.php') {
        update_post_meta($page->ID, '_wp_page_template', 'page-contact.php');
    }
}
add_action('init', 'golf_simulator_theme_ensure_contact_page', 20);

function golf_simulator_theme_handle_contact_form() {
    $redirect_url = home_url('/contact/');
    $nonce = sanitize_text_field(wp_unslash($_POST['contact_nonce'] ?? ''));
    if (!wp_verify_nonce($nonce, 'golf_simulator_contact_submit')) {
        wp_safe_redirect(add_query_arg('contact', 'error', $redirect_url));
        exit;
    }

    if (!empty($_POST['website'])) {
        wp_safe_redirect(add_query_arg('contact', 'sent', $redirect_url));
        exit;
    }

    $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
    $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));

    if (!$name || !is_email($email) || !$message || strlen($message) > 5000) {
        wp_safe_redirect(add_query_arg('contact', 'error', $redirect_url));
        exit;
    }

    $email_body = "New message from the Tee Time Nexus contact form.\n\n"
        . "Name: {$name}\n"
        . "Email: {$email}\n"
        . "Phone: " . ($phone ?: 'Not provided') . "\n\n"
        . "Message:\n{$message}";
    $headers = array('Reply-To: ' . $name . ' <' . $email . '>');
    $sent = wp_mail('sales@teetimenexus.com', 'Website contact message from ' . $name, $email_body, $headers);

    wp_safe_redirect(add_query_arg('contact', $sent ? 'sent' : 'send-error', $redirect_url));
    exit;
}
add_action('admin_post_golf_simulator_contact_submit', 'golf_simulator_theme_handle_contact_form');
add_action('admin_post_nopriv_golf_simulator_contact_submit', 'golf_simulator_theme_handle_contact_form');

function golf_simulator_theme_render_launch_screen() {
    if (!is_front_page() || is_user_logged_in()) {
        return;
    }

    $template = get_template_directory() . '/page-splash.php';
    if (!file_exists($template)) {
        return;
    }

    include $template;
}
add_action('wp_footer', 'golf_simulator_theme_render_launch_screen', 999);

function golf_simulator_theme_get_seo_description() {
    $default = 'Play indoor golf in Mooresville, NC at Tee Time Nexus. Book GOLFZON TwoVision NX simulator bays for practice, full rounds, and year-round play.';

    if (is_front_page()) {
        return $default;
    }

    if (is_singular()) {
        $post = get_post();
        if ($post) {
            $page_descriptions = array(
                'about-us' => 'Meet Tee Time Nexus, a locally owned indoor golf destination in Mooresville, North Carolina, built for year-round play, practice, and time with friends.',
                'golf-technology' => 'Explore GOLFZON TwoVision NX golf technology at Tee Time Nexus, including its moving swing plate, multi-surface play, precision putting, and shot tracking.',
                'hours' => 'Find Tee Time Nexus weekday and weekend hours in Mooresville, NC, plus details about secure 24/7 facility access for members.',
                'contact' => 'Contact Tee Time Nexus in Mooresville, NC about bookings, memberships, or visiting. Call, email, or send our team a message.',
                'book-a-bay' => 'Book an indoor golf simulator bay at Tee Time Nexus in Mooresville, NC. Choose your bay, date, duration, and available tee time.',
                'membership' => 'Explore Tee Time Nexus memberships for indoor golf in Mooresville, NC, including member access, daily playing hours, and simulator benefits.',
            );
            if (isset($page_descriptions[$post->post_name])) {
                return $page_descriptions[$post->post_name];
            }

            if (has_excerpt($post)) {
                return wp_html_excerpt(wp_strip_all_tags($post->post_excerpt), 160, '...');
            }

            $content = wp_strip_all_tags($post->post_content);
            if (!empty($content)) {
                return wp_html_excerpt($content, 160, '...');
            }
        }
    }

    return get_bloginfo('description') ?: $default;
}

function golf_simulator_theme_get_seo_title() {
    $site_name = get_bloginfo('name');

    if (is_front_page()) {
        return $site_name . ' | Indoor Golf in Mooresville, NC';
    }

    if (is_singular()) {
        $post = get_post();
        $page_titles = array(
            'about-us' => 'About Tee Time Nexus | Indoor Golf in Mooresville, NC',
            'golf-technology' => 'Golf Technology | GOLFZON TwoVision NX | ' . $site_name,
            'hours' => 'Hours & 24/7 Member Access | ' . $site_name,
            'contact' => 'Contact Tee Time Nexus | ' . $site_name,
            'book-a-bay' => 'Book an Indoor Golf Simulator | ' . $site_name,
            'membership' => 'Indoor Golf Memberships | ' . $site_name,
        );
        if ($post && isset($page_titles[$post->post_name])) {
            return $page_titles[$post->post_name];
        }
        return get_the_title() . ' | ' . $site_name;
    }

    if (is_archive()) {
        return get_the_archive_title() . ' | ' . $site_name;
    }

    if (is_search()) {
        return sprintf('Search results for %s | %s', get_search_query(), $site_name);
    }

    if (is_404()) {
        return 'Page not found | ' . $site_name;
    }

    return $site_name . ' | Indoor Golf in Mooresville, NC';
}
add_filter('pre_get_document_title', 'golf_simulator_theme_get_seo_title');

function golf_simulator_theme_filter_robots($robots) {
    if (is_search() || is_404() || is_attachment()) {
        $robots['noindex'] = true;
        return $robots;
    }

    $robots['max-image-preview'] = 'large';
    $robots['max-snippet'] = -1;
    $robots['max-video-preview'] = -1;

    return $robots;
}
add_filter('wp_robots', 'golf_simulator_theme_filter_robots');

function golf_simulator_theme_render_seo_meta() {
    global $wp;

    remove_action('wp_head', 'rel_canonical');

    $site_name = get_bloginfo('name');
    if (is_front_page()) {
        $current_url = home_url('/');
    } elseif (is_singular()) {
        $current_url = wp_get_canonical_url();
    } else {
        $current_url = home_url(user_trailingslashit($wp->request));
    }
    $current_url = $current_url ?: home_url('/');
    $title = wp_strip_all_tags(golf_simulator_theme_get_seo_title());
    $description = wp_strip_all_tags(golf_simulator_theme_get_seo_description());
    $description = preg_replace('/\s+/', ' ', $description);
    $image_url = is_singular() && has_post_thumbnail()
        ? get_the_post_thumbnail_url(null, 'full')
        : '';
    if (!$image_url) {
        $image_url = get_theme_mod('golf_simulator_og_image');
    }
    if (!$image_url) {
        $image_url = get_theme_mod('golf_simulator_slide_1_image', 'https://images.unsplash.com/photo-1535131749006-b7f58c99034b?auto=format&fit=crop&w=1600&q=80');
    }

    echo "<meta name=\"description\" content=\"" . esc_attr($description) . "\" />\n";
    echo "<link rel=\"canonical\" href=\"" . esc_url($current_url) . "\" />\n";

    echo "<meta property=\"og:locale\" content=\"" . esc_attr(str_replace('_', '-', get_locale())) . "\" />\n";
    echo "<meta property=\"og:type\" content=\"" . (is_singular('post') ? 'article' : 'website') . "\" />\n";
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
        'image' => get_theme_mod('golf_simulator_og_image') ?: get_theme_mod('golf_simulator_slide_1_image', 'https://images.unsplash.com/photo-1535131749006-b7f58c99034b?auto=format&fit=crop&w=1600&q=80'),
        'hasMap' => 'https://maps.app.goo.gl/Fu5JUodn9BqbYo7A8',
        'telephone' => '+1-980-503-3288',
        'email' => 'sales@teetimenexus.com',
        'address' => array(
            '@type' => 'PostalAddress',
            'streetAddress' => '2785 Charlotte Hwy, Suites 11 & 12',
            'addressLocality' => 'Mooresville',
            'addressRegion' => 'NC',
            'postalCode' => '28117',
            'addressCountry' => 'US',
        ),
        'openingHours' => array('Mo-Fr 10:00-21:00', 'Sa-Su 09:00-22:00'),
        'areaServed' => array(
            '@type' => 'City',
            'name' => 'Mooresville, North Carolina',
        ),
        'sameAs' => array(
            'https://www.facebook.com/teetimenexus',
            'https://www.instagram.com/teetimenexus/',
            'https://www.tiktok.com/@teetimenexus',
            'https://www.youtube.com/@TeeTimeNexus',
        ),
    );

    echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>' . "\n";
}
add_action('wp_head', 'golf_simulator_theme_render_local_business_schema', 2);

function golf_simulator_theme_preload_front_page_hero() {
    if (!is_front_page() || is_user_logged_in()) {
        return;
    }

    $hero_image = get_theme_mod(
        'golf_simulator_slide_1_image',
        'https://images.unsplash.com/photo-1535131749006-b7f58c99034b?auto=format&fit=crop&w=1600&q=80'
    );
    if ($hero_image) {
        echo '<link rel="preload" as="image" fetchpriority="high" href="' . esc_url($hero_image) . '">' . "\n";
    }
}
add_action('wp_head', 'golf_simulator_theme_preload_front_page_hero', 1);

function golf_simulator_theme_enqueue_assets() {
    $theme_version = wp_get_theme()->get('Version');
    $style_version = file_exists(get_stylesheet_directory() . '/style.css') ? filemtime(get_stylesheet_directory() . '/style.css') : $theme_version;
    $slider_version = file_exists(get_stylesheet_directory() . '/assets/js/slider.js') ? filemtime(get_stylesheet_directory() . '/assets/js/slider.js') : $theme_version;

    wp_enqueue_style('golf-simulator-theme-style', get_stylesheet_uri(), array(), $style_version);
    wp_enqueue_script(
        'golf-simulator-theme-navigation',
        get_template_directory_uri() . '/assets/js/navigation.js',
        array(),
        filemtime(get_template_directory() . '/assets/js/navigation.js'),
        true
    );
    wp_enqueue_script(
        'golf-simulator-theme-slider',
        get_template_directory_uri() . '/assets/js/slider.js',
        array(),
        $slider_version,
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
            '--primary-rgb' => '15, 81, 50',
            '--primary-hover' => '#147a45',
            '--primary-contrast' => '#ffffff',
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
            '--primary-rgb' => '35, 213, 234',
            '--primary-hover' => '#5be3f3',
            '--primary-contrast' => '#0a1a1d',
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
// Priority 100 ensures this prints after the enqueued stylesheet's own :root block.
add_action('wp_head', 'golf_simulator_theme_render_color_theme_css', 100);

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
            'default_kicker' => 'Early Bird Memberships',
            'default_heading' => 'Lock In Your Early Bird Rate',
            'default_text' => 'Be among the first to join Tee Time Nexus and become a Founding Member. Unlock exclusive Early Bird membership benefits before we open. Become a Founding Member — Early Bird memberships available.',
            'default_button_1' => 'Explore Services',
            'default_button_1_url' => '#services',
            'default_button_2' => 'Reserve a Bay',
            'default_button_2_url' => '#contact',
        ),
        3 => array(
            'label' => __('Slide 3', 'golf-simulator-theme'),
            'default_image' => 'https://images.unsplash.com/photo-1517466787929-bc90951d0974?auto=format&fit=crop&w=1600&q=80',
            'default_kicker' => 'NEXT-LEVEL INDOOR GOLF',
            'default_heading' => 'Technology That Makes Every Shot Feel Real.',
            'default_text' => 'Experience advanced golf simulation with realistic course conditions designed for a more immersive indoor golf experience.',
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

    // Feature Cards (Video / GIF / Image) Section
    $wp_customize->add_section('golf_simulator_feature_cards_section', array(
        'title'    => __('Homepage Feature Cards Media & Content', 'golf-simulator-theme'),
        'priority' => 35,
    ));

    $card_defaults = array(
        1 => 'Auto Tee (Card 1)',
        2 => 'Dynamic Swing Plate (Card 2)',
        3 => 'High-Speed Swing Sensors (Card 3)',
        4 => 'Realistic Bunker Play (Card 4)',
        5 => 'Precision Putting (Card 5)',
        6 => 'Network Play (Card 6)',
    );

    foreach ($card_defaults as $card_num => $card_label) {
        $wp_customize->add_setting('golf_simulator_feature_' . $card_num . '_media', array(
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
        ));
        $wp_customize->add_control(new WP_Customize_Upload_Control($wp_customize, 'golf_simulator_feature_' . $card_num . '_media', array(
            'label'       => $card_label . ' Video or GIF / Image',
            'description' => __('Upload an MP4/WebM video or an animated GIF.', 'golf-simulator-theme'),
            'section'     => 'golf_simulator_feature_cards_section',
        )));

        $wp_customize->add_setting('golf_simulator_feature_' . $card_num . '_title', array(
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        $wp_customize->add_control('golf_simulator_feature_' . $card_num . '_title', array(
            'label'       => $card_label . ' Title / Kicker',
            'section'     => 'golf_simulator_feature_cards_section',
            'type'        => 'text',
        ));

        $wp_customize->add_setting('golf_simulator_feature_' . $card_num . '_text', array(
            'default'           => '',
            'sanitize_callback' => 'sanitize_textarea_field',
        ));
        $wp_customize->add_control('golf_simulator_feature_' . $card_num . '_text', array(
            'label'       => $card_label . ' Description',
            'section'     => 'golf_simulator_feature_cards_section',
            'type'        => 'textarea',
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
        echo '<nav class="site-nav"><ul><li><a href="' . esc_url(home_url('/')) . '">Home</a></li><li><a href="' . esc_url(home_url('/about-us/')) . '">About</a></li><li><a href="' . esc_url(home_url('/golf-technology/')) . '">Golf Technology</a></li><li><a href="' . esc_url(home_url('/hours/')) . '">Hours</a></li><li><a href="' . esc_url(home_url('/contact/')) . '">Contact</a></li></ul></nav>';
    }
}
