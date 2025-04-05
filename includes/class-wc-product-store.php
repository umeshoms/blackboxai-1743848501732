<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Product_Store {
    public function __construct() {
        // Add product meta box
        add_action('add_meta_boxes', array($this, 'add_product_meta_box'));
        add_action('save_post_product', array($this, 'save_product_store_data'));

        // Filter product price and stock
        add_filter('woocommerce_product_get_price', array($this, 'get_store_specific_price'), 10, 2);
        add_filter('woocommerce_product_get_stock_quantity', array($this, 'get_store_specific_stock'), 10, 2);
        add_filter('woocommerce_product_is_in_stock', array($this, 'check_store_specific_stock'), 10, 2);
    }

    public function add_product_meta_box() {
        add_meta_box(
            'wc_product_store_data',
            __('Store Availability', 'wc-multi-store'),
            array($this, 'render_product_meta_box'),
            'product',
            'normal',
            'high'
        );
    }

    public function render_product_meta_box($post) {
        wp_nonce_field('wc_product_store_save_data', 'wc_product_store_nonce');

        $stores = get_posts(array(
            'post_type' => 'wc_store',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));

        $store_data = get_post_meta($post->ID, '_wc_product_store_data', true);
        $global_pricing = get_option('wc_multi_store_global_pricing') === 'yes';
        $global_stock = get_option('wc_multi_store_global_stock') === 'yes';

        echo '<div class="wc-product-store-meta">';
        echo '<p><label><input type="checkbox" name="wc_product_global_availability" value="1" ' . 
             checked($global_pricing && $global_stock, true, false) . ' disabled /> ' . 
             __('Use global pricing and stock settings', 'wc-multi-store') . '</label></p>';

        echo '<table class="widefat wc-product-store-table">';
        echo '<thead><tr>';
        echo '<th>' . __('Store', 'wc-multi-store') . '</th>';
        echo '<th>' . __('Price', 'wc-multi-store') . '</th>';
        echo '<th>' . __('Stock Quantity', 'wc-multi-store') . '</th>';
        echo '<th>' . __('Available', 'wc-multi-store') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($stores as $store) {
            $store_id = $store->ID;
            $price = isset($store_data[$store_id]['price']) ? $store_data[$store_id]['price'] : '';
            $stock = isset($store_data[$store_id]['stock']) ? $store_data[$store_id]['stock'] : '';
            $available = isset($store_data[$store_id]['available']) ? $store_data[$store_id]['available'] : '';

            echo '<tr>';
            echo '<td>' . esc_html($store->post_title) . '</td>';
            echo '<td><input type="number" step="0.01" min="0" name="wc_product_store[' . $store_id . '][price]" value="' . esc_attr($price) . '" /></td>';
            echo '<td><input type="number" step="1" min="0" name="wc_product_store[' . $store_id . '][stock]" value="' . esc_attr($stock) . '" /></td>';
            echo '<td><input type="checkbox" name="wc_product_store[' . $store_id . '][available]" value="1" ' . checked($available, '1', false) . ' /></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    public function save_product_store_data($post_id) {
        if (!isset($_POST['wc_product_store_nonce']) || !wp_verify_nonce($_POST['wc_product_store_nonce'], 'wc_product_store_save_data')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $store_data = array();
        if (isset($_POST['wc_product_store'])) {
            foreach ($_POST['wc_product_store'] as $store_id => $data) {
                $store_data[$store_id] = array(
                    'price' => isset($data['price']) ? wc_format_decimal($data['price']) : '',
                    'stock' => isset($data['stock']) ? absint($data['stock']) : 0,
                    'available' => isset($data['available']) ? '1' : '0'
                );
            }
        }

        update_post_meta($post_id, '_wc_product_store_data', $store_data);
    }

    public function get_store_specific_price($price, $product) {
        $selected_store = $this->get_selected_store();
        if (!$selected_store) {
            return $price;
        }

        $store_data = get_post_meta($product->get_id(), '_wc_product_store_data', true);
        if (isset($store_data[$selected_store]['price']) && $store_data[$selected_store]['price'] !== '') {
            return $store_data[$selected_store]['price'];
        }

        return $price;
    }

    public function get_store_specific_stock($stock, $product) {
        $selected_store = $this->get_selected_store();
        if (!$selected_store) {
            return $stock;
        }

        $store_data = get_post_meta($product->get_id(), '_wc_product_store_data', true);
        if (isset($store_data[$selected_store]['stock'])) {
            return $store_data[$selected_store]['stock'];
        }

        return $stock;
    }

    public function check_store_specific_stock($in_stock, $product) {
        $selected_store = $this->get_selected_store();
        if (!$selected_store) {
            return $in_stock;
        }

        $store_data = get_post_meta($product->get_id(), '_wc_product_store_data', true);
        if (isset($store_data[$selected_store]['available'])) {
            return (bool)$store_data[$selected_store]['available'];
        }

        return $in_stock;
    }

    private function get_selected_store() {
        // This will be implemented later with the store selector functionality
        return isset($_COOKIE['wc_selected_store']) ? absint($_COOKIE['wc_selected_store']) : false;
    }
}