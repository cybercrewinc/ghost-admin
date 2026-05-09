<?php
/**
 * GA_Admin — settings page in WP admin.
 */

defined( 'ABSPATH' ) || exit;

class GA_Admin {

    public function register_hooks(): void {
        add_action( 'admin_menu',              [ $this, 'add_menu' ] );
        add_action( 'admin_enqueue_scripts',   [ $this, 'enqueue_assets' ] );
        add_action( 'admin_post_ga_save',      [ $this, 'handle_save' ] );
    }

    public function add_menu(): void {
        add_menu_page(
            esc_html__( 'GhostAdmin Settings', 'ghost-admin' ),
            esc_html__( 'GhostAdmin', 'ghost-admin' ),
            'manage_options',
            'ghost-admin',
            [ $this, 'settings_page' ],
            'dashicons-hidden',
            81
        );
    }

    public function enqueue_assets( string $hook ): void {
        if ( 'toplevel_page_ghost-admin' !== $hook ) {
            return;
        }
        wp_enqueue_style(
            'ga-admin',
            GA_URL . 'admin/css/admin.css',
            [],
            GA_VERSION
        );
    }

    public function settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'ghost-admin' ) );
        }

        $settings = GA_Settings::get_all();
        $saved    = isset( $_GET['saved'] ) && '1' === $_GET['saved']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ?>
        <div class="ga-wrap">

            <header class="ga-header">
                <span class="ga-header__icon">&#x1F47B;</span>
                <div class="ga-header__text">
                    <h1><?php esc_html_e( 'GhostAdmin', 'ghost-admin' ); ?></h1>
                    <p><?php esc_html_e( 'Hides your WordPress admin presence entirely.', 'ghost-admin' ); ?></p>
                </div>
                <span class="ga-badge">v<?php echo esc_html( GA_VERSION ); ?></span>
            </header>

            <?php if ( $saved ) : ?>
                <div class="ga-notice ga-notice--success">
                    <?php esc_html_e( 'Settings saved.', 'ghost-admin' ); ?>
                </div>
            <?php endif; ?>

            <?php
            // Warn: block_default_login on but no custom slug = lockout risk.
            if ( $settings['block_default_login'] && '' === $settings['custom_login_slug'] ) :
            ?>
                <div class="ga-notice ga-notice--warning">
                    <strong><?php esc_html_e( 'Lockout risk:', 'ghost-admin' ); ?></strong>
                    <?php esc_html_e( '"Block /wp-login.php" is ON but no Custom Login Slug is set. Set a slug first — otherwise logged-out users (including you) cannot log back in.', 'ghost-admin' ); ?>
                </div>
            <?php endif; ?>

            <div class="ga-notice ga-notice--info">
                <strong><?php esc_html_e( 'You are logged in.', 'ghost-admin' ); ?></strong>
                <?php esc_html_e( 'All blocks are bypassed for logged-in admins — that is intentional. To test blocks, open a private/incognito window and try visiting /wp-login.php or /wp-admin/.', 'ghost-admin' ); ?>
                <?php if ( '' !== $settings['custom_login_slug'] ) : ?>
                    <?php
                    $login_url = trailingslashit( home_url( $settings['custom_login_slug'] ) );
                    ?>
                    <br><strong><?php esc_html_e( 'Your login URL:', 'ghost-admin' ); ?></strong>
                    <a href="<?php echo esc_url( $login_url ); ?>" target="_blank" class="ga-login-url">
                        <?php echo esc_html( $login_url ); ?>
                    </a>
                <?php else : ?>
                    <br><?php esc_html_e( 'No custom slug set — login URL is still /wp-login.php.', 'ghost-admin' ); ?>
                <?php endif; ?>
            </div>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'ga_save_settings', 'ga_nonce' ); ?>
                <input type="hidden" name="action" value="ga_save">

                <!-- Custom Login URL -->
                <section class="ga-card">
                    <h2 class="ga-section-title"><?php esc_html_e( 'Custom Admin URL', 'ghost-admin' ); ?></h2>
                    <hr class="ga-section-divider">

                    <div class="ga-field">
                        <label for="ga_slug" class="ga-label">
                            <?php esc_html_e( 'Custom Login Slug', 'ghost-admin' ); ?>
                        </label>
                        <div class="ga-slug-preview">
                            <span class="ga-slug-base"><?php echo esc_html( trailingslashit( home_url() ) ); ?></span>
                            <input
                                type="text"
                                id="ga_slug"
                                name="custom_login_slug"
                                value="<?php echo esc_attr( $settings['custom_login_slug'] ); ?>"
                                placeholder="ghost-panel"
                                class="ga-input"
                                autocomplete="off"
                                spellcheck="false"
                            >
                        </div>
                        <p class="ga-help">
                            <?php esc_html_e( 'Leave blank to disable. Use letters, numbers, and hyphens only. Reserved WP paths are rejected.', 'ghost-admin' ); ?>
                        </p>
                    </div>
                </section>

                <!-- Default URL Blocking -->
                <section class="ga-card">
                    <h2 class="ga-section-title"><?php esc_html_e( 'Block Default Admin URLs', 'ghost-admin' ); ?></h2>
                    <hr class="ga-section-divider">

                    <div class="ga-field ga-field--row">
                        <label class="ga-toggle-label">
                            <div class="ga-toggle">
                                <input type="checkbox" name="block_default_login" value="1" <?php checked( $settings['block_default_login'], 1 ); ?>>
                                <span class="ga-toggle__track"><span class="ga-toggle__thumb"></span></span>
                            </div>
                            <div class="ga-toggle-text">
                                <span class="ga-toggle-text__title"><?php esc_html_e( 'Block /wp-login.php and /wp-admin/', 'ghost-admin' ); ?></span>
                                <span class="ga-toggle-text__desc"><?php esc_html_e( 'Unauthenticated access returns a stealth 404. AJAX and cron endpoints are always exempt.', 'ghost-admin' ); ?></span>
                            </div>
                        </label>
                    </div>
                </section>

                <!-- Folder / File Blocking -->
                <section class="ga-card">
                    <h2 class="ga-section-title"><?php esc_html_e( 'Block Direct File & Folder Access', 'ghost-admin' ); ?></h2>
                    <hr class="ga-section-divider">

                    <div class="ga-field ga-field--row">
                        <label class="ga-toggle-label">
                            <div class="ga-toggle">
                                <input type="checkbox" name="block_folder_access" value="1" <?php checked( $settings['block_folder_access'], 1 ); ?>>
                                <span class="ga-toggle__track"><span class="ga-toggle__thumb"></span></span>
                            </div>
                            <div class="ga-toggle-text">
                                <span class="ga-toggle-text__title"><?php esc_html_e( 'Block /wp-content/ and /wp-includes/ directory listings', 'ghost-admin' ); ?></span>
                                <span class="ga-toggle-text__desc"><?php esc_html_e( 'Blocks bare directory URL access. Individual files inside are not affected.', 'ghost-admin' ); ?></span>
                            </div>
                        </label>
                    </div>

                    <div class="ga-field ga-field--row">
                        <label class="ga-toggle-label">
                            <div class="ga-toggle">
                                <input type="checkbox" name="block_sensitive_files" value="1" <?php checked( $settings['block_sensitive_files'], 1 ); ?>>
                                <span class="ga-toggle__track"><span class="ga-toggle__thumb"></span></span>
                            </div>
                            <div class="ga-toggle-text">
                                <span class="ga-toggle-text__title"><?php esc_html_e( 'Block sensitive files', 'ghost-admin' ); ?></span>
                                <span class="ga-toggle-text__desc"><?php esc_html_e( 'readme.html, license.txt, wp-config.php, wp-config-sample.php', 'ghost-admin' ); ?></span>
                            </div>
                        </label>
                    </div>

                    <div class="ga-field ga-field--row">
                        <label class="ga-toggle-label">
                            <div class="ga-toggle">
                                <input type="checkbox" name="block_xmlrpc" value="1" <?php checked( $settings['block_xmlrpc'], 1 ); ?>>
                                <span class="ga-toggle__track"><span class="ga-toggle__thumb"></span></span>
                            </div>
                            <div class="ga-toggle-text">
                                <span class="ga-toggle-text__title"><?php esc_html_e( 'Block xmlrpc.php', 'ghost-admin' ); ?></span>
                                <span class="ga-toggle-text__desc"><?php esc_html_e( 'Recommended unless you specifically use XML-RPC for publishing or Jetpack.', 'ghost-admin' ); ?></span>
                            </div>
                        </label>
                    </div>

                    <div class="ga-field ga-field--row">
                        <label class="ga-toggle-label">
                            <div class="ga-toggle">
                                <input type="checkbox" name="block_wpcron_direct" value="1" <?php checked( $settings['block_wpcron_direct'], 1 ); ?>>
                                <span class="ga-toggle__track"><span class="ga-toggle__thumb"></span></span>
                            </div>
                            <div class="ga-toggle-text">
                                <span class="ga-toggle-text__title"><?php esc_html_e( 'Block direct wp-cron.php access', 'ghost-admin' ); ?></span>
                                <span class="ga-toggle-text__desc"><?php esc_html_e( 'Disable if your host triggers WP-Cron via a system cron job hitting this URL directly.', 'ghost-admin' ); ?></span>
                            </div>
                        </label>
                    </div>
                </section>

                <!-- Stealth Mode -->
                <section class="ga-card">
                    <h2 class="ga-section-title"><?php esc_html_e( 'Stealth Mode', 'ghost-admin' ); ?></h2>
                    <hr class="ga-section-divider">

                    <div class="ga-field ga-field--row">
                        <label class="ga-toggle-label">
                            <div class="ga-toggle">
                                <input type="checkbox" name="stealth_404" value="1" <?php checked( $settings['stealth_404'], 1 ); ?>>
                                <span class="ga-toggle__track"><span class="ga-toggle__thumb"></span></span>
                            </div>
                            <div class="ga-toggle-text">
                                <span class="ga-toggle-text__title"><?php esc_html_e( 'Use stealth 404 instead of 403', 'ghost-admin' ); ?></span>
                                <span class="ga-toggle-text__desc"><?php esc_html_e( 'Blocked requests return a real 404 page so bots cannot fingerprint admin paths. Recommended on.', 'ghost-admin' ); ?></span>
                            </div>
                        </label>
                    </div>
                </section>

                <!-- IP Whitelist -->
                <section class="ga-card">
                    <h2 class="ga-section-title"><?php esc_html_e( 'IP Whitelist', 'ghost-admin' ); ?></h2>
                    <hr class="ga-section-divider">

                    <div class="ga-field">
                        <label for="ga_whitelist" class="ga-label">
                            <?php esc_html_e( 'Whitelisted IPs / CIDR Ranges', 'ghost-admin' ); ?>
                        </label>
                        <textarea
                            id="ga_whitelist"
                            name="whitelist_ips"
                            rows="5"
                            class="ga-textarea"
                            placeholder="192.168.1.1&#10;10.0.0.0/8"
                            spellcheck="false"
                        ><?php echo esc_textarea( $settings['whitelist_ips'] ); ?></textarea>
                        <p class="ga-help">
                            <?php esc_html_e( 'One entry per line. Supports exact IPs and CIDR notation. These IPs bypass all GhostAdmin blocks.', 'ghost-admin' ); ?>
                        </p>
                    </div>
                </section>

                <div class="ga-actions">
                    <button type="submit" class="ga-btn ga-btn--primary">
                        <?php esc_html_e( 'Save Settings', 'ghost-admin' ); ?>
                    </button>
                </div>

            </form>
        </div>
        <?php
    }

    public function handle_save(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'ghost-admin' ) );
        }

        check_admin_referer( 'ga_save_settings', 'ga_nonce' );

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        GA_Settings::update( $_POST );

        wp_safe_redirect( add_query_arg( 'saved', '1', admin_url( 'admin.php?page=ghost-admin' ) ) );
        exit;
    }
}
