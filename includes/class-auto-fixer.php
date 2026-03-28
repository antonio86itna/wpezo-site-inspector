<?php

namespace WPSI;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Auto-Fixer — Premium feature.
 *
 * Provides one-click automatic fixes for common WordPress issues.
 * Only available with an active Freemius paid plan.
 * All fixes are non-destructive and reversible.
 *
 * @package WPSI
 */
class Auto_Fixer {
    /** @var Auto_Fixer|null */
    private static $instance = null;

    /**
     * Singleton.
     *
     * @return Auto_Fixer
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        add_action( 'rest_api_init', array($this, 'register_routes') );
    }

    /**
     * Check if user has active premium plan via Freemius.
     *
     * @return bool
     */
    public static function is_premium() {
        if ( !function_exists( 'wsi_fs' ) ) {
            return false;
        }
        return false;
    }

    /**
     * Get list of fixable check IDs.
     *
     * @return array<string, string>
     */
    public static function get_fixable_checks() {
        return array(
            'file_editor'        => __( 'Disable File Editor', 'wpezo-site-inspector' ),
            'debug_mode'         => __( 'Disable Debug Display', 'wpezo-site-inspector' ),
            'post_revisions'     => __( 'Limit Post Revisions', 'wpezo-site-inspector' ),
            'expired_transients' => __( 'Clean Expired Transients', 'wpezo-site-inspector' ),
            'spam_comments'      => __( 'Delete Spam Comments', 'wpezo-site-inspector' ),
            'trash_posts'        => __( 'Empty Trash', 'wpezo-site-inspector' ),
            'auto_drafts'        => __( 'Clean Auto-Drafts', 'wpezo-site-inspector' ),
            'orphaned_meta'      => __( 'Clean Orphaned Metadata', 'wpezo-site-inspector' ),
            'db_overhead'        => __( 'Optimize Database Tables', 'wpezo-site-inspector' ),
            'security_headers'   => __( 'Add Security Headers', 'wpezo-site-inspector' ),
            'xmlrpc'             => __( 'Disable XML-RPC', 'wpezo-site-inspector' ),
            'tagline'            => __( 'Clear Default Tagline', 'wpezo-site-inspector' ),
        );
    }

    /**
     * Register REST routes.
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route( 'wpsi/v1', '/fix', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_fix'),
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args'                => array(
                'check_id' => array(
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_key',
                ),
            ),
        ) );
    }

    /**
     * Handle a fix request.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_fix( $request ) {
        if ( !self::is_premium() ) {
            return new \WP_Error('premium_required', __( 'Auto-Fix requires a Pro license. Upgrade to unlock one-click fixes.', 'wpezo-site-inspector' ), array(
                'status' => 403,
            ));
        }
        return new \WP_Error('premium_required', __( 'Auto-Fix requires a Pro license.', 'wpezo-site-inspector' ), array(
            'status' => 403,
        ));
    }

}
