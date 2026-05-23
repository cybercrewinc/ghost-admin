<?php
/**
 * Uninstall CyberCrew Admin Hide — runs when plugin is deleted from WP admin.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'ga_settings' );

flush_rewrite_rules();
