<?php
/**
 * Plugin Name: LLMO Ready - Blog Optimizer
 * Description: Automatically adds Schema.org JSON-LD markup with AI-optimized content from LLMO Ready to blog posts for better visibility in generative AI search engines (ChatGPT, Google SGE, Perplexity).
 * Version: 1.0.27
 * Author: LLMO Ready by Libers GmbH
 * Author URI: https://libers.ai
 * Plugin URI: https://wordpress.org/plugins/llmo-ready-blog-optimizer/
 * Requires at least: 5.8
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
define('LLMO_BLOG_OPTIMIZER_VERSION', '1.0.27');
define('LLMO_BLOG_OPTIMIZER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LLMO_BLOG_OPTIMIZER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LLMO_BLOG_OPTIMIZER_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class
 */
class LLMO_Blog_Optimizer {

    const OPTION_PENDING_IDS = 'llmo_blog_optimizer_pending_ids';

    private static $instance = null;

    /**
     * Yoast or Rank Math already output the article or page node.
     */
    private $article_merged = false;

    /**
     * An FAQPage node was added to the SEO plugin graph.
     */
    private $faqpage_merged = false;

    /**
     * Visible FAQ HTML was printed by the automatic block.
     */
    private $faq_inserted = false;
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
        add_action('wp_head', array($this, 'output_schema_markup'), 99);
        add_action('wp_head', array($this, 'output_open_graph_tags'), 20);
        add_filter('the_content', array($this, 'insert_faq'), 12);
        add_action('wp_footer', array($this, 'render_faq_fallback'), 5);
        add_filter('rank_math/json_ld', array($this, 'merge_rank_math_graph'), 100, 2);
        add_filter('wpseo_schema_graph', array($this, 'merge_yoast_graph'), 11, 2);

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
        delete_post_meta($post_id, '_llmo_optimize_error');
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
        $result = $this->queue_optimization($post_id);
        if (is_wp_error($result)) {
            $error_data = $result->get_error_data();
            $status = (is_array($error_data) && isset($error_data['status'])) ? (int) $error_data['status'] : 0;
            update_post_meta(
                $post_id,
                '_llmo_optimize_error',
                $status === 402 ? 'insufficient_tokens' : 'failed'
            );
        }
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
    
    /**
     * Queue optimization (fire-and-forget). Completion is applied via AJAX poll or cron.
     *
     * @param int $post_id Post ID.
     * @return true|WP_Error
     */
    public function optimize_post($post_id) {
        return $this->queue_optimization($post_id);
    }

    /**
     * Queue an optimization job without blocking PHP (no sleep()).
     *
     * @param int $post_id Post ID.
     * @return true|WP_Error
     */
    public function queue_optimization($post_id) {
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

        $this->add_pending_post($post_id);
        delete_post_meta($post_id, '_llmo_optimize_error');
        return true;
    }

    /**
     * One-shot poll: apply meta when the remote job is ready.
     *
     * @param int $post_id Post ID.
     * @return array|WP_Error Status payload or error.
     */
    public function poll_optimization($post_id) {
        $post_id = (int) $post_id;
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('invalid_post', __('Invalid post ID', 'llmo-ready-blog-optimizer'));
        }

        // A post that is already optimized still has this flag. A new run sets
        // _llmo_pending. Without that check the poll returns the stored FAQ
        // and the fresh result never reaches the page.
        if (get_post_meta($post_id, '_llmo_optimized', true) && !get_post_meta($post_id, '_llmo_pending', true)) {
            return array(
                'status' => 'done',
                'score' => get_post_meta($post_id, '_llmo_ai_readiness_score', true),
            );
        }

