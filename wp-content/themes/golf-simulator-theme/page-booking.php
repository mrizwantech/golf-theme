<?php
/*
Template Name: Booking Page
*/
get_header();

$hourly_price = function_exists('ttn_booking_get_hourly_price') ? ttn_booking_get_hourly_price() : (float) get_option('ttn_standard_hourly_price', 50);
$formatted_price = (floor($hourly_price) == $hourly_price) ? number_format($hourly_price, 0) : number_format($hourly_price, 2);
?>
<main class="container">
    <article class="entry-content booking-card">
        <div class="kicker">Reserve a Bay</div>
        <h1><?php the_title(); ?></h1>
        <p>Reserve your bay and enjoy an immersive indoor golf experience.</p>
        <p><strong>Pricing:</strong> $<?php echo esc_html($formatted_price); ?> per hour per bay rental</p>
        <p><strong>Business:</strong> Tee Time Nexus<br><strong>Legal Entity:</strong> Far Nexes LLC</p>

        <?php echo do_shortcode('[ttn_booking_form]'); ?>
    </article>
</main>
<?php get_footer(); ?>
