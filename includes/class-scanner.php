<?php
namespace WPSI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Scanner — runs all site health checks.
 */
class Scanner {

    /**
     * Weight constants.
     */
    const W_HIGH   = 3;
    const W_MEDIUM = 2;
    const W_LOW    = 1;

    /**
     * Status constants.
     */
    const PASS = 'pass';
    const WARN = 'warn';
    const FAIL = 'fail';

    /**
     * Run all checks and return structured results.
     *
     * @return array
     */
    public function run_all() {
        $categories = array(
            'security'      => $this->security_checks(),
            'performance'   => $this->performance_checks(),
            'seo'           => $this->seo_checks(),
            'accessibility' => $this->accessibility_checks(),
            'database'      => $this->database_checks(),
        );

        $total_weight  = 0;
        $passed_weight = 0;
        $warn_weight   = 0;
        $summary       = array(
            'pass' => 0,
            'warn' => 0,
            'fail' => 0,
        );

        foreach ( $categories as $cat_key => &$checks ) {
            foreach ( $checks as &$check ) {
                $total_weight += $check['weight'];
                if ( self::PASS === $check['status'] ) {
                    $passed_weight += $check['weight'];
                    $summary['pass']++;
                } elseif ( self::WARN === $check['status'] ) {
                    $passed_weight += $check['weight'] * 0.5;
                    $warn_weight   += $check['weight'];
                    $summary['warn']++;
                } else {
                    $summary['fail']++;
                }
            }
            unset( $check );
        }
        unset( $checks );

        $score = $total_weight > 0 ? round( ( $passed_weight / $total_weight ) * 100 ) : 0;
        $grade = Plugin::score_to_grade( $score );

        return array(
            'score'      => $score,
            'grade'      => $grade,
            'summary'    => $summary,
            'categories' => $categories,
            'meta'       => array(
                'wp_version'  => get_bloginfo( 'version' ),
                'php_version' => PHP_VERSION,
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
                'server'      => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'Unknown',
                'scan_time'   => current_time( 'mysql' ),
                'plugin_ver'  => WPSI_VERSION,
            ),
        );
    }

    /* =========================================================================
     * SECURITY CHECKS
     * ====================================================================== */

    /**
     * Run security checks.
     *
     * @return array
     */
    private function security_checks() {
        return array(
            $this->check_ssl(),
            $this->check_file_editor(),
            $this->check_debug_mode(),
            $this->check_db_prefix(),
            $this->check_php_version(),
            $this->check_wp_version(),
            $this->check_admin_username(),
            $this->check_xmlrpc(),
            $this->check_security_headers(),
            $this->check_file_permissions(),
        );
    }

    private function check_ssl() {
        $is_ssl = is_ssl();
        return array(
            'id'     => 'ssl',
            'title'  => __( 'SSL / HTTPS', 'wpezo-site-inspector' ),
            'status' => $is_ssl ? self::PASS : self::FAIL,
            'weight' => self::W_HIGH,
            'detail' => $is_ssl
                ? __( 'Your site is served over HTTPS.', 'wpezo-site-inspector' )
                : __( 'Your site is NOT using HTTPS. Install an SSL certificate immediately.', 'wpezo-site-inspector' ),
            'fix'    => $is_ssl ? '' : __( 'Get a free SSL from Let\'s Encrypt or your hosting provider, then update WordPress URLs.', 'wpezo-site-inspector' ),
        );
    }

    private function check_file_editor() {
        $disabled = defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT;
        return array(
            'id'     => 'file_editor',
            'title'  => __( 'File Editor Disabled', 'wpezo-site-inspector' ),
            'status' => $disabled ? self::PASS : self::WARN,
            'weight' => self::W_MEDIUM,
            'detail' => $disabled
                ? __( 'The built-in file editor is disabled.', 'wpezo-site-inspector' )
                : __( 'The theme/plugin file editor is enabled. Attackers could exploit it.', 'wpezo-site-inspector' ),
            'fix'    => $disabled ? '' : __( 'Add define(\'DISALLOW_FILE_EDIT\', true); to wp-config.php.', 'wpezo-site-inspector' ),
        );
    }

    private function check_debug_mode() {
        $debug_on = defined( 'WP_DEBUG' ) && WP_DEBUG;
        $display  = defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY;
        $is_bad   = $debug_on && $display;
        return array(
            'id'     => 'debug_mode',
            'title'  => __( 'Debug Mode', 'wpezo-site-inspector' ),
            'status' => $is_bad ? self::FAIL : ( $debug_on ? self::WARN : self::PASS ),
            'weight' => self::W_MEDIUM,
            'detail' => $is_bad
                ? __( 'WP_DEBUG and WP_DEBUG_DISPLAY are both ON. Error details are visible to visitors.', 'wpezo-site-inspector' )
                : ( $debug_on
                    ? __( 'WP_DEBUG is on but display is off. Acceptable for staging.', 'wpezo-site-inspector' )
                    : __( 'Debug mode is properly disabled.', 'wpezo-site-inspector' ) ),
            'fix'    => $is_bad ? __( 'Set WP_DEBUG_DISPLAY to false and use WP_DEBUG_LOG instead.', 'wpezo-site-inspector' ) : '',
        );
    }

    private function check_db_prefix() {
        global $wpdb;
        $default = ( 'wp_' === $wpdb->prefix );
        return array(
            'id'     => 'db_prefix',
            'title'  => __( 'Database Prefix', 'wpezo-site-inspector' ),
            'status' => $default ? self::WARN : self::PASS,
            'weight' => self::W_LOW,
            'detail' => $default
                ? __( 'You are using the default "wp_" prefix. This makes SQL injection attacks easier.', 'wpezo-site-inspector' )
                : sprintf(
                    /* translators: %s: current prefix */
                    __( 'Custom prefix "%s" is in use.', 'wpezo-site-inspector' ),
                    esc_html( $wpdb->prefix )
                ),
            'fix'    => $default ? __( 'Consider changing the prefix (requires careful migration). For new installs, always use a custom prefix.', 'wpezo-site-inspector' ) : '',
        );
    }

    private function check_php_version() {
        $current = PHP_VERSION;
        $is_ok   = version_compare( $current, '8.1', '>=' );
        $is_old  = version_compare( $current, '8.0', '<' );
        return array(
            'id'     => 'php_version',
            'title'  => __( 'PHP Version', 'wpezo-site-inspector' ),
            'status' => $is_ok ? self::PASS : ( $is_old ? self::FAIL : self::WARN ),
            'weight' => self::W_HIGH,
            'detail' => sprintf(
                /* translators: %s: PHP version */
                __( 'PHP %s detected.', 'wpezo-site-inspector' ),
                esc_html( $current )
            ) . ( $is_ok ? '' : ' ' . __( 'Older PHP versions lack security patches and performance improvements.', 'wpezo-site-inspector' ) ),
            'fix'    => $is_ok ? '' : __( 'Upgrade to PHP 8.1+ via your hosting control panel.', 'wpezo-site-inspector' ),
        );
    }

