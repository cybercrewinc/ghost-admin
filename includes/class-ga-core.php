<?php
/**
 * GHOST_ADMIN — singleton core controller.
 */

defined( 'ABSPATH' ) || exit;

final class GHOST_ADMIN {

    private static ?self $instance = null;

    private GA_Settings  $settings;
    private GA_Url_Guard $url_guard;
    private GA_Admin     $admin;

    private function __construct() {}

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
            self::$instance->init();
        }
        return self::$instance;
    }

    private function init(): void {
        $this->settings  = new GA_Settings();
        $this->url_guard = new GA_Url_Guard();
        $this->admin     = new GA_Admin();

        $this->url_guard->register_hooks();

        // Admin class only needs to register hooks in the admin context.
        if ( is_admin() ) {
            $this->admin->register_hooks();
        }
    }
}
