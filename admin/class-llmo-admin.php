<?php
/**
 * LLMO Admin Interface
 *
 * @package LLMO_Blog_Optimizer
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Class
 */
class LLMO_Blog_Optimizer_Admin {

    /**
     * Optional post-connect redirect URL (set during admin_init, consumed in enqueue).
     *
     * @var string
     */
    private $post_connect_redirect = '';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_api_token_callback'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_llmo_optimize_post', array($this, 'ajax_optimize_post'));
        add_action('wp_ajax_llmo_poll_optimization', array($this, 'ajax_poll_optimization'));
        add_action('wp_ajax_llmo_test_connection', array($this, 'ajax_test_connection'));
    }
    
    /**
     * Handle API token callback from LLMO Ready.
     *
     * After login/register, the user is redirected back with
     * ?api_token=xxx&connected=1&llmo_state=yyy
     *
     * The token is delivered via GET because the connect flow is an external
     * browser redirect (same pattern as OAuth return URLs). We harden it with:
     * - manage_options capability
     * - one-time llmo_state (user transient) embedded in return_url
     * - sanitize_text_field on the token
     * - immediate redirect that strips the token from the address bar
     */
    public function handle_api_token_callback() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- External redirect; protected by capability + one-time state.
        if (!isset($_GET['page']) || sanitize_text_field(wp_unslash($_GET['page'])) !== 'llmo-blog-optimizer') {
            return;
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- External redirect; see above.
        if (!isset($_GET['api_token']) || !isset($_GET['connected'])) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- External redirect; see above.
        $state = isset($_GET['llmo_state']) ? sanitize_text_field(wp_unslash($_GET['llmo_state'])) : '';
        $expected = get_transient('llmo_connect_state_' . get_current_user_id());
        if (empty($state) || empty($expected) || !hash_equals((string) $expected, $state)) {
            return;
        }
        delete_transient('llmo_connect_state_' . get_current_user_id());
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- External redirect; see above.
        $api_token = sanitize_text_field(wp_unslash($_GET['api_token']));
        
        if (!empty($api_token)) {
            update_option('llmo_blog_optimizer_api_key', $api_token);

            // Enqueued via admin_enqueue_scripts (no raw <script> echo).
            $this->post_connect_redirect = admin_url('admin.php?page=llmo-blog-optimizer&llmo_connected=1');
        }
    }

    /**
     * Build connect/register URLs with a one-time state in return_url.
     *
     * @return array{connect:string,register:string}
     */
    private function get_connect_urls() {
        $state = wp_generate_password(32, false);
        set_transient('llmo_connect_state_' . get_current_user_id(), $state, HOUR_IN_SECONDS);

        $return_url = add_query_arg(
            array(
                'page' => 'llmo-blog-optimizer',
                'llmo_state' => $state,
            ),
            admin_url('admin.php')
        );

        $site_url = rawurlencode(get_site_url());
        $encoded_return = rawurlencode($return_url);

        return array(
            'connect' => 'https://app.libers.ai/login?site=' . $site_url . '&from=plugin&return_url=' . $encoded_return,
            'register' => 'https://libers.ai/plugin/register?site=' . $site_url . '&return_url=' . $encoded_return,
        );
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('LLMO Blog Optimizer', 'llmo-ready-blog-optimizer'),
            __('LLMO Optimizer', 'llmo-ready-blog-optimizer'),
            'manage_options',
            'llmo-blog-optimizer',
            array($this, 'render_settings_page'),
            'dashicons-chart-line',
            30
        );
        
        add_submenu_page(
            'llmo-blog-optimizer',
            __('Settings', 'llmo-ready-blog-optimizer'),
            __('Settings', 'llmo-ready-blog-optimizer'),
            'manage_options',
            'llmo-blog-optimizer',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'llmo-blog-optimizer',
            __('Bulk Optimizer', 'llmo-ready-blog-optimizer'),
            __('Bulk Optimizer', 'llmo-ready-blog-optimizer'),
            'manage_options',
            'llmo-bulk-optimizer',
            array($this, 'render_bulk_optimizer_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('llmo_blog_optimizer_settings', 'llmo_blog_optimizer_api_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ));
        
        register_setting('llmo_blog_optimizer_settings', 'llmo_blog_optimizer_auto_optimize', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_yes_checkbox'),
            'default' => 'yes',
        ));
        
        register_setting('llmo_blog_optimizer_settings', 'llmo_blog_optimizer_post_types', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_post_types'),
            'default' => array('post'),
        ));

        register_setting('llmo_blog_optimizer_settings', 'llmo_blog_optimizer_consent', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_yes_checkbox'),
            'default' => '',
        ));
        
        add_settings_section(
            'llmo_blog_optimizer_api_section',
            __('API Configuration', 'llmo-ready-blog-optimizer'),
            array($this, 'render_api_section'),
            'llmo-blog-optimizer'
        );
        
        add_settings_field(
            'llmo_blog_optimizer_api_key',
            __('API Key', 'llmo-ready-blog-optimizer'),
            array($this, 'render_api_key_field'),
            'llmo-blog-optimizer',
            'llmo_blog_optimizer_api_section'
        );
        
        add_settings_field(
            'llmo_blog_optimizer_consent',
            __('Data Processing Consent', 'llmo-ready-blog-optimizer'),
            array($this, 'render_consent_field'),
            'llmo-blog-optimizer',
            'llmo_blog_optimizer_api_section'
        );
        
        add_settings_section(
            'llmo_blog_optimizer_general_section',
            __('General Settings', 'llmo-ready-blog-optimizer'),
            array($this, 'render_general_section'),
            'llmo-blog-optimizer'
        );
        
        add_settings_field(
            'llmo_blog_optimizer_auto_optimize',
            __('Auto-Optimize', 'llmo-ready-blog-optimizer'),
            array($this, 'render_auto_optimize_field'),
            'llmo-blog-optimizer',
            'llmo_blog_optimizer_general_section'
        );
        
        add_settings_field(
            'llmo_blog_optimizer_post_types',
            __('Post Types', 'llmo-ready-blog-optimizer'),
            array($this, 'render_post_types_field'),
            'llmo-blog-optimizer',
            'llmo_blog_optimizer_general_section'
        );
        
        // Organization section
        add_settings_section(
            'llmo_blog_optimizer_organization_section',
            __('Organization / Business Information', 'llmo-ready-blog-optimizer'),
            array($this, 'render_organization_section'),
            'llmo-blog-optimizer'
        );
        
        // Organization fields
        $org_fields = array(
            'llmo_organization_type' => __('Type', 'llmo-ready-blog-optimizer'),
            'llmo_organization_name' => __('Name', 'llmo-ready-blog-optimizer'),
            'llmo_organization_phone' => __('Phone', 'llmo-ready-blog-optimizer'),
            'llmo_organization_email' => __('Email', 'llmo-ready-blog-optimizer'),
            'llmo_organization_street' => __('Street Address', 'llmo-ready-blog-optimizer'),
            'llmo_organization_city' => __('City', 'llmo-ready-blog-optimizer'),
            'llmo_organization_postal' => __('Postal Code', 'llmo-ready-blog-optimizer'),
            'llmo_organization_country' => __('Country', 'llmo-ready-blog-optimizer'),
        );
        
        foreach ($org_fields as $field_id => $field_label) {
            $sanitize = ($field_id === 'llmo_organization_email') ? 'sanitize_email' : 'sanitize_text_field';
            register_setting('llmo_blog_optimizer_settings', $field_id, array(
                'type' => 'string',
                'sanitize_callback' => $sanitize,
                'default' => '',
            ));
            
            add_settings_field(
                $field_id,
                $field_label,
                array($this, 'render_text_field'),
                'llmo-blog-optimizer',
                'llmo_blog_optimizer_organization_section',
                array('field_id' => $field_id)
            );
        }
    }

    /**
     * Sanitize yes/empty checkbox options (clears when unchecked).
     *
     * @param mixed $value Raw option value.
     * @return string
     */
    public function sanitize_yes_checkbox($value) {
        return ($value === 'yes') ? 'yes' : '';
    }

    /**
     * Sanitize selected public post types.
     *
     * @param mixed $value Raw option value.
     * @return array
     */
    public function sanitize_post_types($value) {
        if (!is_array($value)) {
            return array('post');
        }

        $public_types = array_keys(get_post_types(array('public' => true), 'names'));
        $sanitized = array();

        foreach ($value as $post_type) {
            $post_type = sanitize_key($post_type);
            if ($post_type === '') {
                continue;
            }
            if (in_array($post_type, $public_types, true) && $post_type !== 'attachment') {
                $sanitized[] = $post_type;
            }
        }

        return !empty($sanitized) ? array_values(array_unique($sanitized)) : array('post');
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {
        $is_llmo_page = (strpos($hook, 'llmo-') !== false);
        $is_post_screen = ($hook === 'post.php' || $hook === 'post-new.php');

        if (!$is_llmo_page && !$is_post_screen) {
            return;
        }

        if ($is_post_screen) {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            $allowed = get_option('llmo_blog_optimizer_post_types', array('post'));
            if (!$screen || empty($screen->post_type) || !in_array($screen->post_type, (array) $allowed, true)) {
                return;
            }
        }
        
        wp_enqueue_style(
            'llmo-blog-optimizer-admin',
            LLMO_BLOG_OPTIMIZER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            LLMO_BLOG_OPTIMIZER_VERSION
        );
        
        wp_enqueue_script(
            'llmo-blog-optimizer-admin',
            LLMO_BLOG_OPTIMIZER_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            LLMO_BLOG_OPTIMIZER_VERSION,
            true
        );
        
        $localize = array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('llmo_admin_nonce'),
            'pollInterval' => 10000,
            'pollMaxAttempts' => 30,
            'strings' => array(
                'optimizing' => __('Optimizing...', 'llmo-ready-blog-optimizer'),
                'optimized' => __('Optimized!', 'llmo-ready-blog-optimizer'),
                'error' => __('Error occurred', 'llmo-ready-blog-optimizer'),
                'confirm_reoptimize' => __('Are you sure you want to re-optimize this post?', 'llmo-ready-blog-optimizer'),
                'testing' => __('Testing...', 'llmo-ready-blog-optimizer'),
                'testConnection' => __('Test Connection', 'llmo-ready-blog-optimizer'),
                'reoptimize' => __('Re-optimize', 'llmo-ready-blog-optimizer'),
                'optimizeNow' => __('Optimize Now', 'llmo-ready-blog-optimizer'),
                'noPending' => __('No pending posts to optimize', 'llmo-ready-blog-optimizer'),
                'selectPosts' => __('Please select posts to optimize', 'llmo-ready-blog-optimizer'),
                'optimizationComplete' => __('Optimization complete!', 'llmo-ready-blog-optimizer'),
                'optimizationTimeout' => __('Optimization is still running. Results will appear when ready.', 'llmo-ready-blog-optimizer'),
            ),
        );

        if (!empty($this->post_connect_redirect)) {
            $localize['redirectUrl'] = esc_url_raw($this->post_connect_redirect);
        }

        wp_localize_script('llmo-blog-optimizer-admin', 'llmoAdmin', $localize);
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        $api_key = get_option('llmo_blog_optimizer_api_key', '');
        $urls = $this->get_connect_urls();
        $connect_url = $urls['connect'];
        $register_url = $urls['register'];
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php settings_errors(); ?>
            
            <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag, no data processing. ?>
            <?php if (isset($_GET['llmo_connected'])): ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <span class="dashicons dashicons-yes-alt llmo-notice-icon-ok"></span>
                    <strong><?php esc_html_e('Successfully connected with LLMO Ready!', 'llmo-ready-blog-optimizer'); ?></strong>
                    <?php esc_html_e('Your website is being analyzed. You can now start optimizing your blog posts.', 'llmo-ready-blog-optimizer'); ?>
                </p>
            </div>
            <?php endif; ?>
            
            <?php if (empty($api_key)): ?>
            <div class="card llmo-connect-card llmo-connect-card--open">
                <h2 class="llmo-connect-card__title">
                    <span class="dashicons dashicons-admin-links llmo-connect-card__icon llmo-connect-card__icon--open"></span>
                    <?php esc_html_e('Connect with LLMO Ready', 'llmo-ready-blog-optimizer'); ?>
                </h2>
                <p class="llmo-connect-card__text">
                    <?php esc_html_e('To use the Blog Optimizer, connect your website with your LLMO Ready account. Your website will be automatically analyzed and you will receive an API token.', 'llmo-ready-blog-optimizer'); ?>
                </p>
                <ol class="llmo-connect-card__steps">
                    <li><?php esc_html_e('Click the button below to open LLMO Ready', 'llmo-ready-blog-optimizer'); ?></li>
                    <li><?php esc_html_e('Register or log in to your account', 'llmo-ready-blog-optimizer'); ?></li>
                    <li><?php esc_html_e('Your API token will be set up automatically', 'llmo-ready-blog-optimizer'); ?></li>
                </ol>
                <p class="llmo-connect-card__actions">
                    <a href="<?php echo esc_url($connect_url); ?>" class="button button-primary llmo-btn-connect">
                        <span class="dashicons dashicons-admin-links"></span>
                        <?php esc_html_e('Connect with LLMO Ready', 'llmo-ready-blog-optimizer'); ?>
                    </a>
                    <a href="<?php echo esc_url($register_url); ?>" class="button button-secondary llmo-btn-register">
                        <?php esc_html_e('Create free account', 'llmo-ready-blog-optimizer'); ?>
                    </a>
                </p>
            </div>
            <?php else: ?>
            <div class="card llmo-connect-card llmo-connect-card--ok">
                <h2 class="llmo-connect-card__title">
                    <span class="dashicons dashicons-yes-alt llmo-connect-card__icon llmo-connect-card__icon--ok"></span>
                    <?php esc_html_e('Connected with LLMO Ready', 'llmo-ready-blog-optimizer'); ?>
                </h2>
                <p class="llmo-connect-card__text">
                    <?php esc_html_e('Your website is connected. Blog posts will be automatically optimized with Schema.org markup for better AI visibility.', 'llmo-ready-blog-optimizer'); ?>
                </p>
                <p class="llmo-connect-card__actions--ok">
                    <a href="https://app.libers.ai/websites" target="_blank" rel="noopener noreferrer" class="button button-secondary llmo-btn-dashboard">
                        <span class="dashicons dashicons-external"></span>
                        <?php esc_html_e('Open LLMO Ready Dashboard', 'llmo-ready-blog-optimizer'); ?>
                    </a>
                </p>
            </div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('llmo_blog_optimizer_settings');
                do_settings_sections('llmo-blog-optimizer');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render bulk optimizer page
     */
    public function render_bulk_optimizer_page() {
        $bulk_optimizer = new LLMO_Blog_Optimizer_Bulk();
        $bulk_optimizer->render();
    }
    
    /**
     * Render API section
     */
    public function render_api_section() {
        echo '<p>' . esc_html__('Configure your API connection and data processing consent.', 'llmo-ready-blog-optimizer') . '</p>';
        echo '<p>' . esc_html__('You must provide consent before any content is sent to our API for optimization.', 'llmo-ready-blog-optimizer') . '</p>';
    }
    
    /**
     * Render API key field
     */
    public function render_api_key_field() {
        $api_key = get_option('llmo_blog_optimizer_api_key', '');
        ?>
        <input type="text" 
               name="llmo_blog_optimizer_api_key" 
               id="llmo_blog_optimizer_api_key" 
               value="<?php echo esc_attr($api_key); ?>" 
               class="regular-text"
               placeholder="<?php esc_attr_e('Enter your API key', 'llmo-ready-blog-optimizer'); ?>">
        <button type="button" class="button button-secondary" id="llmo-test-connection">
            <?php esc_html_e('Test Connection', 'llmo-ready-blog-optimizer'); ?>
        </button>
        <span id="llmo-connection-status"></span>
        <p class="description">
            <?php
            printf(
                /* translators: %s: Link to LLMO Ready app dashboard */
                esc_html__('Get your API key from %s', 'llmo-ready-blog-optimizer'),
                '<a href="https://app.libers.ai/websites" target="_blank" rel="noopener noreferrer">app.libers.ai</a>'
            );
            ?>
        </p>
        <?php
    }
    
    /**
     * Render consent field
     */
    public function render_consent_field() {
        $consent = get_option('llmo_blog_optimizer_consent', '');
        $has_consent = ($consent === 'yes');
        ?>
        <label class="llmo-consent-label">
            <input type="hidden" name="llmo_blog_optimizer_consent" value="">
            <input type="checkbox" 
                   name="llmo_blog_optimizer_consent" 
                   value="yes" 
                   <?php checked($has_consent); ?>>
            <strong><?php esc_html_e('I consent to sending my post content to Libers GmbH for AI optimization and processing.', 'llmo-ready-blog-optimizer'); ?></strong>
        </label>
        
        <p class="description llmo-consent-desc">
            <?php esc_html_e('By checking this box, you agree that your post content (title, content, excerpt) will be sent to Libers GmbH via secure HTTPS for AI optimization and analysis.', 'llmo-ready-blog-optimizer'); ?>
        </p>
        
        <p class="description llmo-consent-desc">
            <?php
            printf(
                /* translators: %1$s: Privacy Policy link, %2$s: Terms of Use link */
                esc_html__('Please review our %1$s and %2$s before proceeding.', 'llmo-ready-blog-optimizer'),
                '<a href="https://libers.ai/privacy" target="_blank" rel="noopener noreferrer">' . esc_html__('Privacy Policy', 'llmo-ready-blog-optimizer') . '</a>',
                '<a href="https://libers.ai/terms" target="_blank" rel="noopener noreferrer">' . esc_html__('Terms of Use', 'llmo-ready-blog-optimizer') . '</a>'
            );
            ?>
        </p>
        
        <?php if (!$has_consent): ?>
            <div class="notice notice-warning inline llmo-consent-notice">
                <p>
                    <span class="dashicons dashicons-warning"></span>
                    <strong><?php esc_html_e('Consent required:', 'llmo-ready-blog-optimizer'); ?></strong>
                    <?php esc_html_e('You must check this box and save settings before optimizing posts. No data will be sent without your explicit consent.', 'llmo-ready-blog-optimizer'); ?>
                </p>
            </div>
        <?php else: ?>
            <div class="notice notice-success inline llmo-consent-notice">
                <p>
                    <span class="dashicons dashicons-yes-alt"></span>
                    <strong><?php esc_html_e('Consent given.', 'llmo-ready-blog-optimizer'); ?></strong>
                    <?php esc_html_e('You can now optimize your posts. You may withdraw consent at any time by unchecking this box.', 'llmo-ready-blog-optimizer'); ?>
                </p>
            </div>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Render general section
     */
    public function render_general_section() {
        echo '<p>' . esc_html__('Configure general optimization settings.', 'llmo-ready-blog-optimizer') . '</p>';
    }
    
    /**
     * Render auto-optimize field
     */
    public function render_auto_optimize_field() {
        $auto_optimize = get_option('llmo_blog_optimizer_auto_optimize', 'yes');
        ?>
        <label>
            <input type="hidden" name="llmo_blog_optimizer_auto_optimize" value="">
            <input type="checkbox" 
                   name="llmo_blog_optimizer_auto_optimize" 
                   value="yes" 
                   <?php checked($auto_optimize, 'yes'); ?>>
            <?php esc_html_e('Automatically optimize posts when published', 'llmo-ready-blog-optimizer'); ?>
        </label>
        <?php
    }
    
    /**
     * Render post types field
     */
    public function render_post_types_field() {
        $selected_post_types = get_option('llmo_blog_optimizer_post_types', array('post'));
        if (!is_array($selected_post_types)) {
            $selected_post_types = array('post');
        }
        $post_types = get_post_types(array('public' => true), 'objects');

        // Hidden empty value ensures unchecking all post types still submits the field.
        echo '<input type="hidden" name="llmo_blog_optimizer_post_types[]" value="">';
        
        foreach ($post_types as $post_type) {
            if (in_array($post_type->name, array('attachment', 'revision', 'nav_menu_item'), true)) {
                continue;
            }
            ?>
            <label class="llmo-post-type-label">
                <input type="checkbox" 
                       name="llmo_blog_optimizer_post_types[]" 
                       value="<?php echo esc_attr($post_type->name); ?>" 
                       <?php checked(in_array($post_type->name, $selected_post_types, true)); ?>>
                <?php echo esc_html($post_type->label); ?>
            </label>
            <?php
        }
    }
    
    /**
     * Render organization section
     */
    public function render_organization_section() {
        echo '<p>' . esc_html__('Configure your organization information for Schema.org markup on the homepage.', 'llmo-ready-blog-optimizer') . '</p>';
    }
    
    /**
     * Render generic text field
     */
    public function render_text_field($args) {
        $field_id = $args['field_id'];
        $value = get_option($field_id, '');
        
        if ($field_id === 'llmo_organization_type') {
            $types = array(
                'Organization' => __('Organization', 'llmo-ready-blog-optimizer'),
                'LocalBusiness' => __('Local Business', 'llmo-ready-blog-optimizer'),
                'Corporation' => __('Corporation', 'llmo-ready-blog-optimizer'),
                'EducationalOrganization' => __('Educational Organization', 'llmo-ready-blog-optimizer'),
                'GovernmentOrganization' => __('Government Organization', 'llmo-ready-blog-optimizer'),
                'NGO' => __('NGO', 'llmo-ready-blog-optimizer'),
            );
            ?>
            <select name="<?php echo esc_attr($field_id); ?>" class="regular-text">
                <?php foreach ($types as $type_value => $type_label) : ?>
                    <option value="<?php echo esc_attr($type_value); ?>" <?php selected($value, $type_value); ?>><?php echo esc_html($type_label); ?></option>
                <?php endforeach; ?>
            </select>
            <?php
        } else {
            ?>
            <input type="text" 
                   name="<?php echo esc_attr($field_id); ?>" 
                   value="<?php echo esc_attr($value); ?>" 
                   class="regular-text">
            <?php
        }
    }
    
    /**
     * AJAX: Optimize post (queues job; JS polls for completion).
     */
    public function ajax_optimize_post() {
        check_ajax_referer('llmo_admin_nonce', 'nonce');
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid post ID', 'llmo-ready-blog-optimizer')));
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => __('Permission denied', 'llmo-ready-blog-optimizer')));
        }
        
        $optimizer = LLMO_Blog_Optimizer::get_instance();
        $result = $optimizer->optimize_post($post_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array(
            'message' => __('Optimization queued', 'llmo-ready-blog-optimizer'),
            'status' => 'pending',
            'post_id' => $post_id,
        ));
    }

    /**
     * AJAX: Poll optimization status for a single post.
     */
    public function ajax_poll_optimization() {
        check_ajax_referer('llmo_admin_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid post ID', 'llmo-ready-blog-optimizer')));
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => __('Permission denied', 'llmo-ready-blog-optimizer')));
        }

        $optimizer = LLMO_Blog_Optimizer::get_instance();
        $result = $optimizer->poll_optimization($post_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Test API connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('llmo_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'llmo-ready-blog-optimizer')));
        }
        
        $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
        
        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('API key is required', 'llmo-ready-blog-optimizer')));
        }
        
        $api_client = new LLMO_API_Client($api_key);
        $connected = $api_client->test_connection();
        
        if ($connected) {
            wp_send_json_success(array('message' => __('Connection successful!', 'llmo-ready-blog-optimizer')));
        } else {
            wp_send_json_error(array('message' => __('Connection failed. Please check your API key.', 'llmo-ready-blog-optimizer')));
        }
    }
}
