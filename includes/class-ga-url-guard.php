<?php
/**
 * GA_Url_Guard — custom login URL routing and stealth 404 blocking.
 */

defined( 'ABSPATH' ) || exit;

class GA_Url_Guard {

    public function register_hooks(): void {
        // Priority 1: serve custom login slug (must be before WP processes the request).
        add_action( 'init', [ $this, 'serve_custom_login_slug' ],  1 );
        // Priority 2: block wp-admin / wp-login early — before auth_redirect() fires.
        add_action( 'init', [ $this, 'block_admin_paths_early' ], 2 );

        // template_redirect handles frontend paths (folder listings, sensitive files).
        add_action( 'template_redirect', [ $this, 'intercept_request' ], 1 );

        // Rewrite all WP-generated login/logout URLs to use the custom slug.
        add_filter( 'login_url',        [ $this, 'filter_login_url' ],        10, 3 );
        add_filter( 'logout_url',       [ $this, 'filter_logout_url' ],       10, 2 );
        add_filter( 'lostpassword_url', [ $this, 'filter_login_url_simple' ], 10, 2 );
        add_filter( 'register_url',     [ $this, 'filter_login_url_simple' ], 10, 1 );

        // The login form action uses site_url('wp-login.php','login_post') directly —
        // it bypasses login_url filter. Swap it so the POST goes to the custom slug.
        add_filter( 'site_url', [ $this, 'filter_site_url_login' ], 10, 4 );
    }

    // -------------------------------------------------------------------------
    // Early wp-admin / wp-login block — init priority 2.
    // Must run before auth_redirect() inside /wp-admin/admin.php fires.
    // template_redirect never runs for /wp-admin/ requests so this is the
    // only hook that fires in time.
    // -------------------------------------------------------------------------

    public function block_admin_paths_early(): void {
        $settings = GA_Settings::get_all();

        if ( ! $settings['block_default_login'] ) {
            return;
        }

        $path = wp_parse_url(
            sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ),
            PHP_URL_PATH
        ) ?? '';

        // AJAX and cron must always pass through.
        if ( false !== strpos( $path, 'admin-ajax.php' ) ) {
            return;
        }
        if ( false !== strpos( $path, 'admin-post.php' ) ) {
            return;
        }

        if ( $this->ip_is_whitelisted() ) {
            return;
        }

