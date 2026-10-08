<?php
/**
 * Plugin Name: Tee Time Nexus Competitions
 * Description: Manage golf leagues and tournaments and publish their listings to the website and mobile app.
 * Version: 1.1.3
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Tee Time Nexus
 * Text Domain: tee-time-nexus-competitions
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TTN_COMPETITIONS_VERSION', '1.1.3');
define('TTN_COMPETITIONS_SCHEMA_VERSION', '1.1.0');
define('TTN_COMPETITIONS_FILE', __FILE__);
define('TTN_COMPETITIONS_DIR', plugin_dir_path(__FILE__));

require_once TTN_COMPETITIONS_DIR . 'includes/class-competition-post-type.php';
require_once TTN_COMPETITIONS_DIR . 'includes/class-competition-api.php';
require_once TTN_COMPETITIONS_DIR . 'includes/class-competition-frontend.php';
require_once TTN_COMPETITIONS_DIR . 'includes/class-competition-admin.php';
require_once TTN_COMPETITIONS_DIR . 'includes/class-competition-registrations.php';

final class TTN_Competitions_Plugin {
    public function __construct() {
        add_action('init', array('TTN_Competitions_Post_Type', 'register'));
        add_action('rest_api_init', array('TTN_Competitions_API', 'register_routes'));
        add_action('init', array('TTN_Competitions_Frontend', 'register_shortcodes'));
        add_action('wp_enqueue_scripts', array('TTN_Competitions_Frontend', 'enqueue_assets'));
        add_filter('template_include', array('TTN_Competitions_Frontend', 'template_include'));
        add_action('plugins_loaded', array('TTN_Competitions_Registrations', 'maybe_upgrade'));
        TTN_Competitions_Registrations::register_hooks();

        if (is_admin()) {
            new TTN_Competitions_Admin();
        }
    }

    public static function activate() {
        TTN_Competitions_Post_Type::register();
        if (!TTN_Competitions_Registrations::install_schema()) {
            wp_die(esc_html__('Competition registrations could not be initialized. Check the database permissions and try activating the plugin again.', 'tee-time-nexus-competitions'));
        }
        self::add_capabilities();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        wp_clear_scheduled_hook('ttn_competitions_expire_registrations');
        self::remove_capabilities();
        flush_rewrite_rules();
    }

    private static function add_capabilities() {
        foreach (array('administrator', 'shop_manager') as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach (self::capabilities() as $capability) {
                    $role->add_cap($capability);
                }
            }
        }
    }

    private static function remove_capabilities() {
        foreach (array('administrator', 'shop_manager') as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach (self::capabilities() as $capability) {
                    $role->remove_cap($capability);
                }
            }
        }
    }

    private static function capabilities() {
        return array(
            'read_ttn_competition',
            'read_private_ttn_competitions',
            'edit_ttn_competition',
            'edit_ttn_competitions',
            'edit_others_ttn_competitions',
            'edit_published_ttn_competitions',
            'publish_ttn_competitions',
            'delete_ttn_competition',
            'delete_ttn_competitions',
            'delete_others_ttn_competitions',
            'delete_published_ttn_competitions',
            'manage_ttn_competitions',
        );
    }
}

new TTN_Competitions_Plugin();

register_activation_hook(__FILE__, array('TTN_Competitions_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('TTN_Competitions_Plugin', 'deactivate'));
