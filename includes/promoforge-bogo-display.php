<?php
if (!defined('ABSPATH')) {
    exit;
}

// Display BOGO offer on single product page
add_action('woocommerce_single_product_summary', 'promoforge_display_bogo_offer_on_product_page', 20);
function promoforge_display_bogo_offer_on_product_page()
{
    if (!is_single()) {
        return;
    }

    // Enqueue countdown script for BOGO offer countdown timer
    wp_enqueue_script('promoforge-timer');

    global $product, $wpdb;

    $product_id = $product->get_id();
    $current_date = current_time('mysql');

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $bogo_offers = $wpdb->get_results($wpdb->prepare(
        "SELECT o.* FROM {$wpdb->prefix}promoforge_bogo_offers o
         JOIN {$wpdb->posts} wp ON o.post_id = wp.ID
         WHERE (o.buy_product_id = %d OR o.get_product_id = %d)
         AND o.start_date <= %s
         AND o.end_date >= %s
         AND wp.post_status = 'publish'",
        $product_id,
        $product_id,
        $current_date,
        $current_date
    ));

    if (!empty($bogo_offers)) {
        foreach ($bogo_offers as $offer) {
            $buy_product = wc_get_product($offer->buy_product_id);
            $get_product = wc_get_product($offer->get_product_id);
            if (!$buy_product || !$get_product)
                continue;

            $buy_in_cart = false;
            $get_in_cart = false;

            if (WC()->cart) {
                foreach (WC()->cart->get_cart() as $cart_item) {
                    if (intval($cart_item['product_id']) === intval($offer->buy_product_id)) {
                        $buy_in_cart = true;
                    }
                    if (intval($cart_item['product_id']) === intval($offer->get_product_id)) {
                        $get_in_cart = true;
                    }
                }
            }

            if ($buy_in_cart && $get_in_cart) {
                continue;
            }
            do_action('promoforge_offers_before_bogo_offer_notice', $offer, $buy_product, $get_product);

            echo '<div class="bogo-offer-notice">';
            echo '<h3>' . esc_html__('Special BOGO Offer!', 'promoforge-smart-campaigns-for-woocommerce') . '</h3>';

            $offer_type = $offer->offer_type;
            $discount_text = $offer->discount > 0 ? $offer->discount . '%' : '100%';

            /* translators: 1: Buy quantity 2: Buy product name 3: Get quantity 4: Get product name 5: Discount percentage */
            $msg_fmt = esc_html__('Buy %1$d %2$s and Get %3$d %4$s with %5$s Discount!', 'promoforge-smart-campaigns-for-woocommerce');
            $message = sprintf(
                $msg_fmt,
                intval($offer->buy_quantity),
                esc_html($buy_product->get_name()),
                intval($offer->get_quantity),
                esc_html($get_product->get_name()),
                esc_html($discount_text)
            );
            echo '<p>' . wp_kses_post($message) . '</p>';

            do_action('promoforge_offers_before_bogo_offer_box', $offer, $buy_product, $get_product);

            echo '<button type="button" class="button alt bogo-popup-btn"
                    data-buy-product-id="' . esc_attr($offer->buy_product_id) . '"
                    data-buy-quantity="' . esc_attr($offer->buy_quantity) . '"
                    data-get-product-id="' . esc_attr($offer->get_product_id) . '"
                    data-get-quantity="' . esc_attr($offer->get_quantity) . '"
                    data-offer-type="' . esc_attr($offer->offer_type) . '"
                    data-discount="' . esc_attr($offer->discount) . '">' . esc_html__('Add BOGO Offer to Cart', 'promoforge-smart-campaigns-for-woocommerce') . '</button>';

            do_action('promoforge_offers_after_bogo_offer_box', $offer, $buy_product, $get_product);

            echo '</div>';

            do_action('promoforge_offers_after_bogo_offer_notice', $offer, $buy_product, $get_product);
        }
    }
}

