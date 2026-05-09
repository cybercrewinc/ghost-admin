<?php
/**
 * GA_Settings — manages plugin options stored in ga_settings.
 */

defined( 'ABSPATH' ) || exit;

class GA_Settings {

    private const OPTION_KEY = 'ga_settings';

    /**
     * Default values for every setting.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array {
        return [
            'custom_login_slug'    => '',
            'block_default_login'  => 1,
            'block_folder_access'  => 1,
            'block_sensitive_files'=> 1,
            'block_xmlrpc'         => 1,
            'block_wpcron_direct'  => 0,
            'stealth_404'          => 1,
            'whitelist_ips'        => '',
        ];
    }

    /**
     * Return all settings, merged over defaults so unknown keys are never returned.
     *
     * @return array<string, mixed>
     */
    public static function get_all(): array {
        $saved = get_option( self::OPTION_KEY, [] );
        if ( ! is_array( $saved ) ) {
            $saved = [];
        }
        return array_merge( self::defaults(), $saved );
    }

    /**
     * Return a single setting value.
     *
     * @param string $key
     * @return mixed
     */
    public static function get( string $key ): mixed {
        $all = self::get_all();
        return $all[ $key ] ?? null;
    }

    /**
     * Sanitize and persist settings from a raw POST array.
     *
     * @param array<string, mixed> $input
     */
    public static function update( array $input ): void {
        $current  = self::get_all();
        $old_slug = $current['custom_login_slug'];

        $clean = [
            'custom_login_slug'     => self::sanitize_slug( $input['custom_login_slug'] ?? '' ),
            'block_default_login'   => isset( $input['block_default_login'] )   ? 1 : 0,
            'block_folder_access'   => isset( $input['block_folder_access'] )   ? 1 : 0,
            'block_sensitive_files' => isset( $input['block_sensitive_files'] ) ? 1 : 0,
            'block_xmlrpc'          => isset( $input['block_xmlrpc'] )          ? 1 : 0,
            'block_wpcron_direct'   => isset( $input['block_wpcron_direct'] )   ? 1 : 0,
            'stealth_404'           => isset( $input['stealth_404'] )           ? 1 : 0,
            'whitelist_ips'         => self::sanitize_ip_list( $input['whitelist_ips'] ?? '' ),
        ];

        update_option( self::OPTION_KEY, $clean, false );

        // Rewrite rules must be rebuilt when the custom slug changes.
        if ( $old_slug !== $clean['custom_login_slug'] ) {
            flush_rewrite_rules();
        }
    }

    /**
     * Validate and return a URL-safe slug, or '' if invalid/empty.
     */
    private static function sanitize_slug( string $raw ): string {
        $slug = sanitize_title( trim( $raw ) );
        // Reject reserved WP paths.
        $reserved = [ 'wp-login', 'wp-admin', 'wp-content', 'wp-includes', 'wp-json' ];
        if ( '' === $slug || in_array( $slug, $reserved, true ) ) {
            return '';
        }
        return $slug;
    }

    /**
     * Sanitize a newline-separated list of IPs/CIDRs, remove blanks.
     */
    private static function sanitize_ip_list( string $raw ): string {
        $lines = preg_split( '/\r\n|\r|\n/', $raw );
        if ( ! is_array( $lines ) ) {
            return '';
        }
        $clean = array_filter(
            array_map( 'trim', $lines ),
            fn( string $l ) => '' !== $l
        );
        return implode( "\n", $clean );
    }
}
