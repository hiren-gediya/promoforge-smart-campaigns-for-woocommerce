<?php
defined('ABSPATH') or die('Direct access not allowed');

// Add meta box to flash offer admin side
add_action('add_meta_boxes', function () {
    add_meta_box(
        'promoforge_offer_details',
        esc_html__('Offer Details', 'promoforge-smart-campaigns-for-woocommerce'),
        'promoforge_offer_details_callback',
        'promoforge_flash',
        'normal',
        'default'
    );
});

// Callback function to display the meta box
function promoforge_offer_details_callback($post)
{
    global $wpdb;

    wp_nonce_field('promoforge_offer_details', 'promoforge_offer_details_nonce');

    // Verify tables exist
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}promoforge_offers'") != $wpdb->prefix . 'promoforge_offers') {
        echo '<div class="error"><p>' . esc_html__('Promoforge Flash Offers tables not found. Please deactivate and reactivate the plugin.', 'promoforge-smart-campaigns-for-woocommerce') . '</p></div>';
        return;
    }

    // Get offer data
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $offer = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}promoforge_offers WHERE post_id = %d",
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
            "SELECT product_id FROM {$wpdb->prefix}promoforge_offer_products WHERE offer_id = %d",
            $offer->id
        ));
    }

    // Get assigned categories
    $categories = [];
    if ($offer) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $categories = $wpdb->get_col($wpdb->prepare(
            "SELECT category_id FROM {$wpdb->prefix}promoforge_offer_categories WHERE offer_id = %d",
            $offer->id
        ));
    }

    // Get all product categories
    $categories_list = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false
    ]);
    ?>

    <div class="promoforge-offer-admin">
        <p>
            <label
                for="offer_type"><?php esc_html_e('Offer Type:', 'promoforge-smart-campaigns-for-woocommerce'); ?></label><br>
            <select name="offer_type" id="offer_type" class="promoforge-admin-select-200">
                <option value="flash" <?php selected($offer_type, 'flash'); ?>>
                    <?php esc_html_e('Flash Offer', 'promoforge-smart-campaigns-for-woocommerce'); ?>
                </option>
                <option value="upcoming" <?php selected($offer_type, 'upcoming'); ?>>
                    <?php esc_html_e('Upcoming Offer', 'promoforge-smart-campaigns-for-woocommerce'); ?>
                </option>
                <option value="special" <?php selected($offer_type, 'special'); ?>>
                    <?php esc_html_e('Special Offer', 'promoforge-smart-campaigns-for-woocommerce'); ?>
                </option>
            </select>
        </p>

        <p
            class="promoforge_offer_start_fields <?php echo ($offer_type === 'flash') ? 'promoforge-display-none' : 'promoforge-display-block'; ?>">
            <label
                for="promoforge_offer_start"><?php esc_html_e('Start Date & Time:', 'promoforge-smart-campaigns-for-woocommerce'); ?></label><br>
            <input type="datetime-local" name="promoforge_offer_start"
                value="<?php echo esc_attr($start ? str_replace(' ', 'T', substr($start, 0, 16)) : ''); ?>"
                class="promoforge-admin-input-200">
        </p>

        <p>
            <label
                for="promoforge_offer_end"><?php esc_html_e('End Date & Time:', 'promoforge-smart-campaigns-for-woocommerce'); ?></label><br>
            <input type="datetime-local" name="promoforge_offer_end"
                value="<?php echo esc_attr($end ? str_replace(' ', 'T', substr($end, 0, 16)) : ''); ?>"
                class="promoforge-admin-input-200">
        </p>

        <p>
            <label
                for="promoforge_offer_discount"><?php esc_html_e('Discount (%):', 'promoforge-smart-campaigns-for-woocommerce'); ?></label><br>
            <input type="number" name="promoforge_offer_discount" value="<?php echo esc_attr($discount); ?>" min="1"
                max="100" class="promoforge-admin-input-200">
        </p>
        <p>
            <label
                for="promoforge_offer_use_offers"><?php esc_html_e('How Many Time User Can Use This Offers:', 'promoforge-smart-campaigns-for-woocommerce'); ?></label><br>
            <input type="number" name="promoforge_offer_use_offers" value="<?php echo esc_attr($use_offers); ?>" min="1"
                max="100" class="promoforge-admin-input-200">
        </p>


        <div id="promoforge_offer_upcoming_offer_fields_product">
            <p>
                <label><?php esc_html_e('Assign to Categories:', 'promoforge-smart-campaigns-for-woocommerce'); ?></label><br>
                <select id="promoforge_offer_category_selector" name="promoforge_offer_offer_categories[]"
                    multiple="multiple" class="promoforge_offer_wc-enhanced-select promoforge-admin-width-400">
                    <?php foreach ($categories_list as $category): ?>
                        <option value="<?php echo esc_attr($category->term_id); ?>" <?php selected(in_array($category->term_id, $categories)); ?>>
                            <?php echo esc_html($category->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <div id="category-product-preview" class="promoforge-mt-20">
                <?php if (!empty($products)): ?>
                    <div class="promoforge-product-select-list">
                        <?php foreach ($products as $product_id):
                            $product = wc_get_product($product_id);
                            if (!$product)
                                continue;
                            ?>
                            <label class="promoforge-product-box">
                                <input type="checkbox" name="offer_products[]" value="<?php echo esc_attr($product_id); ?>"
                                    checked />
                                <div class="promoforge-product-img-container">
                                    <?php echo wp_kses_post($product->get_image('thumbnail')); ?>
                                </div>
                                <strong><?php echo esc_html($product->get_name()); ?></strong>
                                <span><?php echo wp_kses_post($product->get_price_html()); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p><?php esc_html_e('Select categories to see products', 'promoforge-smart-campaigns-for-woocommerce'); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div id="promoforge-selected-product-list" class="promoforge-mt-30">
            <h4><?php esc_html_e('Selected Products:', 'promoforge-smart-campaigns-for-woocommerce'); ?></h4>
            <div class="selected-promoforge-product-box promoforge-flex-wrap-gap-15"></div>
        </div>

        <div id="hidden-offer-products"></div>
    </div>

    <?php
}