// Custom display for BOGO products in table format
function promoforge_bogo_custom_display_variable_product($product_type = '')
{
    global $product, $promoforge_bogo_data;


    if (!isset($promoforge_bogo_data)) {
        $promoforge_bogo_data = array();
    }
    $bogo_get_attrs = '';
    if ($product_type === 'get' && isset($GLOBALS['bogo_offer_id']) && isset($GLOBALS['bogo_discount'])) {
        $bogo_get_attrs = ' data-is-bogo-get="1" data-bogo-offer-id="' . esc_attr($GLOBALS['bogo_offer_id']) . '" data-bogo-discount="' . esc_attr($GLOBALS['bogo_discount']) . '"';
    }

    $offer_data = promoforge_get_bogo_offer_data($product);

    if ($product->is_type('simple')) {
        $product_id = $product->get_ID();
        // $price = $product->get_sale_price() ?: $product->get_regular_price();
        $price_override_type = $offer_data['bogo_override_type'] ?? 'sale';
        $regular_price = (float) $product->get_regular_price();
        $sale_price = (float) $product->get_sale_price();
        // $price_per_piece = $price; // assuming 1 piece
        $offer_type = $GLOBALS['promoforge_bogo_data']['offer_type'];
        $buy_product_id = $GLOBALS['promoforge_bogo_data']['buy_product_id'];
        $buy_quantity = $GLOBALS['promoforge_bogo_data']['buy_quantity'];
        $get_quantity = $GLOBALS['promoforge_bogo_data']['get_quantity'];

        echo '<table class="variable-product-table bogo-' . esc_attr($product_type) . '-table">
        <thead>
          <tr>
            <th>Product</th>
            <th>Price</th>
            <th>Price/Unit</th>
            <th>Quantity</th>
            <th>Add BOGO</th>
          </tr>
        </thead>';
        echo "<tbody>";
        echo '<tr data-variation-id="0">';
        echo '<td>' . esc_html($product->get_name()) . '</td>';
        if ($price_override_type == 'regular') {
            // ✅ Always show regular price
            echo '<td class="price">' . wp_kses_post(wc_price($regular_price)) . '</td>';
        } else {
            // ✅ Default behavior (sale if exists and valid)
            if ($sale_price && $sale_price < $regular_price) {
                echo '<td class="price"><del>' . wp_kses_post(wc_price($regular_price)) . '</del> <ins>' . wp_kses_post(wc_price($sale_price)) . '</ins></td>';
            } else {
                echo '<td class="price">' . wp_kses_post(wc_price($regular_price)) . '</td>';
            }
        }

        if ($price_override_type === 'regular') {
            $price_per_piece = $regular_price;
        } else {
            $price_per_piece = ($sale_price && $sale_price < $regular_price) ? $sale_price : $regular_price;
        }
        echo '<td class="price-unit">' . wp_kses_post(wc_price($price_per_piece)) . ' /Piece</td>';
        if ($offer_type == 'buy_one_get_one') {
            echo '<td class="list-quantity"><input type="number" value="2" min="1" class="qty" id="qty_' . esc_attr($product->get_ID()) . '" name="quantity_' . esc_attr($product->get_ID()) . '" readonly></td>';
        }
        if ($offer_type == 'buy_x_get_y') {

            if ($product_id === $buy_product_id) {
                echo '<td class="list-quantity"><input type="number" value="' . esc_attr($buy_quantity) . '" min="1" class="qty" id="qty_' . esc_attr($product->get_ID()) . '" name="quantity_' . esc_attr($product->get_ID()) . '" readonly></td>';
            } else {
                echo '<td class="list-quantity"><input type="number" value="' . esc_attr($get_quantity) . '" min="1" class="qty" id="qty_' . esc_attr($product->get_ID()) . '" name="quantity_' . esc_attr($product->get_ID()) . '" readonly></td>';
            }
        }
        echo '<td class="add-bogo">';
        $offer_id = $GLOBALS['bogo_offer_id'] ?? 0;
        $offer_data = promoforge_get_bogo_offer_data($product);
        $price_override_type = $offer_data['bogo_override_type'] ?? 'sale';

        // $bogo_get_attrs contains HTML attributes, we use printf to output it safely alongside other attributes
        printf(
            '<button type="button" class="button alt bogo-add-to-cart" data-product-id="%s" data-variation-id="0" data-product-type="%s" data-offer-id="%s" data-price-override-type="%s" %s><span>Add %s Product</span></button>',
            esc_attr($product->get_ID()),
            esc_attr($product_type),
            esc_attr($offer_id),
            esc_attr($price_override_type),
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            $bogo_get_attrs, // Contains already escaped attributes
            esc_html(ucfirst($product_type))
        );

        echo '</td>';
        echo '</tr>';
        echo "</tbody>";
        echo '</table>';
    }
    if ($product->is_type('variable')) {
        $available_variations = $product->get_available_variations();
        echo '<table class="variable-product-table bogo-' . esc_attr($product_type) . '-table">
    <thead>
      <tr>
        <th>Pack Size</th>
        <th>Price</th>
        <th>Price/Unit</th>
        <th>Quantity</th>
        <th>Add BOGO</th>
      </tr>
    </thead>';
        echo "<tbody>";

        // Get override type (default sale)
        $price_override_type = $offer_data['bogo_override_type'] ?? 'sale';

        // Loop through all variations
        foreach ($available_variations as $variation) {
            $variation_obj = wc_get_product($variation['variation_id']);
            $regular_price = (float) $variation_obj->get_regular_price();
            $sale_price = (float) $variation_obj->get_sale_price();

            $attributes = $variation['attributes'];
            $buy_quantity = $GLOBALS['promoforge_bogo_data']['buy_quantity'];
            $get_quantity = $GLOBALS['promoforge_bogo_data']['get_quantity'];
            $buy_product_id = $GLOBALS['promoforge_bogo_data']['buy_product_id'];
            $parent_id = $variation_obj->get_parent_id();
            $is_in_stock = $variation_obj && $variation_obj->is_in_stock();
            $offer_type = $GLOBALS['promoforge_bogo_data']['offer_type'];
            $pack_size = $attributes['attribute_pa_pack-size'];

            // ✅ Decide which price to use
            if ($price_override_type === 'regular') {
                $final_price = $regular_price;
                $price_display_html = wc_price($regular_price);
            } else {
                if ($sale_price && $sale_price < $regular_price) {
                    $final_price = $sale_price;
                    $price_display_html = '<del>' . wc_price($regular_price) . '</del> <ins>' . wc_price($sale_price) . '</ins>';
                } else {
                    $final_price = $regular_price;
                    $price_display_html = wc_price($regular_price);
                }
            }

            // ✅ Calculate price per piece
            $price_per_piece = !empty($pack_size) ? number_format($final_price / (int) $pack_size, 2) : '-';

            echo '<tr data-variation-id="' . esc_attr($variation['variation_id']) . '">';
            if (isset($pack_size)) {
                $slug = $pack_size;
                $taxonomy = 'pa_pack-size';

                $term = get_term_by('slug', $slug, $taxonomy);
                if ($term) {
                    echo '<td>' . esc_html($term->name) . '</td>'; // Output: e.g., "100 Tablets"
                }
            }

            // ✅ Price cell
            echo '<td class="price">' . wp_kses_post($price_display_html) . '</td>';

            // ✅ Price per unit cell
            echo '<td class="price-unit">' . ($price_per_piece !== '-' ? wp_kses_post(wc_price($price_per_piece)) . ' /Piece' : '-') . '</td>';

            // ✅ Quantity logic
            if ($offer_type == 'buy_one_get_one') {
                echo '<td class="list-quantity"><input type="number" value="2" min="1" class="qty" id="qty_' . esc_attr($variation['variation_id']) . '" name="quantity_' . esc_attr($variation['variation_id']) . '" readonly></td>';
            }
            if ($offer_type == 'buy_x_get_y') {
                if ($parent_id == $buy_product_id) {
                    echo '<td class="list-quantity"><input type="number" value="' . esc_attr($buy_quantity) . '" min="1" class="qty" id="qty_' . esc_attr($variation['variation_id']) . '" name="quantity_' . esc_attr($variation['variation_id']) . '" readonly></td>';
                } else {
                    echo '<td class="list-quantity"><input type="number" value="' . esc_attr($get_quantity) . '" min="1" class="qty" id="qty_' . esc_attr($variation['variation_id']) . '" name="quantity_' . esc_attr($variation['variation_id']) . '" readonly></td>';
                }
            }

            // ✅ Add to cart button
            echo '<td>';
            $offer_id = $GLOBALS['bogo_offer_id'] ?? 0;
            $offer_data = promoforge_get_bogo_offer_data($product);
            $price_override_type = $offer_data['bogo_override_type'] ?? 'sale';

            printf(
                '<button type="button" class="button alt bogo-add-to-cart" data-product-id="%s" data-variation-id="%s" data-product-type="%s" data-offer-id="%s" data-price-override-type="%s" %s><span>Add %s Product</span></button>',
                esc_attr($product->get_id()),
                esc_attr($variation['variation_id']),
                esc_attr($product_type),
                esc_attr($offer_id),
                esc_attr($price_override_type),
                (!$is_in_stock ? 'disabled' : ''),
                esc_html(ucfirst($product_type))
            );

            echo '</td>';

            echo '</tr>';
        }

        echo "</tbody>";
        echo '</table>';
    }
}

