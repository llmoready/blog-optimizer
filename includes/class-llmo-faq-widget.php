<?php
/**
 * LLMO FAQ Widget
 *
 * @package LLMO_Blog_Optimizer
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * FAQ Widget Class
 */
class LLMO_FAQ_Widget extends WP_Widget {
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'llmo_faq_widget',
            __('LLMO FAQ', 'llmo-blog-optimizer'),
            array(
                'description' => __('Display AI-generated FAQs', 'llmo-blog-optimizer'),
            )
        );
    }
    
    /**
     * Widget output
     */
    public function widget($args, $instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('FAQ', 'llmo-blog-optimizer');
        $post_id = get_the_ID();
        
        $faq_data = get_post_meta($post_id, '_llmo_faq', true);
        
        if (empty($faq_data) || !is_array($faq_data)) {
            return;
        }
        
        echo $args['before_widget'];
        
        if (!empty($title)) {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }
        
        echo do_shortcode('[llmo_faq show_title="no"]');
        
        echo $args['after_widget'];
    }
    
    /**
     * Widget form
     */
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('FAQ', 'llmo-blog-optimizer');
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php esc_html_e('Title:', 'llmo-blog-optimizer'); ?>
            </label>
            <input class="widefat" 
                   id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" 
                   type="text" 
                   value="<?php echo esc_attr($title); ?>">
        </p>
        <?php
    }
    
    /**
     * Update widget
     */
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = !empty($new_instance['title']) ? sanitize_text_field($new_instance['title']) : '';
        return $instance;
    }
}

/**
 * Register widget
 */
function llmo_register_faq_widget() {
    register_widget('LLMO_FAQ_Widget');
}
add_action('widgets_init', 'llmo_register_faq_widget');
