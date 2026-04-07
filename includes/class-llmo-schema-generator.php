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
     * Generate appropriate schema based on page type
     */
    public static function generate_schema($post_id) {
        $post = get_post($post_id);
        
        if (!$post) {
            return null;
        }
        
        // Determine schema type
        if (is_front_page() || is_home()) {
            return self::generate_organization_schema();
        } elseif ($post->post_type === 'post') {
            return self::generate_article_schema($post_id);
        } elseif ($post->post_type === 'page') {
            return self::generate_webpage_schema($post_id);
        }
        
        return self::generate_webpage_schema($post_id);
    }
    
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
    
    /**
     * Generate WebPage schema
     */
    public static function generate_webpage_schema($post_id) {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => get_the_title($post_id),
            'description' => get_the_excerpt($post_id),
            'url' => get_permalink($post_id),
            'datePublished' => get_the_date('c', $post_id),
            'dateModified' => get_the_modified_date('c', $post_id),
        );
        
        // Add breadcrumb if available
        $schema['breadcrumb'] = array(
            '@type' => 'BreadcrumbList',
            'itemListElement' => array(
                array(
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Home',
                    'item' => home_url(),
                ),
                array(
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => get_the_title($post_id),
                    'item' => get_permalink($post_id),
                ),
            ),
        );
        
        return $schema;
    }
    
    /**
     * Generate Organization schema for homepage
     */
    public static function generate_organization_schema() {
        $org_name = get_option('llmo_organization_name', get_bloginfo('name'));
        $org_type = get_option('llmo_organization_type', 'Organization');
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => $org_type,
            'name' => $org_name,
            'url' => home_url(),
            'description' => get_bloginfo('description'),
        );
        
        // Add logo if available
        $logo_url = get_option('llmo_organization_logo');
        if ($logo_url) {
            $schema['logo'] = $logo_url;
        }
        
        // Add contact information
        $phone = get_option('llmo_organization_phone');
        if ($phone) {
            $schema['telephone'] = $phone;
        }
        
        $email = get_option('llmo_organization_email');
        if ($email) {
            $schema['email'] = $email;
        }
        
        // Add address for LocalBusiness
        if ($org_type === 'LocalBusiness') {
            $street = get_option('llmo_organization_street');
            $city = get_option('llmo_organization_city');
            $postal = get_option('llmo_organization_postal');
            $country = get_option('llmo_organization_country');
            
            if ($street || $city) {
                $schema['address'] = array(
                    '@type' => 'PostalAddress',
                    'streetAddress' => $street,
                    'addressLocality' => $city,
                    'postalCode' => $postal,
                    'addressCountry' => $country,
                );
            }
            
            // Add opening hours
            $hours = get_option('llmo_organization_hours');
            if ($hours) {
                $schema['openingHours'] = $hours;
            }
        }
        
        // Add social media
        $social = array();
        $facebook = get_option('llmo_organization_facebook');
        $twitter = get_option('llmo_organization_twitter');
        $linkedin = get_option('llmo_organization_linkedin');
        $instagram = get_option('llmo_organization_instagram');
        
        if ($facebook) $social[] = $facebook;
        if ($twitter) $social[] = $twitter;
        if ($linkedin) $social[] = $linkedin;
        if ($instagram) $social[] = $instagram;
        
        if (!empty($social)) {
            $schema['sameAs'] = $social;
        }
        
        return $schema;
    }
}
