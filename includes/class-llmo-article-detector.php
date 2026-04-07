<?php
/**
 * LLMO Article Detector
 *
 * @package LLMO_Blog_Optimizer
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Article Detector Class
 */
class LLMO_Article_Detector {
    
    /**
     * Detect blog articles
     */
    public static function detect_articles() {
        $post_types = get_option('llmo_blog_optimizer_post_types', array('post'));
        
        $args = array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => -1,
        );
        
        $posts = get_posts($args);
        
        return $posts;
    }
    
    /**
     * Check if post needs optimization
     */
    public static function needs_optimization($post_id) {
        return !get_post_meta($post_id, '_llmo_optimized', true);
    }
}
