<?php
if (!defined('ABSPATH')) {
    exit;
}

// Shortcode to display special offer products
add_shortcode('flash_special_offer', 'flashoffers_display_special_offer_products');

function flashoffers_display_special_offer_products($atts)
{
    $atts = shortcode_atts([
        'id' => 0, // Special Offer post ID
        'columns' => 3,
        'limit' => -1,
    ], $atts, 'flash_special_offer');

    $offer_id = (int) $atts['id'];
    if (!$offer_id)
        return '<p class="flash-offer-error">' . esc_html__('Invalid offer ID.', 'advanced-offers-for-woocommerce') . '</p>';

    global $wpdb;

    // Get offer info
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $offer = $wpdb->get_row($wpdb->prepare(
        "SELECT o.id, o.discount, o.end_date FROM {$wpdb->prefix}flash_offers o
         JOIN {$wpdb->posts} wp ON o.post_id = wp.ID
         WHERE o.post_id = %d AND o.offer_type = %s AND wp.post_status = 'publish'",
        $offer_id,
        'special'
    ));

    if (!$offer)
        return '<p class="flash-offer-error">' . esc_html__('Invalid or missing special offer.', 'advanced-offers-for-woocommerce') . '</p>';

    // Check if offer is expired
    if (strtotime($offer->end_date) < current_time('timestamp')) {
        return '<p class="flash-offer-error">' . esc_html__('This special offer has expired.', 'advanced-offers-for-woocommerce') . '</p>';
    }

    // Get product IDs linked to this offer
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $product_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT product_id FROM {$wpdb->prefix}flash_offer_products WHERE offer_id = %d",
        $offer->id
    ));

    if (empty($product_ids))
        return '<p class="flash-offer-notice">' . esc_html__('No products found in this offer.', 'advanced-offers-for-woocommerce') . '</p>';

    // Apply limit if set
    if ($atts['limit'] > 0) {
        $product_ids = array_slice($product_ids, 0, $atts['limit']);
    }

    // WP Query to get products
    $args = [
        'post_type' => 'product',
        'post_status' => 'publish',
        'post__in' => $product_ids,
        'posts_per_page' => -1,
        'orderby' => 'post__in',
    ];
    $products = new WP_Query($args);

    if (!$products->have_posts())
        return '<p class="flash-offer-notice">' . esc_html__('No valid products found.', 'advanced-offers-for-woocommerce') . '</p>';

    // Force discount logic to work by setting `from_offer` param
    $_GET['from_offer'] = $offer_id;

    // Count products
    $product_count = $products->post_count;
    $slider_class = 'flash-offer-slider-enabled';

    // Output
    ob_start();
    ?>

    <div class="woocommerce flash-special-offer-container <?php echo esc_attr($slider_class); ?>"
        data-offer-id="<?php echo (int) $offer_id; ?>" data-columns="<?php echo (int) $atts['columns']; ?>">
        <div class="wao-products">
            <?php
            $GLOBALS['flashoffers_special_offer_context'] = true;
            while ($products->have_posts()):
                $products->the_post(); ?>
                <?php
                global $product;
                $product_url = add_query_arg('from_offer', $offer_id, get_permalink($product->get_id()));
                ?>

                <div class="wao-product flash-special-offer-product product">
                    <a href="<?php echo esc_url($product_url); ?>" class="woocommerce-LoopProduct-link">
                        <?php echo wp_kses_post($product->get_image('woocommerce_thumbnail')); ?>
                    </a>

                    <?php
                    // Explicitly call badge and countdown functions since we removed the hooks
                    flashoffers_display_flash_offer_badge();
                    flashoffers_show_countdown();
                    ?>

                    <h2 class="woocommerce-loop-product__title">
                        <a href="<?php echo esc_url($product_url); ?>"><?php echo esc_html(get_the_title()); ?></a>
                    </h2>

                    <span class="price"><?php echo wp_kses_post($product->get_price_html()); ?></span>

                    <div class="flash-special-offer-actions">
                        <?php
                        // Add to cart button with offer parameter
                        $add_to_cart_url = add_query_arg('from_offer', $offer_id, $product->add_to_cart_url());
                        echo sprintf(
                            '<a href="%s" data-quantity="1" class="button product_type_%s add_to_cart_button" data-product_id="%s" data-product_sku="%s">%s</a>',
                            esc_url($add_to_cart_url),
                            esc_attr($product->get_type()),
                            esc_attr($product->get_id()),
                            esc_attr($product->get_sku()),
                            esc_html($product->add_to_cart_text())
                        );
                        ?>
                    </div>
                </div>
            <?php endwhile;
            unset($GLOBALS['flashoffers_special_offer_context']);
            ?>
        </div>
    </div>

    <?php
    wp_reset_postdata();
    unset($_GET['from_offer']);
    return ob_get_clean();
}

