<?php
/**
 * Plugin Name: Tee Time Nexus Bookings
 * Description: Adds an hourly bay reservation workflow with availability checks, calendar visibility, and Stripe-ready checkout for Tee Time Nexus.
 * Version: 1.0.0
 * Author: Muhammad Rizwan
 */

if (!defined('ABSPATH')) {
    exit;
}

function ttn_booking_send_mail($to, $subject, $message) {
    $sender_name = static function () {
        return 'Tee Time Nexus';
    };

    add_filter('wp_mail_from_name', $sender_name);
    $sent = wp_mail($to, $subject, $message);
    remove_filter('wp_mail_from_name', $sender_name);

    return $sent;
}

function ttn_booking_register_cpt() {
    register_post_type('ttn_booking', array(
        'labels' => array(
            'name' => __('Bookings', 'tee-time-nexus-bookings'),
            'singular_name' => __('Booking', 'tee-time-nexus-bookings'),
        ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-calendar-alt',
        'supports' => array('title', 'editor'),
        'capability_type' => 'post',
    ));
}
add_action('init', 'ttn_booking_register_cpt');

// ===== HELPER FUNCTIONS =====

/**
 * Normalize a string by removing spaces and colons, convert to lowercase
 * Used for bay name comparison across the system
 */
function ttn_normalize_string($string) {
    return strtolower(str_replace(array(':', ' '), '', (string) $string));
}

/**
 * Normalize bay names to canonical values so old bookings remain compatible.
 */
function ttn_normalize_bay_name($bay_name) {
    $normalized = ttn_normalize_string($bay_name);

    $mapping = array(
        'tigerwoodsbay' => 'bay1',
        'jacknicklausbay' => 'bay2',
        'philmickelsonbay' => 'bay3',
        'rorymcilroybay' => 'bay4',
        'bay1' => 'bay1',
        'bay2' => 'bay2',
        'bay3' => 'bay3',
        'bay4' => 'bay4',
    );

    return isset($mapping[$normalized]) ? $mapping[$normalized] : $normalized;
}

/**
 * Convert any bay name format to the current display label.
 */
function ttn_get_bay_display_name($bay_name) {
    $canonical = ttn_normalize_bay_name($bay_name);
    $labels = array(
        'bay1' => 'Bay 1',
        'bay2' => 'Bay 2',
        'bay3' => 'Bay 3',
        'bay4' => 'Bay 4',
    );

    return isset($labels[$canonical]) ? $labels[$canonical] : (string) $bay_name;
}

/**
 * Get all bookings for a specific email address
 */
function ttn_get_user_bookings($email) {
    $user_bookings = array();
    $posts = get_posts(array(
        'post_type' => 'ttn_booking',
        'numberposts' => -1,
        'post_status' => 'publish',
        'fields' => 'ids',
    ));

    foreach ($posts as $post_id) {
        $booking_email = get_post_meta($post_id, 'ttn_booking_email', true);
        if ($booking_email === $email) {
            $user_bookings[] = array(
                'ID' => $post_id,
                'bay' => ttn_get_bay_display_name(get_post_meta($post_id, 'ttn_booking_bay', true)),
                'date' => get_post_meta($post_id, 'ttn_booking_date', true),
                'time' => get_post_meta($post_id, 'ttn_booking_time', true),
                'duration' => intval(get_post_meta($post_id, 'ttn_booking_duration', true) ?: 1),
                'players' => intval(get_post_meta($post_id, 'ttn_booking_players', true) ?: 1),
                'phone' => get_post_meta($post_id, 'ttn_booking_phone', true),
                'name' => get_post_meta($post_id, 'ttn_booking_name', true),
            );
        }
    }

    return $user_bookings;
}

/**
 * Save booking metadata for a post
 * @param int $post_id
 * @param array $booking_data Associative array with keys: name, phone, email, bay, date, time, duration, total_price, payment_status, stripe_token, parent_id
 */
function ttn_save_booking_metadata($post_id, $booking_data) {
    if (isset($booking_data['name'])) {
        update_post_meta($post_id, 'ttn_booking_name', $booking_data['name']);
    }
    if (isset($booking_data['phone'])) {
        update_post_meta($post_id, 'ttn_booking_phone', $booking_data['phone']);
    }
    if (isset($booking_data['email'])) {
        update_post_meta($post_id, 'ttn_booking_email', $booking_data['email']);
    }
    if (isset($booking_data['bay'])) {
        update_post_meta($post_id, 'ttn_booking_bay', $booking_data['bay']);
    }
    if (isset($booking_data['date'])) {
        update_post_meta($post_id, 'ttn_booking_date', $booking_data['date']);
    }
    if (isset($booking_data['time'])) {
        update_post_meta($post_id, 'ttn_booking_time', $booking_data['time']);
    }
    if (isset($booking_data['duration'])) {
        update_post_meta($post_id, 'ttn_booking_duration', $booking_data['duration']);
    }
    if (isset($booking_data['players'])) {
        update_post_meta($post_id, 'ttn_booking_players', $booking_data['players']);
    }
    if (isset($booking_data['total_price'])) {
        update_post_meta($post_id, 'ttn_booking_total_price', $booking_data['total_price']);
    }
    if (isset($booking_data['payment_status'])) {
        update_post_meta($post_id, 'ttn_booking_payment_status', $booking_data['payment_status']);
    }
    if (isset($booking_data['stripe_token'])) {
        update_post_meta($post_id, 'ttn_booking_stripe_token', $booking_data['stripe_token']);
    }
    if (isset($booking_data['parent_id'])) {
        update_post_meta($post_id, 'ttn_booking_parent_id', $booking_data['parent_id']);
    }
}

function ttn_booking_get_time_slots() {
    $slots = array(
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
    return apply_filters('ttn_get_time_slots', $slots);
}

function ttn_booking_get_bays() {
    return array(
        'bay-1' => 'Bay 1',
        'bay-2' => 'Bay 2',
        'bay-3' => 'Bay 3',
        'bay-4' => 'Bay 4',
    );
}

function ttn_booking_get_booking_records() {
    $posts = get_posts(array(
        'post_type' => 'ttn_booking',
        'numberposts' => -1,
        'post_status' => 'publish',
        'fields' => 'ids',
    ));

    $records = array();

    foreach ($posts as $post_id) {
        $records[] = array(
            'bay' => ttn_get_bay_display_name(get_post_meta($post_id, 'ttn_booking_bay', true)),
            'date' => get_post_meta($post_id, 'ttn_booking_date', true),
            'time' => get_post_meta($post_id, 'ttn_booking_time', true),
        );
    }

    return $records;
}

function ttn_booking_get_booked_slots($bay, $date) {
    $bookings = ttn_booking_get_booking_records();
    $booked = array();
    
    // Normalize the incoming bay name
    $bay_normalized = ttn_normalize_bay_name($bay);

    foreach ($bookings as $booking) {
        // Normalize stored bay name for comparison
        $stored_bay = ttn_normalize_bay_name($booking['bay']);
        if ($stored_bay === $bay_normalized && $booking['date'] === $date) {
            $booked[] = $booking['time'];
        }
    }

    return array_values(array_unique($booked));
}

function ttn_booking_is_slot_in_past($date, $time_start) {
    $timezone = wp_timezone();
    $slot_datetime = new DateTime($date . ' ' . $time_start, $timezone);
    $now = new DateTime('now', $timezone);

    return $slot_datetime < $now;
}

function ttn_booking_get_consecutive_slots($start_index, $duration, $time_slots) {
    // Get consecutive time slots starting from start_index
    $slots = array();
    for ($i = 0; $i < $duration; $i++) {
        if (isset($time_slots[$start_index + $i])) {
            $slots[] = $time_slots[$start_index + $i];
        }
    }
    return $slots;
}

function ttn_booking_check_availability_for_duration($bay, $date, $start_time_label, $duration, $time_slots, $bookings) {
    // Find the starting slot index
    $start_index = null;
    foreach ($time_slots as $index => $slot) {
        if ($slot['label'] === $start_time_label) {
            $start_index = $index;
            break;
        }
    }

    if ($start_index === null) {
        return false; // Invalid start time
    }

    // Check if we have enough slots for the requested duration
    if ($start_index + $duration > count($time_slots)) {
        return false; // Not enough slots remaining
    }

    // Normalize bay name for comparison
    $bay_normalized = ttn_normalize_bay_name($bay);

    // Check if ANY of the consecutive slots are booked
    for ($i = 0; $i < $duration; $i++) {
        $slot = $time_slots[$start_index + $i];
        
        // Check if this slot is booked
        foreach ($bookings as $booking) {
            if ($booking['date'] === $date) {
                $booking_bay = ttn_normalize_bay_name($booking['bay']);
                
                if ($booking_bay === $bay_normalized && $booking['time'] === $slot['label']) {
                    return false; // Slot is booked
                }
            }
        }
    }

    return true; // All slots are available
}

function ttn_booking_get_calendar_html($bay = '') {
    $month = current_time('m');
    $year = current_time('Y');
    $date = new DateTime($year . '-' . $month . '-01');
    $days_in_month = (int) $date->format('t');
    $first_day_offset = (int) $date->format('w');
    $month_name = $date->format('F Y');
    $bookings = ttn_booking_get_booking_records();
    $booked_dates = array();

    foreach ($bookings as $booking) {
        if ($bay && ttn_normalize_bay_name($booking['bay']) !== ttn_normalize_bay_name($bay)) {
            continue;
        }

        if (!empty($booking['date']) && substr($booking['date'], 0, 7) === $date->format('Y-m')) {
            $booked_dates[] = $booking['date'];
        }
    }

    $calendar = '<div class="booking-calendar"><div class="calendar-title">' . esc_html($month_name) . '</div><div class="calendar-weekdays"><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span></div><div class="calendar-days">';

    $day_counter = 1;
    $offset = $first_day_offset === 0 ? 6 : $first_day_offset - 1;

    for ($i = 0; $i < $offset; $i++) {
        $calendar .= '<span class="calendar-day empty"></span>';
    }

    for ($day = 1; $day <= $days_in_month; $day++) {
        $day_date = $year . '-' . $month . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
        $has_bookings = in_array($day_date, $booked_dates, true);
        $classes = 'calendar-day';

        if ($has_bookings) {
            $classes .= ' has-bookings';
        }

        $calendar .= '<span class="' . esc_attr($classes) . '"><strong>' . (int) $day . '</strong>' . ($has_bookings ? '<em>Booked</em>' : '') . '</span>';
        $day_counter++;
    }

    $calendar .= '</div></div>';

    return $calendar;
}

function ttn_booking_get_bay_product_id($bay_name) {
    if (!class_exists('WC_Product_Simple')) {
        return 0;
    }

    $sku = str_replace(' ', '-', strtolower($bay_name));
    $products = wc_get_products(array('sku' => $sku, 'limit' => 1, 'status' => 'any'));

    if (!empty($products) && isset($products[0])) {
        return $products[0]->get_id();
    }

    return 0;
}

function ttn_booking_add_to_cart_and_redirect($bay_name, $booking_data = array()) {
    if (!class_exists('WC_Cart') || !function_exists('wc_get_checkout_url')) {
        return false;
    }

    if (!function_exists('WC')) {
        return false;
    }

    $product_id = ttn_booking_get_bay_product_id($bay_name);
    if (!$product_id) {
        return false;
    }

    try {
        $wc = WC();
        if (!$wc || !isset($wc->cart)) {
            return false;
        }

        $cart = $wc->cart;
        if (!$cart) {
            return false;
        }

        $cart->empty_cart();

        $cart_item_data = array();
        if (!empty($booking_data)) {
            $cart_item_data['ttn_booking'] = array(
                'bay' => isset($booking_data['bay']) ? $booking_data['bay'] : $bay_name,
                'date' => isset($booking_data['date']) ? $booking_data['date'] : '',
                'time' => isset($booking_data['time']) ? $booking_data['time'] : '',
                'name' => isset($booking_data['name']) ? $booking_data['name'] : '',
                'email' => isset($booking_data['email']) ? $booking_data['email'] : '',
            );
        }

        $cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);

        return wc_get_checkout_url();
    } catch (Exception $e) {
        return false;
    }
}

function ttn_booking_render_cart_item_data($item_data, $cart_item) {
    if (!empty($cart_item['ttn_booking'])) {
        $booking = $cart_item['ttn_booking'];
        $summary = $booking['bay'] . ' • ' . $booking['date'] . ' • ' . $booking['time'];

        $item_data[] = array(
            'key' => __('Reservation', 'tee-time-nexus-bookings'),
            'value' => esc_html($summary),
        );
    }

    return $item_data;
}
add_filter('woocommerce_get_item_data', 'ttn_booking_render_cart_item_data', 10, 2);

function ttn_booking_store_order_line_data($item, $cart_item_key, $values, $order) {
    if (!empty($values['ttn_booking'])) {
        $booking = $values['ttn_booking'];
        $item->add_meta_data('ttn_booking_bay', $booking['bay']);
        $item->add_meta_data('ttn_booking_date', $booking['date']);
        $item->add_meta_data('ttn_booking_time', $booking['time']);
        $item->add_meta_data('ttn_booking_name', $booking['name']);
        $item->add_meta_data('ttn_booking_email', $booking['email']);
    }
}
add_action('woocommerce_checkout_create_order_line_item', 'ttn_booking_store_order_line_data', 10, 4);

function ttn_booking_shortcode() {
    $time_slots = ttn_booking_get_time_slots();
    $bays = ttn_booking_get_bays();
    $default_date = current_time('Y-m-d');
    $confirmed = isset($_GET['booking']) && $_GET['booking'] === 'confirmed';

    // Get booking records using the centralized function
    $booking_records = ttn_booking_get_booking_records();

    ob_start();
    ?>
    <div class="booking-card">
        <?php if ($confirmed) : ?>
            <div class="booking-success-alert" id="successAlert">
                <div class="success-icon">✓</div>
                <div class="success-content">
                    <h2>Booking Confirmed!</h2>
                    <p>Your reservation has been successfully booked and paid.</p>
                    <div class="success-details">
                        <p>✓ Confirmation email sent to your inbox</p>
                        <p>✓ SMS notification if number provided</p>
                    </div>
                    <div class="success-actions">
                        <a href="<?php echo esc_url(home_url('/my-account/')); ?>" class="btn btn-small-white">View My Bookings</a>
                        <a href="<?php echo esc_url(home_url('/book-a-bay/')); ?>" class="btn btn-small-white">Book Another Bay</a>
                    </div>
                    <div class="success-timer">
                        <p>Redirecting to booking page in <span id="countdown">60</span> seconds...</p>
                    </div>
                </div>
                <button class="success-close" onclick="document.getElementById('successAlert').style.display='none';">×</button>
            </div>
        <?php endif; ?>

        <p class="booking-note">Select a bay, choose your date and time, then proceed to payment.</p>

        <div class="booking-section">
            <h3>Select Your Bay</h3>
            <div class="bay-selector" id="ttn-bay-selector">
                <?php foreach ($bays as $bay_key => $bay_label) : ?>
                    <label class="bay-pill">
                        <input type="radio" name="bay" value="<?php echo esc_attr($bay_label); ?>" data-bay-key="<?php echo esc_attr($bay_key); ?>" />
                        <span><?php echo esc_html($bay_label); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="booking-section">
            <h3>Select Date</h3>
            <input type="date" id="ttn-date" value="<?php echo esc_attr($default_date); ?>" min="<?php echo esc_attr($default_date); ?>" required>
        </div>

        <div class="booking-section">
            <h3>Duration (Hours)</h3>
            <div class="duration-selector" id="ttn-duration-selector">
                <?php for ($h = 1; $h <= 8; $h++) : ?>
                    <label class="duration-pill">
                        <input type="radio" name="duration" value="<?php echo esc_attr($h); ?>" data-duration="<?php echo esc_attr($h); ?>" <?php checked($h, 1); ?> />
                        <span><?php echo esc_html($h); ?> <?php echo $h === 1 ? 'Hour' : 'Hours'; ?></span>
                    </label>
                <?php endfor; ?>
            </div>
        </div>

        <div class="booking-section">
            <h3>Players</h3>
            <div class="player-selector" id="ttn-player-selector">
                <?php for ($p = 1; $p <= 4; $p++) : ?>
                    <label class="player-pill">
                        <input type="radio" name="players" value="<?php echo esc_attr($p); ?>" data-players="<?php echo esc_attr($p); ?>" <?php checked($p, 1); ?> />
                        <span><?php echo esc_html($p); ?></span>
                    </label>
                <?php endfor; ?>
            </div>
        </div>

        <div class="booking-section">
            <h3>Select Start Time</h3>
            <div class="time-slots" id="ttn-time-slots">
                <?php foreach ($time_slots as $slot) : ?>
                    <button type="button" class="time-slot-pill" data-time="<?php echo esc_attr($slot['label']); ?>" data-start="<?php echo esc_attr($slot['start']); ?>">
                        <?php echo esc_html($slot['label']); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="booking-summary" id="ttn-selection-summary">
            Choose a bay, date, and time to continue.
        </div>

        <button class="btn btn-primary" id="ttn-proceed-to-payment" disabled>Proceed to Payment</button>
    </div>

    <script>
    (function () {
        const bookingRecords = <?php echo wp_json_encode($booking_records); ?>;
        const timeSlots = <?php echo wp_json_encode($time_slots); ?>;
        const baySelector = document.getElementById('ttn-bay-selector');
        const dateField = document.getElementById('ttn-date');
        const durationSelector = document.getElementById('ttn-duration-selector');
        const timeSlots_el = document.getElementById('ttn-time-slots');
        const summary = document.getElementById('ttn-selection-summary');
        const proceedBtn = document.getElementById('ttn-proceed-to-payment');

        const PRICE_PER_HOUR = 50;

        let selectedBay = null;
        let selectedDate = null;
        let selectedTime = null;
        let selectedDuration = 1;
        let selectedPlayers = 1;

        function updateTimeSlots() {
            const bay = document.querySelector('input[name="bay"]:checked');
            if (!bay) {
                timeSlots_el.style.display = 'none';
                return;
            }

            timeSlots_el.style.display = 'flex';
            selectedBay = bay.value;
            selectedDate = dateField.value;
            selectedDuration = parseInt(document.querySelector('input[name="duration"]:checked')?.value || 1);

            const today = new Date();
            const selectedDateObj = new Date(selectedDate + 'T00:00:00');
            const todaysDateObj = new Date(today.getFullYear(), today.getMonth(), today.getDate());
            const isToday = selectedDateObj.getTime() === todaysDateObj.getTime();

            const booked = bookingRecords
                .filter(item => {
                    const itemBay = (item.bay || '').replace(/\s+/g, '').toLowerCase();
                    const selectedBayNorm = selectedBay.replace(/\s+/g, '').toLowerCase();
                    return itemBay === selectedBayNorm && item.date === selectedDate;
                })
                .map(item => item.time);

            document.querySelectorAll('.time-slot-pill').forEach((btn, index) => {
                const slotTime = btn.getAttribute('data-time');
                const slotStart = btn.getAttribute('data-start');
                const slotStartObj = new Date(selectedDate + 'T' + slotStart);
                const isPast = isToday && slotStartObj < today;

                // Check if this slot OR any following slots for the duration are booked
                let isAvailable = true;
                if (isPast || index + selectedDuration > timeSlots.length) {
                    isAvailable = false;
                } else {
                    // Check all consecutive slots
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

            updateSummary();
        }

        function updateSelectedTimeRangeUI() {
            if (!selectedTime) {
                document.querySelectorAll('.time-slot-pill').forEach(btn => btn.classList.remove('selected'));
                return;
            }

            const duration = parseInt(document.querySelector('input[name="duration"]:checked')?.value || 1, 10);
            const startIndex = timeSlots.findIndex(slot => slot.label === selectedTime);
            if (startIndex === -1) {
                return;
            }

            document.querySelectorAll('.time-slot-pill').forEach((btn, index) => {
                const shouldSelect = index >= startIndex && index < startIndex + duration;
                btn.classList.toggle('selected', shouldSelect);
            });
        }

        function updateSummary() {
            const bayInput = document.querySelector('input[name="bay"]:checked');
            const bay = bayInput ? bayInput.value : 'No bay selected';
            const date = dateField.value;
            const duration = document.querySelector('input[name="duration"]:checked')?.value || 1;
            const players = document.querySelector('input[name="players"]:checked')?.value || 1;
            const time = selectedTime || 'No time selected';
            const totalPrice = parseInt(duration) * PRICE_PER_HOUR;

            if (selectedTime) {
                const startIndex = timeSlots.findIndex(s => s.label === selectedTime);
                const endTime = startIndex + parseInt(duration) - 1 < timeSlots.length 
                    ? timeSlots[startIndex + parseInt(duration) - 1].label 
                    : selectedTime;
                
                summary.innerHTML = `<strong>${bay}</strong><br/>${date} • ${time} - ${endTime} (${duration}h) • ${players}<br/><strong>Total: $${totalPrice}</strong>`;
            } else {
                summary.innerHTML = '<strong>' + bay + '</strong><br/>' + date + ' • No time selected • ' + players + '<br/><strong>$' + totalPrice + '</strong>';
            }

            if (selectedBay && selectedDate && selectedTime) {
                proceedBtn.disabled = false;
            } else {
                proceedBtn.disabled = true;
            }
        }

        function updateDurationSelectionUI() {
            document.querySelectorAll('.duration-pill').forEach(pill => {
                pill.classList.remove('selected');
            });

            const checkedDuration = document.querySelector('input[name="duration"]:checked');
            if (checkedDuration) {
                const selectedPill = checkedDuration.closest('.duration-pill');
                if (selectedPill) {
                    selectedPill.classList.add('selected');
                }
            }
        }

        function updateBaySelectionUI() {
            document.querySelectorAll('.bay-pill').forEach(pill => {
                pill.classList.remove('selected');
            });

            const checkedBay = document.querySelector('input[name="bay"]:checked');
            if (checkedBay) {
                const selectedPill = checkedBay.closest('.bay-pill');
                if (selectedPill) {
                    selectedPill.classList.add('selected');
                }
            }
        }

        function updatePlayersSelectionUI() {
            document.querySelectorAll('.player-pill').forEach(pill => {
                pill.classList.remove('selected');
            });

            const checkedPlayers = document.querySelector('input[name="players"]:checked');
            if (checkedPlayers) {
                const selectedPill = checkedPlayers.closest('.player-pill');
                if (selectedPill) {
                    selectedPill.classList.add('selected');
                }
            }
        }

        document.querySelectorAll('input[name="bay"]').forEach(radio => {
            radio.addEventListener('change', () => {
                updateBaySelectionUI();
                selectedTime = null;
                document.querySelectorAll('.time-slot-pill').forEach(btn => btn.classList.remove('selected'));
                updateTimeSlots();
            });

            // Clicking an already checked radio (common after browser back) won't fire change.
            // This ensures Bay 1 can be used immediately when state is restored.
            radio.addEventListener('click', () => {
                updateBaySelectionUI();
                updateTimeSlots();
            });
        });

        document.querySelectorAll('input[name="duration"]').forEach(radio => {
            radio.addEventListener('change', () => {
                updateDurationSelectionUI();
                const nextSelectedTime = selectedTime;
                if (nextSelectedTime) {
                    updateSelectedTimeRangeUI();
                } else {
                    document.querySelectorAll('.time-slot-pill').forEach(btn => btn.classList.remove('selected'));
                }
                updateTimeSlots();
            });
        });

        document.querySelectorAll('input[name="players"]').forEach(radio => {
            radio.addEventListener('change', () => {
                updatePlayersSelectionUI();
                selectedPlayers = parseInt(radio.value, 10) || 1;
                updateSummary();
            });
        });

        dateField.addEventListener('change', () => {
            selectedTime = null;
            document.querySelectorAll('.time-slot-pill').forEach(btn => btn.classList.remove('selected'));
            updateTimeSlots();
        });

        document.querySelectorAll('.time-slot-pill').forEach(btn => {
            btn.addEventListener('click', (e) => {
                if (btn.disabled) return;
                selectedTime = btn.getAttribute('data-time');
                updateSelectedTimeRangeUI();
                updateSummary();
            });
        });

        proceedBtn.addEventListener('click', () => {
            const checkoutUrl = new URL('<?php echo esc_url(home_url('/booking-checkout/')); ?>');
            checkoutUrl.searchParams.set('bay', encodeURIComponent(selectedBay));
            checkoutUrl.searchParams.set('date', selectedDate);
            checkoutUrl.searchParams.set('time', encodeURIComponent(selectedTime));
            checkoutUrl.searchParams.set('duration', document.querySelector('input[name="duration"]:checked').value);
            checkoutUrl.searchParams.set('players', document.querySelector('input[name="players"]:checked').value);
            window.location.href = checkoutUrl.toString();
        });

        // Initialize from browser-restored state (e.g., when user navigates back).
        updateBaySelectionUI();
        updateDurationSelectionUI();
        updatePlayersSelectionUI();
        if (document.querySelector('input[name="bay"]:checked')) {
            updateTimeSlots();
        } else {
            timeSlots_el.style.display = 'none';
        }
    })();
    </script>
    <?php if ($confirmed) : ?>
    <script>
    (function() {
        const successAlert = document.getElementById('successAlert');
        const countdownEl = document.getElementById('countdown');
        let seconds = 60;

        if (successAlert) {
            const timer = setInterval(() => {
                seconds--;
                if (countdownEl) {
                    countdownEl.textContent = seconds;
                }
                if (seconds <= 0) {
                    clearInterval(timer);
                    window.location.href = '<?php echo esc_url(home_url('/book-a-bay/')); ?>';
                }
            }, 1000);
        }
    })();
    </script>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}
add_shortcode('ttn_booking_form', 'ttn_booking_shortcode');

function ttn_booking_submit() {
    if (!isset($_POST['ttn_booking_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ttn_booking_nonce'])), 'ttn_booking_submit')) {
        wp_die(__('Security check failed.', 'tee-time-nexus-bookings'));
    }

    $name = sanitize_text_field(wp_unslash($_POST['name']));
    $phone = sanitize_text_field(wp_unslash($_POST['phone']));
    $email = sanitize_email(wp_unslash($_POST['email']));
    $bay = sanitize_text_field(wp_unslash($_POST['bay']));
    $date = sanitize_text_field(wp_unslash($_POST['date']));
    $time = sanitize_text_field(wp_unslash($_POST['time']));
    $notes = sanitize_textarea_field(wp_unslash($_POST['notes']));
    $players = isset($_POST['players']) ? max(1, min(4, intval($_POST['players']))) : 1;

    $time_slots = ttn_booking_get_time_slots();
    $selected_slot = null;
    foreach ($time_slots as $slot) {
        if ($slot['label'] === $time) {
            $selected_slot = $slot;
            break;
        }
    }

    if (!$selected_slot) {
        wp_die(__('Please choose a valid time slot.', 'tee-time-nexus-bookings'));
    }

    if (ttn_booking_is_slot_in_past($date, $selected_slot['start'])) {
        wp_die(__('Please choose a future time slot.', 'tee-time-nexus-bookings'));
    }

    $booked_slots = ttn_booking_get_booked_slots($bay, $date);
    if (in_array($time, $booked_slots, true)) {
        wp_die(__('That time slot is no longer available. Please choose another time.', 'tee-time-nexus-bookings'));
    }

    $post_id = wp_insert_post(array(
        'post_type' => 'ttn_booking',
        'post_status' => 'publish',
        'post_title' => $name . ' - ' . $date,
        'post_content' => sprintf(
            "Bay: %s\nDate: %s\nTime: %s\nPhone: %s\nEmail: %s\nNotes: %s",
            $bay,
            $date,
            $time,
            $phone,
            $email,
            $notes
        ),
    ), true);

    if (!is_wp_error($post_id)) {
        update_post_meta($post_id, 'ttn_booking_name', $name);
        update_post_meta($post_id, 'ttn_booking_phone', $phone);
        update_post_meta($post_id, 'ttn_booking_email', $email);
        update_post_meta($post_id, 'ttn_booking_bay', $bay);
        update_post_meta($post_id, 'ttn_booking_date', $date);
        update_post_meta($post_id, 'ttn_booking_time', $time);
        update_post_meta($post_id, 'ttn_booking_players', $players);
        update_post_meta($post_id, 'ttn_booking_notes', $notes);

        $admin_email = get_option('admin_email');
        $subject = 'New Tee Time Nexus Booking Request';
        $message = sprintf(
            "New booking request from %s.\n\nBay: %s\nDate: %s\nTime: %s\nPhone: %s\nEmail: %s\nNotes: %s",
            $name,
            $bay,
            $date,
            $time,
            $phone,
            $email,
            $notes
        );

        ttn_booking_send_mail($admin_email, $subject, $message);
        ttn_booking_send_mail($email, 'Your Tee Time Nexus booking request', sprintf(
            "Hi %s,\n\nThanks for your request. We received your reservation for %s on %s from %s for %d player%s.\n\nPlease complete checkout to secure your booking.",
            $name,
            $bay,
            $date,
            $time,
            $players,
            $players === 1 ? '' : 's'
        ));
    }

    $checkout_url = ttn_booking_add_to_cart_and_redirect($bay, array(
        'bay' => $bay,
        'date' => $date,
        'time' => $time,
        'name' => $name,
        'email' => $email,
    ));
    if ($checkout_url) {
        wp_safe_redirect($checkout_url);
        exit;
    }

    wp_safe_redirect(home_url('/book-a-bay/?booking=success'));
    exit;
}
add_action('admin_post_ttn_booking_submit', 'ttn_booking_submit');
add_action('admin_post_nopriv_ttn_booking_submit', 'ttn_booking_submit');

function ttn_booking_checkout() {
    if (!isset($_POST['ttn_checkout_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ttn_checkout_nonce'])), 'ttn_booking_checkout')) {
        wp_die(__('Security check failed.', 'tee-time-nexus-bookings'));
    }

    $name = sanitize_text_field(wp_unslash($_POST['name']));
    $email = sanitize_email(wp_unslash($_POST['email']));
    $phone = sanitize_text_field(wp_unslash($_POST['phone']));
    $bay = sanitize_text_field(wp_unslash($_POST['bay']));
    $date = sanitize_text_field(wp_unslash($_POST['date']));
    $time = trim(sanitize_text_field(wp_unslash($_POST['time'])));
    $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 1;
    $players = isset($_POST['players']) ? max(1, min(4, intval($_POST['players']))) : 1;
    $stripe_token = isset($_POST['stripeToken']) ? sanitize_text_field(wp_unslash($_POST['stripeToken'])) : '';

    if (!$stripe_token) {
        wp_die(__('Payment token is missing.', 'tee-time-nexus-bookings'));
    }

    if (!$time || $time === 'null' || $time === 'undefined') {
        wp_die(__('Please select a valid time slot and try again.', 'tee-time-nexus-bookings'));
    }

    $time_slots = ttn_booking_get_time_slots();
    $selected_slot = null;
    $start_index = null;
    
    // Normalize time for comparison
    $time_normalized = ttn_normalize_string($time);
    
    foreach ($time_slots as $index => $slot) {
        $slot_normalized = ttn_normalize_string($slot['label']);
        if ($slot_normalized === $time_normalized) {
            $selected_slot = $slot;
            $start_index = $index;
            break;
        }
    }

    if (!$selected_slot) {
        // Debug: show what we received vs what we expected
        $available_times = array_map(function($s) { return $s['label']; }, $time_slots);
        $error_msg = sprintf(
            'Invalid time slot. Received: "%s" (length: %d). Available: %s',
            $time,
            strlen($time),
            implode(', ', $available_times)
        );
        wp_die(__($error_msg, 'tee-time-nexus-bookings'));
    }

    // Check if all consecutive slots are available
    if ($start_index + $duration > count($time_slots)) {
        wp_die(__('Not enough consecutive hours available for the selected time.', 'tee-time-nexus-bookings'));
    }

    // Check all consecutive slots for conflicts
    $bookings = ttn_booking_get_booking_records();
    for ($i = 0; $i < $duration; $i++) {
        $check_slot = $time_slots[$start_index + $i];
        if (ttn_booking_is_slot_in_past($date, $check_slot['start'])) {
            wp_die(__('Cannot book a time slot in the past.', 'tee-time-nexus-bookings'));
        }
        
        // Check if booked
        foreach ($bookings as $booking) {
            $booking_bay = ttn_normalize_bay_name($booking['bay']);
            $check_bay = ttn_normalize_bay_name($bay);
            
            if ($booking_bay === $check_bay && $booking['date'] === $date && $booking['time'] === $check_slot['label']) {
                wp_die(__('One or more of the requested time slots are no longer available.', 'tee-time-nexus-bookings'));
            }
        }
    }

    // Create booking entries for each hour
    $total_price = $duration * 50;
    $parent_booking_id = null;

    for ($i = 0; $i < $duration; $i++) {
        $hour_slot = $time_slots[$start_index + $i];
        $hour_time_label = $hour_slot['label'];

        $post_id = wp_insert_post(array(
            'post_type' => 'ttn_booking',
            'post_status' => 'publish',
            'post_title' => $name . ' - ' . $bay . ' - ' . $date,
            'post_content' => sprintf(
                "Bay: %s\nDate: %s\nTime: %s\nDuration: %d hours\nPlayers: %d\nName: %s\nPhone: %s\nEmail: %s\nStripe Token: %s",
                $bay,
                $date,
                $hour_time_label,
                $duration,
                $players,
                $name,
                $phone,
                $email,
                $stripe_token
            ),
        ), true);

        if (!is_wp_error($post_id)) {
            // Save booking metadata using centralized helper
            ttn_save_booking_metadata($post_id, array(
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'bay' => $bay,
                'date' => $date,
                'time' => $hour_time_label,
                'duration' => $duration,
                'players' => $players,
                'total_price' => $total_price,
                'payment_status' => 'completed',
                'stripe_token' => $stripe_token,
                'parent_id' => ($i > 0) ? $parent_booking_id : null,
            ));

            // Keep track of parent booking
            if ($i === 0) {
                $parent_booking_id = $post_id;
            }
        }
    }

    // Send confirmation email (only once, from first booking)
    if ($parent_booking_id) {
        $end_time_label = $time_slots[$start_index + $duration - 1]['label'];
        
        $subject = 'Your Tee Time Nexus Booking Confirmation';
        $message = sprintf(
            "Dear %s,\n\nYour reservation has been confirmed!\n\n" .
            "Bay: %s\n" .
            "Date: %s\n" .
            "Time: %s - %s (%d hours)\n" .
            "Amount Paid: $%.2f\n\n" .
            "Thank you for booking with Tee Time Nexus!\n\n" .
            "Questions? Reply to this email.",
            $name,
            $bay,
            $date,
            $selected_slot['label'],
            $end_time_label,
            $duration,
            $total_price
        );
        ttn_booking_send_mail($email, $subject, $message);

        // Send admin notification
        $admin_email = get_option('admin_email');
        $admin_subject = 'New Booking: ' . $name . ' - ' . $bay;
        $admin_message = sprintf(
            "New booking received!\n\n" .
            "Name: %s\n" .
            "Email: %s\n" .
            "Phone: %s\n" .
            "Bay: %s\n" .
            "Date: %s\n" .
            "Time: %s - %s\n" .
            "Duration: %d hours\n" .
            "Players: %d\n" .
            "Amount: $%.2f\n" .
            "Payment Status: Completed",
            $name,
            $email,
            $phone,
            $bay,
            $date,
            $selected_slot['label'],
            $end_time_label,
            $duration,
            $players,
            $total_price
        );
        ttn_booking_send_mail($admin_email, $admin_subject, $admin_message);
    }

    wp_safe_redirect(home_url('/book-a-bay/?booking=confirmed&id=' . $parent_booking_id));
    exit;
}
add_action('admin_post_ttn_booking_checkout', 'ttn_booking_checkout');
add_action('admin_post_nopriv_ttn_booking_checkout', 'ttn_booking_checkout');