// Save offer details in database
add_action('save_post_promoforge_flash', function ($post_id) {
    global $wpdb;

    if (
        !isset($_POST['promoforge_offer_details_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['promoforge_offer_details_nonce'])), 'promoforge_offer_details')
    ) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return;
    if (!current_user_can('edit_post', $post_id))
        return;

    // Prevent duplicate saves with transient lock
    $lock_key = 'promoforge_offer_saving_' . $post_id;
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
        'start_date' => !empty($_POST['promoforge_offer_start']) ? str_replace('T', ' ', sanitize_text_field(wp_unslash($_POST['promoforge_offer_start']))) : current_time('mysql'),
        'end_date' => str_replace('T', ' ', sanitize_text_field(wp_unslash($_POST['promoforge_offer_end'] ?? ''))),
        'discount' => floatval($_POST['promoforge_offer_discount'] ?? 0),
        'use_offers' => intval($_POST['promoforge_offer_use_offers'] ?? 0)
    ];

    // Check if offer exists
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $offer = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}promoforge_offers WHERE post_id = %d",
        $post_id
    ));

    if ($offer) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $wpdb->prefix . 'promoforge_offers',
            $offer_data,
            ['id' => $offer->id],
            ['%d', '%s', '%s', '%s', '%f', '%d'],
            ['%d']
        );
        $offer_id = $offer->id;
    } else {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->insert(
            $wpdb->prefix . 'promoforge_offers',
            $offer_data,
            ['%d', '%s', '%s', '%s', '%f', '%d']
        );
        $offer_id = $wpdb->insert_id;
    }

    // Ensure table migration is complete before saving
    promoforge_migrate_categories_table();

    // Get current products for this offer
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $current_products = $wpdb->get_col($wpdb->prepare(
        "SELECT product_id FROM {$wpdb->prefix}promoforge_offer_products WHERE offer_id = %d",
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
        $query = "DELETE FROM {$wpdb->prefix}promoforge_offer_products WHERE offer_id = %d AND product_id IN ($placeholders)";
        $params = array_merge([$offer_id], $products_to_remove);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query($wpdb->prepare($query, $params));

        // Also remove from categories table
        $query_cat = "DELETE FROM {$wpdb->prefix}promoforge_offer_categories WHERE offer_id = %d AND product_id IN ($placeholders)";
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
                    "INSERT IGNORE INTO {$wpdb->prefix}promoforge_offer_products
                    (offer_id, product_id)
                    VALUES (%d, %d)",
                    $offer_id,
                    $product_id
                ));

                // Get primary category for this product and insert into categories table
                $primary_category_id = promoforge_get_product_primary_category($product_id);
                if ($primary_category_id > 0) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->query($wpdb->prepare(
                        "INSERT IGNORE INTO {$wpdb->prefix}promoforge_offer_categories
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
add_action('add_meta_boxes_promoforge_flash', 'promoforge_add_conditional_shortcode_meta_box');
function promoforge_add_conditional_shortcode_meta_box($post)
{
    global $wpdb;

    // Get current post ID
    $post_id = $post->ID;

    // Query the custom table for the current post only
    // Query the custom table for the current post only
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $offer_type = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT offer_type FROM {$wpdb->prefix}promoforge_offers WHERE post_id = %d AND offer_type = %s",
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
        'promoforge_display_special_offer_shortcode',
        'promoforge_flash',
        'side',
        'high'
    );
}

// Display shortcode for special offer at edit post
function promoforge_display_special_offer_shortcode($post)
{
    $shortcode = '[promoforge_special_offer id=' . $post->ID . ']';
    ?>
    <p><?php esc_html_e('Use this shortcode to display this special offer:', 'promoforge-smart-campaigns-for-woocommerce'); ?>
    </p>
    <input type="text" value="<?php echo esc_attr($shortcode); ?>" readonly class="widefat" id="special-offer-shortcode">
    <button type="button" class="button button-primary copy-shortcode"
        data-clipboard-text="<?php echo esc_attr($shortcode); ?>"><?php esc_html_e('Copy', 'promoforge-smart-campaigns-for-woocommerce'); ?></button>

    <?php
}


// AJAX handler for getting products by categories
add_action('wp_ajax_promoforge_get_products_by_categories', 'promoforge_get_products_by_categories');

function promoforge_get_products_by_categories()
{
    global $wpdb;

    if (!current_user_can('edit_posts')) {
        wp_die();
    }

    check_ajax_referer('promoforge_offer_details', 'nonce'); // Make sure JS sends this

    $categories = isset($_POST['categories']) ? array_map('intval', $_POST['categories']) : [];
    $current_post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

    if (empty($categories)) {
        echo '<p>' . esc_html__('Please select at least one category.', 'promoforge-smart-campaigns-for-woocommerce') . '</p>';
        wp_die();
    }

    // 1. Get products already selected in current offer
    $current_offer_products = [];
    if ($current_post_id) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $offer_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}promoforge_offers WHERE post_id = %d",
            $current_post_id
        ));

        if ($offer_id) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $current_offer_products = $wpdb->get_col($wpdb->prepare(
                "SELECT product_id FROM {$wpdb->prefix}promoforge_offer_products WHERE offer_id = %d",
                $offer_id
            ));
        }
    }

    // 2. Get products used in other flash offers
    $excluded_product_ids = [];
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $other_offers = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT DISTINCT product_id FROM {$wpdb->prefix}promoforge_offer_products 
             WHERE offer_id IN (
                 SELECT id FROM {$wpdb->prefix}promoforge_offers 
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
    $bogo_products = $wpdb->get_col("SELECT buy_product_id FROM {$wpdb->prefix}promoforge_bogo_offers WHERE buy_product_id > 0");
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $bogo_products2 = $wpdb->get_col("SELECT get_product_id FROM {$wpdb->prefix}promoforge_bogo_offers WHERE get_product_id > 0");

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
        echo '<div class="promoforge-product-select-list">';

        while ($products_query->have_posts()) {
            $products_query->the_post();
            $product = wc_get_product(get_the_ID());
            if (!$product)
                continue;

            $product_id = $product->get_id();
            $checked = in_array($product_id, $current_offer_products) ? 'checked' : '';

            echo '<label class="promoforge-product-box">';
            echo '<input type="checkbox" name="offer_products[]" value="' . esc_attr($product_id) . '" ' . esc_attr($checked) . ' />';
            echo '<div class="promoforge-product-img-container">' . wp_kses_post($product->get_image('thumbnail')) . '</div>';
            echo '<strong class="promoforge-product-title-small">' . esc_html($product->get_name()) . '</strong>';
            echo '<span class="promoforge-price-small">' . wp_kses_post($product->get_price_html()) . '</span>';
            echo '</label>';
        }

        echo '</div>';
    } else {
        echo '<p>' . esc_html__('No products found in selected categories.', 'promoforge-smart-campaigns-for-woocommerce') . '</p>';
    }

    wp_reset_postdata();
    wp_die();
}