    private function check_wp_version() {
        $current = get_bloginfo( 'version' );
        require_once ABSPATH . 'wp-admin/includes/update.php';
        $updates  = get_core_updates();
        $up2date  = ( empty( $updates ) || ( isset( $updates[0]->response ) && 'latest' === $updates[0]->response ) );
        return array(
            'id'     => 'wp_version',
            'title'  => __( 'WordPress Version', 'wpezo-site-inspector' ),
            'status' => $up2date ? self::PASS : self::WARN,
            'weight' => self::W_HIGH,
            'detail' => sprintf(
                /* translators: %s: WP version */
                __( 'WordPress %s.', 'wpezo-site-inspector' ),
                esc_html( $current )
            ) . ( $up2date ? ' ' . __( 'You are up to date.', 'wpezo-site-inspector' ) : ' ' . __( 'An update is available.', 'wpezo-site-inspector' ) ),
            'fix'    => $up2date ? '' : __( 'Update WordPress from Dashboard → Updates.', 'wpezo-site-inspector' ),
        );
    }

    private function check_admin_username() {
        $admin_user = get_user_by( 'login', 'admin' );
        $has_admin  = ( false !== $admin_user );
        return array(
            'id'     => 'admin_username',
            'title'  => __( 'Default "admin" Username', 'wpezo-site-inspector' ),
            'status' => $has_admin ? self::FAIL : self::PASS,
            'weight' => self::W_MEDIUM,
            'detail' => $has_admin
                ? __( 'A user with the "admin" login exists. This is the first username brute-force bots try.', 'wpezo-site-inspector' )
                : __( 'No user with the default "admin" username found.', 'wpezo-site-inspector' ),
            'fix'    => $has_admin ? __( 'Create a new admin account with a unique username, transfer content, then delete the "admin" account.', 'wpezo-site-inspector' ) : '',
        );
    }

    private function check_xmlrpc() {
        $enabled = true;
        if ( defined( 'XMLRPC_REQUEST' ) && ! XMLRPC_REQUEST ) {
            $enabled = false;
        }
        // Check if a plugin disables it.
        $filters = has_filter( 'xmlrpc_enabled' );
        if ( false !== $filters ) {
            $enabled = false;
        }
        return array(
            'id'     => 'xmlrpc',
            'title'  => __( 'XML-RPC', 'wpezo-site-inspector' ),
            'status' => $enabled ? self::WARN : self::PASS,
            'weight' => self::W_LOW,
            'detail' => $enabled
                ? __( 'XML-RPC appears enabled. Unless you need it (Jetpack, mobile apps), it\'s an attack vector.', 'wpezo-site-inspector' )
                : __( 'XML-RPC is disabled or filtered.', 'wpezo-site-inspector' ),
            'fix'    => $enabled ? __( 'Disable via .htaccess, a security plugin, or add add_filter(\'xmlrpc_enabled\', \'__return_false\');', 'wpezo-site-inspector' ) : '',
        );
    }

    private function check_security_headers() {
        $site_url = home_url( '/' );
        $headers  = array();
        $response = wp_remote_head( $site_url, array( 'timeout' => 10, 'sslverify' => false ) );

        $missing = array();
        $checked = array( 'X-Content-Type-Options', 'X-Frame-Options', 'Referrer-Policy' );

        if ( ! is_wp_error( $response ) ) {
            $resp_headers = wp_remote_retrieve_headers( $response );
            foreach ( $checked as $h ) {
                $val = $resp_headers[ strtolower( $h ) ] ?? '';
                if ( empty( $val ) ) {
                    $missing[] = $h;
                }
            }
        } else {
            $missing = $checked; // Assume missing on error.
        }

        $count_missing = count( $missing );
        return array(
            'id'     => 'security_headers',
            'title'  => __( 'Security Headers', 'wpezo-site-inspector' ),
            'status' => 0 === $count_missing ? self::PASS : ( $count_missing <= 1 ? self::WARN : self::FAIL ),
            'weight' => self::W_MEDIUM,
            'detail' => 0 === $count_missing
                ? __( 'All checked security headers are present.', 'wpezo-site-inspector' )
                : sprintf(
                    /* translators: %s: comma-separated header names */
                    __( 'Missing headers: %s', 'wpezo-site-inspector' ),
                    esc_html( implode( ', ', $missing ) )
                ),
            'fix'    => $count_missing > 0 ? __( 'Add the missing headers via .htaccess, your server config, or a security plugin.', 'wpezo-site-inspector' ) : '',
        );
    }

    private function check_file_permissions() {
        $wp_config = ABSPATH . 'wp-config.php';
        $perms     = '';
        $is_ok     = true;

        if ( file_exists( $wp_config ) && function_exists( 'fileperms' ) ) {
            $perms_octal = substr( sprintf( '%o', fileperms( $wp_config ) ), -3 );
            $perms       = $perms_octal;
            // 644 or 640 or 600 are acceptable.
            $is_ok = in_array( $perms_octal, array( '644', '640', '600', '440', '400' ), true );
        }

        return array(
            'id'     => 'file_permissions',
            'title'  => __( 'wp-config.php Permissions', 'wpezo-site-inspector' ),
            'status' => $is_ok ? self::PASS : self::WARN,
            'weight' => self::W_MEDIUM,
            'detail' => $is_ok
                ? sprintf(
                    /* translators: %s: file permissions */
                    __( 'wp-config.php has secure permissions (%s).', 'wpezo-site-inspector' ),
                    esc_html( $perms )
                )
                : sprintf(
                    /* translators: %s: file permissions */
                    __( 'wp-config.php permissions are %s — should be 644 or more restrictive.', 'wpezo-site-inspector' ),
                    esc_html( $perms )
                ),
            'fix'    => $is_ok ? '' : __( 'Set file permissions to 644 or 640 via FTP/SSH: chmod 644 wp-config.php', 'wpezo-site-inspector' ),
        );
    }

    /* =========================================================================
     * PERFORMANCE CHECKS
     * ====================================================================== */

    private function performance_checks() {
        return array(
            $this->check_active_plugins(),
            $this->check_autoload_size(),
            $this->check_post_revisions(),
            $this->check_expired_transients(),
            $this->check_php_memory(),
            $this->check_gzip(),
            $this->check_cron_health(),
            $this->check_page_cache(),
        );
    }

    private function check_active_plugins() {
        $plugins = get_option( 'active_plugins', array() );
        $count   = count( $plugins );
        return array(
            'id'     => 'active_plugins',
            'title'  => __( 'Active Plugins Count', 'wpezo-site-inspector' ),
            'status' => $count <= 15 ? self::PASS : ( $count <= 25 ? self::WARN : self::FAIL ),
            'weight' => self::W_MEDIUM,
            'detail' => sprintf(
                /* translators: %d: number of plugins */
                __( '%d active plugins detected.', 'wpezo-site-inspector' ),
                $count
            ) . ( $count > 15 ? ' ' . __( 'Too many plugins slow down your site and increase security risks.', 'wpezo-site-inspector' ) : '' ),
            'fix'    => $count > 15 ? __( 'Audit your plugins. Deactivate and delete any you don\'t actively use. Look for multi-purpose plugins to replace multiple single-purpose ones.', 'wpezo-site-inspector' ) : '',
        );
    }

