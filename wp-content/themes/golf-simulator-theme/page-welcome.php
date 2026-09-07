<?php
/**
 * Template for the welcome signup page.
 */
get_header();
?>
<main class="welcome-page-shell">
    <section class="welcome-signup-panel">
        <span class="welcome-kicker">Grand Opening</span>
        <h1>Be First to Tee Off</h1>
        <p class="welcome-subtitle">Your next round starts here.</p>
        <p class="welcome-copy">Tee Time Nexus is getting ready to open in Mooresville.</p>
        <p class="welcome-copy">Join our list for <strong>grand opening updates, early booking opportunities, and special launch announcements.</strong></p>

        <?php echo do_shortcode('[welcome_signup_form]'); ?>
    </section>
</main>
<?php get_footer(); ?>
