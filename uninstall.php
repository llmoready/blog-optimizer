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

// Remove scheduled cron events
$llmo_cron_hook = 'llmo_blog_optimizer_poll_pending';
$llmo_timestamp = wp_next_scheduled($llmo_cron_hook);
while ($llmo_timestamp) {
    wp_unschedule_event($llmo_timestamp, $llmo_cron_hook);
    $llmo_timestamp = wp_next_scheduled($llmo_cron_hook);
}

// Delete plugin options
$llmo_options = array(
    'llmo_blog_optimizer_api_key',
    'llmo_blog_optimizer_auto_optimize',
    'llmo_blog_optimizer_post_types',
    'llmo_blog_optimizer_consent',
    'llmo_blog_optimizer_version',
    'llmo_blog_optimizer_pending_ids',
    'llmo_organization_type',
    'llmo_organization_name',
    'llmo_organization_phone',
    'llmo_organization_email',
    'llmo_organization_street',
    'llmo_organization_city',
    'llmo_organization_postal',
    'llmo_organization_country',
    'llmo_organization_logo',
    'llmo_organization_hours',
    'llmo_organization_facebook',
    'llmo_organization_twitter',
    'llmo_organization_linkedin',
    'llmo_organization_instagram',
);

foreach ($llmo_options as $llmo_option) {
    delete_option($llmo_option);
}

// Delete all post meta created by the plugin using WP functions
$llmo_meta_keys = array(
    '_llmo_optimized',
    '_llmo_optimized_at',
    '_llmo_schema_org',
    '_llmo_faq',
    '_llmo_key_takeaways',
    '_llmo_ai_readiness_score',
    '_llmo_pending',
    '_llmo_og_title',
    '_llmo_og_description',
    '_llmo_og_image',
    '_llmo_optimize_error',
);

foreach ($llmo_meta_keys as $llmo_meta_key) {
    delete_post_meta_by_key($llmo_meta_key);
}