// Display the BOGO product form (table or default Woo)
function promoforge_display_bogo_product_form($product, $type_label = '', $show_price = false, $product_type = '')
{
    $options = get_option('promoforge_offers_options');
    $bogo_format = $options['bogo_format'] ?? 'default';

    // Allow theme or plugin to override BOGO product form display
    $override = apply_filters('promoforge_offers_override_bogo_product_form', false, $product, $type_label, $show_price, $product_type);
    if ($override) {
        echo wp_kses_post($override);
        return;
    }

    // Force table format
    promoforge_bogo_custom_display_variable_product($product_type);
}



// Function to get BOGO offer data for a product
function promoforge_get_bogo_offer_data($product)
{
    global $wpdb;

    if (!$product || !is_a($product, 'WC_Product')) {
        return false;
    }

    $product_id = $product->get_id();
    if ($product->is_type('variation')) {
        $product_id = $product->get_parent_id();
    }

    $current_date = current_time('mysql');

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $offer = $wpdb->get_row($wpdb->prepare(
        "SELECT o.* FROM {$wpdb->prefix}promoforge_bogo_offers o
         JOIN {$wpdb->posts} wp ON o.post_id = wp.ID
         WHERE (o.buy_product_id = %d OR o.get_product_id = %d)
         AND o.start_date <= %s
         AND o.end_date >= %s
         AND wp.post_status = 'publish'
         ORDER BY o.id DESC
         LIMIT 1",
        $product_id,
        $product_id,
        $current_date,
        $current_date
    ));

    if (!$offer) {
        return false;
    }

    $options = get_option('promoforge_offers_options');
    $badge_text = $options['promoforge_bogo_offer_badge_text'] ?? '';
    $locations = $options['locations'] ?? [];
    $countdown_locations = $options['countdown_locations'] ?? [];
    $bogo_override_type = $options['bogo_override_type'] ?? 'sale';

    // Determine status based on current time and offer start/end
    $now = new DateTime('now', wp_timezone());
    $start = new DateTime($offer->start_date, wp_timezone());
    $end = new DateTime($offer->end_date, wp_timezone());

    if ($now < $start) {
        $status = 'upcoming';
    } elseif ($now >= $start && $now <= $end) {
        $status = 'active';
    } else {
        $status = 'expired';
    }

    return [
        'offer' => $offer,
        'badge_text' => $badge_text,
        'bogo_override_type' => $bogo_override_type,
        'locations' => $locations,
        'countdown_locations' => $countdown_locations,
        'status' => $status,
        'start' => $start->format('Y-m-d H:i:s'),
        'end' => $end->format('Y-m-d H:i:s'),
        'background_color' => $options['badge_bg_color'] ?? '#00a99d',
    ];
}


