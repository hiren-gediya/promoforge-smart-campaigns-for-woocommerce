<?php
if (!defined('ABSPATH')) {
    exit;
}

// discount offer new price
add_filter('woocommerce_cart_item_price', 'flashoffers_cart_price_only', 10, 3);
function flashoffers_cart_price_only($price_html, $cart_item, $cart_item_key)
{
    return wp_kses_post(flashoffers_get_price_html($cart_item['data']));
}