// Hook to delete flash offer data when post is deleted
add_action('before_delete_post', function ($post_id) {
    global $wpdb;

    // Check if this is a promoforge_offer post type
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'promoforge_flash') {
        return;
    }

    // Get the offer ID from the promoforge_offers table
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $offer = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}promoforge_offers WHERE post_id = %d",
        $post_id
    ));

    if ($offer) {
        $offer_id = $offer->id;

        // Delete from promoforge_offer_categories table
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete(
            $wpdb->prefix . 'promoforge_offer_categories',
            ['offer_id' => $offer_id],
            ['%d']
        );

        // Delete from promoforge_offer_products table
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete(
            $wpdb->prefix . 'promoforge_offer_products',
            ['offer_id' => $offer_id],
            ['%d']
        );

        // Delete from promoforge_offers table
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete(
            $wpdb->prefix . 'promoforge_offers',
            ['post_id' => $post_id],
            ['%d']
        );
    }
});

// --- Admin Columns for Flash Offers ---

// 1. Add Columns
add_filter('manage_promoforge_flash_posts_columns', 'promoforge_add_admin_columns');
function promoforge_add_admin_columns($columns)
{
    $new_columns = [];
    $new_columns['cb'] = $columns['cb'];
    $new_columns['title'] = $columns['title'];

    // Add new columns
    $new_columns['offer_type'] = esc_html__('Offer Type', 'promoforge-smart-campaigns-for-woocommerce');
    $new_columns['start_date'] = esc_html__('Start Date', 'promoforge-smart-campaigns-for-woocommerce');
    $new_columns['end_date'] = esc_html__('End Date', 'promoforge-smart-campaigns-for-woocommerce');
    $new_columns['discount'] = esc_html__('Discount', 'promoforge-smart-campaigns-for-woocommerce');

    // Retain Date column if needed, or remove. Usually Date is publish date which is less useful here.
    // User requested "title show ho raha hai uske sath show krna hai" (show with title).
    // Default date column is fine to keep at end.
    $new_columns['date'] = $columns['date'];
    return $new_columns;
}

