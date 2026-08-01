<?php
/**
 * Plugin Name: LLMO Ready - Blog Optimizer
 * Description: Automatically adds Schema.org JSON-LD markup with AI-optimized content from LLMO Ready to blog posts for better visibility in generative AI search engines (ChatGPT, Google SGE, Perplexity).
 * Version: 1.0.14
 * Author: LLMO Ready by Libers GmbH
 * Author URI: https://libers.ai
 * Plugin URI: https://wordpress.org/plugins/llmo-ready-blog-optimizer/
 * Requires at least: 5.8
 * Tested up to: 7.0
 * Requires PHP: 7.4
 * Text Domain: llmo-ready-blog-optimizer
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('LLMO_BLOG_OPTIMIZER_VERSION', '1.0.14');
define('LLMO_BLOG_OPTIMIZER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LLMO_BLOG_OPTIMIZER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LLMO_BLOG_OPTIMIZER_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class
 */
class LLMO_Blog_Optimizer {

    const OPTION_PENDING_IDS = 'llmo_blog_optimizer_pending_ids';

    private static $instance = null;
    private $api_endpoint = 'https://api.libers.ai/api';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    private function load_dependencies() {
        require_once LLMO_BLOG_OPTIMIZER_PLUGIN_DIR . 'includes/class-llmo-api-client.php';
        require_once LLMO_BLOG_OPTIMIZER_PLUGIN_DIR . 'includes/class-llmo-article-detector.php';
        require_once LLMO_BLOG_OPTIMIZER_PLUGIN_DIR . 'includes/class-llmo-schema-generator.php';
        require_once LLMO_BLOG_OPTIMIZER_PLUGIN_DIR . 'includes/class-llmo-faq-shortcode.php';
        require_once LLMO_BLOG_OPTIMIZER_PLUGIN_DIR . 'includes/class-llmo-faq-widget.php';
        require_once LLMO_BLOG_OPTIMIZER_PLUGIN_DIR . 'admin/class-llmo-admin.php';
        require_once LLMO_BLOG_OPTIMIZER_PLUGIN_DIR . 'admin/class-llmo-bulk-optimizer.php';
    }
    
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        if (is_admin()) {
            new LLMO_Blog_Optimizer_Admin();
        }
        
        add_action('publish_post', array($this, 'auto_optimize_post'), 10, 2);
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post', array($this, 'save_post_meta'));
        add_action('wp_head', array($this, 'output_schema_markup'), 10);
        add_action('wp_head', array($this, 'output_open_graph_tags'), 20);

        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
        add_action('llmo_blog_optimizer_poll_pending', array($this, 'poll_pending_optimizations'));

        add_filter('wpseo_opengraph_image', array($this, 'filter_yoast_og_image'));
        add_filter('wpseo_opengraph_title', array($this, 'filter_yoast_og_title'));
        add_filter('wpseo_opengraph_desc', array($this, 'filter_yoast_og_description'));
        add_filter('rank_math/opengraph/facebook/image', array($this, 'filter_rankmath_og_image'));
        add_filter('rank_math/opengraph/facebook/og_image', array($this, 'filter_rankmath_og_image'));
        add_filter('rank_math/opengraph/facebook/og_title', array($this, 'filter_rankmath_og_title'));
        add_filter('rank_math/opengraph/facebook/og_description', array($this, 'filter_rankmath_og_description'));

