<?php
require 'wp-load.php';

$plugin = 'tee-time-nexus-bookings/tee-time-nexus-bookings.php';
$active = is_plugin_active($plugin);

if (!$active) {
    activate_plugin($plugin);
    echo 'activated';
} else {
    echo 'already-active';
}
