<?php get_header(); ?>
<main class="container">
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <article class="entry-content">
            <h1><?php the_title(); ?></h1>
            <?php the_content(); ?>
        </article>
    <?php endwhile; endif; ?>
</main>
<?php get_footer(); ?>
