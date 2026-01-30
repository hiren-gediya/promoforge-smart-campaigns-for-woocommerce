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
            <select name="offer_type" id="offer_type" class="wao-admin-select-200">
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

        <p
            class="flash_offer_start_fields <?php echo ($offer_type === 'flash') ? 'wao-display-none' : 'wao-display-block'; ?>">
            <label
                for="flash_offer_start"><?php esc_html_e('Start Date & Time:', 'advanced-offers-for-woocommerce'); ?></label><br>
            <input type="datetime-local" name="flash_offer_start"
                value="<?php echo esc_attr($start ? str_replace(' ', 'T', substr($start, 0, 16)) : ''); ?>"
                class="wao-admin-input-200">
        </p>

        <p>
            <label
                for="flash_offer_end"><?php esc_html_e('End Date & Time:', 'advanced-offers-for-woocommerce'); ?></label><br>
            <input type="datetime-local" name="flash_offer_end"
                value="<?php echo esc_attr($end ? str_replace(' ', 'T', substr($end, 0, 16)) : ''); ?>"
                class="wao-admin-input-200">
        </p>

        <p>
            <label
                for="flash_offer_discount"><?php esc_html_e('Discount (%):', 'advanced-offers-for-woocommerce'); ?></label><br>
            <input type="number" name="flash_offer_discount" value="<?php echo esc_attr($discount); ?>" min="1" max="100"
                class="wao-admin-input-200">
        </p>
        <p>
            <label
                for="flash_offer_use_offers"><?php esc_html_e('How Many Time User Can Use This Offers:', 'advanced-offers-for-woocommerce'); ?></label><br>
            <input type="number" name="flash_offer_use_offers" value="<?php echo esc_attr($use_offers); ?>" min="1"
                max="100" class="wao-admin-input-200">
        </p>


        <div id="flash_offer_upcoming_offer_fields_product">
            <p>
                <label><?php esc_html_e('Assign to Categories:', 'advanced-offers-for-woocommerce'); ?></label><br>
                <select id="flash_offer_category_selector" name="flash_offer_offer_categories[]" multiple="multiple"
                    class="flash_offer_wc-enhanced-select wao-admin-width-400">
                    <?php foreach ($categories_list as $category): ?>
                        <option value="<?php echo esc_attr($category->term_id); ?>" <?php selected(in_array($category->term_id, $categories)); ?>>
                            <?php echo esc_html($category->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <div id="category-product-preview" class="wao-mt-20">
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
                                <div class="wao-product-img-container">
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

        <div id="selected-product-list" class="wao-mt-30">
            <h4><?php esc_html_e('Selected Products:', 'advanced-offers-for-woocommerce'); ?></h4>
            <div class="selected-product-box wao-flex-wrap-gap-15"></div>
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
            echo '<div class="wao-product-img-container">' . wp_kses_post($product->get_image('thumbnail')) . '</div>';
            echo '<strong class="wao-product-title-small">' . esc_html($product->get_name()) . '</strong>';
            echo '<span class="wao-price-small">' . wp_kses_post($product->get_price_html()) . '</span>';
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

// --- Admin Columns for Flash Offers ---

// 1. Add Columns
add_filter('manage_flash_offer_posts_columns', 'flashoffers_add_admin_columns');
function flashoffers_add_admin_columns($columns)
{
    $new_columns = [];
    $new_columns['cb'] = $columns['cb'];
    $new_columns['title'] = $columns['title'];

    // Add new columns
    $new_columns['offer_type'] = esc_html__('Offer Type', 'advanced-offers-for-woocommerce');
    $new_columns['start_date'] = esc_html__('Start Date', 'advanced-offers-for-woocommerce');
    $new_columns['end_date'] = esc_html__('End Date', 'advanced-offers-for-woocommerce');
    $new_columns['discount'] = esc_html__('Discount', 'advanced-offers-for-woocommerce');

    // Retain Date column if needed, or remove. Usually Date is publish date which is less useful here.
    // User requested "title show ho raha hai uske sath show krna hai" (show with title).
    // Default date column is fine to keep at end.
    $new_columns['date'] = $columns['date'];
    return $new_columns;
}

// 2. Populate Columns
add_action('manage_flash_offer_posts_custom_column', 'flashoffers_populate_admin_columns', 10, 2);
function flashoffers_populate_admin_columns($column, $post_id)
{
    global $wpdb;

    // Fetch data from custom table
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $offer = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}flash_offers WHERE post_id = %d",
        $post_id
    ));

    if (!$offer) {
        if (in_array($column, ['offer_type', 'start_date', 'end_date', 'discount'])) {
            echo '-';
        }
        return;
    }

    switch ($column) {
        case 'offer_type':
            $types = [
                'flash' => esc_html__('Flash Offer', 'advanced-offers-for-woocommerce'),
                'upcoming' => esc_html__('Upcoming Offer', 'advanced-offers-for-woocommerce'),
                'special' => esc_html__('Special Offer', 'advanced-offers-for-woocommerce'),
            ];
            echo esc_html($types[$offer->offer_type] ?? ucfirst($offer->offer_type));
            break;

        case 'start_date':
            // Format logic: If valid date, format it.
            if (!empty($offer->start_date) && $offer->start_date !== '0000-00-00 00:00:00') {
                echo esc_html(get_date_from_gmt($offer->start_date, 'M j, Y g:i a'));
            } else {
                echo '-';
            }
            break;

        case 'end_date':
            if (!empty($offer->end_date) && $offer->end_date !== '0000-00-00 00:00:00') {
                echo esc_html(get_date_from_gmt($offer->end_date, 'M j, Y g:i a'));
            } else {
                echo '-';
            }
            break;

        case 'discount':
            echo esc_html($offer->discount . '%');
            break;
    }
}

