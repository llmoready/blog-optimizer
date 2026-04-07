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
delete_option('llmo_blog_optimizer_version');

// Delete all post meta created by the plugin
global $wpdb;

$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_llmo_%'");

// Clear any cached data
wp_cache_flush();
