<?php
/**
 * GA_Activator — handles plugin activation and deactivation.
 */

defined( 'ABSPATH' ) || exit;

class GA_Activator {

    /**
     * Run on plugin activation.
     * Sets default settings (if not already stored) and flushes rewrite rules.
     */
    public static function activate(): void {
        if ( false === get_option( 'ga_settings' ) ) {
            add_option( 'ga_settings', GA_Settings::defaults(), '', false );
        }

        // Register rules before flushing so they are written to .htaccess / nginx map.
        GA_Url_Guard::register_rewrite_rules_static();
        flush_rewrite_rules();
    }

    /**
     * Run on plugin deactivation.
     * Removes custom rewrite rules and flushes so WP reverts to default routing.
     */
    public static function deactivate(): void {
        flush_rewrite_rules();
    }
}
