<?php
/**
 * Template for the launch splash screen.
 */
?>
<div id="golf-simulator-launch-splash" class="launch-splash" aria-live="polite" aria-hidden="true">
    <div class="launch-splash-inner">
        <span class="launch-splash-kicker">Grand Opening</span>
        <h1>Opening Fall 2026</h1>
        <p>Something special is coming to Mooresville, NC.</p>
        <p>A premium indoor golf experience is almost here.</p>
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

        window.addEventListener("load", function () {
            setTimeout(function () {
                hideSplash();
            }, 5000);

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
