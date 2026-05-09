<?php
/**
 * GA_Url_Guard — custom login URL routing and stealth 404 blocking.
 */

defined( 'ABSPATH' ) || exit;

class GA_Url_Guard {

    /**
     * Wire all hooks for URL interception and rewrite rule registration.
     */
    public function register_hooks(): void {
        add_action( 'init',               [ $this, 'register_rewrite_rules' ],  1 );
        add_action( 'template_redirect',  [ $this, 'intercept_request' ],       1 );

        // Redirect logged-in users who visit the custom slug to wp-admin.
        add_action( 'template_redirect',  [ $this, 'handle_custom_login_redirect' ], 2 );
    }

    /**
     * Instance method wrapper — delegates to the static version so GA_Activator
     * can call it before the instance is fully bootstrapped.
     */
    public function register_rewrite_rules(): void {
        self::register_rewrite_rules_static();
    }

    /**
     * Static: register the custom login rewrite rule.
     * Called both from the instance hook and directly from GA_Activator.
     */
    public static function register_rewrite_rules_static(): void {
        $slug = GA_Settings::get( 'custom_login_slug' );
        if ( '' === $slug ) {
            return;
        }
        // Map /custom-slug and /custom-slug/ to wp-login.php.
        add_rewrite_rule(
            '^' . preg_quote( $slug, '#' ) . '/?$',
            'index.php?ga_login=1',
            'top'
        );
        add_rewrite_tag( '%ga_login%', '([0-9]+)' );
    }

    /**
     * When a user hits the custom login URL, forward them to wp-login.php.
     * Logged-in admins go straight to wp-admin.
     */
    public function handle_custom_login_redirect(): void {
        $slug = GA_Settings::get( 'custom_login_slug' );
        if ( '' === $slug ) {
            return;
        }

        $raw_uri      = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
        $request_path = trim( wp_parse_url( $raw_uri, PHP_URL_PATH ) ?? '', '/' );

        if ( $request_path !== trim( $slug, '/' ) ) {
            return;
        }

        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            wp_safe_redirect( admin_url() );
            exit;
        }

        // Pass through to wp-login.php while preserving query string.
        $query_string = sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ?? '' ) );
        $target       = site_url( 'wp-login.php' ) . ( '' !== $query_string ? '?' . $query_string : '' );
        wp_safe_redirect( $target );
        exit;
    }

    /**
     * Main request interceptor — runs at template_redirect priority 1.
     */
    public function intercept_request(): void {
        $request_uri  = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
        $request_path = wp_parse_url( $request_uri, PHP_URL_PATH ) ?? '';

        // Never block admin-ajax.php — plugins rely on it from the front end.
        if ( false !== strpos( $request_path, 'admin-ajax.php' ) ) {
            return;
        }

        // Whitelisted IPs pass through everything.
        if ( $this->ip_is_whitelisted() ) {
            return;
        }

        // Logged-in admins are never blocked.
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = GA_Settings::get_all();

        if ( $settings['block_default_login'] ) {
            $this->maybe_block_default_login( $request_path, $settings );
        }

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
        // wp-login.php — block direct access entirely.
        if ( preg_match( '#/wp-login\.php#i', $path ) ) {
            $this->send_block( $settings );
        }

        // /wp-admin/ for unauthenticated users — but allow admin-post.php for cron.
        if ( preg_match( '#/wp-admin/#i', $path ) ) {
            if ( false !== strpos( $path, 'admin-post.php' ) ) {
                return;
            }
            $this->send_block( $settings );
        }
    }

    private function maybe_block_folder_access( string $path, array $settings ): void {
        // Only block bare directory traversal attempts, not legitimate file requests.
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

        // xmlrpc.php has its own toggle; skip here if block_xmlrpc is off.
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
     * Terminate the request with a stealth 404 or a plain 403, depending on settings.
     */
    private function send_block( array $settings ): void {
        if ( $settings['stealth_404'] ) {
            global $wp_query;
            $wp_query->set_404();
            status_header( 404 );
            nocache_headers();

            // Attempt to load the theme 404 template; fall back to a plain exit.
            $template = get_404_template();
            if ( $template && file_exists( $template ) ) {
                // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
                include $template;
            } else {
                echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head>';
                echo '<body><h1>Not Found</h1><p>The page you requested could not be found.</p></body></html>';
            }
            exit;
        }

        // Non-stealth: plain 403.
        wp_die(
            esc_html__( 'Forbidden', 'ghost-admin' ),
            esc_html__( 'Access Denied', 'ghost-admin' ),
            [ 'response' => 403 ]
        );
    }

    // -------------------------------------------------------------------------
    // IP whitelist check
    // -------------------------------------------------------------------------

    /**
     * Returns true if the current visitor's IP is in the whitelist.
     */
    private function ip_is_whitelisted(): bool {
        $list_raw = GA_Settings::get( 'whitelist_ips' );
        if ( '' === $list_raw ) {
            return false;
        }

        $visitor_ip = $this->get_visitor_ip();
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
            // Exact IP match.
            if ( $entry === $visitor_ip ) {
                return true;
            }
            // CIDR match.
            if ( str_contains( $entry, '/' ) && $this->ip_in_cidr( $visitor_ip, $entry ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the real visitor IP, preferring REMOTE_ADDR for security.
     * Only falls back to forwarded headers if REMOTE_ADDR is a known proxy.
     */
    private function get_visitor_ip(): string {
        return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
    }

    /**
     * Check if an IPv4 address falls within a CIDR range.
     */
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
