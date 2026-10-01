<?php get_header(); ?>
<?php if (is_user_logged_in()) : ?>
    <?php
    $current_user = wp_get_current_user();
    $first_name = get_user_meta($current_user->ID, 'first_name', true);
    $first_name = $first_name ?: $current_user->display_name;
    $first_name = trim(explode(' ', $first_name)[0]);
    $bookings = function_exists('ttn_get_user_bookings') ? ttn_get_user_bookings($current_user->user_email) : array();
    $upcoming_bookings = array();

    foreach ($bookings as $booking) {
        if (($booking['status'] ?? '') === 'cancelled' || empty($booking['date']) || empty($booking['time'])) {
            continue;
        }
        try {
            $booking_start = new DateTimeImmutable($booking['date'] . ' ' . $booking['time'], wp_timezone());
            if ($booking_start->getTimestamp() >= time()) {
                $booking['_start_timestamp'] = $booking_start->getTimestamp();
                $upcoming_bookings[] = $booking;
            }
        } catch (Exception $exception) {
            continue;
        }
    }
    usort($upcoming_bookings, static function ($first, $second) {
        return $first['_start_timestamp'] <=> $second['_start_timestamp'];
    });
    $next_booking = $upcoming_bookings[0] ?? null;
    $membership = function_exists('golf_simulator_theme_get_user_membership_record')
        ? golf_simulator_theme_get_user_membership_record($current_user->ID)
        : null;
    $hour = (int) current_time('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $member_home_image = get_theme_mod('golf_simulator_slide_1_image', 'https://images.unsplash.com/photo-1535131749006-b7f58c99034b?auto=format&fit=crop&w=1800&q=85');
    ?>
    <main class="member-home">
        <div class="container member-home-inner">
            <section class="member-home-welcome" style="background-image: linear-gradient(90deg, rgba(3, 12, 12, .94) 0%, rgba(3, 12, 12, .76) 48%, rgba(3, 12, 12, .2) 100%), url('<?php echo esc_url($member_home_image); ?>');">
                <span class="member-home-kicker">TEE TIME NEXUS · MEMBER HOME</span>
                <h1><?php echo esc_html($greeting . ', ' . $first_name); ?></h1>
                <p>Your next round starts here. Reserve a bay, manage your bookings, and check your membership.</p>
                <a class="btn btn-primary member-home-book" href="<?php echo esc_url(home_url('/book-a-bay/')); ?>">RESERVE A BAY <span aria-hidden="true">&#8594;</span></a>
            </section>

            <div class="member-home-card-grid">
                <section class="member-home-panel" aria-labelledby="upcoming-reservation-heading">
                    <div class="member-home-panel-heading">
                        <h2 id="upcoming-reservation-heading">Upcoming Reservation</h2>
                        <a href="<?php echo esc_url(home_url('/my-bookings/')); ?>">View all</a>
                    </div>
                    <?php if ($next_booking) : ?>
                        <div class="member-home-reservation">
                            <span class="member-home-reservation-icon" aria-hidden="true">&#9678;</span>
                            <div>
                                <strong><?php echo esc_html($next_booking['bay']); ?> Bay</strong>
                                <p><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($next_booking['date']))); ?> · <?php echo esc_html($next_booking['time']); ?></p>
                                <p><?php echo esc_html($next_booking['players']); ?> players · <?php echo esc_html($next_booking['duration']); ?> hour<?php echo (int) $next_booking['duration'] === 1 ? '' : 's'; ?></p>
                            </div>
                            <span class="member-home-reference"><?php echo esc_html($next_booking['booking_reference']); ?></span>
                        </div>
                    <?php else : ?>
                        <p class="member-home-empty">No upcoming reservations. Your next round starts here.</p>
                    <?php endif; ?>
                </section>

                <section class="member-home-panel member-home-membership" aria-labelledby="membership-status-heading">
                    <span class="member-home-membership-icon" aria-hidden="true">&#10022;</span>
                    <div class="member-home-membership-copy">
                        <h2 id="membership-status-heading"><?php echo $membership ? esc_html($membership->package_name) : 'Founding Member'; ?></h2>
                        <p><?php echo $membership ? esc_html(ucfirst($membership->status) . ' membership') : 'Your membership is ready when you are.'; ?></p>
                    </div>
                    <a href="<?php echo esc_url(home_url('/membership/')); ?>">View membership</a>
                </section>
            </div>
        </div>
    </main>
