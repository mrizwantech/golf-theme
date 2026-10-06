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
$password_reset_notice = isset($_GET['checkemail']) && 'confirm' === sanitize_key(wp_unslash($_GET['checkemail']));

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
        <p>Log in to manage your bookings and member benefits, or create an account to save your booking history, track perks, and book faster next time.</p>

        <?php if ($password_reset_notice) : ?>
            <div class="notice notice-success" data-tab="login">
                <p>If an account matches that email, we sent a password reset link. Check your inbox and spam folder for next steps.</p>
            </div>
        <?php endif; ?>

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
                        <input type="password" id="ttn-register-password" name="password" placeholder="8+ characters, upper/lowercase, number, symbol" autocomplete="new-password" minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,}" title="Use at least 8 characters, including uppercase and lowercase letters, a number, and a symbol." required>
                    </label>
                    <ul class="password-requirements full-width" id="ttn-password-requirements" aria-live="polite">
                        <li data-rule="length"><span>○</span>At least 8 characters</li>
                        <li data-rule="uppercase"><span>○</span>One uppercase letter</li>
                        <li data-rule="lowercase"><span>○</span>One lowercase letter</li>
                        <li data-rule="number"><span>○</span>One number</li>
                        <li data-rule="symbol"><span>○</span>One symbol</li>
                    </ul>
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
.auth-card .notice-success {
    background: rgba(var(--primary-rgb), 0.12);
    border: 1px solid rgba(var(--primary-rgb), 0.35);
    color: var(--heading);
}
.password-requirements {
    display: grid;
    gap: 6px;
    margin: -6px 0 12px;
    padding: 0;
    list-style: none;
}
.password-requirements li {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--muted);
    font-size: .82rem;
}
.password-requirements li span {
    width: 18px;
    color: var(--muted);
    font-weight: 800;
}
.password-requirements li.is-met {
    color: var(--heading);
}
.password-requirements li.is-met span {
    color: var(--primary);
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
    var passwordInput = document.getElementById('ttn-register-password');
    var passwordRequirements = document.getElementById('ttn-password-requirements');
    if (passwordInput && passwordRequirements) {
        function updatePasswordRequirements() {
            var value = passwordInput.value;
            var checks = {
                length: value.length >= 8,
                uppercase: /[A-Z]/.test(value),
                lowercase: /[a-z]/.test(value),
                number: /[0-9]/.test(value),
                symbol: /[^A-Za-z0-9\s]/.test(value)
            };
            passwordRequirements.querySelectorAll('[data-rule]').forEach(function(item) {
                var met = checks[item.getAttribute('data-rule')];
                item.classList.toggle('is-met', met);
                item.querySelector('span').textContent = met ? '✓' : '○';
            });
        }
        passwordInput.addEventListener('input', updatePasswordRequirements);
        updatePasswordRequirements();
    }

    var tabs = document.querySelectorAll('.auth-tab');
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            var target = tab.getAttribute('data-tab');
            document.querySelectorAll('.auth-tab').forEach(function(t) { t.classList.remove('active'); });
            document.querySelectorAll('.auth-panel').forEach(function(p) { p.classList.remove('active'); });
            tab.classList.add('active');
            document.getElementById('auth-panel-' + target).classList.add('active');

            var notice = document.querySelector('.notice-error, .notice-success');
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
