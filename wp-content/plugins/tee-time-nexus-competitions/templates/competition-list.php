<?php
$posts = TTN_Competitions_Frontend::get_posts($type, $view === 'landing' ? 6 : 50);
?>
<div class="ttn-competitions">
    <?php if ($view === 'landing') : ?>
        <section class="ttn-competitions-hero">
            <p class="ttn-competitions-kicker"><?php esc_html_e('TEE TIME NEXUS · MOORESVILLE, NC', 'tee-time-nexus-competitions'); ?></p>
            <h1><?php esc_html_e('Compete. Connect. Conquer.', 'tee-time-nexus-competitions'); ?></h1>
            <p><?php esc_html_e('Join leagues and tournaments at Tee Time Nexus. Find your next event and get ready to play.', 'tee-time-nexus-competitions'); ?></p>
        </section>
        <nav class="ttn-competition-categories" aria-label="<?php esc_attr_e('Competition types', 'tee-time-nexus-competitions'); ?>">
            <a href="<?php echo esc_url(home_url('/golf-leagues/')); ?>"><?php esc_html_e('Explore Golf Leagues', 'tee-time-nexus-competitions'); ?> <span aria-hidden="true">&rarr;</span></a>
            <a href="<?php echo esc_url(home_url('/golf-tournaments/')); ?>"><?php esc_html_e('Explore Golf Tournaments', 'tee-time-nexus-competitions'); ?> <span aria-hidden="true">&rarr;</span></a>
        </nav>
        <div class="ttn-competition-section-heading">
            <h2><?php esc_html_e('Upcoming Events', 'tee-time-nexus-competitions'); ?></h2>
            <p><?php esc_html_e('Competition details and schedules are managed by Tee Time Nexus.', 'tee-time-nexus-competitions'); ?></p>
        </div>
    <?php else : ?>
        <header class="ttn-competitions-heading">
            <p class="ttn-competitions-kicker"><?php esc_html_e('TEE TIME NEXUS · MOORESVILLE, NC', 'tee-time-nexus-competitions'); ?></p>
            <h1><?php echo esc_html($type === 'league' ? __('Golf Leagues', 'tee-time-nexus-competitions') : ($type === 'tournament' ? __('Golf Tournaments', 'tee-time-nexus-competitions') : __('Leagues & Tournaments', 'tee-time-nexus-competitions'))); ?></h1>
            <p><?php esc_html_e('Browse upcoming competitions and event details.', 'tee-time-nexus-competitions'); ?></p>
        </header>
    <?php endif; ?>

    <?php if ($posts) : ?>
        <div class="ttn-competition-grid">
            <?php foreach ($posts as $post) : ?>
                <?php TTN_Competitions_Frontend::render_card($post); ?>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <div class="ttn-competitions-empty">
            <h2><?php esc_html_e('Events are on the way.', 'tee-time-nexus-competitions'); ?></h2>
            <p><?php esc_html_e('There are no upcoming competitions listed right now. Please check back soon.', 'tee-time-nexus-competitions'); ?></p>
        </div>
    <?php endif; ?>
</div>