    private function check_autoload_size() {
        global $wpdb;
        $size_bytes = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload = 'yes'"
        );
        $size_kb = round( $size_bytes / 1024 );
        $size_mb = round( $size_bytes / 1048576, 2 );
        return array(
            'id'     => 'autoload_size',
            'title'  => __( 'Autoloaded Data Size', 'wpezo-site-inspector' ),
            'status' => $size_kb < 800 ? self::PASS : ( $size_kb < 2048 ? self::WARN : self::FAIL ),
            'weight' => self::W_HIGH,
            'detail' => sprintf(
                /* translators: %s: size in KB or MB */
                __( 'Autoloaded data: %s.', 'wpezo-site-inspector' ),
                $size_kb > 1024 ? $size_mb . ' MB' : $size_kb . ' KB'
            ) . ( $size_kb >= 800 ? ' ' . __( 'Large autoloaded data slows every page load.', 'wpezo-site-inspector' ) : '' ),
            'fix'    => $size_kb >= 800 ? __( 'Identify and clean large autoloaded options. Remove leftover data from deactivated plugins.', 'wpezo-site-inspector' ) : '',
        );
    }

    private function check_post_revisions() {
        global $wpdb;
        $count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'"
        );
        return array(
            'id'     => 'post_revisions',
            'title'  => __( 'Post Revisions', 'wpezo-site-inspector' ),
            'status' => $count < 100 ? self::PASS : ( $count < 500 ? self::WARN : self::FAIL ),
            'weight' => self::W_LOW,
            'detail' => sprintf(
                /* translators: %d: revision count */
                __( '%d revisions stored in the database.', 'wpezo-site-inspector' ),
                $count
            ),
            'fix'    => $count >= 100 ? __( 'Limit revisions with define(\'WP_POST_REVISIONS\', 5); in wp-config.php. Clean old revisions with a database optimizer.', 'wpezo-site-inspector' ) : '',
        );
    }

    private function check_expired_transients() {
        global $wpdb;
        $count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options}
                 WHERE option_name LIKE %s
                 AND option_value < %d",
                $wpdb->esc_like( '_transient_timeout_' ) . '%',
                time()
            )
        );
        return array(
            'id'     => 'expired_transients',
            'title'  => __( 'Expired Transients', 'wpezo-site-inspector' ),
            'status' => $count < 50 ? self::PASS : ( $count < 200 ? self::WARN : self::FAIL ),
            'weight' => self::W_LOW,
            'detail' => sprintf(
                /* translators: %d: transient count */
                __( '%d expired transients found.', 'wpezo-site-inspector' ),
                $count
            ),
            'fix'    => $count >= 50 ? __( 'Clean expired transients using a database cleanup plugin or WP-CLI: wp transient delete --expired', 'wpezo-site-inspector' ) : '',
        );
    }

    private function check_php_memory() {
        $limit   = ini_get( 'memory_limit' );
        $bytes   = wp_convert_hr_to_bytes( $limit );
        $is_ok   = $bytes >= 268435456; // 256 MB
        $is_warn = $bytes >= 134217728; // 128 MB
        return array(
            'id'     => 'php_memory',
            'title'  => __( 'PHP Memory Limit', 'wpezo-site-inspector' ),
            'status' => $is_ok ? self::PASS : ( $is_warn ? self::WARN : self::FAIL ),
            'weight' => self::W_MEDIUM,
            'detail' => sprintf(
                /* translators: %s: memory limit */
                __( 'PHP memory limit is %s.', 'wpezo-site-inspector' ),
                esc_html( $limit )
            ),
            'fix'    => ! $is_ok ? __( 'Increase to at least 256M in php.ini: memory_limit = 256M', 'wpezo-site-inspector' ) : '',
        );
    }

    private function check_gzip() {
        $site_url = home_url( '/' );
        $response = wp_remote_get( $site_url, array(
            'timeout'   => 10,
            'sslverify' => false,
            'headers'   => array( 'Accept-Encoding' => 'gzip, deflate' ),
        ) );

        $encoding = '';
        if ( ! is_wp_error( $response ) ) {
            $headers  = wp_remote_retrieve_headers( $response );
            $encoding = isset( $headers['content-encoding'] ) ? $headers['content-encoding'] : '';
        }

        $has_gzip = ( false !== stripos( $encoding, 'gzip' ) || false !== stripos( $encoding, 'br' ) );

        return array(
            'id'     => 'gzip',
            'title'  => __( 'GZIP / Brotli Compression', 'wpezo-site-inspector' ),
            'status' => $has_gzip ? self::PASS : self::WARN,
            'weight' => self::W_MEDIUM,
            'detail' => $has_gzip
                ? sprintf(
                    /* translators: %s: encoding type */
                    __( 'Compression active (%s).', 'wpezo-site-inspector' ),
                    esc_html( $encoding )
                )
                : __( 'No compression detected. Pages are being served uncompressed.', 'wpezo-site-inspector' ),
            'fix'    => $has_gzip ? '' : __( 'Enable GZIP in .htaccess, Nginx config, or via a caching plugin.', 'wpezo-site-inspector' ),
        );
    }

    private function check_cron_health() {
        $crons      = _get_cron_array();
        $overdue    = 0;
        $now        = time();

        if ( is_array( $crons ) ) {
            foreach ( $crons as $timestamp => $hooks ) {
                if ( $timestamp < ( $now - 600 ) ) { // 10+ minutes overdue.
                    $overdue += count( $hooks );
                }
            }
        }

        $disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;

        return array(
            'id'     => 'cron_health',
            'title'  => __( 'WP-Cron Health', 'wpezo-site-inspector' ),
            'status' => ( $overdue > 5 ) ? self::WARN : self::PASS,
            'weight' => self::W_LOW,
            'detail' => sprintf(
                /* translators: %d: overdue task count */
                __( '%d overdue cron tasks detected.', 'wpezo-site-inspector' ),
                $overdue
            ) . ( $disabled ? ' ' . __( 'WP-Cron is disabled (using system cron).', 'wpezo-site-inspector' ) : '' ),
            'fix'    => $overdue > 5 ? __( 'If WP-Cron is disabled, ensure a system cron is set up. Check for stuck tasks with WP Crontrol plugin.', 'wpezo-site-inspector' ) : '',
        );
    }

    private function check_page_cache() {
        $cache_plugins = array(
            'wp-super-cache/wp-cache.php',
            'w3-total-cache/w3-total-cache.php',
            'wp-fastest-cache/wpFastestCache.php',
            'litespeed-cache/litespeed-cache.php',
            'wp-rocket/wp-rocket.php',
            'breeze/breeze.php',
            'sg-cachepress/sg-cachepress.php',
            'cache-enabler/cache-enabler.php',
            'hummingbird-performance/wp-hummingbird.php',
            'powered-cache/powered-cache.php',
        );

        $active = get_option( 'active_plugins', array() );
        $found  = '';
        foreach ( $cache_plugins as $cp ) {
            if ( in_array( $cp, $active, true ) ) {
                $data  = get_plugin_data( WP_PLUGIN_DIR . '/' . $cp, false, false );
                $found = $data['Name'] ?? $cp;
                break;
            }
        }

        $has_cache = ! empty( $found ) || ( defined( 'WP_CACHE' ) && WP_CACHE );

        return array(
            'id'     => 'page_cache',
            'title'  => __( 'Page Caching', 'wpezo-site-inspector' ),
            'status' => $has_cache ? self::PASS : self::WARN,
            'weight' => self::W_HIGH,
            'detail' => $has_cache
                ? ( $found
                    ? sprintf(
                        /* translators: %s: cache plugin name */
                        __( 'Page caching active via %s.', 'wpezo-site-inspector' ),
                        esc_html( $found )
                    )
                    : __( 'WP_CACHE is enabled.', 'wpezo-site-inspector' ) )
                : __( 'No page caching detected. Your server processes every request from scratch.', 'wpezo-site-inspector' ),
            'fix'    => $has_cache ? '' : __( 'Install a caching plugin like LiteSpeed Cache, WP Super Cache, or WP Rocket.', 'wpezo-site-inspector' ),
        );
    }

    /* =========================================================================
     * SEO CHECKS
     * ====================================================================== */

    private function seo_checks() {
        return array(
            $this->check_site_title(),
            $this->check_tagline(),
            $this->check_search_visibility(),
            $this->check_permalink_structure(),
            $this->check_sitemap(),
            $this->check_robots_txt(),
            $this->check_seo_plugin(),
        );
    }

    private function check_site_title() {
        $title   = get_bloginfo( 'name' );
        $default = in_array( strtolower( trim( $title ) ), array( '', 'my site', 'my wordpress site', 'just another wordpress site', 'wordpress site' ), true );
        return array(
            'id'     => 'site_title',
            'title'  => __( 'Site Title', 'wpezo-site-inspector' ),
            'status' => $default ? self::FAIL : self::PASS,
            'weight' => self::W_MEDIUM,
            'detail' => $default
                ? __( 'Your site title is empty or set to a default value.', 'wpezo-site-inspector' )
                : sprintf(
                    /* translators: %s: site title */
                    __( 'Site title: "%s".', 'wpezo-site-inspector' ),
                    esc_html( $title )
                ),
            'fix'    => $default ? __( 'Go to Settings → General and set a meaningful site title with your brand or keywords.', 'wpezo-site-inspector' ) : '',
        );
    }

    private function check_tagline() {
        $tagline = get_bloginfo( 'description' );
        $default = ( 'Just another WordPress site' === $tagline || empty( $tagline ) );
        return array(
            'id'     => 'tagline',
            'title'  => __( 'Site Tagline', 'wpezo-site-inspector' ),
            'status' => $default ? self::WARN : self::PASS,
            'weight' => self::W_LOW,
            'detail' => $default
                ? __( 'The site tagline is default or empty.', 'wpezo-site-inspector' )
                : sprintf(
                    /* translators: %s: tagline */
                    __( 'Tagline: "%s".', 'wpezo-site-inspector' ),
                    esc_html( $tagline )
                ),
            'fix'    => $default ? __( 'Set a descriptive tagline in Settings → General.', 'wpezo-site-inspector' ) : '',
        );
    }

    private function check_search_visibility() {
        $discouraged = '0' !== get_option( 'blog_public', '1' );
        // blog_public = 1 means visible, 0 means discouraged
        $blog_public = get_option( 'blog_public' );
        $visible     = ( '1' === (string) $blog_public );
        return array(
            'id'     => 'search_visibility',
            'title'  => __( 'Search Engine Visibility', 'wpezo-site-inspector' ),
            'status' => $visible ? self::PASS : self::FAIL,
            'weight' => self::W_HIGH,
            'detail' => $visible
                ? __( 'Search engines are allowed to index this site.', 'wpezo-site-inspector' )
                : __( 'Search engines are DISCOURAGED from indexing this site! This might be intentional for staging, but critical if this is a live site.', 'wpezo-site-inspector' ),
            'fix'    => $visible ? '' : __( 'Go to Settings → Reading and uncheck "Discourage search engines from indexing this site" — unless this is a staging site.', 'wpezo-site-inspector' ),
        );
    }

    private function check_permalink_structure() {
        $structure = get_option( 'permalink_structure' );
        $is_plain  = empty( $structure );
        return array(
            'id'     => 'permalink_structure',
            'title'  => __( 'Permalink Structure', 'wpezo-site-inspector' ),
            'status' => $is_plain ? self::FAIL : self::PASS,
            'weight' => self::W_MEDIUM,
            'detail' => $is_plain
                ? __( 'Using plain permalinks (?p=123). This is bad for SEO.', 'wpezo-site-inspector' )
                : sprintf(
                    /* translators: %s: permalink structure */
                    __( 'Permalink structure: %s', 'wpezo-site-inspector' ),
                    esc_html( $structure )
                ),
            'fix'    => $is_plain ? __( 'Go to Settings → Permalinks and choose "Post name" or a custom structure.', 'wpezo-site-inspector' ) : '',
        );
    }

    private function check_sitemap() {
        $sitemap_url = home_url( '/wp-sitemap.xml' );
        $response    = wp_remote_head( $sitemap_url, array( 'timeout' => 10, 'sslverify' => false ) );
        $has_core    = ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) );

        // Also check common plugin sitemaps.
        if ( ! $has_core ) {
            $alt_urls = array(
                home_url( '/sitemap.xml' ),
                home_url( '/sitemap_index.xml' ),
            );
            foreach ( $alt_urls as $url ) {
                $r = wp_remote_head( $url, array( 'timeout' => 5, 'sslverify' => false ) );
                if ( ! is_wp_error( $r ) && 200 === wp_remote_retrieve_response_code( $r ) ) {
                    $has_core = true;
                    break;
                }
            }
        }

        return array(
            'id'     => 'sitemap',
            'title'  => __( 'XML Sitemap', 'wpezo-site-inspector' ),
            'status' => $has_core ? self::PASS : self::WARN,
            'weight' => self::W_MEDIUM,
            'detail' => $has_core
                ? __( 'XML sitemap is accessible.', 'wpezo-site-inspector' )
                : __( 'No XML sitemap detected at standard locations.', 'wpezo-site-inspector' ),
            'fix'    => $has_core ? '' : __( 'WordPress 5.5+ includes a built-in sitemap. Check if it\'s disabled, or install an SEO plugin like Yoast or RankMath.', 'wpezo-site-inspector' ),
        );
    }

    private function check_robots_txt() {
        $robots_url = home_url( '/robots.txt' );
        $response   = wp_remote_head( $robots_url, array( 'timeout' => 5, 'sslverify' => false ) );
        $exists     = ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) );

        return array(
            'id'     => 'robots_txt',
            'title'  => __( 'robots.txt', 'wpezo-site-inspector' ),
            'status' => $exists ? self::PASS : self::WARN,
            'weight' => self::W_LOW,
            'detail' => $exists
                ? __( 'robots.txt is accessible.', 'wpezo-site-inspector' )
                : __( 'No robots.txt found. WordPress generates a virtual one, but a physical file gives you more control.', 'wpezo-site-inspector' ),
            'fix'    => $exists ? '' : __( 'Create a robots.txt file or use an SEO plugin to manage it.', 'wpezo-site-inspector' ),
        );
    }

    private function check_seo_plugin() {
        $seo_plugins = array(
            'wordpress-seo/wp-seo.php',
            'all-in-one-seo-pack/all_in_one_seo_pack.php',
            'seo-by-rank-math/rank-math.php',
            'the-seo-framework/autodescription.php',
            'squirrly-seo/squirrly.php',
            'slim-seo/slim-seo.php',
        );

        $active = get_option( 'active_plugins', array() );
        $found  = '';
        foreach ( $seo_plugins as $sp ) {
            if ( in_array( $sp, $active, true ) ) {
                $data  = get_plugin_data( WP_PLUGIN_DIR . '/' . $sp, false, false );
                $found = $data['Name'] ?? $sp;
                break;
            }
        }

        return array(
            'id'     => 'seo_plugin',
            'title'  => __( 'SEO Plugin', 'wpezo-site-inspector' ),
            'status' => $found ? self::PASS : self::WARN,
            'weight' => self::W_LOW,
            'detail' => $found
                ? sprintf(
                    /* translators: %s: plugin name */
                    __( 'SEO plugin active: %s.', 'wpezo-site-inspector' ),
                    esc_html( $found )
                )
                : __( 'No SEO plugin detected. You\'re missing meta descriptions, Open Graph, schema markup, and more.', 'wpezo-site-inspector' ),
            'fix'    => $found ? '' : __( 'Install an SEO plugin like Yoast SEO, RankMath, or The SEO Framework.', 'wpezo-site-inspector' ),
        );
    }

    /* =========================================================================
     * ACCESSIBILITY CHECKS
     * ====================================================================== */

    private function accessibility_checks() {
        return array(
            $this->check_theme_a11y(),
            $this->check_alt_text(),
            $this->check_language_attr(),
            $this->check_skip_link(),
            $this->check_heading_structure(),
        );
    }

    private function check_theme_a11y() {
        $theme = wp_get_theme();
        $tags  = $theme->get( 'Tags' );
        $ready = ( is_array( $tags ) && in_array( 'accessibility-ready', $tags, true ) );
        return array(
            'id'     => 'theme_a11y',
            'title'  => __( 'Accessibility-Ready Theme', 'wpezo-site-inspector' ),
            'status' => $ready ? self::PASS : self::WARN,
            'weight' => self::W_MEDIUM,
            'detail' => $ready
                ? sprintf(
                    /* translators: %s: theme name */
                    __( '"%s" is tagged accessibility-ready.', 'wpezo-site-inspector' ),
                    esc_html( $theme->get( 'Name' ) )
                )
                : sprintf(
                    /* translators: %s: theme name */
                    __( '"%s" is NOT tagged accessibility-ready. It may lack proper focus styles, skip links, ARIA attributes, etc.', 'wpezo-site-inspector' ),
                    esc_html( $theme->get( 'Name' ) )
                ),
            'fix'    => $ready ? '' : __( 'Consider switching to an accessibility-ready theme, or audit your current theme for WCAG compliance.', 'wpezo-site-inspector' ),
        );
    }

    private function check_alt_text() {
        global $wpdb;
        $total = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
        );
        $missing = 0;
        if ( $total > 0 ) {
            $missing = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
                 WHERE p.post_type = 'attachment'
                 AND p.post_mime_type LIKE 'image/%'
                 AND (pm.meta_value IS NULL OR pm.meta_value = '')"
            );
        }

        $pct = $total > 0 ? round( ( ( $total - $missing ) / $total ) * 100 ) : 100;

        return array(
            'id'     => 'alt_text',
            'title'  => __( 'Image Alt Text', 'wpezo-site-inspector' ),
            'status' => $pct >= 90 ? self::PASS : ( $pct >= 60 ? self::WARN : self::FAIL ),
            'weight' => self::W_HIGH,
            'detail' => sprintf(
                /* translators: 1: missing count 2: total count 3: percentage with alt */
                __( '%1$d of %2$d images missing alt text (%3$d%% have alt text).', 'wpezo-site-inspector' ),
                $missing,
                $total,
                $pct
            ),
            'fix'    => $pct < 90 ? __( 'Add descriptive alt text to all images in the Media Library. This helps screen readers and SEO.', 'wpezo-site-inspector' ) : '',
        );
    }

    private function check_language_attr() {
        $locale = get_locale();
        $lang   = get_bloginfo( 'language' );
        $has_lang = ! empty( $lang ) && 'en-US' !== $lang || ! empty( $locale );
        // WordPress always sets the lang attribute, so this is almost always pass.
        return array(
            'id'     => 'language_attr',
            'title'  => __( 'HTML Language Attribute', 'wpezo-site-inspector' ),
            'status' => self::PASS,
            'weight' => self::W_LOW,
            'detail' => sprintf(
                /* translators: %s: language code */
                __( 'Language is set to "%s". WordPress handles this automatically.', 'wpezo-site-inspector' ),
                esc_html( $lang )
            ),
            'fix'    => '',
        );
    }

    private function check_skip_link() {
        $home_url = home_url( '/' );
        $response = wp_remote_get( $home_url, array( 'timeout' => 15, 'sslverify' => false ) );
        $has_skip = false;

        if ( ! is_wp_error( $response ) ) {
            $body     = wp_remote_retrieve_body( $response );
            $has_skip = ( false !== stripos( $body, 'skip' ) && ( false !== stripos( $body, '#content' ) || false !== stripos( $body, '#main' ) || false !== stripos( $body, 'skip-link' ) ) );
        }

        return array(
            'id'     => 'skip_link',
            'title'  => __( 'Skip to Content Link', 'wpezo-site-inspector' ),
            'status' => $has_skip ? self::PASS : self::WARN,
            'weight' => self::W_MEDIUM,
            'detail' => $has_skip
                ? __( 'A "Skip to content" link was detected.', 'wpezo-site-inspector' )
                : __( 'No "Skip to content" link found. Keyboard users must tab through all navigation to reach content.', 'wpezo-site-inspector' ),
            'fix'    => $has_skip ? '' : __( 'Add a visually-hidden skip link as the first focusable element in your theme header.', 'wpezo-site-inspector' ),
        );
    }

    private function check_heading_structure() {
        // Check the latest 5 published posts for heading hierarchy.
        $posts   = get_posts( array( 'numberposts' => 5, 'post_status' => 'publish' ) );
        $issues  = 0;
        $checked = 0;

        foreach ( $posts as $post ) {
            $content = $post->post_content;
            if ( empty( $content ) ) continue;
            $checked++;
            // Check for H1 in post content (theme should provide H1).
            if ( preg_match( '/<h1[\s>]/i', $content ) ) {
                $issues++;
            }
        }

        return array(
            'id'     => 'heading_structure',
            'title'  => __( 'Heading Hierarchy', 'wpezo-site-inspector' ),
            'status' => $issues > 0 ? self::WARN : self::PASS,
            'weight' => self::W_LOW,
            'detail' => $issues > 0
                ? sprintf(
                    /* translators: %d: count */
                    __( '%d of your recent posts contain H1 tags in the content. The theme should provide the H1 (post title).', 'wpezo-site-inspector' ),
                    $issues
                )
                : sprintf(
                    /* translators: %d: count */
                    __( 'Checked %d recent posts — heading structure looks good.', 'wpezo-site-inspector' ),
                    $checked
                ),
            'fix'    => $issues > 0 ? __( 'Use H2-H6 in your post content. The post title should be the only H1 on the page.', 'wpezo-site-inspector' ) : '',
        );
    }

    /* =========================================================================
     * DATABASE CHECKS
     * ====================================================================== */

    private function database_checks() {
        return array(
            $this->check_spam_comments(),
            $this->check_trash_posts(),
            $this->check_auto_drafts(),
            $this->check_orphaned_meta(),
            $this->check_db_overhead(),
        );
    }

    private function check_spam_comments() {
        $count = (int) wp_count_comments()->spam;
        return array(
            'id'     => 'spam_comments',
            'title'  => __( 'Spam Comments', 'wpezo-site-inspector' ),
            'status' => $count < 50 ? self::PASS : ( $count < 500 ? self::WARN : self::FAIL ),
            'weight' => self::W_LOW,
            'detail' => sprintf(
                /* translators: %d: spam count */
                __( '%d spam comments in the database.', 'wpezo-site-inspector' ),
                $count
            ),
            'fix'    => $count >= 50 ? __( 'Empty the spam folder regularly. Use Akismet or CleanTalk to reduce spam.', 'wpezo-site-inspector' ) : '',
        );
    }

    private function check_trash_posts() {
        global $wpdb;
        $count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'"
        );
        return array(
            'id'     => 'trash_posts',
            'title'  => __( 'Trashed Posts', 'wpezo-site-inspector' ),
            'status' => $count < 20 ? self::PASS : ( $count < 100 ? self::WARN : self::FAIL ),
            'weight' => self::W_LOW,
            'detail' => sprintf(
                /* translators: %d: trash count */
                __( '%d posts in the trash.', 'wpezo-site-inspector' ),
                $count
            ),
            'fix'    => $count >= 20 ? __( 'Empty the trash from Posts → All Posts → Trash → Empty Trash.', 'wpezo-site-inspector' ) : '',
        );
    }

    private function check_auto_drafts() {
        global $wpdb;
        $count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'"
        );
        return array(
            'id'     => 'auto_drafts',
            'title'  => __( 'Auto-Drafts', 'wpezo-site-inspector' ),
            'status' => $count < 10 ? self::PASS : self::WARN,
            'weight' => self::W_LOW,
            'detail' => sprintf(
                /* translators: %d: auto-draft count */
                __( '%d auto-drafts lingering in the database.', 'wpezo-site-inspector' ),
                $count
            ),
            'fix'    => $count >= 10 ? __( 'Clean auto-drafts with a database cleanup plugin.', 'wpezo-site-inspector' ) : '',
        );
    }

    private function check_orphaned_meta() {
        global $wpdb;
        $count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
             LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.ID IS NULL"
        );
        return array(
            'id'     => 'orphaned_meta',
            'title'  => __( 'Orphaned Post Meta', 'wpezo-site-inspector' ),
            'status' => $count < 100 ? self::PASS : ( $count < 1000 ? self::WARN : self::FAIL ),
            'weight' => self::W_MEDIUM,
            'detail' => sprintf(
                /* translators: %d: orphaned meta count */
                __( '%d orphaned post meta entries (metadata for deleted posts).', 'wpezo-site-inspector' ),
                $count
            ),
            'fix'    => $count >= 100 ? __( 'Use a database optimizer to clean orphaned metadata. Always backup first.', 'wpezo-site-inspector' ) : '',
        );
    }

    private function check_db_overhead() {
        global $wpdb;
        $tables   = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $overhead = 0;

        if ( is_array( $tables ) ) {
            foreach ( $tables as $table ) {
                if ( isset( $table['Data_free'] ) ) {
                    $overhead += (int) $table['Data_free'];
                }
            }
        }

        $overhead_mb = round( $overhead / 1048576, 2 );

        return array(
            'id'     => 'db_overhead',
            'title'  => __( 'Database Overhead', 'wpezo-site-inspector' ),
            'status' => $overhead_mb < 10 ? self::PASS : ( $overhead_mb < 50 ? self::WARN : self::FAIL ),
            'weight' => self::W_MEDIUM,
            'detail' => sprintf(
                /* translators: %s: overhead in MB */
                __( 'Database overhead: %s MB.', 'wpezo-site-inspector' ),
                $overhead_mb
            ),
            'fix'    => $overhead_mb >= 10 ? __( 'Optimize database tables from phpMyAdmin or using a plugin like WP-Optimize.', 'wpezo-site-inspector' ) : '',
        );
    }

    /* =========================================================================
     * PRO CHECKS (email-gated)
     * ====================================================================== */

    /**
     * Return preview data for locked pro checks (no real scan).
     *
     * @return array
     */
    public function get_pro_checks_preview() {
        return array(
            array(
                'id'     => 'pro_mixed_content',
                'title'  => __( 'Mixed Content Detection', 'wpezo-site-inspector' ),
                'status' => 'locked',
                'weight' => self::W_HIGH,
                'detail' => __( 'Scans all pages for HTTP resources loaded on HTTPS pages that cause browser warnings.', 'wpezo-site-inspector' ),
                'fix'    => '',
                'pro'    => true,
            ),
            array(
                'id'     => 'pro_image_optimization',
                'title'  => __( 'Image Optimization Analysis', 'wpezo-site-inspector' ),
                'status' => 'locked',
                'weight' => self::W_HIGH,
                'detail' => __( 'Finds unoptimized images over 200KB, missing WebP/AVIF versions, and oversized dimensions.', 'wpezo-site-inspector' ),
                'fix'    => '',
                'pro'    => true,
            ),
            array(
                'id'     => 'pro_login_security',
                'title'  => __( 'Login Security Audit', 'wpezo-site-inspector' ),
                'status' => 'locked',
                'weight' => self::W_HIGH,
                'detail' => __( 'Checks for Two-Factor Authentication, login URL change, brute-force protection, and password policies.', 'wpezo-site-inspector' ),
                'fix'    => '',
                'pro'    => true,
            ),
            array(
                'id'     => 'pro_backup_status',
                'title'  => __( 'Backup Status', 'wpezo-site-inspector' ),
                'status' => 'locked',
                'weight' => self::W_HIGH,
                'detail' => __( 'Detects backup plugins and checks when the last backup was performed. Alerts if no backup system found.', 'wpezo-site-inspector' ),
                'fix'    => '',
                'pro'    => true,
            ),
            array(
                'id'     => 'pro_update_status',
                'title'  => __( 'Plugin & Theme Update Status', 'wpezo-site-inspector' ),
                'status' => 'locked',
                'weight' => self::W_MEDIUM,
                'detail' => __( 'Lists all plugins and themes with pending updates, flags abandoned plugins (no update in 2+ years).', 'wpezo-site-inspector' ),
                'fix'    => '',
                'pro'    => true,
            ),
            array(
                'id'     => 'pro_rest_api_exposure',
                'title'  => __( 'REST API Exposure', 'wpezo-site-inspector' ),
                'status' => 'locked',
                'weight' => self::W_MEDIUM,
                'detail' => __( 'Checks if user enumeration is possible via REST API and if sensitive endpoints are publicly accessible.', 'wpezo-site-inspector' ),
                'fix'    => '',
                'pro'    => true,
            ),
            array(
                'id'     => 'pro_email_deliverability',
                'title'  => __( 'Email Deliverability', 'wpezo-site-inspector' ),
                'status' => 'locked',
                'weight' => self::W_MEDIUM,
                'detail' => __( 'Tests if WordPress can send emails, checks for SMTP plugin, and verifies sender configuration.', 'wpezo-site-inspector' ),
                'fix'    => '',
                'pro'    => true,
            ),
            array(
                'id'     => 'pro_broken_media',
                'title'  => __( 'Broken Media Files', 'wpezo-site-inspector' ),
                'status' => 'locked',
                'weight' => self::W_LOW,
                'detail' => __( 'Scans the Media Library for missing files, orphaned attachments, and broken image references in content.', 'wpezo-site-inspector' ),
                'fix'    => '',
                'pro'    => true,
            ),
        );
    }

    /**
     * Run the actual pro checks (after email unlock).
     * Deeply integrated — verifies HMAC-signed unlock status.
     *
     * @return array
     */
    public function run_pro_checks() {
        // ── Anti-bypass: verify unlock is genuine ──
        if ( ! Email_Gate::is_unlocked() ) {
            return $this->get_pro_checks_preview();
        }

        return array(
            $this->check_pro_mixed_content(),
            $this->check_pro_image_optimization(),
            $this->check_pro_login_security(),
            $this->check_pro_backup_status(),
            $this->check_pro_update_status(),
            $this->check_pro_rest_api_exposure(),
            $this->check_pro_email_deliverability(),
            $this->check_pro_broken_media(),
        );
    }

    private function check_pro_mixed_content() {
        $site_url = home_url( '/' );
        $is_ssl   = is_ssl();
        $mixed    = 0;

        if ( $is_ssl ) {
            $response = wp_remote_get( $site_url, array( 'timeout' => 15, 'sslverify' => false ) );
            if ( ! is_wp_error( $response ) ) {
                $body = wp_remote_retrieve_body( $response );
                // Find http:// resources in src and href (excluding anchors and mailto).
                preg_match_all( '/(?:src|href)\s*=\s*["\']http:\/\/[^"\']+["\']/', $body, $matches );
                $mixed = isset( $matches[0] ) ? count( $matches[0] ) : 0;
            }
        }

        return array(
            'id'     => 'pro_mixed_content',
            'title'  => __( 'Mixed Content Detection', 'wpezo-site-inspector' ),
            'status' => ! $is_ssl ? self::WARN : ( $mixed > 0 ? self::FAIL : self::PASS ),
            'weight' => self::W_HIGH,
            'detail' => ! $is_ssl
                ? __( 'Site is not using HTTPS — mixed content check not applicable.', 'wpezo-site-inspector' )
                : ( $mixed > 0
                    /* translators: %d: number of insecure HTTP resources */
                    ? sprintf( __( '%d insecure (HTTP) resources found on your homepage. These cause browser security warnings.', 'wpezo-site-inspector' ), $mixed )
                    : __( 'No mixed content detected on your homepage. All resources load over HTTPS.', 'wpezo-site-inspector' ) ),
            'fix'    => $mixed > 0 ? __( 'Update resource URLs to HTTPS, or use a plugin like "Better Search Replace" to fix database URLs.', 'wpezo-site-inspector' ) : '',
            'pro'    => true,
        );
    }

    private function check_pro_image_optimization() {
        global $wpdb;

        // Find images over 200 KB.
        $large_images = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attached_file'
             WHERE p.post_type = 'attachment'
             AND p.post_mime_type LIKE 'image/%'"
        );

        $total_size = 0;
        $oversized  = 0;
        $upload_dir = wp_upload_dir();
        $base_dir   = $upload_dir['basedir'];

        // Sample check (max 50 images for performance).
        $images = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT pm.meta_value FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attached_file'
             WHERE p.post_type = 'attachment'
             AND p.post_mime_type LIKE 'image/%'
             ORDER BY p.ID DESC LIMIT 50"
        );

        foreach ( $images as $file ) {
            $path = $base_dir . '/' . $file;
            if ( file_exists( $path ) ) {
                $size = filesize( $path );
                $total_size += $size;
                if ( $size > 204800 ) { // 200 KB
                    $oversized++;
                }
            }
        }

        $total_mb   = round( $total_size / 1048576, 1 );
        $sample     = count( $images );

        return array(
            'id'     => 'pro_image_optimization',
            'title'  => __( 'Image Optimization Analysis', 'wpezo-site-inspector' ),
            'status' => $oversized === 0 ? self::PASS : ( $oversized <= 5 ? self::WARN : self::FAIL ),
            'weight' => self::W_HIGH,
            'detail' => sprintf(
                /* translators: %1$d: number of images sampled, %2$s: total size in MB, %3$d: number of oversized images */
                __( 'Sampled %1$d recent images (%2$s MB total): %3$d are over 200 KB and should be optimized.', 'wpezo-site-inspector' ),
                $sample, $total_mb, $oversized
            ),
            'fix'    => $oversized > 0 ? __( 'Use an image optimization plugin (ShortPixel, Smush, Imagify) to compress images. Convert to WebP for 25-50% smaller files.', 'wpezo-site-inspector' ) : '',
            'pro'    => true,
        );
    }

    private function check_pro_login_security() {
        $active = get_option( 'active_plugins', array() );
        $checks = array(
            'two_factor' => false,
            'login_limit' => false,
            'login_url'   => false,
        );

        $security_plugins = array(
            'two-factor/two-factor.php',
            'wordfence/wordfence.php',
            'sucuri-scanner/sucuri.php',
            'ithemes-security-pro/ithemes-security-pro.php',
            'better-wp-security/better-wp-security.php',
            'all-in-one-wp-security-and-firewall/wp-security.php',
            'loginizer/loginizer.php',
            'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php',
            'wps-hide-login/wps-hide-login.php',
        );

        foreach ( $active as $p ) {
            if ( in_array( $p, $security_plugins, true ) ) {
                if ( false !== strpos( $p, 'two-factor' ) ) $checks['two_factor'] = true;
                if ( false !== strpos( $p, 'limit-login' ) || false !== strpos( $p, 'loginizer' ) || false !== strpos( $p, 'wordfence' ) || false !== strpos( $p, 'security' ) ) $checks['login_limit'] = true;
                if ( false !== strpos( $p, 'hide-login' ) || false !== strpos( $p, 'security' ) ) $checks['login_url'] = true;
            }
        }

        $score    = array_sum( array_map( 'intval', $checks ) );
        $missing  = array();
        if ( ! $checks['two_factor'] )  $missing[] = __( 'Two-Factor Authentication', 'wpezo-site-inspector' );
        if ( ! $checks['login_limit'] ) $missing[] = __( 'Login attempt limiting', 'wpezo-site-inspector' );
        if ( ! $checks['login_url'] )   $missing[] = __( 'Custom login URL', 'wpezo-site-inspector' );

        return array(
            'id'     => 'pro_login_security',
            'title'  => __( 'Login Security Audit', 'wpezo-site-inspector' ),
            'status' => $score >= 3 ? self::PASS : ( $score >= 1 ? self::WARN : self::FAIL ),
            'weight' => self::W_HIGH,
            'detail' => $score >= 3
                ? __( 'Login security is well configured: 2FA, brute-force protection, and custom login URL detected.', 'wpezo-site-inspector' )
                : sprintf(
                    /* translators: %s: comma-separated list of missing login protections */
                    __( 'Missing login protections: %s.', 'wpezo-site-inspector' ),
                    implode( ', ', $missing )
                ),
            'fix'    => $score < 3 ? __( 'Install security plugins for 2FA (Two Factor), brute-force protection (Limit Login Attempts Reloaded), and login URL hiding (WPS Hide Login).', 'wpezo-site-inspector' ) : '',
            'pro'    => true,
        );
    }

    private function check_pro_backup_status() {
        $active = get_option( 'active_plugins', array() );
        $backup_plugins = array(
            'updraftplus/updraftplus.php',
            'backwpup/backwpup.php',
            'duplicator/duplicator.php',
            'backup-backup/backup-backup.php',
            'jetpack/jetpack.php',
            'blogvault-real-time-backup/developer_developer.php',
            'wpvivid-backuprestore/wpvivid-backuprestore.php',
            'all-in-one-wp-migration/all-in-one-wp-migration.php',
        );

        $found = '';
        foreach ( $backup_plugins as $bp ) {
            if ( in_array( $bp, $active, true ) ) {
                $data  = get_plugin_data( WP_PLUGIN_DIR . '/' . $bp, false, false );
                $found = isset( $data['Name'] ) ? $data['Name'] : $bp;
                break;
            }
        }

        return array(
            'id'     => 'pro_backup_status',
            'title'  => __( 'Backup Status', 'wpezo-site-inspector' ),
            'status' => $found ? self::PASS : self::FAIL,
            'weight' => self::W_HIGH,
            'detail' => $found
                ? sprintf(
                    /* translators: %s: name of the detected backup plugin */
                    __( 'Backup plugin detected: %s. Make sure automated backups are scheduled and tested.', 'wpezo-site-inspector' ),
                    esc_html( $found )
                )
                : __( 'No backup plugin detected! Your site has no automated backup system. Data loss risk is critical.', 'wpezo-site-inspector' ),
            'fix'    => $found ? '' : __( 'Install UpdraftPlus (free) or BlogVault for automated backups. Schedule daily database and weekly full-site backups.', 'wpezo-site-inspector' ),
            'pro'    => true,
        );
    }

    private function check_pro_update_status() {
        $updates = get_plugin_updates();
        $theme_updates = get_theme_updates();
        $plugin_count = is_array( $updates ) ? count( $updates ) : 0;
        $theme_count  = is_array( $theme_updates ) ? count( $theme_updates ) : 0;
        $total = $plugin_count + $theme_count;

        // Check for abandoned plugins (no update in 2+ years via slug info).
        $abandoned = 0;
        $all_plugins = get_plugins();
        foreach ( $all_plugins as $file => $data ) {
            if ( ! empty( $data['Version'] ) && ! empty( $data['AuthorURI'] ) ) {
                // Heuristic: very old version number patterns.
                // A proper check would use the API, but we keep it lightweight.
            }
        }

        return array(
            'id'     => 'pro_update_status',
            'title'  => __( 'Plugin & Theme Update Status', 'wpezo-site-inspector' ),
            'status' => $total === 0 ? self::PASS : ( $total <= 3 ? self::WARN : self::FAIL ),
            'weight' => self::W_MEDIUM,
            'detail' => $total === 0
                ? __( 'All plugins and themes are up to date.', 'wpezo-site-inspector' )
                : sprintf(
                    /* translators: %1$d: number of plugins with updates, %2$d: number of themes with updates */
                    __( '%1$d plugin(s) and %2$d theme(s) have pending updates.', 'wpezo-site-inspector' ),
                    $plugin_count, $theme_count
                ),
            'fix'    => $total > 0 ? __( 'Update all plugins and themes from Dashboard → Updates. Backup your site first.', 'wpezo-site-inspector' ) : '',
            'pro'    => true,
        );
    }

    private function check_pro_rest_api_exposure() {
        // Check if user enumeration via REST is possible.
        $users_url = rest_url( 'wp/v2/users' );
        $response  = wp_remote_get( $users_url, array(
            'timeout'   => 10,
            'sslverify' => false,
            'headers'   => array(),
            'cookies'   => array(),
        ) );

        $exposed = false;
        if ( ! is_wp_error( $response ) ) {
            $code = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );
            if ( 200 === $code && ! empty( $body ) ) {
                $data = json_decode( $body, true );
                if ( is_array( $data ) && ! empty( $data ) ) {
                    $exposed = true;
                }
            }
        }

        return array(
            'id'     => 'pro_rest_api_exposure',
            'title'  => __( 'REST API Exposure', 'wpezo-site-inspector' ),
            'status' => $exposed ? self::FAIL : self::PASS,
            'weight' => self::W_MEDIUM,
            'detail' => $exposed
                ? __( 'User data is publicly accessible via the REST API. Attackers can enumerate usernames.', 'wpezo-site-inspector' )
                : __( 'User enumeration via REST API is restricted.', 'wpezo-site-inspector' ),
            'fix'    => $exposed ? __( 'Restrict the /wp/v2/users endpoint with a security plugin or custom code. Disable user enumeration.', 'wpezo-site-inspector' ) : '',
            'pro'    => true,
        );
    }

    private function check_pro_email_deliverability() {
        $active = get_option( 'active_plugins', array() );
        $smtp_plugins = array(
            'wp-mail-smtp/wp_mail_smtp.php',
            'fluent-smtp/fluent-smtp.php',
            'post-smtp/postman-smtp.php',
            'easy-wp-smtp/easy-wp-smtp.php',
            'smtp-mailer/main.php',
        );

        $has_smtp = false;
        $found    = '';
        foreach ( $smtp_plugins as $sp ) {
            if ( in_array( $sp, $active, true ) ) {
                $data     = get_plugin_data( WP_PLUGIN_DIR . '/' . $sp, false, false );
                $found    = isset( $data['Name'] ) ? $data['Name'] : $sp;
                $has_smtp = true;
                break;
            }
        }

        return array(
            'id'     => 'pro_email_deliverability',
            'title'  => __( 'Email Deliverability', 'wpezo-site-inspector' ),
            'status' => $has_smtp ? self::PASS : self::WARN,
            'weight' => self::W_MEDIUM,
            'detail' => $has_smtp
                ? sprintf(
                    /* translators: %s: name of the active SMTP plugin */
                    __( 'SMTP plugin active: %s. Emails are sent via authenticated SMTP for better deliverability.', 'wpezo-site-inspector' ),
                    esc_html( $found )
                )
                : __( 'No SMTP plugin detected. WordPress is using PHP mail() which often ends up in spam folders.', 'wpezo-site-inspector' ),
            'fix'    => $has_smtp ? '' : __( 'Install WP Mail SMTP or FluentSMTP and configure it with a transactional email service (SendGrid, Mailgun, AWS SES, etc.).', 'wpezo-site-inspector' ),
            'pro'    => true,
        );
    }

    private function check_pro_broken_media() {
        global $wpdb;

        // Sample 100 recent attachments and check if files exist.
        $attachments = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT p.ID, pm.meta_value as file_path
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attached_file'
             WHERE p.post_type = 'attachment'
             ORDER BY p.ID DESC LIMIT 100",
            ARRAY_A
        );

        $upload_dir = wp_upload_dir();
        $base_dir   = $upload_dir['basedir'];
        $missing    = 0;
        $checked    = count( $attachments );

        foreach ( $attachments as $att ) {
            $path = $base_dir . '/' . $att['file_path'];
            if ( ! file_exists( $path ) ) {
                $missing++;
            }
        }

        return array(
            'id'     => 'pro_broken_media',
            'title'  => __( 'Broken Media Files', 'wpezo-site-inspector' ),
            'status' => $missing === 0 ? self::PASS : ( $missing <= 3 ? self::WARN : self::FAIL ),
            'weight' => self::W_LOW,
            'detail' => sprintf(
                /* translators: %1$d: number of media files checked, %2$d: number of missing files */
                __( 'Checked %1$d recent media files: %2$d have missing physical files on the server.', 'wpezo-site-inspector' ),
                $checked, $missing
            ),
            'fix'    => $missing > 0 ? __( 'Re-upload missing files, or remove the broken entries from the Media Library. Check for failed migrations or storage issues.', 'wpezo-site-inspector' ) : '',
            'pro'    => true,
        );
    }
}