// ===== ADMIN BOOKING DASHBOARD =====

function ttn_add_admin_menu() {
    add_menu_page(
        'Booking Management',
        'Bookings Manager',
        'manage_options',
        'ttn-bookings-dashboard',
        'ttn_render_booking_dashboard',
        'dashicons-calendar-alt',
        25
    );

    add_submenu_page(
        'ttn-bookings-dashboard',
        'All Bookings',
        'All Bookings',
        'manage_options',
        'ttn-bookings-dashboard',
        'ttn_render_booking_dashboard'
    );
}
add_action('admin_menu', 'ttn_add_admin_menu');

function ttn_render_booking_dashboard() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    // Handle actions
    if (isset($_GET['action']) && isset($_GET['booking_id'])) {
        $booking_id = intval($_GET['booking_id']);
        $action = sanitize_text_field($_GET['action']);
        $nonce = isset($_GET['nonce']) ? sanitize_text_field($_GET['nonce']) : '';

        if (!wp_verify_nonce($nonce, 'ttn_booking_action')) {
            wp_die('Nonce verification failed');
        }

        if ($action === 'delete') {
            wp_delete_post($booking_id);
            echo '<div class="notice notice-success is-dismissible"><p>Booking deleted successfully.</p></div>';
        } elseif ($action === 'send_reminder') {
            ttn_send_booking_reminder($booking_id);
            echo '<div class="notice notice-success is-dismissible"><p>Reminder email sent to customer.</p></div>';
        }
    }

    // Handle edit/update
    if (isset($_POST['ttn_update_booking']) && check_admin_referer('ttn_update_booking_nonce')) {
        $booking_id = intval($_POST['booking_id']);
        $bay = sanitize_text_field($_POST['bay']);
        $date = sanitize_text_field($_POST['date']);
        $time = sanitize_text_field($_POST['time']);
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);

        ttn_save_booking_metadata($booking_id, array(
            'bay' => $bay,
            'date' => $date,
            'time' => $time,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        ));

        wp_update_post(array(
            'ID' => $booking_id,
            'post_title' => $name . ' - ' . $bay . ' - ' . $date,
        ));

        echo '<div class="notice notice-success is-dismissible"><p>Booking updated successfully.</p></div>';
    }

    // Check if editing
    $edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
    $booking_to_edit = null;

    if ($edit_id) {
        $booking_to_edit = array(
            'ID' => $edit_id,
            'name' => get_post_meta($edit_id, 'ttn_booking_name', true),
            'email' => get_post_meta($edit_id, 'ttn_booking_email', true),
            'phone' => get_post_meta($edit_id, 'ttn_booking_phone', true),
            'bay' => ttn_get_bay_display_name(get_post_meta($edit_id, 'ttn_booking_bay', true)),
            'date' => get_post_meta($edit_id, 'ttn_booking_date', true),
            'time' => get_post_meta($edit_id, 'ttn_booking_time', true),
        );
    }

    $bays = ttn_booking_get_bays();
    $time_slots = ttn_booking_get_time_slots();
    $bookings = ttn_booking_get_booking_records();
    ?>
    <div class="wrap">
        <h1>Booking Management Dashboard</h1>

        <?php if ($booking_to_edit) : ?>
        <div style="background: #f8f9fa; padding: 20px; margin-bottom: 20px; border-radius: 5px;">
            <h2>Edit Booking</h2>
            <form method="post">
                <?php wp_nonce_field('ttn_update_booking_nonce'); ?>
                <input type="hidden" name="ttn_update_booking" value="1">
                <input type="hidden" name="booking_id" value="<?php echo esc_attr($booking_to_edit['ID']); ?>">
                
                <table class="form-table">
                    <tr>
                        <th><label for="name">Customer Name</label></th>
                        <td><input type="text" id="name" name="name" value="<?php echo esc_attr($booking_to_edit['name']); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="email">Email</label></th>
                        <td><input type="email" id="email" name="email" value="<?php echo esc_attr($booking_to_edit['email']); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="phone">Phone</label></th>
                        <td><input type="tel" id="phone" name="phone" value="<?php echo esc_attr($booking_to_edit['phone']); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="bay">Bay</label></th>
                        <td>
                            <select id="bay" name="bay" required>
                                <?php foreach ($bays as $key => $label) : ?>
                                    <option value="<?php echo esc_attr($label); ?>" <?php selected($booking_to_edit['bay'], $label); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="date">Date</label></th>
                        <td><input type="date" id="date" name="date" value="<?php echo esc_attr($booking_to_edit['date']); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="time">Time</label></th>
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
                </table>

                <p>
                    <button type="submit" class="button button-primary">Update Booking</button>
                    <a href="?page=ttn-bookings-dashboard" class="button">Cancel</a>
                </p>
            </form>
        </div>
        <?php endif; ?>

        <h2>All Bookings</h2>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th>Customer Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Bay</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($bookings)) : ?>
                    <?php foreach ($bookings as $booking) : ?>
                        <?php
                        $post_id = null;
                        // Find the post ID for this booking
                        $posts = get_posts(array(
                            'post_type' => 'ttn_booking',
                            'meta_query' => array(
                                array(
                                    'key' => 'ttn_booking_bay',
                                    'value' => $booking['bay'],
                                ),
                                array(
                                    'key' => 'ttn_booking_date',
                                    'value' => $booking['date'],
                                ),
                            ),
                            'numberposts' => 1,
                        ));
                        if (!empty($posts)) {
                            $post_id = $posts[0]->ID;
                            $name = get_post_meta($post_id, 'ttn_booking_name', true);
                            $email = get_post_meta($post_id, 'ttn_booking_email', true);
                            $phone = get_post_meta($post_id, 'ttn_booking_phone', true);
                        }
                        ?>
                        <tr>
                            <td><?php echo esc_html($name ?? ''); ?></td>
                            <td><?php echo esc_html($email ?? ''); ?></td>
                            <td><?php echo esc_html($phone ?? ''); ?></td>
                            <td><?php echo esc_html($booking['bay']); ?></td>
                            <td><?php echo esc_html($booking['date']); ?></td>
                            <td><?php echo esc_html($booking['time']); ?></td>
                            <td>
                                <?php if ($post_id) : ?>
                                    <?php
                                    $edit_url = wp_nonce_url(admin_url('admin.php?page=ttn-bookings-dashboard&edit=' . $post_id), 'ttn_booking_action', 'nonce');
                                    $reminder_url = wp_nonce_url(admin_url('admin.php?page=ttn-bookings-dashboard&action=send_reminder&booking_id=' . $post_id), 'ttn_booking_action', 'nonce');
                                    $delete_url = wp_nonce_url(admin_url('admin.php?page=ttn-bookings-dashboard&action=delete&booking_id=' . $post_id), 'ttn_booking_action', 'nonce');
                                    ?>
                                    <a href="<?php echo esc_url($edit_url); ?>" class="button button-small">Edit</a>
                                    <a href="<?php echo esc_url($reminder_url); ?>" class="button button-small">Send Reminder</a>
                                    <a href="<?php echo esc_url($delete_url); ?>" class="button button-small button-link-delete" onclick="return confirm('Are you sure?');">Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="7">No bookings found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function ttn_send_booking_reminder($booking_id) {
    $name = get_post_meta($booking_id, 'ttn_booking_name', true);
    $email = get_post_meta($booking_id, 'ttn_booking_email', true);
    $bay = ttn_get_bay_display_name(get_post_meta($booking_id, 'ttn_booking_bay', true));
    $date = get_post_meta($booking_id, 'ttn_booking_date', true);
    $time = get_post_meta($booking_id, 'ttn_booking_time', true);

    $subject = 'Reminder: Your Upcoming Tee Time Nexus Booking';
    $message = sprintf(
        "Hi %s,\n\nThis is a friendly reminder about your upcoming reservation:\n\n" .
        "Bay: %s\n" .
        "Date: %s\n" .
        "Time: %s\n\n" .
        "We look forward to seeing you!\n\n" .
        "Best regards,\n" .
        "Tee Time Nexus Team",
        $name,
        $bay,
        $date,
        $time
    );

    wp_mail($email, $subject, $message);
}

