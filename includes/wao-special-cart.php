<?php
// 1. Add offer data to cart items
if (!defined('ABSPATH'))
    exit;
add_filter('woocommerce_add_cart_item_data', function ($cart_item_data, $product_id, $variation_id) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_REQUEST['from_offer'])) {
        // Verify the offer is valid
        global $wpdb;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $offer_id = (int) $_REQUEST['from_offer'];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $is_valid = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}flash_offers o
             JOIN {$wpdb->prefix}flash_offer_products op ON o.id = op.offer_id
             WHERE o.post_id = %d 
             AND o.offer_type = 'special'
             AND op.product_id = %d
             AND o.end_date > %s",
            $offer_id,
            $product_id,
            current_time('mysql')
        ));

        if ($is_valid) {
            $cart_item_data['from_offer'] = $offer_id;
            $cart_item_data['special_offer_source'] = true;
            $cart_item_data['special_offer_original_price'] = wc_get_product($variation_id ? $variation_id : $product_id)->get_regular_price();
        }
    }
    return $cart_item_data;
}, 10, 3);

// 2. Apply special offer pricing in cart
add_action('woocommerce_before_calculate_totals', function ($cart) {
    if (is_admin() && !defined('DOING_AJAX'))
        return;

    // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if (isset($_POST['remove_special_offer'])) {
        // Safe numeric cast + default 0
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $remove_offer_id = 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (isset($_POST['remove_offer_id'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $remove_offer_id = (int) $_POST['remove_offer_id'];
        }

        $cart_contents = WC()->cart->get_cart();
        foreach ($cart_contents as $cart_item_key => $cart_item) {
            // Only process items with special offer source
            if (empty($cart_item['special_offer_source']))
                continue;

            if (isset($cart_item['from_offer']) && $cart_item['from_offer'] == $remove_offer_id) {
                WC()->cart->remove_cart_item($cart_item_key);
                $notice_key = 'flash_offer_removed_' . $remove_offer_id;
                if (!wc_has_notice($notice_key)) {
                    wc_add_notice(__('Special offer removed from your cart.', 'advanced-offers-for-woocommerce'), 'notice', array('key' => $notice_key));
                }
            }
        }
    }

    if (did_action('woocommerce_before_calculate_totals') >= 2)
        return;

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        // Only process items with special offer source
        if (empty($cart_item['special_offer_source']))
            continue;

        $product = $cart_item['data'];
        $product_id = $cart_item['product_id'];
        $offer_id = $cart_item['from_offer'] ?? null;

        if (!$offer_id)
            continue;

        global $wpdb;

        // Verify the product is still in this special offer
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $is_valid = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}flash_offer_products op
             JOIN {$wpdb->prefix}flash_offers o ON op.offer_id = o.id
             WHERE o.post_id = %d 
             AND o.offer_type = 'special'
             AND op.product_id = %d
             AND o.end_date > %s",
            $offer_id,
            $product_id,
            current_time('mysql')
        ));

        if (!$is_valid) {
            // Remove the offer data if invalid
            unset(WC()->cart->cart_contents[$cart_item_key]['from_offer']);
            unset(WC()->cart->cart_contents[$cart_item_key]['special_offer_source']);
            continue;
        }

        // Get offer discount
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $offer = $wpdb->get_row($wpdb->prepare(
            "SELECT discount FROM {$wpdb->prefix}flash_offers 
             WHERE post_id = %d AND offer_type = 'special'",
            $offer_id
        ));

        if ($offer && $offer->discount > 0) {
            $original_price = $cart_item['special_offer_original_price'] ?? $product->get_regular_price();
            $discounted_price = $original_price - ($original_price * $offer->discount / 100);

            // Set the discounted price
            $product->set_price($discounted_price);
            $product->set_sale_price($discounted_price);

            // Store offer info
            $cart_item['data']->update_meta_data('_special_offer_applied', 'yes');
            $cart_item['data']->update_meta_data('_special_offer_discount', $offer->discount);
            $cart_item['data']->update_meta_data('_special_offer_id', $offer_id);
        }
    }
}, 20);


// 6. Preserve the from_offer parameter in redirects
add_filter('woocommerce_add_to_cart_redirect', function ($url) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_REQUEST['from_offer'])) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $url = add_query_arg('from_offer', (int) $_REQUEST['from_offer'], $url);
    }
    return $url;
});

// 7. Add JavaScript to handle single product page add-to-cart
// 7. Pass special offer data to frontend JS
add_action('wp_enqueue_scripts', function () {
    if (!is_product())
        return;
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (!isset($_GET['from_offer']))
        return;

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $offer_id = (int) $_GET['from_offer'];

    wp_localize_script('flashoffers-frontend', 'wao_special_offer_params', [
        'offer_id' => $offer_id,
        'cart_redirect_url' => add_query_arg('from_offer', $offer_id, wc_get_cart_url())
    ]);
});



