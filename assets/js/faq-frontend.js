/**
 * LLMO FAQ Frontend JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // FAQ Accordion functionality
        $('.llmo-faq-question').on('click', function() {
            var $item = $(this).closest('.llmo-faq-item');
            var $container = $(this).closest('.llmo-faq-container');
            var style = $container.data('style');
            
            if (style === 'accordion') {
                // Close all other items
                $container.find('.llmo-faq-item').not($item).removeClass('active');
            }
            
            // Toggle current item
            $item.toggleClass('active');
        });
        
        // Open first item by default
        $('.llmo-faq-container[data-style="accordion"] .llmo-faq-item:first-child').addClass('active');
    });

})(jQuery);
