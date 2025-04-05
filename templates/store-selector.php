<div class="wc-store-selector-container">
    <div class="wc-store-selector">
        <div class="wc-store-selector-current">
            <?php if ($selected_store_data) : ?>
                <div class="wc-store-selected">
                    <span class="wc-store-selected-name"><?php echo esc_html($selected_store_data->post_title); ?></span>
                    <span class="wc-store-selected-address"><?php echo esc_html(get_post_meta($selected_store_data->ID, '_store_address', true)); ?></span>
                </div>
            <?php else : ?>
                <div class="wc-store-not-selected">
                    <?php _e('No store selected', 'wc-multi-store'); ?>
                </div>
            <?php endif; ?>
            <button type="button" class="wc-store-selector-toggle">
                <span class="dashicons dashicons-arrow-down-alt2"></span>
            </button>
        </div>

        <div class="wc-store-selector-dropdown">
            <div class="wc-store-selector-actions">
                <button type="button" class="button wc-store-use-location">
                    <span class="dashicons dashicons-location"></span>
                    <?php _e('Use My Location', 'wc-multi-store'); ?>
                </button>
                <button type="button" class="button wc-store-toggle-map">
                    <span class="dashicons dashicons-location-alt"></span>
                    <?php _e('View on Map', 'wc-multi-store'); ?>
                </button>
            </div>

            <div class="wc-store-selector-map" style="display: none;"></div>

            <div class="wc-store-selector-list">
                <?php foreach ($stores as $store) : 
                    $distance = WC_Store_Selector::get_store_distance(
                        $store->ID,
                        isset($_COOKIE['wc_user_lat']) ? floatval($_COOKIE['wc_user_lat']) : 0,
                        isset($_COOKIE['wc_user_lng']) ? floatval($_COOKIE['wc_user_lng']) : 0
                    );
                ?>
                    <div class="wc-store-item" data-store-id="<?php echo esc_attr($store->ID); ?>">
                        <div class="wc-store-info">
                            <h4 class="wc-store-name"><?php echo esc_html($store->post_title); ?></h4>
                            <p class="wc-store-address"><?php echo esc_html(get_post_meta($store->ID, '_store_address', true)); ?></p>
                            <?php if ($distance !== false) : ?>
                                <p class="wc-store-distance">
                                    <?php printf(
                                        __('%s %s away', 'wc-multi-store'),
                                        $distance,
                                        get_option('wc_multi_store_distance_unit', 'km')
                                    ); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button wc-store-select-button">
                            <?php _e('Select', 'wc-multi-store'); ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>