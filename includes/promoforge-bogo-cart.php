<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validate BOGO offers on Cart and Checkout
 */
add_action('woocommerce_check_cart_items', 'promoforge_validate_cart_bogo');

function promoforge_validate_cart_bogo()
{
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    global $wpdb;

    if (!WC()->cart || WC()->cart->is_empty()) {
        return;
    }

    $current_date = current_time('mysql');
    $cart_items = WC()->cart->get_cart();

    // Collect all IDs in cart
    $cart_ids = [];
    $item_qtys = []; // Track quantities per product/variation ID
    foreach ($cart_items as $item) {
        $p_id = (int) $item['product_id'];
        $v_id = (int) ($item['variation_id'] ?? 0);
        $cart_ids[] = $p_id;
        $item_qtys[$p_id] = ($item_qtys[$p_id] ?? 0) + $item['quantity'];
        if ($v_id > 0) {
            $cart_ids[] = $v_id;
            $item_qtys[$v_id] = ($item_qtys[$v_id] ?? 0) + $item['quantity'];
        }
    }
    $cart_ids = array_unique($cart_ids);
    if (empty($cart_ids))
        return;

    $placeholders = implode(',', array_fill(0, count($cart_ids), '%d'));
    // Query offers where either Buy OR Get product is in cart
    $query = "SELECT * FROM {$wpdb->prefix}promoforge_bogo_offers 
              WHERE (buy_product_id IN ($placeholders) OR get_product_id IN ($placeholders)) 
              AND start_date <= %s AND end_date >= %s";
    $params = array_merge($cart_ids, $cart_ids, [$current_date, $current_date]);

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
    $bogo_offers = $wpdb->get_results($wpdb->prepare($query, $params));

    if (empty($bogo_offers))
        return;

    static $promoforge_bogo_notices_shown = [];

    foreach ($bogo_offers as $offer) {
        if (in_array($offer->id, $promoforge_bogo_notices_shown)) {
            continue;
        }

        $buy_id = (int) $offer->buy_product_id;
        $get_id = (int) $offer->get_product_id;
        $buy_req = (int) $offer->buy_quantity;
        $get_req = (int) $offer->get_quantity;

        $has_buy_qty = $item_qtys[$buy_id] ?? 0;
        $has_get_qty = $item_qtys[$get_id] ?? 0;

        // CASE 1: Same Product (Buy 1 Get 1 or Buy X Get X)
        if ($buy_id === $get_id) {
            $total_required = $buy_req + $get_req;
            // If offer type is buy_one_get_one, usually buy 1 get 1 = 2 total
            if ($offer->offer_type === 'buy_one_get_one') {
                $total_required = 2;
            }

            // Find the maximum quantity of any single variation matching this offer
            $max_single_qty = 0;
            $has_any = false;
            foreach ($cart_items as $item) {
                $pid = (int) $item['product_id'];
                $vid = (int) ($item['variation_id'] ?? 0);
                if ($pid === $buy_id || ($vid > 0 && $vid === $buy_id)) {
                    $has_any = true;
                    if ($item['quantity'] > $max_single_qty) {
                        $max_single_qty = $item['quantity'];
                    }
                }
            }

            if ($has_any && $max_single_qty < $total_required) {
                wc_add_notice(sprintf(
                    /* translators: 1: required quantity, 2: product title */
                    esc_html__('To apply this BOGO offer, please add %1$d quantity of "%2$s" to your cart.', 'promoforge-smart-campaigns-for-woocommerce'),
                    $total_required,
                    get_the_title($buy_id)
                ), 'error');
                $promoforge_bogo_notices_shown[] = $offer->id;
            }
        }
        // CASE 2: Different Products (Buy X Get Y)
        else {
            // Retrieve totals for buy and get products
            $has_buy_qty = $item_qtys[$buy_id] ?? 0;
            $has_get_qty = $item_qtys[$get_id] ?? 0;

            // Scenario A: User has the Buy product, but not enough/any Get product
            if ($has_buy_qty >= $buy_req && $has_get_qty < $get_req) {
                wc_add_notice(sprintf(
                    /* translators: 1: required quantity, 2: product title */
                    esc_html__('To apply this BOGO offer, Please add %1$d quantity of "%2$s" to your cart.', 'promoforge-smart-campaigns-for-woocommerce'),
                    $get_req,
                    get_the_title($get_id)
                ), 'error');
                $promoforge_bogo_notices_shown[] = $offer->id;
            }
            // Scenario B: User has the Get product, but not enough/any Buy product
            elseif ($has_get_qty >= 1 && $has_buy_qty < $buy_req) {
                wc_add_notice(sprintf(
                    /* translators: 1: discounted product title, 2: required quantity, 3: buy product title */
                    esc_html__('To receive "%1$s" at a discounted price, please add %2$d quantity of "%3$s" to your cart.', 'promoforge-smart-campaigns-for-woocommerce'),
                    get_the_title($get_id),
                    $buy_req,
                    get_the_title($buy_id)
                ), 'error');
                $promoforge_bogo_notices_shown[] = $offer->id;
            }
        }
    }
}


