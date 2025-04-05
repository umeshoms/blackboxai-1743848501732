jQuery(function($) {
    'use strict';

    $(document).on('click', '.wc-store-change-button', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        $button.addClass('loading');
        
        // Get the store selector HTML from the main widget
        const $storeSelector = $('.wc-store-selector-dropdown').clone();
        $storeSelector.addClass('wc-store-selector-popup').show();
        
        // Create overlay
        const $overlay = $('<div class="wc-store-selector-overlay"></div>');
        
        // Add to DOM
        $('body').append($overlay).append($storeSelector);
        
        // Position the popup
        $storeSelector.css({
            'position': 'fixed',
            'top': '50%',
            'left': '50%',
            'transform': 'translate(-50%, -50%)',
            'z-index': 9999
        });
        
        // Close handlers
        $overlay.on('click', function() {
            $storeSelector.remove();
            $overlay.remove();
            $button.removeClass('loading');
        });
        
        // Store selection handler
        $storeSelector.on('click', '.wc-store-select-button', function(e) {
            e.preventDefault();
            const storeId = $(this).closest('.wc-store-item').data('store-id');
            
            $.ajax({
                url: wc_store_shortcode_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_change_store',
                    store_id: storeId,
                    nonce: $button.data('nonce')
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    }
                }
            });
        });
    });
});