<?php
if (!defined('ABSPATH')) {
    exit;
}

// Add meta box for selecting products in BOGO Offers
add_action('add_meta_boxes', function () {
    add_meta_box(
        'promoforge_bogo_products_box',
        esc_html__('BOGO Offer Configuration', 'promoforge-smart-campaigns-for-woocommerce'),
        'promoforge_bogo_products_box_callback',
        'promoforge_bogo',
        'normal',
        'default'
    );
});

// Callback to render the meta box content
function promoforge_bogo_products_box_callback($post)
{
    global $wpdb;

    // Current BOGO post
    $post_id = $post->ID;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $bogo_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}promoforge_bogo_offers WHERE post_id = %d",
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
    $flash_offer_ids = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}promoforge_flash_offers");

    $flash_product_ids = [];
    if (!empty($flash_offer_ids)) {
        // 2. Get all product_ids linked to those flash offers
        $placeholders = implode(',', array_fill(0, count($flash_offer_ids), '%d'));

        // Prepare query safely
        $query = "SELECT product_id FROM {$wpdb->prefix}promoforge_offer_products WHERE offer_id IN ($placeholders)";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $flash_product_ids = $wpdb->get_col($wpdb->prepare($query, $flash_offer_ids));
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $bogo_products = $wpdb->get_col("SELECT buy_product_id FROM {$wpdb->prefix}promoforge_bogo_offers WHERE buy_product_id > 0");
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $bogo_products2 = $wpdb->get_col("SELECT get_product_id FROM {$wpdb->prefix}promoforge_bogo_offers WHERE get_product_id > 0");

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

    wp_nonce_field('promoforge_bogo_details', 'promoforge_bogo_details_nonce');
    ?>

    <div class="bogo-offer-configuration">
        <p>
            <label
                for="bogo_offer_type"><strong><?php esc_html_e('BOGO Offer Type:', 'promoforge-smart-campaigns-for-woocommerce'); ?></strong></label><br>
            <select name="bogo_offer_type" id="bogo_offer_type" class="wao-admin-select-300">
                <option value="buy_x_get_y" <?php selected($offer_type, 'buy_x_get_y'); ?>>
                    <?php esc_html_e('Buy X Get Y (Different Products)', 'promoforge-smart-campaigns-for-woocommerce'); ?>
                </option>
                <option value="buy_one_get_one" <?php selected($offer_type, 'buy_one_get_one'); ?>>
                    <?php esc_html_e('Buy One Get One (Same Product)', 'promoforge-smart-campaigns-for-woocommerce'); ?>
                </option>
            </select>
        </p>

        <div id="buy_x_get_y_fields"
            class="bogo-type-fields <?php echo ($offer_type === 'buy_one_get_one') ? 'wao-display-none' : 'wao-display-block'; ?>">
            <p>
                <label
                    for="bogo_buy_product"><strong><?php esc_html_e('Buy Product:', 'promoforge-smart-campaigns-for-woocommerce'); ?></strong></label><br>
                <select name="bogo_buy_product" class="wao-width-full">
                    <option value="">
                        <?php esc_html_e('Select Buy Product', 'promoforge-smart-campaigns-for-woocommerce'); ?>
                    </option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo esc_attr($product->ID); ?>" <?php selected($buy_product_id, $product->ID); ?>>
                            <?php echo esc_html($product->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <label
                    for="bogo_buy_quantity"><strong><?php esc_html_e('Buy Quantity:', 'promoforge-smart-campaigns-for-woocommerce'); ?></strong></label><br>
                <input type="number" name="bogo_buy_quantity" value="<?php echo esc_attr($buy_quantity); ?>" min="1"
                    class="wao-admin-input-200" />
            </p>

            <p>
                <label
                    for="bogo_get_product"><strong><?php esc_html_e('Get Product:', 'promoforge-smart-campaigns-for-woocommerce'); ?></strong></label><br>
                <select name="bogo_get_product" class="wao-width-full">
                    <option value="">
                        <?php esc_html_e('Select Get Product', 'promoforge-smart-campaigns-for-woocommerce'); ?>
                    </option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo esc_attr($product->ID); ?>" <?php selected($get_product_id, $product->ID); ?>>
                            <?php echo esc_html($product->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <label
                    for="bogo_get_quantity"><strong><?php esc_html_e('Get Quantity:', 'promoforge-smart-campaigns-for-woocommerce'); ?></strong></label><br>
                <input type="number" name="bogo_get_quantity" value="<?php echo esc_attr($get_quantity); ?>" min="1"
                    class="wao-admin-input-200" />
            </p>

            <p>
                <label
                    for="bogo_discount"><strong><?php esc_html_e('Discount (%):', 'promoforge-smart-campaigns-for-woocommerce'); ?></strong></label><br>
                <input type="number" name="bogo_discount" value="<?php echo esc_attr($discount); ?>" min="0" max="100"
                    class="wao-admin-input-200" />
                <em><?php esc_html_e('Set to 100 for free product, 0 for no discount', 'promoforge-smart-campaigns-for-woocommerce'); ?></em>
            </p>
        </div>

        <div id="buy_one_get_one_fields"
            class="bogo-type-fields <?php echo ($offer_type === 'buy_one_get_one') ? 'wao-display-block' : 'wao-display-none'; ?>">
            <p>
                <label
                    for="bogo_bogo_product"><strong><?php esc_html_e('Product:', 'promoforge-smart-campaigns-for-woocommerce'); ?></strong></label><br>
                <select name="bogo_bogo_product" class="wao-admin-select-200">
                    <option value=""><?php esc_html_e('Select Product', 'promoforge-smart-campaigns-for-woocommerce'); ?>
                    </option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo esc_attr($product->ID); ?>" <?php selected($buy_product_id, $product->ID); ?>>
                            <?php echo esc_html($product->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <em><?php esc_html_e('For "Buy One Get One" offers, the system will automatically add 2 products to cart when this product is purchased.', 'promoforge-smart-campaigns-for-woocommerce'); ?></em>
            </p>
        </div>

        <p>
            <label
                for="bogo_start_date"><strong><?php esc_html_e('Start Date:', 'promoforge-smart-campaigns-for-woocommerce'); ?></strong></label><br>
            <input type="datetime-local" name="bogo_start_date" value="<?php echo esc_attr($start_date); ?>"
                class="wao-admin-input-200" />
        </p>

        <p>
            <label
                for="bogo_end_date"><strong><?php esc_html_e('End Date:', 'promoforge-smart-campaigns-for-woocommerce'); ?></strong></label><br>
            <input type="datetime-local" name="bogo_end_date" value="<?php echo esc_attr($end_date); ?>"
                class="wao-admin-input-200" />
        </p>
    </div>


    <?php
}

// Hook to save BOGO offer data when post is saved
add_action('save_post_promoforge_bogo', function ($post_id) {
    global $wpdb;

    if (
        !isset($_POST['promoforge_bogo_details_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['promoforge_bogo_details_nonce'])), 'promoforge_bogo_details')
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
        'start_date' => str_replace('T', ' ', sanitize_text_field(wp_unslash($_POST['bogo_start_date'] ?? ''))),
        'end_date' => str_replace('T', ' ', sanitize_text_field(wp_unslash($_POST['bogo_end_date'] ?? '')))
    ];

    // Check if offer exists
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}promoforge_bogo_offers WHERE post_id = %d",
        $post_id
    ));

    if ($existing) {
        // Update existing BOGO offer
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $wpdb->prefix . 'promoforge_bogo_offers',
            $bogo_data,
            ['post_id' => $post_id],
            ['%d', '%s', '%d', '%d', '%d', '%d', '%f', '%s', '%s'],
            ['%d']
        );
    } else {
        // Insert new BOGO offer
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->insert(
            $wpdb->prefix . 'promoforge_bogo_offers',
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
    if (!$post || $post->post_type !== 'promoforge_bogo') {
        return;
    }

    // Delete from bogo_offers table
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->delete(
        $wpdb->prefix . 'promoforge_bogo_offers',
        ['post_id' => $post_id],
        ['%d']
    );
});

// --- Admin Columns for BOGO Offers ---

// 1. Add Columns
add_filter('manage_bogo_offer_posts_columns', 'promoforge_add_bogo_admin_columns');
function promoforge_add_bogo_admin_columns($columns)
{
    $new_columns = [];
    $new_columns['cb'] = $columns['cb'];
    $new_columns['title'] = $columns['title'];

    // Add new columns
    $new_columns['offer_type'] = esc_html__('Offer Type', 'promoforge-smart-campaigns-for-woocommerce');
    $new_columns['buy_product'] = esc_html__('Buy Product', 'promoforge-smart-campaigns-for-woocommerce');
    $new_columns['get_product'] = esc_html__('Get Product', 'promoforge-smart-campaigns-for-woocommerce');
    $new_columns['start_date'] = esc_html__('Start Date', 'promoforge-smart-campaigns-for-woocommerce');
    $new_columns['end_date'] = esc_html__('End Date', 'promoforge-smart-campaigns-for-woocommerce');
    $new_columns['discount'] = esc_html__('Discount', 'promoforge-smart-campaigns-for-woocommerce');

    $new_columns['date'] = $columns['date'];
    return $new_columns;
}

// 2. Populate Columns
add_action('manage_bogo_offer_posts_custom_column', 'promoforge_populate_bogo_admin_columns', 10, 2);
function promoforge_populate_bogo_admin_columns($column, $post_id)
{
    global $wpdb;

    // Fetch data from custom table
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $offer = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}promoforge_bogo_offers WHERE post_id = %d",
        $post_id
    ));

    if (!$offer) {
        if (in_array($column, ['offer_type', 'buy_product', 'get_product', 'start_date', 'end_date', 'discount'])) {
            echo '-';
        }
        return;
    }

    switch ($column) {
        case 'offer_type':
            $types = [
                'buy_x_get_y' => esc_html__('Buy X Get Y', 'promoforge-smart-campaigns-for-woocommerce'),
                'buy_one_get_one' => esc_html__('Buy One Get One', 'promoforge-smart-campaigns-for-woocommerce'),
            ];
            echo esc_html($types[$offer->offer_type] ?? ucfirst(str_replace('_', ' ', $offer->offer_type)));
            break;

        case 'buy_product':
            if ($offer->buy_product_id) {
                $product = wc_get_product($offer->buy_product_id);
                echo $product ? esc_html($product->get_name()) . ' (x' . esc_html($offer->buy_quantity) . ')' : esc_html__('Unknown Product', 'promoforge-smart-campaigns-for-woocommerce');
            } else {
                echo '-';
            }
            break;

        case 'get_product':
            if ($offer->get_product_id) {
                $product = wc_get_product($offer->get_product_id);
                echo $product ? esc_html($product->get_name()) . ' (x' . esc_html($offer->get_quantity) . ')' : esc_html__('Unknown Product', 'promoforge-smart-campaigns-for-woocommerce');
            } else {
                echo '-';
            }
            break;

        case 'start_date':
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
add_filter('manage_edit-bogo_offer_sortable_columns', 'promoforge_sortable_bogo_admin_columns');
function promoforge_sortable_bogo_admin_columns($columns)
{
    $columns['offer_type'] = 'offer_type';
    $columns['start_date'] = 'start_date';
    $columns['end_date'] = 'end_date';
    $columns['discount'] = 'discount';
    return $columns;
}

// 4. Handle Sorting
add_action('pre_get_posts', 'promoforge_handle_bogo_admin_sorting');
function promoforge_handle_bogo_admin_sorting($query)
{
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'promoforge_bogo') {
        return;
    }

    $orderby = $query->get('orderby');
    if (in_array($orderby, ['offer_type', 'start_date', 'end_date', 'discount'])) {
        add_filter('posts_join', 'promoforge_bogo_admin_join_table');
        add_filter('posts_orderby', 'promoforge_bogo_admin_orderby_table');
    }
}

function promoforge_bogo_admin_join_table($join)
{
    global $wpdb;
    if (strpos($join, $wpdb->prefix . 'promoforge_bogo_offers') === false) {
        $join .= " LEFT JOIN {$wpdb->prefix}promoforge_bogo_offers ON {$wpdb->posts}.ID = {$wpdb->prefix}promoforge_bogo_offers.post_id ";
    }
    return $join;
}

function promoforge_bogo_admin_orderby_table($orderby)
{
    global $wpdb, $wp_query;
    $sort_col = $wp_query->get('orderby');
    $order = $wp_query->get('order') ? $wp_query->get('order') : 'ASC';

    $allowed_cols = ['offer_type', 'start_date', 'end_date', 'discount'];

    if (in_array($sort_col, $allowed_cols)) {
        return "{$wpdb->prefix}promoforge_bogo_offers.{$sort_col} {$order}";
    }

    return $orderby;
}

// 5. Handle Search
add_action('pre_get_posts', 'promoforge_handle_bogo_admin_search');
function promoforge_handle_bogo_admin_search($query)
{
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'promoforge_bogo' || !$query->is_search()) {
        return;
    }

    $term = $query->get('s');
    if (empty($term))
        return;

    global $wpdb;

    // 1. Search in Custom Table
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $custom_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->prefix}promoforge_bogo_offers 
         WHERE offer_type LIKE %s 
         OR discount LIKE %s 
         OR start_date LIKE %s 
         OR end_date LIKE %s",
        '%' . $wpdb->esc_like($term) . '%',
        '%' . $wpdb->esc_like($term) . '%',
        '%' . $wpdb->esc_like($term) . '%',
        '%' . $wpdb->esc_like($term) . '%'
    ));

    // 2. Search in Post Title
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $title_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} 
         WHERE post_title LIKE %s 
         AND post_type = 'promoforge_bogo' 
         AND post_status != 'trash'",
        '%' . $wpdb->esc_like($term) . '%'
    ));

    // 3. Merge IDs
    $merged_ids = array_unique(array_merge($custom_ids, $title_ids));

    if (empty($merged_ids)) {
        $merged_ids = [0];
    }

    // 4. Modify Query
    $query->set('post__in', $merged_ids);
    // $query->set('s', ''); // Keep 's' for UI
    add_filter('posts_search', 'promoforge_suppress_default_search', 10, 2);
}

// 6. Add Filter Dropdown
add_action('restrict_manage_posts', 'promoforge_render_bogo_type_filter');
function promoforge_render_bogo_type_filter($post_type)
{
    if ($post_type !== 'promoforge_bogo')
        return;

    $options = [
        'buy_x_get_y' => esc_html__('Buy X Get Y', 'promoforge-smart-campaigns-for-woocommerce'),
        'buy_one_get_one' => esc_html__('Buy One Get One', 'promoforge-smart-campaigns-for-woocommerce'),
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
add_action('pre_get_posts', 'promoforge_handle_bogo_admin_filter');
function promoforge_handle_bogo_admin_filter($query)
{
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'promoforge_bogo') {
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
    if (!empty($_GET['filter_offer_type'])) {
        global $wpdb;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $type = sanitize_text_field(wp_unslash($_GET['filter_offer_type']));

        // Ensure table is joined
        add_filter('posts_join', 'promoforge_bogo_admin_join_table');

        // Add where clause
        add_filter('posts_where', function ($where) use ($type, $wpdb) {
            $where .= $wpdb->prepare(" AND {$wpdb->prefix}promoforge_bogo_offers.offer_type = %s", $type);
            return $where;
        });
    }
}
