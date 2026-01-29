<?php
if (!defined('ABSPATH')) {
    exit;
}

// Add meta box for selecting products in BOGO Offers
add_action('add_meta_boxes', function () {
    add_meta_box(
        'bogo_products_box',
        esc_html__('BOGO Offer Configuration', 'advanced-offers-for-woocommerce'),
        'flashoffers_bogo_products_box_callback',
        'bogo_offer',
        'normal',
        'default'
    );
});

// Callback to render the meta box content
function flashoffers_bogo_products_box_callback($post)
{
    global $wpdb;

    // Current BOGO post
    $post_id = $post->ID;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $bogo_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}bogo_offers WHERE post_id = %d",
        $post_id
    ), ARRAY_A);

    if (!$bogo_data) {
        $bogo_data = [];
    }

    $offer_type = $bogo_data['offer_type'] ?? 'buy_x_get_y';
    $buy_product_id = $bogo_data['buy_product_id'] ?? '';
    $get_product_id = $bogo_data['get_product_id'] ?? '';
    $discount = $bogo_data['discount'] ?? '';
    $start_date = $bogo_data['start_date'] ?? '';
    $end_date = $bogo_data['end_date'] ?? '';
    $buy_quantity = $bogo_data['buy_quantity'] ?? 1;
    $get_quantity = $bogo_data['get_quantity'] ?? 1;

    // 1. Get all Flash Offer IDs (custom table: flash_offers)
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $flash_offer_ids = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}flash_offers");

    $flash_product_ids = [];
    if (!empty($flash_offer_ids)) {
        // 2. Get all product_ids linked to those flash offers
        $placeholders = implode(',', array_fill(0, count($flash_offer_ids), '%d'));

        // Prepare query safely
        $query = "SELECT product_id FROM {$wpdb->prefix}flash_offer_products WHERE offer_id IN ($placeholders)";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $flash_product_ids = $wpdb->get_col($wpdb->prepare($query, $flash_offer_ids));
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $bogo_products = $wpdb->get_col("SELECT buy_product_id FROM {$wpdb->prefix}bogo_offers WHERE buy_product_id > 0");
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $bogo_products2 = $wpdb->get_col("SELECT get_product_id FROM {$wpdb->prefix}bogo_offers WHERE get_product_id > 0");

    $bogo_product_ids = array_merge($bogo_products, $bogo_products2);

    /**
     * 🔹 3. Build exclusion list
     */
    $exclude_ids = array_merge($flash_product_ids, $bogo_product_ids);

    // Keep current post's own buy/get IDs allowed (so you can edit them)
    $exclude_ids = array_diff($exclude_ids, [$buy_product_id, $get_product_id]);

    // 3. Query WooCommerce products, excluding Flash Offer products
    $args = [
        'post_type' => 'product',
        'posts_per_page' => -1,
        // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
        'post__not_in' => $exclude_ids,
    ];

    $products = get_posts($args);

    wp_nonce_field('bogo_offer_details', 'bogo_offer_details_nonce');
    ?>

    <div class="bogo-offer-configuration">
        <p>
            <label
                for="bogo_offer_type"><strong><?php esc_html_e('BOGO Offer Type:', 'advanced-offers-for-woocommerce'); ?></strong></label><br>
            <select name="bogo_offer_type" id="bogo_offer_type" class="wao-admin-select-300">
                <option value="buy_x_get_y" <?php selected($offer_type, 'buy_x_get_y'); ?>>
                    <?php esc_html_e('Buy X Get Y (Different Products)', 'advanced-offers-for-woocommerce'); ?>
                </option>
                <option value="buy_one_get_one" <?php selected($offer_type, 'buy_one_get_one'); ?>>
                    <?php esc_html_e('Buy One Get One (Same Product)', 'advanced-offers-for-woocommerce'); ?>
                </option>
            </select>
        </p>

        <div id="buy_x_get_y_fields"
            class="bogo-type-fields <?php echo ($offer_type === 'buy_one_get_one') ? 'wao-display-none' : 'wao-display-block'; ?>">
            <p>
                <label
                    for="bogo_buy_product"><strong><?php esc_html_e('Buy Product:', 'advanced-offers-for-woocommerce'); ?></strong></label><br>
                <select name="bogo_buy_product" class="wao-width-full">
                    <option value=""><?php esc_html_e('Select Buy Product', 'advanced-offers-for-woocommerce'); ?></option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo esc_attr($product->ID); ?>" <?php selected($buy_product_id, $product->ID); ?>>
                            <?php echo esc_html($product->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <label
                    for="bogo_buy_quantity"><strong><?php esc_html_e('Buy Quantity:', 'advanced-offers-for-woocommerce'); ?></strong></label><br>
                <input type="number" name="bogo_buy_quantity" value="<?php echo esc_attr($buy_quantity); ?>" min="1"
                    class="wao-admin-input-200" />
            </p>

            <p>
                <label
                    for="bogo_get_product"><strong><?php esc_html_e('Get Product:', 'advanced-offers-for-woocommerce'); ?></strong></label><br>
                <select name="bogo_get_product" class="wao-width-full">
                    <option value=""><?php esc_html_e('Select Get Product', 'advanced-offers-for-woocommerce'); ?></option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo esc_attr($product->ID); ?>" <?php selected($get_product_id, $product->ID); ?>>
                            <?php echo esc_html($product->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <label
                    for="bogo_get_quantity"><strong><?php esc_html_e('Get Quantity:', 'advanced-offers-for-woocommerce'); ?></strong></label><br>
                <input type="number" name="bogo_get_quantity" value="<?php echo esc_attr($get_quantity); ?>" min="1"
                    class="wao-admin-input-200" />
            </p>

            <p>
                <label
                    for="bogo_discount"><strong><?php esc_html_e('Discount (%):', 'advanced-offers-for-woocommerce'); ?></strong></label><br>
                <input type="number" name="bogo_discount" value="<?php echo esc_attr($discount); ?>" min="0" max="100"
                    class="wao-admin-input-200" />
                <em><?php esc_html_e('Set to 100 for free product, 0 for no discount', 'advanced-offers-for-woocommerce'); ?></em>
            </p>
        </div>

        <div id="buy_one_get_one_fields"
            class="bogo-type-fields <?php echo ($offer_type === 'buy_one_get_one') ? 'wao-display-block' : 'wao-display-none'; ?>">
            <p>
                <label
                    for="bogo_bogo_product"><strong><?php esc_html_e('Product:', 'advanced-offers-for-woocommerce'); ?></strong></label><br>
                <select name="bogo_bogo_product" class="wao-admin-select-200">
                    <option value=""><?php esc_html_e('Select Product', 'advanced-offers-for-woocommerce'); ?></option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo esc_attr($product->ID); ?>" <?php selected($buy_product_id, $product->ID); ?>>
                            <?php echo esc_html($product->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <em><?php esc_html_e('For "Buy One Get One" offers, the system will automatically add 2 products to cart when this product is purchased.', 'advanced-offers-for-woocommerce'); ?></em>
            </p>
        </div>

        <p>
            <label
                for="bogo_start_date"><strong><?php esc_html_e('Start Date:', 'advanced-offers-for-woocommerce'); ?></strong></label><br>
            <input type="datetime-local" name="bogo_start_date" value="<?php echo esc_attr($start_date); ?>"
                class="wao-admin-input-200" />
        </p>

        <p>
            <label
                for="bogo_end_date"><strong><?php esc_html_e('End Date:', 'advanced-offers-for-woocommerce'); ?></strong></label><br>
            <input type="datetime-local" name="bogo_end_date" value="<?php echo esc_attr($end_date); ?>"
                class="wao-admin-input-200" />
        </p>
    </div>


    <?php
}

// Hook to save BOGO offer data when post is saved
add_action('save_post_bogo_offer', function ($post_id) {
    global $wpdb;

    if (
        !isset($_POST['bogo_offer_details_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bogo_offer_details_nonce'])), 'bogo_offer_details')
    ) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return;
    if (!current_user_can('edit_post', $post_id))
        return;

    // Prevent duplicate saves
    $lock_key = 'bogo_saving_' . $post_id;
    if (get_transient($lock_key))
        return;
    set_transient($lock_key, true, 10);

    // Prepare BOGO offer data
    $offer_type = sanitize_text_field(wp_unslash($_POST['bogo_offer_type'] ?? 'buy_x_get_y'));

    if ($offer_type === 'buy_one_get_one') {
        $buy_product_id = intval($_POST['bogo_bogo_product'] ?? 0);
        $get_product_id = $buy_product_id; // Same product
        $buy_quantity = 1;
        $get_quantity = 1;
        $discount = 100; // Always free for BOGO same product
    } else {
        $buy_product_id = intval($_POST['bogo_buy_product'] ?? 0);
        $get_product_id = intval($_POST['bogo_get_product'] ?? 0);
        $buy_quantity = intval($_POST['bogo_buy_quantity'] ?? 1);
        $get_quantity = intval($_POST['bogo_get_quantity'] ?? 1);
        $discount = floatval($_POST['bogo_discount'] ?? 0);
    }

    $bogo_data = [
        'post_id' => $post_id,
        'offer_type' => $offer_type,
        'buy_product_id' => $buy_product_id,
        'get_product_id' => $get_product_id,
        'buy_quantity' => $buy_quantity,
        'get_quantity' => $get_quantity,
        'discount' => $discount,
        'start_date' => sanitize_text_field(wp_unslash($_POST['bogo_start_date'] ?? '')),
        'end_date' => sanitize_text_field(wp_unslash($_POST['bogo_end_date'] ?? ''))
    ];

    // Check if offer exists
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}bogo_offers WHERE post_id = %d",
        $post_id
    ));

    if ($existing) {
        // Update existing BOGO offer
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $wpdb->prefix . 'bogo_offers',
            $bogo_data,
            ['post_id' => $post_id],
            ['%d', '%s', '%d', '%d', '%d', '%d', '%f', '%s', '%s'],
            ['%d']
        );
    } else {
        // Insert new BOGO offer
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->insert(
            $wpdb->prefix . 'bogo_offers',
            $bogo_data,
            ['%d', '%s', '%d', '%d', '%d', '%d', '%f', '%s', '%s']
        );
    }

    // Clear lock
    delete_transient($lock_key);
});

// Hook to delete BOGO offer data when post is deleted
add_action('before_delete_post', function ($post_id) {
    global $wpdb;

    // Check if this is a bogo_offer post type
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'bogo_offer') {
        return;
    }

    // Delete from bogo_offers table
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->delete(
        $wpdb->prefix . 'bogo_offers',
        ['post_id' => $post_id],
        ['%d']
    );
});
