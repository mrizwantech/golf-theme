<?php
/**
 * Template Name: Hours & Access
 */
get_header();
$hours = golf_simulator_theme_hours_content();
$member = $hours['sections'][0];
$public = $hours['sections'][1];
?>
<main class="hours-page">
    <section class="hours-hero container">
        <h1><?php echo esc_html($hours['title']); ?></h1>
    </section>

    <section class="hours-schedule-section">
        <div class="container">
            <div class="hours-schedule-grid">
                <article class="hours-schedule hours-member-schedule">
                    <h2 class="hours-schedule-title"><?php echo esc_html($member['title']); ?> <span class="hours-member-access-time"><?php echo esc_html($member['time']); ?></span></h2>
                    <p class="hours-note"><?php echo esc_html($member['note']); ?></p>
                    <a class="btn btn-primary" href="<?php echo esc_url(home_url('/membership/')); ?>"><?php echo esc_html($member['action']['label']); ?> <span aria-hidden="true">&#8594;</span></a>
                </article>
                <article class="hours-schedule hours-public-schedule">
                    <h2 class="hours-schedule-title"><?php echo esc_html($public['title']); ?></h2>
                    <p class="hours-time"><?php echo str_replace(' to ', ' <span>to</span> ', esc_html($public['time'])); ?></p>
                    <p class="hours-note"><?php echo esc_html($public['note']); ?></p>
                    <a class="btn btn-primary" href="<?php echo esc_url(home_url('/book-a-bay/')); ?>"><?php echo esc_html($public['action']['label']); ?> <span aria-hidden="true">&#8594;</span></a>
                </article>
            </div>
        </div>
    </section>
</main>
<?php get_footer(); ?>