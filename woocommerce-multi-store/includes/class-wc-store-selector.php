<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Store_Selector {
    public function __construct() {
        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('woocommerce_before_main_content', array($this, 'display_store_selector'), 5);
        add_action('woocommerce_before_cart', array($this, 'display_store_selector'), 5);
        add_action('woocommerce_before_checkout_form', array($this, 'display_store_selector'), 5);
        add_action('wp_ajax_wc_set_selected_store', array($this, 'set_selected_store'));
        add_action('wp_ajax_nopriv_wc_set_selected_store', array($this, 'set_selected_store'));

        // Product filtering
        add_filter('woocommerce_product_query', array($this, 'filter_products_by_store'));
    }

    public function enqueue_scripts() {
        $api_key = get_option('wc_multi_store_google_maps_api_key');
        if ($api_key) {
            wp_enqueue_script(
                'wc-store-google-maps',
                'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($api_key) . '&libraries=places',
                array(),
                null,
                true
            );
        }

        wp_enqueue_script(
            'wc-store-selector',
            plugin_dir_url(__FILE__) . '../assets/js/store-selector.js',
            array('jquery'),
            WC_MULTI_STORE_VERSION,
            true
        );

        wp_localize_script('wc-store-selector', 'wc_store_selector_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'current_store' => $this->get_selected_store(),
            'distance_unit' => get_option('wc_multi_store_distance_unit', 'km'),
            'i18n' => array(
                'select_store' => __('Select a Store', 'wc-multi-store'),
                'nearest_store' => __('Nearest Store', 'wc-multi-store'),
                'use_my_location' => __('Use My Location', 'wc-multi-store'),
                'store_finder' => __('Store Finder', 'wc-multi-store'),
                'view_on_map' => __('View on Map', 'wc-multi-store'),
                'hide_map' => __('Hide Map', 'wc-multi-store'),
                'distance_away' => __('%s away', 'wc-multi-store')
            )
        ));

        wp_enqueue_style(
            'wc-store-selector',
            plugin_dir_url(__FILE__) . '../assets/css/store-selector.css',
            array(),
            WC_MULTI_STORE_VERSION
        );
    }

    public function display_store_selector() {
        $stores = get_posts(array(
            'post_type' => 'wc_store',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        if (empty($stores)) {
            return;
        }

        $selected_store = $this->get_selected_store();
        $selected_store_data = $selected_store ? get_post($selected_store) : null;
        $position = get_option('wc_multi_store_selector_position', 'header');

        // Only show if position matches current hook
        $current_hook = current_filter();
        $show_in_header = in_array($current_hook, array('woocommerce_before_main_content')) && in_array($position, array('header', 'both'));
        $show_in_sidebar = in_array($current_hook, array('woocommerce_before_cart', 'woocommerce_before_checkout_form')) && in_array($position, array('sidebar', 'both'));

        if (!$show_in_header && !$show_in_sidebar) {
            return;
        }

        include plugin_dir_path(__FILE__) . '../templates/store-selector.php';
    }

    public function set_selected_store() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wc_store_selector_nonce')) {
            wp_send_json_error(__('Invalid nonce', 'wc-multi-store'));
        }

        $store_id = isset($_POST['store_id']) ? absint($_POST['store_id']) : 0;
        if ($store_id) {
            setcookie('wc_selected_store', $store_id, time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN);
            $_COOKIE['wc_selected_store'] = $store_id;
            wp_send_json_success();
        }

        wp_send_json_error(__('Invalid store ID', 'wc-multi-store'));
    }

    public function filter_products_by_store($query) {
        $selected_store = $this->get_selected_store();
        if (!$selected_store || is_admin() || !$query->is_main_query()) {
            return;
        }

        $meta_query = (array) $query->get('meta_query');
        $meta_query[] = array(
            'relation' => 'OR',
            array(
                'key' => '_wc_product_store_data',
                'compare' => 'NOT EXISTS'
            ),
            array(
                'key' => '_wc_product_store_data',
                'value' => sprintf('s:%d:"%d";s:7:"available";s:1:"1"', strlen($selected_store), $selected_store),
                'compare' => 'LIKE'
            )
        );

        $query->set('meta_query', $meta_query);
    }

    public function get_selected_store() {
        return isset($_COOKIE['wc_selected_store']) ? absint($_COOKIE['wc_selected_store']) : false;
    }

    public static function get_store_distance($store_id, $user_lat, $user_lng) {
        $store_lat = get_post_meta($store_id, '_store_latitude', true);
        $store_lng = get_post_meta($store_id, '_store_longitude', true);

        if (!$store_lat || !$store_lng) {
            return false;
        }

        $theta = $user_lng - $store_lng;
        $distance = sin(deg2rad($user_lat)) * sin(deg2rad($store_lat)) + 
                   cos(deg2rad($user_lat)) * cos(deg2rad($store_lat)) * cos(deg2rad($theta));
        $distance = acos($distance);
        $distance = rad2deg($distance);
        $distance = $distance * 60 * 1.1515;

        if (get_option('wc_multi_store_distance_unit', 'km') === 'km') {
            $distance *= 1.609344;
        }

        return round($distance, 2);
    }
}