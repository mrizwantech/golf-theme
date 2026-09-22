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
        <p>Create an account before booking to save your booking history, manage future reservations, unlock member perks, and check out faster next time. If you already have an account, log in to manage your booking and access member benefits.</p>
        <p><strong>Pricing:</strong> $50 per hour per bay rental</p>
        <p><strong>Business:</strong> Tee Time Nexus<br><strong>Legal Entity:</strong> Far Nexes LLC</p>

        <?php echo do_shortcode('[ttn_booking_form]'); ?>
    </article>
</main>
<?php get_footer(); ?>