// ✅ Apply BOGO discounts to cart
add_action('woocommerce_before_calculate_totals', 'promoforge_apply_bogo_discount', 20);

function promoforge_apply_bogo_discount($cart)
{
    if (is_admin() && !defined('DOING_AJAX'))
        return;
    if (did_action('woocommerce_before_calculate_totals') >= 2)
        return;

    global $wpdb;
    $current_date = current_time('mysql');

    // Get all product and variation IDs in cart
    $cart_ids = array();
    foreach ($cart->get_cart() as $cart_item) {
        $cart_ids[] = (int) $cart_item['product_id'];
        if (!empty($cart_item['variation_id'])) {
            $cart_ids[] = (int) $cart_item['variation_id'];
        }
    }
    $cart_ids = array_unique($cart_ids);
    if (empty($cart_ids))
        return;

    // Use placeholder format for IN clause
    $placeholders = implode(',', array_fill(0, count($cart_ids), '%d'));

    // Construct the query
    $query = "SELECT * FROM {$wpdb->prefix}promoforge_bogo_offers
        WHERE (buy_product_id IN ($placeholders) OR get_product_id IN ($placeholders))
        AND start_date <= %s
        AND end_date >= %s";

    // Flatten parameters for prepare()
    $params = array_merge($cart_ids, $cart_ids, [$current_date, $current_date]);

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
    $bogo_offers = $wpdb->get_results($wpdb->prepare($query, $params));

    if (empty($bogo_offers))
        return;

    // Get global BOGO override type
    $options = get_option('promoforge_offers_options');
    $bogo_override_type = $options['bogo_override_type'] ?? 'sale';

    // Reset all prices before applying discounts
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $product_id = (int) $cart_item['product_id'];
        $variation_id = (int) ($cart_item['variation_id'] ?? 0);
        $target_id = ($variation_id > 0) ? $variation_id : $product_id;

        // Find matching offer
        $offer = null;
        foreach ($bogo_offers as $o) {
            if (
                $o->buy_product_id == $product_id || $o->get_product_id == $product_id ||
                ($variation_id > 0 && ($o->buy_product_id == $variation_id || $o->get_product_id == $variation_id))
            ) {
                $offer = $o;
                break;
            }
        }
        if (!$offer)
            continue;

        // Fetch fresh product object for clean prices
        $fresh_product = wc_get_product($target_id);
        if (!$fresh_product)
            continue;

        // Use override type from offer if available, else global
        $override_type = $offer->override_type ?? $bogo_override_type;
        $regular_price = (float) $fresh_product->get_regular_price();
        $sale_price = (float) $fresh_product->get_sale_price();

        if ($override_type == 'regular') {
            $base_price = $regular_price;
        } else {
            $base_price = ($sale_price > 0 && $sale_price < $regular_price) ? $sale_price : $regular_price;
        }

        if ($base_price <= 0) {
            $base_price = (float) $fresh_product->get_price();
        }

        // Apply reset price and save it
        $cart->cart_contents[$cart_item_key]['data']->set_price($base_price);
        $cart->cart_contents[$cart_item_key]['promoforge_bogo_original_price'] = $base_price;
    }

    // Apply discounts
    foreach ($bogo_offers as $offer) {
        if ($offer->buy_product_id == $offer->get_product_id) {
            promoforge_apply_bogo_same_product_discount($cart, $offer);
        } else {
            promoforge_apply_bogo_different_products_discount($cart, $offer);
        }
    }
}

function promoforge_apply_bogo_same_product_discount($cart, $offer)
{
    $offer_product_id = (int) $offer->buy_product_id;
    $buy_quantity = $offer->buy_quantity;
    $get_quantity = $offer->get_quantity;
    $discount = $offer->discount > 0 ? $offer->discount : 100;

    // Group items by their specific variation to ensure we don't mix different pack sizes in one BOGO rule
    $cart_groups = [];

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $product_id = (int) $cart_item['product_id'];
        $variation_id = (int) ($cart_item['variation_id'] ?? 0);

        // Match if the cart item belongs to the offer's targeted product or variation
        if ($product_id === $offer_product_id || ($variation_id > 0 && $variation_id === $offer_product_id)) {
            $group_key = $product_id . '_' . $variation_id;
            if (!isset($cart_groups[$group_key])) {
                $cart_groups[$group_key] = [
                    'count' => 0,
                    'keys' => []
                ];
            }
            $cart_groups[$group_key]['count'] += $cart_item['quantity'];
            $cart_groups[$group_key]['keys'][] = $cart_item_key;
        }
    }

    foreach ($cart_groups as $group) {
        // Apply offer to this specific group/variation only if it has enough quantity
        if ($group['count'] >= ($buy_quantity + $get_quantity)) {
            $discount_items = $get_quantity; // Only once as per original single-application preference

            foreach ($group['keys'] as $cart_key) {
                $qty = $cart->cart_contents[$cart_key]['quantity'];
                $original_price = $cart->cart_contents[$cart_key]['promoforge_bogo_original_price'];

                // Split: free only applies up to the remaining items in the offer set
                $line_free = min($discount_items, $qty);
                $line_paid = $qty - $line_free;
                $discount_items -= $line_free;

                // Weighted average for this specific cart item
                $line_total = ($line_paid * $original_price) + ($line_free * $original_price * (1 - $discount / 100));
                $line_price = $line_total / $qty;

                $cart->cart_contents[$cart_key]['data']->set_price($line_price);
            }
        }
    }
}

