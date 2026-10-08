<?php
/**
 * Template Name: Hours & Access
 */
get_header();
?>
<main class="hours-page">
    <section class="hours-hero container">
        <h1>Hours &amp; Access</h1>
    </section>

    <section class="hours-schedule-section">
        <div class="container">
            <div class="hours-schedule-grid">
                <article class="hours-schedule hours-member-schedule">
                    <h2 class="hours-schedule-title">Member Access <span class="hours-member-access-time">24/7</span></h2>
                    <p class="hours-note">Active members have secure facility access 24 hours a day, 7 days a week through the Tee Time Nexus mobile app.</p>
                    <a class="btn btn-primary" href="<?php echo esc_url(home_url('/membership/')); ?>">Explore Memberships <span aria-hidden="true">&#8594;</span></a>
                </article>
                <article class="hours-schedule hours-public-schedule">
                    <h2 class="hours-schedule-title">Public Hours</h2>
                    <p class="hours-time">10:00 AM <span>to</span> 10:00 PM</p>
                    <p class="hours-note">Open 7 days a week for public bookings.</p>
                    <a class="btn btn-primary" href="<?php echo esc_url(home_url('/book-a-bay/')); ?>">Book Public Hours <span aria-hidden="true">&#8594;</span></a>
                </article>
            </div>
        </div>
    </section>
</main>
<?php get_footer(); ?>