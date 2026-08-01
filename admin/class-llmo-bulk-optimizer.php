<?php
/**
 * LLMO Bulk Optimizer
 *
 * @package LLMO_Blog_Optimizer
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bulk Optimizer Class
 */
class LLMO_Blog_Optimizer_Bulk {
    
    /**
     * Posts per page on the bulk list.
     */
    const PER_PAGE = 50;

    /**
     * Render bulk optimizer page
     */
    public function render() {
        $post_types = get_option('llmo_blog_optimizer_post_types', array('post'));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;

        $count_query = new WP_Query(array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => false,
        ));
        $total_posts = (int) $count_query->found_posts;

        // Admin-only stats (infrequent page load). SlowDBQuery is expected for this count.
        // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        $optimized_posts = (int) (new WP_Query(array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => '_llmo_optimized',
            'meta_value' => '1',
        )))->found_posts;
        // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value

        $pending_posts = max(0, $total_posts - $optimized_posts);

        $list_query = new WP_Query(array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => self::PER_PAGE,
            'paged' => $paged,
            'orderby' => 'date',
            'order' => 'DESC',
        ));
        $posts = $list_query->posts;
        $total_pages = (int) $list_query->max_num_pages;
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('LLMO Blog Optimizer', 'llmo-ready-blog-optimizer'); ?></h1>
            
            <div class="notice notice-info">
                <p><strong><?php esc_html_e('Status:', 'llmo-ready-blog-optimizer'); ?></strong> <?php esc_html_e('Plugin is active and ready to optimize your blog posts.', 'llmo-ready-blog-optimizer'); ?></p>
            </div>
            
            <div class="card">
                <h2><?php esc_html_e('Statistics', 'llmo-ready-blog-optimizer'); ?></h2>
                <table class="widefat llmo-bulk-stats-table">
                    <tr>
                        <td class="llmo-bulk-stats-table__label"><strong><?php esc_html_e('Total Posts:', 'llmo-ready-blog-optimizer'); ?></strong></td>
                        <td>
                            <?php
                            /* translators: %s: Number of posts */
                            printf(esc_html__('%s posts', 'llmo-ready-blog-optimizer'), esc_html($total_posts));
                            ?>
                        </td>
                    </tr>
                    <tr class="llmo-bulk-row--ok">
                        <td><strong><?php esc_html_e('Optimized Posts:', 'llmo-ready-blog-optimizer'); ?></strong></td>
                        <td><strong>
                            <?php
                            /* translators: %1$s: Number of optimized posts, %2$s: Percentage */
                            printf(esc_html__('%1$s posts (%2$s%%)', 'llmo-ready-blog-optimizer'), esc_html($optimized_posts), esc_html($total_posts > 0 ? round(($optimized_posts / $total_posts) * 100) : 0));
                            ?>
                        </strong></td>
                    </tr>
                    <tr class="llmo-bulk-row--pending">
                        <td><strong><?php esc_html_e('Not Optimized:', 'llmo-ready-blog-optimizer'); ?></strong></td>
                        <td>
                            <?php
                            /* translators: %1$s: Number of pending posts, %2$s: Percentage */
                            printf(esc_html__('%1$s posts (%2$s%%)', 'llmo-ready-blog-optimizer'), esc_html($pending_posts), esc_html($total_posts > 0 ? round(($pending_posts / $total_posts) * 100) : 0));
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="card">
                <h2><?php esc_html_e('Bulk Actions', 'llmo-ready-blog-optimizer'); ?></h2>
                <p><?php esc_html_e('Select posts on this page to optimize, or optimize all pending posts on this page.', 'llmo-ready-blog-optimizer'); ?></p>
                
                <button type="button" class="button button-primary button-large" id="llmo-optimize-all-pending">
                    <?php esc_html_e('Optimize Pending on This Page', 'llmo-ready-blog-optimizer'); ?>
                </button>
                
                <button type="button" class="button button-secondary button-large llmo-btn-selected" id="llmo-optimize-selected">
                    <?php esc_html_e('Optimize Selected', 'llmo-ready-blog-optimizer'); ?>
                </button>
                
                <div id="llmo-progress">
                    <h3><?php esc_html_e('Optimization Progress', 'llmo-ready-blog-optimizer'); ?></h3>
                    <div class="llmo-progress-track">
                        <div id="llmo-progress-bar"></div>
                    </div>
                    <p id="llmo-progress-text">0 / 0</p>
                </div>
            </div>

            <?php if ($total_pages > 1) : ?>
                <div class="llmo-bulk-pagination tablenav">
                    <div class="tablenav-pages">
                        <?php
                        echo wp_kses_post(
                            paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'current' => $paged,
                                'total' => $total_pages,
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                            ))
                        );
                        ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="post" id="llmo-bulk-form">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="check-column">
                                <input type="checkbox" id="llmo-select-all">
                            </td>
                            <th><?php esc_html_e('Title', 'llmo-ready-blog-optimizer'); ?></th>
                            <th><?php esc_html_e('Date', 'llmo-ready-blog-optimizer'); ?></th>
                            <th><?php esc_html_e('Status', 'llmo-ready-blog-optimizer'); ?></th>
                            <th><?php esc_html_e('AI Score', 'llmo-ready-blog-optimizer'); ?></th>
                            <th><?php esc_html_e('Actions', 'llmo-ready-blog-optimizer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post): 
                            $optimized = get_post_meta($post->ID, '_llmo_optimized', true);
                            $ai_score = get_post_meta($post->ID, '_llmo_ai_readiness_score', true);
                            $score_int = (int) $ai_score;
                            $score_mod = $score_int >= 80 ? 'high' : ($score_int >= 60 ? 'mid' : 'low');
                        ?>
                        <tr>
                            <th class="check-column">
                                <input type="checkbox" name="post_ids[]" value="<?php echo esc_attr($post->ID); ?>" class="llmo-post-checkbox" data-optimized="<?php echo $optimized ? '1' : '0'; ?>">
                            </th>
                            <td>
                                <?php if ($optimized): ?>
                                    <span class="llmo-bulk-mark llmo-bulk-mark--ok" title="<?php esc_attr_e('LLMO-optimized', 'llmo-ready-blog-optimizer'); ?>">✓</span>
                                <?php else: ?>
                                    <span class="llmo-bulk-mark llmo-bulk-mark--pending" title="<?php esc_attr_e('Not optimized', 'llmo-ready-blog-optimizer'); ?>">○</span>
                                <?php endif; ?>
                                <strong>
                                    <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>">
                                        <?php echo esc_html($post->post_title); ?>
                                    </a>
                                </strong>
                                <?php if ($optimized): ?>
                                    <span class="llmo-bulk-badge">● <?php esc_html_e('AI-optimized', 'llmo-ready-blog-optimizer'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(get_the_date('', $post->ID)); ?></td>
                            <td>
                                <?php if ($optimized): ?>
                                    <span class="llmo-bulk-status--ok">
                                        <?php esc_html_e('Optimized', 'llmo-ready-blog-optimizer'); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="llmo-bulk-status--pending">
                                        <?php esc_html_e('Pending', 'llmo-ready-blog-optimizer'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($ai_score): ?>
                                    <strong class="llmo-bulk-score--<?php echo esc_attr($score_mod); ?>">
                                        <?php echo esc_html($ai_score); ?>/100
                                    </strong>
                                <?php else: ?>
                                    <span class="llmo-bulk-score--empty">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" 
                                        class="button button-small llmo-optimize-single" 
                                        data-post-id="<?php echo esc_attr($post->ID); ?>">
                                    <?php $optimized ? esc_html_e('Re-optimize', 'llmo-ready-blog-optimizer') : esc_html_e('Optimize', 'llmo-ready-blog-optimizer'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        </div>
        <?php
    }
}
