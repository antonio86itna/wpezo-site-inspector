<?php
namespace WPSI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin page — renders the Site Inspector dashboard.
 */
class Admin {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function add_menu() {
        add_menu_page(
            __( 'Site Inspector', 'wpezo-site-inspector' ),
            __( 'Site Inspector', 'wpezo-site-inspector' ),
            'manage_options',
            'wpezo-site-inspector',
            array( $this, 'render_page' ),
            'dashicons-search',
            80
        );
    }

    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_wpezo-site-inspector' !== $hook ) {
            return;
        }
        wp_enqueue_style(
            'wpsi-admin',
            WPSI_URL . 'assets/css/admin.css',
            array(),
            filemtime( WPSI_PATH . 'assets/css/admin.css' )
        );
        wp_enqueue_script(
            'wpsi-admin',
            WPSI_URL . 'assets/js/admin.js',
            array(),
            filemtime( WPSI_PATH . 'assets/js/admin.js' ),
            true
        );
        // Premium state — Freemius compliant.
        $is_paying    = Auto_Fixer::is_premium();
        $upgrade_url  = function_exists( 'wsi_fs' ) ? wsi_fs()->get_upgrade_url() : '';
        $checkout_url = function_exists( 'wsi_fs' ) ? wsi_fs()->checkout_url() : '';

        wp_localize_script( 'wpsi-admin', 'wpsiData', array(
            'restUrl'       => rest_url( 'wpsi/v1/' ),
            'nonce'         => wp_create_nonce( 'wp_rest' ),
            'lastScan'      => get_option( 'wpsi_last_scan_time', '' ),
            'results'       => get_option( 'wpsi_last_results', null ),
            'history'       => History::get_all(),
            'proUnlocked'   => Email_Gate::is_unlocked(),
            'isPremium'     => $is_paying,
            'upgradeUrl'    => $upgrade_url,
            'checkoutUrl'   => $checkout_url,
            'fixableChecks' => array_keys( Auto_Fixer::get_fixable_checks() ),
            'i18n'          => array(
                'scanning'   => __( 'Scanning your site...', 'wpezo-site-inspector' ),
                'scanDone'   => __( 'Scan complete!', 'wpezo-site-inspector' ),
                'scanError'  => __( 'Scan failed. Please try again.', 'wpezo-site-inspector' ),
                'runScan'    => __( 'Run Full Scan', 'wpezo-site-inspector' ),
                'reScan'     => __( 'Re-Scan', 'wpezo-site-inspector' ),
                'noResults'  => __( 'No scan results yet. Click "Run Full Scan" to get started.', 'wpezo-site-inspector' ),
                'pass'       => __( 'Passed', 'wpezo-site-inspector' ),
                'warn'       => __( 'Warning', 'wpezo-site-inspector' ),
                'fail'       => __( 'Failed', 'wpezo-site-inspector' ),
                'print'      => __( 'Print Report', 'wpezo-site-inspector' ),
                'lastScan'   => __( 'Last scan:', 'wpezo-site-inspector' ),
                'fix'        => __( 'How to fix:', 'wpezo-site-inspector' ),
            ),
        ) );
    }

    public function render_page() {
        ?>
        <div class="wpsi-wrap">
            <!-- Header -->
            <div class="wpsi-header">
                <div class="wpsi-header-left">
                    <div class="wpsi-logo">
                        <svg width="36" height="36" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect width="36" height="36" rx="8" fill="#2B6CB0"/>
                            <path d="M10 18C10 13.58 13.58 10 18 10C22.42 10 26 13.58 26 18" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
                            <circle cx="18" cy="18" r="3" fill="white"/>
                            <line x1="18" y1="18" x2="23" y2="13" stroke="white" stroke-width="2" stroke-linecap="round"/>
                            <circle cx="11" cy="24" r="1.5" fill="#BEE3F8"/>
                            <circle cx="18" cy="26" r="1.5" fill="#BEE3F8"/>
                            <circle cx="25" cy="24" r="1.5" fill="#BEE3F8"/>
                        </svg>
                    </div>
                    <div>
                        <h1><?php esc_html_e( 'WPezo Site Inspector', 'wpezo-site-inspector' ); ?></h1>
                        <p class="wpsi-subtitle"><?php esc_html_e( 'Comprehensive WordPress site audit — security, performance, SEO, accessibility & database health.', 'wpezo-site-inspector' ); ?></p>
                    </div>
                </div>
                <div class="wpsi-header-right">
                    <button id="wpsi-scan-btn" class="wpsi-btn wpsi-btn-primary">
                        <span class="wpsi-btn-icon">&#9654;</span>
                        <span class="wpsi-btn-label"><?php esc_html_e( 'Run Full Scan', 'wpezo-site-inspector' ); ?></span>
                    </button>
                    <button id="wpsi-print-btn" class="wpsi-btn wpsi-btn-secondary" style="display:none;">
                        <span class="wpsi-btn-label"><?php esc_html_e( 'Print Report', 'wpezo-site-inspector' ); ?></span>
                    </button>
                    <button id="wpsi-export-btn" class="wpsi-btn wpsi-btn-secondary" style="display:none;">
                        <span class="wpsi-btn-label">📄 <?php esc_html_e( 'Export Report', 'wpezo-site-inspector' ); ?></span>
                    </button>
                </div>
            </div>

            <!-- Progress bar (hidden by default) -->
            <div id="wpsi-progress" class="wpsi-progress" style="display:none;">
                <div class="wpsi-progress-bar">
                    <div class="wpsi-progress-fill" id="wpsi-progress-fill"></div>
                </div>
                <p class="wpsi-progress-text" id="wpsi-progress-text"><?php esc_html_e( 'Scanning your site...', 'wpezo-site-inspector' ); ?></p>
            </div>

            <!-- Results container -->
            <div id="wpsi-results" class="wpsi-results">
                <!-- Populated by JS -->
            </div>

            <!-- Powered by WPezo footer -->
            <div class="wpsi-footer">
                <div class="wpsi-footer-brand">
                    <strong>Powered by <a href="https://wpezo.com" target="_blank" rel="noopener">WPezo</a></strong>
                    &nbsp;&middot;&nbsp;
                    <a href="https://www.youtube.com/@WPezo" target="_blank" rel="noopener"><?php esc_html_e( 'YouTube Tutorials', 'wpezo-site-inspector' ); ?></a>
                </div>
                <div class="wpsi-footer-version">
                    v<?php echo esc_html( WPSI_VERSION ); ?>
                </div>
            </div>
        </div>
        <?php
    }
}
