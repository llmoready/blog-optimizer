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
     * Render bulk optimizer page
     */
    public function render() {
        $post_types = get_option('llmo_blog_optimizer_post_types', array('post'));
        
        // Get posts
        $args = array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        );
        
        $posts = get_posts($args);
        
        // Calculate stats
        $total_posts = count($posts);
        $optimized_posts = 0;
        $pending_posts = 0;
        
        foreach ($posts as $post) {
            if (get_post_meta($post->ID, '_llmo_optimized', true)) {
                $optimized_posts++;
            } else {
                $pending_posts++;
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('LLMO Blog Optimizer', 'llmo-blog-optimizer'); ?></h1>
            
            <div class="notice notice-info">
                <p><strong><?php esc_html_e('Status:', 'llmo-blog-optimizer'); ?></strong> <?php esc_html_e('Plugin is active and ready to optimize your blog posts.', 'llmo-blog-optimizer'); ?></p>
            </div>
            
            <div class="card">
                <h2><?php esc_html_e('Statistics', 'llmo-blog-optimizer'); ?></h2>
                <table class="widefat" style="width: auto; min-width: 500px;">
                    <tr>
                        <td style="width: 200px;"><strong><?php esc_html_e('Total Posts:', 'llmo-blog-optimizer'); ?></strong></td>
                        <td><?php printf(esc_html__('%s posts', 'llmo-blog-optimizer'), esc_html($total_posts)); ?></td>
                    </tr>
                    <tr style="background: #e8f5e9;">
                        <td><strong><?php esc_html_e('✓ Optimized Posts:', 'llmo-blog-optimizer'); ?></strong></td>
                        <td><strong><?php printf(esc_html__('%1$s posts (%2$s%%)', 'llmo-blog-optimizer'), esc_html($optimized_posts), esc_html($total_posts > 0 ? round(($optimized_posts / $total_posts) * 100) : 0)); ?></strong></td>
                    </tr>
                    <tr style="background: #fff3e0;">
                        <td><strong><?php esc_html_e('○ Not Optimized:', 'llmo-blog-optimizer'); ?></strong></td>
                        <td><?php printf(esc_html__('%1$s posts (%2$s%%)', 'llmo-blog-optimizer'), esc_html($pending_posts), esc_html($total_posts > 0 ? round(($pending_posts / $total_posts) * 100) : 0)); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="card">
                <h2><?php esc_html_e('Bulk Actions', 'llmo-blog-optimizer'); ?></h2>
                <p><?php esc_html_e('Select posts to optimize or optimize all pending posts at once.', 'llmo-blog-optimizer'); ?></p>
                
                <button type="button" class="button button-primary button-large" id="llmo-optimize-all-pending">
                    <?php esc_html_e('Optimize All Pending Posts', 'llmo-blog-optimizer'); ?>
                    (<?php echo esc_html($pending_posts); ?>)
                </button>
                
                <button type="button" class="button button-secondary button-large" id="llmo-optimize-selected" style="margin-left: 10px;">
                    <?php esc_html_e('Optimize Selected', 'llmo-blog-optimizer'); ?>
                </button>
                
                <div id="llmo-progress" style="display: none; margin-top: 20px;">
                    <h3><?php esc_html_e('Optimization Progress', 'llmo-blog-optimizer'); ?></h3>
                    <div style="background: #f0f0f0; height: 30px; border-radius: 5px; overflow: hidden;">
                        <div id="llmo-progress-bar" style="background: #0073aa; height: 100%; width: 0%; transition: width 0.3s;"></div>
                    </div>
                    <p id="llmo-progress-text" style="margin: 10px 0 0 0;">0 / 0</p>
                </div>
            </div>
            
            <form method="post" id="llmo-bulk-form">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="check-column">
                                <input type="checkbox" id="llmo-select-all">
                            </td>
                            <th><?php esc_html_e('Title', 'llmo-blog-optimizer'); ?></th>
                            <th><?php esc_html_e('Date', 'llmo-blog-optimizer'); ?></th>
                            <th><?php esc_html_e('Status', 'llmo-blog-optimizer'); ?></th>
                            <th><?php esc_html_e('AI Score', 'llmo-blog-optimizer'); ?></th>
                            <th><?php esc_html_e('Actions', 'llmo-blog-optimizer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post): 
                            $optimized = get_post_meta($post->ID, '_llmo_optimized', true);
                            $ai_score = get_post_meta($post->ID, '_llmo_ai_readiness_score', true);
                        ?>
                        <tr>
                            <th class="check-column">
                                <input type="checkbox" name="post_ids[]" value="<?php echo esc_attr($post->ID); ?>" class="llmo-post-checkbox">
                            </th>
                            <td>
                                <?php if ($optimized): ?>
                                    <span style="color: #4caf50; font-size: 16px; margin-right: 8px;" title="<?php esc_attr_e('LLMO-optimized', 'llmo-blog-optimizer'); ?>">✓</span>
                                <?php else: ?>
                                    <span style="color: #ff9800; font-size: 16px; margin-right: 8px;" title="<?php esc_attr_e('Not optimized', 'llmo-blog-optimizer'); ?>">○</span>
                                <?php endif; ?>
                                <strong>
                                    <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>">
                                        <?php echo esc_html($post->post_title); ?>
                                    </a>
                                </strong>
                                <?php if ($optimized): ?>
                                    <span style="color: #4caf50; font-size: 11px; margin-left: 8px;">● <?php esc_html_e('AI-optimized', 'llmo-blog-optimizer'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(get_the_date('', $post->ID)); ?></td>
                            <td>
                                <?php if ($optimized): ?>
                                    <span style="color: #4caf50;">
                                        <?php esc_html_e('Optimized', 'llmo-blog-optimizer'); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #ff9800;">
                                        <?php esc_html_e('Pending', 'llmo-blog-optimizer'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($ai_score): ?>
                                    <strong style="color: <?php echo $ai_score >= 80 ? '#4caf50' : ($ai_score >= 60 ? '#ff9800' : '#dc3232'); ?>">
                                        <?php echo esc_html($ai_score); ?>/100
                                    </strong>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" 
                                        class="button button-small llmo-optimize-single" 
                                        data-post-id="<?php echo esc_attr($post->ID); ?>">
                                    <?php $optimized ? esc_html_e('Re-optimize', 'llmo-blog-optimizer') : esc_html_e('Optimize', 'llmo-blog-optimizer'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Select all checkbox
            $('#llmo-select-all').on('change', function() {
                $('.llmo-post-checkbox').prop('checked', $(this).prop('checked'));
            });
            
            // Optimize all pending
            $('#llmo-optimize-all-pending').on('click', function() {
                var pendingPosts = [];
                $('tbody tr').each(function() {
                    var $row = $(this);
                    if ($row.find('td:nth-child(4)').text().indexOf('Pending') !== -1) {
                        pendingPosts.push($row.find('.llmo-post-checkbox').val());
                    }
                });
                
                if (pendingPosts.length === 0) {
                    alert('<?php esc_html_e('No pending posts to optimize', 'llmo-blog-optimizer'); ?>');
                    return;
                }
                
                optimizePosts(pendingPosts);
            });
            
            // Optimize selected
            $('#llmo-optimize-selected').on('click', function() {
                var selectedPosts = $('.llmo-post-checkbox:checked').map(function() {
                    return $(this).val();
                }).get();
                
                if (selectedPosts.length === 0) {
                    alert('<?php esc_html_e('Please select posts to optimize', 'llmo-blog-optimizer'); ?>');
                    return;
                }
                
                optimizePosts(selectedPosts);
            });
            
            // Optimize single post
            $('.llmo-optimize-single').on('click', function() {
                var postId = $(this).data('post-id');
                optimizePosts([postId]);
            });
            
            // Optimize posts function
            function optimizePosts(postIds) {
                var total = postIds.length;
                var current = 0;
                
                $('#llmo-progress').show();
                updateProgress(current, total);
                
                function optimizeNext() {
                    if (current >= total) {
                        alert('<?php esc_html_e('Optimization complete!', 'llmo-blog-optimizer'); ?>');
                        location.reload();
                        return;
                    }
                    
                    var postId = postIds[current];
                    
                    $.ajax({
                        url: llmoAdmin.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'llmo_optimize_post',
                            nonce: llmoAdmin.nonce,
                            post_id: postId
                        },
                        success: function(response) {
                            current++;
                            updateProgress(current, total);
                            optimizeNext();
                        },
                        error: function() {
                            current++;
                            updateProgress(current, total);
                            optimizeNext();
                        }
                    });
                }
                
                optimizeNext();
            }
            
            function updateProgress(current, total) {
                var percentage = (current / total) * 100;
                $('#llmo-progress-bar').css('width', percentage + '%');
                $('#llmo-progress-text').text(current + ' / ' + total);
            }
        });
        </script>
        <?php
    }
}
