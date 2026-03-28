<?php
namespace WPSI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tracks score history over time.
 */
class History {

    const OPTION_KEY  = 'wpsi_score_history';
    const MAX_ENTRIES = 30;

    /**
     * Record a scan result in history.
     */
    public static function record( $results ) {
        $history = get_option( self::OPTION_KEY, array() );

        $entry = array(
            'date'    => current_time( 'Y-m-d H:i' ),
            'score'   => isset( $results['score'] ) ? intval( $results['score'] ) : 0,
            'grade'   => isset( $results['grade'] ) ? $results['grade'] : 'F',
            'summary' => isset( $results['summary'] ) ? $results['summary'] : array(),
        );

        $history[] = $entry;

        // Keep only last N entries.
        if ( count( $history ) > self::MAX_ENTRIES ) {
            $history = array_slice( $history, -self::MAX_ENTRIES );
        }

        update_option( self::OPTION_KEY, $history, false );
    }

    /**
     * Get score history.
     */
    public static function get_all() {
        return get_option( self::OPTION_KEY, array() );
    }

    /**
     * Register REST route.
     */
    public static function register_routes() {
        register_rest_route( 'wpsi/v1', '/history', array(
            'methods'             => 'GET',
            'callback'            => function () {
                return rest_ensure_response( self::get_all() );
            },
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ) );
    }
}
