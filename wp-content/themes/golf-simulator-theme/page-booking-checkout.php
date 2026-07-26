<?php
/*
Template Name: Booking Checkout
*/
get_header();

$bay = isset($_GET['bay']) ? sanitize_text_field(wp_unslash($_GET['bay'])) : '';
$bay = preg_replace('/\s+/', ' ', trim($bay)); // Normalize spaces
$date = isset($_GET['date']) ? sanitize_text_field(wp_unslash($_GET['date'])) : '';
$time = isset($_GET['time']) ? sanitize_text_field(wp_unslash($_GET['time'])) : '';
$time = preg_replace('/\s+/', ' ', trim($time)); // Normalize spaces
$duration = isset($_GET['duration']) ? intval($_GET['duration']) : 1;
$total_price = $duration * 50;

if (!$bay || !$date || !$time) {
    echo '<main class="container"><article class="entry-content"><p>Invalid booking selection. Please <a href="' . esc_url(home_url('/book-a-bay/')) . '">go back</a> and try again.</p></article></main>';
    get_footer();
    exit;
}
?>
<main class="container">
    <article class="entry-content booking-card">
        <div class="kicker">Complete Your Reservation</div>
        <h1>Payment & Booking</h1>
        
        <div class="checkout-summary">
            <h3>Your Reservation</h3>
            <p><strong><?php echo esc_html($bay); ?></strong><br/>
            <?php echo esc_html($date); ?> at <?php echo esc_html($time); ?><br/>
            <strong><?php echo $duration; ?> <?php echo $duration === 1 ? 'Hour' : 'Hours'; ?></strong><br/>
            <strong>Total: $<?php echo esc_html(number_format($total_price, 2)); ?></strong></p>
        </div>

        <form id="ttn-checkout-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="ttn_booking_checkout">
            <input type="hidden" name="bay" value="<?php echo esc_attr($bay); ?>">
            <input type="hidden" name="date" value="<?php echo esc_attr($date); ?>">
            <input type="hidden" name="time" value="<?php echo esc_attr($time); ?>">
            <input type="hidden" name="duration" value="<?php echo esc_attr($duration); ?>">
            <?php wp_nonce_field('ttn_booking_checkout', 'ttn_checkout_nonce'); ?>

            <div class="form-grid">
                <label class="full-width">
                    Full Name
                    <input type="text" name="name" placeholder="Your full name" autocomplete="name" required>
                </label>
                <label>
                    Email Address
                    <input type="email" name="email" placeholder="you@example.com" autocomplete="email" required>
                </label>
                <label>
                    Phone Number
                    <input type="tel" name="phone" placeholder="(555) 123-4567" autocomplete="tel" required>
                </label>
                <label class="full-width">
                    <strong>Card Details</strong>
                </label>
                <div class="full-width stripe-card-element" id="card-element"></div>
                <div class="full-width" id="card-errors" style="color: #fa755a; margin-top: 8px;"></div>
            </div>

            <button class="btn btn-primary" type="submit" id="submit-btn">Complete Payment - $50.00</button>
        </form>

        <p style="font-size: 0.9rem; color: #666; margin-top: 20px;">
            <strong>Test Card:</strong> 4242 4242 4242 4242 | Any future date | Any CVC
        </p>
    </article>
</main>

<script src="https://js.stripe.com/v3/"></script>
<script>
(function() {
    const stripe = Stripe('pk_test_51TxKQ5GvsZrLG3yulrfaXb1jCaIIIcdEVZv28bF4ilRGFWW2gebxfWnuoJdXMGWzkEAgTU3yuPgniadk4UTIahHm00ZFuicsCP');
    const elements = stripe.elements();
    const cardElement = elements.create('card');

    cardElement.mount('#card-element');

    cardElement.on('change', function(event) {
        const displayError = document.getElementById('card-errors');
        if (event.error) {
            displayError.textContent = event.error.message;
        } else {
            displayError.textContent = '';
        }
    });

    const form = document.getElementById('ttn-checkout-form');
    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const submitBtn = document.getElementById('submit-btn');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Processing...';

        const { token, error } = await stripe.createToken(cardElement);

        if (error) {
            const errorElement = document.getElementById('card-errors');
            errorElement.textContent = error.message;
            submitBtn.disabled = false;
            submitBtn.textContent = 'Complete Payment - $50.00';
        } else {
            const tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = 'stripeToken';
            tokenInput.value = token.id;
            form.appendChild(tokenInput);

            form.submit();
        }
    });
})();
</script>

<style>
.checkout-summary {
    background: #f0f8f5;
    border: 1px solid rgba(15, 81, 50, 0.2);
    padding: 18px;
    border-radius: 12px;
    margin-bottom: 28px;
}
.checkout-summary h3 {
    margin-top: 0;
    font-size: 1rem;
}
.stripe-card-element {
    padding: 12px 14px;
    border: 1px solid rgba(15, 81, 50, 0.2);
    border-radius: 12px;
    background: #fff;
}
</style>

<?php get_footer();