        $api_key = get_option('llmo_blog_optimizer_api_key');
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('API key not configured', 'llmo-ready-blog-optimizer'));
        }

        $api_client = $this->get_api_client();
        $article = $api_client->check_optimization_status(get_permalink($post_id));
        if (is_wp_error($article)) {
            $this->remove_pending_post($post_id);
            update_post_meta($post_id, '_llmo_optimize_error', 'failed');
            return $article;
        }

        if (is_array($article)) {
            $this->apply_article_meta($post_id, $article);
            return array(
                'status' => 'done',
                'score' => get_post_meta($post_id, '_llmo_ai_readiness_score', true),
            );
        }

        return array('status' => 'pending');
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
        $optimize_error = get_post_meta($post->ID, '_llmo_optimize_error', true);
        ?>
        <div class="llmo-meta-box">
            <div class="llmo-meta-live" hidden></div>
            <?php if (!$can_optimize): ?>
                <div class="llmo-setup-notice">
                    <p class="llmo-setup-notice__title">
                        <span class="dashicons dashicons-warning llmo-setup-notice__icon"></span>
                        <?php esc_html_e('Setup Required', 'llmo-ready-blog-optimizer'); ?>
                    </p>
                    <ul class="llmo-setup-notice__list">
                        <?php if (!$has_api_key): ?>
                            <li><?php esc_html_e('Enter your API key in Settings', 'llmo-ready-blog-optimizer'); ?></li>
                        <?php endif; ?>
                        <?php if (!$has_consent): ?>
                            <li><?php esc_html_e('Give consent to data processing', 'llmo-ready-blog-optimizer'); ?></li>
                        <?php endif; ?>
                    </ul>
                    <p class="llmo-setup-notice__actions">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=llmo-blog-optimizer')); ?>" class="button button-small">
                            <?php esc_html_e('Go to Settings', 'llmo-ready-blog-optimizer'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($pending && !$optimized): ?>
                <div class="llmo-meta-pending">
                <p>
                    <span class="dashicons dashicons-update llmo-status-pending"></span>
                    <strong><?php esc_html_e('Optimization pending…', 'llmo-ready-blog-optimizer'); ?></strong>
                </p>
                <p class="description"><?php esc_html_e('Results will be applied automatically within a few minutes.', 'llmo-ready-blog-optimizer'); ?></p>
                </div>
            <?php elseif ($optimize_error && !$optimized): ?>
                <div class="llmo-meta-pending">
                <p>
                    <span class="dashicons dashicons-warning" style="color:#d63638;"></span>
                    <strong><?php esc_html_e('Optimization failed', 'llmo-ready-blog-optimizer'); ?></strong>
                </p>
                <p class="description">
                    <?php
                    if ($optimize_error === 'insufficient_tokens') {
                        esc_html_e('Not enough tokens. Top up in your LLMO Ready account, then try Optimize Now.', 'llmo-ready-blog-optimizer');
                    } else {
                        esc_html_e('The last automatic optimization could not be queued. Try Optimize Now or check your connection.', 'llmo-ready-blog-optimizer');
                    }
                    ?>
                </p>
                </div>
            <?php endif; ?>
            
            <?php if ($optimized): ?>
                <p>
                    <span class="llmo-status-ok"><span class="dashicons dashicons-yes-alt"></span></span>
                    <strong class="llmo-meta-state"><?php esc_html_e('Optimized', 'llmo-ready-blog-optimizer'); ?></strong>
                </p>
                <?php if ($optimized_at): ?>
                    <p class="description">
                        <?php
                        /* translators: %s: localized date and time of last optimization */
                        printf(esc_html__('Last optimized: %s', 'llmo-ready-blog-optimizer'), esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($optimized_at))));
                        ?>
                    </p>
                <?php endif; ?>
                <?php if ($ai_score !== '' && $ai_score !== false && $ai_score !== null): ?>
                    <p class="llmo-meta-score">
                        <strong><?php esc_html_e('Readability for AI (before optimization)', 'llmo-ready-blog-optimizer'); ?></strong>
                        <span class="dashicons dashicons-editor-help llmo-score-help" title="<?php echo esc_attr__('How well AI systems understand your original text. The questions and answers from the optimization are not included in this number.', 'llmo-ready-blog-optimizer'); ?>"></span><br>
                        <span class="llmo-score">
                            <?php echo esc_html($ai_score); ?>/100
                        </span>
                    </p>
                <?php endif; ?>
                <p class="llmo-meta-actions">
                    <button type="button" class="button button-secondary llmo-reoptimize" data-post-id="<?php echo esc_attr($post->ID); ?>" <?php disabled(!$can_optimize); ?>>
                        <?php esc_html_e('Re-optimize', 'llmo-ready-blog-optimizer'); ?>
                    </button>
                </p>
            <?php else: ?>
                <p>
                    <span class="llmo-status-missing"><span class="dashicons dashicons-marker"></span></span>
                    <strong class="llmo-meta-state"><?php esc_html_e('Not optimized yet', 'llmo-ready-blog-optimizer'); ?></strong>
                </p>
                <p class="llmo-meta-actions">
                    <button type="button" class="button button-primary llmo-optimize" data-post-id="<?php echo esc_attr($post->ID); ?>" <?php disabled(!$can_optimize); ?>>
                        <?php esc_html_e('Optimize Now', 'llmo-ready-blog-optimizer'); ?>
                    </button>
                </p>
                <p class="description llmo-optimize-hint">
                    <?php esc_html_e('Please wait. This can take up to a few minutes…', 'llmo-ready-blog-optimizer'); ?>
                </p>
                <?php if (!$can_optimize): ?>
                    <p class="description llmo-setup-required">
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
        if (!$this->is_llmo_content_view()) {
            return;
        }

        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }

        if (!$this->article_merged) {
            $schema = get_post_meta($post_id, '_llmo_schema_org', true);
            if (empty($schema)) {
                $schema = LLMO_Schema_Generator::generate_schema($post_id);
            }
            $this->echo_json_ld($schema);
        }

        if (!$this->faqpage_merged && $this->is_faq_view() && $this->should_output_faqpage($post_id)) {
            $this->echo_json_ld($this->faqpage_entity($post_id, true));
        }
    }

    /**
     * @param mixed $data
     * @param mixed $jsonld
     * @return mixed
     */
    public function merge_rank_math_graph($data, $jsonld = null) {
        return $this->merge_seo_graph($data);
    }

    /**
     * @param mixed $graph
     * @param mixed $context
     * @return mixed
     */
    public function merge_yoast_graph($graph, $context = null) {
        return $this->merge_seo_graph($graph);
    }

    /**
     * @param string $content
     * @return string
     */
    public function insert_faq($content) {
        if (!$this->faq_enabled() || !$this->is_faq_view() || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        if ($this->faq_inserted || LLMO_FAQ_Shortcode::was_rendered() || $this->content_has_faq_shortcode()) {
            return $content;
        }

        $html = LLMO_FAQ_Shortcode::render_faq(array('show_title' => 'yes'));
        if ($html === '') {
            return $content;
        }

        $this->faq_inserted = true;
        if ($this->faq_position() === 'before_content') {
            return $html . $content;
        }

        return $content . $html;
    }

    public function render_faq_fallback() {
        if ($this->faq_inserted || LLMO_FAQ_Shortcode::was_rendered() || !$this->faq_enabled() || !$this->is_faq_view()) {
            return;
        }
        if ($this->content_has_faq_shortcode()) {
            return;
        }

        $html = LLMO_FAQ_Shortcode::render_faq(array('show_title' => 'yes'));
        if ($html === '') {
            return;
        }

        $this->faq_inserted = true;
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_faq() escapes its output.
    }

    /**
     * @param mixed $data
     * @return mixed
     */
    private function merge_seo_graph($data) {
        if (!is_array($data) || !$this->is_llmo_content_view()) {
            return $data;
        }

        $post_id = (int) get_the_ID();
        $matching_types = $this->matching_schema_types();
        foreach ($data as $key => $entity) {
            if (!is_array($entity)) {
                continue;
            }
            $types = isset($entity['@type']) ? (array) $entity['@type'] : array();
            if ($matching_types !== array() && array_intersect($types, $matching_types)) {
                $data[$key] = $this->supplement_content_node($entity);
                $this->article_merged = true;
            }
            if (in_array('FAQPage', $types, true) && $this->is_faq_view() && $this->should_output_faqpage($post_id)) {
                $data[$key] = $this->merge_into_faqpage($entity, $post_id);
                $this->faqpage_merged = true;
            }
        }

        if (!$this->faqpage_merged && $this->is_faq_view() && $this->should_output_faqpage($post_id)) {
            $faqpage = $this->faqpage_entity($post_id, false);
            if (!empty($faqpage)) {
                $data['llmo-faqpage'] = $faqpage;
                $this->faqpage_merged = true;
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $entity
     * @return array<string, mixed>
     */
    private function supplement_content_node(array $entity) {
        $extra = $this->stored_schema();
        if ($extra === array()) {
            return $entity;
        }

        unset($extra['@context'], $extra['@type'], $extra['mainEntity'], $extra['headline'], $extra['name'], $extra['url']);
        foreach ($extra as $key => $value) {
            if ($value === '' || $value === null || $value === array()) {
                continue;
            }
            if (!empty($entity[$key])) {
                continue;
            }
            $entity[$key] = $value;
        }

        return $entity;
    }

    /**
     * Types in an SEO-plugin graph that already represent the node we would print.
     *
     * @return array<int, string>
     */
    private function matching_schema_types() {
        $schema = $this->stored_schema();
        if ($schema === array()) {
            $post_id = (int) get_the_ID();
            if ($post_id > 0) {
                $generated = LLMO_Schema_Generator::generate_schema($post_id);
                $schema = is_array($generated) ? $generated : array();
            }
        }

        $type = isset($schema['@type']) ? (string) $schema['@type'] : '';
        if (in_array($type, array('Article', 'BlogPosting', 'NewsArticle'), true)) {
            return array('Article', 'BlogPosting', 'NewsArticle');
        }
        if ($type === 'WebPage') {
            return array('WebPage');
        }
        if (in_array($type, array('Organization', 'LocalBusiness'), true)) {
            return array('Organization', 'LocalBusiness');
        }

        return array();
    }

    /**
     * @return array<string, mixed>
     */
    private function stored_schema() {
        $post_id = (int) get_the_ID();
        if ($post_id < 1) {
            return array();
        }
        $schema = get_post_meta($post_id, '_llmo_schema_org', true);
        if (is_string($schema)) {
            $decoded = json_decode($schema, true);
            $schema = is_array($decoded) ? $decoded : array();
        }
        return is_array($schema) ? $schema : array();
    }

    /**
     * @param array<string, mixed> $entity
     * @return array<string, mixed>
     */
    private function merge_into_faqpage(array $entity, $post_id) {
        $ours = $this->question_entities($post_id);
        if ($ours === array()) {
            return $entity;
        }

        $existing = isset($entity['mainEntity']) ? $entity['mainEntity'] : array();
        if (isset($existing['@type'])) {
            $existing = array($existing);
        }
        if (!is_array($existing)) {
            $existing = array();
        }

        $known = array();
        foreach ($existing as $item) {
            if (is_array($item) && isset($item['name'])) {
                $known[strtolower(trim((string) $item['name']))] = true;
            }
        }
        foreach ($ours as $question) {
            $key = strtolower(trim((string) $question['name']));
            if (isset($known[$key])) {
                continue;
            }
            $existing[] = $question;
            $known[$key] = true;
        }
        $entity['mainEntity'] = array_values($existing);

        return $entity;
    }

    /**
     * @return array<string, mixed>
     */
    private function faqpage_entity($post_id, $with_context) {
        $questions = $this->question_entities($post_id);
        if ($questions === array()) {
            return array();
        }

        $entity = array(
            '@type' => 'FAQPage',
            'mainEntity' => $questions,
        );
        if ($with_context) {
            $entity = array('@context' => 'https://schema.org') + $entity;
        }

        return $entity;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function question_entities($post_id) {
        $entities = array();
        foreach ($this->faq_pairs($post_id) as $faq) {
            $entities[] = array(
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text' => $faq['answer'],
                ),
            );
        }
        return $entities;
    }

    /**
     * @return array<int, array{question: string, answer: string}>
     */
    private function faq_pairs($post_id) {
        $raw = get_post_meta($post_id, '_llmo_faq', true);
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : array();
        }
        if (!is_array($raw)) {
            return array();
        }

        $pairs = array();
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $question = trim(wp_strip_all_tags((string) (isset($item['question']) ? $item['question'] : '')));
            $answer = trim(wp_strip_all_tags((string) (isset($item['answer']) ? $item['answer'] : '')));
            if ($question === '' || $answer === '') {
                continue;
            }
            $pairs[] = array(
                'question' => $question,
                'answer' => $answer,
            );
        }
        return $pairs;
    }

    private function should_output_faqpage($post_id) {
        if ($this->faq_pairs($post_id) === array()) {
            return false;
        }
        if ($this->faq_enabled()) {
            return true;
        }
        return $this->content_has_faq_shortcode();
    }

    private function faq_enabled() {
        return get_option('llmo_blog_optimizer_show_faq', 'yes') === 'yes';
    }

    private function faq_position() {
        $position = get_option('llmo_blog_optimizer_faq_position', 'after_content');
        return in_array($position, array('before_content', 'after_content'), true) ? $position : 'after_content';
    }

    private function content_has_faq_shortcode() {
        $post = get_post();
        return $post instanceof WP_Post && has_shortcode($post->post_content, 'llmo_faq');
    }

    private function is_llmo_content_view() {
        return is_singular() || is_front_page() || is_home();
    }

    /**
     * The FAQ belongs on one post or one page. The blog index keeps its existing schema.
     */
    private function is_faq_view() {
        return is_singular();
    }

    /**
     * @param mixed $data
     */
    private function echo_json_ld($data) {
        if (empty($data)) {
            return;
        }
        $json = $this->encode_json_ld($data);
        if (false === $json) {
            return;
        }
        echo '<script type="application/ld+json">' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Encoded via encode_json_ld().
        echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Encoded via encode_json_ld().
        echo "\n" . '</script>' . "\n";
    }
}

function llmo_blog_optimizer_init() {
    return LLMO_Blog_Optimizer::get_instance();
}

llmo_blog_optimizer_init();
