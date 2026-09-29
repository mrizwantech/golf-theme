<?php
/*
Template Name: My Bookings
*/

get_header();

if (!is_user_logged_in()) {
    wp_safe_redirect(golf_simulator_theme_get_login_url(get_permalink()));
    exit;
}

$current_user = wp_get_current_user();
$bookings = function_exists('ttn_get_user_bookings') ? ttn_get_user_bookings($current_user->user_email) : array();
$booking_id = isset($_GET['booking_id']) ? absint($_GET['booking_id']) : 0;
$selected_booking = null;
foreach ($bookings as $booking) {
    if ((int) $booking['ID'] === $booking_id) {
        $selected_booking = $booking;
        break;
    }
}

$booking_timestamp = static function ($booking) {
    try {
        return (new DateTimeImmutable($booking['date'] . ' ' . $booking['time'], wp_timezone()))->getTimestamp();
    } catch (Exception $exception) {
        return 0;
    }
};

$view = isset($_GET['view']) && sanitize_key(wp_unslash($_GET['view'])) === 'past' ? 'past' : 'upcoming';
$visible_bookings = array();
foreach ($bookings as $booking) {
    $is_past = ($booking['status'] ?? '') === 'cancelled' || $booking_timestamp($booking) < time();
    if (($view === 'past' && $is_past) || ($view === 'upcoming' && !$is_past)) {
        $visible_bookings[] = $booking;
    }
}
usort($visible_bookings, static function ($first, $second) use ($booking_timestamp, $view) {
    $comparison = $booking_timestamp($first) <=> $booking_timestamp($second);
    return $view === 'past' ? -$comparison : $comparison;
});

