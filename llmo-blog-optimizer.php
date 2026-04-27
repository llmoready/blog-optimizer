<?php
/**
 * Plugin Name: LLMO Ready - Blog Optimizer
 * Plugin URI: https://wordpress.org/plugins/llmo-blog-optimizer/
 * Description: Automatically adds Schema.org JSON-LD markup with AI-optimized content from LLMO Ready to blog posts for better visibility in generative AI search engines (ChatGPT, Google SGE, Perplexity).
 * Version: 1.0.1
 * Author: LLMO Ready
 * Author URI: https://llmoready.com
 * Requires at least: 5.8
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Update URI: https://llmoready.com
 * Text Domain: llmo-blog-optimizer
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('LLMO_BLOG_OPTIMIZER_VERSION', '1.0.0');
define('LLMO_BLOG_OPTIMIZER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LLMO_BLOG_OPTIMIZER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LLMO_BLOG_OPTIMIZER_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class
 */
class LLMO_Blog_Optimizer {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * API endpoint
     */
    private $api_endpoint = 'https://llmoready.com/api';
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        require_once LLMO_BLOG_OPTIMIZER_PLUGIN_DIR . 'includes/class-llmo-api-client.php';
        require_once LLMO_BLOG_OPTIMIZER_PLUGIN_DIR . 'includes/class-llmo-article-detector.php';
        require_once LLMO_BLOG_OPTIMIZER_PLUGIN_DIR . 'includes/class-llmo-schema-generator.php';
        require_once LLMO_BLOG_OPTIMIZER_PLUGIN_DIR . 'includes/class-llmo-faq-shortcode.php';
        require_once LLMO_BLOG_OPTIMIZER_PLUGIN_DIR . 'includes/class-llmo-faq-widget.php';
        require_once LLMO_BLOG_OPTIMIZER_PLUGIN_DIR . 'admin/class-llmo-admin.php';
        require_once LLMO_BLOG_OPTIMIZER_PLUGIN_DIR . 'admin/class-llmo-bulk-optimizer.php';
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Activation/Deactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Admin hooks
        if (is_admin()) {
            $admin = new LLMO_Blog_Optimizer_Admin();
        }
        
        // Auto-optimize on post publish
        add_action('publish_post', array($this, 'auto_optimize_post'), 10, 2);
        
        // Add meta box to post editor
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        
        // Save post meta
        add_action('save_post', array($this, 'save_post_meta'));
        
        // Add Schema.org markup to head
        add_action('wp_head', array($this, 'output_schema_markup'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create options with defaults
        add_option('llmo_blog_optimizer_api_key', '');
        add_option('llmo_blog_optimizer_auto_optimize', 'yes');
        add_option('llmo_blog_optimizer_post_types', array('post'));
        add_option('llmo_blog_optimizer_version', LLMO_BLOG_OPTIMIZER_VERSION);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Check if user has given explicit consent
     * 
     * @return bool
     */
    private function has_explicit_consent() {
        return get_option('llmo_blog_optimizer_consent', '') === 'yes';
    }
    
    /**
     * Auto-optimize post on publish
     */
    public function auto_optimize_post($post_id, $post) {
        // Check if auto-optimize is enabled
        if (get_option('llmo_blog_optimizer_auto_optimize') !== 'yes') {
            return;
        }
        
        // CRITICAL: Check explicit consent before any data processing (WordPress.org compliance)
        if (!$this->has_explicit_consent()) {
            // Silently skip - don't break publishing flow
            return;
        }
        
        // Check if post type is enabled
        $enabled_post_types = get_option('llmo_blog_optimizer_post_types', array('post'));
        if (!in_array($post->post_type, $enabled_post_types)) {
            return;
        }
        
        // Check if already optimized
        if (get_post_meta($post_id, '_llmo_optimized', true)) {
            return;
        }
        
        // Trigger optimization
        $this->optimize_post($post_id);
    }
    
    /**
     * Optimize a single post
     */
    public function optimize_post($post_id) {
        $api_key = get_option('llmo_blog_optimizer_api_key');
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('API key not configured', 'llmo-blog-optimizer'));
        }
        
        $consent = get_option('llmo_blog_optimizer_consent');
        if ($consent !== 'yes') {
            return new WP_Error('no_consent', __('User consent required. Please enable data processing consent in plugin settings.', 'llmo-blog-optimizer'));
        }
        
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('invalid_post', __('Invalid post ID', 'llmo-blog-optimizer'));
        }
        
