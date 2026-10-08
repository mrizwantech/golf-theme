<?php
/**
 * Template Name: Golf Technology
 */
get_header();
$technology_sections = golf_simulator_theme_golf_technology_sections();
?>
<main class="about-page golf-technology-page">
    <section class="about-hero container">
        <div class="about-hero-copy">
            <h1>Practice Smarter. Play Better.</h1>
            <p>Advanced practice tools, realistic gameplay, and detailed performance data help you understand your game, sharpen every shot, and make every practice session count.</p>
            <div class="about-actions">
                <a class="btn btn-primary" href="<?php echo esc_url(home_url('/book-a-bay/')); ?>">Book a Bay</a>
            </div>
        </div>
        <div class="about-demo">
            <iframe src="https://www.youtube-nocookie.com/embed/fuEV8m1Bdb4?enablejsapi=1" title="GOLFZON TwoVision NX simulator demo" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
        </div>
    </section>

    <section class="about-technology container" aria-labelledby="golf-technology-heading">
        <div class="about-section-heading">
            <div>
                <h2 id="golf-technology-heading">Explore the TwoVision NX experience</h2>
            </div>
        </div>
        <div class="about-feature-list golf-tech-grid">
            <?php foreach ($technology_sections as $feature_id => $feature) : ?>
                <article class="about-feature<?php echo !empty($feature['featured']) ? ' about-feature-highlight' : ''; ?>" id="<?php echo esc_attr($feature_id); ?>" tabindex="-1">
                    <div>
                        <?php foreach ($feature['aliases'] ?? array() as $alias) : ?>
                            <span class="golf-tech-legacy-anchor" id="<?php echo esc_attr($alias); ?>" aria-hidden="true"></span>
                        <?php endforeach; ?>
                        <h3><?php echo esc_html($feature['label']); ?></h3>
                        <p><?php echo esc_html($feature['text']); ?></p>
                        <?php if (!empty($feature['details'])) : ?>
                            <?php if ($feature_id === 'technology-shot-analysis') : ?>
                                <div class="golf-technology-metrics">
                                    <?php foreach (array_chunk($feature['details'], 5) as $metric_column) : ?>
                                        <ul class="golf-technology-feature-details golf-technology-metric-column">
                                            <?php foreach ($metric_column as $detail) : ?>
                                                <li><?php echo esc_html($detail); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endforeach; ?>
                                </div>
                            <?php else : ?>
                                <ul class="golf-technology-feature-details">
                                    <?php foreach ($feature['details'] as $detail) : ?>
                                        <li><?php echo esc_html($detail); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php foreach ($feature['subsections'] ?? array() as $title => $text) : ?>
                            <div class="golf-tech-subsection">
                                <h4><?php echo esc_html($title); ?></h4>
                                <p><?php echo esc_html($text); ?></p>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!empty($feature['after'])) : ?>
                            <p class="golf-tech-followup"><?php echo esc_html($feature['after']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($feature['video_id']) && !empty($feature['video_title'])) : ?>
                            <div class="golf-tech-video">
                                <h4 class="golf-tech-video-heading">See It in Action</h4>
                                <p class="golf-tech-video-title"><?php echo esc_html($feature['video_title']); ?></p>
                                <div class="golf-tech-video-frame">
                                    <iframe src="<?php echo esc_url('https://www.youtube-nocookie.com/embed/' . rawurlencode($feature['video_id']) . '?enablejsapi=1'); ?>" title="<?php echo esc_attr($feature['video_title']); ?>" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="about-attribution">Simulator capabilities are based on GOLFZON TwoVision NX product information. Features and availability may vary depending on Tee Time Nexus facility configuration.</p>
    </section>

    <section class="about-visit-band">
        <div class="container about-visit-inner">
            <div>
                <span class="about-eyebrow">Play it for yourself</span>
                <h2>Step into your next round.</h2>
                <p>At Tee Time Nexus, we&rsquo;re creating more than a place to hit golf balls. We&rsquo;re bringing premium indoor golf technology into a social environment where beginners can discover the game, friends can compete, and players can keep improving in any weather.</p>
                <p class="about-tagline">Practice. Play. Compete. Improve. Powered by GOLFZON TwoVision NX.</p>
            </div>
            <a class="btn btn-primary" href="<?php echo esc_url(home_url('/book-a-bay/')); ?>">Book a Bay <span aria-hidden="true">&#8594;</span></a>
        </div>
    </section>
</main>
<script>
(function() {
    var frames = Array.prototype.slice.call(document.querySelectorAll('.golf-technology-page iframe[src*="youtube-nocookie.com/embed/"]'));
    var initialized = false;

    if (!frames.length) {
        return;
    }

    function initializePlayers() {
        if (initialized || !window.YT || !window.YT.Player) {
            return;
        }
        initialized = true;

        var players = [];
        frames.forEach(function(frame) {
            var player = new window.YT.Player(frame, {
                events: {
                    onStateChange: function(event) {
                        if (event.data !== window.YT.PlayerState.PLAYING) {
                            return;
                        }
                        players.forEach(function(otherPlayer) {
                            if (otherPlayer !== event.target && otherPlayer.getPlayerState() === window.YT.PlayerState.PLAYING) {
                                otherPlayer.pauseVideo();
                            }
                        });
                    }
                }
            });
            players.push(player);
        });
    }

    if (window.YT && window.YT.Player) {
        initializePlayers();
        return;
    }

    var previousReadyCallback = window.onYouTubeIframeAPIReady;
    window.onYouTubeIframeAPIReady = function() {
        if (typeof previousReadyCallback === 'function') {
            previousReadyCallback();
        }
        initializePlayers();
    };

    if (!document.querySelector('script[src="https://www.youtube.com/iframe_api"]')) {
        var apiScript = document.createElement('script');
        apiScript.src = 'https://www.youtube.com/iframe_api';
        document.head.appendChild(apiScript);
    }
})();
</script>
<?php get_footer(); ?>