// Hook display function to WooCommerce actions
add_action('woocommerce_before_shop_loop_item_title', 'promoforge_display_promoforge_bogo_offer_badge', 5);
add_action('woocommerce_single_product_summary', 'promoforge_display_promoforge_bogo_offer_badge', 8);
add_action('promoforge_display_promoforge_bogo_offer_badge', 'promoforge_display_promoforge_bogo_offer_badge');
function promoforge_display_promoforge_bogo_offer_badge()
{
    global $product;
    if (!$product)
        return;

    $product_id = $product->get_id();
    // Check if already rendered via block filter
    if (!empty($GLOBALS['promoforge_badge_rendered_' . $product_id])) {
        return;
    }

    $offer_data = promoforge_get_bogo_offer_data($product);

    if (!$offer_data || empty($offer_data['badge_text']))
        return;

    // Check if offer is already in cart
    if (!empty($offer_data['offer'])) {
        $offer = $offer_data['offer'];
        $buy_in_cart = false;
        $get_in_cart = false;

        if (WC()->cart) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                if (intval($cart_item['product_id']) === intval($offer->buy_product_id)) {
                    $buy_in_cart = true;
                }
                if (intval($cart_item['product_id']) === intval($offer->get_product_id)) {
                    $get_in_cart = true;
                }
            }
        }

        if ($buy_in_cart && $get_in_cart) {
            return;
        }
    }

    $locations = $offer_data['locations'];
    $show_on_shop = is_shop() && !empty($locations['shop_loop']);
    $show_on_category = is_product_category() && !empty($locations['category_page']);
    $show_on_home = is_front_page() && !empty($locations['home_page']);
    $is_other_location = !is_shop() && !is_product_category() && !is_product() && !is_front_page() && !empty($locations['other_page']);
    $show_on_single = is_product() && !empty($locations['product_page']);
    $options = get_option('promoforge_offers_options');
    $badge_color = $options['badge_bg_color'] ?? '#00a99d';

    if ($show_on_shop || $show_on_category || $show_on_home || $is_other_location || $show_on_single) {
        // Inject CSS to hide default sale badge when custom BOGO badge is shown
        echo '<div class="promoforge-offer-badge ' . esc_attr($offer_data['status']) . '" style="background:' . esc_attr($badge_color) . '">' . esc_html($offer_data['badge_text']) . '</div>';
    }
}