// ===== USER-FACING CRUD HANDLERS (BUSINESS LOGIC) =====

/**
 * Update a user booking (CRUD: Update)
 * Returns: array with 'success' boolean and 'message' string
 */
function ttn_crud_update_user_booking($booking_id, $user_email, $booking_data) {
    // Verify booking belongs to user
    $booking_email = get_post_meta($booking_id, 'ttn_booking_email', true);
    if ($booking_email !== $user_email) {
        return array('success' => false, 'message' => 'You do not have permission to edit this booking.');
    }

    // Validate required fields
    if (empty($booking_data['date']) || empty($booking_data['time']) || empty($booking_data['duration'])) {
        return array('success' => false, 'message' => 'Missing required booking information.');
    }

    // Validate date is not in past
    $time_slots = ttn_booking_get_time_slots();
    $selected_slot = null;
    foreach ($time_slots as $slot) {
        if ($slot['label'] === $booking_data['time']) {
            $selected_slot = $slot;
            break;
        }
    }

    if (!$selected_slot) {
        return array('success' => false, 'message' => 'Invalid time slot.');
    }

    if (ttn_booking_is_slot_in_past($booking_data['date'], $selected_slot['start'])) {
        return array('success' => false, 'message' => 'Cannot book a time slot in the past.');
    }

    // Update the booking
    ttn_save_booking_metadata($booking_id, array(
        'date' => $booking_data['date'],
        'time' => $booking_data['time'],
        'duration' => intval($booking_data['duration']),
    ));

    return array('success' => true, 'message' => 'Booking updated successfully.');
}

