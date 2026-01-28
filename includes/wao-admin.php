<?php
defined('ABSPATH') or die('Direct access not allowed');

// Add meta box to flash offer admin side
add_action('add_meta_boxes', function () {
    add_meta_box(
        'flash_offer_details',
        esc_html__('Offer Details', 'advanced-offers-for-woocommerce'),
        'flashoffers_offer_details_callback',
        'flash_offer',
        'normal',
        'default'
    );
});

// Callback function to display the meta box
function flashoffers_offer_details_callback($post)
{
    global $wpdb;

    wp_nonce_field('flash_offer_details', 'flash_offer_details_nonce');

    // Verify tables exist
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}flash_offers'") != $wpdb->prefix . 'flash_offers') {
        echo '<div class="error"><p>' . esc_html__('Flash Offers tables not found. Please deactivate and reactivate the plugin.', 'advanced-offers-for-woocommerce') . '</p></div>';
        return;
    }

    // Get offer data
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $offer = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}flash_offers WHERE post_id = %d",
        $post->ID
    ));

    $offer_type = $offer ? $offer->offer_type : 'flash';
    $end = $offer ? $offer->end_date : '';
    $start = $offer ? $offer->start_date : '';
    $discount = $offer ? $offer->discount : '';
    $use_offers = $offer ? $offer->use_offers : '';

    // Get assigned products
    $products = [];
    if ($offer) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $products = $wpdb->get_col($wpdb->prepare(
            "SELECT product_id FROM {$wpdb->prefix}flash_offer_products WHERE offer_id = %d",
            $offer->id
        ));
    }

    // Get assigned categories
    $categories = [];
    if ($offer) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $categories = $wpdb->get_col($wpdb->prepare(
            "SELECT category_id FROM {$wpdb->prefix}flash_offer_categories WHERE offer_id = %d",
            $offer->id
        ));
    }

    // Get all product categories
    $categories_list = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false
    ]);
    ?>

    <div class="flash-offer-admin">
        <p>
            <label for="offer_type"><?php esc_html_e('Offer Type:', 'advanced-offers-for-woocommerce'); ?></label><br>
            <select name="offer_type" id="offer_type" style="width:200px;">
                <option value="flash" <?php selected($offer_type, 'flash'); ?>>
                    <?php esc_html_e('Flash Offer', 'advanced-offers-for-woocommerce'); ?>
                </option>
                <option value="upcoming" <?php selected($offer_type, 'upcoming'); ?>>
                    <?php esc_html_e('Upcoming Offer', 'advanced-offers-for-woocommerce'); ?>
                </option>
                <option value="special" <?php selected($offer_type, 'special'); ?>>
                    <?php esc_html_e('Special Offer', 'advanced-offers-for-woocommerce'); ?>
                </option>
            </select>
        </p>

        <p class="flash_offer_start_fields" style="display:<?php echo ($offer_type === 'flash') ? 'none' : 'block'; ?>">
            <label
                for="flash_offer_start"><?php esc_html_e('Start Date & Time:', 'advanced-offers-for-woocommerce'); ?></label><br>
            <input type="datetime-local" name="flash_offer_start"
                value="<?php echo esc_attr($start ? str_replace(' ', 'T', substr($start, 0, 16)) : ''); ?>"
                style="width:200px;">
        </p>

        <p>
            <label
                for="flash_offer_end"><?php esc_html_e('End Date & Time:', 'advanced-offers-for-woocommerce'); ?></label><br>
            <input type="datetime-local" name="flash_offer_end"
                value="<?php echo esc_attr($end ? str_replace(' ', 'T', substr($end, 0, 16)) : ''); ?>"
                style="width:200px;">
        </p>

        <p>
            <label
                for="flash_offer_discount"><?php esc_html_e('Discount (%):', 'advanced-offers-for-woocommerce'); ?></label><br>
            <input type="number" name="flash_offer_discount" value="<?php echo esc_attr($discount); ?>" min="1" max="100"
                style="width:200px;">
        </p>
        <p>
            <label
                for="flash_offer_use_offers"><?php esc_html_e('How Many Time User Can Use This Offers:', 'advanced-offers-for-woocommerce'); ?></label><br>
            <input type="number" name="flash_offer_use_offers" value="<?php echo esc_attr($use_offers); ?>" min="1"
                max="100" style="width:200px;">
        </p>


        <div id="flash_offer_upcoming_offer_fields_product">
            <p>
                <label><?php esc_html_e('Assign to Categories:', 'advanced-offers-for-woocommerce'); ?></label><br>
                <select id="flash_offer_category_selector" name="flash_offer_offer_categories[]" multiple="multiple"
                    style="width:400px;" class="flash_offer_wc-enhanced-select">
                    <?php foreach ($categories_list as $category): ?>
                        <option value="<?php echo esc_attr($category->term_id); ?>" <?php selected(in_array($category->term_id, $categories)); ?>>
                            <?php echo esc_html($category->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <div id="category-product-preview" style="margin-top: 20px;">
                <?php if (!empty($products)): ?>
                    <div class="product-select-list">
                        <?php foreach ($products as $product_id):
                            $product = wc_get_product($product_id);
                            if (!$product)
                                continue;
                            ?>
                            <label class="product-box">
                                <input type="checkbox" name="offer_products[]" value="<?php echo esc_attr($product_id); ?>"
                                    checked />
                                <div style="height:100px; display:flex; align-items:center; justify-content:center;">
                                    <?php echo wp_kses_post($product->get_image('thumbnail')); ?>
                                </div>
                                <strong><?php echo esc_html($product->get_name()); ?></strong>
                                <span><?php echo wp_kses_post($product->get_price_html()); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p><?php esc_html_e('Select categories to see products', 'advanced-offers-for-woocommerce'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div id="selected-product-list" style="margin-top: 30px;">
            <h4><?php esc_html_e('Selected Products:', 'advanced-offers-for-woocommerce'); ?></h4>
            <div class="selected-product-box" style="display: flex; flex-wrap: wrap; gap: 15px;"></div>
        </div>

        <div id="hidden-offer-products"></div>
    </div>

    <?php
}

// Save offer details in database
add_action('save_post_flash_offer', function ($post_id) {
    global $wpdb;

    if (
        !isset($_POST['flash_offer_details_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['flash_offer_details_nonce'])), 'flash_offer_details')
    ) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return;
    if (!current_user_can('edit_post', $post_id))
        return;

    // Prevent duplicate saves with transient lock
    $lock_key = 'flash_offer_saving_' . $post_id;
    if (get_transient($lock_key)) {
        return; // Already saving, skip this call
    }
    set_transient($lock_key, true, 10); // Lock for 10 seconds



    // Prepare offer data
    // Prepare offer data
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
    $offer_data = [
        'post_id' => $post_id,
        'offer_type' => sanitize_text_field(wp_unslash($_POST['offer_type'] ?? 'flash')),
        'start_date' => sanitize_text_field(wp_unslash($_POST['flash_offer_start'] ?? '')),
        'end_date' => sanitize_text_field(wp_unslash($_POST['flash_offer_end'] ?? '')),
        'discount' => floatval($_POST['flash_offer_discount'] ?? 0),
        'use_offers' => intval($_POST['flash_offer_use_offers'] ?? 0)
    ];

    // Check if offer exists
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $offer = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}flash_offers WHERE post_id = %d",
        $post_id
    ));

    if ($offer) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $wpdb->prefix . 'flash_offers',
            $offer_data,
            ['id' => $offer->id],
            ['%d', '%s', '%s', '%s', '%f', '%d'],
            ['%d']
        );
        $offer_id = $offer->id;
    } else {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->insert(
            $wpdb->prefix . 'flash_offers',
            $offer_data,
            ['%d', '%s', '%s', '%s', '%f', '%d']
        );
        $offer_id = $wpdb->insert_id;
    }

    // Ensure table migration is complete before saving
    flashoffers_migrate_categories_table();

    // Get current products for this offer
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $current_products = $wpdb->get_col($wpdb->prepare(
        "SELECT product_id FROM {$wpdb->prefix}flash_offer_products WHERE offer_id = %d",
        $offer_id
    ));

    // Get new products from form
    $new_products = [];
    if (!empty($_POST['offer_products']) && is_array($_POST['offer_products'])) {
        $new_products = array_unique(array_map('intval', $_POST['offer_products']));
    }

    // Find products to add and remove
    $products_to_add = array_diff($new_products, $current_products);
    $products_to_remove = array_diff($current_products, $new_products);

    // Remove products that are no longer selected
    if (!empty($products_to_remove)) {
        $placeholders = implode(',', array_fill(0, count($products_to_remove), '%d'));
        // Prepare delete reliably
        // Prepare delete reliably
        $query = "DELETE FROM {$wpdb->prefix}flash_offer_products WHERE offer_id = %d AND product_id IN ($placeholders)";
        $params = array_merge([$offer_id], $products_to_remove);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query($wpdb->prepare($query, $params));

        // Also remove from categories table
        $query_cat = "DELETE FROM {$wpdb->prefix}flash_offer_categories WHERE offer_id = %d AND product_id IN ($placeholders)";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query($wpdb->prepare($query_cat, $params));
    }

    // Add new products
    foreach ($products_to_add as $product_id) {
        if ($product_id > 0) {
            // Check if product exists
            $product = wc_get_product($product_id);
            if ($product) {
                // Insert into products table
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->query($wpdb->prepare(
                    "INSERT IGNORE INTO {$wpdb->prefix}flash_offer_products
                    (offer_id, product_id)
                    VALUES (%d, %d)",
                    $offer_id,
                    $product_id
                ));

                // Get primary category for this product and insert into categories table
                $primary_category_id = flashoffers_get_product_primary_category($product_id);
                if ($primary_category_id > 0) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->query($wpdb->prepare(
                        "INSERT IGNORE INTO {$wpdb->prefix}flash_offer_categories
                        (offer_id, category_id, product_id)
                        VALUES (%d, %d, %d)",
                        $offer_id,
                        $primary_category_id,
                        $product_id
                    ));
                }
            }
        }
    }



    // Clear the transient lock
    delete_transient($lock_key);
});


