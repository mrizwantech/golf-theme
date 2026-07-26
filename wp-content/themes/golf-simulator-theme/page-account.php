<?php
/*
Template Name: User Account
*/
get_header();

// Redirect if not logged in
if (!is_user_logged_in()) {
    echo '<main class="container"><article class="entry-content"><p>Please <a href="' . esc_url(wp_login_url(get_permalink())) . '">log in</a> to manage your bookings.</p></article></main>';
    get_footer();
    exit;
}

$current_user = wp_get_current_user();
$user_email = $current_user->user_email;

// Handle edit/cancel/add actions
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

// Handle update booking
if (isset($_POST['ttn_update_user_booking']) && check_admin_referer('ttn_update_user_booking_nonce')) {
    $booking_id_update = intval($_POST['booking_id']);
    $new_date = sanitize_text_field($_POST['date']);
    $new_time = sanitize_text_field($_POST['time']);
    $new_duration = intval($_POST['duration']);

    // Verify this booking belongs to the current user
    $booking_email = get_post_meta($booking_id_update, 'ttn_booking_email', true);
    if ($booking_email === $user_email) {
        update_post_meta($booking_id_update, 'ttn_booking_date', $new_date);
        update_post_meta($booking_id_update, 'ttn_booking_time', $new_time);
        update_post_meta($booking_id_update, 'ttn_booking_duration', $new_duration);
        
        echo '<div class="notice notice-success is-dismissible"><p>Booking updated successfully!</p></div>';
        $action = '';
    }
}

// Handle cancel booking
if ($action === 'cancel' && $booking_id && check_admin_referer('ttn_booking_action')) {
    $booking_email = get_post_meta($booking_id, 'ttn_booking_email', true);
    if ($booking_email === $user_email) {
        wp_delete_post($booking_id);
        echo '<div class="notice notice-success is-dismissible"><p>Booking cancelled successfully!</p></div>';
        $action = '';
    }
}

// Get user's bookings
$user_bookings = ttn_get_user_bookings($user_email);

$time_slots = apply_filters('ttn_get_time_slots', array());
if (empty($time_slots)) {
    // Fallback if filter not set
    $time_slots = array(
        array('label' => '11:00 AM', 'start' => '11:00'),
        array('label' => '12:00 PM', 'start' => '12:00'),
        array('label' => '1:00 PM', 'start' => '13:00'),
        array('label' => '2:00 PM', 'start' => '14:00'),
        array('label' => '3:00 PM', 'start' => '15:00'),
        array('label' => '4:00 PM', 'start' => '16:00'),
        array('label' => '5:00 PM', 'start' => '17:00'),
        array('label' => '6:00 PM', 'start' => '18:00'),
        array('label' => '7:00 PM', 'start' => '19:00'),
        array('label' => '8:00 PM', 'start' => '20:00'),
        array('label' => '9:00 PM', 'start' => '21:00'),
    );
}

$bays = array(
    'Tiger Woods Bay',
    'Jack Nicklaus Bay',
    'Phil Mickelson Bay',
    'Rory McIlroy Bay',
);

