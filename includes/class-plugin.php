<?php
namespace WPSI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main Plugin singleton.
 */
final class Plugin {

    /** @var Plugin|null */
    private static $instance = null;

    /**
     * Get singleton instance.
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor — wire up hooks.
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Register hooks.
     */
    private function init_hooks() {
        // Admin-only components.
        if ( is_admin() ) {
            Admin::instance();
            Dashboard_Widget::instance();
        }

        // REST API — always register.
        Rest_API::instance();

        // Email gate system.
        Email_Gate::instance();

        // Auto-Fixer (premium feature).
        Auto_Fixer::instance();

        // History REST route.
        add_action( 'rest_api_init', array( 'WPSI\\History', 'register_routes' ) );

        // Scheduled scan.
        add_action( 'wpsi_scheduled_scan', array( $this, 'run_scheduled_scan' ) );

        // Admin bar indicator.
        add_action( 'admin_bar_menu', array( $this, 'admin_bar_indicator' ), 999 );

        // Plugin action links.
        add_filter( 'plugin_action_links_' . WPSI_BASENAME, array( $this, 'action_links' ) );

        // Plugin row meta.
        add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
    }

    /**
     * Run scheduled background scan.
     */
    public function run_scheduled_scan() {
        $scanner = new Scanner();
        $results = $scanner->run_all();
        update_option( 'wpsi_last_results', $results, false );
        update_option( 'wpsi_last_scan_time', current_time( 'mysql' ), false );
    }

    /**
     * Admin bar score indicator.
     */
    public function admin_bar_indicator( $wp_admin_bar ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $settings = get_option( 'wpsi_settings', array() );
        if ( empty( $settings['show_admin_bar'] ) ) {
            return;
        }
        $results = get_option( 'wpsi_last_results' );
        if ( ! $results ) {
            return;
        }
        $score = isset( $results['score'] ) ? intval( $results['score'] ) : 0;
        $grade = self::score_to_grade( $score );
        $color = self::grade_color( $grade );

        $wp_admin_bar->add_node( array(
            'id'    => 'wpsi-score',
            'title' => sprintf(
                '<span style="display:inline-flex;align-items:center;gap:6px;">
                    <span style="background:%s;color:#fff;font-weight:700;font-size:11px;padding:2px 7px;border-radius:3px;line-height:1.4;">%s</span>
                    <span>Site Inspector</span>
                </span>',
                esc_attr( $color ),
                esc_html( $grade )
            ),
            'href'  => admin_url( 'admin.php?page=wpezo-site-inspector' ),
            'meta'  => array(
                'title' => sprintf(
                    /* translators: 1: score number 2: grade letter */
                    __( 'Site Health Score: %1$d/100 (Grade %2$s)', 'wpezo-site-inspector' ),
                    $score,
                    $grade
                ),
            ),
        ) );
    }

    /**
     * Plugin action links.
     */
    public function action_links( $links ) {
        $custom = array(
            '<a href="' . admin_url( 'admin.php?page=wpezo-site-inspector' ) . '">' . __( 'Run Scan', 'wpezo-site-inspector' ) . '</a>',
        );
        return array_merge( $custom, $links );
    }

    /**
     * Plugin row meta.
     */
    public function row_meta( $links, $file ) {
        if ( WPSI_BASENAME === $file ) {
            $links[] = '<a href="https://wpezo.com" target="_blank" rel="noopener">' . __( 'More by WPezo', 'wpezo-site-inspector' ) . '</a>';
            $links[] = '<a href="https://www.youtube.com/@WPezo" target="_blank" rel="noopener">' . __( 'YouTube Tutorials', 'wpezo-site-inspector' ) . '</a>';
        }
        return $links;
    }

    /**
     * Convert numeric score to letter grade.
     */
    public static function score_to_grade( $score ) {
        if ( $score >= 90 ) return 'A';
        if ( $score >= 80 ) return 'B';
        if ( $score >= 70 ) return 'C';
        if ( $score >= 60 ) return 'D';
        return 'F';
    }

    /**
     * Grade badge color.
     */
    public static function grade_color( $grade ) {
        $map = array(
            'A' => '#16a34a',
            'B' => '#65a30d',
            'C' => '#ca8a04',
            'D' => '#ea580c',
            'F' => '#dc2626',
        );
        return isset( $map[ $grade ] ) ? $map[ $grade ] : '#6b7280';
    }

    /**
     * Prevent cloning.
     */
    private function __clone() {}

    /**
     * Prevent unserialization.
     */
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize singleton.' );
    }
}