/**
 * Cancel a user booking (CRUD: Delete)
 * Returns: array with 'success' boolean and 'message' string
 */
function ttn_crud_cancel_user_booking($booking_id, $user_email) {
    // Verify booking belongs to user
    $booking_email = get_post_meta($booking_id, 'ttn_booking_email', true);
    if ($booking_email !== $user_email) {
        return array('success' => false, 'message' => 'You do not have permission to cancel this booking.');
    }

    // Verify booking exists
    $booking = get_post($booking_id);
    if (!$booking || $booking->post_type !== 'ttn_booking') {
        return array('success' => false, 'message' => 'Booking not found.');
    }

    // Delete the booking
    $deleted = wp_delete_post($booking_id, true);
    if (!$deleted) {
        return array('success' => false, 'message' => 'Failed to cancel booking.');
    }

    return array('success' => true, 'message' => 'Booking cancelled successfully.');
}

/**
 * Handle user update booking via POST (calls CRUD function)
 */
function ttn_handle_user_update_booking() {
    if (!is_user_logged_in()) {
        wp_die('You must be logged in to update a booking.');
    }

    if (!isset($_POST['booking_id']) || !check_admin_referer('ttn_update_user_booking_nonce')) {
        wp_die('Security check failed.');
    }

    $booking_id = intval($_POST['booking_id']);
    $user_email = wp_get_current_user()->user_email;
    
    $booking_data = array(
        'date' => sanitize_text_field(wp_unslash($_POST['date'] ?? '')),
        'time' => sanitize_text_field(wp_unslash($_POST['time'] ?? '')),
        'duration' => intval($_POST['duration'] ?? 1),
    );

    $result = ttn_crud_update_user_booking($booking_id, $user_email, $booking_data);
    
    // Store result in transient for display on redirect
    set_transient('ttn_user_booking_message_' . $user_email, $result, 30);
    
    wp_safe_redirect(home_url('/my-account/'));
    exit;
}
add_action('admin_post_ttn_update_user_booking', 'ttn_handle_user_update_booking');
add_action('admin_post_nopriv_ttn_update_user_booking', 'ttn_handle_user_update_booking');

/**
 * Handle user cancel booking via GET/POST (calls CRUD function)
 */
function ttn_handle_user_cancel_booking() {
    if (!is_user_logged_in()) {
        wp_die('You must be logged in to cancel a booking.');
    }

    if (!isset($_GET['ttn_cancel_booking_id']) || !check_admin_referer('ttn_cancel_booking_nonce')) {
        wp_die('Security check failed.');
    }

    $booking_id = intval($_GET['ttn_cancel_booking_id']);
    $user_email = wp_get_current_user()->user_email;

    $result = ttn_crud_cancel_user_booking($booking_id, $user_email);
    
    // Store result in transient for display on redirect
    set_transient('ttn_user_booking_message_' . $user_email, $result, 30);
    
    wp_safe_redirect(home_url('/my-account/'));
    exit;
}
add_action('admin_post_ttn_cancel_user_booking', 'ttn_handle_user_cancel_booking');
add_action('admin_post_nopriv_ttn_cancel_user_booking', 'ttn_handle_user_cancel_booking');

