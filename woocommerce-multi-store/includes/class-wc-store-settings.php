<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Store_Settings {
    public function __construct() {
        add_filter('woocommerce_get_sections_products', array($this, 'add_settings_section'));
        add_filter('woocommerce_get_settings_products', array($this, 'add_settings_fields'), 10, 2);
        add_action('woocommerce_admin_field_wc_store_google_maps', array($this, 'render_google_maps_field'));
    }

    public function add_settings_section($sections) {
        $sections['wc_multi_store'] = __('Multi-Store Settings', 'wc-multi-store');
        return $sections;
    }

    public function add_settings_fields($settings, $current_section) {
        if ($current_section !== 'wc_multi_store') {
            return $settings;
        }

        $settings = array(
            array(
                'title' => __('Multi-Store Configuration', 'wc-multi-store'),
                'type'  => 'title',
                'desc'  => __('Configure global settings for the multi-store functionality.', 'wc-multi-store'),
                'id'    => 'wc_multi_store_options'
            ),
            array(
                'title'    => __('Google Maps API Key', 'wc-multi-store'),
                'desc'     => __('Required for location-based features and maps display.', 'wc-multi-store'),
                'id'       => 'wc_multi_store_google_maps_api_key',
                'type'     => 'text',
                'css'      => 'min-width: 400px;',
                'desc_tip' => true
            ),
            array(
                'title'    => __('Default Distance Unit', 'wc-multi-store'),
                'id'       => 'wc_multi_store_distance_unit',
                'type'     => 'select',
                'options'  => array(
                    'km' => __('Kilometers', 'wc-multi-store'),
                    'mi' => __('Miles', 'wc-multi-store')
                ),
                'default'  => 'km'
            ),
            array(
                'title'    => __('Enable Global Pricing', 'wc-multi-store'),
                'desc'     => __('Allow products to have the same price across all stores', 'wc-multi-store'),
                'id'       => 'wc_multi_store_global_pricing',
                'type'     => 'checkbox',
                'default'  => 'no'
            ),
            array(
                'title'    => __('Enable Global Stock', 'wc-multi-store'),
                'desc'     => __('Allow products to share stock across all stores', 'wc-multi-store'),
                'id'       => 'wc_multi_store_global_stock',
                'type'     => 'checkbox',
                'default'  => 'no'
            ),
            array(
                'title'    => __('Store Selector Position', 'wc-multi-store'),
                'desc'     => __('Where to display the store selector widget', 'wc-multi-store'),
                'id'       => 'wc_multi_store_selector_position',
                'type'     => 'select',
                'options'  => array(
                    'header' => __('Header', 'wc-multi-store'),
                    'sidebar' => __('Sidebar', 'wc-multi-store'),
                    'both' => __('Both', 'wc-multi-store')
                ),
                'default'  => 'header'
            ),
            array(
                'type' => 'sectionend',
                'id'   => 'wc_multi_store_options'
            ),
            array(
                'type' => 'wc_store_google_maps'
            )
        );

        return $settings;
    }

    public function render_google_maps_field() {
        ?>
        <div class="wc-store-maps-test">
            <h3><?php _e('Google Maps Test', 'wc-multi-store'); ?></h3>
            <p class="description"><?php _e('Test if your Google Maps API key is working correctly.', 'wc-multi-store'); ?></p>
            <div id="wc-store-maps-test-container" style="width: 100%; height: 300px; margin: 10px 0;"></div>
            <button type="button" id="wc-store-test-maps" class="button button-secondary">
                <?php _e('Test Maps', 'wc-multi-store'); ?>
            </button>
        </div>
        <?php
    }
}