<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Store_Orders {
    public function __construct() {
        // Store manager capabilities
        add_action('init', array($this, 'add_store_manager_role'));
        add_action('user_register', array($this, 'assign_default_shop_manager_role'));
        add_action('show_user_profile', array($this, 'add_store_assignment_field'));
        add_action('edit_user_profile', array($this, 'add_store_assignment_field'));
        add_action('personal_options_update', array($this, 'save_store_assignment'));
        add_action('edit_user_profile_update', array($this, 'save_store_assignment'));

        // Order management
        add_action('woocommerce_checkout_create_order', array($this, 'set_order_store_meta'));
        add_filter('woocommerce_order_data_store_cpt_get_orders_query', array($this, 'handle_store_order_query'), 10, 2);
        add_action('pre_get_posts', array($this, 'filter_orders_by_store'));
        add_filter('views_edit-shop_order', array($this, 'add_store_order_views'));

        // Admin UI
        add_filter('manage_shop_order_posts_columns', array($this, 'add_store_order_column'), 20);
        add_action('manage_shop_order_posts_custom_column', array($this, 'show_store_order_column'), 10, 2);
        add_action('restrict_manage_posts', array($this, 'add_store_filter_dropdown'));

        // Order actions for store managers
        add_filter('woocommerce_order_actions', array($this, 'filter_order_actions_for_managers'), 10, 2);
        add_filter('bulk_actions-edit-shop_order', array($this, 'filter_bulk_actions_for_managers'));
    }

    public function add_store_manager_role() {
        add_role('shop_manager', __('Shop Manager', 'wc-multi-store'), array(
            'read' => true,
            'edit_posts' => true,
            'delete_posts' => true,
            'upload_files' => true,
            'manage_woocommerce' => true,
            'view_woocommerce_reports' => true,
            'edit_shop_orders' => true,
            'edit_others_shop_orders' => false,
            'edit_private_shop_orders' => false,
            'edit_published_shop_orders' => true,
            'delete_shop_orders' => false,
            'delete_private_shop_orders' => false,
            'delete_published_shop_orders' => false,
            'delete_others_shop_orders' => false,
            'publish_shop_orders' => true,
            'read_private_shop_orders' => false,
            'edit_shop_order' => true,
            'read_shop_order' => true,
            'delete_shop_order' => false,
            'edit_shop_orders' => true,
            'edit_others_shop_orders' => false,
            'manage_woocommerce_orders' => true
        ));
    }

    public function assign_default_shop_manager_role($user_id) {
        if (current_user_can('edit_user', $user_id)) {
            $user = new WP_User($user_id);
            if (in_array('administrator', $user->roles)) {
                return;
            }
            $user->add_role('shop_manager');
        }
    }

    public function add_store_assignment_field($user) {
        if (!current_user_can('edit_user', $user->ID) || !current_user_can('manage_woocommerce')) {
            return;
        }

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

        $assigned_stores = get_user_meta($user->ID, '_wc_assigned_stores', true);
        if (!is_array($assigned_stores)) {
            $assigned_stores = array();
        }

        ?>
        <h3><?php _e('Store Assignments', 'wc-multi-store'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="wc_assigned_stores"><?php _e('Assigned Stores', 'wc-multi-store'); ?></label></th>
                <td>
                    <select name="wc_assigned_stores[]" id="wc_assigned_stores" multiple="multiple" style="width: 50%;">
                        <?php foreach ($stores as $store) : ?>
                            <option value="<?php echo esc_attr($store->ID); ?>" <?php selected(in_array($store->ID, $assigned_stores)); ?>>
                                <?php echo esc_html($store->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Select stores this user can manage. Leave empty for all stores.', 'wc-multi-store'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_store_assignment($user_id) {
        if (!current_user_can('edit_user', $user_id) || !current_user_can('manage_woocommerce')) {
            return;
        }

        if (isset($_POST['wc_assigned_stores'])) {
            $stores = array_map('absint', $_POST['wc_assigned_stores']);
            update_user_meta($user_id, '_wc_assigned_stores', $stores);
        } else {
            delete_user_meta($user_id, '_wc_assigned_stores');
        }
    }

    public function set_order_store_meta($order) {
        $selected_store = isset($_COOKIE['wc_selected_store']) ? absint($_COOKIE['wc_selected_store']) : false;
        if ($selected_store) {
            $order->update_meta_data('_wc_order_store', $selected_store);
        }
    }

    public function handle_store_order_query($query, $query_vars) {
        if (!empty($query_vars['wc_order_store'])) {
            $query['meta_query'][] = array(
                'key' => '_wc_order_store',
                'value' => esc_attr($query_vars['wc_order_store']),
                'compare' => '='
            );
        }
        return $query;
    }

    public function filter_orders_by_store($query) {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'shop_order') {
            return;
        }

        // Check if current user is a shop manager
        if (current_user_can('administrator')) {
            return;
        }

        if (current_user_can('shop_manager')) {
            $user_id = get_current_user_id();
            $assigned_stores = get_user_meta($user_id, '_wc_assigned_stores', true);

            if (!empty($assigned_stores)) {
                $meta_query = $query->get('meta_query') ?: array();
                $meta_query[] = array(
                    'key' => '_wc_order_store',
                    'value' => $assigned_stores,
                    'compare' => 'IN'
                );
                $query->set('meta_query', $meta_query);
            } else {
                // If no stores assigned, show no orders
                $query->set('post__in', array(0));
            }
        }
    }

    public function add_store_order_views($views) {
        $stores = get_posts(array(
            'post_type' => 'wc_store',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        if (empty($stores)) {
            return $views;
        }

        foreach ($stores as $store) {
            $count = $this->get_orders_count_by_store($store->ID);
            if ($count > 0) {
                $class = isset($_GET['wc_order_store']) && $_GET['wc_order_store'] == $store->ID ? 'current' : '';
                $views['store_' . $store->ID] = sprintf(
                    '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                    add_query_arg('wc_order_store', $store->ID, admin_url('edit.php?post_type=shop_order')),
                    $class,
                    esc_html($store->post_title),
                    $count
                );
            }
        }

        return $views;
    }

    public function add_store_order_column($columns) {
        $new_columns = array();
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            if ($key === 'order_total') {
                $new_columns['wc_order_store'] = __('Store', 'wc-multi-store');
            }
        }
        return $new_columns;
    }

    public function show_store_order_column($column, $post_id) {
        if ($column === 'wc_order_store') {
            $store_id = get_post_meta($post_id, '_wc_order_store', true);
            if ($store_id) {
                $store = get_post($store_id);
                if ($store) {
                    echo esc_html($store->post_title);
                } else {
                    echo '<span class="na">' . __('N/A', 'wc-multi-store') . '</span>';
                }
            } else {
                echo '<span class="na">' . __('N/A', 'wc-multi-store') . '</span>';
            }
        }
    }

    public function add_store_filter_dropdown() {
        global $typenow;

        if ('shop_order' !== $typenow) {
            return;
        }

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

        $selected = isset($_GET['wc_order_store']) ? $_GET['wc_order_store'] : '';
        ?>
        <select name="wc_order_store" id="wc_order_store">
            <option value=""><?php _e('All stores', 'wc-multi-store'); ?></option>
            <?php foreach ($stores as $store) : ?>
                <option value="<?php echo esc_attr($store->ID); ?>" <?php selected($selected, $store->ID); ?>>
                    <?php echo esc_html($store->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    private function get_orders_count_by_store($store_id) {
        $orders = wc_get_orders(array(
            'limit' => -1,
            'return' => 'ids',
            'wc_order_store' => $store_id,
            'status' => array_keys(wc_get_order_statuses())
        ));

        return count($orders);
    }

    public function filter_order_actions_for_managers($actions, $order) {
        if (current_user_can('administrator')) {
            return $actions;
        }

        if (current_user_can('shop_manager')) {
            $user_id = get_current_user_id();
            $assigned_stores = get_user_meta($user_id, '_wc_assigned_stores', true);
            $order_store = $order->get_meta('_wc_order_store');

            if (!empty($assigned_stores) && in_array($order_store, $assigned_stores)) {
                // Only allow specific actions for store managers
                $allowed_actions = array(
                    'complete' => $actions['complete'],
                    'processing' => $actions['processing'],
                    'view' => $actions['view']
                );
                return $allowed_actions;
            }
            return array(); // No actions if order doesn't belong to manager's store
        }

        return $actions;
    }

    public function filter_bulk_actions_for_managers($actions) {
        if (current_user_can('administrator')) {
            return $actions;
        }

        if (current_user_can('shop_manager')) {
            // Only allow specific bulk actions for store managers
            $allowed_actions = array(
                'mark_processing' => $actions['mark_processing'],
                'mark_completed' => $actions['mark_completed']
            );
            return $allowed_actions;
        }

        return $actions;
    }
}