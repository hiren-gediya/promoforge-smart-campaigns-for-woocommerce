<?php
if (!defined('ABSPATH')) {
    exit;
}

// Include separated BOGO frontend files
require_once plugin_dir_path(__FILE__) . 'promoforge-bogo-display.php';
require_once plugin_dir_path(__FILE__) . 'promoforge-bogo-ajax.php';
require_once plugin_dir_path(__FILE__) . 'promoforge-bogo-cart.php';

add_action('wp_enqueue_scripts', 'promoforge_enqueue_bogo_frontend_assets');
function promoforge_enqueue_bogo_frontend_assets()
{
    // Enqueue CSS
    wp_enqueue_style('promoforge-bogo-css', plugin_dir_url(__FILE__) . '../assets/css/promoforge-bogo-frontend.css', array(), '1.0');

    // Enqueue JS
    wp_enqueue_script('promoforge-bogo-js', plugin_dir_url(__FILE__) . '../assets/js/promoforge-bogo-frontend.js', array('jquery'), '1.0', true);

    // Localize script for AJAX
    wp_localize_script('promoforge-bogo-js', 'promoforge_bogo_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('promoforge_bogo_nonce')
    ));

}
