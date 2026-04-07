<?php
/**
 * LLMO Schema Generator
 *
 * @package LLMO_Blog_Optimizer
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Schema Generator Class
 */
class LLMO_Schema_Generator {
    
    /**
     * Generate Article schema
     */
    public static function generate_article_schema($post_id) {
        $post = get_post($post_id);
        
        if (!$post) {
            return null;
        }
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => get_the_title($post_id),
            'description' => get_the_excerpt($post_id),
            'datePublished' => get_the_date('c', $post_id),
            'dateModified' => get_the_modified_date('c', $post_id),
            'author' => array(
                '@type' => 'Person',
                'name' => get_the_author_meta('display_name', $post->post_author),
            ),
            'publisher' => array(
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'url' => home_url(),
            ),
        );
        
        // Add image if available
        if (has_post_thumbnail($post_id)) {
            $image_id = get_post_thumbnail_id($post_id);
            $image_url = wp_get_attachment_image_url($image_id, 'full');
            
            if ($image_url) {
                $schema['image'] = $image_url;
            }
        }
        
        return $schema;
    }
}
