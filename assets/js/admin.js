/**
 * LLMO Blog Optimizer - Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Test API connection
        $('#llmo-test-connection').on('click', function() {
            var $button = $(this);
            var $status = $('#llmo-connection-status');
            var apiKey = $('#llmo_blog_optimizer_api_key').val();
            
            if (!apiKey) {
                $status.removeClass('success').addClass('error').text(llmoAdmin.strings.error);
                return;
            }
            
            $button.prop('disabled', true).text('Testing...');
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
                    $status.removeClass('success').addClass('error').text('✗ ' + llmoAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text(llmoAdmin.strings.testConnection || 'Test Connection');
                }
            });
        });
        
        // Optimize single post from meta box
        $('.llmo-optimize, .llmo-reoptimize').on('click', function() {
            var $button = $(this);
            var postId = $button.data('post-id');
            var isReoptimize = $button.hasClass('llmo-reoptimize');
            
            if (isReoptimize && !confirm(llmoAdmin.strings.confirm_reoptimize)) {
                return;
            }
            
            $button.prop('disabled', true).text(llmoAdmin.strings.optimizing);
            
            $.ajax({
                url: llmoAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'llmo_optimize_post',
                    nonce: llmoAdmin.nonce,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        $button.text(llmoAdmin.strings.optimized);
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        alert(response.data.message || llmoAdmin.strings.error);
                        $button.prop('disabled', false).text(isReoptimize ? 'Re-optimize' : 'Optimize Now');
                    }
                },
                error: function() {
                    alert(llmoAdmin.strings.error);
                    $button.prop('disabled', false).text(isReoptimize ? 'Re-optimize' : 'Optimize Now');
                }
            });
        });
    });

})(jQuery);
