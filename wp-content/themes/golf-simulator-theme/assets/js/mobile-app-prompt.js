document.addEventListener('DOMContentLoaded', function () {
    var prompt = document.getElementById('ttn-mobile-app-prompt');
    var userAgent = navigator.userAgent;
    var mobileScreen = window.matchMedia('(max-width: 860px)');
    if (!prompt || typeof prompt.showModal !== 'function'
        || !/Android/i.test(userAgent) || !/Mobile/i.test(userAgent)
        || /\bwv\b/i.test(userAgent)
        || document.body.classList.contains('ttn-app-checkout')
        || window.matchMedia('(display-mode: standalone)').matches) {
        return;
    }

    var storageKey = 'ttn_app_prompt_dismissed_at';
    var dismissalDuration = 7 * 24 * 60 * 60 * 1000;
    var observer;

    function recentlyDismissed() {
        try {
            var dismissedAt = Number(window.localStorage.getItem(storageKey));
            return dismissedAt > 0 && Date.now() - dismissedAt < dismissalDuration;
        } catch (error) {
            console.warn('Tee Time Nexus: unable to read app popup dismissal.', error);
            return false;
        }
    }

    function rememberDismissal() {
        try {
            window.localStorage.setItem(storageKey, String(Date.now()));
        } catch (error) {
            console.warn('Tee Time Nexus: unable to remember app popup dismissal.', error);
        }
    }

    function dismiss() {
        rememberDismissal();
        prompt.close();
    }

    prompt.querySelectorAll('[data-app-dismiss]').forEach(function (button) {
        button.addEventListener('click', dismiss);
    });
    prompt.querySelector('[data-app-download]').addEventListener('click', dismiss);
    prompt.addEventListener('cancel', function (event) {
        event.preventDefault();
        dismiss();
    });
    prompt.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            dismiss();
        }
    });

    function showPrompt() {
        var splash = document.getElementById('golf-simulator-launch-splash');
        if (splash && !splash.classList.contains('is-hidden')) {
            return;
        }
        if (observer) {
            observer.disconnect();
        }
        if (mobileScreen.matches && !prompt.open && !recentlyDismissed()
            && !document.querySelector('dialog[open]')) {
            prompt.showModal();
        }
    }

    if (document.getElementById('golf-simulator-launch-splash')) {
        observer = new MutationObserver(showPrompt);
        observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
    }
    mobileScreen.addEventListener('change', function () {
        if (!mobileScreen.matches && prompt.open) {
            prompt.close();
        } else {
            showPrompt();
        }
    });
    showPrompt();
});