// Add shortcode meta box ONLY if offer is special
add_action('add_meta_boxes_flash_offer', 'flashoffers_add_conditional_shortcode_meta_box');
function flashoffers_add_conditional_shortcode_meta_box($post)
{
    global $wpdb;

    // Get current post ID
    $post_id = $post->ID;

    // Query the custom table for the current post only
    // Query the custom table for the current post only
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $offer_type = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT offer_type FROM {$wpdb->prefix}flash_offers WHERE post_id = %d AND offer_type = %s",
            $post_id,
            'special'
        )
    );

    // Only proceed if this post has 'special' offer_type AND is published
    if ($offer_type !== 'special' || $post->post_status !== 'publish') {
        return;
    }

    // Add the meta box
    add_meta_box(
        'special_offer_shortcode',
        'Special Offer Shortcode',
        'flashoffers_display_special_offer_shortcode',
        'flash_offer',
        'side',
        'high'
    );
}

// Display shortcode for special offer at edit post
function flashoffers_display_special_offer_shortcode($post)
{
    $shortcode = '[flash_special_offer id=' . $post->ID . ']';
    ?>
    <p><?php esc_html_e('Use this shortcode to display this special offer:', 'advanced-offers-for-woocommerce'); ?></p>
    <input type="text" value="<?php echo esc_attr($shortcode); ?>" readonly class="widefat" id="special-offer-shortcode">
    <button type="button" class="button button-primary copy-shortcode"
        data-clipboard-text="<?php echo esc_attr($shortcode); ?>"><?php esc_html_e('Copy', 'advanced-offers-for-woocommerce'); ?></button>

    <?php
}


