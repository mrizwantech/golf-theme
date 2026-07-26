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
        <a class="brand" href="<?php echo esc_url(home_url('/')); ?>">
            <span class="brand-mark">TN</span>
            <span>Tee Time Nexus</span>
        </a>
        <?php golf_simulator_theme_menu(); ?>
        <div class="header-user-menu">
            <?php if (is_user_logged_in()) : ?>
                <a href="<?php echo esc_url(home_url('/my-account/')); ?>" class="user-link">My Account</a>
                <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="user-link">Logout</a>
            <?php else : ?>
                <a href="<?php echo esc_url(wp_login_url()); ?>" class="user-link">Login</a>
            <?php endif; ?>
        </div>
    </div>
</header>
