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
     * Check if post needs optimization
     */
    public static function needs_optimization($post_id) {
        return !get_post_meta($post_id, '_llmo_optimized', true);
    }
}
