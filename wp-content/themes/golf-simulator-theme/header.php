<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="site-header">
    <div class="container header-inner">
        <?php $site_logo = get_theme_mod('golf_simulator_site_logo'); ?>
        <?php if (!empty($site_logo)) : ?>
            <a class="brand" href="<?php echo esc_url(home_url('/')); ?>">
                <img class="custom-logo" src="<?php echo esc_url($site_logo); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
            </a>
        <?php elseif (has_custom_logo()) : ?>
            <?php the_custom_logo(); ?>
        <?php else : ?>
            <a class="brand brand-fallback" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Tee Time Nexus">
                <span class="brand-fallback-mark">TT</span>
                <span class="brand-fallback-text">
                    <span class="brand-fallback-top">Tee Time</span>
                    <span class="brand-fallback-bottom">Nexus</span>
                </span>
            </a>
        <?php endif; ?>
        <?php golf_simulator_theme_menu(); ?>
        <div class="header-user-menu">
            <?php if (is_user_logged_in()) : ?>
                <a href="<?php echo esc_url(home_url('/my-account/')); ?>" class="user-link">My Account</a>
                <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="user-link">Logout</a>
            <?php else : ?>
                <a href="<?php echo esc_url(golf_simulator_theme_get_login_url()); ?>" class="user-link">Login</a>
            <?php endif; ?>
        </div>
    </div>
</header>
