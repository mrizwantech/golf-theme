<?php
/**
 * Template Name: Contact Tee Time Nexus
 */
get_header();
$contact_status = isset($_GET['contact']) ? sanitize_key(wp_unslash($_GET['contact'])) : '';
?>
<main class="contact-page container">
    <header class="contact-page-heading">
        <span class="about-eyebrow">We’re here to help</span>
        <h1>Contact Tee Time Nexus</h1>
        <p>Questions about booking, memberships, or visiting us? Send us a message or reach out directly.</p>
    </header>

    <div class="contact-layout">
        <section class="contact-details" aria-label="Contact details">
            <div class="contact-detail">
                <span class="contact-detail-label">Visit</span>
                <a href="https://maps.app.goo.gl/Fu5JUodn9BqbYo7A8" target="_blank" rel="noopener noreferrer">2785 Charlotte Hwy, Suites 11 &amp; 12<br>Mooresville, NC 28117 <span aria-hidden="true">&#8599;</span></a>
            </div>
            <div class="contact-detail">
                <span class="contact-detail-label">Email</span>
                <a href="mailto:sales@teetimenexus.com">sales@teetimenexus.com</a>
            </div>
            <div class="contact-detail">
                <span class="contact-detail-label">Phone</span>
                <a href="tel:+19805033288">+1 (980) 503-3288</a>
            </div>
            <div class="contact-detail">
                <span class="contact-detail-label">Hours</span>
                <p>Weekdays: 10:00 AM–9:00 PM<br>Weekends: 9:00 AM–10:00 PM<br>Members: secure 24/7 access</p>
            </div>
        </section>

        <section class="contact-form-panel" aria-labelledby="contact-form-heading">
            <h2 id="contact-form-heading">Send us a message</h2>
            <?php if ($contact_status === 'sent') : ?>
                <div class="contact-notice contact-notice-success" role="status">Thanks for reaching out. Your message has been sent.</div>
            <?php elseif ($contact_status === 'error') : ?>
                <div class="contact-notice contact-notice-error" role="alert">We couldn’t send your message. Check the required fields and try again.</div>
            <?php elseif ($contact_status === 'send-error') : ?>
                <div class="contact-notice contact-notice-error" role="alert">Your message could not be delivered right now. Please email sales@teetimenexus.com or call +1 (980) 503-3288.</div>
            <?php endif; ?>

            <form class="contact-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="golf_simulator_contact_submit">
                <?php wp_nonce_field('golf_simulator_contact_submit', 'contact_nonce'); ?>
                <div class="contact-honeypot" aria-hidden="true">
                    <label for="contact-website">Leave this field empty</label>
                    <input type="text" id="contact-website" name="website" tabindex="-1" autocomplete="off">
                </div>
                <div class="contact-form-row">
                    <label>Name
                        <input type="text" name="name" autocomplete="name" maxlength="120" required>
                    </label>
                    <label>Email
                        <input type="email" name="email" autocomplete="email" maxlength="254" required>
                    </label>
                </div>
                <label>Phone <span>(optional)</span>
                    <input type="tel" name="phone" autocomplete="tel" maxlength="40">
                </label>
                <label>Message
                    <textarea name="message" rows="6" maxlength="5000" required></textarea>
                </label>
                <button class="btn btn-primary" type="submit">Send Message <span aria-hidden="true">&#8594;</span></button>
            </form>
        </section>
    </div>
</main>
<?php get_footer(); ?>