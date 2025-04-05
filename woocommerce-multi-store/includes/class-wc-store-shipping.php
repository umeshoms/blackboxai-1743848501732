<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Store_Shipping {
    public function __construct() {
        add_action('woocommerce_shipping_init', array($this, 'init_shipping_method'));
        add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));
    }

    public function init_shipping_method() {
        require_once plugin_dir_path(__FILE__) . 'class-wc-store-distance-shipping.php';
    }

    public function add_shipping_method($methods) {
        $methods['wc_store_distance_shipping'] = 'WC_Store_Distance_Shipping';
        return $methods;
    }
}

class WC_Store_Distance_Shipping extends WC_Shipping_Method {
    public function __construct($instance_id = 0) {
        $this->id = 'wc_store_distance_shipping';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Store Distance Shipping', 'wc-multi-store');
        $this->method_description = __('Calculate shipping based on distance from store to delivery address.', 'wc-multi-store');
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal'
        );

        $this->init();
    }

    public function init() {
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title', __('Store Distance Shipping', 'wc-multi-store'));
        $this->tax_status = $this->get_option('tax_status', 'taxable');
        $this->base_cost = $this->get_option('base_cost', 0);
        $this->cost_per_km = $this->get_option('cost_per_km', 0);
        $this->free_shipping_threshold = $this->get_option('free_shipping_threshold', 0);
        $this->max_distance = $this->get_option('max_distance', 0);
        $this->distance_unit = get_option('wc_multi_store_distance_unit', 'km');

        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields() {
        $this->instance_form_fields = array(
            'title' => array(
                'title' => __('Method Title', 'wc-multi-store'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'wc-multi-store'),
                'default' => __('Store Distance Shipping', 'wc-multi-store'),
                'desc_tip' => true
            ),
            'tax_status' => array(
                'title' => __('Tax Status', 'wc-multi-store'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'default' => 'taxable',
                'options' => array(
                    'taxable' => __('Taxable', 'wc-multi-store'),
                    'none' => __('None', 'wc-multi-store')
                )
            ),
            'base_cost' => array(
                'title' => __('Base Cost', 'wc-multi-store'),
                'type' => 'number',
                'description' => __('Fixed cost added to all shipments.', 'wc-multi-store'),
                'default' => 0,
                'desc_tip' => true,
                'custom_attributes' => array(
                    'step' => '0.01',
                    'min' => '0'
                )
            ),
            'cost_per_km' => array(
                'title' => sprintf(__('Cost per %s', 'wc-multi-store'), $this->distance_unit),
                'type' => 'number',
                'description' => __('Cost per distance unit from store to delivery address.', 'wc-multi-store'),
                'default' => 0,
                'desc_tip' => true,
                'custom_attributes' => array(
                    'step' => '0.01',
                    'min' => '0'
                )
            ),
            'free_shipping_threshold' => array(
                'title' => __('Free Shipping Threshold', 'wc-multi-store'),
                'type' => 'number',
                'description' => __('Minimum order amount for free shipping (0 to disable).', 'wc-multi-store'),
                'default' => 0,
                'desc_tip' => true,
                'custom_attributes' => array(
                    'step' => '0.01',
                    'min' => '0'
                )
            ),
            'max_distance' => array(
                'title' => sprintf(__('Maximum Delivery Distance (%s)', 'wc-multi-store'), $this->distance_unit),
                'type' => 'number',
                'description' => __('Maximum distance for delivery (0 for unlimited).', 'wc-multi-store'),
                'default' => 0,
                'desc_tip' => true,
                'custom_attributes' => array(
                    'step' => '1',
                    'min' => '0'
                )
            )
        );
    }

    public function calculate_shipping($package = array()) {
        // Check if local pickup is selected
        $local_pickup = false;
        foreach ($package['rates'] as $rate) {
            if ($rate->method_id === 'local_pickup') {
                $local_pickup = true;
                break;
            }
        }

        // If local pickup is selected, update the method
        if ($local_pickup) {
            $selected_store = isset($_COOKIE['wc_selected_store']) ? absint($_COOKIE['wc_selected_store']) : false;
            if ($selected_store) {
                $store_address = get_post_meta($selected_store, '_store_address', true);
                $this->title .= ' (' . esc_html($store_address) . ')';
            }
        }
        $selected_store = isset($_COOKIE['wc_selected_store']) ? absint($_COOKIE['wc_selected_store']) : false;
        if (!$selected_store) {
            return;
        }

        $store = get_post($selected_store);
        if (!$store || $store->post_type !== 'wc_store') {
            return;
        }

        $store_lat = get_post_meta($selected_store, '_store_latitude', true);
        $store_lng = get_post_meta($selected_store, '_store_longitude', true);
        if (!$store_lat || !$store_lng) {
            return;
        }

        $destination = $package['destination'];
        if (empty($destination['postcode']) || empty($destination['country'])) {
            return;
        }

        $distance = $this->calculate_distance(
            $store_lat,
            $store_lng,
            $destination['latitude'] ?? 0,
            $destination['longitude'] ?? 0
        );

        if ($this->max_distance > 0 && $distance > $this->max_distance) {
            return;
        }

        // Check for free shipping threshold
        $cart_total = WC()->cart->get_cart_contents_total();
        if ($this->free_shipping_threshold > 0 && $cart_total >= $this->free_shipping_threshold) {
            $rate = array(
                'id' => $this->get_rate_id(),
                'label' => __('Free Shipping', 'wc-multi-store'),
                'cost' => 0,
                'package' => $package,
            );
            $this->add_rate($rate);
            return;
        }

        // Calculate shipping cost
        $cost = $this->base_cost + ($distance * $this->cost_per_km);
        $cost = max($cost, 0);

        $rate = array(
            'id' => $this->get_rate_id(),
            'label' => $this->title,
            'cost' => $cost,
            'package' => $package,
            'meta_data' => array(
                'distance' => round($distance, 2),
                'distance_unit' => $this->distance_unit
            )
        );

        $this->add_rate($rate);
    }

    private function calculate_distance($lat1, $lng1, $lat2, $lng2) {
        if (!$lat2 || !$lng2) {
            return 0;
        }

        $theta = $lng1 - $lng2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + 
                cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;

        if ($this->distance_unit === 'km') {
            $distance = $miles * 1.609344;
        } else {
            $distance = $miles;
        }

        return round($distance, 2);
    }
}