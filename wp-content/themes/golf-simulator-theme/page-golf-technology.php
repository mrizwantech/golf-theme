<?php
/**
 * Template Name: Golf Technology
 */
get_header();
?>
<main class="about-page golf-technology-page">
    <section class="about-hero container">
        <div class="about-hero-copy">
            <span class="about-eyebrow">Powered by GOLFZON TwoVision NX</span>
            <h1>Experience Golf Like Never Before</h1>
            <p>At Tee Time Nexus, we’re bringing one of the world’s most advanced indoor golf experiences to Mooresville, North Carolina. Our simulator bays are powered by GOLFZON TwoVision NX, combining advanced sensing technology, realistic course graphics, moving playing surfaces, detailed performance analysis, and immersive gameplay.</p>
            <p>Whether you’re practicing your swing, playing a quick nine, competing with friends, or experiencing famous courses from around the world, TwoVision NX is designed to make every shot feel closer to real golf.</p>
            <div class="about-actions">
                <a class="btn btn-primary" href="<?php echo esc_url(home_url('/book-a-bay/')); ?>">Book a Bay</a>
            </div>
        </div>
        <a class="about-demo" href="https://www.youtube.com/watch?v=fuEV8m1Bdb4" target="_blank" rel="noopener noreferrer" aria-label="Watch the GOLFZON TwoVision NX demo on YouTube">
            <img src="https://i.ytimg.com/vi/fuEV8m1Bdb4/hqdefault.jpg" alt="Preview of the GOLFZON TwoVision NX simulator demo" loading="lazy">
            <span class="about-demo-play" aria-hidden="true">&#9654;</span>
            <span class="about-demo-caption">Watch the TwoVision NX demo <span aria-hidden="true">&#8599;</span></span>
        </a>
    </section>

    <section class="about-technology container">
        <div class="about-section-heading">
            <div>
                <span class="about-eyebrow">The technology</span>
                <h2>Practice Smarter. Play Better.</h2>
            </div>
            <p>Advanced practice tools and detailed performance data help you understand your game, sharpen every shot, and make every practice session count.</p>
        </div>

        <nav class="golf-tech-feature-tabs" aria-label="Golf technology features">
            <a href="#technology-moving-swing-plate">Motion Plate</a>
            <a href="#technology-auto-tee">Auto-Tee</a>
            <a href="#technology-mapped-courses">350+ Golf Courses</a>
            <a href="#technology-shot-analysis">Shot Analysis</a>
            <a href="#technology-shot-tracking">Advanced Shot Tracking</a>
            <a href="#technology-mobile-app">Mobile App</a>
            <a href="#technology-club-data">Club Data</a>
            <a href="#technology-precision-putting">Precision Putting</a>
            <a href="#technology-driving-range">Driving Range</a>
            <a href="#technology-approach-practice">Approach Practice</a>
            <a href="#technology-pitch-chip">Pitch &amp; Chip</a>
            <a href="#technology-performance-tracking">Performance Tracking</a>
            <a href="#technology-multi-surface-play">Multi-Surface Play</a>
            <a href="#technology-network-play">Network Play</a>
            <a href="#technology-swing-replay">Swing Replay</a>
            <a href="#technology-led-putting-guide">LED Putting Guide &amp; Practice</a>
            <a href="#technology-short-game">Short-Game Practice</a>
            <a href="#technology-unreal-graphics">Unreal Engine 5 Graphics</a>
            <a href="#technology-zero-latency">Zero-Latency Gameplay</a>
            <a href="#technology-touchscreen">Touchscreen Control</a>
            <a href="#technology-keypad">Player Keypad</a>
            <a href="#technology-course-info">Course Information</a>
            <a href="#technology-putt-off-green">Putt From Off the Green</a>
            <a href="#technology-arcade-plus">Arcade Plus</a>
        </nav>

        <div class="about-feature-list">
            <article class="about-feature" id="technology-shot-analysis" tabindex="-1">
                <span class="about-feature-number">04</span>
                <div><h3>Shot Analysis</h3><p>Get detailed feedback on every shot, including distance, speed, spin, launch, and more. Depending on the shot and practice mode, measurements can include total and carry distance, apex, ball and club speed, launch direction and angle, attack angle, face angle, dynamic loft, face-to-path, smash factor, club path, spin axis, back spin, and spin rate. Practice modes can track up to 20 data points.</p><?php echo golf_simulator_theme_render_golf_technology_feature_gif('technology-shot-analysis'); ?></div>
            </article>
            <article class="about-feature" id="technology-shot-tracking" tabindex="-1">
                <span class="about-feature-number">05</span>
                <div><h3>Advanced Shot Tracking</h3><p>Review important ball and club data to better understand your swing and shot performance. TwoVision NX uses high-speed, high-definition camera sensors above and in front of the golfer to capture ball and club information after impact and reproduce shot shape and trajectory.</p><?php echo golf_simulator_theme_render_golf_technology_feature_gif('technology-shot-tracking'); ?></div>
            </article>
            <article class="about-feature" id="technology-mobile-app" tabindex="-1">
                <span class="about-feature-number">06</span>
                <div><h3>Mobile App</h3><p>Track your performance, review your stats, and stay connected to your game from your phone. With a GOLFZON account and Global App, players can review rounds, practice sessions, club performance, swing videos, course information, scores, and community activity.</p><?php echo golf_simulator_theme_render_golf_technology_feature_gif('technology-mobile-app'); ?></div>
            </article>
            <article class="about-feature" id="technology-club-data" tabindex="-1">
                <span class="about-feature-number">07</span>
                <div><h3>Club Data</h3><p>See detailed club and ball data to better understand every swing. Compare performance by club and connect impact conditions with ball flight, consistency, and shot shape.</p><?php echo golf_simulator_theme_render_golf_technology_feature_gif('technology-club-data'); ?></div>
            </article>
            <article class="about-feature" id="technology-precision-putting" tabindex="-1">
                <span class="about-feature-number">08</span>
                <div><h3>Precision Putting</h3><p>Practice and play with technology designed to make putting more immersive, with realistic roll and natural ball response. Practice distance, direction, speed, and break for a more realistic putting experience. The putting system is designed to detect short putts as well as longer attempts, while the LED guide provides a visual reference for the intended line.</p><?php echo golf_simulator_theme_render_golf_technology_feature_gif('technology-precision-putting'); ?></div>
            </article>
            <article class="about-feature" id="technology-driving-range" tabindex="-1">
                <span class="about-feature-number">09</span>
                <div><h3>Driving Range</h3><p>Practice your swing and receive instant feedback with detailed performance data. Review ball flight, launch, speed, spin, and club metrics to connect each swing with its result.</p><?php echo golf_simulator_theme_render_golf_technology_feature_gif('technology-driving-range'); ?></div>
            </article>
            <article class="about-feature" id="technology-approach-practice" tabindex="-1">
                <span class="about-feature-number">10</span>
                <div><h3>Approach Practice</h3><p>Sharpen your approach shots with realistic on-course situations. Practice from fairway, rough, and bunker lies within approximately 30 meters of the green, with adjustable green speed, firmness, and pin position.</p><?php echo golf_simulator_theme_render_golf_technology_feature_gif('technology-approach-practice'); ?></div>
            </article>
            <article class="about-feature" id="technology-pitch-chip" tabindex="-1">
                <span class="about-feature-number">11</span>
                <div><h3>Pitch &amp; Chip</h3><p>Build confidence around the green with focused short-game practice. Work on scoring shots from a range of distances and lies, including flat, uphill, and downhill situations.</p><?php echo golf_simulator_theme_render_golf_technology_feature_gif('technology-pitch-chip'); ?></div>
            </article>
            <article class="about-feature" id="technology-performance-tracking" tabindex="-1">
                <span class="about-feature-number">12</span>
                <div><h3>Performance Tracking</h3><p>Monitor key metrics and see how your game improves over time. Review previous sessions, compare clubs, examine shot dispersion, and track trends across practice rounds.</p><?php echo golf_simulator_theme_render_golf_technology_feature_gif('technology-performance-tracking'); ?></div>
            </article>
            <article class="about-feature" id="technology-moving-swing-plate" tabindex="-1">
                <span class="about-feature-number">01</span>
                <div>
                    <h3>Motion Plate</h3>
                    <p>Experience realistic course contours instead of hitting from a completely flat surface. The Motion Plate helps players adjust stance, balance, and swing to match the lie.</p>
                    <ul class="golf-technology-feature-details">
                        <li>Moves in 64 directions</li>
                        <li>Reproduces up to 56,000 possible lies</li>
                        <li>Integrated pressure plates</li>
                        <li>Realistic uphill, downhill, and sidehill lies</li>
                        <li>Works with five hitting surfaces</li>
                    </ul>
                    <?php echo golf_simulator_theme_render_golf_technology_feature_gif('technology-moving-swing-plate'); ?>
                </div>
            </article>
            <article class="about-feature" id="technology-multi-surface-play" tabindex="-1">
                <span class="about-feature-number">13</span>
                <div><h3>Multi-Surface Play</h3><p>Experience different playing conditions, including fairway, rough, and bunker surfaces. The five realistic hitting surfaces include fairway, light rough, deep rough, hard bunker, and soft bunker, each designed to change how the club interacts with the lie.</p><?php echo golf_simulator_theme_render_golf_technology_feature_gif('technology-multi-surface-play'); ?></div>
            </article>
            <article class="about-feature" id="technology-network-play" tabindex="-1">
                <span class="about-feature-number">14</span>
                <div><h3>Network Play</h3><p>Play together and compete through connected simulator experiences, with real-time scoring and head-to-head rounds. GOLFZON Network Play can connect compatible simulator locations for multiplayer golf, online competition, and tournament-style events. GOLFZON lists support for up to 2,000 players across its network.</p><?php echo golf_simulator_theme_render_golf_technology_feature_gif('technology-network-play'); ?></div>
            </article>
            <article class="about-feature" id="technology-auto-tee" tabindex="-1">
                <span class="about-feature-number">02</span>
                <div><h3>Auto-Tee Technology</h3><p>Spend less time setting up and more time playing. The ball automatically tees up after each shot. The ball retrieval system supplies the next ball, and players can adjust tee height to suit their club and setup.</p><?php echo golf_simulator_theme_render_golf_technology_feature_gif('technology-auto-tee'); ?></div>
            </article>
            <article class="about-feature" id="technology-mapped-courses" tabindex="-1">
                <span class="about-feature-number">03</span>
                <div><h3>350+ Golf Courses</h3><p>Explore a course library featuring more than 350 virtually recreated golf courses, including destinations such as St Andrews, Pebble Beach, Kiawah Island, Harbour Town, and PGA National. Play somewhere new each visit or return to a favorite course.</p><?php echo golf_simulator_theme_render_golf_technology_feature_gif('technology-mapped-courses'); ?></div>
            </article>
            <article class="about-feature" id="technology-swing-replay" tabindex="-1">
                <span class="about-feature-number">15</span>
                <div><h3>Swing Replay</h3><p>Review your swing after a shot with the Swing Replay Camera. Pair video with shot data to connect your movement and technique with the ball’s flight.</p><?php echo golf_simulator_theme_render_golf_technology_feature_gif('technology-swing-replay'); ?></div>
            </article>
            <article class="about-feature" id="technology-led-putting-guide" tabindex="-1">
                <span class="about-feature-number">16</span>
                <div><h3>LED Putting Guide &amp; Practice</h3><p>The illuminated guide provides a visual reference for the suggested putting direction, helping players read virtual green breaks before making a stroke. The dedicated putting practice environment offers 80 positions for different distances and lines, including flat, uphill, downhill, and breaking putts.</p><?php echo golf_simulator_theme_render_golf_technology_feature_gif('technology-led-putting-guide'); ?></div>
            </article>
            <article class="about-feature" id="technology-short-game" tabindex="-1">
                <span class="about-feature-number">18</span>
                <div><h3>Short-Game Practice</h3><p>Practice approaches from multiple distances and lies. GOLFZON’s short-game environment includes 18 practice locations ranging from approximately 30 to 200 meters.</p><?php echo golf_simulator_theme_render_golf_technology_feature_gif('technology-short-game'); ?></div>
            </article>
            <article class="about-feature" id="technology-unreal-graphics" tabindex="-1">
                <span class="about-feature-number">19</span>
                <div><h3>Unreal Engine 5 Graphics</h3><p>Detailed terrain, vegetation, bunkers, greens, lighting, and course surroundings create an immersive virtual golf environment, with visual details such as moving flags, divots, and flying tees.</p><?php echo golf_simulator_theme_render_golf_technology_feature_gif('technology-unreal-graphics'); ?></div>
            </article>
            <article class="about-feature" id="technology-zero-latency" tabindex="-1">
                <span class="about-feature-number">20</span>
                <div><h3>Zero-Latency Gameplay</h3><p>TwoVision NX is designed to minimize the delay between impact and ball flight, helping maintain a natural rhythm between your swing and the on-screen shot.</p><?php echo golf_simulator_theme_render_golf_technology_feature_gif('technology-zero-latency'); ?></div>
            </article>
            <article class="about-feature" id="technology-touchscreen" tabindex="-1">
                <span class="about-feature-number">21</span>
                <div><h3>Touchscreen Control</h3><p>The 32-inch high-definition touchscreen kiosk provides access to course selection, settings, practice options, player information, and simulator controls.</p><?php echo golf_simulator_theme_render_golf_technology_feature_gif('technology-touchscreen'); ?></div>
            </article>
            <article class="about-feature" id="technology-keypad" tabindex="-1">
                <span class="about-feature-number">22</span>
                <div><h3>Convenient Player Keypad</h3><p>Use controls from the hitting area for common actions such as adjusting tee height, taking a mulligan, or skipping a player’s turn.</p><?php echo golf_simulator_theme_render_golf_technology_feature_gif('technology-keypad'); ?></div>
            </article>
            <article class="about-feature" id="technology-course-info" tabindex="-1">
                <span class="about-feature-number">23</span>
                <div><h3>Strategic Course Information</h3><p>Use interactive course information and target positions to review distances, consider club selection, and plan a shot before swinging.</p><?php echo golf_simulator_theme_render_golf_technology_feature_gif('technology-course-info'); ?></div>
            </article>
            <article class="about-feature" id="technology-putt-off-green" tabindex="-1">
                <span class="about-feature-number">24</span>
                <div><h3>Putt From Off the Green</h3><p>Choose the putter from eligible fairway or rough areas near the green to play a Texas wedge or another creative shot.</p><?php echo golf_simulator_theme_render_golf_technology_feature_gif('technology-putt-off-green'); ?></div>
            </article>
            <article class="about-feature" id="technology-arcade-plus" tabindex="-1">
                <span class="about-feature-number">25</span>
                <div><h3>Arcade Plus</h3><p>When included in the facility configuration, Arcade Plus makes it more than golf: it adds an all-ages entertainment experience that can attract more guests, encourage longer visits, and support food-and-beverage sales. Choose from seven interactive games: Slope Golf, Dart Golf, Block Golf, ProBowl Showdown, Wild Wild West, Whack-A-Mole, and Night Glow Range.</p><?php echo golf_simulator_theme_render_golf_technology_feature_gif('technology-arcade-plus'); ?></div>
            </article>
        </div>
        <p class="about-attribution">Simulator capabilities are based on GOLFZON TwoVision NX product information. Features and availability may depend on facility configuration.</p>
    </section>

    <section class="about-visit-band">
        <div class="container about-visit-inner">
            <div>
                <span class="about-eyebrow">Play it for yourself</span>
                <h2>Step into your next round.</h2>
                <p>At Tee Time Nexus, we’re creating more than a place to hit golf balls. We’re bringing premium indoor golf technology into a social environment where beginners can discover the game, friends can compete, and players can keep improving in any weather.</p>
                <p class="about-tagline">Practice. Play. Compete. Improve. Powered by GOLFZON TwoVision NX.</p>
            </div>
            <a class="btn btn-primary" href="<?php echo esc_url(home_url('/book-a-bay/')); ?>">Book a Bay <span aria-hidden="true">&#8594;</span></a>
        </div>
    </section>
</main>
<?php get_footer(); ?>