$base_url = get_permalink();
$booking_images = array(
    'nexus' => get_theme_mod('golf_simulator_slide_3_image', 'https://images.unsplash.com/photo-1517466787929-bc90951d0974?auto=format&fit=crop&w=900&q=80'),
    'fairway' => get_theme_mod('golf_simulator_slide_1_image', 'https://images.unsplash.com/photo-1535131749006-b7f58c99034b?auto=format&fit=crop&w=900&q=80'),
    'apex' => get_theme_mod('golf_simulator_slide_2_image', 'https://images.unsplash.com/photo-1593111774278-0b6b02b7961c?auto=format&fit=crop&w=900&q=80'),
);
?>
<main class="container member-bookings-page">
    <?php if ($booking_id) : ?>
        <?php if (!$selected_booking) : ?>
            <a class="member-bookings-back" href="<?php echo esc_url($base_url); ?>">&larr; All bookings</a>
            <h1 class="member-bookings-title">Reservation not found</h1>
            <p class="member-bookings-intro">This reservation could not be found in your account.</p>
        <?php else : ?>
            <?php
            $is_cancelled = ($selected_booking['status'] ?? '') === 'cancelled';
            $can_manage = !$is_cancelled && function_exists('ttn_booking_is_within_self_service_window')
                && ttn_booking_is_within_self_service_window($selected_booking['date'], $selected_booking['time']);
            $payment = $selected_booking['payment'] ?? array();
            $price = (int) $selected_booking['duration'] * (function_exists('ttn_booking_get_hourly_price') ? ttn_booking_get_hourly_price($selected_booking['bay']) : 50);
            $detail_image = $booking_images[strtolower($selected_booking['bay'])] ?? reset($booking_images);
            ?>
            <a class="member-bookings-back" href="<?php echo esc_url(add_query_arg('view', $view, $base_url)); ?>">&larr; <?php echo $view === 'past' ? 'Past bookings' : 'Upcoming bookings'; ?></a>
            <div class="member-booking-detail-heading">
                <span class="member-booking-detail-image" style="background-image: linear-gradient(0deg, rgba(0, 0, 0, .15), rgba(0, 0, 0, .02)), url('<?php echo esc_url($detail_image); ?>');" aria-hidden="true"></span>
                <div>
                    <span class="member-bookings-kicker">RESERVATION DETAILS</span>
                    <h1 class="member-bookings-title"><?php echo esc_html($selected_booking['bay']); ?> Bay</h1>
                </div>
                <span class="member-booking-status<?php echo $is_cancelled ? ' is-cancelled' : ''; ?>"><?php echo esc_html($is_cancelled ? 'Cancelled' : ($selected_booking['payment_status'] ?: 'Confirmed')); ?></span>
            </div>
            <section class="member-booking-detail-panel">
                <dl class="member-booking-detail-grid">
                    <div><dt>Booking reference</dt><dd><?php echo esc_html($selected_booking['booking_reference']); ?></dd></div>
                    <div><dt>Date</dt><dd><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($selected_booking['date']))); ?></dd></div>
                    <div><dt>Start time</dt><dd><?php echo esc_html($selected_booking['time']); ?></dd></div>
                    <div><dt>Duration</dt><dd><?php echo esc_html($selected_booking['duration']); ?> hour<?php echo (int) $selected_booking['duration'] === 1 ? '' : 's'; ?></dd></div>
                    <div><dt>Players</dt><dd><?php echo esc_html($selected_booking['players']); ?></dd></div>
                    <div><dt>Total</dt><dd>$<?php echo esc_html(number_format($price, 2)); ?></dd></div>
                    <div><dt>Payment</dt><dd><?php echo esc_html($payment['payment_method'] ?? ($selected_booking['payment_status'] ?: 'Unavailable')); ?><?php if (!empty($payment['card_last_four'])) : ?> ···· <?php echo esc_html($payment['card_last_four']); ?><?php endif; ?></dd></div>
                </dl>
            </section>
            <?php if ($can_manage) : ?>
                <div class="member-booking-actions">
                    <a class="btn btn-secondary" href="<?php echo esc_url(home_url('/my-account/?action=edit&booking_id=' . (int) $selected_booking['ID'])); ?>">Edit reservation</a>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="member-booking-cancel-form">
                        <input type="hidden" name="action" value="ttn_cancel_user_booking">
                        <?php wp_nonce_field('ttn_cancel_booking_nonce', 'ttn_cancel_booking_nonce'); ?>
                        <input type="hidden" name="ttn_cancel_booking_id" value="<?php echo esc_attr($selected_booking['ID']); ?>">
                        <label for="member-booking-password">Confirm your password to cancel</label>
                        <input id="member-booking-password" type="password" name="account_password" autocomplete="current-password" required>
                        <button class="btn btn-danger" type="submit">Cancel reservation</button>
                    </form>
                </div>
            <?php elseif (!$is_cancelled) : ?>
                <p class="member-bookings-notice">This reservation starts within 24 hours and can no longer be changed online. Please call <a href="tel:+19805033288">+1 (980) 503-3288</a> for help.</p>
            <?php endif; ?>
        <?php endif; ?>
    <?php else : ?>
        <span class="member-bookings-kicker">YOUR ACCOUNT</span>
        <h1 class="member-bookings-title">My Bookings</h1>
        <p class="member-bookings-intro">Review upcoming reservations and your past visits.</p>
        <nav class="member-bookings-tabs" aria-label="Booking history">
            <a class="<?php echo $view === 'upcoming' ? 'active' : ''; ?>" href="<?php echo esc_url(add_query_arg('view', 'upcoming', $base_url)); ?>">Upcoming <span><?php echo esc_html(count(array_filter($bookings, static function ($item) use ($booking_timestamp) { return ($item['status'] ?? '') !== 'cancelled' && $booking_timestamp($item) >= time(); }))); ?></span></a>
            <a class="<?php echo $view === 'past' ? 'active' : ''; ?>" href="<?php echo esc_url(add_query_arg('view', 'past', $base_url)); ?>">Past</a>
        </nav>
        <?php if (empty($visible_bookings)) : ?>
            <div class="member-bookings-empty">
                <h2><?php echo $view === 'past' ? 'No past reservations' : 'No upcoming reservations'; ?></h2>
                <p><?php echo $view === 'past' ? 'Completed and cancelled visits will appear here.' : 'Your next round starts here.'; ?></p>
                <?php if ($view === 'upcoming') : ?><a class="btn btn-primary" href="<?php echo esc_url(home_url('/book-a-bay/')); ?>">Book a Bay</a><?php endif; ?>
            </div>
        <?php else : ?>
            <div class="member-booking-list">
                <?php foreach ($visible_bookings as $booking) : ?>
                    <?php $cancelled = ($booking['status'] ?? '') === 'cancelled'; ?>
                    <a class="member-booking-row" href="<?php echo esc_url(add_query_arg(array('booking_id' => (int) $booking['ID'], 'view' => $view), $base_url)); ?>">
                        <?php $booking_image = $booking_images[strtolower($booking['bay'])] ?? reset($booking_images); ?>
                        <span class="member-booking-image" style="background-image: linear-gradient(0deg, rgba(0, 0, 0, .12), rgba(0, 0, 0, .02)), url('<?php echo esc_url($booking_image); ?>');" aria-hidden="true"></span>
                        <div class="member-booking-main">
                            <strong><?php echo esc_html($booking['bay']); ?> Bay</strong>
                            <span><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($booking['date']))); ?> · <?php echo esc_html($booking['time']); ?></span>
                        </div>
                        <div class="member-booking-secondary">
                            <span><?php echo esc_html($booking['duration']); ?> hour<?php echo (int) $booking['duration'] === 1 ? '' : 's'; ?> · <?php echo esc_html($booking['players']); ?> player<?php echo (int) $booking['players'] === 1 ? '' : 's'; ?></span>
                            <span class="member-booking-status<?php echo $cancelled ? ' is-cancelled' : ''; ?>"><?php echo esc_html($cancelled ? 'Cancelled' : ($booking['payment_status'] ?: 'Confirmed')); ?></span>
                        </div>
                        <span class="member-booking-reference"><?php echo esc_html($booking['booking_reference']); ?></span>
                        <span class="member-booking-arrow" aria-hidden="true">&rarr;</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php get_footer(); ?>
