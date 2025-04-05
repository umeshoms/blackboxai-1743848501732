<?php
/**
 * Plugin Name: WooCommerce Multi-Store
 * Description: Manage multiple stores with location-based product availability and shipping.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: wc-multi-store
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 7.0
 */

defined('ABSPATH') || exit;

// Define plugin constants
define('WC_MULTI_STORE_VERSION', '1.0.0');
define('WC_MULTI_STORE_FILE', __FILE__);
define('WC_MULTI_STORE_PATH', plugin_dir_path(__FILE__));
define('WC_MULTI_STORE_URL', plugin_dir_url(__FILE__));

// Check if WooCommerce is active
if (!class_exists('WooCommerce')) {
    error_log('WooCommerce Multi-Store: WooCommerce class not found');
    add_action('admin_notices', function() {
        echo '<div class="error"><p>';
        _e('WooCommerce Multi-Store requires WooCommerce to be installed and active!', 'wc-multi-store');
        echo '</p></div>';
    });
    return;
} else {
    error_log('WooCommerce Multi-Store: WooCommerce detected, version: ' . (function_exists('WC') ? WC()->version : 'WC() not available'));
}

// Load the main plugin class
require_once WC_MULTI_STORE_PATH . 'includes/class-wc-multi-store.php';

// Initialize the plugin
new WC_Multi_Store_Init();
