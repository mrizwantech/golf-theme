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
                    <article class="hero-slide <?php echo $index === 0 ? 'active' : ''; ?>" <?php if ($index === 0) : ?>style="background-image: url('<?php echo esc_url($slide['image']); ?>');"<?php else : ?>data-background-image="<?php echo esc_url($slide['image']); ?>"<?php endif; ?>>
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
            $feature_4_media = get_theme_mod('golf_simulator_feature_4_media', '');
            $feature_5_media = get_theme_mod('golf_simulator_feature_5_media', '');
            $feature_6_media = get_theme_mod('golf_simulator_feature_6_media', '');

            $feature_1_title = get_theme_mod('golf_simulator_feature_1_title', '') ?: 'Auto Tee';
            $feature_1_text = get_theme_mod('golf_simulator_feature_1_text', '') ?: 'The ball automatically tees up after every shot. Spend less time resetting and more time focused on your game, with a smooth and consistent tee-up experience from shot to shot.';

            $feature_2_title = get_theme_mod('golf_simulator_feature_2_title', '') ?: 'Dynamic Swing Plate';
            $feature_2_text = get_theme_mod('golf_simulator_feature_2_text', '') ?: 'Experience a more realistic golf swing with our dynamic swing plate. The platform moves with the terrain and shot conditions, simulating uneven lies such as uphill, downhill, and sidehill shots. Adjust your stance naturally and experience a more challenging, true-to-life round of golf.';

            $feature_3_title = get_theme_mod('golf_simulator_feature_3_title', '') ?: 'High-Speed Swing Sensors';
            $feature_3_text = get_theme_mod('golf_simulator_feature_3_text', '') ?: 'Advanced high-speed sensors capture every shot with precision, tracking key ball and club data in real time. Get fast, accurate feedback on your swing, ball flight, speed, launch, and shot performance to help you understand and improve your game.';

            $feature_4_title = get_theme_mod('golf_simulator_feature_4_title', '') ?: 'Realistic Bunker Play';
            $feature_4_text = get_theme_mod('golf_simulator_feature_4_text', '') ?: 'Take your short game to the next level with realistic bunker conditions that recreate the feel of playing from the sand. Experience authentic shot response, changing ball flight, and the challenge of getting up and down.';

            $feature_5_title = get_theme_mod('golf_simulator_feature_5_title', '') ?: 'Precision Putting';
            $feature_5_text = get_theme_mod('golf_simulator_feature_5_text', '') ?: 'Dial in your putting with realistic green surfaces designed to replicate the feel of the course. Read the break, control your speed, and build confidence on every putt with accurate roll and natural ball response.';

            $feature_6_title = get_theme_mod('golf_simulator_feature_6_title', '') ?: 'Network Play';
            $feature_6_text = get_theme_mod('golf_simulator_feature_6_text', '') ?: 'Play together, compete, and enjoy a connected golf experience with friends and other players. Join the same round, track scores in real time, and experience the excitement of head-to-head competition across connected simulator bays.';

            $render_card_media = static function ($media_url, $alt_text) {
                if (empty($media_url)) {
                    return '';
                }
                $is_video = (bool) preg_match('/\.(mp4|webm|ogg)$/i', $media_url);
                $is_gif = (bool) preg_match('/\.gif(\?.*)?$/i', $media_url);
                $play_badge = ($is_video || $is_gif)
                    ? '<span class="card-media-badge" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span>'
                    : '';

                if ($is_video) {
                    return '<div class="card-media" data-media="video">' . $play_badge . '<video data-src="' . esc_url($media_url) . '" loop muted playsinline preload="none"></video></div>';
                }
                $img_class = $is_gif ? ' class="hover-gif"' : '';
                return '<div class="card-media" data-media="' . ($is_gif ? 'gif' : 'image') . '">' . $play_badge . '<img src="' . esc_url($media_url) . '" alt="' . esc_attr($alt_text) . '" loading="lazy"' . $img_class . ' /></div>';
            };
            ?>
            <div class="grid">
                <div class="card has-media" tabindex="0">
                    <?php echo $render_card_media($feature_1_media, $feature_1_title); ?>
                    <div class="card-body">
                        <div class="kicker"><?php echo esc_html($feature_1_title); ?></div>
                        <p><?php echo esc_html($feature_1_text); ?></p>
                    </div>
                </div>
                <div class="card has-media" tabindex="0">
                    <?php echo $render_card_media($feature_2_media, $feature_2_title); ?>
                    <div class="card-body">
                        <div class="kicker"><?php echo esc_html($feature_2_title); ?></div>
                        <p><?php echo esc_html($feature_2_text); ?></p>
                    </div>
                </div>
                <div class="card has-media" tabindex="0">
                    <?php echo $render_card_media($feature_3_media, $feature_3_title); ?>
                    <div class="card-body">
                        <div class="kicker"><?php echo esc_html($feature_3_title); ?></div>
                        <p><?php echo esc_html($feature_3_text); ?></p>
                    </div>
                </div>
                <div class="card has-media" tabindex="0">
                    <?php echo $render_card_media($feature_4_media, $feature_4_title); ?>
                    <div class="card-body">
                        <div class="kicker"><?php echo esc_html($feature_4_title); ?></div>
                        <p><?php echo esc_html($feature_4_text); ?></p>
                    </div>
                </div>
                <div class="card has-media" tabindex="0">
                    <?php echo $render_card_media($feature_5_media, $feature_5_title); ?>
                    <div class="card-body">
                        <div class="kicker"><?php echo esc_html($feature_5_title); ?></div>
                        <p><?php echo esc_html($feature_5_text); ?></p>
                    </div>
                </div>
                <div class="card has-media" tabindex="0">
                    <?php echo $render_card_media($feature_6_media, $feature_6_title); ?>
                    <div class="card-body">
                        <div class="kicker"><?php echo esc_html($feature_6_title); ?></div>
                        <p><?php echo esc_html($feature_6_text); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>
<?php endif; ?>
<?php get_footer(); ?>
