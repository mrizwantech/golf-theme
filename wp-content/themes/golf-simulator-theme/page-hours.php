<?php
/**
 * Template Name: Hours & Access
 */
get_header();
?>
<main class="hours-page">
    <section class="hours-hero container">
        <span class="about-eyebrow">Plan your visit</span>
        <h1>Hours &amp; Access</h1>
        <p>Play at a time that works for you. Non-member hours vary by day, while members enjoy secure access around the clock.</p>
    </section>

    <section class="hours-schedule-section">
        <div class="container">
            <div class="hours-schedule-grid">
                <article class="hours-schedule">
                    <span class="hours-label">Monday - Friday</span>
                    <h2>Weekday Hours</h2>
                    <p class="hours-time">10:00 AM <span>to</span> 9:00 PM</p>
                    <p class="hours-note">Non-member bookings are available during these hours.</p>
                </article>
                <article class="hours-schedule">
                    <span class="hours-label">Saturday - Sunday</span>
                    <h2>Weekend Hours</h2>
                    <p class="hours-time">9:00 AM <span>to</span> 10:00 PM</p>
                    <p class="hours-note">Non-member bookings are available during these hours.</p>
                </article>
            </div>

            <aside class="hours-member-access">
                <div class="hours-member-mark" aria-hidden="true">24/7</div>
                <div>
                    <span class="about-eyebrow">For members</span>
                    <h2>Access the game on your schedule.</h2>
                    <p>Active members have secure facility access 24 hours a day, 7 days a week. Mobile access is managed through Kisi.</p>
                </div>
                <a class="btn btn-primary" href="<?php echo esc_url(home_url('/membership/')); ?>">Explore Memberships <span aria-hidden="true">&#8594;</span></a>
            </aside>
        </div>
    </section>

    <section class="hours-booking-note container">
        <p>Bay availability depends on your selected date and time. <a href="<?php echo esc_url(home_url('/book-a-bay/')); ?>">Check availability and book a bay <span aria-hidden="true">&#8594;</span></a></p>
    </section>
</main>
<?php get_footer(); ?>