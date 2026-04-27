<?php
/**
 * Uninstall Script
 *
 * @package LLMO_Blog_Optimizer
 */

// Exit if accessed directly or not uninstalling
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('llmo_blog_optimizer_api_key');
delete_option('llmo_blog_optimizer_auto_optimize');
delete_option('llmo_blog_optimizer_post_types');
delete_option('llmo_blog_optimizer_consent');
delete_option('llmo_blog_optimizer_version');

// Delete all post meta created by the plugin using WP functions
// This avoids direct database queries for better compatibility

// List all meta keys created by the plugin
$meta_keys = array(
    '_llmo_optimized',
    '_llmo_optimized_at',
    '_llmo_schema_org',
    '_llmo_faq',
    '_llmo_key_takeaways',
    '_llmo_ai_readiness_score',
);

// Delete each meta key for all posts
foreach ($meta_keys as $meta_key) {
    delete_post_meta_by_key($meta_key);
}

// Clear any cached data
wp_cache_flush();