<?php else : ?>
<main>
    <?php
    $slides = array(
        array(
            'image' => get_theme_mod('golf_simulator_slide_1_image', 'https://images.unsplash.com/photo-1535131749006-b7f58c99034b?auto=format&fit=crop&w=1600&q=80'),
            'kicker' => get_theme_mod('golf_simulator_slide_1_kicker', 'Coming Soon'),
            'heading' => get_theme_mod('golf_simulator_slide_1_heading', 'Grand Opening TBD'),
            'text' => get_theme_mod('golf_simulator_slide_1_text', 'We are preparing something special for golfers in the area. Check back soon for updates, launch dates, and opening details.'),
            'button_text' => get_theme_mod('golf_simulator_slide_1_button_1', 'Stay Tuned'),
            'button_url' => get_theme_mod('golf_simulator_slide_1_button_1_url', home_url('/welcome')),
        ),
        array(
            'image' => get_theme_mod('golf_simulator_slide_2_image', 'https://images.unsplash.com/photo-1593111774278-0b6b02b7961c?auto=format&fit=crop&w=1600&q=80'),
            'kicker' => get_theme_mod('golf_simulator_slide_2_kicker', 'Opening Soon'),
            'heading' => get_theme_mod('golf_simulator_slide_2_heading', 'A premium golf simulator experience is on the way.'),
            'text' => get_theme_mod('golf_simulator_slide_2_text', 'Follow our launch updates for the grand opening, bay availability, and special early access announcements.'),
            'button_text' => get_theme_mod('golf_simulator_slide_2_button_1', 'Follow Updates'),
            'button_url' => get_theme_mod('golf_simulator_slide_2_button_1_url', home_url('/')),
        ),
        array(
            'image' => get_theme_mod('golf_simulator_slide_3_image', 'https://images.unsplash.com/photo-1517466787929-bc90951d0974?auto=format&fit=crop&w=1600&q=80'),
            'kicker' => get_theme_mod('golf_simulator_slide_3_kicker', 'Grand Opening'),
            'heading' => get_theme_mod('golf_simulator_slide_3_heading', 'TBD — we will announce the launch date soon.'),
            'text' => get_theme_mod('golf_simulator_slide_3_text', 'Stay connected for the official opening announcement, booking launch, and member access details.'),
            'button_text' => get_theme_mod('golf_simulator_slide_3_button_1', 'Watch for Launch'),
            'button_url' => get_theme_mod('golf_simulator_slide_3_button_1_url', home_url('/')),
        ),
    );
    ?>
    <section class="home-booking-layout container">
        <div class="hero-slider">
            <button class="slider-arrow slider-prev" type="button" aria-label="Previous slide">&#10094;</button>
            <div class="slider-track">
                <?php foreach ($slides as $index => $slide) : ?>
                    <article class="hero-slide <?php echo $index === 0 ? 'active' : ''; ?>" style="background-image: url('<?php echo esc_url($slide['image']); ?>');">
                        <div class="hero-copy">
                            <span class="kicker"><?php echo esc_html($slide['kicker']); ?></span>
                            <h1><?php echo esc_html($slide['heading']); ?></h1>
                            <p><?php echo esc_html($slide['text']); ?></p>
                            <div class="hero-actions hero-actions-lg">
                                <a class="btn btn-primary btn-hero" href="<?php echo esc_url(home_url('/membership/')); ?>">Become a Member</a>
                                <a class="btn btn-secondary btn-hero" href="<?php echo esc_url(home_url('/book-a-bay/')); ?>">Book a Bay</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <button class="slider-arrow slider-next" type="button" aria-label="Next slide">&#10095;</button>
            <div class="slider-dots" aria-label="Slider navigation"></div>
        </div>
    </section>

    <section class="section" id="services">
        <div class="container">
            <h2 class="section-title">WHERE GOLF MEETS TECHNOLOGY</h2>
            <h3 class="section-subtitle">Experience the ultimate fusion of cutting-edge golf technology and immersive gameplay.</h3>
            <?php
            $feature_1_media = get_theme_mod('golf_simulator_feature_1_media', '');
            $feature_2_media = get_theme_mod('golf_simulator_feature_2_media', '');
            $feature_3_media = get_theme_mod('golf_simulator_feature_3_media', '');

            $render_card_media = static function ($media_url, $alt_text) {
                if (empty($media_url)) {
                    return '';
                }
                $is_video = (bool) preg_match('/\.(mp4|webm|ogg)$/i', $media_url);
                if ($is_video) {
                    return '<div class="card-media"><video src="' . esc_url($media_url) . '" autoplay loop muted playsinline></video></div>';
                }
                return '<div class="card-media"><img src="' . esc_url($media_url) . '" alt="' . esc_attr($alt_text) . '" loading="lazy" /></div>';
            };
            ?>
            <div class="grid">
                <div class="card has-media">
                    <?php echo $render_card_media($feature_1_media, 'Auto Tee Simulator Setup'); ?>
                    <div class="card-body">
                        <div class="kicker">Auto Tee</div>
                       
                        <p>The ball automatically tees up after every shot. Spend less time resetting and more time focused on your game, with a smooth and consistent tee-up experience from shot to shot.
</p>
                    </div>
                </div>
                <div class="card has-media">
                    <?php echo $render_card_media($feature_2_media, 'Private Leagues & Events'); ?>
                    <div class="card-body">
                        <div class="kicker">Dynamic Swing Plate</div>
                        
                        <p>Experience a more realistic golf swing with our dynamic swing plate. The platform moves with the terrain and shot conditions, simulating uneven lies such as uphill, downhill, and sidehill shots. Adjust your stance naturally and experience a more challenging, true-to-life round of golf.</p>
                    </div>
                </div>
                <div class="card has-media">
                    <?php echo $render_card_media($feature_3_media, 'Technology & Growth'); ?>
                    <div class="card-body">
                        <div class="kicker">High-Speed Swing Sensors</div>
                       
                        <p>Advanced high-speed sensors capture every shot with precision, tracking key ball and club data in real time. Get fast, accurate feedback on your swing, ball flight, speed, launch, and shot performance to help you understand and improve your game.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="packages">
        <div class="container">
            <h2 class="section-title">Pricing</h2>
            <div class="grid">
                <div class="card">
                    <div class="kicker">Per Hour / Per Bay</div>
                    <?php
                    $front_hourly_price = function_exists('ttn_booking_get_hourly_price') ? ttn_booking_get_hourly_price() : (float) get_option('ttn_standard_hourly_price', 50);
                    $formatted_front_price = (floor($front_hourly_price) == $front_hourly_price) ? number_format($front_hourly_price, 0) : number_format($front_hourly_price, 2);
                    ?>
                    <div class="price">$<?php echo esc_html($formatted_front_price); ?></div>
                    <p>Hourly bay rental for golf simulator sessions, practice, and private play.</p>
                </div>
            </div>
        </div>
    </section>

   
</main>
<?php endif; ?>
<?php get_footer(); ?>
