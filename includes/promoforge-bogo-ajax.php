<?php
if (!defined('ABSPATH')) {
    exit;
}

// Handle AJAX request to add BOGO product to cart
add_action('wp_ajax_promoforge_bogo_add_to_cart', 'promoforge_handle_bogo_ajax_add_to_cart');
add_action('wp_ajax_nopriv_promoforge_bogo_add_to_cart', 'promoforge_handle_bogo_ajax_add_to_cart');

function promoforge_handle_bogo_ajax_add_to_cart()
{
    check_ajax_referer('bogo_add_nonce', 'nonce');

    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $variation_id = isset($_POST['variation_id']) ? intval($_POST['variation_id']) : 0;
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;

    if (!$product_id) {
        wp_send_json_error(['message' => esc_html__('Invalid product ID.', 'promoforge-smart-campaigns-for-woocommerce')]);
    }

    $product = wc_get_product($product_id);

    if (!$product) {
        wp_send_json_error(['message' => esc_html__('Invalid product.', 'promoforge-smart-campaigns-for-woocommerce')]);
    }

    // Validate variation if provided
    if ($variation_id && !wc_get_product($variation_id)) {
        wp_send_json_error(['message' => esc_html__('Invalid variation.', 'promoforge-smart-campaigns-for-woocommerce')]);
    }

    // Add product/variation to cart
    $added = WC()->cart->add_to_cart($product_id, $quantity, $variation_id);

    if ($added) {
        wp_send_json_success(['message' => esc_html__('Product added to cart successfully!', 'promoforge-smart-campaigns-for-woocommerce')]);
    } else {
        wp_send_json_error(['message' => esc_html__('Could not add product to cart.', 'promoforge-smart-campaigns-for-woocommerce')]);
    }
}