// 2. Populate Columns
add_action('manage_promoforge_flash_posts_custom_column', 'promoforge_populate_admin_columns', 10, 2);
function promoforge_populate_admin_columns($column, $post_id)
{
    global $wpdb;

    // Fetch data from custom table
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $offer = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}promoforge_offers WHERE post_id = %d",
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
                'flash' => esc_html__('Flash Offer', 'promoforge-smart-campaigns-for-woocommerce'),
                'upcoming' => esc_html__('Upcoming Offer', 'promoforge-smart-campaigns-for-woocommerce'),
                'special' => esc_html__('Special Offer', 'promoforge-smart-campaigns-for-woocommerce'),
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
add_filter('manage_edit-promoforge_flash_sortable_columns', 'promoforge_sortable_admin_columns');
function promoforge_sortable_admin_columns($columns)
{
    $columns['offer_type'] = 'offer_type';
    $columns['start_date'] = 'start_date';
    $columns['end_date'] = 'end_date';
    $columns['discount'] = 'discount';
    return $columns;
}

// 4. Handle Sorting
add_action('pre_get_posts', 'promoforge_handle_admin_sorting');
function promoforge_handle_admin_sorting($query)
{
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'promoforge_flash') {
        return;
    }

    $orderby = $query->get('orderby');
    if (in_array($orderby, ['offer_type', 'start_date', 'end_date', 'discount'])) {
        add_filter('posts_join', 'promoforge_admin_join_table');
        add_filter('posts_orderby', 'promoforge_admin_orderby_table');
    }
}

