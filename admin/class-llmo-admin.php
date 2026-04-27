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
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_llmo_optimize_post', array($this, 'ajax_optimize_post'));
        add_action('wp_ajax_llmo_test_connection', array($this, 'ajax_test_connection'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('LLMO Blog Optimizer', 'llmo-blog-optimizer'),
            __('LLMO Optimizer', 'llmo-blog-optimizer'),
            'manage_options',
            'llmo-blog-optimizer',
            array($this, 'render_settings_page'),
            'dashicons-chart-line',
            30
        );
        
        add_submenu_page(
            'llmo-blog-optimizer',
            __('Settings', 'llmo-blog-optimizer'),
            __('Settings', 'llmo-blog-optimizer'),
            'manage_options',
            'llmo-blog-optimizer',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'llmo-blog-optimizer',
            __('Bulk Optimizer', 'llmo-blog-optimizer'),
            __('Bulk Optimizer', 'llmo-blog-optimizer'),
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
        ));
        
        register_setting('llmo_blog_optimizer_settings', 'llmo_blog_optimizer_auto_optimize', array(
            'type' => 'string',
            'default' => 'yes',
        ));
        
        register_setting('llmo_blog_optimizer_settings', 'llmo_blog_optimizer_post_types', array(
            'type' => 'array',
            'default' => array('post'),
        ));
        
        add_settings_section(
            'llmo_blog_optimizer_api_section',
            __('API Configuration', 'llmo-blog-optimizer'),
            array($this, 'render_api_section'),
            'llmo-blog-optimizer'
        );
        
        add_settings_field(
            'llmo_blog_optimizer_api_key',
            __('API Key', 'llmo-blog-optimizer'),
            array($this, 'render_api_key_field'),
            'llmo-blog-optimizer',
            'llmo_blog_optimizer_api_section'
        );
        
        register_setting('llmo_blog_optimizer_settings', 'llmo_blog_optimizer_consent', array(
            'type' => 'string',
            'default' => '',
        ));
        
        add_settings_field(
            'llmo_blog_optimizer_consent',
            __('Data Processing Consent', 'llmo-blog-optimizer'),
            array($this, 'render_consent_field'),
            'llmo-blog-optimizer',
            'llmo_blog_optimizer_api_section'
        );
        
        add_settings_section(
            'llmo_blog_optimizer_general_section',
            __('General Settings', 'llmo-blog-optimizer'),
            array($this, 'render_general_section'),
            'llmo-blog-optimizer'
        );
        
        add_settings_field(
            'llmo_blog_optimizer_auto_optimize',
            __('Auto-Optimize', 'llmo-blog-optimizer'),
            array($this, 'render_auto_optimize_field'),
            'llmo-blog-optimizer',
            'llmo_blog_optimizer_general_section'
        );
        
        add_settings_field(
            'llmo_blog_optimizer_post_types',
            __('Post Types', 'llmo-blog-optimizer'),
            array($this, 'render_post_types_field'),
            'llmo-blog-optimizer',
            'llmo_blog_optimizer_general_section'
        );
        
        // Organization section
        add_settings_section(
            'llmo_blog_optimizer_organization_section',
            __('Organization / Business Information', 'llmo-blog-optimizer'),
            array($this, 'render_organization_section'),
            'llmo-blog-optimizer'
        );
        
        // Organization fields
        $org_fields = array(
            'llmo_organization_type' => __('Type', 'llmo-blog-optimizer'),
            'llmo_organization_name' => __('Name', 'llmo-blog-optimizer'),
            'llmo_organization_phone' => __('Phone', 'llmo-blog-optimizer'),
            'llmo_organization_email' => __('Email', 'llmo-blog-optimizer'),
            'llmo_organization_street' => __('Street Address', 'llmo-blog-optimizer'),
            'llmo_organization_city' => __('City', 'llmo-blog-optimizer'),
            'llmo_organization_postal' => __('Postal Code', 'llmo-blog-optimizer'),
            'llmo_organization_country' => __('Country', 'llmo-blog-optimizer'),
        );
        
        foreach ($org_fields as $field_id => $field_label) {
            register_setting('llmo_blog_optimizer_settings', $field_id, array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
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
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'llmo-') === false && $hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
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
        
        wp_localize_script('llmo-blog-optimizer-admin', 'llmoAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('llmo_admin_nonce'),
            'strings' => array(
                'optimizing' => __('Optimizing...', 'llmo-blog-optimizer'),
                'optimized' => __('Optimized!', 'llmo-blog-optimizer'),
                'error' => __('Error occurred', 'llmo-blog-optimizer'),
                'confirm_reoptimize' => __('Are you sure you want to re-optimize this post?', 'llmo-blog-optimizer'),
            ),
        ));
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php settings_errors(); ?>
            
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
        echo '<p>' . esc_html__('Configure your API connection and data processing consent.', 'llmo-blog-optimizer') . '</p>';
        echo '<p>' . esc_html__('You must provide consent before any content is sent to our API for optimization.', 'llmo-blog-optimizer') . '</p>';
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
               placeholder="<?php esc_attr_e('Enter your API key', 'llmo-blog-optimizer'); ?>">
        <button type="button" class="button button-secondary" id="llmo-test-connection">
            <?php esc_html_e('Test Connection', 'llmo-blog-optimizer'); ?>
        </button>
        <span id="llmo-connection-status"></span>
        <p class="description">
            <?php printf(
                esc_html__('Get your API key from %s', 'llmo-blog-optimizer'),
                '<a href="https://llmoready.com/dashboard" target="_blank">llmoready.com</a>'
            ); ?>
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
        <label style="display: block; margin-bottom: 10px;">
            <input type="checkbox" 
                   name="llmo_blog_optimizer_consent" 
                   value="yes" 
                   <?php checked($has_consent); ?>>
            <strong><?php esc_html_e('I consent to sending my post content to LLMOReady.com for AI optimization and processing.', 'llmo-blog-optimizer'); ?></strong>
        </label>
        
        <p class="description" style="margin-left: 24px; margin-top: 8px;">
            <?php esc_html_e('By checking this box, you agree that your post content (title, content, excerpt) will be sent to LLMOReady.com via secure HTTPS for AI optimization and analysis.', 'llmo-blog-optimizer'); ?>
        </p>
        
        <p class="description" style="margin-left: 24px; margin-top: 8px;">
            <?php printf(
                esc_html__('Please review our %1$s and %2$s before proceeding.', 'llmo-blog-optimizer'),
                '<a href="https://llmoready.com/privacy" target="_blank">' . esc_html__('Privacy Policy', 'llmo-blog-optimizer') . '</a>',
                '<a href="https://llmoready.com/terms" target="_blank">' . esc_html__('Terms of Use', 'llmo-blog-optimizer') . '</a>'
            ); ?>
        </p>
        
        <?php if (!$has_consent): ?>
            <div class="notice notice-warning inline" style="margin-top: 15px; margin-left: 24px;">
                <p>
                    <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
                    <strong><?php esc_html_e('Consent required:', 'llmo-blog-optimizer'); ?></strong>
                    <?php esc_html_e('You must check this box and save settings before optimizing posts. No data will be sent without your explicit consent.', 'llmo-blog-optimizer'); ?>
                </p>
            </div>
        <?php else: ?>
            <div class="notice notice-success inline" style="margin-top: 15px; margin-left: 24px;">
                <p>
                    <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                    <strong><?php esc_html_e('Consent given.', 'llmo-blog-optimizer'); ?></strong>
                    <?php esc_html_e('You can now optimize your posts. You may withdraw consent at any time by unchecking this box.', 'llmo-blog-optimizer'); ?>
                </p>
            </div>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Render general section
     */
    public function render_general_section() {
        echo '<p>' . esc_html__('Configure general optimization settings.', 'llmo-blog-optimizer') . '</p>';
    }
    
    /**
     * Render auto-optimize field
     */
    public function render_auto_optimize_field() {
        $auto_optimize = get_option('llmo_blog_optimizer_auto_optimize', 'yes');
        ?>
        <label>
            <input type="checkbox" 
                   name="llmo_blog_optimizer_auto_optimize" 
                   value="yes" 
                   <?php checked($auto_optimize, 'yes'); ?>>
            <?php esc_html_e('Automatically optimize posts when published', 'llmo-blog-optimizer'); ?>
        </label>
        <?php
    }
    
    /**
     * Render post types field
     */
    public function render_post_types_field() {
        $selected_post_types = get_option('llmo_blog_optimizer_post_types', array('post'));
        $post_types = get_post_types(array('public' => true), 'objects');
        
        foreach ($post_types as $post_type) {
            if (in_array($post_type->name, array('attachment', 'revision', 'nav_menu_item'))) {
                continue;
            }
            ?>
            <label style="display: block; margin-bottom: 5px;">
                <input type="checkbox" 
                       name="llmo_blog_optimizer_post_types[]" 
                       value="<?php echo esc_attr($post_type->name); ?>" 
                       <?php checked(in_array($post_type->name, $selected_post_types)); ?>>
                <?php echo esc_html($post_type->label); ?>
            </label>
            <?php
        }
    }
    
    /**
     * Render organization section
     */
    public function render_organization_section() {
        echo '<p>' . esc_html__('Configure your organization information for Schema.org markup on the homepage.', 'llmo-blog-optimizer') . '</p>';
    }
    
    /**
     * Render generic text field
     */
    public function render_text_field($args) {
        $field_id = $args['field_id'];
        $value = get_option($field_id, '');
        
        if ($field_id === 'llmo_organization_type') {
            ?>
            <select name="<?php echo esc_attr($field_id); ?>" class="regular-text">
                <option value="Organization" <?php selected($value, 'Organization'); ?>>Organization</option>
                <option value="LocalBusiness" <?php selected($value, 'LocalBusiness'); ?>>Local Business</option>
                <option value="Corporation" <?php selected($value, 'Corporation'); ?>>Corporation</option>
                <option value="EducationalOrganization" <?php selected($value, 'EducationalOrganization'); ?>>Educational Organization</option>
                <option value="GovernmentOrganization" <?php selected($value, 'GovernmentOrganization'); ?>>Government Organization</option>
                <option value="NGO" <?php selected($value, 'NGO'); ?>>NGO</option>
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
     * AJAX: Optimize post
     */
    public function ajax_optimize_post() {
        check_ajax_referer('llmo_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'llmo-blog-optimizer')));
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid post ID', 'llmo-blog-optimizer')));
        }
        
        $optimizer = LLMO_Blog_Optimizer::get_instance();
        $result = $optimizer->optimize_post($post_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array(
            'message' => __('Post optimized successfully', 'llmo-blog-optimizer'),
            'score' => get_post_meta($post_id, '_llmo_ai_readiness_score', true),
        ));
    }
    
    /**
     * AJAX: Test API connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('llmo_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'llmo-blog-optimizer')));
        }
        
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        
        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('API key is required', 'llmo-blog-optimizer')));
        }
        
        $api_client = new LLMO_API_Client($api_key);
        $connected = $api_client->test_connection();
        
        if ($connected) {
            wp_send_json_success(array('message' => __('Connection successful!', 'llmo-blog-optimizer')));
        } else {
            wp_send_json_error(array('message' => __('Connection failed. Please check your API key.', 'llmo-blog-optimizer')));
        }
    }
}
