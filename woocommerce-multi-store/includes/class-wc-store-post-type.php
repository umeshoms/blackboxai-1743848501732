<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Store_Post_Type {
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_wc_store', array($this, 'save_store_meta'));
    }

    public function register_post_type() {
        $labels = array(
            'name' => __('Stores', 'wc-multi-store'),
            'singular_name' => __('Store', 'wc-multi-store'),
            'menu_name' => __('Stores', 'wc-multi-store'),
            'add_new' => __('Add New Store', 'wc-multi-store'),
            'add_new_item' => __('Add New Store', 'wc-multi-store'),
            'edit_item' => __('Edit Store', 'wc-multi-store'),
            'new_item' => __('New Store', 'wc-multi-store'),
            'view_item' => __('View Store', 'wc-multi-store'),
            'search_items' => __('Search Stores', 'wc-multi-store'),
            'not_found' => __('No stores found', 'wc-multi-store'),
            'not_found_in_trash' => __('No stores found in trash', 'wc-multi-store'),
        );

        $args = array(
            'labels' => $labels,
            'public' => true,
            'has_archive' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => 'woocommerce',
            'query_var' => true,
            'rewrite' => false,
            'capability_type' => 'post',
            'hierarchical' => false,
            'supports' => array('title', 'thumbnail'),
            'show_in_rest' => true,
        );

        register_post_type('wc_store', $args);
    }

    public function add_meta_boxes() {
        add_meta_box(
            'wc_store_details',
            __('Store Details', 'wc-multi-store'),
            array($this, 'render_store_details_meta_box'),
            'wc_store',
            'normal',
            'high'
        );
    }

    public function render_store_details_meta_box($post) {
        wp_nonce_field('wc_store_save_meta', 'wc_store_meta_nonce');

        $address = get_post_meta($post->ID, '_store_address', true);
        $latitude = get_post_meta($post->ID, '_store_latitude', true);
        $longitude = get_post_meta($post->ID, '_store_longitude', true);
        $manager_id = get_post_meta($post->ID, '_store_manager_id', true);

        echo '<div class="wc-store-meta-fields">';
        echo '<p><label for="store_address">' . __('Address', 'wc-multi-store') . '</label>';
        echo '<input type="text" id="store_address" name="store_address" value="' . esc_attr($address) . '" class="widefat" /></p>';

        echo '<p><label for="store_latitude">' . __('Latitude', 'wc-multi-store') . '</label>';
        echo '<input type="text" id="store_latitude" name="store_latitude" value="' . esc_attr($latitude) . '" class="widefat" /></p>';

        echo '<p><label for="store_longitude">' . __('Longitude', 'wc-multi-store') . '</label>';
        echo '<input type="text" id="store_longitude" name="store_longitude" value="' . esc_attr($longitude) . '" class="widefat" /></p>';

        // Store manager selection
        $users = get_users(array('role__in' => array('shop_manager', 'administrator')));
        echo '<p><label for="store_manager_id">' . __('Store Manager', 'wc-multi-store') . '</label>';
        echo '<select id="store_manager_id" name="store_manager_id" class="widefat">';
        echo '<option value="">' . __('Select Manager', 'wc-multi-store') . '</option>';
        foreach ($users as $user) {
            $selected = selected($manager_id, $user->ID, false);
            echo '<option value="' . esc_attr($user->ID) . '" ' . $selected . '>' . esc_html($user->display_name) . '</option>';
        }
        echo '</select></p>';

        echo '</div>';
    }

    public function save_store_meta($post_id) {
        if (!isset($_POST['wc_store_meta_nonce']) || !wp_verify_nonce($_POST['wc_store_meta_nonce'], 'wc_store_save_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = array(
            '_store_address' => 'sanitize_text_field',
            '_store_latitude' => 'floatval',
            '_store_longitude' => 'floatval',
            '_store_manager_id' => 'intval'
        );

        foreach ($fields as $key => $sanitizer) {
            if (isset($_POST[str_replace('_', '', $key)])) {
                $value = call_user_func($sanitizer, $_POST[str_replace('_', '', $key)]);
                update_post_meta($post_id, $key, $value);
            }
        }
    }
}