add_action('woocommerce_before_shop_loop_item', 'promoforge_remove_bogo_sale_badge_css');
add_action('woocommerce_before_single_product_summary', 'promoforge_remove_bogo_sale_badge_css');

/**
 * determine if default sale badge should be hidden
 * Helper function to check if the default sale badge should be hidden
 * 
 * @param WC_Product $product
 * @return bool
 */
function promoforge_should_hide_default_badge($product)
{
    if (!$product) {
        return false;
    }

    $product_id = $product->get_id();

    // Check if already rendered via block filter
    if (!empty($GLOBALS['promoforge_badge_rendered_' . $product_id])) {
        return false;
    }

    // Check for Flash/Special offers first
    $promoforge_data = promoforge_get_offer_data($product);
    if (
        $promoforge_data && isset($promoforge_data['status']) && $promoforge_data['status'] === 'active' &&
        isset($promoforge_data['remaining_display']) && $promoforge_data['remaining_display'] > 0
    ) {
        return true;
    }

    $offer_data = promoforge_get_bogo_offer_data($product);

    if (!$offer_data || empty($offer_data['offer'])) {
        // If variation has no offer, check parent
        if ($product->is_type('variation')) {
            $parent = wc_get_product($product->get_parent_id());
            if ($parent) {
                $offer_data = promoforge_get_bogo_offer_data($parent);
            }
        }
    }

    if (!$offer_data || empty($offer_data['offer'])) {
        return false;
    }

    // Check if offer is already in cart
    $offer = $offer_data['offer'];
    $buy_in_cart = false;
    $get_in_cart = false;

    if (WC()->cart) {
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (intval($cart_item['product_id']) === intval($offer->buy_product_id)) {
                $buy_in_cart = true;
            }
            if (intval($cart_item['product_id']) === intval($offer->get_product_id)) {
                $get_in_cart = true;
            }
        }
    }

    if ($buy_in_cart && $get_in_cart) {
        return false;
    }

    // Check locations logic (replicated from display function logic where applicable, 
    // or just assume if offer exists and is active, we validly might show it)
    // The display logic checks locations.
    $locations = $offer_data['locations'];
    $show_on_shop = is_shop() && !empty($locations['shop_loop']);
    $show_on_category = is_product_category() && !empty($locations['category_page']);
    $show_on_home = is_front_page() && !empty($locations['home_page']);
    $is_other_location = !is_shop() && !is_product_category() && !is_product() && !is_front_page() && !empty($locations['other_page']);
    $show_on_single = is_product() && !empty($locations['product_page']);

    if ($show_on_shop || $show_on_category || $show_on_home || $is_other_location || $show_on_single) {
        return true;
    }

    return false;
}

