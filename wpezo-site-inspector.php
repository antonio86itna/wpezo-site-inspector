<?php

/**
 * Plugin Name:       WPezo Site Inspector
 * Plugin URI:        https://wpezo.com/site-inspector
 * Description:       Free comprehensive site audit — security, performance, SEO, accessibility & database health. Get an instant grade and actionable fixes for your WordPress site.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            WPezo
 * Author URI:        https://wpezo.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpezo-site-inspector
 * Domain Path:       /languages
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Plugin constants.
 */
define( 'WPSI_VERSION', '1.0.0' );
define( 'WPSI_FILE', __FILE__ );
define( 'WPSI_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPSI_URL', plugin_dir_url( __FILE__ ) );
define( 'WPSI_BASENAME', plugin_basename( __FILE__ ) );
/**
 * ── Freemius Integration ─────────────────────────────────────────
 */
if ( function_exists( 'wsi_fs' ) ) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Freemius SDK generated name.
    wsi_fs()->set_basename( false, __FILE__ );
} else {
    /**
     * DO NOT REMOVE THIS IF, IT IS ESSENTIAL FOR THE
     * `function_exists` CALL ABOVE TO PROPERLY WORK.
     */
    if ( !function_exists( 'wsi_fs' ) ) {
        // Create a helper function for easy SDK access.
        function wsi_fs() {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Freemius SDK generated name.
            global $wsi_fs;
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Freemius SDK generated name.
            if ( !isset( $wsi_fs ) ) {
                // Include Freemius SDK.
                require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';
                $wsi_fs = fs_dynamic_init( array(
                    'id'               => '26657',
                    'slug'             => 'wpezo-site-inspector',
                    'type'             => 'plugin',
                    'public_key'       => 'pk_01303b8c78b14abc12d281c620b4e',
                    'is_premium'       => false,
                    'premium_suffix'   => 'Pro',
                    'has_addons'       => false,
                    'has_paid_plans'   => true,
                    'is_org_compliant' => true,
                    'menu'             => array(
                        'slug' => 'wpezo-site-inspector',
                    ),
                    'is_live'          => true,
                ) );
            }
            return $wsi_fs;
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Freemius SDK generated name.
        }

        // Init Freemius.
        wsi_fs();
        // Signal that SDK was initiated.
        do_action( 'wsi_fs_loaded' );
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Freemius SDK generated hook.
    }
    /**
     * Autoload plugin classes.
     */
    require_once WPSI_PATH . 'includes/class-plugin.php';
    require_once WPSI_PATH . 'includes/class-scanner.php';
    require_once WPSI_PATH . 'includes/class-admin.php';
    require_once WPSI_PATH . 'includes/class-rest-api.php';
    require_once WPSI_PATH . 'includes/class-dashboard-widget.php';
    require_once WPSI_PATH . 'includes/class-email-gate.php';
    require_once WPSI_PATH . 'includes/class-history.php';
    require_once WPSI_PATH . 'includes/class-auto-fixer.php';
    /**
     * Boot the plugin.
     */
    function wpsi_init() {
        return WPSI\Plugin::instance();
    }

    add_action( 'plugins_loaded', 'wpsi_init' );
    /**
     * Activation hook.
     */
    function wpsi_activate() {
        if ( false === get_option( 'wpsi_settings' ) ) {
            update_option( 'wpsi_settings', array(
                'version'        => WPSI_VERSION,
                'installed_at'   => current_time( 'mysql' ),
                'last_scan'      => '',
                'auto_scan'      => 'weekly',
                'show_admin_bar' => true,
                'show_dashboard' => true,
            ) );
        }
        if ( !wp_next_scheduled( 'wpsi_scheduled_scan' ) ) {
            wp_schedule_event( time(), 'weekly', 'wpsi_scheduled_scan' );
        }
        flush_rewrite_rules();
    }

    register_activation_hook( WPSI_FILE, 'wpsi_activate' );
    /**
     * Deactivation hook.
     */
    function wpsi_deactivate() {
        wp_clear_scheduled_hook( 'wpsi_scheduled_scan' );
    }

    register_deactivation_hook( WPSI_FILE, 'wpsi_deactivate' );
    /**
     * Freemius uninstall cleanup.
     */
    function wpsi_fs_uninstall() {
        delete_option( 'wpsi_settings' );
        delete_option( 'wpsi_last_results' );
        delete_option( 'wpsi_last_scan_time' );
        delete_option( 'wpsi_score_history' );
        delete_option( 'wpsi_pending_tokens' );
        wp_clear_scheduled_hook( 'wpsi_scheduled_scan' );
        // DO NOT delete wpsi_pro_unlock_v2 — persistent by design.
    }

    wsi_fs()->add_action( 'after_uninstall', 'wpsi_fs_uninstall' );
}