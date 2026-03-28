<?php
namespace WPSI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pro Checks email unlock gate with Resend Contacts + Segments API.
 *
 * Security layers:
 * - Double opt-in (confirmation email)
 * - HMAC-signed unlock data (tamper-proof)
 * - Server-side segment check (anti-abuse: 1 email = 1 site)
 * - Persistent unlock (survives uninstall/reinstall on same site)
 * - Disposable email blocking + MX validation
 * - Deep integration (removing breaks scanner)
 */
class Email_Gate {

    private static $instance = null;

    /* ── Option Keys ─────────────────────────────────────────────── */

    /**
     * Persistent unlock — NOT deleted on uninstall.
     * Stored with HMAC signature tied to this specific site.
     */
    const OPT_UNLOCK = 'wpsi_pro_unlock_v2';

    /** Pending confirmation tokens (temporary). */
    const OPT_PENDING = 'wpsi_pending_tokens';

    /** Token expiration: 24 hours. */
    const TOKEN_TTL = 86400;

    /* ── Resend Config ───────────────────────────────────────────── */

    const SENDER_EMAIL = 'no-reply@wpezo.com';
    const SENDER_NAME  = 'WPezo Site Inspector';
    const SEGMENT_ID   = '63974af0-b582-4ad8-b770-1682342ac44a';
    const API_BASE     = 'https://api.resend.com';

    /** Proxy endpoint on wpezo.com — handles contacts/segments securely. */
    const PROXY_URL    = 'https://wpezo.com/wp-api/site-inspector/';
    const PROXY_SECRET = 'wpsi_proxy_8f3a9c2d1e7b4k6m5n0p';

    /**
     * Resend API key — SENDING ACCESS ONLY.
     * Can only send emails. Cannot read/create/delete contacts, domains, etc.
     * Obfuscated to deter casual scraping.
     */
    private static function api_key() {
        return implode( '', array(
            base64_decode( 'cmVfSjV2QXk1' ),
            base64_decode( 'Mjhf' ),
            base64_decode( 'OFc2UzFOMXhC' ),
            base64_decode( 'cExYZXd4cnJF' ),
            base64_decode( 'eXJuWlA5' ),
        ) );
    }

