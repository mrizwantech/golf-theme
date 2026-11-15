<?php

/**
 * Android-only app promotion; iOS stays hidden until its store listing is available.
 * The browser remembers dismissal for seven days and waits for the launch splash.
 */
function golf_simulator_theme_enqueue_mobile_app_prompt() {
    wp_enqueue_script(
        'golf-simulator-theme-mobile-app-prompt',
        get_template_directory_uri() . '/assets/js/mobile-app-prompt.js',
        array(),
        filemtime(get_template_directory() . '/assets/js/mobile-app-prompt.js'),
        true
    );
}
add_action('wp_enqueue_scripts', 'golf_simulator_theme_enqueue_mobile_app_prompt');

function golf_simulator_theme_render_mobile_app_prompt() {
    ?>
    <dialog id="ttn-mobile-app-prompt" class="mobile-app-prompt" aria-labelledby="ttn-mobile-app-title" aria-describedby="ttn-mobile-app-description">
        <button type="button" class="mobile-app-prompt-close" data-app-dismiss aria-label="<?php echo esc_attr__('Close app download popup', 'golf-simulator-theme'); ?>">&times;</button>
        <span class="mobile-app-prompt-kicker"><?php esc_html_e('Tee Time Nexus for Android', 'golf-simulator-theme'); ?></span>
        <h2 id="ttn-mobile-app-title"><?php esc_html_e('Take your next tee time with you', 'golf-simulator-theme'); ?></h2>
        <p id="ttn-mobile-app-description"><?php esc_html_e('Download the Tee Time Nexus app on your Android phone.', 'golf-simulator-theme'); ?></p>
        <a class="btn btn-primary" data-app-download href="<?php echo esc_url('https://play.google.com/store/apps/details?id=com.teetimenexus.app&hl=en_US'); ?>"><?php esc_html_e('Download on Google Play', 'golf-simulator-theme'); ?></a>
        <button type="button" class="mobile-app-prompt-later" data-app-dismiss><?php esc_html_e('Continue on website', 'golf-simulator-theme'); ?></button>
    </dialog>
    <?php
}
add_action('wp_footer', 'golf_simulator_theme_render_mobile_app_prompt');
