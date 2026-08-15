<?php
/*
Template Name: Account Access
Description: Branded login / register page
*/
get_header();

if (is_user_logged_in()) {
    wp_safe_redirect(home_url('/my-account/'));
    exit;
}

$redirect_to = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : home_url('/my-account/');
$requested_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : '';
$active_tab = ($requested_tab === 'register' || get_post_field('post_name') === 'register') ? 'register' : 'login';
$auth_message = golf_simulator_theme_get_auth_message();
?>
<main class="container">
    <article class="entry-content auth-card">
        <div class="kicker">Tee Time Nexus</div>
        <h1>My Account</h1>
        <p>Log in to manage your bookings, or create an account to book faster next time.</p>

        <?php if ($auth_message) : ?>
            <div class="notice notice-<?php echo esc_attr($auth_message['success'] ? 'success' : 'error'); ?>">
                <p><?php echo esc_html($auth_message['message']); ?></p>
            </div>
        <?php endif; ?>

        <div class="auth-tabs">
            <button type="button" class="auth-tab<?php echo $active_tab === 'login' ? ' active' : ''; ?>" data-tab="login">Log In</button>
            <button type="button" class="auth-tab<?php echo $active_tab === 'register' ? ' active' : ''; ?>" data-tab="register">Create Account</button>
        </div>

        <div class="auth-panel<?php echo $active_tab === 'login' ? ' active' : ''; ?>" id="auth-panel-login">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="ttn_user_login">
                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
                <?php wp_nonce_field('ttn_user_login', 'ttn_login_nonce'); ?>
                <div class="form-grid">
                    <label class="full-width">
                        Email Address
                        <input type="email" name="email" placeholder="you@example.com" autocomplete="email" required>
                    </label>
                    <label class="full-width">
                        Password
                        <input type="password" name="password" placeholder="Your password" autocomplete="current-password" required>
                    </label>
                </div>
                <button type="submit" class="btn btn-primary full-width">Log In</button>
            </form>
        </div>

        <div class="auth-panel<?php echo $active_tab === 'register' ? ' active' : ''; ?>" id="auth-panel-register">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="ttn_user_register">
                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
                <?php wp_nonce_field('ttn_user_register', 'ttn_register_nonce'); ?>
                <div class="form-grid">
                    <label class="full-width">
                        Full Name
                        <input type="text" name="name" placeholder="Your full name" autocomplete="name" required>
                    </label>
                    <label class="full-width">
                        Email Address
                        <input type="email" name="email" placeholder="you@example.com" autocomplete="email" required>
                    </label>
                    <label class="full-width">
                        Password
                        <input type="password" name="password" placeholder="At least 6 characters" autocomplete="new-password" minlength="6" required>
                    </label>
                </div>
                <button type="submit" class="btn btn-primary full-width">Create Account</button>
            </form>
        </div>
    </article>
</main>

<style>
.auth-card { max-width: 480px; }
.auth-tabs {
    display: flex;
    gap: 8px;
    margin: 20px 0 24px;
    border-bottom: 1px solid var(--border-soft);
}
.auth-tab {
    background: none;
    border: none;
    color: var(--muted);
    font-weight: 700;
    font-size: 0.98rem;
    padding: 10px 4px;
    cursor: pointer;
    border-bottom: 2px solid transparent;
}
.auth-tab.active {
    color: var(--heading);
    border-bottom-color: var(--primary);
}
.auth-panel { display: none; }
.auth-panel.active { display: block; }
.auth-card .btn.full-width { width: 100%; margin-top: 12px; }
.auth-card .notice {
    padding: 12px 14px;
    border-radius: 10px;
    margin-bottom: 18px;
    font-size: 0.92rem;
}
.auth-card .notice-error {
    background: rgba(220, 38, 38, 0.12);
    border: 1px solid rgba(220, 38, 38, 0.35);
    color: #f87171;
}
.auth-card .notice-success {
    background: rgba(var(--primary-rgb), 0.12);
    border: 1px solid rgba(var(--primary-rgb), 0.35);
    color: var(--heading);
}
</style>
<script>
(function() {
    var tabs = document.querySelectorAll('.auth-tab');
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            var target = tab.getAttribute('data-tab');
            document.querySelectorAll('.auth-tab').forEach(function(t) { t.classList.remove('active'); });
            document.querySelectorAll('.auth-panel').forEach(function(p) { p.classList.remove('active'); });
            tab.classList.add('active');
            document.getElementById('auth-panel-' + target).classList.add('active');
        });
    });
})();
</script>

<?php get_footer();
