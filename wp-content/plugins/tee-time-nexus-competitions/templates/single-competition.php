<?php
get_header();
?>
<main class="ttn-competition-single container">
    <?php while (have_posts()) : the_post(); ?>
        <?php
        $terms = get_the_terms(get_the_ID(), TTN_Competitions_Post_Type::TAXONOMY);
        $type_label = is_array($terms) && !empty($terms) ? $terms[0]->name : __('Competition', 'tee-time-nexus-competitions');
        $start_date = get_post_meta(get_the_ID(), '_ttn_start_date', true);
        $end_date = get_post_meta(get_the_ID(), '_ttn_end_date', true);
        ?>
        <article <?php post_class('ttn-competition-detail'); ?>>
            <p class="ttn-competitions-kicker"><?php echo esc_html($type_label); ?></p>
            <h1><?php the_title(); ?></h1>
            <?php if (has_post_thumbnail()) : ?><div class="ttn-competition-featured-image"><?php the_post_thumbnail('large'); ?></div><?php endif; ?>
            <div class="ttn-competition-facts">
                <?php if ($start_date) : ?><p><strong><?php esc_html_e('Date', 'tee-time-nexus-competitions'); ?></strong><br><?php echo esc_html(TTN_Competitions_Frontend::format_date_range($start_date, $end_date)); ?></p><?php endif; ?>
                <?php if ($course = get_post_meta(get_the_ID(), '_ttn_course', true)) : ?><p><strong><?php esc_html_e('Course', 'tee-time-nexus-competitions'); ?></strong><br><?php echo esc_html($course); ?></p><?php endif; ?>
                <?php if ($format = get_post_meta(get_the_ID(), '_ttn_format', true)) : ?><p><strong><?php esc_html_e('Format', 'tee-time-nexus-competitions'); ?></strong><br><?php echo esc_html($format); ?></p><?php endif; ?>
                <?php if (($capacity = absint(get_post_meta(get_the_ID(), '_ttn_capacity', true))) > 0) : ?><p><strong><?php esc_html_e('Maximum participants', 'tee-time-nexus-competitions'); ?></strong><br><?php echo esc_html((string) $capacity); ?></p><?php endif; ?>
            </div>
            <div class="ttn-competition-content"><?php the_content(); ?></div>
            <?php echo TTN_Competitions_Registrations::render_registration_box(get_the_ID()); ?>
        </article>
    <?php endwhile; ?>
</main>
<?php
get_footer();
