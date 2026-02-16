<?php
/*
Plugin Name: PromoForge Smart Campaigns for WooCommerce
Description: A powerful WooCommerce plugin to create **Flash Offers, Upcoming Offers, Special Offers, and BOGO Offers**
with full control over products, categories, discounts, and display logic.
Version: 1.0.0
Author: Hiren Gediya
Tested up to: 6.9
Text Domain: promoforge-smart-campaigns-for-woocommerce
Domain Path: /languages
Network: True
Requires Plugins: woocommerce
License: GPLv2 or later
*/

if (!defined('ABSPATH'))
    exit;

// Prevent activation if WooCommerce is not active
register_activation_hook(__FILE__, function () {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(esc_html__('PromoForge Smart Campaigns for WooCommerce requires WooCommerce to be installed and active.', 'promoforge-smart-campaigns-for-woocommerce'));
    }
});

define('PROMOFORGE_PATH', plugin_dir_path(__FILE__));

require_once PROMOFORGE_PATH . 'includes/promoforge-functions.php';
require_once PROMOFORGE_PATH . 'includes/promoforge-settings.php';
require_once PROMOFORGE_PATH . 'includes/promoforge-admin.php';
require_once PROMOFORGE_PATH . 'includes/promoforge-cart.php';
require_once PROMOFORGE_PATH . 'includes/promoforge-thank-you.php';
require_once PROMOFORGE_PATH . 'includes/promoforge-checkout.php';
require_once PROMOFORGE_PATH . 'includes/promoforge-shortcodes.php';
require_once PROMOFORGE_PATH . 'includes/promoforge-special-cart.php';
require_once PROMOFORGE_PATH . 'includes/promoforge-bogo-admin.php';
require_once PROMOFORGE_PATH . 'includes/promoforge-bogo-frontend.php';

// Initialize global variable for hiding sale badges (Deprecated: now using CSS classes)
// add_action('init', function () {
//     $GLOBALS['promoforge_hide_sale_badge_ids'] = [];
// });
// Run migration on plugin activation or when admin loads
add_action('admin_init', function () {
    if (current_user_can('activate_plugins')) {
        promoforge_migrate_categories_table();
        promoforge_migrate_use_offers_column();
    }
});

// Enqueue countdown script on single product pages
add_action('wp_enqueue_scripts', function () {
    // Enqueue on Product, Shop, Category, and Home pages
    if (is_product() || is_shop() || is_product_category() || is_front_page()) {
        wp_enqueue_script('promoforge-timer', plugin_dir_url(__FILE__) . 'assets/js/countdown.js', [], '1.1', true);
    }
    // Get countdown format from settings
    $options = get_option('promoforge_offers_options');
    $countdown_format = $options['countdown_format'] ?? 'format1';

    wp_localize_script('promoforge-timer', 'promoforge_vars', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('promoforge_nonce'),
        'countdown_format' => $countdown_format
    ]);
    wp_enqueue_style('promoforge-style', plugin_dir_url(__FILE__) . 'assets/css/style.css', [], '1.1');
    wp_enqueue_style('slick-css', plugin_dir_url(__FILE__) . 'assets/css/slick.css', [], '1.8.1');
    wp_enqueue_script(
        'slick-js',
        plugin_dir_url(__FILE__) . 'assets/js/slick.min.js',
        ['jquery'],
        '1.8.1',
        true
    );
    wp_enqueue_script(
        'promoforge-frontend',
        plugin_dir_url(__FILE__) . 'assets/js/promoforge-frontend.js',
        ['jquery', 'slick-js'],
        '1.1',
        true
    );
});


// Add admin scripts
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook === 'post-new.php' || $hook === 'post.php') {
        global $post;
        if ($post && ($post->post_type === 'promoforge_flash' || $post->post_type === 'promoforge_bogo')) {
            wp_enqueue_script('promoforge-admin', plugin_dir_url(__FILE__) . 'assets/js/admin.js', ['jquery'], '1.1', true);
        }
    }

    wp_enqueue_style('promoforge-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin.css', [], '1.1');
    wp_enqueue_script('jquery');
    wp_localize_script('promoforge-offer-product-select', 'PromoforgeOfferAjax', [
        'ajax_url' => admin_url('admin-ajax.php'),
    ]);

    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');
    wp_add_inline_script('wp-color-picker', '
jQuery(document).ready(function($) {
$(".promoforge-offer-color-field").wpColorPicker();
});
');
});


// Replace the current uninstall hook with:
register_uninstall_hook(__FILE__, 'promoforge_handle_uninstall');

// Add this function:
function promoforge_handle_uninstall()
{
    if (!defined('PROMOFORGE_WP_UNINSTALL_PLUGIN')) {
        define('PROMOFORGE_WP_UNINSTALL_PLUGIN', true);
    }

    require_once plugin_dir_path(__FILE__) . 'includes/promoforge-functions.php';
    promoforge_uninstall();
}