// Add class to product wrapper if BOGO offer is active
add_filter('post_class', 'promoforge_add_bogo_class_to_product', 10, 3);
function promoforge_add_bogo_class_to_product($classes, $class, $post_id)
{
    if ('product' === get_post_type($post_id)) {
        $product = wc_get_product($post_id);
        if (promoforge_should_hide_default_badge($product)) {
            $classes[] = 'promoforge-hide-default-sale-badge';
        }
    }
    return $classes;
}

// Deprecated/Modified: No longer used to populate global array, but kept for legacy hook compatibility if needed.
// Only used to ensure logic consistency or future hooks.
function promoforge_remove_bogo_sale_badge_css()
{
    // The logic is now handled via post_class and static CSS
}


// Hook countdown display for BOGO offers
add_action('woocommerce_before_shop_loop_item_title', 'promoforge_display_bogoffers_countdown', 5);
add_action('woocommerce_single_product_summary', 'promoforge_display_bogoffers_countdown', 8);
function promoforge_display_bogoffers_countdown()
{
    global $product;
    if (!$product)
        return;

    $product_id = $product->get_id();
    // Check if already rendered via block filter
    if (!empty($GLOBALS['promoforge_badge_rendered_' . $product_id])) {
        return;
    }

    $offer_data = promoforge_get_bogo_offer_data($product);
    if (!$offer_data)
        return;

    // Check if offer is already in cart
    if (!empty($offer_data['offer'])) {
        $offer = $offer_data['offer'];
        $buy_in_cart = false;
        $get_in_cart = false;

        if (WC()->cart) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                if (intval($cart_item['product_id']) === intval($offer->buy_product_id)) {
                    $buy_in_cart = true;
                }
                if (intval($cart_item['product_id']) === intval($offer->get_product_id)) {
                    $get_in_cart = true;
                }
            }
        }

        if ($buy_in_cart && $get_in_cart) {
            return;
        }
    }

    $locations = $offer_data['countdown_locations'];
    $show_on_shop = is_shop() && !empty($locations['shop_loop']);
    $show_on_category = is_product_category() && !empty($locations['category_page']);
    $show_on_home = is_front_page() && !empty($locations['home_page']);
    $is_other_location = !is_shop() && !is_product_category() && !is_product() && !is_front_page() && !empty($locations['other_page']);
    $show_on_single = is_product() && !empty($locations['product_page']);
    $wp_timezone = wp_timezone();

    if ($show_on_shop || $show_on_category || $show_on_home || $is_other_location || $show_on_single) {
        if ($offer_data['status'] === 'upcoming' && !empty($offer_data['start'])) {
            $start = new DateTime($offer_data['start'], $wp_timezone);
            $start->setTimezone(new DateTimeZone('UTC'));
            echo '<div class="promoforge-offer-countdown-timer upcoming-offer"
                  data-start="' . esc_attr($start->format('Y-m-d\TH:i:s\Z')) . '"
                  data-product-id="' . esc_attr($product->get_id()) . '">' . esc_html__('Starts soon', 'promoforge-smart-campaigns-for-woocommerce') . '</div>';
        } elseif (!empty($offer_data['end'])) {
            $end = new DateTime($offer_data['end'], $wp_timezone);
            $end->setTimezone(new DateTimeZone('UTC'));
            echo '<div class="promoforge-offer-countdown-timer"
                  data-end="' . esc_attr($end->format('Y-m-d\TH:i:s\Z')) . '"
                  data-product-id="' . esc_attr($product->get_id()) . '">' . esc_html__('Ending soon', 'promoforge-smart-campaigns-for-woocommerce') . '</div>';
        }
    }
}



