<?php
/**
 * Plugin Name:  GhostAdmin
 * Plugin URI:   https://cyber.spool.co.jp/ghost-admin
 * Description:  Hides your WordPress admin presence entirely.
 * Version:      1.0.0
 * Author:       CyberCrew
 * Author URI:   https://cyber.spool.co.jp
 * License:      GPLv2 or later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.2
 * Requires PHP: 8.1
 * Text Domain:  ghostadmin-2
 */

defined( 'ABSPATH' ) || exit;

define( 'GA_VERSION',  '1.0.0' );
define( 'GA_PATH',     plugin_dir_path( __FILE__ ) );
define( 'GA_URL',      plugin_dir_url( __FILE__ ) );
define( 'GA_BASENAME', plugin_basename( __FILE__ ) );

require_once GA_PATH . 'includes/class-ga-settings.php';
require_once GA_PATH . 'includes/class-ga-activator.php';
require_once GA_PATH . 'includes/class-ga-url-guard.php';
require_once GA_PATH . 'includes/class-ga-admin.php';
require_once GA_PATH . 'includes/class-ga-core.php';

register_activation_hook(   __FILE__, [ 'GA_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'GA_Activator', 'deactivate' ] );

add_action( 'plugins_loaded', [ 'GHOST_ADMIN', 'instance' ] );
