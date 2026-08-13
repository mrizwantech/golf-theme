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
$formatted_total_price = number_format($total_price, 2);

if (!function_exists('ttn_booking_get_time_slots') || !function_exists('ttn_booking_get_bays') || !function_exists('ttn_get_bay_display_name')) {
    echo '<main class="container"><article class="entry-content"><p>Booking plugin is not active. Please <a href="' . esc_url(home_url('/book-a-bay/')) . '">go back</a> and try again.</p></article></main>';
    get_footer();
    exit;
}

$time_slots = ttn_booking_get_time_slots();
$bay_options = array_values(ttn_booking_get_bays());
$display_bay = ttn_get_bay_display_name($bay);
$time_slot_labels = array_column($time_slots, 'label');

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
            <p>
                <strong id="summary-bay"><?php echo esc_html($display_bay); ?></strong><br/>
                <span id="summary-date-time"><?php echo esc_html($date); ?> at <?php echo esc_html($time); ?></span><br/>
                <strong id="summary-duration"><?php echo esc_html($duration); ?> <?php echo $duration === 1 ? 'Hour' : 'Hours'; ?></strong><br/>
                <strong id="summary-total">Total: $<?php echo esc_html($formatted_total_price); ?></strong>
            </p>
            <div class="checkout-summary-actions">
                <button type="button" class="btn btn-secondary checkout-edit-toggle" id="toggle-edit-reservation">Edit Reservation</button>
                <button type="button" class="btn btn-secondary" id="back-to-booking">Back to Booking</button>
            </div>
        </div>

        <div class="checkout-edit-panel" id="checkout-edit-panel" style="display: none;">
            <h3>Edit Reservation</h3>
            <p class="checkout-edit-note">Update details below before payment. Availability is re-validated when you submit payment.</p>
            <div class="form-grid">
                <label>
                    Bay
                    <select id="edit-bay">
                        <?php foreach ($bay_options as $bay_option) : ?>
                            <option value="<?php echo esc_attr($bay_option); ?>" <?php selected($display_bay, $bay_option); ?>><?php echo esc_html($bay_option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Date
                    <input type="date" id="edit-date" value="<?php echo esc_attr($date); ?>" min="<?php echo esc_attr(current_time('Y-m-d')); ?>">
                </label>
                <label>
                    Start Time
                    <select id="edit-time">
                        <?php foreach ($time_slot_labels as $slot_label) : ?>
                            <option value="<?php echo esc_attr($slot_label); ?>" <?php selected($time, $slot_label); ?>><?php echo esc_html($slot_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Duration
                    <select id="edit-duration">
                        <?php for ($h = 1; $h <= 8; $h++) : ?>
                            <option value="<?php echo esc_attr($h); ?>" <?php selected($duration, $h); ?>><?php echo esc_html($h); ?> <?php echo $h === 1 ? 'Hour' : 'Hours'; ?></option>
                        <?php endfor; ?>
                    </select>
                </label>
            </div>
            <div class="checkout-edit-actions">
                <button type="button" class="btn btn-primary" id="apply-edit-reservation">Apply Changes</button>
            </div>
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

            <button class="btn btn-primary" type="submit" id="submit-btn">Complete Payment - $<?php echo esc_html($formatted_total_price); ?></button>
        </form>

        <p style="font-size: 0.9rem; color: #666; margin-top: 20px;">
            <strong>Test Card:</strong> 4242 4242 4242 4242 | Any future date | Any CVC
        </p>
    </article>
</main>

<script src="https://js.stripe.com/v3/"></script>
<script>
(function() {
    const PRICE_PER_HOUR = 50;
    const timeSlots = <?php echo wp_json_encode($time_slot_labels); ?>;
    let bookingState = {
        bay: <?php echo wp_json_encode($display_bay); ?>,
        date: <?php echo wp_json_encode($date); ?>,
        time: <?php echo wp_json_encode($time); ?>,
        duration: <?php echo (int) $duration; ?>
    };

    const summaryBay = document.getElementById('summary-bay');
    const summaryDateTime = document.getElementById('summary-date-time');
    const summaryDuration = document.getElementById('summary-duration');
    const summaryTotal = document.getElementById('summary-total');
    const toggleEditButton = document.getElementById('toggle-edit-reservation');
    const backToBookingButton = document.getElementById('back-to-booking');
    const editPanel = document.getElementById('checkout-edit-panel');
    const editBay = document.getElementById('edit-bay');
    const editDate = document.getElementById('edit-date');
    const editTime = document.getElementById('edit-time');
    const editDuration = document.getElementById('edit-duration');
    const applyEditButton = document.getElementById('apply-edit-reservation');

    const hiddenBay = document.querySelector('input[name="bay"]');
    const hiddenDate = document.querySelector('input[name="date"]');
    const hiddenTime = document.querySelector('input[name="time"]');
    const hiddenDuration = document.querySelector('input[name="duration"]');

    const form = document.getElementById('ttn-checkout-form');
    const submitBtn = document.getElementById('submit-btn');

    function getFormattedTotal() {
        return (bookingState.duration * PRICE_PER_HOUR).toFixed(2);
    }

    function getEndTime(startLabel, durationHours) {
        const startIndex = timeSlots.indexOf(startLabel);
        if (startIndex === -1) {
            return startLabel;
        }

        const endIndex = Math.min(startIndex + durationHours - 1, timeSlots.length - 1);
        return timeSlots[endIndex];
    }

    function getCheckoutTotalText() {
        return 'Complete Payment - $' + getFormattedTotal();
    }

    function syncHiddenFields() {
        hiddenBay.value = bookingState.bay;
        hiddenDate.value = bookingState.date;
        hiddenTime.value = bookingState.time;
        hiddenDuration.value = bookingState.duration;
    }

    function renderSummary() {
        const endTime = getEndTime(bookingState.time, bookingState.duration);

        summaryBay.textContent = bookingState.bay;
        summaryDateTime.textContent = bookingState.date + ' at ' + bookingState.time + (endTime ? ' - ' + endTime : '');
        summaryDuration.textContent = bookingState.duration + (bookingState.duration === 1 ? ' Hour' : ' Hours');
        summaryTotal.textContent = 'Total: $' + getFormattedTotal();
        submitBtn.textContent = getCheckoutTotalText();
    }

    function applyEdits() {
        bookingState.bay = editBay.value;
        bookingState.date = editDate.value;
        bookingState.time = editTime.value;
        bookingState.duration = parseInt(editDuration.value, 10) || 1;

        syncHiddenFields();
        renderSummary();
    }

    toggleEditButton.addEventListener('click', function() {
        editPanel.style.display = editPanel.style.display === 'none' ? 'block' : 'none';
    });

    backToBookingButton.addEventListener('click', function() {
        if (window.history.length > 1) {
            window.history.back();
            return;
        }

        window.location.href = '<?php echo esc_url(home_url('/book-a-bay/')); ?>';
    });

    applyEditButton.addEventListener('click', function() {
        applyEdits();
        editPanel.style.display = 'none';
    });

    syncHiddenFields();
    renderSummary();

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

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        submitBtn.disabled = true;
        submitBtn.textContent = 'Processing...';

        const { token, error } = await stripe.createToken(cardElement);

        if (error) {
            const errorElement = document.getElementById('card-errors');
            errorElement.textContent = error.message;
            submitBtn.disabled = false;
            submitBtn.textContent = getCheckoutTotalText();
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
    background: #0d0d0d;
    border: 1px solid rgba(255, 255, 255, 0.08);
    padding: 18px;
    border-radius: 12px;
    margin-bottom: 28px;
    color: #ffffff;
}
.checkout-summary h3 {
    margin-top: 0;
    font-size: 1rem;
    color: #ffffff;
}
.checkout-edit-toggle {
    margin-top: 10px;
}
.checkout-summary-actions {
    margin-top: 10px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.checkout-summary-actions .btn-secondary {
    background: #111111;
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.2);
}
.checkout-summary-actions .btn-secondary:hover {
    background: rgba(255, 255, 255, 0.06);
    color: #f2c14e;
    border-color: rgba(242, 193, 78, 0.7);
}
.checkout-edit-panel {
    background: #111111;
    border: 1px solid rgba(255, 255, 255, 0.08);
    padding: 18px;
    border-radius: 12px;
    margin-bottom: 24px;
    color: #ffffff;
}
.checkout-edit-panel h3 {
    margin-top: 0;
    font-size: 1rem;
    color: #ffffff;
}
.checkout-edit-note {
    font-size: 0.9rem;
    color: #d4d4d4;
    margin-bottom: 14px;
}
.checkout-edit-actions {
    margin-top: 14px;
}
.stripe-card-element {
    padding: 12px 14px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 12px;
    background: #121212;
    color: #ffffff;
}
</style>

<?php get_footer();
