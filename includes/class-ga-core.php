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

        add_action( 'login_enqueue_scripts', [ $this, 'login_page_branding' ] );
        add_filter( 'login_headerurl',       [ $this, 'login_header_url' ] );
        add_filter( 'login_headertext',      [ $this, 'login_header_text' ] );

        if ( is_admin() ) {
            $this->admin->register_hooks();
        }
    }

    public function login_page_branding(): void {
        ?>
        <style>
        #login h1 a,
        .login h1 a {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%91%BB%3C/text%3E%3C/svg%3E") !important;
            background-size: contain !important;
            background-repeat: no-repeat !important;
            background-position: center !important;
            width: 84px !important;
            height: 84px !important;
        }
        </style>
        <?php
    }

    public function login_header_url(): string {
        return home_url();
    }

    public function login_header_text(): string {
        return esc_html__( 'GhostAdmin — Secure Login', 'ghostadmin' );
    }
}