// AJAX handler for getting products by categories
add_action('wp_ajax_get_products_by_categories', 'flashoffers_get_products_by_categories');

function flashoffers_get_products_by_categories()
{
    global $wpdb;

    if (!current_user_can('edit_posts')) {
        wp_die();
    }

    check_ajax_referer('flash_offer_details', 'nonce'); // Make sure JS sends this

    $categories = isset($_POST['categories']) ? array_map('intval', $_POST['categories']) : [];
    $current_post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

    if (empty($categories)) {
        echo '<p>' . esc_html__('Please select at least one category.', 'advanced-offers-for-woocommerce') . '</p>';
        wp_die();
    }

    // 1. Get products already selected in current offer
    $current_offer_products = [];
    if ($current_post_id) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $offer_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}flash_offers WHERE post_id = %d",
            $current_post_id
        ));

        if ($offer_id) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $current_offer_products = $wpdb->get_col($wpdb->prepare(
                "SELECT product_id FROM {$wpdb->prefix}flash_offer_products WHERE offer_id = %d",
                $offer_id
            ));
        }
    }

    // 2. Get products used in other flash offers
    $excluded_product_ids = [];
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $other_offers = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT DISTINCT product_id FROM {$wpdb->prefix}flash_offer_products 
             WHERE offer_id IN (
                 SELECT id FROM {$wpdb->prefix}flash_offers 
                 WHERE post_id != %d AND end_date > NOW()
             )",
            $current_post_id
        )
    );

    foreach ($other_offers as $offer) {
        if (!in_array($offer->product_id, $current_offer_products)) {
            $excluded_product_ids[] = $offer->product_id;
        }
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $bogo_products = $wpdb->get_col("SELECT buy_product_id FROM {$wpdb->prefix}bogo_offers WHERE buy_product_id > 0");
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $bogo_products2 = $wpdb->get_col("SELECT get_product_id FROM {$wpdb->prefix}bogo_offers WHERE get_product_id > 0");

    $bogo_product_ids = array_merge($bogo_products, $bogo_products2);

    /**
     * 🔹 3. Build exclusion list
     */
    $exclude_ids = array_merge($excluded_product_ids, $bogo_product_ids);
    // $exclude_ids = array_diff($exclude_ids, [$buy_product_id, $get_product_id]);

    // 3. Get products by category (excluding those already used)
    $args = [
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
        'post__not_in' => $exclude_ids,
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
        'tax_query' => [
            [
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $categories,
                'operator' => 'IN'
            ]
        ]
    ];

    $products_query = new WP_Query($args);

    if ($products_query->have_posts()) {
        echo '<div class="product-select-list">';

        while ($products_query->have_posts()) {
            $products_query->the_post();
            $product = wc_get_product(get_the_ID());
            if (!$product)
                continue;

            $product_id = $product->get_id();
            $checked = in_array($product_id, $current_offer_products) ? 'checked' : '';

            echo '<label class="product-box">';
            echo '<input type="checkbox" name="offer_products[]" value="' . esc_attr($product_id) . '" ' . esc_attr($checked) . ' />';
            echo '<div style="height:100px; display:flex; align-items:center; justify-content:center;">' . wp_kses_post($product->get_image('thumbnail')) . '</div>';
            echo '<strong style="display:block; margin-top:5px; font-size:0.9em;">' . esc_html($product->get_name()) . '</strong>';
            echo '<span style="font-size:0.8em;">' . wp_kses_post($product->get_price_html()) . '</span>';
            echo '</label>';
        }

        echo '</div>';
    } else {
        echo '<p>' . esc_html__('No products found in selected categories.', 'advanced-offers-for-woocommerce') . '</p>';
    }

    wp_reset_postdata();
    wp_die();
}

// Hook to delete flash offer data when post is deleted
add_action('before_delete_post', function ($post_id) {
    global $wpdb;

    // Check if this is a flash_offer post type
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'flash_offer') {
        return;
    }

    // Get the offer ID from the flash_offers table
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $offer = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}flash_offers WHERE post_id = %d",
        $post_id
    ));

    if ($offer) {
        $offer_id = $offer->id;

        // Delete from flash_offer_categories table
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete(
            $wpdb->prefix . 'flash_offer_categories',
            ['offer_id' => $offer_id],
            ['%d']
        );

        // Delete from flash_offer_products table
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete(
            $wpdb->prefix . 'flash_offer_products',
            ['offer_id' => $offer_id],
            ['%d']
        );

        // Delete from flash_offers table
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete(
            $wpdb->prefix . 'flash_offers',
            ['post_id' => $post_id],
            ['%d']
        );
    }
});
