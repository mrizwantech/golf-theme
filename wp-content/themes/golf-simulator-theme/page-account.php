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
$membership_history = golf_simulator_theme_get_membership_history($current_user->ID);
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
$membership_error = isset($_GET['membership_error']) ? sanitize_text_field(wp_unslash($_GET['membership_error'])) : '';
$membership_notice = isset($_GET['membership_notice']) ? sanitize_text_field(wp_unslash($_GET['membership_notice'])) : '';
$membership_updated = isset($_GET['membership_updated']) && '1' === $_GET['membership_updated'];
$profile_updated = isset($_GET['profile_updated']) && '1' === $_GET['profile_updated'];
$profile_error = isset($_GET['profile_error']) ? sanitize_text_field(wp_unslash($_GET['profile_error'])) : '';
$user_phone = get_user_meta($current_user->ID, 'phone_number', true);
$user_sms_opt_in = get_user_meta($current_user->ID, 'sms_opt_in', true) === '1';
$user_promo_opt_in = get_user_meta($current_user->ID, 'promo_opt_in', true) === '1';
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

        <?php if ($membership_error) : ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html($membership_error); ?></p>
            </div>
        <?php elseif ($membership_notice) : ?>
            <div class="notice notice-warning is-dismissible">
                <p><?php echo esc_html($membership_notice); ?></p>
            </div>
        <?php elseif ($membership_updated) : ?>
            <div class="notice notice-success is-dismissible">
                <p>Your membership was updated successfully.</p>
            </div>
        <?php endif; ?>

        <?php if ($profile_error) : ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html($profile_error); ?></p>
            </div>
        <?php elseif ($profile_updated) : ?>
            <div class="notice notice-success is-dismissible">
                <p>Your communication preferences were updated.</p>
            </div>
        <?php endif; ?>

        <section class="account-membership-panel">
            <h2>Communication Preferences</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="golf_simulator_profile_update">
                <?php wp_nonce_field('ttn_profile_update', 'ttn_profile_nonce'); ?>
                <div class="form-grid">
                    <label class="full-width">
                        Phone Number
                        <input type="tel" name="phone" value="<?php echo esc_attr($user_phone); ?>" placeholder="(555) 123-4567" autocomplete="tel">
                    </label>
                    <label class="full-width checkbox-label">
                        <input type="checkbox" name="sms_opt_in" value="1" <?php checked($user_sms_opt_in); ?>>
                        Text me about my bookings and tee time offers
                    </label>
                    <label class="full-width checkbox-label">
                        <input type="checkbox" name="promo_opt_in" value="1" <?php checked($user_promo_opt_in); ?>>
                        Send me promotional emails and text messages about offers
                    </label>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top: 16px;">Save Preferences</button>
            </form>
        </section>

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
                <?php if (!empty($membership_history)) : ?>
                    <div class="account-membership-history">
                        <h3>Membership History</h3>
                        <div class="account-membership-history-list">
                            <?php foreach ($membership_history as $history) : ?>
                                <div class="account-membership-history-item">
                                    <strong><?php echo esc_html(ucfirst($history->action)); ?></strong>
                                    <span><?php echo esc_html(mysql2date(get_option('date_format'), $history->created_at)); ?></span>
                                    <span><?php echo esc_html($history->previous_package ? $history->previous_package . ' to ' : ''); ?><?php echo esc_html($history->new_package); ?></span>
                                    <?php if ((float) $history->amount > 0) : ?>
                                        <span><?php echo 'cancel' === $history->action || 'downgrade' === $history->action ? 'Refunded' : 'Charged'; ?> $<?php echo esc_html(number_format((float) $history->amount, 2)); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="account-membership-actions">
                    <h3>Manage Membership</h3>
                    <?php if ('' !== $upgrade_balance) : ?>
                        <p class="account-membership-note">Next upgrade balance: <strong>$<?php echo esc_html($upgrade_balance); ?></strong>. Payment remains pending until the upgrade is verified.</p>
                    <?php endif; ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="golf_simulator_membership_manage">
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
                            <label>
                                Account Password
                                <input type="password" name="account_password" placeholder="Confirm password" autocomplete="current-password" required style="min-width: 170px; padding: 11px 12px; border: 1px solid var(--border-soft); border-radius: 8px; background: var(--surface); color: var(--text);">
                            </label>
                            <button type="submit" class="btn btn-primary">Update Membership</button>
                        </div>
                    </form>
                </div>
            <?php else : ?>
                <p>You do not have a membership yet. <a href="<?php echo esc_url(home_url('/membership/')); ?>" class="text-link">View membership options</a></p>
            <?php endif; ?>
        </section>

        <?php if ($booking_to_edit) : ?>
        <?php
        $bays = function_exists('ttn_booking_get_bays') ? ttn_booking_get_bays() : array();
        $all_booking_records = function_exists('ttn_booking_get_booking_records') ? ttn_booking_get_booking_records() : array();
        $edit_bay_config = function_exists('ttn_booking_get_bay_config') ? ttn_booking_get_bay_config($booking_to_edit['bay']) : null;
        $edit_bay_type = $edit_bay_config['type'] ?? 'dual';
        ?>
        <section class="booking-card edit-booking-card" style="margin-bottom: 32px;">
            <div class="booking-panel-head">
                <div class="booking-panel-title">Edit Reservation</div>
                <span class="booking-live-badge">Real-time availability</span>
            </div>
            <p class="booking-note">Modify your simulator type, bay, date, duration, or start time below and save changes.</p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="ttn-edit-booking-form">
                <input type="hidden" name="action" value="ttn_update_user_booking">
                <?php wp_nonce_field('ttn_update_user_booking_nonce'); ?>
                <input type="hidden" name="booking_id" value="<?php echo esc_attr($booking_to_edit['ID']); ?>">
                <input type="hidden" name="bay" id="edit-hidden-bay" value="<?php echo esc_attr($booking_to_edit['bay']); ?>">
                <input type="hidden" name="date" id="edit-hidden-date" value="<?php echo esc_attr($booking_to_edit['date']); ?>">
                <input type="hidden" name="time" id="edit-hidden-time" value="<?php echo esc_attr($booking_to_edit['time']); ?>">
                <input type="hidden" name="duration" id="edit-hidden-duration" value="<?php echo esc_attr($booking_to_edit['duration']); ?>">

                <div class="booking-section">
                    <h3>Select Simulator Type</h3>
                    <div class="bay-type-selector" id="edit-bay-type-selector">
                        <label class="bay-type-pill">
                            <input type="radio" name="edit_bay_type" value="dual" <?php checked($edit_bay_type, 'dual'); ?> />
                            <span>Dual (Left & Right Handed)</span>
                        </label>
                        <label class="bay-type-pill">
                            <input type="radio" name="edit_bay_type" value="right-handed" <?php checked($edit_bay_type, 'right-handed'); ?> />
                            <span>Right-Handed</span>
                        </label>
                    </div>
                </div>

                <div class="booking-section" id="edit-bay-section">
                    <h3>Select Bay</h3>
                    <div class="bay-selector" id="edit-bay-selector">
                        <?php foreach ($bays as $bay_k => $bay_l) : ?>
                            <?php
                            $b_cfg = function_exists('ttn_booking_get_bay_config') ? ttn_booking_get_bay_config($bay_k) : null;
                            $b_type = $b_cfg['type'] ?? 'right-handed';
                            $is_checked = ($booking_to_edit['bay'] === $bay_l || $booking_to_edit['bay'] === $bay_k);
                            ?>
                            <label class="bay-pill" data-bay-type="<?php echo esc_attr($b_type); ?>">
                                <input type="radio" name="edit_bay_radio" value="<?php echo esc_attr($bay_l); ?>" data-bay-key="<?php echo esc_attr($bay_k); ?>" data-bay-type="<?php echo esc_attr($b_type); ?>" data-price="<?php echo esc_attr(function_exists('ttn_booking_get_hourly_price') ? ttn_booking_get_hourly_price($bay_k) : 50); ?>" <?php checked($is_checked); ?> />
                                <span><?php echo esc_html($bay_l); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="booking-section">
                    <h3>Select Date</h3>
                    <input type="date" id="edit-date-input" value="<?php echo esc_attr($booking_to_edit['date']); ?>" min="<?php echo esc_attr(current_time('Y-m-d')); ?>" required>
                </div>

                <div class="booking-section">
                    <h3>Duration (Hours)</h3>
                    <div class="duration-selector" id="edit-duration-selector">
                        <?php for ($h = 1; $h <= 8; $h++) : ?>
                            <label class="duration-pill">
                                <input type="radio" name="edit_duration_radio" value="<?php echo esc_attr($h); ?>" data-duration="<?php echo esc_attr($h); ?>" <?php checked($h, (int) $booking_to_edit['duration']); ?> />
                                <span><?php echo esc_html($h); ?> <?php echo $h === 1 ? 'Hour' : 'Hours'; ?></span>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="booking-section">
                    <h3>Select Start Time</h3>
                    <div class="time-slots" id="edit-time-slots">
                        <?php foreach ($time_slots as $slot) : ?>
                            <button type="button" class="time-slot-pill" data-time="<?php echo esc_attr($slot['label']); ?>" data-start="<?php echo esc_attr($slot['start']); ?>">
                                <?php echo esc_html($slot['label']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="booking-section">
                    <h3>Account Password Confirmation</h3>
                    <p style="margin: 0 0 10px; color: var(--muted); font-size: 0.92rem;">Enter your current password to authorize changes to this reservation.</p>
                    <input type="password" name="account_password" placeholder="Account password" autocomplete="current-password" required style="width: 100%; max-width: 320px; min-height: 44px; padding: 10px 14px; border-radius: 12px; background: var(--panel-input-bg); border: 1px solid var(--panel-input-border); color: var(--heading); font: inherit; font-size: 0.95rem;">
                </div>

                <div class="booking-summary" id="edit-selection-summary">
                    Loading reservation details...
                </div>

                <div style="display: flex; gap: 12px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary" id="edit-save-btn">Save Changes</button>
                    <a href="<?php echo esc_url(get_permalink()); ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>

            <script>
            (function() {
                const bookingRecords = <?php echo wp_json_encode($all_booking_records); ?>;
                const timeSlots = <?php echo wp_json_encode($time_slots); ?>;
                const currentBookingId = <?php echo (int) $booking_to_edit['ID']; ?>;
                const dateField = document.getElementById('edit-date-input');
                const timeSlotsEl = document.getElementById('edit-time-slots');
                const summaryEl = document.getElementById('edit-selection-summary');
                const saveBtn = document.getElementById('edit-save-btn');

                const hiddenBay = document.getElementById('edit-hidden-bay');
                const hiddenDate = document.getElementById('edit-hidden-date');
                const hiddenTime = document.getElementById('edit-hidden-time');
                const hiddenDuration = document.getElementById('edit-hidden-duration');

                let selectedBay = <?php echo wp_json_encode($booking_to_edit['bay']); ?>;
                let selectedDate = <?php echo wp_json_encode($booking_to_edit['date']); ?>;
                let selectedTime = <?php echo wp_json_encode($booking_to_edit['time']); ?>;
                let selectedDuration = <?php echo (int) $booking_to_edit['duration']; ?>;

                function updateBayTypeUI() {
                    document.querySelectorAll('.bay-type-pill').forEach(pill => pill.classList.remove('selected'));
                    const checkedType = document.querySelector('input[name="edit_bay_type"]:checked');
                    if (checkedType) {
                        const parent = checkedType.closest('.bay-type-pill');
                        if (parent) parent.classList.add('selected');
                    }
                }

                function filterBaysByType() {
                    const checkedType = document.querySelector('input[name="edit_bay_type"]:checked')?.value;
                    const bayPills = document.querySelectorAll('#edit-bay-selector .bay-pill');

                    if (!checkedType) return;

                    let bayStillVisible = false;
                    bayPills.forEach(pill => {
                        const pillType = pill.getAttribute('data-bay-type');
                        if (pillType === checkedType) {
                            pill.style.display = 'inline-flex';
                            const radio = pill.querySelector('input[name="edit_bay_radio"]');
                            if (radio && radio.checked) bayStillVisible = true;
                        } else {
                            pill.style.display = 'none';
                            const radio = pill.querySelector('input[name="edit_bay_radio"]');
                            if (radio) radio.checked = false;
                        }
                    });

                    if (!bayStillVisible) {
                        const firstVisible = document.querySelector('#edit-bay-selector .bay-pill[style*="inline-flex"] input[name="edit_bay_radio"]');
                        if (firstVisible) {
                            firstVisible.checked = true;
                            selectedBay = firstVisible.value;
                        }
                    }
                    updateBayUI();
                    updateTimeSlots();
                }

                function updateBayUI() {
                    document.querySelectorAll('#edit-bay-selector .bay-pill').forEach(pill => pill.classList.remove('selected'));
                    const checkedBay = document.querySelector('input[name="edit_bay_radio"]:checked');
                    if (checkedBay) {
                        const parent = checkedBay.closest('.bay-pill');
                        if (parent) parent.classList.add('selected');
                        selectedBay = checkedBay.value;
                        hiddenBay.value = selectedBay;
                    }
                }

                const originalPaidAmount = <?php echo floatval(get_post_meta($booking_to_edit['ID'], 'ttn_booking_total_price', true) ?: ($booking_to_edit['duration'] * (function_exists('ttn_booking_get_hourly_price') ? ttn_booking_get_hourly_price($booking_to_edit['bay']) : 50))); ?>;

                function calculateEndTimeLabel(startLabel, durationHours) {
                    const slot = timeSlots.find(s => s.label === startLabel);
                    if (!slot || !slot.start) return startLabel;
                    const parts = slot.start.split(':');
                    const startMinutes = (parseInt(parts[0], 10) * 60) + parseInt(parts[1], 10);
                    const endMinutes = startMinutes + (parseInt(durationHours, 10) * 60);
                    let endHour = Math.floor(endMinutes / 60) % 24;
                    const endMinute = endMinutes % 60;
                    const period = endHour >= 12 ? 'PM' : 'AM';
                    let displayHour = endHour % 12;
                    if (displayHour === 0) displayHour = 12;
                    const displayMinute = endMinute < 10 ? '0' + endMinute : endMinute;
                    return displayHour + ':' + displayMinute + ' ' + period;
                }

                function updateDurationUI() {
                    document.querySelectorAll('#edit-duration-selector .duration-pill').forEach(pill => pill.classList.remove('selected'));
                    const checkedDur = document.querySelector('input[name="edit_duration_radio"]:checked');
                    if (checkedDur) {
                        const parent = checkedDur.closest('.duration-pill');
                        if (parent) parent.classList.add('selected');
                        selectedDuration = parseInt(checkedDur.value, 10) || 1;
                        hiddenDuration.value = selectedDuration;
                    }
                }

                function updateTimeRangeUI() {
                    document.querySelectorAll('#edit-time-slots .time-slot-pill').forEach(btn => {
                        const btnTime = btn.getAttribute('data-time');
                        btn.classList.toggle('selected', selectedTime && btnTime === selectedTime);
                    });
                }

                function updateTimeSlots() {
                    selectedDate = dateField.value;
                    hiddenDate.value = selectedDate;

                    const today = new Date();
                    const selectedDateObj = new Date(selectedDate + 'T00:00:00');
                    const todaysDateObj = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                    const isToday = selectedDateObj.getTime() === todaysDateObj.getTime();

                    const booked = bookingRecords
                        .filter(item => {
                            if (item.id && item.id === currentBookingId) return false;
                            const itemBay = (item.bay_key || item.bay || '').replace(/[\s-]+/g, '').toLowerCase();
                            const currentBayNorm = (selectedBay || '').replace(/[\s-]+/g, '').toLowerCase();
                            return itemBay === currentBayNorm && item.date === selectedDate;
                        })
                        .map(item => item.time);

                    document.querySelectorAll('#edit-time-slots .time-slot-pill').forEach((btn, index) => {
                        const slotTime = btn.getAttribute('data-time');
                        const slotStart = btn.getAttribute('data-start');
                        const slotStartObj = new Date(selectedDate + 'T' + slotStart);
                        const isPast = isToday && slotStartObj < today;

                        let isAvailable = true;
                        if (isPast || index + selectedDuration > timeSlots.length) {
                            isAvailable = false;
                        } else {
                            for (let i = 0; i < selectedDuration; i++) {
                                const checkSlot = timeSlots[index + i];
                                if (booked.includes(checkSlot.label)) {
                                    isAvailable = false;
                                    break;
                                }
                            }
                        }

                        btn.classList.remove('selected', 'disabled');
                        if (!isAvailable) {
                            btn.classList.add('disabled');
                            btn.disabled = true;
                        } else {
                            btn.disabled = false;
                        }
                    });

                    updateTimeRangeUI();
                    updateSummary();
                }

                function updateSummary() {
                    const bayRadio = document.querySelector('input[name="edit_bay_radio"]:checked');
                    const pricePerHour = parseFloat(bayRadio?.getAttribute('data-price') || 50);
                    const newTotal = selectedDuration * pricePerHour;
                    const diff = newTotal - originalPaidAmount;

                    if (selectedTime) {
                        const endTime = calculateEndTimeLabel(selectedTime, selectedDuration);
                        let adjustmentHtml = '';

                        if (diff > 0) {
                            adjustmentHtml = `<br/><span style="color: var(--primary); font-weight: 700;">Additional Balance Due: $${diff.toFixed(2)}</span>`;
                            saveBtn.textContent = `Proceed to Payment ($${diff.toFixed(2)})`;
                        } else if (diff < 0) {
                            adjustmentHtml = `<br/><span style="color: #6ee7b7; font-weight: 700;">Refund Credit: $${Math.abs(diff).toFixed(2)}</span>`;
                            saveBtn.textContent = `Save & Process $${Math.abs(diff).toFixed(2)} Refund`;
                        } else {
                            saveBtn.textContent = 'Save Changes';
                        }

                        summaryEl.innerHTML = `<strong>${selectedBay}</strong><br/>${selectedDate} • ${selectedTime} - ${endTime} (${selectedDuration}h)<br/><strong>Updated Total: $${newTotal.toFixed(2)}</strong>${adjustmentHtml}`;
                        hiddenTime.value = selectedTime;
                        saveBtn.disabled = false;
                    } else {
                        summaryEl.innerHTML = `<strong>${selectedBay}</strong><br/>${selectedDate} • Please select an available start time`;
                        saveBtn.disabled = true;
                    }
                }

                document.querySelectorAll('input[name="edit_bay_type"]').forEach(radio => {
                    radio.addEventListener('change', () => {
                        updateBayTypeUI();
                        filterBaysByType();
                    });
                });

                document.querySelectorAll('input[name="edit_bay_radio"]').forEach(radio => {
                    radio.addEventListener('change', () => {
                        updateBayUI();
                        updateTimeSlots();
                    });
                });

                document.querySelectorAll('input[name="edit_duration_radio"]').forEach(radio => {
                    radio.addEventListener('change', () => {
                        updateDurationUI();
                        updateTimeSlots();
                    });
                });

                dateField.addEventListener('click', function() {
                    if (typeof this.showPicker === 'function') {
                        try {
                            this.showPicker();
                        } catch (err) {}
                    }
                });

                dateField.addEventListener('change', () => {
                    updateTimeSlots();
                });

                document.querySelectorAll('#edit-time-slots .time-slot-pill').forEach(btn => {
                    btn.addEventListener('click', () => {
                        if (btn.disabled) return;
                        selectedTime = btn.getAttribute('data-time');
                        updateTimeRangeUI();
                        updateSummary();
                    });
                });

                // Init UI
                updateBayTypeUI();
                filterBaysByType();
                updateBayUI();
                updateDurationUI();
                updateTimeSlots();
            })();
            </script>
        </section>
        <?php endif; ?>

        <section class="account-membership-panel bookings-section">
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
                            <th>Payment</th>
                            <th>Last Updated</th>
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
                            $is_cancelled = ($booking['status'] ?? '') === 'cancelled';
                            $is_past = $booking_date < $today;
                            $price = $booking['duration'] * (function_exists('ttn_booking_get_hourly_price') ? ttn_booking_get_hourly_price($booking['bay']) : 50);
                            
                            // Links to edit and cancel using action handlers
                            $edit_url = get_permalink() . '?action=edit&booking_id=' . $booking['ID'];
                            $cancel_url = wp_nonce_url(add_query_arg(array('ttn_cancel_booking_id' => $booking['ID']), admin_url('admin-post.php?action=ttn_cancel_user_booking')), 'ttn_cancel_booking_nonce');
                            ?>
                            <tr class="<?php echo $is_cancelled ? 'booking-past' : ($is_past ? 'booking-past' : 'booking-upcoming'); ?>">
                                <td data-label="Bay"><?php echo esc_html($booking['bay']); ?></td>
                                <td data-label="Date"><?php echo esc_html($booking['date']); ?></td>
                                <td data-label="Time"><?php echo esc_html($booking['time']); ?> <?php if ($end_time) echo ' - ' . esc_html($end_time); ?></td>
                                <td data-label="Duration"><?php echo esc_html($booking['duration']); ?>h</td>
                                <td data-label="Price">$<?php echo number_format($price, 2); ?></td>
                                <td data-label="Status">
                                    <?php if ($is_cancelled) : ?>
                                        <span style="color: #ff5c5c; font-weight: 700;">Cancelled</span>
                                    <?php elseif (($booking['status'] ?? '') === 'updated') : ?>
                                        <span style="color: var(--primary); font-weight: 700;">Updated</span>
                                    <?php else : ?>
                                        <span style="color: #6ee7b7; font-weight: 600;"><?php echo esc_html($booking['payment_status'] ?: 'Confirmed'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Payment">
                                    <?php $payment = $booking['payment'] ?? array(); ?>
                                    <?php if (!empty($payment)) : ?>
                                        <strong><?php echo esc_html($payment['payment_method'] ?: 'WooCommerce'); ?></strong><br>
                                        <small style="color: var(--muted);">
                                            <?php echo esc_html($payment['order_status']); ?>
                                            <?php if (!empty($booking['member_free_hours'])) : ?>
                                                <?php echo esc_html(' - ' . $booking['member_free_hours'] . ' member ' . ($booking['member_free_hours'] === 1 ? 'hour' : 'hours') . ' redeemed'); ?>
                                            <?php endif; ?>
                                            <?php if (!empty($payment['card_last_four'])) : ?>
                                                <?php echo esc_html(' - Card ending ' . $payment['card_last_four']); ?>
                                            <?php endif; ?>
                                            <?php if (!empty($payment['paid_at'])) : ?>
                                                <?php echo esc_html(' - Paid ' . $payment['paid_at']); ?>
                                            <?php endif; ?>
                                        </small>
                                    <?php else : ?>
                                        <small style="color: var(--muted);">Payment details unavailable</small>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Last Updated">
                                    <?php if (!empty($booking['updated_at'])) : ?>
                                        <small style="color: var(--muted);"><?php echo esc_html(mysql2date('M j, Y', $booking['updated_at'])); ?></small>
                                    <?php else : ?>
                                        <small style="color: var(--muted);">—</small>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Reference"><?php echo esc_html($booking['booking_reference']); ?></td>
                                <td data-label="Actions">
                                    <?php if (!$is_past && !$is_cancelled) : ?>
                                        <a href="<?php echo esc_url($edit_url); ?>" class="btn btn-small">Edit</a>
                                        <button type="button" class="btn btn-small btn-danger btn-cancel-booking-trigger" data-id="<?php echo esc_attr($booking['ID']); ?>" data-ref="<?php echo esc_attr($booking['booking_reference']); ?>" data-bay="<?php echo esc_attr($booking['bay']); ?>" data-date="<?php echo esc_attr($booking['date']); ?>" data-time="<?php echo esc_attr($booking['time']); ?>">Cancel</button>
                                    <?php elseif ($is_cancelled) : ?>
                                        <span class="badge-past" style="background: rgba(239, 68, 68, 0.15); color: #ff5c5c; border: 1px solid rgba(239, 68, 68, 0.4);">Cancelled</span>
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
                <a href="<?php echo esc_url(home_url('/book-a-bay/')); ?>" class="btn btn-primary">Book a Bay</a>
            </p>
        </section>
    </article>

    <!-- Cancel Booking Password Confirmation Modal -->
    <div id="cancel-booking-modal" class="ttn-modal" style="display: none;">
        <div class="ttn-modal-backdrop" id="cancel-modal-backdrop"></div>
        <div class="ttn-modal-card">
            <div class="booking-panel-head" style="margin-bottom: 16px;">
                <div class="booking-panel-title" style="font-size: 1.4rem;">Cancel Reservation</div>
                <button type="button" class="success-close" id="cancel-modal-close" style="position: static;">×</button>
            </div>
            
            <p style="margin: 0 0 8px; color: var(--text); font-size: 1rem;">
                Are you sure you want to cancel reservation <strong id="modal-cancel-ref" style="color: var(--primary);"></strong>?
            </p>
            <p id="modal-cancel-details" style="margin: 0 0 16px; color: var(--muted); font-size: 0.92rem;"></p>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="ttn_cancel_user_booking">
                <?php wp_nonce_field('ttn_cancel_booking_nonce', 'ttn_cancel_booking_nonce'); ?>
                <input type="hidden" name="ttn_cancel_booking_id" id="modal-cancel-booking-id" value="">
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 700; color: var(--heading); font-size: 0.92rem;">
                        Enter Account Password to Confirm
                    </label>
                    <input type="password" name="account_password" id="modal-cancel-password" placeholder="Account password" autocomplete="current-password" required style="width: 100%; box-sizing: border-box; min-height: 44px; padding: 10px 14px; border-radius: 12px; background: var(--panel-input-bg); border: 1px solid var(--panel-input-border); color: var(--heading); font: inherit; font-size: 0.95rem;">
                </div>
                
                <div style="display: flex; gap: 12px; justify-content: flex-end; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary" id="cancel-modal-dismiss">Keep Booking</button>
                    <button type="submit" class="btn btn-small btn-danger" style="width: auto; padding: 10px 20px; min-height: 44px;">Confirm Cancellation</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('cancel-booking-modal');
    var backdrop = document.getElementById('cancel-modal-backdrop');
    var closeBtn = document.getElementById('cancel-modal-close');
    var dismissBtn = document.getElementById('cancel-modal-dismiss');
    var modalRef = document.getElementById('modal-cancel-ref');
    var modalDetails = document.getElementById('modal-cancel-details');
    var modalIdInput = document.getElementById('modal-cancel-booking-id');
    var modalPassInput = document.getElementById('modal-cancel-password');

    function closeModal() {
        if (modal) {
            modal.style.display = 'none';
            if (modalPassInput) modalPassInput.value = '';
        }
    }

    if (backdrop) backdrop.addEventListener('click', closeModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (dismissBtn) dismissBtn.addEventListener('click', closeModal);

    document.querySelectorAll('.btn-cancel-booking-trigger').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var id = this.getAttribute('data-id');
            var ref = this.getAttribute('data-ref');
            var bay = this.getAttribute('data-bay');
            var date = this.getAttribute('data-date');
            var time = this.getAttribute('data-time');

            if (modalRef) modalRef.textContent = ref;
            if (modalDetails) modalDetails.textContent = bay + ' on ' + date + ' at ' + time;
            if (modalIdInput) modalIdInput.value = id;
            if (modal) {
                modal.style.display = 'flex';
                if (modalPassInput) {
                    setTimeout(function() { modalPassInput.focus(); }, 50);
                }
            }
        });
    });
});
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

