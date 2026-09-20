<?php
/*
Template Name: Booking Checkout
*/
if (function_exists('wc_get_checkout_url')) {
    wp_safe_redirect(wc_get_checkout_url());
    exit;
}

get_header();
?>
<main class="container">
    <article class="entry-content">
        <h1>Booking Checkout</h1>
        <p>WooCommerce checkout is unavailable. Please <a href="<?php echo esc_url(home_url('/book-a-bay/')); ?>">return to booking</a>.</p>
    </article>
</main>
<?php get_footer();