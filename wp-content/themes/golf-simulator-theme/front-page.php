<?php get_header(); ?>
<main>
    <?php
    $slides = array(
        array(
            'image' => get_theme_mod('golf_simulator_slide_1_image', 'https://images.unsplash.com/photo-1535131749006-b7f58c99034b?auto=format&fit=crop&w=1600&q=80'),
            'kicker' => get_theme_mod('golf_simulator_slide_1_kicker', 'Tee Time Nexus • Far Nexes LLC'),
            'heading' => get_theme_mod('golf_simulator_slide_1_heading', 'Indoor golf that feels like your next championship round.'),
            'text' => get_theme_mod('golf_simulator_slide_1_text', 'Welcome to Tee Time Nexus, your modern golf simulator destination for practice, entertainment, leagues, and business events.'),
            'button_text' => get_theme_mod('golf_simulator_slide_1_button_1', 'Book Now'),
            'button_url' => get_theme_mod('golf_simulator_slide_1_button_1_url', home_url('/book-a-bay/')),
        ),
        array(
            'image' => get_theme_mod('golf_simulator_slide_2_image', 'https://images.unsplash.com/photo-1593111774278-0b6b02b7961c?auto=format&fit=crop&w=1600&q=80'),
            'kicker' => get_theme_mod('golf_simulator_slide_2_kicker', 'Practice. Play. Perform.'),
            'heading' => get_theme_mod('golf_simulator_slide_2_heading', 'Train smarter with high-performance simulator sessions.'),
            'text' => get_theme_mod('golf_simulator_slide_2_text', 'Use Tee Time Nexus for coaching, private play, and feature-packed bay rentals that keep every visit exciting.'),
            'button_text' => get_theme_mod('golf_simulator_slide_2_button_1', 'Book Now'),
            'button_url' => get_theme_mod('golf_simulator_slide_2_button_1_url', home_url('/book-a-bay/')),
        ),
        array(
            'image' => get_theme_mod('golf_simulator_slide_3_image', 'https://images.unsplash.com/photo-1517466787929-bc90951d0974?auto=format&fit=crop&w=1600&q=80'),
            'kicker' => get_theme_mod('golf_simulator_slide_3_kicker', 'Book Your Next Session'),
            'heading' => get_theme_mod('golf_simulator_slide_3_heading', 'Built for new customers, leagues, and premium events.'),
            'text' => get_theme_mod('golf_simulator_slide_3_text', 'Launch your local golf simulator business with a polished landing page that highlights fast bookings and simple pricing.'),
            'button_text' => get_theme_mod('golf_simulator_slide_3_button_1', 'Book Now'),
            'button_url' => get_theme_mod('golf_simulator_slide_3_button_1_url', home_url('/book-a-bay/')),
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

    <section class="section" id="services">
        <div class="container">
            <h2 class="section-title">Why golfers choose us</h2>
            <div class="grid">
                <div class="card">
                    <div class="kicker">Precision</div>
                    <h3>Professional simulator setup</h3>
                    <p>High-speed launch tracking and immersive course play make every session feel like the real thing.</p>
                </div>
                <div class="card">
                    <div class="kicker">Events</div>
                    <h3>Private leagues & parties</h3>
                    <p>Perfect for corporate nights, birthdays, and friendly competitions with a premium atmosphere.</p>
                </div>
                <div class="card">
                    <div class="kicker">Growth</div>
                    <h3>Marketing-ready brand image</h3>
                    <p>Built to attract local customers and present your business professionally online.</p>
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
                    <div class="price">$50</div>
                    <p>Hourly bay rental for golf simulator sessions, practice, and private play.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="contact">
        <div class="container">
            <div class="card">
                <div class="kicker">Book now</div>
                <h2 class="section-title">Let’s grow Tee Time Nexus with your next customer</h2>
                <p>Use this section for your booking form, phone number, email, or schedule link. This is a strong homepage area for a new golf simulator business.</p>
                <p><strong>Business:</strong> Tee Time Nexus<br><strong>Legal Entity:</strong> Far Nexes LLC<br><strong>Phone:</strong> (555) 123-4567<br><strong>Email:</strong> hello@teetimenexus.com</p>
            </div>
        </div>
    </section>
</main>
<?php get_footer(); ?>
