<?php
/**
 * Template Name: Membership Page
 */
get_header();
?>
<main class="membership-page-shell">
    <section class="section">
        <div class="container">
            <div class="section-heading section-heading-center">
                <span class="kicker">Membership</span>
                <h1 class="section-title">Choose your level</h1>
            </div>

            <?php echo do_shortcode('[golf_simulator_membership_packages]'); ?>
        </div>
    </section>
</main>
<?php get_footer(); ?>