        // Initialize API client
        $api_client = new LLMO_API_Client($api_key, $this->api_endpoint);
        
        // Send post data to API
        $response = $api_client->optimize_article(array(
            'url' => get_permalink($post_id),
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'author' => get_the_author_meta('display_name', $post->post_author),
            'published_at' => $post->post_date,
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Save optimization results
        update_post_meta($post_id, '_llmo_optimized', true);
        update_post_meta($post_id, '_llmo_optimized_at', current_time('mysql'));
        update_post_meta($post_id, '_llmo_schema_org', $response['optimized_schema']);
        update_post_meta($post_id, '_llmo_faq', $response['optimized_faq']);
        update_post_meta($post_id, '_llmo_key_takeaways', $response['key_takeaways']);
        update_post_meta($post_id, '_llmo_ai_readiness_score', $response['ai_readiness_score']);
        
        return true;
    }
    
    /**
     * Add meta box to post editor
     */
    public function add_meta_box() {
        $post_types = get_option('llmo_blog_optimizer_post_types', array('post'));
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'llmo_blog_optimizer',
                __('LLMO Blog Optimizer', 'llmo-blog-optimizer'),
                array($this, 'render_meta_box'),
                $post_type,
                'side',
                'high'
            );
        }
    }
    
    /**
     * Render meta box content
     */
    public function render_meta_box($post) {
        wp_nonce_field('llmo_blog_optimizer_meta_box', 'llmo_blog_optimizer_nonce');
        
        // Check prerequisites
        $has_consent = $this->has_explicit_consent();
        $api_key = get_option('llmo_blog_optimizer_api_key');
        $has_api_key = !empty($api_key);
        $can_optimize = $has_consent && $has_api_key;
        
        // Get optimization status
        $optimized = get_post_meta($post->ID, '_llmo_optimized', true);
        $optimized_at = get_post_meta($post->ID, '_llmo_optimized_at', true);
        $ai_score = get_post_meta($post->ID, '_llmo_ai_readiness_score', true);
        ?>
        <div class="llmo-meta-box">
            <?php 
            // Show configuration warnings if setup incomplete
            if (!$can_optimize): 
                ?>
                <div style="background: #f0f0f1; border-left: 4px solid #d63638; padding: 12px; margin-bottom: 15px;">
                    <p style="margin: 0 0 8px 0; font-weight: 600;">
                        <span class="dashicons dashicons-warning" style="color: #d63638; vertical-align: middle;"></span>
                        <?php esc_html_e('Setup Required', 'llmo-blog-optimizer'); ?>
                    </p>
                    <ul style="margin: 0; padding-left: 20px; font-size: 12px;">
                        <?php if (!$has_api_key): ?>
                            <li><?php esc_html_e('Enter your API key in Settings', 'llmo-blog-optimizer'); ?></li>
                        <?php endif; ?>
                        <?php if (!$has_consent): ?>
                            <li><?php esc_html_e('Give consent to data processing', 'llmo-blog-optimizer'); ?></li>
                        <?php endif; ?>
                    </ul>
                    <p style="margin: 10px 0 0 0;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=llmo-blog-optimizer')); ?>" class="button button-small">
                            <?php esc_html_e('Go to Settings', 'llmo-blog-optimizer'); ?>
                        </a>
                    </p>
                </div>
                <?php
            endif;
            ?>
            
            <?php if ($optimized): ?>
                <p>
                    <span style="color: #00a32a; font-size: 16px;">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </span>
                    <strong><?php esc_html_e('Optimized', 'llmo-blog-optimizer'); ?></strong>
                </p>
                <?php if ($optimized_at): ?>
                    <p class="description">
                        <?php
                        /* translators: %s: Date and time of last optimization */
                        printf(esc_html__('Last optimized: %s', 'llmo-blog-optimizer'), esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($optimized_at))));
                        ?>
                    </p>
                <?php endif; ?>
                
                <?php if ($ai_score): ?>
                    <p>
                        <strong><?php esc_html_e('AI Readiness Score:', 'llmo-blog-optimizer'); ?></strong><br>
                        <span style="font-size: 24px; font-weight: bold; color: <?php echo $ai_score >= 80 ? '#00a32a' : ($ai_score >= 60 ? '#ff9800' : '#d63638'); ?>">
                            <?php echo esc_html($ai_score); ?>/100
                        </span>
                    </p>
                <?php endif; ?>
                
                <p style="margin-top: 15px;">
                    <button type="button" 
                            class="button button-secondary llmo-reoptimize" 
                            data-post-id="<?php echo esc_attr($post->ID); ?>"
                            <?php disabled(!$can_optimize); ?>>
                        <?php esc_html_e('Re-optimize', 'llmo-blog-optimizer'); ?>
                    </button>
                </p>
                
            <?php else: ?>
                <p>
                    <span style="color: #d63638; font-size: 16px;">
                        <span class="dashicons dashicons-marker"></span>
                    </span>
                    <strong><?php esc_html_e('Not optimized yet', 'llmo-blog-optimizer'); ?></strong>
                </p>
                
                <p style="margin-top: 15px;">
                    <button type="button" 
                            class="button button-primary llmo-optimize" 
                            data-post-id="<?php echo esc_attr($post->ID); ?>"
                            <?php disabled(!$can_optimize); ?>>
                        <?php esc_html_e('Optimize Now', 'llmo-blog-optimizer'); ?>
                    </button>
                </p>
                
                <?php if (!$can_optimize): ?>
                    <p class="description" style="color: #d63638; margin-top: 8px;">
                        <?php esc_html_e('Complete setup in Settings to enable optimization.', 'llmo-blog-optimizer'); ?>
                    </p>
                <?php endif; ?>
                
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Save post meta
     */
    public function save_post_meta($post_id) {
        // Check nonce
        if (!isset($_POST['llmo_blog_optimizer_nonce']) || !wp_verify_nonce(wp_unslash(sanitize_text_field($_POST['llmo_blog_optimizer_nonce'])), 'llmo_blog_optimizer_meta_box')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
    }
    
    /**
     * Output Schema.org markup in head
     */
    public function output_schema_markup() {
        if (!is_singular() && !is_front_page() && !is_home()) {
            return;
        }
        
        $post_id = get_the_ID();
        
        // Try to get optimized schema from post meta first
        $schema = get_post_meta($post_id, '_llmo_schema_org', true);
        
        // If no optimized schema, generate basic schema
        if (empty($schema)) {
            $schema = LLMO_Schema_Generator::generate_schema($post_id);
        }
        
        if (empty($schema)) {
            return;
        }
        
        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        echo "\n" . '</script>' . "\n";
        
        // Output FAQ schema if available
        $faq_data = get_post_meta($post_id, '_llmo_faq', true);
        if (!empty($faq_data) && is_array($faq_data)) {
            $faq_schema = array(
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array()
            );
            
            foreach ($faq_data as $item) {
                if (empty($item['question']) || empty($item['answer'])) {
                    continue;
                }
                
                $faq_schema['mainEntity'][] = array(
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => $item['answer']
                    )
                );
            }
            
            if (!empty($faq_schema['mainEntity'])) {
                echo '<script type="application/ld+json">' . "\n";
                echo wp_json_encode($faq_schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                echo "\n" . '</script>' . "\n";
            }
        }
    }
}

/**
 * Initialize the plugin
 */
function llmo_blog_optimizer_init() {
    return LLMO_Blog_Optimizer::get_instance();
}

// Start the plugin
llmo_blog_optimizer_init();
