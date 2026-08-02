<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * First-run setup wizard.
 */
class Permalink_Manager_Setup_Wizard {

    const PAGE_SLUG = 'permalink-manager-setup';
    const NAG_ID = 'setup-wizard';
    const DISMISS_TRANSIENT = 'permalink-manager-notice_setup-wizard';

    /**
     * Hook suffix of the wizard page (used to scope asset loading).
     *
     * @var string
     */
    public $page_hook;

    /**
     * Cached result of should_display_nag() for the current request.
     *
     * @var bool|null
     */
    protected $nag_visible = null;

    public function __construct() {
        add_action( 'init', array( $this, 'init_hooks' ), 99 );
    }

    function init_hooks() {
        // Check if the user is being redirected from a successful setup submission.
        $is_done_redirect = ! empty( $_GET['pm_setup_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_GET['pm_setup_nonce'] ) ), 'pm_setup_done' );

        if ( self::is_unconfigured() || $is_done_redirect ) {
            add_action( 'admin_menu', array( $this, 'register_page' ), 20 );
            add_action( 'admin_init', array( $this, 'maybe_handle_submit' ), 20 );

            add_action( 'admin_notices', array( $this, 'display_setup_nag' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

            add_action( 'wp_ajax_pm_dismiss_setup_wizard', array( $this, 'ajax_dismiss_setup_wizard' ) );
        }
    }

    /**
     * Check whether Permalink Manager Pro features are available.
     *
     * @return bool
     */
    protected static function is_pro() {
        return ( defined( 'PERMALINK_MANAGER_PRO' ) && PERMALINK_MANAGER_PRO );
    }

    /**
     * Number of steps in the wizard. Pro adds a dedicated "License" step.
     *
     * @return int
     */
    protected static function get_total_steps() {
        return self::is_pro() ? 3 : 2;
    }

    /**
     * Check if the plugin has not been configured yet.
     *
     * The runtime global is always filled with default values, so the raw stored option is read here instead.
     *
     * @return bool
     */
    public static function is_unconfigured() {
        $stored = get_option( 'permalink-manager', array() );

        return empty( $stored );
    }

    /**
     * Get the URL of the wizard page.
     *
     * @param array $args Extra query arguments.
     *
     * @return string
     */
    public static function get_page_url( $args = array() ) {
        $args = array_merge( array( 'page' => self::PAGE_SLUG ), $args );

        return add_query_arg( $args, admin_url( 'admin.php' ) );
    }

    /**
     * Build a link to one of the plugin's admin sections, with a graceful fallback when Permalink_Manager_Admin_Functions is unavailable.
     *
     * @param string $section Section slug (e.g. "settings", "permastructs").
     *
     * @return string
     */
    protected static function admin_url_for( $section ) {
        if ( class_exists( 'Permalink_Manager_Admin_Functions' ) ) {
            return Permalink_Manager_Admin_Functions::get_admin_url( '&section=' . $section );
        }

        $url = admin_url( 'tools.php?page=permalink-manager' );

        return ( 'permastructs' === $section ) ? $url : add_query_arg( 'section', $section, $url );
    }

    /**
     * The list of content types the wizard can toggle.
     *
     * @return array {
     * @type array $post_types Associative array of post type slug => label.
     * @type array $taxonomies Associative array of taxonomy slug => label.
     * }
     */
    protected static function get_supported_content_types() {
        return array(
                'post_types' => Permalink_Manager_Helper_Functions::get_post_types_array( null, null, true ),
                'taxonomies' => Permalink_Manager_Helper_Functions::get_taxonomies_array( null, null, true ),
        );
    }

    /**
     * Register the (hidden) admin page that hosts the wizard.
     */
    public function register_page() {
        // Use 'options.php' as the parent slug instead of null
        $this->page_hook = add_submenu_page( 'options.php', __( 'Set up Permalink Manager', 'permalink-manager' ), __( 'Set up Permalink Manager', 'permalink-manager' ), 'manage_options', self::PAGE_SLUG, array( $this, 'render_page' ) );
    }

    /**
     * Enqueue the wizard stylesheet and script.
     *
     * The stylesheet is only needed on the wizard page. The script is loaded both on the wizard page (step navigation) and anywhere the setup notice is shown (to persist its dismissal).
     *
     * @param string $hook Current admin page hook suffix.
     */
    public function enqueue_assets( $hook ) {
        $on_wizard = ( ! empty( $this->page_hook ) && $hook === $this->page_hook );
        $show_nag  = $this->should_display_nag();

        if ( ! $on_wizard && ! $show_nag ) {
            return;
        }

        if ( $on_wizard ) {
            wp_enqueue_style( 'permalink-manager-setup-wizard', PERMALINK_MANAGER_URL . '/out/permalink-manager-setup-wizard.css', array(), PERMALINK_MANAGER_VERSION );
        }

        wp_enqueue_script( 'permalink-manager-setup-wizard', PERMALINK_MANAGER_URL . '/out/permalink-manager-setup-wizard.js', array(), PERMALINK_MANAGER_VERSION, true );

        wp_localize_script( 'permalink-manager-setup-wizard', 'pmSetupWizard', array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'permalink-manager' ),
                'nagId'   => self::NAG_ID,
        ) );
    }

    /**
     * Decide if the setup notice should be shown on the current screen.
     *
     * The result is memoized because this method runs on both admin_enqueue_scripts and admin_notices during the same request.
     *
     * @return bool
     */
    public function should_display_nag() {
        if ( null === $this->nag_visible ) {
            $this->nag_visible = $this->calculate_nag_visibility();
        }

        return $this->nag_visible;
    }

    /**
     * Run the actual visibility checks for the setup notice.
     *
     * @return bool
     */
    protected function calculate_nag_visibility() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        if ( ! self::is_unconfigured() ) {
            return false;
        }

        if ( get_transient( self::DISMISS_TRANSIENT ) ) {
            return false;
        }

        // Do not repeat the notice on the wizard page itself.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen check.
        $current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( self::PAGE_SLUG === $current_page ) {
            return false;
        }

        return true;
    }

    /**
     * Display the dismissible admin notice that links to the wizard page.
     */
    public function display_setup_nag() {
        if ( ! $this->should_display_nag() ) {
            return;
        }

        $message = sprintf( /* translators: %s: URL to the setup wizard */ __( '<a href="%s">Run the setup wizard</a> to choose which content types should use custom permalinks and to enable additional features.', 'permalink-manager' ), esc_url( self::get_page_url() ) );

        printf( '<div class="notice notice-alt notice-warning is-dismissible permalink-manager-notice" data-alert_id="%1$s"><p><strong>%2$s</strong> %3$s</p></div>', esc_attr( self::NAG_ID ), esc_html__( 'Permalink Manager is not configured.', 'permalink-manager' ), wp_kses( $message, array( 'a' => array( 'href' => array() ) ) ) );
    }

    /**
     * Open the shared page chrome (container, header, and content box).
     *
     * @param string $title Page heading.
     * @param string $intro_html Optional intro paragraphs (already-built HTML).
     * @param bool $show_docs Whether to render the "Documentation" button.
     */
    protected function render_container_open( $title, $intro_html = '', $show_docs = false ) {
        $docs_url    = Permalink_Manager_Admin_Functions::get_plugin_website_url( 'docs', array( 'utm_campaign' => 'plugin_setup' ) );
        $support_url = Permalink_Manager_Admin_Functions::get_plugin_website_url( 'contact', array( 'utm_campaign' => 'plugin_setup' ) );

        ?>
        <div class="pm-setup-container">
        <div class="wrap pm-setup-wrap">
        <div class="pm-setup-header">
            <div>
                <h1><?php echo esc_html( $title ); ?></h1>
                <?php
                // Intro paragraphs contain translator-controlled inline markup (<em>, <strong>).
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted translation strings.
                echo $intro_html;
                ?>
            </div>
            <?php if ( $show_docs ) : ?>
                <div>
                    <a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener" class="button button-secondary"><?php esc_html_e( 'Documentation', 'permalink-manager' ); ?></a>
                    <a href="<?php echo esc_url( $support_url ); ?>" target="_blank" rel="noopener" class="button button-secondary"><?php esc_html_e( 'Support', 'permalink-manager' ); ?></a>
                </div>
            <?php endif; ?>
        </div>

        <div class="pm-setup-box">
        <?php
    }

    /**
     * Close the shared page chrome opened by render_shell_open().
     */
    protected function render_container_close() {
        ?>
        </div>
        </div>
        </div>
        <?php
    }

    /**
     * Render the step progress indicator for the current step count.
     *
     * @param int $total Total number of steps.
     */
    protected function render_progress( $total ) {
        $names = array(
                1 => __( 'Which Content Types Should Use Custom Permalinks?', 'permalink-manager' ),
                2 => __( 'Configure Additional Features', 'permalink-manager' ),
                3 => __( 'License', 'permalink-manager' ),
        );

        echo '<p class="pm-setup-progress">';

        for ( $i = 1; $i <= $total; $i ++ ) {
            $label = sprintf( /* translators: 1: current step number, 2: total steps, 3: step name */ __( 'Step %1$d of %2$d: %3$s', 'permalink-manager' ), $i, $total, $names[ $i ] );

            printf( '<span data-step-name="%1$d"%2$s>%3$s</span>', absint( $i ), ( 1 === $i ) ? '' : ' hidden', esc_html( $label ) );
        }

        echo '</p>';
    }

    /**
     * Build the "Upgrade to Pro" button.
     *
     * @param string $label Button label.
     *
     * @return string
     */
    protected function upgrade_button( $label ) {
        $upgrade_url = Permalink_Manager_Admin_Functions::get_plugin_website_url( 'pricing', array( 'utm_campaign' => 'plugin_setup' ) );

        return sprintf( '<a href="%1$s" target="_blank" rel="noopener" class="button button-primary pm-button">%2$s</a>', esc_url( $upgrade_url ), esc_html( $label ) );
    }

    /**
     * Field definitions for step 2 (basic options).
     *
     * @param bool $is_pro Whether Pro is active.
     * @param array $current Current stored values keyed by field.
     *
     * @return array List of [ 'name' => string, 'args' => array ] entries.
     */
    protected function get_step_two_fields( $is_pro, $current ) {
        return array(
                array(
                        'name' => 'save_redirects',
                        'args' => array(
                                'type'        => 'single_checkbox',
                                'label'       => __( 'Save Previous Custom Permalinks as Redirects', 'permalink-manager' ),
                                'value'       => ( $current['save_redirects'] && $is_pro ) ? 1 : 0,
                                'pro'         => ! $is_pro,
                                'disabled'    => ! $is_pro,
                                'container'   => 'row',
                                'description' => __( 'Automatically save the previous custom permalink as an "extra redirect" whenever the URL changes to prevent 404 errors for previously used URLs.', 'permalink-manager' ),
                        ),
                ),
                array(
                        'name' => 'force_unique_uris',
                        'args' => array(
                                'type'        => 'single_checkbox',
                                'label'       => __( 'Force Unique Custom Permalinks', 'permalink-manager' ),
                                'value'       => $current['force_unique'] ? 1 : 0,
                                'container'   => 'row',
                                'description' => __( 'To avoid duplicate custom permalinks, Permalink Manager can append a numeric suffix so each URL remains unique, for example <code>example.com/hello-world</code> and <code>example.com/hello-world-2</code>.', 'permalink-manager' ),
                        ),
                ),
                array(
                        'name' => 'ignore_drafts',
                        'args' => array(
                                'type'        => 'single_checkbox',
                                'label'       => __( 'Ignore Drafts and Pending Posts', 'permalink-manager' ),
                                'value'       => $current['ignore_drafts'] ? 1 : 0,
                                'container'   => 'row',
                                'description' => __( 'By default, for new posts, Permalink Manager saves the custom permalink <strong>only after the post is published</strong>. If you disable this setting, you can define your own permalink before WordPress generates the native slug, which allows it to replace the default custom permalink.', 'permalink-manager' ),
                        ),
                ),
        );
    }

    /**
     * Render the dedicated wizard page.
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to access this page.', 'permalink-manager' ) );
        }

        // Completion screen (shown after the form is saved and redirected).
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success flag; no data mutated.
        if ( ! empty( $_GET['done'] ) ) {
            $this->render_done_screen();

            return;
        }

        global $permalink_manager_options;

        $is_pro      = self::is_pro();
        $total       = self::get_total_steps();
        $website_url = Permalink_Manager_Admin_Functions::get_plugin_website_url( 'features', array( 'utm_campaign' => 'plugin_setup' ) );

        // Universe of supported content types (technical ones already removed).
        $content_types  = self::get_supported_content_types();
        $all_post_types = $content_types['post_types'];
        $all_taxonomies = $content_types['taxonomies'];

        // Content types currently excluded (unchecked by default in the wizard).
        $excluded_post_types = ( ! empty( $permalink_manager_options['general']['partial_disable']['post_types'] ) ) ? (array) $permalink_manager_options['general']['partial_disable']['post_types'] : array();
        $excluded_taxonomies = ( ! empty( $permalink_manager_options['general']['partial_disable']['taxonomies'] ) ) ? (array) $permalink_manager_options['general']['partial_disable']['taxonomies'] : array();

        // Selected (included) content types = universe minus excluded.
        $selected_post_types = array_values( array_diff( array_keys( $all_post_types ), $excluded_post_types ) );
        $selected_taxonomies = array_values( array_diff( array_keys( $all_taxonomies ), $excluded_taxonomies ) );

        // Current values for step 2.
        $current = array(
                'force_unique'   => ! empty( $permalink_manager_options['general']['force_unique_uris'] ),
                'ignore_drafts'  => ! empty( $permalink_manager_options['general']['ignore_drafts'] ),
                'save_redirects' => ( ! empty( $permalink_manager_options['general']['setup_redirects'] ) && ! empty( $permalink_manager_options['general']['extra_redirects'] ) ),
        );

        $intro = '<p class="description">' . __( 'Select the content types that should use custom permalinks and choose which additional features to enable.', 'permalink-manager' ) . '</p>';

        $this->render_container_open( __( 'Permalink Manager: Quick Configuration', 'permalink-manager' ), $intro, true );
        $this->render_progress( $total );

        // phpcs:disabled WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in generate_option_field().
        ?>
        <form method="post" action="<?php echo esc_url( self::get_page_url() ); ?>" class="pm-setup-form">
            <?php wp_nonce_field( 'permalink-manager', 'pm_setup_nonce' ); ?>

            <!-- Step 1: content types -->
            <div class="pm-setup-step" data-step="1">
                <p class="description"><?php echo wp_kses_post( __( 'Content types that are <strong>not selected will be excluded and ignored by the plugin</strong>. Their URLs will continue to use the standard WordPress permalink structure.', 'permalink-manager' ) ); ?></p>

                <h3><?php esc_html_e( 'Post Types', 'permalink-manager' ); ?></h3>
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in generate_option_field().
                echo Permalink_Manager_UI_Elements::generate_option_field( 'post_types', array( 'type' => 'checkbox', 'choices' => $all_post_types, 'value' => $selected_post_types ) );
                ?>

                <h3><?php esc_html_e( 'Taxonomies', 'permalink-manager' ); ?></h3>
                <?php if ( $is_pro ) : ?>
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in generate_option_field().
                    echo Permalink_Manager_UI_Elements::generate_option_field( 'taxonomies', array( 'type' => 'checkbox', 'choices' => $all_taxonomies, 'value' => $selected_taxonomies ) );
                    ?>
                <?php else : ?>
                    <p class="field-description description alert pro-alert info">
                        <?php echo wp_kses_post( sprintf( __( 'Custom taxonomy permalinks are supported only in <a href="%s" target="_blank">Permalink Manager Pro</a>.', 'permalink-manager' ), esc_url( $website_url ) ) ); ?>
                    </p>
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in generate_option_field().
                    echo Permalink_Manager_UI_Elements::generate_option_field( 'taxonomies', array( 'type' => 'checkbox', 'choices' => $all_taxonomies, 'value' => array(), 'disabled' => true ) );
                    ?>
                <?php endif; ?>
            </div>

            <!-- Step 2: options -->
            <div class="pm-setup-step" data-step="2" hidden>
                <table class="form-table" role="presentation">
                    <tbody>
                    <?php
                    foreach ( $this->get_step_two_fields( $is_pro, $current ) as $field ) {
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in generate_option_field().
                        echo Permalink_Manager_UI_Elements::generate_option_field( $field['name'], $field['args'] );
                    }
                    ?>
                    </tbody>
                </table>
            </div>

            <?php
            // Step 3 (Pro only): license key.
            if ( $is_pro ) {
                $this->render_license_step( 3 );
            }
            ?>

            <div class="pm-setup-footer">
                <div>
                    <?php if ( ! $is_pro ) : ?>
                        <?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static markup with escaped values.
                        echo $this->upgrade_button( __( 'Upgrade to Pro', 'permalink-manager' ) );
                        ?>
                    <?php endif; ?>
                </div>

                <div>
                    <button type="button" class="button button-secondary pm-setup-back" hidden><?php esc_html_e( 'Back', 'permalink-manager' ); ?></button>
                    <button type="button" class="button button-primary pm-setup-next"><?php esc_html_e( 'Next', 'permalink-manager' ); ?></button>
                    <button type="submit" class="button button-primary pm-setup-finish" hidden><?php esc_html_e( 'Finish setup', 'permalink-manager' ); ?></button>
                </div>
            </div>
        </form>
        <?php
        $this->render_container_close();
    }

    /**
     * Render step 3: the license key field with its current validation status.
     *
     * @param int $step_number Step index used for the data-step attribute.
     */
    protected function render_license_step( $step_number ) {
        $license_key   = Permalink_Manager_Pro_License::get_license_key();
        $from_constant = ( defined( 'PMP_LICENCE_KEY' ) || defined( 'PMP_LICENSE_KEY' ) );

        $field_args = array(
                'type'        => $from_constant ? 'password' : 'text',
                'label'       => __( 'License key', 'permalink-manager' ),
                'value'       => $license_key,
                'container'   => 'row',
                'input_class' => 'widefat',
                'disabled'    => $from_constant,
                'description' => __( 'You can find your license key in the purchase confirmation email.', 'permalink-manager' )
        );

        if ( $from_constant ) {
            $field_args['after_description'] .= sprintf( '<p class="field-description description">%s</p>', esc_html__( 'The license key is defined in wp-config.php and cannot be changed here.', 'permalink-manager' ) );
        }
        ?>
        <div class="pm-setup-step" data-step="<?php echo absint( $step_number ); ?>" hidden>
            <p class="description"><?php esc_html_e( 'Enter your Permalink Manager Pro license key to unlock automatic updates and technical support. If your license has expired, paste a renewed key here and finish the setup to re-validate it.', 'permalink-manager' ); ?></p>

            <table class="form-table" role="presentation">
                <tbody>
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in generate_option_field().
                echo Permalink_Manager_UI_Elements::generate_option_field( 'licence[licence_key]', $field_args );
                ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render the "setup complete" screen shown after saving.
     */
    protected function render_done_screen() {
        $is_pro       = self::is_pro();
        $editor_url   = self::admin_url_for( 'permastructs' );
        $settings_url = self::admin_url_for( 'settings' );

        $this->render_container_open( __( 'Setup Complete', 'permalink-manager' ), '', true );
        ?>
        <div class="pm-setup-success">
            <p class="description pm-lead"><?php echo wp_kses_post( __( 'Congratulations! The plugin has been successfully configured and is ready to use.', 'permalink-manager' ) ); ?></p>

            <p class="pm-setup-actions">
                <a href="<?php echo esc_url( $editor_url ); ?>" class="button button-primary"><?php esc_html_e( 'Edit permalink structures', 'permalink-manager' ); ?></a>
                <a href="<?php echo esc_url( $settings_url ); ?>" class="button button-secondary"><?php esc_html_e( 'All settings', 'permalink-manager' ); ?></a>
            </p>

            <?php if ( ! $is_pro ) : ?>
                <?php $this->render_pro_box(); ?>
            <?php endif; ?>
        </div>
        <?php
        $this->render_container_close();
    }

    /**
     * Render the "Unlock all features" upsell shown to free users on the done screen.
     */
    protected function render_pro_box() {
        $allowed_tags = array(
                'code'   => array(),
                'strong' => array(),
        );

        $features = array(
                array(
                        'label' => esc_html__( 'Posts, pages & custom post type permalinks', 'permalink-manager' ),
                        'lite'  => true,
                ),
                array(
                        'label' => esc_html__( 'Permalink formats & bulk URL updates', 'permalink-manager' ),
                        'lite'  => true,
                ),
                array(
                        'label' => esc_html__( 'Automatic redirect for original URL', 'permalink-manager' ),
                        'lite'  => true,
                ),
                array(
                        'label' => esc_html__( 'Translated permalinks (WPML, Polylang)', 'permalink-manager' ),
                        'lite'  => true,
                ),
                array(
                        'label' => esc_html__( 'Automatic redirects after custom permalink changes', 'permalink-manager' ),
                        'lite'  => false,
                ),
                array(
                        'label' => esc_html__( 'Change category & custom taxonomy permalinks', 'permalink-manager' ),
                        'lite'  => false,
                ),
                array(
                        'label' => sprintf( /* translators: 1: /product-category/ URL slug. 2: /product-tag/ URL slug. */ esc_html__( 'Remove %1$s and %2$s from WooCommerce URLs', 'permalink-manager' ), '<code>/product-category/</code>', '<code>/product-tag/</code>' ),
                        'lite'  => false,
                ),
                array(
                        'label' => esc_html__( 'Add custom fields to permalinks (ACF, Pods, JetEngine, Toolset)', 'permalink-manager' ),
                        'lite'  => false,
                ),
                array(
                        'label' => esc_html__( 'Add SKUs to product URLs & edit WooCommerce coupon links', 'permalink-manager' ),
                        'lite'  => false,
                ),
                array(
                        'label' => esc_html__( 'Additional & external redirects', 'permalink-manager' ),
                        'lite'  => false,
                ),
                array(
                        'label' => esc_html__( 'Priority support from the developer', 'permalink-manager' ),
                        'lite'  => false,
                ),
        );

        // Keeps the footer headline in sync with the table above it.
        $pro_only = 0;
        foreach ( $features as $feature ) {
            if ( empty( $feature['lite'] ) ) {
                $pro_only ++;
            }
        }
        ?>
        <div class="pm-setup-pro">
            <h2 class="pm-pro-title"><?php esc_html_e( 'Permalink Manager Lite vs Pro', 'permalink-manager' ); ?></h2>

            <p class="description pm-lead"><?php esc_html_e( 'You are currently using the Lite version. The Pro version adds advanced controls for custom taxonomies, extra redirects, and WooCommerce URLs.', 'permalink-manager' ); ?></p>

            <div class="pm-pro-compare">
                <table class="pm-pro-table">
                    <thead>
                    <tr>
                        <th scope="col" class="pm-col-feature"><?php esc_html_e( 'Feature', 'permalink-manager' ); ?></th>
                        <th scope="col" class="pm-col-mark"><?php esc_html_e( 'Lite', 'permalink-manager' ); ?></th>
                        <th scope="col" class="pm-col-mark pm-col-pro"><?php esc_html_e( 'Pro', 'permalink-manager' ); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $features as $feature ) : ?>
                        <tr<?php echo empty( $feature['lite'] ) ? ' class="pm-row-locked"' : ''; ?>>
                            <th scope="row" class="pm-col-feature"><?php echo wp_kses( $feature['label'], $allowed_tags ); ?></th>
                            <td class="pm-col-mark">
                                <?php if ( ! empty( $feature['lite'] ) ) : ?>
                                    <span class="pm-mark pm-mark-yes" aria-hidden="true">&#10003;</span>
                                    <span class="screen-reader-text"><?php esc_html_e( 'Included in Lite', 'permalink-manager' ); ?></span>
                                <?php else : ?>
                                    <span class="pm-mark pm-mark-no" aria-hidden="true">&#8212;</span>
                                    <span class="screen-reader-text"><?php esc_html_e( 'Not included in Lite', 'permalink-manager' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="pm-col-mark pm-col-pro">
                                <span class="pm-mark pm-mark-yes" aria-hidden="true">&#10003;</span>
                                <span class="screen-reader-text"><?php esc_html_e( 'Included in Pro', 'permalink-manager' ); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="pm-pro-footer">
                    <div class="pm-pro-footer-copy">
                        <strong><?php esc_html_e( 'Unlock All Features With Permalink Manager Pro', 'permalink-manager' ); ?></strong>
                        <span><?php esc_html_e( 'All existing settings and custom permalinks are preserved when upgrading to the Pro version.', 'permalink-manager' ); ?></span>
                    </div>

                    <div class="pm-pro-cta">
                        <?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static markup with escaped values.
                        echo $this->upgrade_button( __( 'Upgrade to Pro', 'permalink-manager' ) );
                        ?>
                    </div>
                </div>
            </div>

            <p class="pm-pro-proof">
                <span class="pm-stars" aria-hidden="true">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
                <?php esc_html_e( '4.8/5 on WordPress.org', 'permalink-manager' ); ?>
                <span class="pm-sep" aria-hidden="true">&middot;</span>
                <?php esc_html_e( '100,000+ active installs', 'permalink-manager' ); ?>
                <span class="pm-sep" aria-hidden="true">&middot;</span>
                <?php esc_html_e( '14-day money-back guarantee', 'permalink-manager' ); ?>
            </p>
        </div>
        <?php
    }


    /**
     * Handle the wizard form submission and redirect to the completion screen.
     */
    public function maybe_handle_submit() {
        if ( empty( $_POST['pm_setup_nonce'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_key( $_POST['pm_setup_nonce'] ), 'permalink-manager' ) ) {
            return;
        }

        $this->save_choices();

        $redirect_url = self::get_page_url( array( 'done' => 1, 'pm_setup_nonce' => wp_create_nonce( 'pm_setup_done' ) ) );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Persist the choices made in the wizard into the plugin options.
     *
     * The wizard collects the content types that SHOULD use custom permalinks.
     * The plugin stores the ones to IGNORE, so the exclusion list is the
     * complement of the selection within the available universe.
     */
    protected function save_choices() {
        global $permalink_manager_options;

        $is_pro = self::is_pro();

        // Start from the current options (already filled with defaults) so that
        // nothing else is lost, then override only the wizard fields.
        $options = ( is_array( $permalink_manager_options ) ) ? $permalink_manager_options : array();

        if ( empty( $options['general'] ) || ! is_array( $options['general'] ) ) {
            $options['general'] = array();
        }

        if ( ! isset( $options['general']['partial_disable'] ) || ! is_array( $options['general']['partial_disable'] ) ) {
            $options['general']['partial_disable'] = array();
        }

        // --- Step 1: content types -> exclusion lists -------------------------
        $content_types  = self::get_supported_content_types();
        $all_post_types = array_keys( $content_types['post_types'] );
        $all_taxonomies = array_keys( $content_types['taxonomies'] );

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- The nonce is validated in maybe_handle_submit().
        $selected_post_types = isset( $_POST['post_types'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['post_types'] ) ) : array();
        $selected_post_types = array_values( array_intersect( $all_post_types, $selected_post_types ) );
        $excluded_post_types = array_values( array_diff( $all_post_types, $selected_post_types ) );

        $options['general']['partial_disable']['post_types'] = $excluded_post_types;

        // Taxonomies are a Pro feature. Only touch them when Pro is active.
        if ( $is_pro ) {
            $selected_taxonomies = isset( $_POST['taxonomies'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['taxonomies'] ) ) : array();
            $selected_taxonomies = array_values( array_intersect( $all_taxonomies, $selected_taxonomies ) );
            $excluded_taxonomies = array_values( array_diff( $all_taxonomies, $selected_taxonomies ) );

            $options['general']['partial_disable']['taxonomies'] = $excluded_taxonomies;
        }

        // --- Step 2: basic options -------------------------------------------
        $options['general']['force_unique_uris'] = ( ! empty( $_POST['force_unique_uris'] ) ) ? 1 : 0;

        // Value 2 = ignore both drafts AND pending posts (matches the label).
        $options['general']['ignore_drafts'] = ( ! empty( $_POST['ignore_drafts'] ) ) ? 2 : 0;

        // "Save old URLs as redirects" needs both extra_redirects and setup_redirects.
        if ( $is_pro ) {
            $save_redirects = ( ! empty( $_POST['save_redirects'] ) ) ? 1 : 0;

            $options['general']['extra_redirects'] = $save_redirects;
            $options['general']['setup_redirects'] = $save_redirects;

            // --- Step 3: license key -----------------------------------------
            $submitted_key = isset( $_POST['licence']['licence_key'] ) ? preg_replace( '/[^a-zA-Z0-9-]/', '', sanitize_text_field( wp_unslash( $_POST['licence']['licence_key'] ) ) ) : '';

            // Re-read the freshly stored licence subtree (post-validation).
            $stored         = get_option( 'permalink-manager', array() );
            $stored_licence = ( ! empty( $stored['licence'] ) && is_array( $stored['licence'] ) ) ? $stored['licence'] : array();

            if ( '' !== $submitted_key ) {
                // On multisite the key lives in a site option managed by the
                // licence class, so it is only persisted here on single sites.
                if ( ! is_multisite() ) {
                    $stored_licence['licence_key'] = $submitted_key;
                }

                // Force a fresh remote check on the next license lookup.
                delete_site_transient( 'permalink_manager_active' );
            }

            if ( ! empty( $stored_licence ) ) {
                $options['licence'] = $stored_licence;
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        // Persist. Writing a non-empty value here also stops the setup notice from appearing again.
        $permalink_manager_options = $options;
        update_option( 'permalink-manager', $options );

        // Remove the dismissal flag (no longer relevant once configured).
        delete_transient( self::DISMISS_TRANSIENT );
    }

    /**
     * Remember that the setup notice was dismissed.
     */
    public function ajax_dismiss_setup_wizard() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'forbidden', 403 );
        }

        $nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
        if ( ! wp_verify_nonce( $nonce, 'permalink-manager' ) ) {
            wp_send_json_error( 'invalid_nonce', 400 );
        }

        set_transient( self::DISMISS_TRANSIENT, 1, MONTH_IN_SECONDS );

        wp_send_json_success();
    }
}