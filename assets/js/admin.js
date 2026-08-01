/**
 * LLMO Blog Optimizer - Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        var strings = (window.llmoAdmin && llmoAdmin.strings) ? llmoAdmin.strings : {};

        // Redirect after external API connect callback.
        if (llmoAdmin && llmoAdmin.redirectUrl) {
            window.location.replace(llmoAdmin.redirectUrl);
            return;
        }

        // Test API connection
        $('#llmo-test-connection').on('click', function() {
            var $button = $(this);
            var $status = $('#llmo-connection-status');
            var apiKey = $('#llmo_blog_optimizer_api_key').val();

            if (!apiKey) {
                $status.removeClass('success').addClass('error').text(strings.error || 'Error');
                return;
            }

            $button.prop('disabled', true).text(strings.testing || 'Testing...');
            $status.text('');

            $.ajax({
                url: llmoAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'llmo_test_connection',
                    nonce: llmoAdmin.nonce,
                    api_key: apiKey
                },
                success: function(response) {
                    if (response.success) {
                        $status.removeClass('error').addClass('success').text('✓ ' + response.data.message);
                    } else {
                        $status.removeClass('success').addClass('error').text('✗ ' + response.data.message);
                    }
                },
                error: function() {
                    $status.removeClass('success').addClass('error').text('✗ ' + (strings.error || 'Error'));
                },
                complete: function() {
                    $button.prop('disabled', false).text(strings.testConnection || 'Test Connection');
                }
            });
        });

        // Optimize single post from meta box
        $(document).on('click', '.llmo-optimize, .llmo-reoptimize', function() {
            var $button = $(this);
            var postId = $button.data('post-id');
            var isReoptimize = $button.hasClass('llmo-reoptimize');

            if (isReoptimize && !confirm(strings.confirm_reoptimize || 'Re-optimize?')) {
                return;
            }

            $button.prop('disabled', true).text(strings.optimizing || 'Optimizing...');
            $('.llmo-optimize-hint').show();

            $.ajax({
                url: llmoAdmin.ajaxurl,
                type: 'POST',
                timeout: 300000,
                data: {
                    action: 'llmo_optimize_post',
                    nonce: llmoAdmin.nonce,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        $button.text(strings.optimized || 'Optimized!');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        alert((response.data && response.data.message) || strings.error || 'Error');
                        $button.prop('disabled', false).text(isReoptimize ? (strings.reoptimize || 'Re-optimize') : (strings.optimizeNow || 'Optimize Now'));
                        $('.llmo-optimize-hint').hide();
                    }
                },
                error: function() {
                    alert(strings.error || 'Error');
                    $button.prop('disabled', false).text(isReoptimize ? (strings.reoptimize || 'Re-optimize') : (strings.optimizeNow || 'Optimize Now'));
                    $('.llmo-optimize-hint').hide();
                }
            });
        });

        // Bulk optimizer: only bind when the page markup exists.
        if (!$('#llmo-progress').length) {
            return;
        }

        $('#llmo-select-all').on('change', function() {
            $('.llmo-post-checkbox').prop('checked', $(this).prop('checked'));
        });

        $('#llmo-optimize-all-pending').on('click', function() {
            var pendingPosts = [];
            $('tbody tr').each(function() {
                var $row = $(this);
                if ($row.find('td:nth-child(4)').text().indexOf('Pending') !== -1) {
                    pendingPosts.push($row.find('.llmo-post-checkbox').val());
                }
            });

            if (pendingPosts.length === 0) {
                alert(strings.noPending || 'No pending posts to optimize');
                return;
            }

            optimizePosts(pendingPosts);
        });

        $('#llmo-optimize-selected').on('click', function() {
            var selectedPosts = $('.llmo-post-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            if (selectedPosts.length === 0) {
                alert(strings.selectPosts || 'Please select posts to optimize');
                return;
            }

            optimizePosts(selectedPosts);
        });

        $(document).on('click', '.llmo-optimize-single', function() {
            var postId = $(this).data('post-id');
            optimizePosts([postId]);
        });

        function optimizePosts(postIds) {
            var total = postIds.length;
            var current = 0;

            $('#llmo-progress').show();
            updateProgress(current, total);

            function optimizeNext() {
                if (current >= total) {
                    alert(strings.optimizationComplete || 'Optimization complete!');
                    location.reload();
                    return;
                }

                var postId = postIds[current];

                $.ajax({
                    url: llmoAdmin.ajaxurl,
                    type: 'POST',
                    timeout: 300000,
                    data: {
                        action: 'llmo_optimize_post',
                        nonce: llmoAdmin.nonce,
                        post_id: postId
                    },
                    success: function() {
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
            var percentage = total > 0 ? (current / total) * 100 : 0;
            $('#llmo-progress-bar').css('width', percentage + '%');
            $('#llmo-progress-text').text(current + ' / ' + total);
        }
    });

})(jQuery);