function promoforge_apply_bogo_different_products_discount($cart, $offer)
{
    $buy_id = $offer->buy_product_id;
    $get_id = $offer->get_product_id;
    $buy_qty = $offer->buy_quantity;
    $get_qty = $offer->get_quantity;
    $discount = $offer->discount > 0 ? $offer->discount : 100;

    $total_buy_qty = 0;
    $get_key = null;
    $get_quantity_in_cart = 0;

    foreach ($cart->get_cart() as $cart_key => $cart_item) {
        if ($cart_item['product_id'] == $buy_id) {
            $total_buy_qty += $cart_item['quantity'];
        }
        if ($cart_item['product_id'] == $get_id) {
            $get_key = $cart_key;
            $get_quantity_in_cart = $cart_item['quantity'];
        }
    }

    if ($total_buy_qty >= $buy_qty && $get_key !== null) {
        $product = $cart->cart_contents[$get_key]['data'];
        $offer_data = promoforge_get_bogo_offer_data($product);
        $override_type = isset($offer_data['bogo_override_type']) ? $offer_data['bogo_override_type'] : '';

        $regular_price = (float) $product->get_regular_price();
        $sale_price = (float) $product->get_sale_price();

        // ✅ Apply override
        if ($override_type === 'regular') {
            $original_price = $regular_price;
        } else {
            $original_price = ($sale_price > 0 && $sale_price < $regular_price) ? $sale_price : $regular_price;
        }

        if ($original_price <= 0) {
            $original_price = (float) ($cart->cart_contents[$get_key]['promoforge_bogo_original_price'] ?? $product->get_price());
        }

        if ($original_price <= 0)
            return; // Still 0, avoid division errors

        if ($get_quantity_in_cart == $get_qty) {
            $new_price = $original_price * (1 - $discount / 100);
            $product->set_price($new_price);
        } elseif ($get_quantity_in_cart > $get_qty) {
            $discounted_items = $get_qty;
            $extra_items = $get_quantity_in_cart - $get_qty;

            $total_price = ($discounted_items * $original_price * (1 - $discount / 100))
                + ($extra_items * $original_price);

            $average_price = $total_price / $get_quantity_in_cart;
            $product->set_price($average_price);
        }
    }
}

// ✅ Display BOGO price in cart
add_filter('woocommerce_cart_item_price', 'promoforge_display_bogo_discount_in_cart', 20, 3);
function promoforge_display_bogo_discount_in_cart($price_html, $cart_item, $cart_item_key)
{
    $product = $cart_item['data'];
    $product_id = $cart_item['product_id'];
    $offer = promoforge_get_offer_for_product($product_id);

    // ✅ If not part of any BOGO offer → use default Woo price display
    if (!$offer) {
        $regular_price = (float) $product->get_regular_price();
        $sale_price = (float) $product->get_sale_price();
        $current_price = (float) $product->get_price();

        if ($sale_price && $sale_price < $regular_price) {
            return '<del>' . wp_kses_post(wc_price($regular_price)) . '</del> <ins>' . wp_kses_post(wc_price($sale_price)) . '</ins>';
        }
        return wp_kses_post(wc_price($current_price));
    }

    // ✅ Get offer data
    $offer_data = promoforge_get_bogo_offer_data($product);
    $override_type = isset($offer_data['bogo_override_type']) ? $offer_data['bogo_override_type'] : '';

    $regular_price = (float) $product->get_regular_price();
    $sale_price = (float) $product->get_sale_price();
    $current_price = (float) $product->get_price();
    // 🟢 CASE 1: Override = regular → always show regular price without strike-through
    if ($override_type === 'regular') {
        if ($regular_price && $current_price < $regular_price) {
            return '<del>' . wp_kses_post(wc_price($regular_price)) . '</del> <ins>' . wp_kses_post(wc_price($current_price)) . '</ins>';
        } else {
            return wp_kses_post(wc_price($regular_price));
        }
    }

    // 🟡 CASE 2: Override = sale → show sale price (if available)
    if ($override_type === 'sale') {
        if ($sale_price && $current_price < $sale_price) {
            return '<del>' . wp_kses_post(wc_price($sale_price)) . '</del> <ins>' . wp_kses_post(wc_price($current_price)) . '</ins>';
        } else {
            return wp_kses_post(wc_price($sale_price));
        }
    }
}


function promoforge_get_offer_for_product($product_id)
{
    global $wpdb;
    $current_date = current_time('mysql');

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}promoforge_bogo_offers
        WHERE (buy_product_id = %d OR get_product_id = %d)
        AND start_date <= %s AND end_date >= %s LIMIT 1",
        $product_id,
        $product_id,
        $current_date,
        $current_date
    ));
}
