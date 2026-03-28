<?php
namespace WPSI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WordPress Dashboard widget showing site score.
 */
class Dashboard_Widget {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
    }

    public function register_widget() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $settings = get_option( 'wpsi_settings', array() );
        if ( empty( $settings['show_dashboard'] ) ) {
            return;
        }
        wp_add_dashboard_widget(
            'wpsi_dashboard_widget',
            __( 'WPezo Site Inspector', 'wpezo-site-inspector' ),
            array( $this, 'render' )
        );
    }

    public function render() {
        $results   = get_option( 'wpsi_last_results' );
        $last_scan = get_option( 'wpsi_last_scan_time', '' );

        if ( ! $results ) {
            printf(
                '<p>%s</p><p><a href="%s" class="button button-primary">%s</a></p>',
                esc_html__( 'No scan results yet.', 'wpezo-site-inspector' ),
                esc_url( admin_url( 'admin.php?page=wpezo-site-inspector' ) ),
                esc_html__( 'Run Your First Scan', 'wpezo-site-inspector' )
            );
            return;
        }

        $score   = isset( $results['score'] ) ? intval( $results['score'] ) : 0;
        $grade   = Plugin::score_to_grade( $score );
        $color   = Plugin::grade_color( $grade );
        $summary = isset( $results['summary'] ) ? $results['summary'] : array( 'pass' => 0, 'warn' => 0, 'fail' => 0 );

        ?>
        <div style="text-align:center;padding:10px 0;">
            <div style="display:inline-flex;align-items:center;gap:16px;margin-bottom:12px;">
                <div style="width:64px;height:64px;border-radius:50%;background:<?php echo esc_attr( $color ); ?>;color:#fff;font-size:28px;font-weight:800;display:flex;align-items:center;justify-content:center;line-height:1;">
                    <?php echo esc_html( $grade ); ?>
                </div>
                <div style="text-align:left;">
                    <div style="font-size:32px;font-weight:700;color:#1a202c;line-height:1;"><?php echo esc_html( intval( $score ) ); ?><span style="font-size:16px;color:#718096;">/100</span></div>
                    <div style="font-size:12px;color:#718096;margin-top:2px;"><?php echo esc_html( $last_scan ); ?></div>
                </div>
            </div>
            <div style="display:flex;justify-content:center;gap:16px;font-size:13px;">
                <span style="color:#16a34a;">&#10004; <?php echo intval( $summary['pass'] ); ?> <?php esc_html_e( 'Passed', 'wpezo-site-inspector' ); ?></span>
                <span style="color:#ca8a04;">&#9888; <?php echo intval( $summary['warn'] ); ?> <?php esc_html_e( 'Warnings', 'wpezo-site-inspector' ); ?></span>
                <span style="color:#dc2626;">&#10008; <?php echo intval( $summary['fail'] ); ?> <?php esc_html_e( 'Failed', 'wpezo-site-inspector' ); ?></span>
            </div>
        </div>
        <p style="text-align:center;margin-top:8px;">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpezo-site-inspector' ) ); ?>" class="button button-primary">
                <?php esc_html_e( 'View Full Report', 'wpezo-site-inspector' ); ?>
            </a>
        </p>
        <p style="text-align:center;font-size:11px;color:#a0aec0;margin-top:6px;">
            Powered by <a href="https://wpezo.com" target="_blank" rel="noopener" style="color:#3182ce;text-decoration:none;">WPezo</a>
        </p>
        <?php
    }
}