// 3. Make Columns Sortable
add_filter('manage_edit-flash_offer_sortable_columns', 'flashoffers_sortable_admin_columns');
function flashoffers_sortable_admin_columns($columns)
{
    $columns['offer_type'] = 'offer_type';
    $columns['start_date'] = 'start_date';
    $columns['end_date'] = 'end_date';
    $columns['discount'] = 'discount';
    return $columns;
}

// 4. Handle Sorting
add_action('pre_get_posts', 'flashoffers_handle_admin_sorting');
function flashoffers_handle_admin_sorting($query)
{
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'flash_offer') {
        return;
    }

    $orderby = $query->get('orderby');
    if (in_array($orderby, ['offer_type', 'start_date', 'end_date', 'discount'])) {
        add_filter('posts_join', 'flashoffers_admin_join_table');
        add_filter('posts_orderby', 'flashoffers_admin_orderby_table');
    }
}

function flashoffers_admin_join_table($join)
{
    global $wpdb;
    // Check if not already joined to avoid errors if triggered multiple times
    if (strpos($join, $wpdb->prefix . 'flash_offers') === false) {
        $join .= " LEFT JOIN {$wpdb->prefix}flash_offers ON {$wpdb->posts}.ID = {$wpdb->prefix}flash_offers.post_id ";
    }
    return $join;
}

function flashoffers_admin_orderby_table($orderby)
{
    global $wpdb, $wp_query;
    $sort_col = $wp_query->get('orderby');
    $order = $wp_query->get('order') ? $wp_query->get('order') : 'ASC';

    // Map standard sort keys to table columns
    $allowed_cols = ['offer_type', 'start_date', 'end_date', 'discount'];

    if (in_array($sort_col, $allowed_cols)) {
        return "{$wpdb->prefix}flash_offers.{$sort_col} {$order}";
    }

    return $orderby;
}

// 5. Handle Search
add_action('pre_get_posts', 'flashoffers_handle_admin_search');
function flashoffers_handle_admin_search($query)
{
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'flash_offer' || !$query->is_search()) {
        return;
    }

    $term = $query->get('s');
    if (empty($term))
        return;

    global $wpdb;

    // 1. Search in Custom Table
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $custom_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->prefix}flash_offers 
         WHERE offer_type LIKE %s 
         OR discount LIKE %s 
         OR start_date LIKE %s 
         OR end_date LIKE %s",
        '%' . $wpdb->esc_like($term) . '%',
        '%' . $wpdb->esc_like($term) . '%',
        '%' . $wpdb->esc_like($term) . '%',
        '%' . $wpdb->esc_like($term) . '%'
    ));

    // 2. Search in Post Title (Default WP behavior, but since we are overriding, we must do it manually)
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $title_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} 
         WHERE post_title LIKE %s 
         AND post_type = 'flash_offer' 
         AND post_status != 'trash'",
        '%' . $wpdb->esc_like($term) . '%'
    ));

    // 3. Merge IDs
    $merged_ids = array_unique(array_merge($custom_ids, $title_ids));

    // If no results, force empty result
    if (empty($merged_ids)) {
        $merged_ids = [0];
    }

    // 4. Modify Query
    $query->set('post__in', $merged_ids);
    $query->set('s', ''); // Clear search term to prevent default WP search from restricting results further
    $query->set('compare', 'IN');
}