// Sort by date descending
usort($user_bookings, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Check if editing
$booking_to_edit = null;
if ($action === 'edit' && $booking_id) {
    foreach ($user_bookings as $booking) {
        if ($booking['ID'] === $booking_id) {
            $booking_to_edit = $booking;
            break;
        }
    }
}

?>
<main class="container">
    <article class="entry-content">
        <div class="account-header">
            <h1>My Account</h1>
            <p>Welcome, <strong><?php echo esc_html($current_user->display_name); ?></strong> (<?php echo esc_html($user_email); ?>)</p>
            <p><a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="btn btn-secondary">Logout</a></p>
        </div>

        <?php if ($booking_to_edit) : ?>
        <div style="background: #f8f9fa; padding: 20px; margin-bottom: 20px; border-radius: 5px;">
            <h2>Edit Booking</h2>
            <form method="post">
                <?php wp_nonce_field('ttn_update_user_booking_nonce'); ?>
                <input type="hidden" name="ttn_update_user_booking" value="1">
                <input type="hidden" name="booking_id" value="<?php echo esc_attr($booking_to_edit['ID']); ?>">
                
                <table class="form-table">
                    <tr>
                        <th><label>Bay</label></th>
                        <td><strong><?php echo esc_html($booking_to_edit['bay']); ?></strong></td>
                    </tr>
                    <tr>
                        <th><label for="date">Date</label></th>
                        <td><input type="date" id="date" name="date" value="<?php echo esc_attr($booking_to_edit['date']); ?>" min="<?php echo esc_attr(current_time('Y-m-d')); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="time">Start Time</label></th>
                        <td>
                            <select id="time" name="time" required>
                                <?php foreach ($time_slots as $slot) : ?>
                                    <option value="<?php echo esc_attr($slot['label']); ?>" <?php selected($booking_to_edit['time'], $slot['label']); ?>>
                                        <?php echo esc_html($slot['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="duration">Duration (Hours)</label></th>
                        <td>
                            <select id="duration" name="duration" required>
                                <?php for ($h = 1; $h <= 8; $h++) : ?>
                                    <option value="<?php echo esc_attr($h); ?>" <?php selected($booking_to_edit['duration'], $h); ?>>
                                        <?php echo esc_html($h); ?> <?php echo $h === 1 ? 'Hour' : 'Hours'; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="<?php echo esc_url(get_permalink()); ?>" class="btn btn-secondary">Cancel</a>
                </p>
            </form>
        </div>
        <?php endif; ?>

        <div class="bookings-section">
            <h2>My Bookings</h2>
            
            <?php if (empty($user_bookings)) : ?>
                <p>You don't have any bookings yet. <a href="<?php echo esc_url(home_url('/book-a-bay/')); ?>">Book a bay now</a></p>
            <?php else : ?>
                <table class="bookings-table">
                    <thead>
                        <tr>
                            <th>Bay</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Duration</th>
                            <th>Price</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($user_bookings as $booking) : ?>
                            <?php
                            $end_time = '';
                            $start_index = array_search($booking['time'], array_column($time_slots, 'label'));
                            if ($start_index !== false && $start_index + $booking['duration'] - 1 < count($time_slots)) {
                                $end_time = $time_slots[$start_index + $booking['duration'] - 1]['label'];
                            }
                            $booking_date = strtotime($booking['date']);
                            $today = strtotime(current_time('Y-m-d'));
                            $is_past = $booking_date < $today;
                            $price = $booking['duration'] * 50;
                            
                            $edit_url = wp_nonce_url(get_permalink() . '?action=edit&booking_id=' . $booking['ID'], 'ttn_booking_action', 'nonce');
                            $cancel_url = wp_nonce_url(get_permalink() . '?action=cancel&booking_id=' . $booking['ID'], 'ttn_booking_action', 'nonce');
                            ?>
                            <tr class="<?php echo $is_past ? 'booking-past' : 'booking-upcoming'; ?>">
                                <td><?php echo esc_html($booking['bay']); ?></td>
                                <td><?php echo esc_html($booking['date']); ?></td>
                                <td><?php echo esc_html($booking['time']); ?> <?php if ($end_time) echo ' - ' . esc_html($end_time); ?></td>
                                <td><?php echo esc_html($booking['duration']); ?>h</td>
                                <td>$<?php echo number_format($price, 2); ?></td>
                                <td>
                                    <?php if (!$is_past) : ?>
                                        <a href="<?php echo esc_url($edit_url); ?>" class="btn btn-small">Edit</a>
                                        <a href="<?php echo esc_url($cancel_url); ?>" class="btn btn-small btn-danger" onclick="return confirm('Are you sure you want to cancel this booking?');">Cancel</a>
                                    <?php else : ?>
                                        <span class="badge-past">Past</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <p style="margin-top: 20px;">
                <a href="<?php echo esc_url(home_url('/book-a-bay/')); ?>" class="btn btn-primary">Book Another Bay</a>
            </p>
        </div>
    </article>
</main>

<style>
.account-header {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 5px;
    margin-bottom: 30px;
}

.account-header p {
    margin: 8px 0;
}

.bookings-table {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
}

.bookings-table thead {
    background: #0f5132;
    color: #fff;
}

.bookings-table th,
.bookings-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.bookings-table tbody tr:hover {
    background: #f8f9fa;
}

.bookings-table .booking-past {
    opacity: 0.6;
}

.badge-past {
    display: inline-block;
    background: #ddd;
    color: #666;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 0.85rem;
}

.btn-small {
    display: inline-block;
    padding: 6px 12px;
    margin-right: 6px;
    background: #0f5132;
    color: #fff;
    border-radius: 3px;
    text-decoration: none;
    font-size: 0.9rem;
    border: none;
    cursor: pointer;
}

.btn-small:hover {
    background: #0a3a24;
}

.btn-small.btn-danger {
    background: #d32f2f;
}

.btn-small.btn-danger:hover {
    background: #b71c1c;
}

.btn-secondary {
    display: inline-block;
    padding: 10px 20px;
    background: #6c757d;
    color: #fff;
    border-radius: 5px;
    text-decoration: none;
    margin-left: 10px;
}

.btn-secondary:hover {
    background: #5a6268;
}

.form-table {
    width: 100%;
    border-collapse: collapse;
    margin: 15px 0;
}

.form-table th {
    text-align: right;
    padding: 12px;
    width: 25%;
    font-weight: 600;
}

.form-table td {
    padding: 12px;
}

.form-table input[type="date"],
.form-table select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 1rem;
}

.btn {
    display: inline-block;
    padding: 10px 20px;
    background: #0f5132;
    color: #fff;
    border-radius: 5px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    font-weight: 600;
}

.btn:hover {
    background: #0a3a24;
}

.btn-primary {
    background: #0f5132;
}

.btn-primary:hover {
    background: #0a3a24;
}

.notice {
    padding: 12px;
    margin: 15px 0;
    border-left: 4px solid #0f5132;
    background: #f0f8f5;
    border-radius: 3px;
}
</style>

<?php get_footer(); ?>
