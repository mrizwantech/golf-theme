<?php
/**
 * Template Name: About Tee Time Nexus
 */
get_header();
?>
<main class="about-page">
    <section class="about-hero about-hero-text-only container">
        <div class="about-hero-copy">
            <span class="about-eyebrow">Locally owned in Mooresville, NC</span>
            <h1>The next level of indoor golf.</h1>
            <p>Welcome to <strong>Tee Time Nexus</strong>, a modern indoor golf destination in Mooresville, North Carolina, built for golfers who want more from their time on the course.</p>
            <p>We created Tee Time Nexus with a simple idea: <strong>bring the feeling, challenge, and excitement of real golf indoors, with the flexibility to play on your schedule.</strong></p>
            <p>Whether you're looking to improve your swing, play a competitive round, introduce someone to golf, or enjoy time with friends and family, Tee Time Nexus gives you a place to play year-round.</p>
            <div class="about-actions">
                <a class="btn btn-primary" href="<?php echo esc_url(home_url('/book-a-bay/')); ?>">Book a Bay</a>
                <a class="about-text-link" href="<?php echo esc_url(home_url('/membership/')); ?>">Explore memberships <span aria-hidden="true">&#8594;</span></a>
                <a class="about-text-link" href="<?php echo esc_url(home_url('/golf-technology/')); ?>">Explore golf technology <span aria-hidden="true">&#8594;</span></a>
            </div>
            <div class="about-local-note"><span aria-hidden="true">&#9679;</span> Your game. Your time. Your Tee Time Nexus.</div>
        </div>
    </section>

    <section class="about-visit-band">
        <div class="container about-visit-inner">
            <div>
                <span class="about-eyebrow">Our mission</span>
                <h2>Make indoor golf more realistic, accessible, and enjoyable.</h2>
                <p>We want Tee Time Nexus to be a place where golfers can practice, play, compete, and spend time together whenever it works for them. From your first swing to your next personal best, we&rsquo;re here to make every visit count.</p>
                <p class="about-tagline">Premium indoor golf. Advanced technology. 24/7 access.</p>
                <address class="about-contact-details">
                    <a href="https://maps.app.goo.gl/Fu5JUodn9BqbYo7A8" target="_blank" rel="noopener noreferrer">2785 Charlotte Hwy, Suites 11 &amp; 12<br>Mooresville, NC 28117 <span aria-hidden="true">&#8599;</span></a>
                    <a href="tel:+19805033288">+1 (980) 503-3288</a>
                </address>
            </div>
            <a class="btn btn-primary" href="<?php echo esc_url(home_url('/book-a-bay/')); ?>">Plan Your Visit <span aria-hidden="true">&#8594;</span></a>
        </div>
    </section>

    <section class="about-intro-band about-access-band">
        <div class="container about-intro-inner about-access-inner">
            <span class="about-eyebrow">Golf on your schedule</span>
            <h2>24/7 member access.</h2>
            <p>We believe your golf time should fit your schedule, not the other way around. Members can practice before work, play a late-night round, or get in a few swings on the weekend with around-the-clock facility access.</p>
            <p>Active members have secure facility access 24 hours a day, 7 days a week through the Tee Time Nexus mobile app.</p>
            <p class="about-tagline">Your schedule. Your access. Your game.</p>
        </div>
    </section>

    <section class="about-technology about-more container">
        <div class="about-section-heading">
            <div>
                <span class="about-eyebrow">More than a simulator</span>
                <h2>A place to practice, play, compete, and connect.</h2>
            </div>
            <p>You don’t need to be a scratch golfer. Come learn the basics, work on your short game, play 18 holes, or make it a night out with friends and family.</p>
        </div>
        <div class="about-play-grid">
            <article><h3>Practice</h3><p>Work on your swing and review detailed shot data.</p></article>
            <article><h3>Play</h3><p>Take on recognizable courses without leaving Mooresville.</p></article>
            <article><h3>Compete</h3><p>Challenge friends, family, and other golfers.</p></article>
            <article><h3>Connect</h3><p>Enjoy golf together, whatever the weather or season.</p></article>
            <article><h3>Improve</h3><p>Use feedback to understand your game and work on your goals.</p></article>
        </div>
    </section>

</main>
<?php get_footer(); ?>