        // Logged-in admins always pass through.
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            return;
        }

        $has_custom_slug = '' !== GA_Settings::get( 'custom_login_slug' );

        // Block /wp-login.php only when a custom slug is configured.
        if ( $has_custom_slug && preg_match( '#/wp-login\.php#i', $path ) ) {
            $this->send_block_early( $settings );
        }

        // Block /wp-admin/ (bare and any sub-path except ajax/cron already exempted above).
        if ( preg_match( '#/wp-admin(/|$)#i', $path ) ) {
            $this->send_block_early( $settings );
        }
    }

    // -------------------------------------------------------------------------
    // Custom login slug — serve wp-login.php directly, no HTTP redirect.
    // -------------------------------------------------------------------------

    /**
     * Detect the custom login slug in the current request and include wp-login.php
     * directly. This avoids an HTTP redirect loop when wp-login.php is also blocked.
     */
    public function serve_custom_login_slug(): void {
        $slug = GA_Settings::get( 'custom_login_slug' );
        if ( '' === $slug ) {
            return;
        }

        $relative = $this->get_relative_request_path();
        if ( rtrim( $relative, '/' ) !== trim( $slug, '/' ) ) {
            return;
        }

        nocache_headers();

        // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
        require ABSPATH . 'wp-login.php';
        exit;
    }

    /**
     * Filter login_url() to replace wp-login.php with the custom slug.
     *
     * @param string $login_url    The original login URL.
     * @param string $redirect     Redirect destination.
     * @param bool   $force_reauth Whether reauth is forced.
     */
    public function filter_login_url( string $login_url, string $redirect, bool $force_reauth ): string {
        return $this->swap_login_url( $login_url );
    }

    /** Simpler variant for filters that pass fewer args (lostpassword, register). */
    public function filter_login_url_simple( string $url ): string {
        return $this->swap_login_url( $url );
    }

    /**
     * Filter logout_url() to point the return-to URL at the custom slug.
     *
     * @param string $logout_url The original logout URL.
     * @param string $redirect   Redirect destination.
     */
    public function filter_logout_url( string $logout_url, string $redirect ): string {
        return $this->swap_login_url( $logout_url );
    }

    /**
     * Replace wp-login.php in a URL with the custom slug, preserving scheme/host/query.
     */
    private function swap_login_url( string $url ): string {
        $slug = GA_Settings::get( 'custom_login_slug' );
        if ( '' === $slug ) {
            return $url;
        }
        // Replace only the path segment — keep all query args intact.
        return str_replace( 'wp-login.php', trailingslashit( $slug ), $url );
    }

    /**
     * Filter site_url() so the login form <form action="..."> points at the custom slug.
     * wp-login.php builds its form action via site_url('wp-login.php','login_post'),
     * which does not go through the login_url filter.
     *
     * @param string      $url     The full URL.
     * @param string      $path    The path passed to site_url().
     * @param string|null $scheme  URL scheme.
     * @param int|null    $blog_id Blog ID (multisite).
     */
    public function filter_site_url_login( string $url, string $path, ?string $scheme, ?int $blog_id ): string {
        $slug = GA_Settings::get( 'custom_login_slug' );
        if ( '' === $slug ) {
            return $url;
        }
        if ( false !== strpos( $path, 'wp-login.php' ) ) {
            return str_replace( 'wp-login.php', trailingslashit( $slug ), $url );
        }
        return $url;
    }

    // -------------------------------------------------------------------------
    // Request blocker — template_redirect priority 1.
    // -------------------------------------------------------------------------

    public function intercept_request(): void {
        $request_path = wp_parse_url(
            sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ),
            PHP_URL_PATH
        ) ?? '';

        // Never block admin-ajax.php — front-end plugins (WooCommerce, CF7…) rely on it.
        if ( false !== strpos( $request_path, 'admin-ajax.php' ) ) {
            return;
        }

        if ( $this->ip_is_whitelisted() ) {
            return;
        }

        // Logged-in admins are never blocked.
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = GA_Settings::get_all();

        // wp-admin and wp-login are blocked in the init hook (block_admin_paths_early).
        // template_redirect only handles frontend paths below.

        if ( $settings['block_folder_access'] ) {
            $this->maybe_block_folder_access( $request_path, $settings );
        }

        if ( $settings['block_sensitive_files'] ) {
            $this->maybe_block_sensitive_files( $request_path, $settings );
        }

        if ( $settings['block_wpcron_direct'] ) {
            $this->maybe_block_wpcron( $request_path, $settings );
        }
    }

    // -------------------------------------------------------------------------
    // Block handlers
    // -------------------------------------------------------------------------

    private function maybe_block_default_login( string $path, array $settings ): void {
        // Safety: never block wp-login.php when no custom slug is configured —
        // doing so would lock every user out of the site.
        $has_custom_slug = '' !== GA_Settings::get( 'custom_login_slug' );

        if ( $has_custom_slug && preg_match( '#/wp-login\.php#i', $path ) ) {
            $this->send_block( $settings );
        }

        // Block /wp-admin/ for unauthenticated visitors; allow admin-post.php (cron).
        if ( preg_match( '#/wp-admin/#i', $path ) ) {
            if ( false !== strpos( $path, 'admin-post.php' ) ) {
                return;
            }
            $this->send_block( $settings );
        }
    }

    private function maybe_block_folder_access( string $path, array $settings ): void {
        if ( preg_match( '#/wp-content/?$#i', $path ) ) {
            $this->send_block( $settings );
        }
        if ( preg_match( '#/wp-includes/?$#i', $path ) ) {
            $this->send_block( $settings );
        }
    }

    private function maybe_block_sensitive_files( string $path, array $settings ): void {
        $sensitive = [
            'xmlrpc\.php',
            'readme\.html',
            'license\.txt',
            'wp-config\.php',
            'wp-config-sample\.php',
        ];

        if ( ! $settings['block_xmlrpc'] ) {
            $sensitive = array_filter(
                $sensitive,
                fn( string $p ) => false === strpos( $p, 'xmlrpc' )
            );
        }

        foreach ( $sensitive as $pattern ) {
            if ( preg_match( '#/' . $pattern . '#i', $path ) ) {
                $this->send_block( $settings );
            }
        }
    }

    private function maybe_block_wpcron( string $path, array $settings ): void {
        if ( preg_match( '#/wp-cron\.php#i', $path ) ) {
            $this->send_block( $settings );
        }
    }

    // -------------------------------------------------------------------------
    // Response helpers
    // -------------------------------------------------------------------------

    /**
     * Early block (init context) — theme templates aren't available yet,
     * so we output minimal static HTML.
     */
    private function send_block_early( array $settings ): void {
        nocache_headers();
        if ( ! $settings['stealth_404'] ) {
            wp_die(
                esc_html__( 'Forbidden', 'ghostadmin' ),
                esc_html__( 'Access Denied', 'ghostadmin' ),
                [ 'response' => 403 ]
            );
        }
        status_header( 404 );
        header( 'Content-Type: text/html; charset=UTF-8' );
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Page Not Found</title></head>';
        echo '<body><h1>Not Found</h1><p>The page you were looking for could not be found.</p></body></html>';
        exit;
    }

    private function send_block( array $settings ): void {
        if ( $settings['stealth_404'] ) {
            global $wp_query;
            $wp_query->set_404();
            status_header( 404 );
            nocache_headers();

            $template = get_404_template();
            if ( $template && file_exists( $template ) ) {
                // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
                include $template;
            } else {
                echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head>';
                echo '<body><h1>Not Found</h1></body></html>';
            }
            exit;
        }

        wp_die(
            esc_html__( 'Forbidden', 'ghostadmin' ),
            esc_html__( 'Access Denied', 'ghostadmin' ),
            [ 'response' => 403 ]
        );
    }

    // -------------------------------------------------------------------------
    // Path helpers
    // -------------------------------------------------------------------------

    /**
     * Returns the request path relative to the WordPress site root.
     * Handles WP installed in a subdirectory (e.g. /wordpress/xx → xx).
     */
    private function get_relative_request_path(): string {
        $raw_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
        $path    = wp_parse_url( $raw_uri, PHP_URL_PATH ) ?? '';

        // Strip the site's subdirectory prefix so /wordpress/xx becomes xx.
        $site_root = rtrim( wp_parse_url( site_url(), PHP_URL_PATH ) ?? '', '/' ) . '/';
        if ( '/' !== $site_root && str_starts_with( $path, $site_root ) ) {
            $path = substr( $path, strlen( $site_root ) );
        }

        return ltrim( $path, '/' );
    }

    // -------------------------------------------------------------------------
    // IP whitelist
    // -------------------------------------------------------------------------

    private function ip_is_whitelisted(): bool {
        $list_raw = GA_Settings::get( 'whitelist_ips' );
        if ( '' === $list_raw ) {
            return false;
        }

        $visitor_ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
        if ( '' === $visitor_ip ) {
            return false;
        }

        $entries = preg_split( '/\r\n|\r|\n/', $list_raw );
        if ( ! is_array( $entries ) ) {
            return false;
        }

        foreach ( $entries as $entry ) {
            $entry = trim( $entry );
            if ( '' === $entry ) {
                continue;
            }
            if ( $entry === $visitor_ip ) {
                return true;
            }
            if ( str_contains( $entry, '/' ) && $this->ip_in_cidr( $visitor_ip, $entry ) ) {
                return true;
            }
        }

        return false;
    }

    private function ip_in_cidr( string $ip, string $cidr ): bool {
        [ $subnet, $mask ] = explode( '/', $cidr, 2 );
        if ( ! is_numeric( $mask ) ) {
            return false;
        }
        $mask = (int) $mask;
        if ( $mask < 0 || $mask > 32 ) {
            return false;
        }
        $ip_long     = ip2long( $ip );
        $subnet_long = ip2long( $subnet );
        if ( false === $ip_long || false === $subnet_long ) {
            return false;
        }
        $mask_long = $mask > 0 ? ( ~0 << ( 32 - $mask ) ) : 0;
        return ( $ip_long & $mask_long ) === ( $subnet_long & $mask_long );
    }
}
