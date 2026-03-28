<?php
namespace WPSI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST API endpoints.
 */
class Rest_API {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route( 'wpsi/v1', '/scan', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'run_scan' ),
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ) );

        register_rest_route( 'wpsi/v1', '/results', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_results' ),
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ) );
    }

    public function run_scan( $request ) {
        // Increase time limit for scan — required because scans perform multiple HTTP requests.
        if ( function_exists( 'set_time_limit' ) ) {
            set_time_limit( 120 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Needed to prevent timeout during full site scan.
        }

        $scanner = new Scanner();
        $results = $scanner->run_all();

        // Add pro checks data.
        if ( Email_Gate::is_unlocked() ) {
            $results['pro_checks'] = $scanner->run_pro_checks();
            $results['pro_unlocked'] = true;
        } else {
            $results['pro_checks'] = $scanner->get_pro_checks_preview();
            $results['pro_unlocked'] = false;
        }

        update_option( 'wpsi_last_results', $results, false );
        update_option( 'wpsi_last_scan_time', current_time( 'mysql' ), false );

        // Record in history.
        History::record( $results );

        return rest_ensure_response( $results );
    }

    public function get_results( $request ) {
        $results = get_option( 'wpsi_last_results' );
        if ( ! $results ) {
            return rest_ensure_response( array( 'empty' => true ) );
        }
        return rest_ensure_response( $results );
    }
}
