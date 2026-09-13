<?php
/*
Template Name: Account Access
Description: Branded login / register page
*/

if (is_user_logged_in()) {
    wp_safe_redirect(home_url('/my-account/'));
    exit;
}

$redirect_to = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : home_url('/my-account/');
if (isset($_POST['redirect_to'])) {
    $redirect_to = esc_url_raw(wp_unslash($_POST['redirect_to']));
}

$active_tab = (isset($_GET['tab']) && $_GET['tab'] === 'register') || get_post_field('post_name') === 'register' ? 'register' : 'login';
$error_message = '';

// Processed here (before get_header()) so a successful login/register can
// redirect immediately; on failure we fall through and render the page
// with the error inline, avoiding any redirect/transient/cache edge cases.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ttn_auth_action'])) {
    $submitted_action = sanitize_text_field(wp_unslash($_POST['ttn_auth_action']));

    if ($submitted_action === 'register') {
        $active_tab = 'register';
        $error_message = golf_simulator_theme_process_register($redirect_to);
    } elseif ($submitted_action === 'login') {
        $active_tab = 'login';
        $error_message = golf_simulator_theme_process_login($redirect_to);
    }
}

get_header();
?>
<main class="container">
    <article class="entry-content auth-card">
        <div class="kicker">Tee Time Nexus</div>
        <h1>My Account</h1>
        <p>Log in to manage your bookings, or create an account to book faster next time.</p>

        <?php if ($error_message) : ?>
            <div class="notice notice-error" data-tab="<?php echo esc_attr($active_tab); ?>">
                <p><?php echo wp_kses($error_message, array('a' => array('href' => array()))); ?></p>
            </div>
        <?php endif; ?>

        <div class="auth-tabs">
            <button type="button" class="auth-tab<?php echo $active_tab === 'login' ? ' active' : ''; ?>" data-tab="login">Log In</button>
            <button type="button" class="auth-tab<?php echo $active_tab === 'register' ? ' active' : ''; ?>" data-tab="register">Create Account</button>
        </div>

        <div class="auth-panel<?php echo $active_tab === 'login' ? ' active' : ''; ?>" id="auth-panel-login">
            <form method="post">
                <input type="hidden" name="ttn_auth_action" value="login">
                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
                <?php wp_nonce_field('ttn_user_login', 'ttn_login_nonce'); ?>
                <div class="form-grid">
                    <label class="full-width">
                        Email Address or Username
                        <input type="text" name="login_identifier" value="<?php echo esc_attr($active_tab === 'login' && isset($_POST['login_identifier']) ? wp_unslash($_POST['login_identifier']) : ''); ?>" placeholder="you@example.com or admin" autocomplete="username" required>
                    </label>
                    <label class="full-width">
                        Password
                        <input type="password" name="password" placeholder="Your password" autocomplete="current-password" required>
                    </label>
                </div>
                <p class="auth-forgot"><a href="<?php echo esc_url(wp_lostpassword_url(home_url('/login/'))); ?>" class="text-link">Forgot your password?</a></p>
                <button type="submit" class="btn btn-primary full-width">Log In</button>
            </form>
        </div>

        <div class="auth-panel<?php echo $active_tab === 'register' ? ' active' : ''; ?>" id="auth-panel-register">
            <form method="post">
                <input type="hidden" name="ttn_auth_action" value="register">
                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
                <?php wp_nonce_field('ttn_user_register', 'ttn_register_nonce'); ?>
                <div class="form-grid">
                    <label class="full-width">
                        Full Name
                        <input type="text" name="ttn_name" placeholder="Your full name" autocomplete="name" required>
                    </label>
                    <label class="full-width">
                        Email Address
                        <input type="email" name="email" placeholder="you@example.com" autocomplete="email" required>
                    </label>
                    <label class="full-width">
                        Password
                        <input type="password" name="password" placeholder="At least 6 characters" autocomplete="new-password" minlength="6" required>
                    </label>
                    <label class="full-width">
                        Phone Number
                        <input type="tel" name="phone" placeholder="(555) 123-4567" autocomplete="tel">
                    </label>
                    <label class="full-width checkbox-label">
                        <input type="checkbox" name="sms_opt_in" id="ttn-sms-opt-in" value="1">
                        Text me about my bookings and tee time offers
                    </label>
                    <label class="full-width checkbox-label">
                        <input type="checkbox" name="promo_opt_in" value="1">
                        Send me promotional emails and text messages about offers
                    </label>
                </div>
                <button type="submit" class="btn btn-primary full-width" id="ttn-register-submit">Create Account</button>
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
.auth-forgot { margin: 10px 0 0; text-align: right; font-size: 0.9rem; }
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
.auth-card .optional-tag {
    font-weight: 400;
    color: var(--muted);
}
.auth-card .checkbox-label {
    flex-direction: row !important;
    align-items: center;
    gap: 10px !important;
    font-weight: 500;
}
.auth-card .checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
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

            var notice = document.querySelector('.notice-error');
            if (notice && notice.getAttribute('data-tab') !== target) {
                notice.style.display = 'none';
            }
        });
    });

    document.querySelectorAll('.auth-panel form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (form.dataset.submitting === 'true') {
                return;
            }

            var isRegister = form.querySelector('input[name="ttn_auth_action"]').value === 'register';

            if (isRegister) {
                var smsCheckbox = form.querySelector('#ttn-sms-opt-in');
                if (smsCheckbox && !smsCheckbox.checked) {
                    var proceed = window.confirm('You haven\'t opted in to text messages. You might miss important booking updates and reminders. Continue anyway?');
                    if (!proceed) {
                        e.preventDefault();
                        return;
                    }
                }
            }

            form.dataset.submitting = 'true';

            var button = form.querySelector('button[type="submit"]');
            if (!button) {
                return;
            }

            button.disabled = true;
            button.classList.add('is-loading');
            button.innerHTML = '<span class="btn-spinner" aria-hidden="true"></span>' + (isRegister ? 'Creating account...' : 'Logging in...');
        });
    });
})();
</script>

<?php get_footer();
