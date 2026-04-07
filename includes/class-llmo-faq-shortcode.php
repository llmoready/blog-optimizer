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
     * Initialize shortcode
     */
    public static function init() {
        add_shortcode('llmo_faq', array(__CLASS__, 'render_faq'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_styles'));
    }
    
    /**
     * Enqueue frontend styles
     */
    public static function enqueue_styles() {
        wp_enqueue_style(
            'llmo-faq-frontend',
            LLMO_BLOG_OPTIMIZER_PLUGIN_URL . 'assets/css/faq-frontend.css',
            array(),
            LLMO_BLOG_OPTIMIZER_VERSION
        );
        
        wp_enqueue_script(
            'llmo-faq-frontend',
            LLMO_BLOG_OPTIMIZER_PLUGIN_URL . 'assets/js/faq-frontend.js',
            array('jquery'),
            LLMO_BLOG_OPTIMIZER_VERSION,
            true
        );
    }
    
    /**
     * Render FAQ shortcode
     */
    public static function render_faq($atts) {
        $atts = shortcode_atts(array(
            'post_id' => get_the_ID(),
            'style' => 'accordion',
            'title' => __('Frequently Asked Questions', 'llmo-blog-optimizer'),
            'show_title' => 'yes',
        ), $atts);
        
        $post_id = intval($atts['post_id']);
        $faq_data = get_post_meta($post_id, '_llmo_faq', true);
        
        if (empty($faq_data) || !is_array($faq_data)) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="llmo-faq-container" data-style="<?php echo esc_attr($atts['style']); ?>">
            <?php if ($atts['show_title'] === 'yes' && !empty($atts['title'])): ?>
                <h2 class="llmo-faq-title"><?php echo esc_html($atts['title']); ?></h2>
            <?php endif; ?>
            
            <div class="llmo-faq-items">
                <?php foreach ($faq_data as $index => $item): ?>
                    <?php if (empty($item['question']) || empty($item['answer'])) continue; ?>
                    
                    <div class="llmo-faq-item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
                        <div class="llmo-faq-question" itemprop="name">
                            <span class="dashicons dashicons-arrow-right-alt2 llmo-faq-icon"></span>
                            <?php echo esc_html($item['question']); ?>
                        </div>
                        <div class="llmo-faq-answer" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                            <div itemprop="text">
                                <?php echo wp_kses_post(wpautop($item['answer'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize
LLMO_FAQ_Shortcode::init();
