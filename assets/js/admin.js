/**
 * LLMO Blog Optimizer - Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        var strings = (window.llmoAdmin && llmoAdmin.strings) ? llmoAdmin.strings : {};
        var pollInterval = (llmoAdmin && llmoAdmin.pollInterval) ? llmoAdmin.pollInterval : 10000;
        var pollMaxAttempts = (llmoAdmin && llmoAdmin.pollMaxAttempts) ? llmoAdmin.pollMaxAttempts : 30;

        // Redirect after external API connect callback.
        if (llmoAdmin && llmoAdmin.redirectUrl) {
            window.location.replace(llmoAdmin.redirectUrl);
            return;
        }

        function pollUntilDone(postId, attempt) {
            attempt = attempt || 0;

            var deferred = $.Deferred();

            if (attempt >= pollMaxAttempts) {
                deferred.resolve({ status: 'timeout' });
                return deferred.promise();
            }

            $.ajax({
                url: llmoAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'llmo_poll_optimization',
                    nonce: llmoAdmin.nonce,
                    post_id: postId
                }
            }).done(function(response) {
                if (response && response.success && response.data && response.data.status === 'done') {
                    deferred.resolve(response.data);
                    return;
                }

                if (response && !response.success) {
                    deferred.reject(response);
                    return;
                }

                window.setTimeout(function() {
                    pollUntilDone(postId, attempt + 1).done(deferred.resolve).fail(deferred.reject);
                }, pollInterval);
            }).fail(function() {
                window.setTimeout(function() {
                    pollUntilDone(postId, attempt + 1).done(deferred.resolve).fail(deferred.reject);
                }, pollInterval);
            });

            return deferred.promise();
        }

        function queueAndPoll(postId) {
            var deferred = $.Deferred();

            $.ajax({
                url: llmoAdmin.ajaxurl,
                type: 'POST',
                timeout: 120000,
                data: {
                    action: 'llmo_optimize_post',
                    nonce: llmoAdmin.nonce,
                    post_id: postId
                }
            }).done(function(response) {
                if (!response || !response.success) {
                    deferred.reject(response);
                    return;
                }

                pollUntilDone(postId).done(deferred.resolve).fail(deferred.reject);
            }).fail(function() {
                deferred.reject();
            });

            return deferred.promise();
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

            queueAndPoll(postId).done(function(data) {
                if (data && data.status === 'timeout') {
                    alert(strings.optimizationTimeout || 'Still running…');
                    location.reload();
                    return;
                }
                $button.text(strings.optimized || 'Optimized!');
                setTimeout(function() {
                    location.reload();
                }, 800);
            }).fail(function(response) {
                var message = (response && response.data && response.data.message) || strings.error || 'Error';
                alert(message);
                $button.prop('disabled', false).text(isReoptimize ? (strings.reoptimize || 'Re-optimize') : (strings.optimizeNow || 'Optimize Now'));
                $('.llmo-optimize-hint').hide();
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
            $('.llmo-post-checkbox').each(function() {
                if ($(this).data('optimized') !== 1 && $(this).data('optimized') !== '1') {
                    pendingPosts.push($(this).val());
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

                queueAndPoll(postId).always(function() {
                    current++;
                    updateProgress(current, total);
                    optimizeNext();
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