    /* ── Singleton ───────────────────────────────────────────────── */

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
    }

    /* ══════════════════════════════════════════════════════════════
     *  UNLOCK VERIFICATION — used by Scanner
     * ════════════════════════════════════════════════════════════ */

    /**
     * Check if pro checks are unlocked for THIS site.
     * Called by Scanner and Admin — deeply integrated.
     *
     * @return bool
     */
    public static function is_unlocked() {
        $data = get_option( self::OPT_UNLOCK, null );
        if ( empty( $data ) || ! is_array( $data ) ) {
            return false;
        }

        // Must have required fields.
        if ( empty( $data['email'] ) || empty( $data['sig'] ) || empty( $data['site_hash'] ) ) {
            return false;
        }

        // Verify HMAC signature (tamper-proof).
        $expected_sig = self::compute_signature( $data['email'], $data['site_hash'] );
        if ( ! hash_equals( $expected_sig, $data['sig'] ) ) {
            return false;
        }

        // Verify site hash matches THIS site.
        if ( $data['site_hash'] !== self::site_hash() ) {
            return false;
        }

        return true;
    }

    /**
     * Generate a unique, deterministic hash for this WordPress installation.
     * Based on site URL + AUTH_KEY (unique per install).
     */
    private static function site_hash() {
        $site_url = home_url();
        $secret   = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'wpsi-fallback-' . md5( ABSPATH );
        return hash_hmac( 'sha256', $site_url, $secret );
    }

    /**
     * Compute HMAC signature for unlock data.
     */
    private static function compute_signature( $email, $site_hash ) {
        $secret = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'wpsi-sig-' . md5( ABSPATH . DB_NAME );
        return hash_hmac( 'sha256', strtolower( $email ) . '|' . $site_hash, $secret );
    }

    /**
     * Store verified unlock — HMAC-signed and site-bound.
     */
    private static function store_unlock( $email, $name ) {
        $site_hash = self::site_hash();
        $sig       = self::compute_signature( $email, $site_hash );

        update_option( self::OPT_UNLOCK, array(
            'email'        => strtolower( $email ),
            'name'         => $name,
            'site_hash'    => $site_hash,
            'sig'          => $sig,
            'confirmed_at' => current_time( 'mysql' ),
            'v'            => 2,
        ), true ); // autoload = true for fast checks
    }

    /* ══════════════════════════════════════════════════════════════
     *  REST API ROUTES
     * ════════════════════════════════════════════════════════════ */

    public function register_routes() {
        // Step 1: Submit email.
        register_rest_route( 'wpsi/v1', '/unlock', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_submit' ),
            'permission_callback' => function () { return current_user_can( 'manage_options' ); },
            'args'                => array(
                'email'   => array( 'required' => true, 'sanitize_callback' => 'sanitize_email', 'validate_callback' => array( $this, 'validate_email' ) ),
                'name'    => array( 'required' => false, 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ),
                'consent' => array( 'required' => true, 'validate_callback' => function ( $v ) { return (bool) $v; } ),
            ),
        ) );

        // Step 2: Confirm link (public).
        register_rest_route( 'wpsi/v1', '/confirm', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_confirm' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'token' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
                'email' => array( 'required' => true, 'sanitize_callback' => 'sanitize_email' ),
            ),
        ) );

        // Status check.
        register_rest_route( 'wpsi/v1', '/unlock-status', array(
            'methods'             => 'GET',
            'callback'            => function () {
                return rest_ensure_response( array( 'unlocked' => self::is_unlocked() ) );
            },
            'permission_callback' => function () { return current_user_can( 'manage_options' ); },
        ) );
    }

    /* ══════════════════════════════════════════════════════════════
     *  EMAIL VALIDATION
     * ════════════════════════════════════════════════════════════ */

    public function validate_email( $email ) {
        $email = sanitize_email( $email );

        if ( ! is_email( $email ) ) {
            return new \WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'wpezo-site-inspector' ) );
        }

        // Block disposable domains.
        $blocked = array(
            'mailinator.com', 'guerrillamail.com', 'tempmail.com', 'throwaway.email',
            'yopmail.com', 'sharklasers.com', 'dispostable.com', 'trashmail.com',
            'temp-mail.org', '10minutemail.com', 'maildrop.cc', 'fakeinbox.com',
            'guerrillamailblock.com', 'grr.la', 'mailnesia.com', 'tempail.com',
            'mohmal.com', 'getnada.com', 'emailondeck.com', 'incognitomail.com',
        );
        $domain = strtolower( substr( strrchr( $email, '@' ), 1 ) );
        if ( in_array( $domain, $blocked, true ) ) {
            return new \WP_Error( 'disposable', __( 'Disposable email addresses are not allowed. Please use your real email.', 'wpezo-site-inspector' ) );
        }

        // MX record check.
        if ( function_exists( 'checkdnsrr' ) && ! checkdnsrr( $domain, 'MX' ) && ! checkdnsrr( $domain, 'A' ) ) {
            return new \WP_Error( 'bad_domain', __( 'This email domain does not exist.', 'wpezo-site-inspector' ) );
        }

        return true;
    }

    /* ══════════════════════════════════════════════════════════════
     *  STEP 1: HANDLE EMAIL SUBMISSION
     * ════════════════════════════════════════════════════════════ */

    public function handle_submit( $request ) {
        $email = strtolower( $request->get_param( 'email' ) );
        $name  = $request->get_param( 'name' );

        if ( ! $request->get_param( 'consent' ) ) {
            return new \WP_Error( 'consent', __( 'You must agree to receive updates.', 'wpezo-site-inspector' ), array( 'status' => 400 ) );
        }

        // Already unlocked on THIS site?
        if ( self::is_unlocked() ) {
            $scanner = new Scanner();
            return rest_ensure_response( array(
                'success' => true, 'confirmed' => true,
                'pro_results' => $scanner->run_pro_checks(),
            ) );
        }

        // ── Anti-abuse: check if email already used on ANOTHER site ──
        $segment_check = $this->is_email_in_segment( $email );
        if ( true === $segment_check ) {
            return new \WP_Error(
                'already_used',
                __( 'This email has already been used to unlock Advanced Checks on another site. Please use a different email address.', 'wpezo-site-inspector' ),
                array( 'status' => 409 )
            );
        }

        // Generate secure token.
        $token = wp_generate_password( 40, false, false );

        // Store pending (clean expired first).
        $pending = get_option( self::OPT_PENDING, array() );
        foreach ( $pending as $t => $d ) {
            if ( ( time() - $d['ts'] ) > self::TOKEN_TTL ) unset( $pending[ $t ] );
        }
        $pending[ $token ] = array(
            'email' => $email,
            'name'  => $name,
            'ts'    => time(),
        );
        update_option( self::OPT_PENDING, $pending, false );

        // Build confirmation URL.
        $confirm_url = add_query_arg( array(
            'token' => $token,
            'email' => rawurlencode( $email ),
        ), rest_url( 'wpsi/v1/confirm' ) );

        // Send confirmation email via Resend.
        $sent = $this->send_confirmation( $email, $name, $confirm_url );
        if ( is_wp_error( $sent ) ) {
            return new \WP_Error( 'send_failed', __( 'Could not send confirmation email. Please try again in a few minutes.', 'wpezo-site-inspector' ), array( 'status' => 500 ) );
        }

        return rest_ensure_response( array(
            'success'       => true,
            'confirmed'     => false,
            'pending'       => true,
            'message'       => __( 'Confirmation email sent! Check your inbox and click the link to unlock.', 'wpezo-site-inspector' ),
            'email_sent_to' => self::mask( $email ),
        ) );
    }

    /* ══════════════════════════════════════════════════════════════
     *  STEP 2: HANDLE CONFIRMATION CLICK
     * ════════════════════════════════════════════════════════════ */

    public function handle_confirm( $request ) {
        $token = $request->get_param( 'token' );
        $email = strtolower( $request->get_param( 'email' ) );
        $base  = admin_url( 'admin.php?page=wpezo-site-inspector' );

        $pending = get_option( self::OPT_PENDING, array() );

        // Token exists?
        if ( ! isset( $pending[ $token ] ) ) {
            wp_safe_redirect( $base . '&wpsi_msg=expired' );
            exit;
        }

        $data = $pending[ $token ];

        // Expired?
        if ( ( time() - $data['ts'] ) > self::TOKEN_TTL ) {
            unset( $pending[ $token ] );
            update_option( self::OPT_PENDING, $pending, false );
            wp_safe_redirect( $base . '&wpsi_msg=expired' );
            exit;
        }

        // Email match?
        if ( strtolower( $data['email'] ) !== $email ) {
            wp_safe_redirect( $base . '&wpsi_msg=invalid' );
            exit;
        }

        // ── Anti-abuse: one more check before unlocking ──
        if ( true === $this->is_email_in_segment( $email ) ) {
            unset( $pending[ $token ] );
            update_option( self::OPT_PENDING, $pending, false );
            wp_safe_redirect( $base . '&wpsi_msg=already_used' );
            exit;
        }

        // ✅ UNLOCK — store with HMAC signature.
        self::store_unlock( $email, $data['name'] );

        // Clean token.
        unset( $pending[ $token ] );
        update_option( self::OPT_PENDING, $pending, false );

        // Resend: create contact + add to segment via secure proxy.
        // The proxy handles the fallback if contact already exists.
        $this->resend_create_contact( $email, $data['name'] );

        // Send welcome email.
        $this->send_welcome( $email, $data['name'] );

        // Run pro scan immediately.
        $scanner = new Scanner();
        $results = $scanner->run_all();
        $results['pro_checks']   = $scanner->run_pro_checks();
        $results['pro_unlocked'] = true;
        update_option( 'wpsi_last_results', $results, false );
        History::record( $results );

        wp_safe_redirect( $base . '&wpsi_msg=success' );
        exit;
    }

    /* ══════════════════════════════════════════════════════════════
     *  ADMIN NOTICES
     * ════════════════════════════════════════════════════════════ */

    public function admin_notices() {
        $screen = get_current_screen();
        if ( ! $screen || 'toplevel_page_wpezo-site-inspector' !== $screen->id ) return;
        if ( ! isset( $_GET['wpsi_msg'] ) ) return; // phpcs:ignore

        $status = sanitize_text_field( wp_unslash( $_GET['wpsi_msg'] ) ); // phpcs:ignore
        $notices = array(
            'success'      => array( 'success', '🎉 Email confirmed! Advanced Diagnostics are now unlocked. Run a new scan to see results.' ),
            'expired'      => array( 'warning', '⏰ This confirmation link has expired. Please submit your email again.' ),
            'invalid'      => array( 'error',   '❌ Invalid confirmation link. Please try again.' ),
            'already_used' => array( 'error',   '⚠️ This email has already been used on another site. Please use a different email.' ),
        );
        if ( ! isset( $notices[ $status ] ) ) return;

        printf(
            '<div class="notice notice-%s is-dismissible"><p><strong>WPezo Site Inspector:</strong> %s</p></div>',
            esc_attr( $notices[ $status ][0] ),
            esc_html( $notices[ $status ][1] )
        );
    }

    /* ══════════════════════════════════════════════════════════════
     *  CONTACTS VIA SECURE PROXY (wpezo.com)
     *  The Full Access API key never leaves the server.
     * ════════════════════════════════════════════════════════════ */

    /**
     * Create a contact and add to segment via proxy.
     */
    private function resend_create_contact( $email, $name ) {
        $np = explode( ' ', $name, 2 );
        return $this->proxy_request( 'create_contact', array(
            'email'      => strtolower( $email ),
            'first_name' => isset( $np[0] ) ? $np[0] : '',
            'last_name'  => isset( $np[1] ) ? $np[1] : '',
        ) );
    }

    /**
     * Check if an email is already in the segment via proxy.
     * Returns true if found, false if not.
     */
    private function is_email_in_segment( $email ) {
        $result = $this->proxy_request( 'check_contact', array(
            'email' => strtolower( $email ),
        ) );

        if ( is_wp_error( $result ) ) {
            // On proxy error, allow the request (fail open for UX).
            return false;
        }

        return ! empty( $result['in_segment'] );
    }

    /**
     * Make an authenticated request to the wpezo.com proxy.
     *
     * @param string $action  create_contact | check_contact
     * @param array  $body    Request payload
     * @return array|\WP_Error
     */
    private function proxy_request( $action, $body ) {
        $url = self::PROXY_URL . '?action=' . rawurlencode( $action );

        $response = wp_remote_post( $url, array(
            'timeout'   => 30,
            'headers'   => array(
                'Content-Type'  => 'application/json',
                'X-WPSI-Token'  => self::PROXY_SECRET,
                'User-Agent'    => 'WPezo-Site-Inspector/' . WPSI_VERSION,
            ),
            'body'      => wp_json_encode( $body ),
            'sslverify' => true,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code    = wp_remote_retrieve_response_code( $response );
        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 200 && $code < 300 && is_array( $decoded ) ) {
            return $decoded;
        }

        $msg = isset( $decoded['error'] ) ? $decoded['error'] : "Proxy error (HTTP {$code})";
        return new \WP_Error( 'proxy_error', $msg, array( 'status' => $code ) );
    }

    /* ══════════════════════════════════════════════════════════════
     *  RESEND API: EMAILS
     * ════════════════════════════════════════════════════════════ */

    private function send_confirmation( $email, $name, $url ) {
        $fn = $name ? explode( ' ', $name )[0] : '';
        $hi = $fn ? "Hi {$fn}," : 'Hi there,';

        return $this->resend_request( 'POST', '/emails', array(
            'from'    => self::SENDER_NAME . ' <' . self::SENDER_EMAIL . '>',
            'to'      => array( $email ),
            'subject' => '🔓 Confirm your email — WPezo Site Inspector',
            'html'    => $this->tpl(
                'Confirm Your Email',
                $hi,
                'You\'re one click away from unlocking <strong>8 Advanced Diagnostics</strong> in WPezo Site Inspector — including mixed content detection, login security audit, backup status, and more.',
                $url,
                '✅ Confirm & Unlock Advanced Checks',
                'This link expires in 24 hours and can only be used once. If you didn\'t request this, ignore this email.'
            ),
        ) );
    }

    private function send_welcome( $email, $name ) {
        $fn = $name ? explode( ' ', $name )[0] : '';
        $hi = $fn ? "Welcome {$fn}!" : 'Welcome!';

        return $this->resend_request( 'POST', '/emails', array(
            'from'    => self::SENDER_NAME . ' <' . self::SENDER_EMAIL . '>',
            'to'      => array( $email ),
            'subject' => '🎉 Advanced Checks unlocked! — WPezo Site Inspector',
            'html'    => $this->tpl(
                'Advanced Checks Unlocked!',
                $hi,
                'Your <strong>8 Advanced Diagnostic checks</strong> are now unlocked! Run a new scan to see detailed results.<br><br>As a WPezo community member, you\'ll receive occasional WordPress tips and early access to our upcoming premium plugins.',
                'https://www.youtube.com/@WPezo',
                '🎬 Watch Free WordPress Tutorials',
                'You\'re receiving this because you unlocked Advanced Checks in WPezo Site Inspector.'
            ),
        ) );
    }

    /* ══════════════════════════════════════════════════════════════
     *  RESEND API: HTTP CLIENT
     * ════════════════════════════════════════════════════════════ */

    /**
     * Make a request to the Resend API.
     *
     * @param string $method  GET, POST, PATCH, DELETE
     * @param string $path    e.g. /emails, /contacts, /contacts/segments
     * @param array  $body    Request body (for POST/PATCH)
     * @return array|\WP_Error  Decoded response or error
     */
    private function resend_request( $method, $path, $body = array() ) {
        $url = self::API_BASE . $path;

        $args = array(
            'method'    => $method,
            'timeout'   => 30,
            'headers'   => array(
                'Authorization' => 'Bearer ' . self::api_key(),
                'Content-Type'  => 'application/json',
                'User-Agent'    => 'WPezo-Site-Inspector/' . WPSI_VERSION . ' WordPress/' . get_bloginfo( 'version' ),
            ),
            'sslverify' => true,
        );

        if ( in_array( $method, array( 'POST', 'PATCH' ), true ) && ! empty( $body ) ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $resp_body = wp_remote_retrieve_body( $response );
        $decoded   = json_decode( $resp_body, true );

        if ( $code >= 200 && $code < 300 ) {
            return is_array( $decoded ) ? $decoded : array();
        }

        $msg = isset( $decoded['message'] ) ? $decoded['message'] : "HTTP {$code}";
        return new \WP_Error( 'resend_api', $msg, array( 'status' => $code ) );
    }

    /* ══════════════════════════════════════════════════════════════
     *  EMAIL TEMPLATE
     * ════════════════════════════════════════════════════════════ */

    private function tpl( $title, $greeting, $body, $cta_url, $cta_label, $footer ) {
        $yr = gmdate( 'Y' );
        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>' . esc_html( $title ) . '</title></head>
<body style="margin:0;padding:0;background:#f4f4f7;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f7;padding:40px 20px;"><tr><td align="center">
<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
<tr><td style="background:linear-gradient(135deg,#1a365d,#2b6cb0);padding:28px 40px;text-align:center;">
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto;"><tr>
<td style="width:32px;height:32px;background:#fff;border-radius:7px;text-align:center;vertical-align:middle;font-size:16px;font-weight:bold;color:#2b6cb0;">W</td>
<td style="padding-left:10px;font-size:18px;font-weight:700;color:#fff;letter-spacing:0.5px;">WPezo</td>
</tr></table></td></tr>
<tr><td style="padding:36px 40px;">
<p style="font-size:18px;font-weight:700;color:#1a202c;margin:0 0 14px;">' . esc_html( $greeting ) . '</p>
<p style="font-size:14px;color:#4a5568;line-height:1.7;margin:0 0 24px;">' . $body . '</p>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 24px;"><tr><td style="background:#2b6cb0;border-radius:8px;">
<a href="' . esc_url( $cta_url ) . '" style="display:inline-block;padding:13px 32px;font-size:14px;font-weight:700;color:#fff;text-decoration:none;border-radius:8px;">' . esc_html( $cta_label ) . '</a>
</td></tr></table>
<p style="font-size:11px;color:#a0aec0;line-height:1.6;margin:0;text-align:center;">' . esc_html( $footer ) . '</p>
</td></tr>
<tr><td style="background:#f7fafc;padding:20px 40px;border-top:1px solid #e2e8f0;text-align:center;">
<p style="font-size:11px;color:#a0aec0;margin:0 0 6px;"><a href="https://wpezo.com" style="color:#3182ce;text-decoration:none;">wpezo.com</a> &middot; <a href="https://www.youtube.com/@WPezo" style="color:#3182ce;text-decoration:none;">YouTube</a></p>
<p style="font-size:10px;color:#cbd5e0;margin:0;">&copy; ' . $yr . ' WPezo. All rights reserved.</p>
</td></tr></table></td></tr></table></body></html>';
    }

    /* ── Mask email for display ──────────────────────────────────── */

    private static function mask( $email ) {
        $p = explode( '@', $email );
        if ( count( $p ) !== 2 ) return '***';
        $l = $p[0];
        return substr( $l, 0, 2 ) . str_repeat( '*', max( strlen( $l ) - 2, 3 ) ) . '@' . $p[1];
    }
}
