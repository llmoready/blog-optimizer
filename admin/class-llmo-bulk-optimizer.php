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
     * Page IDs that are not a post with its own text.
     *
     * @return int[]
     */
    private function system_page_ids() {
        $ids = array();

        $posts_page = (int) get_option('page_for_posts');
        if ($posts_page > 0) {
            $ids[] = $posts_page;
        }

        if (function_exists('wc_get_page_id')) {
            foreach (array('shop', 'cart', 'checkout', 'myaccount') as $page) {
                $page_id = (int) wc_get_page_id($page);
                if ($page_id > 0) {
                    $ids[] = $page_id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Same empty-text rule as the API invalid_page response: no text after shortcodes and tags.
     *
     * @param WP_Post $post Post object.
     * @return bool
     */
    private function post_has_own_text($post) {
        $raw = strip_shortcodes((string) $post->post_content);
        $text = trim(wp_strip_all_tags($raw));
        return $text !== '';
    }

    /**
     * Render bulk optimizer page
     */
    public function render() {
        $post_types = get_option('llmo_blog_optimizer_post_types', array('post'));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;

        $all_query = new WP_Query(array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Shortcodes and stripped text cannot be filtered in SQL.
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
        ));

        $hidden_lookup = array_fill_keys($this->system_page_ids(), true);
        $visible = array();
        $hidden_count = 0;

        foreach ($all_query->posts as $candidate) {
            if (isset($hidden_lookup[$candidate->ID]) || !$this->post_has_own_text($candidate)) {
                $hidden_count++;
                continue;
            }
            $visible[] = $candidate;
        }

        $total_posts = count($visible);
        $optimized_posts = 0;
        foreach ($visible as $candidate) {
            if (get_post_meta($candidate->ID, '_llmo_optimized', true)) {
                $optimized_posts++;
            }
        }
        $pending_posts = max(0, $total_posts - $optimized_posts);
        $total_pages = $total_posts > 0 ? (int) ceil($total_posts / self::PER_PAGE) : 1;
        $posts = array_slice($visible, ($paged - 1) * self::PER_PAGE, self::PER_PAGE);
        
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
                            <span id="llmo-stat-total" data-count="<?php echo esc_attr($total_posts); ?>">
                            <?php
                            /* translators: %s: Number of posts */
                            printf(esc_html__('%s posts', 'llmo-ready-blog-optimizer'), esc_html($total_posts));
                            ?>
                            </span>
                        </td>
                    </tr>
                    <tr class="llmo-bulk-row--ok">
                        <td><strong><?php esc_html_e('Optimized Posts:', 'llmo-ready-blog-optimizer'); ?></strong></td>
                        <td><strong>
                            <span id="llmo-stat-optimized" data-count="<?php echo esc_attr($optimized_posts); ?>">
                            <?php
                            /* translators: %1$s: Number of optimized posts, %2$s: Percentage */
                            printf(esc_html__('%1$s posts (%2$s%%)', 'llmo-ready-blog-optimizer'), esc_html($optimized_posts), esc_html($total_posts > 0 ? round(($optimized_posts / $total_posts) * 100) : 0));
                            ?>
                            </span>
                        </strong></td>
                    </tr>
                    <tr class="llmo-bulk-row--pending">
                        <td><strong><?php esc_html_e('Not Optimized:', 'llmo-ready-blog-optimizer'); ?></strong></td>
                        <td>
                            <span id="llmo-stat-pending" data-count="<?php echo esc_attr($pending_posts); ?>">
                            <?php
                            /* translators: %1$s: Number of pending posts, %2$s: Percentage */
                            printf(esc_html__('%1$s posts (%2$s%%)', 'llmo-ready-blog-optimizer'), esc_html($pending_posts), esc_html($total_posts > 0 ? round(($pending_posts / $total_posts) * 100) : 0));
                            ?>
                            </span>
                        </td>
                    </tr>
                </table>
                <?php if ($hidden_count > 0) : ?>
                    <p class="llmo-hidden-system-pages">
                        <?php
                        /* translators: %s: number of hidden system pages. */
                        $hidden_label = _n(
                            '%s system page without its own text is hidden',
                            '%s system pages without their own text are hidden',
                            $hidden_count,
                            'llmo-ready-blog-optimizer'
                        );
                        echo esc_html(sprintf($hidden_label, number_format_i18n($hidden_count)));
                        ?>
                    </p>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h2><?php esc_html_e('Bulk Actions', 'llmo-ready-blog-optimizer'); ?></h2>
                <p><?php esc_html_e('Select posts on this page to optimize, or optimize all pending posts on this page.', 'llmo-ready-blog-optimizer'); ?></p>
                <p><?php esc_html_e('Each page costs tokens. If the balance is too low, the page stays open and the reason is shown here.', 'llmo-ready-blog-optimizer'); ?></p>
                <div id="llmo-bulk-notice" class="notice is-dismissible" hidden>
                    <p></p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text"><?php esc_html_e('Dismiss this notice', 'llmo-ready-blog-optimizer'); ?></span>
                    </button>
                </div>
                
                <button type="button" class="button button-primary button-large" id="llmo-optimize-all-pending">
                    <?php esc_html_e('Optimize Pending on This Page', 'llmo-ready-blog-optimizer'); ?>
                </button>
                
                <button type="button" class="button button-secondary button-large llmo-btn-selected" id="llmo-optimize-selected">
                    <?php esc_html_e('Optimize Selected', 'llmo-ready-blog-optimizer'); ?>
                </button>

                <p id="llmo-bulk-running" class="description" hidden>
                    <?php esc_html_e('Running. Keep this page open until every post is finished.', 'llmo-ready-blog-optimizer'); ?>
                </p>
                
                <div id="llmo-progress">
                    <p id="llmo-progress-current" hidden>
                        <span class="spinner is-active" id="llmo-progress-spinner"></span>
                        <span id="llmo-progress-current-text"></span>
                    </p>
                    <div class="llmo-progress-track">
                        <div id="llmo-progress-bar"></div>
                        <div id="llmo-progress-bar-active"></div>
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
                            <th>
                                <?php esc_html_e('Readability for AI (before optimization)', 'llmo-ready-blog-optimizer'); ?>
                                <span class="dashicons dashicons-editor-help llmo-score-help" title="<?php echo esc_attr__('How well AI systems understand your original text. The questions and answers from the optimization are not included in this number.', 'llmo-ready-blog-optimizer'); ?>"></span>
                            </th>
                            <th><?php esc_html_e('Actions', 'llmo-ready-blog-optimizer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post): 
                            $optimized = get_post_meta($post->ID, '_llmo_optimized', true);
                            $ai_score = get_post_meta($post->ID, '_llmo_ai_readiness_score', true);
                        ?>
                        <tr class="llmo-bulk-row" data-post-id="<?php echo esc_attr($post->ID); ?>" data-title="<?php echo esc_attr($post->post_title); ?>" data-view-url="<?php echo esc_url(get_permalink($post->ID)); ?>" data-optimized="<?php echo $optimized ? '1' : '0'; ?>">
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
                            <td class="llmo-row-state">
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
                            <td class="llmo-row-score">
                                <?php if ($ai_score !== '' && $ai_score !== false && $ai_score !== null): ?>
                                    <span class="llmo-bulk-score">
                                        <?php echo esc_html($ai_score); ?>/100
                                    </span>
                                <?php else: ?>
                                    <span class="llmo-bulk-score--empty">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" 
                                        class="button button-small llmo-optimize-single" 
                                        data-post-id="<?php echo esc_attr($post->ID); ?>"
                                        data-optimized="<?php echo $optimized ? '1' : '0'; ?>">
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
