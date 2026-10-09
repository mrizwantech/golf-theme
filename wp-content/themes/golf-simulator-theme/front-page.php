<?php get_header(); ?>
<?php if (is_user_logged_in()) : ?>
    <?php
    $current_user = wp_get_current_user();
    $first_name = get_user_meta($current_user->ID, 'first_name', true);
    $first_name = $first_name ?: $current_user->display_name;
    $first_name = trim(explode(' ', $first_name)[0]);
    $bookings = function_exists('ttn_get_user_bookings') ? ttn_get_user_bookings($current_user->user_email, $current_user->ID) : array();
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
                        <h2 id="membership-status-heading"><?php echo $membership ? esc_html(golf_simulator_theme_get_membership_package_display_name($membership->package_name)) : 'Founding Member'; ?></h2>
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
    $home_content = golf_simulator_theme_get_home_content();
    $slides = $home_content['slides'];
    ?>
    <section class="home-booking-layout container">
        <div class="hero-slider">
            <button class="slider-arrow slider-prev" type="button" aria-label="Previous slide">&#10094;</button>
            <div class="slider-track">
                <?php foreach ($slides as $index => $slide) : ?>
                    <article class="hero-slide <?php echo $index === 0 ? 'active' : ''; ?>" <?php if ($index === 0) : ?>style="background-image: url('<?php echo esc_url($slide['image']); ?>');"<?php else : ?>data-background-image="<?php echo esc_url($slide['image']); ?>"<?php endif; ?>>
                        <div class="hero-copy">
                            <h1 class="kicker"><?php echo esc_html($slide['kicker']); ?></h1>
                            <h3><?php echo esc_html($slide['heading']); ?></h3>
                            <p><?php echo esc_html($slide['text']); ?></p>
                            <div class="hero-actions hero-actions-lg">
                                <?php foreach ($slide['actions'] as $action_index => $action) : ?>
                                    <a class="btn <?php echo $action_index === 0 ? 'btn-primary' : 'btn-secondary'; ?> btn-hero" href="<?php echo esc_url($action['url']); ?>"><?php echo esc_html($action['label']); ?></a>
                                <?php endforeach; ?>
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
            <h2 class="section-title"><?php echo esc_html($home_content['section']['title']); ?></h2>
            <h3 class="section-subtitle"><?php echo esc_html($home_content['section']['subtitle']); ?></h3>
            <?php
            $render_card_media = static function ($media_url, $alt_text, $media_type) {
                if (empty($media_url)) {
                    return '';
                }
                $is_video = $media_type === 'video';
                $is_gif = $media_type === 'gif';
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
                <?php foreach ($home_content['panels'] as $panel) : ?>
                <div class="card has-media" tabindex="0">
                    <?php echo $render_card_media($panel['media'], $panel['title'], $panel['media_type']); ?>
                    <div class="card-body">
                        <div class="kicker"><?php echo esc_html($panel['title']); ?></div>
                        <p><?php echo esc_html($panel['text']); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

</main>
<?php endif; ?>
<?php get_footer(); ?>