// Filter the price HTML to override based on BOGO settings
add_filter('woocommerce_get_price_html', 'promoforge_bogo_offers_price_html_override', 100, 2);
function promoforge_bogo_offers_price_html_override($price_html, $product)
{
    // Get BOGO offer data for the product
    $offer_data = promoforge_get_bogo_offer_data($product);

    // If no active BOGO offer, return the default price HTML
    if (!$offer_data || $offer_data['status'] !== 'active') {
        return $price_html;
    }

    // Get the override type from settings, defaulting to 'sale'
    $override_type = $offer_data['bogo_override_type'] ?? 'sale';

    // Handle selected variation price display
    if ($product->is_type('variation')) {
        if ($override_type === 'regular') {
            return wc_price($product->get_regular_price());
        }
        // For 'sale' override, the default behavior is correct.
        return $price_html;
    }


    // Handle variable products
    if ($product->is_type('variable')) {
        $prices = $product->get_variation_prices(true);

        if (empty($prices['price'])) {
            return $price_html;
        }

        if ($override_type == 'regular') {
            $min_reg_price = current($prices['regular_price']);
            $max_reg_price = end($prices['regular_price']);
            return $min_reg_price !== $max_reg_price ? sprintf('%1$s–%2$s', wc_price($min_reg_price), wc_price($max_reg_price)) : wc_price($min_reg_price);
        }

        // For 'sale' override, the default WooCommerce behavior is usually correct,
        // as it shows sale price ranges. We'll just return the original HTML.
        return $price_html;
    }

    // Handle simple products
    if ($product->is_type('simple')) {
        if ($override_type == 'regular') {
            // If override is 'regular', display only the regular price.
            return wc_price($product->get_regular_price());
        } else { // 'sale'
            // If override is 'sale', show sale price with regular price struck out if on sale.
            if ($product->is_on_sale()) {
                return wc_format_sale_price(wc_get_price_to_display($product, array('price' => $product->get_regular_price())), wc_get_price_to_display($product));
            } else {
                return wc_price($product->get_regular_price());
            }
        }
    }

    return $price_html;
}


// Filter the variation data to override the price HTML based on BOGO settings.
add_filter('woocommerce_available_variation', 'promoforge_bogo_offers_variation_price_html_override', 100, 3);
function promoforge_bogo_offers_variation_price_html_override($variation_data, $product, $variation)
{
    $offer_data = promoforge_get_bogo_offer_data($product);
    if ($offer_data && ($offer_data['bogo_override_type'] ?? 'sale') === 'regular') {
        $variation_data['price_html'] = '<span class="price">' . wc_price($variation->get_regular_price()) . '</span>';
    }
    return $variation_data;
}
