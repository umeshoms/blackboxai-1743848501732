<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Store_Shortcode {
    public function __construct() {
        add_shortcode('store_change_button', array($this, 'render_store_change_button'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_wc_change_store', array($this, 'change_store'));
        add_action('wp_ajax_nopriv_wc_change_store', array($this, 'change_store'));
    }

    public function render_store_change_button($atts) {
        $atts = shortcode_atts(array(
            'style' => 'button',
            'text' => __('Change Store', 'wc-multi-store'),
            'icon' => 'yes'
        ), $atts);

        ob_start();
        ?>
        <div class="wc-store-change-button-container">
            <button type="button" class="wc-store-change-button <?php echo esc_attr($atts['style']); ?>"
                    data-nonce="<?php echo wp_create_nonce('wc_change_store_nonce'); ?>">
                <?php if ($atts['icon'] === 'yes') : ?>
                    <span class="dashicons dashicons-store"></span>
                <?php endif; ?>
                <?php echo esc_html($atts['text']); ?>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            'wc-store-shortcode',
            plugin_dir_url(__FILE__) . '../assets/js/store-shortcode.js',
            array('jquery'),
            WC_MULTI_STORE_VERSION,
            true
        );

        wp_localize_script('wc-store-shortcode', 'wc_store_shortcode_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'i18n' => array(
                'select_store' => __('Select a Store', 'wc-multi-store')
            )
        ));
    }

    public function change_store() {
        check_ajax_referer('wc_change_store_nonce', 'nonce');

        if (!empty($_POST['store_id'])) {
            $store_id = absint($_POST['store_id']);
            setcookie('wc_selected_store', $store_id, time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN);
            wp_send_json_success();
        }

        wp_send_json_error(__('Invalid store ID', 'wc-multi-store'));
    }
}