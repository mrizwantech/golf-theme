<?php
/*
Template Name: Booking Page
*/
get_header();
?>
<main class="container">
    <article class="entry-content booking-card">
        <div class="kicker">Reserve a Bay</div>
        <h1><?php the_title(); ?></h1>
        <p>Book your Tee Time Nexus bay rental today. Choose from our 4 bays, select your preferred date and time, and submit your reservation request.</p>
        <p><strong>Pricing:</strong> $50 per hour per bay rental</p>
        <p><strong>Business:</strong> Tee Time Nexus<br><strong>Legal Entity:</strong> Far Nexes LLC</p>

        <?php echo do_shortcode('[ttn_booking_form]'); ?>
    </article>
</main>
<?php get_footer(); ?>