.account-membership-history {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid var(--border-soft);
}

.account-membership-history h3 {
    margin: 0 0 14px;
}

.account-membership-history-list {
    display: grid;
    gap: 10px;
}

.account-membership-history-item {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 18px;
    padding: 12px 14px;
    background: var(--surface-soft, #f7faf9);
    border-radius: 8px;
    color: #1a2420;
}

.account-membership-history-item strong {
    color: #101010;
}

.account-membership-actions {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid var(--border-soft);
}

.account-membership-actions h3 {
    margin: 0 0 14px;
}

.edit-booking-panel {
    border-color: rgba(var(--primary-rgb), 0.35);
}

.edit-booking-panel h2 {
    margin: 6px 0 16px;
}

.edit-booking-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 16px 28px;
    margin-bottom: 22px;
    padding: 14px 18px;
    background: var(--surface-soft, #f7faf9);
    border-radius: 12px;
    border: 1px solid var(--border-soft);
}

.edit-booking-meta > div {
    display: grid;
    gap: 4px;
}

.edit-booking-panel .form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.edit-booking-panel label {
    display: flex;
    flex-direction: column;
    gap: 8px;
    font-weight: 700;
    color: var(--heading);
    font-size: 0.95rem;
}

.edit-booking-panel input,
.edit-booking-panel select {
    padding: 12px 14px;
    border: 1px solid var(--border-soft);
    border-radius: 12px;
    background: var(--surface);
    color: var(--text);
    font: inherit;
    transition: all 0.2s ease;
}

.edit-booking-panel input:focus,
.edit-booking-panel select:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15);
}

