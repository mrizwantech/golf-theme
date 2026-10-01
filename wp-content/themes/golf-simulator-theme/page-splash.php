<?php
/**
 * Template for the launch splash screen.
 */
?>
<div id="golf-simulator-launch-splash" class="launch-splash" aria-live="polite" aria-hidden="true">
    <div class="launch-splash-inner">
        <button type="button" class="launch-splash-close" id="golf-simulator-launch-close" aria-label="Close">&times;</button>
        <span class="launch-splash-kicker">Grand Opening</span>
        <h1>Opening Fall 2026</h1>
        <p>Something special is coming to Mooresville, NC.</p>
        <p>A premium indoor golf experience is almost here.</p>
        <div class="launch-splash-actions">
            <a class="btn btn-primary" href="<?php echo esc_url(home_url('/welcome')); ?>">Sign Up for Updates</a>
        </div>
    </div>
</div>
<script>
    (function () {
        var key = "golf_simulator_launch_screen_seen";
        var splash = document.getElementById("golf-simulator-launch-splash");
        if (!splash) {
            return;
        }

        function hideSplash() {
            splash.classList.add("is-hidden");
            setTimeout(function () {
                splash.remove();
            }, 400);
        }

        try {
            if (window.sessionStorage && window.sessionStorage.getItem(key) === "1") {
                splash.remove();
                return;
            }
        } catch (error) {
            // Ignore storage access issues.
        }

        var closeButton = document.getElementById("golf-simulator-launch-close");
        if (closeButton) {
            closeButton.addEventListener("click", hideSplash);
        }

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") {
                hideSplash();
            }
        });

        window.addEventListener("load", function () {
            try {
                if (window.sessionStorage) {
                    window.sessionStorage.setItem(key, "1");
                }
            } catch (error) {
                // Ignore storage access issues.
            }
        });
    })();
</script>
