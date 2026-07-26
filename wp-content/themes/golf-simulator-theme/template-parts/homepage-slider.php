<?php
$slides = array(
    array(
        'image' => get_theme_mod('golf_simulator_slide_1_image', 'https://images.unsplash.com/photo-1535131749006-b7f58c99034b?auto=format&fit=crop&w=1600&q=80'),
        'kicker' => get_theme_mod('golf_simulator_slide_1_kicker', 'Tee Time Nexus • Far Nexes LLC'),
        'heading' => get_theme_mod('golf_simulator_slide_1_heading', 'Indoor golf that feels like your next championship round.'),
        'text' => get_theme_mod('golf_simulator_slide_1_text', 'Welcome to Tee Time Nexus, your modern golf simulator destination for practice, entertainment, leagues, and business events.'),
        'button_text' => get_theme_mod('golf_simulator_slide_1_button_1', 'Book Now'),
        'button_url' => get_theme_mod('golf_simulator_slide_1_button_1_url', home_url('/booking/')),
    ),
    array(
        'image' => get_theme_mod('golf_simulator_slide_2_image', 'https://images.unsplash.com/photo-1593111774278-0b6b02b7961c?auto=format&fit=crop&w=1600&q=80'),
        'kicker' => get_theme_mod('golf_simulator_slide_2_kicker', 'Practice. Play. Perform.'),
        'heading' => get_theme_mod('golf_simulator_slide_2_heading', 'Train smarter with high-performance simulator sessions.'),
        'text' => get_theme_mod('golf_simulator_slide_2_text', 'Use Tee Time Nexus for coaching, private play, and feature-packed bay rentals that keep every visit exciting.'),
        'button_text' => get_theme_mod('golf_simulator_slide_2_button_1', 'Book Now'),
        'button_url' => get_theme_mod('golf_simulator_slide_2_button_1_url', home_url('/booking/')),
    ),
    array(
        'image' => get_theme_mod('golf_simulator_slide_3_image', 'https://images.unsplash.com/photo-1517466787929-bc90951d0974?auto=format&fit=crop&w=1600&q=80'),
        'kicker' => get_theme_mod('golf_simulator_slide_3_kicker', 'Book Your Next Session'),
        'heading' => get_theme_mod('golf_simulator_slide_3_heading', 'Built for new customers, leagues, and premium events.'),
        'text' => get_theme_mod('golf_simulator_slide_3_text', 'Launch your local golf simulator business with a polished landing page that highlights fast bookings and simple pricing.'),
        'button_text' => get_theme_mod('golf_simulator_slide_3_button_1', 'Book Now'),
        'button_url' => get_theme_mod('golf_simulator_slide_3_button_1_url', home_url('/booking/')),
    ),
);
?>
<section class="hero-slider">
    <button class="slider-arrow slider-prev" type="button" aria-label="Previous slide">&#10094;</button>
    <div class="slider-track">
        <?php foreach ($slides as $index => $slide) : ?>
            <article class="hero-slide <?php echo $index === 0 ? 'active' : ''; ?>" style="background-image: linear-gradient(rgba(16, 35, 26, 0.58), rgba(16, 35, 26, 0.7)), url('<?php echo esc_url($slide['image']); ?>');">
                <div class="container hero-copy">
                    <span class="kicker"><?php echo esc_html($slide['kicker']); ?></span>
                    <h1><?php echo esc_html($slide['heading']); ?></h1>
                    <p><?php echo esc_html($slide['text']); ?></p>
                    <div class="hero-actions">
                        <a class="btn btn-primary" href="<?php echo esc_url($slide['button_url']); ?>"><?php echo esc_html($slide['button_text']); ?></a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <button class="slider-arrow slider-next" type="button" aria-label="Next slide">&#10095;</button>
    <div class="slider-dots" aria-label="Slider navigation"></div>
</section>
