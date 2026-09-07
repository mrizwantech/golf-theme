<?php
/**
 * Template Name: Membership Page
 */
get_header();
$selected_package = isset($_GET['package']) ? strtoupper(sanitize_text_field(wp_unslash($_GET['package']))) : '';
?>
<main class="membership-page-shell">
    <section class="section">
        <div class="container">
            <?php if ($selected_package) : ?>
                <?php echo golf_simulator_theme_render_membership_signup($selected_package); ?>
            <?php else : ?>
            <div class="section-heading section-heading-center">
                <span class="kicker">Membership</span>
                <h1 class="section-title">Choose your level</h1>
            </div>

            <?php echo do_shortcode('[golf_simulator_membership_packages]'); ?>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php get_footer(); ?>
