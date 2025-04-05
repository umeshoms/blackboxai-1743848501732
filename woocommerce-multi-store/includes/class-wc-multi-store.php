<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Multi_Store_Init {
    public function __construct() {
        error_log('WooCommerce Multi-Store: Plugin initialization started');
        
        // Verify WooCommerce is loaded
        if (!class_exists('WooCommerce')) {
            error_log('WooCommerce Multi-Store: ERROR - WooCommerce not loaded');
            return;
        }
        error_log('WooCommerce Multi-Store: WooCommerce version ' . WC()->version);
        
        // Load dependencies
        error_log('WooCommerce Multi-Store: Loading dependencies');
        $this->load_dependencies();
        error_log('WooCommerce Multi-Store: Dependencies loaded');

        // Initialize components
        $this->init_components();

        // Register activation/deactivation hooks
        register_activation_hook(WC_MULTI_STORE_FILE, array($this, 'activate'));
        register_deactivation_hook(WC_MULTI_STORE_FILE, array($this, 'deactivate'));
    }

    private function load_dependencies() {
        require_once WC_MULTI_STORE_PATH . 'includes/class-wc-store-post-type.php';
        require_once WC_MULTI_STORE_PATH . 'includes/class-wc-store-settings.php';
        require_once WC_MULTI_STORE_PATH . 'includes/class-wc-product-store.php';
        require_once WC_MULTI_STORE_PATH . 'includes/class-wc-store-selector.php';
        require_once WC_MULTI_STORE_PATH . 'includes/class-wc-store-shipping.php';
        require_once WC_MULTI_STORE_PATH . 'includes/class-wc-store-orders.php';
    }

    private function init_components() {
        // Core components
        new WC_Store_Post_Type();
        new WC_Store_Settings();
        
        // Frontend components
        if (!is_admin() || defined('DOING_AJAX')) {
            new WC_Product_Store();
            new WC_Store_Selector();
            new WC_Store_Shortcode();
        }

        // Shipping
        new WC_Store_Shipping();

        // Admin components
        if (is_admin()) {
            new WC_Store_Orders();
        }
    }

    public function activate() {
        // Flush rewrite rules for custom post types
        flush_rewrite_rules();

        // Create default roles and capabilities
        $this->create_roles();

        // Set default options
        if (!get_option('wc_multi_store_version')) {
            update_option('wc_multi_store_version', WC_MULTI_STORE_VERSION);
            update_option('wc_multi_store_distance_unit', 'km');
            update_option('wc_multi_store_selector_position', 'header');
        }
    }

    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();

        // Remove scheduled events
        $this->clear_scheduled_events();
    }

    private function create_roles() {
        $roles = new WC_Multi_Store_Roles();
        $roles->add_roles();
        $roles->add_capabilities();
    }

    private function clear_scheduled_events() {
        // Remove any scheduled events if needed
    }
}

class WC_Multi_Store_Roles {
    public function add_roles() {
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

    public function add_capabilities() {
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('manage_woocommerce_stores');
            $admin_role->add_cap('assign_shop_managers');
        }

        $shop_manager_role = get_role('shop_manager');
        if ($shop_manager_role) {
            $shop_manager_role->add_cap('manage_assigned_stores');
        }
    }
}