.edit-booking-actions {
    margin-top: 24px;
    display: flex;
    gap: 12px;
    align-items: center;
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
    background: rgba(255, 255, 255, 0.08);
    color: var(--heading, #ffffff);
}

.bookings-table th,
.bookings-table td {
    padding: 14px 12px;
    text-align: left;
    border-bottom: 1px solid var(--border-soft, rgba(255, 255, 255, 0.08));
    color: var(--text, #f5f5f5);
}

.bookings-table tbody tr:hover {
    background: rgba(255, 255, 255, 0.03);
}

.bookings-table .booking-past {
    opacity: 0.5;
}

@media (max-width: 700px) {
    .bookings-table,
    .bookings-table tbody,
    .bookings-table tr,
    .bookings-table td {
        display: block;
    }

    .bookings-table thead {
        display: none;
    }

    .bookings-table {
        margin: 16px 0;
    }

    .bookings-table tr {
        margin-bottom: 16px;
        padding: 12px;
        border: 1px solid var(--border-soft, rgba(255, 255, 255, 0.08));
        border-radius: 8px;
    }

    .bookings-table td {
        display: grid;
        grid-template-columns: minmax(96px, 0.8fr) minmax(0, 1.2fr);
        gap: 12px;
        padding: 9px 0;
    }

    .bookings-table td::before {
        content: attr(data-label);
        color: var(--muted, #b8b8b8);
        font-weight: 700;
    }

    .bookings-table td:last-child {
        border-bottom: 0;
        align-items: center;
    }
}

.badge-past {
    display: inline-block;
    background: rgba(255, 255, 255, 0.08);
    color: var(--muted, #b8b8b8);
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 600;
}

.btn-small {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 76px;
    box-sizing: border-box;
    padding: 7px 0;
    margin-right: 6px;
    background: #000000 !important;
    color: #ffffff !important;
    border: 2px solid #ffffff !important;
    border-radius: 999px;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    text-align: center;
    transition: all 0.2s ease;
}

.btn-small:hover {
    background: #000000 !important;
    border-color: var(--primary) !important;
    color: #ffffff !important;
}

.btn-small.btn-danger {
    background: #000000 !important;
    border: 2px solid #ff5c5c !important;
    color: #ff5c5c !important;
}

.btn-small.btn-danger:hover {
    background: #000000 !important;
    border-color: #ff3333 !important;
    color: #ff3333 !important;
}

.btn-secondary {
    display: inline-block;
    padding: 10px 20px;
    background: transparent;
    border: 1px solid var(--btn-secondary-border, rgba(255, 255, 255, 0.7));
    color: var(--heading, #ffffff);
    border-radius: 8px;
    text-decoration: none;
    margin-left: 10px;
    transition: all 0.2s ease;
}

.btn-secondary:hover {
    background: transparent;
    border-color: var(--primary);
    color: var(--primary);
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

.notice {
    padding: 14px 18px;
    margin: 18px 0 24px;
    border-radius: 12px;
    font-size: 0.95rem;
    font-weight: 500;
    line-height: 1.5;
}

.notice p {
    margin: 0;
}

.notice-success {
    background: rgba(161, 224, 76, 0.12) !important;
    border: 1px solid rgba(161, 224, 76, 0.45) !important;
    color: #ffffff !important;
}

.notice-success p {
    color: #ffffff !important;
}

.notice-error {
    background: rgba(239, 68, 68, 0.12) !important;
    border: 1px solid rgba(239, 68, 68, 0.4) !important;
    color: #fca5a5 !important;
}

.notice-error p {
    color: #fca5a5 !important;
}

.notice-warning {
    background: rgba(245, 158, 11, 0.12) !important;
    border: 1px solid rgba(245, 158, 11, 0.4) !important;
    color: #fcd34d !important;
}

.notice-warning p {
    color: #fcd34d !important;
}

.ttn-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    box-sizing: border-box;
}

.ttn-modal-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(4px);
}

.ttn-modal-card {
    position: relative;
    z-index: 2;
    width: 100%;
    max-width: 460px;
    background: var(--surface-strong, #121212);
    border: 1px solid var(--border-soft);
    border-radius: 20px;
    padding: 24px 26px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.65);
    animation: slideInDown 0.3s ease-out;
}
</style>

<?php get_footer(); ?>
