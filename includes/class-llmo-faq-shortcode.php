<?php
/**
 * LLMO FAQ Shortcode
 *
 * @package LLMO_Blog_Optimizer
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * FAQ Shortcode Class
 */
class LLMO_FAQ_Shortcode {

    /**
     * Whether the visible FAQ block was printed during this request.
     */
    private static $rendered = false;

    public static function was_rendered() {
        return self::$rendered;
    }
    
    /**
     * Initialize shortcode
     */
    public static function init() {
        add_shortcode('llmo_faq', array(__CLASS__, 'render_faq'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register_assets'));
    }
    
    /**
     * Register frontend assets (enqueued only when shortcode/widget renders).
     */
    public static function register_assets() {
        wp_register_style(
            'llmo-faq-frontend',
            LLMO_BLOG_OPTIMIZER_PLUGIN_URL . 'assets/css/faq-frontend.css',
            array(),
            LLMO_BLOG_OPTIMIZER_VERSION
        );
        
        wp_register_script(
            'llmo-faq-frontend',
            LLMO_BLOG_OPTIMIZER_PLUGIN_URL . 'assets/js/faq-frontend.js',
            array('jquery'),
            LLMO_BLOG_OPTIMIZER_VERSION,
            true
        );
    }

    /**
     * Enqueue FAQ assets when the shortcode or widget actually outputs.
     */
    public static function enqueue_assets() {
        wp_enqueue_style('dashicons');
        wp_enqueue_style('llmo-faq-frontend');
        wp_enqueue_script('llmo-faq-frontend');
    }
    
    /**
     * Render FAQ shortcode
     */
    public static function render_faq($atts) {
        $atts = shortcode_atts(array(
            'post_id' => get_the_ID(),
            'style' => 'accordion',
            'title' => __('Frequently Asked Questions', 'llmo-ready-blog-optimizer'),
            'show_title' => 'yes',
        ), $atts);
        
        $post_id = intval($atts['post_id']);
        $faq_data = get_post_meta($post_id, '_llmo_faq', true);
        
        if (empty($faq_data) || !is_array($faq_data)) {
            return '';
        }

        self::enqueue_assets();
        self::$rendered = true;
        
        ob_start();
        ?>
        <div class="llmo-faq-container" data-style="<?php echo esc_attr($atts['style']); ?>">
            <?php if ($atts['show_title'] === 'yes' && !empty($atts['title'])): ?>
                <h2 class="llmo-faq-title"><?php echo esc_html($atts['title']); ?></h2>
            <?php endif; ?>
            
            <div class="llmo-faq-items">
                <?php $visible_index = 0; ?>
                <?php foreach ($faq_data as $item): ?>
                    <?php if (empty($item['question']) || empty($item['answer'])) continue; ?>
                    
                    <div class="llmo-faq-item<?php echo $visible_index === 0 ? ' active' : ''; ?>">
                        <div class="llmo-faq-question">
                            <span class="dashicons dashicons-arrow-right-alt2 llmo-faq-icon"></span>
                            <?php echo esc_html($item['question']); ?>
                        </div>
                        <div class="llmo-faq-answer">
                            <?php echo wp_kses_post(wpautop($item['answer'])); ?>
                        </div>
                    </div>
                    <?php $visible_index++; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize
LLMO_FAQ_Shortcode::init();