// Handle AJAX request to load BOGO product form
add_action('wp_ajax_promoforge_load_bogo_product_form', 'promoforge_load_bogo_product_form');
add_action('wp_ajax_nopriv_promoforge_load_bogo_product_form', 'promoforge_load_bogo_product_form');
function promoforge_load_bogo_product_form()
{
    check_ajax_referer('promoforge_bogo_nonce', 'nonce');

    if (!isset($_POST['buy_product_id']) || !isset($_POST['get_product_id'])) {
        wp_send_json_error(['message' => esc_html__('Missing offer data', 'promoforge-smart-campaigns-for-woocommerce')]);
    }

    $buy_product_id = intval($_POST['buy_product_id']);
    $get_product_id = intval($_POST['get_product_id']);
    $buy_quantity = intval($_POST['buy_quantity'] ?? 1);
    $get_quantity = intval($_POST['get_quantity'] ?? 1);
    $offer_type = sanitize_text_field(wp_unslash($_POST['offer_type'] ?? 'buy_x_get_y'));
    $discount = floatval($_POST['discount'] ?? 0);

    $buy_product = wc_get_product($buy_product_id);
    $get_product = wc_get_product($get_product_id);

    if (!$buy_product || !$get_product) {
        wp_send_json_error(['message' => esc_html__('Invalid products', 'promoforge-smart-campaigns-for-woocommerce')]);
    }

    // Query the full offer record from database for different products offers
    if ($offer_type === 'buy_x_get_y') {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $offer_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}promoforge_bogo_offers WHERE buy_product_id = %d AND get_product_id = %d",
            $buy_product_id,
            $get_product_id
        ));

        if (!$offer_record) {
            wp_send_json_error(['message' => esc_html__('Offer not found in database', 'promoforge-smart-campaigns-for-woocommerce')]);
        }

        // Use database values for accuracy
        $discount = $offer_record->discount;
        $GLOBALS['promoforge_bogo_offer_id'] = (int) $offer_record->id;
        $GLOBALS['promoforge_bogo_discount'] = $discount;
    }



    // Set global BOGO data for custom_display_variable_product
    $GLOBALS['promoforge_bogo_data'] = array(
        'buy_product_id' => $buy_product_id,
        'get_product_id' => $get_product_id,
        'buy_quantity' => $buy_quantity,
        'get_quantity' => $get_quantity,
        'offer_type' => $offer_type,
        'discount' => $discount
    );

    ob_start();

    echo '<div class="bogo-offer-box">';

    // ========================
    // ✅ OFFER TITLE
    // ========================
    if ($offer_type == 'buy_one_get_one') {
        /* translators: %s: Product Name */
        echo '<h3>' . esc_html(sprintf(__('Buy One Get One: %s', 'promoforge-smart-campaigns-for-woocommerce'), $buy_product->get_name())) . '</h3>';
    } else {
        /* translators: 1: Quantity, 2: Product Name */
        echo '<h3>' . esc_html(sprintf(__('Buy %1$d: %2$s', 'promoforge-smart-campaigns-for-woocommerce'), $buy_quantity, $buy_product->get_name())) . '</h3>';
    }

    // ========================
    // ✅ BUY PRODUCT FORM
    // ========================
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    global $product, $post;
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $product = $buy_product; // set global

    $buy_post = get_post($buy_product_id);
    if ($buy_post) {
        $post = $buy_post;
        setup_postdata($buy_post);
    }
    $price = $product->get_sale_price() ?: $product->get_regular_price();

    // Default format logic removed, forcing table display via promoforge_display_bogo_product_form

    ob_start();
    promoforge_display_bogo_product_form($product, true, 'buy');
    $buy_html = ob_get_clean();
    $buy_html = str_replace('<form class="', '<form class="bogo-buy-form ', $buy_html);

    // Add hidden input for simple product add-to-cart
    if ($buy_product->is_type('simple')) {
        $hidden_input = '<input type="hidden" name="add-to-cart" value="' . esc_attr($buy_product->get_id()) . '" />';
        $buy_html = str_replace('</form>', $hidden_input . '</form>', $buy_html);
        // Wrap in div for inline display
        $buy_html = '<div class="bogo-simple-product-inline">' . $buy_html . '</div>';
    }

    // Append variations data inside the form if variable
    if ($buy_product->is_type('variable')) {
        $variations_json = wp_json_encode($buy_product->get_available_variations());
        $attributes = $buy_product->get_variation_attributes();

        $variations_script = '<div class="variations_form" data-product_id="' . esc_attr($buy_product->get_id()) . '">';
        $variations_script .= '<script type="application/json" class="wc-variations">' . $variations_json . '</script>';
        $variations_script .= '</div>';

        $buy_html = str_replace('</form>', $variations_script . '</form>', $buy_html);
    }


    echo $buy_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

    // ========================
    // ✅ GET PRODUCT (only if not BOGO 1+1)
    // ========================
    if ($offer_type !== 'buy_one_get_one') {
        /* translators: 1: Quantity, 2: Discount, 3: Product Name */
        echo '<h3>' . esc_html(sprintf(__('Get %1$d at %2$s%% Off: %3$s', 'promoforge-smart-campaigns-for-woocommerce'), $get_quantity, round($discount), $get_product->get_name())) . '</h3>';

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
        $product = $get_product; // set global

        $get_post = get_post($get_product_id);
        if ($get_post) {
            $post = $get_post;
            setup_postdata($get_post);
        }
        $price = $product->get_sale_price() ?: $product->get_regular_price();

        // Default format logic removed for GET product as well

        ob_start();

        promoforge_display_bogo_product_form($product, true, 'get');
        $get_html = ob_get_clean();
        $get_html = str_replace('<form class="', '<form class="bogo-get-form ', $get_html);
        // Remove id attributes from select tags and for attributes from labels to avoid duplicates
        $get_html = preg_replace('/\sid\s*=\s*["\'][^"\']*["\']/', '', $get_html);
        $get_html = preg_replace('/\sfor\s*=\s*["\'][^"\']*["\']/', '', $get_html);

        // Add hidden input for simple product add-to-cart
        if ($get_product->is_type('simple')) {
            $hidden_input = '<input type="hidden" name="add-to-cart" value="' . esc_attr($get_product->get_id()) . '" />';
            $get_html = str_replace('</form>', $hidden_input . '</form>', $get_html);
            // Wrap in div for inline display
            $get_html = '<div class="bogo-simple-product-inline">' . $get_html . '</div>';
        }

        // Append variations data inside the form if variable
        if ($get_product->is_type('variable')) {
            $variations_json = wp_json_encode($get_product->get_available_variations());
            $attributes = $get_product->get_variation_attributes();

            $variations_script = '<div class="variations_form" data-product_id="' . esc_attr($get_product->get_id()) . '">';
            $variations_script .= '<script type="application/json" class="wc-variations">' . $variations_json . '</script>';
            $variations_script .= '</div>';

            $get_html = str_replace('</form>', $variations_script . '</form>', $get_html);
        }


        echo $get_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    wp_reset_postdata();

    echo '</div>';

    $html = ob_get_clean();

    wp_send_json_success([
        'html' => $html,
        'offer_type' => $offer_type,
        'buy_quantity' => $buy_quantity,
        'get_quantity' => $get_quantity
    ]);
}