        // Ensure cron exists after version upgrades (activate hook may not re-run).
        add_action('init', array($this, 'maybe_schedule_pending_poll'));
    }

    public function maybe_schedule_pending_poll() {
        if (!wp_next_scheduled('llmo_blog_optimizer_poll_pending')) {
            wp_schedule_event(time() + 60, 'llmo_five_minutes', 'llmo_blog_optimizer_poll_pending');
        }
    }

    public function add_cron_schedules($schedules) {
        $schedules['llmo_five_minutes'] = array(
            'interval' => 300,
            'display' => __('Every 5 Minutes (LLMO)', 'llmo-ready-blog-optimizer'),
        );
        return $schedules;
    }
    
    public function activate() {
        add_option('llmo_blog_optimizer_api_key', '');
        add_option('llmo_blog_optimizer_auto_optimize', 'yes');
        add_option('llmo_blog_optimizer_post_types', array('post'));
        add_option('llmo_blog_optimizer_version', LLMO_BLOG_OPTIMIZER_VERSION);
        add_option(self::OPTION_PENDING_IDS, array());

        if (!wp_next_scheduled('llmo_blog_optimizer_poll_pending')) {
            wp_schedule_event(time() + 60, 'llmo_five_minutes', 'llmo_blog_optimizer_poll_pending');
        }
        
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        $timestamp = wp_next_scheduled('llmo_blog_optimizer_poll_pending');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'llmo_blog_optimizer_poll_pending');
        }
        flush_rewrite_rules();
    }
    
    private function has_explicit_consent() {
        return get_option('llmo_blog_optimizer_consent', '') === 'yes';
    }

    private function add_pending_post($post_id) {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return;
        }

        update_post_meta($post_id, '_llmo_pending', '1');

        $pending = get_option(self::OPTION_PENDING_IDS, array());
        if (!is_array($pending)) {
            $pending = array();
        }

        if (!in_array($post_id, $pending, true)) {
            $pending[] = $post_id;
            update_option(self::OPTION_PENDING_IDS, $pending, false);
        }
    }

    private function remove_pending_post($post_id) {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return;
        }

        delete_post_meta($post_id, '_llmo_pending');

        $pending = get_option(self::OPTION_PENDING_IDS, array());
        if (!is_array($pending) || empty($pending)) {
            return;
        }

        $pending = array_values(array_filter(
            array_map('intval', $pending),
            function ($id) use ($post_id) {
                return $id !== $post_id;
            }
        ));

        update_option(self::OPTION_PENDING_IDS, $pending, false);
    }

    private function get_pending_post_ids($limit = 20) {
        $pending = get_option(self::OPTION_PENDING_IDS, array());
        if (!is_array($pending) || empty($pending)) {
            return array();
        }

        $pending = array_values(array_unique(array_map('intval', $pending)));
        $valid = array();
        $still_pending = array();

        foreach ($pending as $post_id) {
            if ($post_id <= 0) {
                continue;
            }

            if (get_post_status($post_id) === 'publish') {
                $still_pending[] = $post_id;
                if (count($valid) < $limit) {
                    $valid[] = $post_id;
                }
            } else {
                delete_post_meta($post_id, '_llmo_pending');
            }
        }

        if ($still_pending !== $pending) {
            update_option(self::OPTION_PENDING_IDS, $still_pending, false);
        }

        return $valid;
    }

    private function get_api_client() {
        $api_key = get_option('llmo_blog_optimizer_api_key');
        return new LLMO_API_Client($api_key, get_site_url(), $this->api_endpoint);
    }

    private function apply_article_meta($post_id, $article) {
        update_post_meta($post_id, '_llmo_optimized', true);
        update_post_meta($post_id, '_llmo_optimized_at', current_time('mysql'));
        update_post_meta($post_id, '_llmo_schema_org', isset($article['optimized_schema']) ? $article['optimized_schema'] : '');
        update_post_meta($post_id, '_llmo_faq', isset($article['optimized_faq']) ? $article['optimized_faq'] : '');
        update_post_meta($post_id, '_llmo_key_takeaways', isset($article['key_takeaways']) ? $article['key_takeaways'] : '');
        update_post_meta($post_id, '_llmo_ai_readiness_score', isset($article['ai_readiness_score']) ? $article['ai_readiness_score'] : '');
        update_post_meta($post_id, '_llmo_og_title', isset($article['og_title']) ? $article['og_title'] : '');
        update_post_meta($post_id, '_llmo_og_description', isset($article['og_description']) ? $article['og_description'] : '');
        update_post_meta($post_id, '_llmo_og_image', isset($article['og_image']) ? $article['og_image'] : '');
        $this->remove_pending_post($post_id);
    }
    
    public function auto_optimize_post($post_id, $post) {
        if (get_option('llmo_blog_optimizer_auto_optimize') !== 'yes') {
            return;
        }
        if (!$this->has_explicit_consent()) {
            return;
        }
        $enabled_post_types = get_option('llmo_blog_optimizer_post_types', array('post'));
        if (!in_array($post->post_type, $enabled_post_types, true)) {
            return;
        }
        if (get_post_meta($post_id, '_llmo_optimized', true)) {
            return;
        }
        $this->queue_optimization($post_id, false);
    }

    public function poll_pending_optimizations() {
        if (!$this->has_explicit_consent()) {
            return;
        }
        $api_key = get_option('llmo_blog_optimizer_api_key');
        if (empty($api_key)) {
            return;
        }

        $posts = $this->get_pending_post_ids(20);

        if (empty($posts)) {
            return;
        }

        $api_client = $this->get_api_client();
        foreach ($posts as $post_id) {
            $result = $api_client->get_article_by_url(get_permalink($post_id));
            if (is_wp_error($result)) {
                continue;
            }
            $article = isset($result['article']) ? $result['article'] : null;
            if (!$article || empty($article['status'])) {
                continue;
            }
            if ($article['status'] === 'analyzed') {
                $this->apply_article_meta($post_id, $article);
                // Keep pending if OG image still generating asynchronously.
                if (empty($article['og_image'])) {
                    $this->add_pending_post($post_id);
                }
            } elseif ($article['status'] === 'failed') {
                $this->remove_pending_post($post_id);
                update_post_meta($post_id, '_llmo_optimize_error', 'failed');
            }
        }
    }
    
    public function optimize_post($post_id) {
        return $this->queue_optimization($post_id, true);
    }

    public function queue_optimization($post_id, $wait = true) {
        $api_key = get_option('llmo_blog_optimizer_api_key');
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('API key not configured', 'llmo-ready-blog-optimizer'));
        }
        if (get_option('llmo_blog_optimizer_consent') !== 'yes') {
            return new WP_Error('no_consent', __('User consent required. Please enable data processing consent in plugin settings.', 'llmo-ready-blog-optimizer'));
        }
        
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('invalid_post', __('Invalid post ID', 'llmo-ready-blog-optimizer'));
        }
        
        $api_client = $this->get_api_client();
        $featured = get_the_post_thumbnail_url($post_id, 'full');

        $response = $api_client->optimize_article(array(
            'url' => get_permalink($post_id),
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'author' => get_the_author_meta('display_name', $post->post_author),
            'published_at' => $post->post_date,
            'featured_image' => $featured ? $featured : null,
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }

        if (!$wait) {
            $this->add_pending_post($post_id);
            return true;
        }

        // Wait for async optimization; avoid set_time_limit() (discouraged by Plugin Check).
        $article = $api_client->wait_for_optimization(get_permalink($post_id));
        if (is_wp_error($article)) {
            $this->add_pending_post($post_id);
            return $article;
        }

        $this->apply_article_meta($post_id, $article);
        return true;
    }

    private function seo_plugin_handles_open_graph() {
        if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION')) {
            return true;
        }
        if (did_action('wpseo_head') > 0 || did_action('rank_math/head') > 0) {
            return true;
        }
        return false;
    }

    private function get_llmo_og_meta($key) {
        if (!is_singular()) {
            return '';
        }
        $post_id = get_the_ID();
        if (!$post_id) {
            return '';
        }
        $value = get_post_meta($post_id, $key, true);
        return is_string($value) ? $value : '';
    }

    public function filter_yoast_og_image($image) {
        $ours = $this->get_llmo_og_meta('_llmo_og_image');
        return !empty($ours) ? $ours : $image;
    }

    public function filter_yoast_og_title($title) {
        $ours = $this->get_llmo_og_meta('_llmo_og_title');
        return !empty($ours) ? $ours : $title;
    }

    public function filter_yoast_og_description($desc) {
        $ours = $this->get_llmo_og_meta('_llmo_og_description');
        return !empty($ours) ? $ours : $desc;
    }

    public function filter_rankmath_og_image($image) {
        return $this->filter_yoast_og_image($image);
    }

    public function filter_rankmath_og_title($title) {
        return $this->filter_yoast_og_title($title);
    }

    public function filter_rankmath_og_description($desc) {
        return $this->filter_yoast_og_description($desc);
    }

    public function output_open_graph_tags() {
        if (!is_singular()) {
            return;
        }
        if ($this->seo_plugin_handles_open_graph()) {
            return;
        }

        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }

        $og_image = get_post_meta($post_id, '_llmo_og_image', true);
        $og_title = get_post_meta($post_id, '_llmo_og_title', true);
        $og_description = get_post_meta($post_id, '_llmo_og_description', true);

        if (empty($og_image) && empty($og_title) && empty($og_description)) {
            return;
        }

        echo "\n<!-- LLMO Blog Optimizer: Open Graph Tags -->\n";
        if (!empty($og_image)) {
            echo '<meta property="og:image" content="' . esc_url($og_image) . '" />' . "\n";
        }
        if (!empty($og_title)) {
            echo '<meta property="og:title" content="' . esc_attr($og_title) . '" />' . "\n";
        }
        if (!empty($og_description)) {
            echo '<meta property="og:description" content="' . esc_attr($og_description) . '" />' . "\n";
        }
        echo "<!-- /LLMO Blog Optimizer: Open Graph Tags -->\n";
    }
    
    public function add_meta_box() {
        $post_types = get_option('llmo_blog_optimizer_post_types', array('post'));
        foreach ($post_types as $post_type) {
            add_meta_box(
                'llmo_blog_optimizer',
                __('LLMO Blog Optimizer', 'llmo-ready-blog-optimizer'),
                array($this, 'render_meta_box'),
                $post_type,
                'side',
                'high'
            );
        }
    }
    
    public function render_meta_box($post) {
        wp_nonce_field('llmo_blog_optimizer_meta_box', 'llmo_blog_optimizer_nonce');
        
        $has_consent = $this->has_explicit_consent();
        $api_key = get_option('llmo_blog_optimizer_api_key');
        $has_api_key = !empty($api_key);
        $can_optimize = $has_consent && $has_api_key;
        
        $optimized = get_post_meta($post->ID, '_llmo_optimized', true);
        $pending = get_post_meta($post->ID, '_llmo_pending', true);
        $optimized_at = get_post_meta($post->ID, '_llmo_optimized_at', true);
        $ai_score = get_post_meta($post->ID, '_llmo_ai_readiness_score', true);
        ?>
        <div class="llmo-meta-box">
            <?php if (!$can_optimize): ?>
                <div style="background: #f0f0f1; border-left: 4px solid #d63638; padding: 12px; margin-bottom: 15px;">
                    <p style="margin: 0 0 8px 0; font-weight: 600;">
                        <span class="dashicons dashicons-warning" style="color: #d63638; vertical-align: middle;"></span>
                        <?php esc_html_e('Setup Required', 'llmo-ready-blog-optimizer'); ?>
                    </p>
                    <ul style="margin: 0; padding-left: 20px; font-size: 12px;">
                        <?php if (!$has_api_key): ?>
                            <li><?php esc_html_e('Enter your API key in Settings', 'llmo-ready-blog-optimizer'); ?></li>
                        <?php endif; ?>
                        <?php if (!$has_consent): ?>
                            <li><?php esc_html_e('Give consent to data processing', 'llmo-ready-blog-optimizer'); ?></li>
                        <?php endif; ?>
                    </ul>
                    <p style="margin: 10px 0 0 0;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=llmo-blog-optimizer')); ?>" class="button button-small">
                            <?php esc_html_e('Go to Settings', 'llmo-ready-blog-optimizer'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($pending && !$optimized): ?>
                <p>
                    <span class="dashicons dashicons-update" style="color: #dba617;"></span>
                    <strong><?php esc_html_e('Optimization pending…', 'llmo-ready-blog-optimizer'); ?></strong>
                </p>
                <p class="description"><?php esc_html_e('Results will be applied automatically within a few minutes.', 'llmo-ready-blog-optimizer'); ?></p>
            <?php endif; ?>
            
            <?php if ($optimized): ?>
                <p>
                    <span style="color: #00a32a; font-size: 16px;"><span class="dashicons dashicons-yes-alt"></span></span>
                    <strong><?php esc_html_e('Optimized', 'llmo-ready-blog-optimizer'); ?></strong>
                </p>
                <?php if ($optimized_at): ?>
                    <p class="description">
                        <?php
                        /* translators: %s: localized date and time of last optimization */
                        printf(esc_html__('Last optimized: %s', 'llmo-ready-blog-optimizer'), esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($optimized_at))));
                        ?>
                    </p>
                <?php endif; ?>
                <?php if ($ai_score): ?>
                    <p>
                        <strong><?php esc_html_e('AI Readiness Score:', 'llmo-ready-blog-optimizer'); ?></strong><br>
                        <span style="font-size: 24px; font-weight: bold; color: <?php echo $ai_score >= 80 ? '#00a32a' : ($ai_score >= 60 ? '#ff9800' : '#d63638'); ?>">
                            <?php echo esc_html($ai_score); ?>/100
                        </span>
                    </p>
                <?php endif; ?>
                <p style="margin-top: 15px;">
                    <button type="button" class="button button-secondary llmo-reoptimize" data-post-id="<?php echo esc_attr($post->ID); ?>" <?php disabled(!$can_optimize); ?>>
                        <?php esc_html_e('Re-optimize', 'llmo-ready-blog-optimizer'); ?>
                    </button>
                </p>
            <?php else: ?>
                <p>
                    <span style="color: #d63638; font-size: 16px;"><span class="dashicons dashicons-marker"></span></span>
                    <strong><?php esc_html_e('Not optimized yet', 'llmo-ready-blog-optimizer'); ?></strong>
                </p>
                <p style="margin-top: 15px;">
                    <button type="button" class="button button-primary llmo-optimize" data-post-id="<?php echo esc_attr($post->ID); ?>" <?php disabled(!$can_optimize); ?>>
                        <?php esc_html_e('Optimize Now', 'llmo-ready-blog-optimizer'); ?>
                    </button>
                </p>
                <p class="description llmo-optimize-hint" style="display:none; margin-top:8px;">
                    <?php esc_html_e('Please wait — this can take up to a few minutes…', 'llmo-ready-blog-optimizer'); ?>
                </p>
                <?php if (!$can_optimize): ?>
                    <p class="description" style="color: #d63638; margin-top: 8px;">
                        <?php esc_html_e('Complete setup in Settings to enable optimization.', 'llmo-ready-blog-optimizer'); ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function save_post_meta($post_id) {
        if (!isset($_POST['llmo_blog_optimizer_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['llmo_blog_optimizer_nonce'])), 'llmo_blog_optimizer_meta_box')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
    }
    
    /**
     * Encode JSON-LD safely for embedding inside a <script> tag.
     * JSON_HEX_TAG prevents a literal </script> from breaking out of the element.
     *
     * @param mixed $data Schema data.
     * @return string
     */
    private function encode_json_ld($data) {
        return wp_json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }

    public function output_schema_markup() {
        if (!is_singular() && !is_front_page() && !is_home()) {
            return;
        }
        
        $post_id = get_the_ID();
        $schema = get_post_meta($post_id, '_llmo_schema_org', true);
        
        if (empty($schema)) {
            $schema = LLMO_Schema_Generator::generate_schema($post_id);
        }
        
        if (empty($schema)) {
            return;
        }

        $schema_json = $this->encode_json_ld($schema);
        if (false !== $schema_json) {
            echo '<script type="application/ld+json">' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Encoded via encode_json_ld().
            echo $schema_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Encoded via encode_json_ld().
            echo "\n" . '</script>' . "\n";
        }
        
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
                $faq_json = $this->encode_json_ld($faq_schema);
                if (false !== $faq_json) {
                    echo '<script type="application/ld+json">' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Encoded via encode_json_ld().
                    echo $faq_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Encoded via encode_json_ld().
                    echo "\n" . '</script>' . "\n";
                }
            }
        }
    }
}

function llmo_blog_optimizer_init() {
    return LLMO_Blog_Optimizer::get_instance();
}

llmo_blog_optimizer_init();