function promoforge_admin_join_table($join)
{
    global $wpdb;
    // Check if not already joined to avoid errors if triggered multiple times
    if (strpos($join, $wpdb->prefix . 'promoforge_offers') === false) {
        $join .= " LEFT JOIN {$wpdb->prefix}promoforge_offers ON {$wpdb->posts}.ID = {$wpdb->prefix}promoforge_offers.post_id ";
    }
    return $join;
}

function promoforge_admin_orderby_table($orderby)
{
    global $wpdb, $wp_query;
    $sort_col = $wp_query->get('orderby');
    $order = $wp_query->get('order') ? $wp_query->get('order') : 'ASC';

    // Map standard sort keys to table columns
    $allowed_cols = ['offer_type', 'start_date', 'end_date', 'discount'];

    if (in_array($sort_col, $allowed_cols)) {
        return "{$wpdb->prefix}promoforge_offers.{$sort_col} {$order}";
    }

    return $orderby;
}

// 5. Handle Search
add_action('pre_get_posts', 'promoforge_handle_admin_search');
function promoforge_handle_admin_search($query)
{
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'promoforge_flash' || !$query->is_search()) {
        return;
    }

    $term = $query->get('s');
    if (empty($term))
        return;

    global $wpdb;

    // 1. Search in Custom Table
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $custom_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->prefix}promoforge_offers 
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
         AND post_type = 'promoforge_flash' 
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
    // $query->set('s', ''); // We keep 's' for UI, but suppress its SQL effect via filter
    add_filter('posts_search', 'promoforge_suppress_default_search', 10, 2);
}

function promoforge_suppress_default_search($search, $query)
{
    if (!is_admin() || !$query->is_main_query() || !$query->is_search()) {
        return $search;
    }

    $post_type = $query->get('post_type');
    if ($post_type === 'promoforge_flash' || $post_type === 'promoforge_bogo') {
        if ($query->get('post__in')) {
            return ''; // Return empty to disable default title/content LIKE clauses
        }
    }

    return $search;
}

// 6. Add Filter Dropdown
add_action('restrict_manage_posts', 'promoforge_render_offer_type_filter');
function promoforge_render_offer_type_filter($post_type)
{
    if ($post_type !== 'promoforge_flash')
        return;

    $options = [
        'flash' => esc_html__('Flash Offer', 'promoforge-smart-campaigns-for-woocommerce'),
        'upcoming' => esc_html__('Upcoming Offer', 'promoforge-smart-campaigns-for-woocommerce'),
        'special' => esc_html__('Special Offer', 'promoforge-smart-campaigns-for-woocommerce'),
    ];

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
    $current = isset($_GET['filter_offer_type']) ? sanitize_text_field(wp_unslash($_GET['filter_offer_type'])) : '';

    echo '<select name="filter_offer_type" id="filter_offer_type">';
    echo '<option value="">' . esc_html__('All Offer Types', 'promoforge-smart-campaigns-for-woocommerce') . '</option>';
    foreach ($options as $key => $label) {
        printf(
            '<option value="%s" %s>%s</option>',
            esc_attr($key),
            selected($current, $key, false),
            esc_html($label)
        );
    }
    echo '</select>';
}

// 7. Handle Filter Logic
add_action('pre_get_posts', 'promoforge_handle_admin_filter');
function promoforge_handle_admin_filter($query)
{
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'promoforge_flash') {
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
    if (!empty($_GET['filter_offer_type'])) {
        global $wpdb;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $type = sanitize_text_field(wp_unslash($_GET['filter_offer_type']));

        // Ensure table is joined
        add_filter('posts_join', 'promoforge_admin_join_table');

        // Add where clause
        add_filter('posts_where', function ($where) use ($type, $wpdb) {
            $where .= $wpdb->prepare(" AND {$wpdb->prefix}promoforge_offers.offer_type = %s", $type);
            return $where;
        });
    }
}
