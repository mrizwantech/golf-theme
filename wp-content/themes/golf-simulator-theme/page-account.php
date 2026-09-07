<?php
/*
Template Name: User Account
Description: User account dashboard for managing bookings - presentation layer only
*/
get_header();

// Redirect if not logged in
if (!is_user_logged_in()) {
    echo '<main class="container"><article class="entry-content"><p>Please <a href="' . esc_url(golf_simulator_theme_get_login_url(get_permalink())) . '">log in</a> to manage your bookings.</p></article></main>';
    get_footer();
    exit;
}

$current_user = wp_get_current_user();
$user_email = $current_user->user_email;
$membership = golf_simulator_theme_get_user_membership_record($current_user->ID);
$upgrade_balance = get_user_meta($current_user->ID, '_membership_upgrade_balance', true);

// Get user's bookings (CRUD: Read)
$user_bookings = ttn_get_user_bookings($user_email);

// Get time slots and bays (presentation data)
$time_slots = apply_filters('ttn_get_time_slots', array());
if (empty($time_slots)) {
    $time_slots = array(
        array('label' => '10:00 AM', 'start' => '10:00'),
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

// Sort bookings by date descending (presentation logic)
usort($user_bookings, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Determine which booking to edit (presentation logic)
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$booking_to_edit = null;

if ($action === 'edit' && $booking_id) {
    foreach ($user_bookings as $booking) {
        if ($booking['ID'] === $booking_id) {
            $booking_to_edit = $booking;
            break;
        }
    }
}

// Get result message from transient if available
$message = get_transient('ttn_user_booking_message_' . $user_email);
if ($message) {
    delete_transient('ttn_user_booking_message_' . $user_email);
}
?>
<main class="container">
    <article class="entry-content">
        <div class="account-header">
            <h1>My Account</h1>
            <p>Welcome, <strong><?php echo esc_html($current_user->display_name); ?></strong> (<?php echo esc_html($user_email); ?>)</p>
            <p><a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="btn btn-secondary">Logout</a></p>
        </div>

        <?php if ($message && !empty($message['message'])) : ?>
            <div class="notice notice-<?php echo esc_attr($message['success'] ? 'success' : 'error'); ?> is-dismissible">
                <p><?php echo esc_html($message['message']); ?></p>
            </div>
        <?php endif; ?>

        <section class="account-membership-panel">
            <h2>My Membership</h2>
            <?php if ($membership) : ?>
                <div class="account-membership-grid">
                    <div>
                        <span class="account-membership-label">Membership Tier</span>
                        <strong><?php echo esc_html($membership->package_name); ?></strong>
                    </div>
                    <div>
                        <span class="account-membership-label">Price</span>
                        <strong>$<?php echo esc_html(!empty($membership->discount_price) ? $membership->discount_price : $membership->price); ?>/Month</strong>
                    </div>
                    <div>
                        <span class="account-membership-label">Status</span>
                        <strong><?php echo esc_html(ucfirst($membership->status)); ?></strong>
                    </div>
                    <div>
                        <span class="account-membership-label">Payment</span>
                        <strong><?php echo esc_html(ucfirst($membership->payment_status)); ?></strong>
                    </div>
                </div>
                <?php if ('pending' === $membership->payment_status) : ?>
                    <p class="account-membership-note">Your payment was submitted and is waiting for verification.</p>
                <?php endif; ?>
                <div class="account-membership-dates">
                    <span><strong>Joined:</strong> <?php echo esc_html($membership->start_date ? mysql2date(get_option('date_format'), $membership->start_date) : 'Pending'); ?></span>
                    <span><strong>Payment date:</strong> <?php echo esc_html($membership->payment_date ? mysql2date(get_option('date_format'), $membership->payment_date) : 'Pending'); ?></span>
                    <span><strong>Next billing:</strong> <?php echo esc_html($membership->next_billing_date ? mysql2date(get_option('date_format'), $membership->next_billing_date) : 'To be confirmed'); ?></span>
                </div>
                <div class="account-membership-actions">
                    <h3>Manage Membership</h3>
                    <?php if ('' !== $upgrade_balance) : ?>
                        <p class="account-membership-note">Next upgrade balance: <strong>$<?php echo esc_html($upgrade_balance); ?></strong>. Payment remains pending until the upgrade is verified.</p>
                    <?php endif; ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="golf_simulator_membership_manage">
                        <input type="hidden" name="stripeToken" id="membership-upgrade-stripe-token">
                        <?php wp_nonce_field('golf_simulator_membership_manage', 'golf_simulator_membership_nonce'); ?>
                        <div class="account-membership-action-fields">
                            <label>
                                Membership tier
                                <select name="membership_package">
                                    <?php foreach (golf_simulator_theme_get_default_membership_packages() as $package_key => $package) : ?>
                                        <option value="<?php echo esc_attr($package_key); ?>" <?php selected($membership->package_name, $package_key); ?>><?php echo esc_html($package['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                Action
                                <select name="membership_action">
                                    <option value="upgrade">Upgrade</option>
                                    <option value="downgrade">Downgrade</option>
                                    <option value="pause">Pause</option>
                                    <option value="cancel">Cancel</option>
                                </select>
                            </label>
                            <div class="membership-upgrade-payment">
                                <span>Card for upgrade payment</span>
                                <div class="stripe-card-element" id="membership-upgrade-card"></div>
                                <small id="membership-upgrade-card-error" role="alert"></small>
                            </div>
                            <button type="submit" class="btn btn-primary">Update Membership</button>
                        </div>
                    </form>
                </div>
            <?php else : ?>
                <p>You do not have a membership yet. <a href="<?php echo esc_url(home_url('/membership/')); ?>">View membership options</a></p>
            <?php endif; ?>
        </section>

        <?php if ($booking_to_edit) : ?>
        <div style="background: #f8f9fa; padding: 20px; margin-bottom: 20px; border-radius: 5px;">
            <h2>Edit Booking</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="ttn_update_user_booking">
                <?php wp_nonce_field('ttn_update_user_booking_nonce'); ?>
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
                            <th>Status</th>
                            <th>Reference</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($user_bookings as $booking) : ?>
                            <?php
                            $end_time = '';
                            $start_index = array_search($booking['time'], array_column($time_slots, 'label'));
                            if ($start_index !== false) {
                                $start_minutes = ((int) substr($time_slots[$start_index]['start'], 0, 2) * 60) + (int) substr($time_slots[$start_index]['start'], 3, 2);
                                $end_minutes = $start_minutes + ((int) $booking['duration'] * 60);
                                $end_time = date('g:i A', mktime((int) floor($end_minutes / 60) % 24, $end_minutes % 60));
                            }
                            $booking_date = strtotime($booking['date']);
                            $today = strtotime(current_time('Y-m-d'));
                            $is_past = $booking_date < $today;
                            $price = $booking['duration'] * 50;
                            
                            // Links to edit and cancel using action handlers
                            $edit_url = get_permalink() . '?action=edit&booking_id=' . $booking['ID'];
                            $cancel_url = wp_nonce_url(add_query_arg(array('ttn_cancel_booking_id' => $booking['ID']), admin_url('admin-post.php?action=ttn_cancel_user_booking')), 'ttn_cancel_booking_nonce');
                            ?>
                            <tr class="<?php echo $is_past ? 'booking-past' : 'booking-upcoming'; ?>">
                                <td><?php echo esc_html($booking['bay']); ?></td>
                                <td><?php echo esc_html($booking['date']); ?></td>
                                <td><?php echo esc_html($booking['time']); ?> <?php if ($end_time) echo ' - ' . esc_html($end_time); ?></td>
                                <td><?php echo esc_html($booking['duration']); ?>h</td>
                                <td>$<?php echo number_format($price, 2); ?></td>
                                <td><?php echo esc_html($booking['payment_status'] ?: 'Submitted'); ?></td>
                                <td><?php echo esc_html($booking['booking_reference']); ?></td>
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
<script src="https://js.stripe.com/v3/"></script>
<script>
(function() {
    var form = document.querySelector('.account-membership-actions form');
    var cardContainer = document.getElementById('membership-upgrade-card');
    if (!form || !cardContainer || typeof Stripe === 'undefined') {
        return;
    }

    var stripe = Stripe('pk_test_51TxKQ5GvsZrLG3yulrfaXb1jCaIIIcdEVZv28bF4ilRGFWW2gebxfWnuoJdXMGWzkEAgTU3yuPgniadk4UTIahHm00ZFuicsCP');
    var card = stripe.elements().create('card');
    card.mount(cardContainer);
    var action = form.querySelector('[name="membership_action"]');
    var token = document.getElementById('membership-upgrade-stripe-token');
    var error = document.getElementById('membership-upgrade-card-error');

    form.addEventListener('submit', function(event) {
        if (!action || action.value !== 'upgrade' || token.value) {
            return;
        }

        event.preventDefault();
        error.textContent = '';
        var button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        stripe.createToken(card).then(function(result) {
            if (result.error) {
                error.textContent = result.error.message;
                button.disabled = false;
                return;
            }

            token.value = result.token.id;
            form.submit();
        });
    });
})();
</script>
<style>
.account-header {
    margin-bottom: 30px;
}

.account-header p {
    margin: 8px 0;
}

.account-membership-panel {
    margin-bottom: 32px;
    padding: 24px;
    background: var(--surface);
    border: 1px solid var(--border-soft);
    border-radius: 18px;
    box-shadow: var(--shadow);
}

.account-membership-panel h2 {
    margin-top: 0;
}

.account-membership-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
}

.account-membership-grid > div {
    display: grid;
    gap: 6px;
}

.account-membership-label {
    color: var(--muted);
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.account-membership-note {
    margin: 18px 0 0;
    color: var(--muted);
}

.account-membership-dates {
    display: flex;
    flex-wrap: wrap;
    gap: 14px 24px;
    margin-top: 18px;
    color: var(--muted);
    font-size: 0.92rem;
}

.account-membership-actions {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid var(--border-soft);
}

.account-membership-actions h3 {
    margin: 0 0 14px;
}

.account-membership-action-fields {
    display: flex;
    flex-wrap: wrap;
    align-items: end;
    gap: 14px;
}

.account-membership-action-fields label {
    display: grid;
    gap: 6px;
    color: var(--muted);
    font-weight: 700;
}

.account-membership-action-fields select {
    min-width: 170px;
    padding: 11px 12px;
    border: 1px solid var(--border-soft);
    border-radius: 8px;
    background: var(--surface);
    color: var(--text);
}

.membership-upgrade-payment {
    display: grid;
    flex: 1 1 260px;
    gap: 6px;
    color: var(--muted);
    font-weight: 700;
}

.membership-upgrade-payment .stripe-card-element {
    min-width: 240px;
}

#membership-upgrade-card-error {
    color: #b42318;
    font-weight: 600;
}

@media (max-width: 700px) {
    .account-membership-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .account-membership-action-fields,
    .account-membership-action-fields label,
    .account-membership-action-fields select,
    .account-membership-action-fields .btn {
        width: 100%;
    }
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
