<?php
/*
Plugin Name: Advanced Offers for WooCommerce
Description: A powerful WooCommerce plugin to create **Flash Offers, Upcoming Offers, Special Offers, and BOGO Offers**
with full control over products, categories, discounts, and display logic.
Version: 1.1
Author: Hiren Gediya
Tested up to: 6.9
Text Domain: advanced-offers-for-woocommerce
Domain Path: /languages
Network: True
License: GPLv2 or later
*/

if (!defined('ABSPATH'))
    exit;

// Prevent activation if WooCommerce is not active
register_activation_hook(__FILE__, function () {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(esc_html__('Advanced Offers for WooCommerce requires WooCommerce to be installed and active.', 'advanced-offers-for-woocommerce'));
    }
});

// Check if WooCommerce is active
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function () {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('Advanced Offers for WooCommerce requires WooCommerce to be installed and active.', 'advanced-offers-for-woocommerce'); ?>
            </p>
        </div>
        <?php
    });
    return;
}

define('FLASHOFFERS_PATH', plugin_dir_path(__FILE__));

require_once FLASHOFFERS_PATH . 'includes/wao-functions.php';
require_once FLASHOFFERS_PATH . 'includes/wao-settings.php';
require_once FLASHOFFERS_PATH . 'includes/wao-admin.php';
require_once FLASHOFFERS_PATH . 'includes/wao-cart.php';
require_once FLASHOFFERS_PATH . 'includes/wao-thank-you.php';
require_once FLASHOFFERS_PATH . 'includes/wao-checkout.php';
require_once FLASHOFFERS_PATH . 'includes/wao-shortcodes.php';
require_once FLASHOFFERS_PATH . 'includes/wao-special-cart.php';
require_once FLASHOFFERS_PATH . 'includes/bogo-admin.php';
require_once FLASHOFFERS_PATH . 'includes/bogo-frontend.php';
// Run migration on plugin activation or when admin loads
add_action('admin_init', function () {
    if (current_user_can('activate_plugins')) {
        flashoffers_migrate_categories_table();
        flashoffers_migrate_use_offers_column();
    }
});

// Enqueue countdown script on single product pages
add_action('wp_enqueue_scripts', function () {
    // Enqueue on Product, Shop, Category, and Home pages
    if (is_product() || is_shop() || is_product_category() || is_front_page()) {
        wp_enqueue_script('flashoffers-timer', plugin_dir_url(__FILE__) . 'assets/js/countdown.js', [], '1.1', true);
    }
    // Get countdown format from settings
    $options = get_option('flash_offers_options');
    $countdown_format = $options['countdown_format'] ?? 'format1';

    wp_localize_script('flashoffers-timer', 'flashoffers_vars', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('flashoffers_nonce'),
        'countdown_format' => $countdown_format
    ]);
    wp_enqueue_style('flashoffers-style', plugin_dir_url(__FILE__) . 'assets/css/style.css', [], '1.1');
    wp_enqueue_style('slick-css', plugin_dir_url(__FILE__) . 'assets/css/slick.css', [], '1.8.1');
    wp_enqueue_script(
        'slick-js',
        plugin_dir_url(__FILE__) . 'assets/js/slick.min.js',
        ['jquery'],
        '1.8.1',
        true
    );
    wp_enqueue_script(
        'flashoffers-frontend',
        plugin_dir_url(__FILE__) . 'assets/js/wao-frontend.js',
        ['jquery', 'slick-js'],
        '1.1',
        true
    );
});


// Add admin scripts
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook === 'post-new.php' || $hook === 'post.php') {
        global $post;
        if ($post && ($post->post_type === 'flash_offer' || $post->post_type === 'bogo_offer')) {
            wp_enqueue_script('flashoffers-admin', plugin_dir_url(__FILE__) . 'assets/js/admin.js', ['jquery'], '1.1', true);
        }
    }

    wp_enqueue_style('flash-offer-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin.css', [], '1.1');
    wp_enqueue_script('jquery');
    wp_localize_script('flash-offer-product-select', 'FlashOfferAjax', [
        'ajax_url' => admin_url('admin-ajax.php'),
    ]);

    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');
    wp_add_inline_script('wp-color-picker', '
jQuery(document).ready(function($) {
$(".flash-offer-color-field").wpColorPicker();
});
');
});


// Replace the current uninstall hook with:
register_uninstall_hook(__FILE__, 'flashoffers_handle_uninstall');

// Add this function:
function flashoffers_handle_uninstall()
{
    if (!defined('FLASHOFFERS_WP_UNINSTALL_PLUGIN')) {
        define('FLASHOFFERS_WP_UNINSTALL_PLUGIN', true);
    }

    require_once plugin_dir_path(__FILE__) . 'includes/wao-functions.php';
    flashoffers_uninstall();
}