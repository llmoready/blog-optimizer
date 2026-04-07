<?php
/**
 * Plugin Name: LLMO Blog Optimizer
 * Plugin URI: https://github.com/llmoready/blog-optimizer
 * Description: AI-powered blog optimization for better visibility in AI search engines. Auto-generates Schema.org markup, FAQs, and key takeaways for your blog posts.
 * Version: 1.0.0
 * Author: LLMO Ready
 * Author URI: https://llmoready.com
 * Requires at least: 5.8
 * Tested up to: 6.5
 * Requires PHP: 7.4
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
        
        // Load text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
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
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'llmo-blog-optimizer',
            false,
            dirname(LLMO_BLOG_OPTIMIZER_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Auto-optimize post on publish
     */
    public function auto_optimize_post($post_id, $post) {
        // Check if auto-optimize is enabled
        if (get_option('llmo_blog_optimizer_auto_optimize') !== 'yes') {
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
        
        $optimized = get_post_meta($post->ID, '_llmo_optimized', true);
        $optimized_at = get_post_meta($post->ID, '_llmo_optimized_at', true);
        $ai_score = get_post_meta($post->ID, '_llmo_ai_readiness_score', true);
        
        ?>
        <div class="llmo-meta-box">
            <?php if ($optimized): ?>
                <p>
                    <span style="color: #4caf50; font-size: 16px;">✓</span>
                    <strong><?php _e('Optimized', 'llmo-blog-optimizer'); ?></strong>
                </p>
                <?php if ($optimized_at): ?>
                    <p class="description">
                        <?php printf(__('Last optimized: %s', 'llmo-blog-optimizer'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($optimized_at))); ?>
                    </p>
                <?php endif; ?>
                <?php if ($ai_score): ?>
                    <p>
                        <strong><?php _e('AI Readiness Score:', 'llmo-blog-optimizer'); ?></strong> 
                        <span style="font-size: 18px; color: <?php echo $ai_score >= 80 ? '#4caf50' : ($ai_score >= 60 ? '#ff9800' : '#dc3232'); ?>">
                            <?php echo esc_html($ai_score); ?>/100
                        </span>
                    </p>
                <?php endif; ?>
                <p>
                    <button type="button" class="button button-secondary llmo-reoptimize" data-post-id="<?php echo esc_attr($post->ID); ?>">
                        <?php _e('Re-optimize', 'llmo-blog-optimizer'); ?>
                    </button>
                </p>
            <?php else: ?>
                <p>
                    <span style="color: #ff9800; font-size: 16px;">○</span>
                    <?php _e('Not optimized yet', 'llmo-blog-optimizer'); ?>
                </p>
                <p>
                    <button type="button" class="button button-primary llmo-optimize" data-post-id="<?php echo esc_attr($post->ID); ?>">
                        <?php _e('Optimize Now', 'llmo-blog-optimizer'); ?>
                    </button>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Save post meta
     */
    public function save_post_meta($post_id) {
        // Check nonce
        if (!isset($_POST['llmo_blog_optimizer_nonce']) || !wp_verify_nonce($_POST['llmo_blog_optimizer_nonce'], 'llmo_blog_optimizer_meta_box')) {
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
