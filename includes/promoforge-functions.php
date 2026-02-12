<?php
if (!defined('ABSPATH')) {
    exit;
}

// In flash-functions.php
register_activation_hook(__FILE__, 'promoforge_activate_plugin');

// Activation hook function
function promoforge_activate_plugin()
{
    // Create tables immediately
    promoforge_create_database_tables();

    // Schedule a verification for next page load
    update_option('promoforge_check_tables', 1);
}

add_action('init', 'promoforge_check_table_creation');

// Check if tables need to be created
function promoforge_check_table_creation()
{
    if (get_option('promoforge_check_tables')) {
        promoforge_create_database_tables();
        delete_option('promoforge_check_tables');
    }
}

// create tables in database
function promoforge_create_database_tables()
{
    global $wpdb;

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Check if we need to migrate existing flash_offer_categories table
    promoforge_migrate_categories_table();

    $tables = [
        'promoforge_flash_offers' => "
            CREATE TABLE {$wpdb->prefix}promoforge_flash_offers (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                post_id bigint(20) NOT NULL,
                offer_type varchar(50) NOT NULL DEFAULT 'flash',
                start_date datetime DEFAULT NULL,
                end_date datetime NOT NULL,
                discount decimal(5,2) NOT NULL,
                use_offers int(11) NOT NULL DEFAULT 1,
                PRIMARY KEY (id),
                KEY post_id (post_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        'promoforge_offer_products' => "
            CREATE TABLE {$wpdb->prefix}promoforge_offer_products (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                offer_id bigint(20) NOT NULL,
                product_id bigint(20) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY offer_product (offer_id,product_id),
                KEY product_id (product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        'promoforge_offer_categories' => "
            CREATE TABLE {$wpdb->prefix}promoforge_offer_categories (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                offer_id bigint(20) NOT NULL,
                category_id bigint(20) NOT NULL,
                product_id bigint(20) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY offer_category_product (offer_id,category_id,product_id),
                KEY product_id (product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        'promoforge_bogo_offers' => "
            CREATE TABLE {$wpdb->prefix}promoforge_bogo_offers (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            offer_type varchar(20) NOT NULL DEFAULT 'buy_x_get_y',
            buy_product_id bigint(20) NOT NULL,
            get_product_id bigint(20) NOT NULL,
            buy_quantity int(11) NOT NULL DEFAULT 1,
            get_quantity int(11) NOT NULL DEFAULT 1,
            discount decimal(5,2) NOT NULL,
            start_date datetime DEFAULT NULL,
            end_date datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY post_bogo (post_id, buy_product_id, get_product_id),
            KEY buy_product_id (buy_product_id),
            KEY get_product_id (get_product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    ];

    foreach ($tables as $table_name_suffix => $sql) {
        // Check if table exists first
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}{$table_name_suffix}'") != $wpdb->prefix . $table_name_suffix) {
            $result = dbDelta($sql);

            // Table creation handled by dbDelta
        }
    }
}

// Migrate existing flash_offer_categories table to include product_id
function promoforge_migrate_categories_table()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'promoforge_offer_categories';

    // Check if table exists
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return; // Table doesn't exist yet, will be created fresh
    }

    // Check if product_id column already exists
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}promoforge_offer_categories LIKE 'product_id'");

    if (empty($column_exists)) {
        // Add product_id column
        // We use $table_name here because it's already prefixed above and consistent with context
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query("ALTER TABLE {$wpdb->prefix}promoforge_offer_categories ADD COLUMN product_id bigint(20) NOT NULL DEFAULT 0 AFTER category_id");

        // Add index for product_id
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query("ALTER TABLE {$wpdb->prefix}promoforge_offer_categories ADD KEY product_id (product_id)");

        // Drop old unique key if it exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query("ALTER TABLE {$wpdb->prefix}promoforge_offer_categories DROP INDEX offer_category");

        // Add new unique key to include product_id
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query("ALTER TABLE {$wpdb->prefix}promoforge_offer_categories ADD UNIQUE KEY offer_category_product (offer_id,category_id,product_id)");

        // Clear existing data since it won't have product_id values
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query("DELETE FROM {$wpdb->prefix}promoforge_offer_categories WHERE product_id = 0");
    }
}

// Migrate flash_offers table to include use_offers column
function promoforge_migrate_use_offers_column()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'promoforge_flash_offers';

    // Check if table exists
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return;
    }

    // Check if use_offers column already exists
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}promoforge_flash_offers LIKE 'use_offers'");

    if (empty($column_exists)) {
        // Add use_offers column
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query("ALTER TABLE {$wpdb->prefix}promoforge_flash_offers ADD COLUMN use_offers int(11) NOT NULL DEFAULT 1");
    }
}

/**
 * Get the primary category ID for a product
 * Priority: 1. Yoast SEO primary category, 2. First assigned category
 */
function promoforge_get_product_primary_category($product_id)
{
    // Method 1: Check if Yoast SEO primary category is set
    if (class_exists('WPSEO_Primary_Term')) {
        $primary_term = new WPSEO_Primary_Term('product_cat', $product_id);
        $primary_category_id = $primary_term->get_primary_term();

        if ($primary_category_id && $primary_category_id > 0) {
            return (int) $primary_category_id;
        }
    }

    // Method 2: Check for RankMath primary category
    $rankmath_primary = get_post_meta($product_id, 'rank_math_primary_product_cat', true);
    if ($rankmath_primary && $rankmath_primary > 0) {
        return (int) $rankmath_primary;
    }

    // Method 3: Get the first assigned category (fallback)
    $categories = wp_get_post_terms($product_id, 'product_cat', [
        'fields' => 'ids',
        'orderby' => 'term_order',
        'order' => 'ASC',
        'number' => 1
    ]);

    if (!is_wp_error($categories) && !empty($categories)) {
        $primary_category_id = (int) $categories[0];
        return $primary_category_id;
    }

    return 0;
}

/**
 * Consolidated table verification function
 * Replaces promoforge_verify_tables_exist() and promoforge_tables_exist()
 */
function promoforge_ensure_tables_exist($force_check = false)
{
    global $wpdb;

    static $tables_verified = null;

    // Skip if already verified and not forcing
    if ($tables_verified === true && !$force_check) {
        return true;
    }

    $required_tables = [
        'promoforge_flash_offers',
        'promoforge_offer_products',
        'promoforge_offer_categories',
        'promoforge_bogo_offers'
    ];

    $missing_tables = [];

    foreach ($required_tables as $table) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}{$table}'") != $wpdb->prefix . $table) {
            $missing_tables[] = $table;
        }
    }

    // If tables are missing, attempt to create them
    if (!empty($missing_tables)) {
        promoforge_create_database_tables();

        // Verify creation was successful
        foreach ($missing_tables as $table) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}{$table}'") != $wpdb->prefix . $table) {
                $tables_verified = false;
                return false;
            }
        }
    }

    $tables_verified = true;
    return true;
}

// Legacy function for backward compatibility
function promoforge_tables_exist()
{
    return promoforge_ensure_tables_exist();
}

// In flash-offers-plugin.php
add_action('plugins_loaded', 'promoforge_emergency_table_check');

// Simplified emergency table check using consolidated function
function promoforge_emergency_table_check()
{
    // Use the consolidated function with force check
    if (!promoforge_ensure_tables_exist(true)) {
        if (is_admin()) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>Flash Offers:</strong> ' . esc_html__('Database tables could not be created automatically. Please deactivate and reactivate the plugin.', 'promoforge-smart-campaigns-for-woocommerce');
                echo '</p></div>';
            });
        }
    }
}

function promoforge_uninstall()
{
    // Only run if explicitly requested
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        return;
    }

    global $wpdb;

    // 1. FIRST: Delete all flash_offer posts and their meta
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $posts = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'promoforge_flash'");

    foreach ($posts as $post_id) {
        wp_delete_post($post_id, true); // true = force delete (bypass trash)
    }

    // 2. Delete plugin tables
    $tables = [
        'promoforge_flash_offers',
        'promoforge_offer_products',
        'promoforge_offer_categories',
        'promoforge_bogo_offers'
    ];

    foreach ($tables as $table) {
        $table_name = $wpdb->prefix . $table;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }

    // 3. Delete options
    delete_option('flash_offers_options');
    delete_option('promoforge_check_tables');

    // 4. Clean up any remaining postmeta (optional but recommended)
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query("
        DELETE FROM {$wpdb->postmeta} 
        WHERE post_id IN (
            SELECT ID FROM {$wpdb->posts} WHERE post_type = 'promoforge_flash'
        )
    ");
}
add_action('admin_menu', function () {
    add_menu_page(
        esc_html__('Offers', 'promoforge-smart-campaigns-for-woocommerce'),          // Page title
        esc_html__('Offers', 'promoforge-smart-campaigns-for-woocommerce'),          // Menu title
        'manage_options',  // Capability
        'offers',          // Menu slug (used as parent slug above)
        '',                // No callback, because CPTs handle their own screens
        'dashicons-clock', // Icon
        25                 // Position
    );
});

// Register Flash Offer post type
add_action('init', function () {
    register_post_type('promoforge_flash', [
        'labels' => [
            'name' => esc_html__('Flash Offers', 'promoforge-smart-campaigns-for-woocommerce'),
            'singular_name' => esc_html__('Flash Offer', 'promoforge-smart-campaigns-for-woocommerce'),
        ],
        'public' => false,
        'show_ui' => true,
        'supports' => ['title'],
        'menu_icon' => 'dashicons-clock', // will be ignored since we're grouping
        'show_in_menu' => 'offers',       // attach under Offers menu
    ]);
});

// Register BOGO Offer post type
add_action('init', function () {
    register_post_type('promoforge_bogo', [
        'labels' => [
            'name' => esc_html__('BOGO Offers', 'promoforge-smart-campaigns-for-woocommerce'),
            'singular_name' => esc_html__('BOGO Offer', 'promoforge-smart-campaigns-for-woocommerce'),
        ],
        'public' => false,
        'show_ui' => true,
        'supports' => ['title'],
        'menu_icon' => 'dashicons-clock',
        'show_in_menu' => 'offers',       // same parent
    ]);
});


// Register settings
add_action('admin_init', function () {
    register_setting('flash_offers_settings_group', 'flash_offers_options', ['sanitize_callback' => 'promoforge_sanitize_options']);

    add_settings_section(
        'flash_offers_main_section',
        '',
        '__return_null',
        'flash-offers-settings'
    );

    add_settings_field(
        'flash_offer_message',
        esc_html__('Flash Offer Message', 'promoforge-smart-campaigns-for-woocommerce'),
        'promoforge_message_field_callback',
        'flash-offers-settings',
        'flash_offers_main_section'
    );

    // Display locations
    add_settings_field(
        'flash_offer_locations',
        esc_html__('Badge Display Locations', 'promoforge-smart-campaigns-for-woocommerce'),
        'promoforge_locations_field_callback',
        'flash-offers-settings',
        'flash_offers_main_section'
    );

    // Add countdown locations to settings
    add_settings_field(
        'flash_offer_countdown_locations',
        esc_html__('Countdown Display Locations', 'promoforge-smart-campaigns-for-woocommerce'),
        'promoforge_countdown_locations_field_callback',
        'flash-offers-settings',
        'flash_offers_main_section'
    );

    // Badge style
    add_settings_field(
        'flash_offer_active_badge_text',
        esc_html__('Active Badge Text', 'promoforge-smart-campaigns-for-woocommerce'),
        'promoforge_active_badge_text_callback',
        'flash-offers-settings',
        'flash_offers_main_section'
    );

    add_settings_field(
        'flash_offer_upcoming_badge_text',
        esc_html__('Upcoming Badge Text', 'promoforge-smart-campaigns-for-woocommerce'),
        'promoforge_upcoming_badge_text_callback',
        'flash-offers-settings',
        'flash_offers_main_section'
    );

    add_settings_field(
        'flash_offer_special_badge_text',
        esc_html__('Special Badge Text', 'promoforge-smart-campaigns-for-woocommerce'),
        'promoforge_special_badge_text_callback',
        'flash-offers-settings',
        'flash_offers_main_section'
    );

    add_settings_field(
        'flash_offer_badge_bg_color',
        esc_html__('Badge Background Color', 'promoforge-smart-campaigns-for-woocommerce'),
        'promoforge_render_color_picker',
        'flash-offers-settings',
        'flash_offers_main_section'
    );

    add_settings_field(
        'flash_offer_override_price_type',
        esc_html__('Override Price Type For Flash Offers', 'promoforge-smart-campaigns-for-woocommerce'),
        'promoforge_render_price_type_field',
        'flash-offers-settings',
        'flash_offers_main_section'
    );

    // Add countdown format selection field
    add_settings_field(
        'flash_offer_countdown_format',
        esc_html__('Countdown Display Format', 'promoforge-smart-campaigns-for-woocommerce'),
        'promoforge_countdown_format_field_callback',
        'flash-offers-settings',
        'flash_offers_main_section'
    );

    add_settings_field(
        'bogo_offer_badge',
        esc_html__('BOGO Offer Badge Text', 'promoforge-smart-campaigns-for-woocommerce'),
        'promoforge_bogo_offer_badge_callback',
        'flash-offers-settings',
        'flash_offers_main_section'
    );
    add_settings_field(
        'bogo_offer_override_price_type',
        esc_html__('Override Price Type For BOGO Offers', 'promoforge-smart-campaigns-for-woocommerce'),
        'promoforge_bogo_offer_render_price_type_field',
        'flash-offers-settings',
        'flash_offers_main_section'
    );

    add_settings_field(
        'flash_offer_variable_product_display',
        esc_html__('Product Details Display', 'promoforge-smart-campaigns-for-woocommerce'),
        'promoforge_variable_product_display_callback',
        'flash-offers-settings',
        'flash_offers_main_section'
    );

});

function promoforge_sanitize_options($input)
{
    // Sanitization logic
    $new_input = [];
    if (isset($input['message']))
        $new_input['message'] = sanitize_textarea_field($input['message']);
    if (isset($input['flash_override_type']))
        $new_input['flash_override_type'] = sanitize_text_field($input['flash_override_type']);
    // Add other fields as necessary
    return $input; // Returning input for now to preserve all data, but normally should be explicit
}


function promoforge_get_offer_data($product)
{
    global $wpdb;

    if (!$product || !is_a($product, 'WC_Product')) {
        return false;
    }

    $product_id = $product->get_id();
    if ($product->is_type('variation')) {
        $product_id = $product->get_parent_id();
    }

    // Verify tables exist
    // Check tables safely
    if (
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        !$wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}promoforge_flash_offers'") ||
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        !$wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}promoforge_offer_products'")
    ) {
        return false;
    }

    // Get ALL potential offers assigned to this product, data checks done in loop
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $offers = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT o.* FROM {$wpdb->prefix}promoforge_flash_offers o
             JOIN {$wpdb->prefix}promoforge_offer_products p ON o.id = p.offer_id
             JOIN {$wpdb->posts} wp ON o.post_id = wp.ID
             WHERE p.product_id = %d
             AND o.end_date > %s
             AND wp.post_status = 'publish'
             ORDER BY o.discount DESC",
            $product_id,
            current_time('mysql')
        )
    );

    if (empty($offers)) {
        return false;
    }

    // Get settings once
    $options = get_option('flash_offers_options');
    $bg_color = $options['badge_bg_color'] ?? '#00a99d';
    $locations = $options['locations'] ?? [];
    $countdown_locations = $options['countdown_locations'] ?? [];
    $flash_override_type = $options['flash_override_type'] ?? 'sale';
    $countdown_format = $options['countdown_format'] ?? 'format1';
    $bogo_format = $options['bogo_format'] ?? 'defualt';

    // Badge texts
    $active_badge_text = $options['active_badge_text'] ?? '';
    $upcoming_badge_text = $options['upcoming_badge_text'] ?? '';
    $special_badge_text = $options['special_badge_text'] ?? '';

    $now = current_time('timestamp');
    $user_id = is_user_logged_in() ? get_current_user_id() : 0;

    foreach ($offers as $offer) {
        // Validate 'special' offers must come from URL
        if ($offer->offer_type === 'special') {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if (!isset($_GET['from_offer']) || (int) $_GET['from_offer'] !== (int) $offer->post_id) {
                continue; // Skip this offer, look for next
            }

            // Double check product assignment (redundant with join but safe)
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $is_in_offer = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}promoforge_offer_products 
                 WHERE offer_id = %d AND product_id = %d",
                $offer->id,
                $product_id
            ));

            if (!$is_in_offer)
                continue;
        }

        // Time checks
        $start_time = strtotime($offer->start_date);
        $end_time = strtotime($offer->end_date);

        // Determine status
        $status = 'expired';
        if ($now < $start_time) {
            $status = 'upcoming';
        } elseif ($now <= $end_time) {
            $status = 'active';
        }

        if ($status === 'expired') {
            continue;
        }

        // Check usage limit
        if ($status === 'active' && $user_id) {
            $limit = isset($offer->use_offers) ? (int) $offer->use_offers : 1;

            // Check past purchase count
            $past_usage = promoforge_get_user_purchase_count($user_id, $product_id, $offer->start_date, $offer->end_date);

            // If satisfied by past purchases alone
            if ($past_usage >= $limit) {
                continue;
            }
        }

        // If we got here, this offer is valid for the user
        // Build badge text
        $discount = (float) $offer->discount;
        $badge_text = '';
        if ($status === 'upcoming' && $now < $start_time) {
            $badge_text = "{$upcoming_badge_text} {$discount}% Off";
        } elseif ($status === 'active' && $offer->offer_type === 'special') {
            $badge_text = "{$special_badge_text} {$discount}% Off";
        } elseif ($status === 'active' && $now >= $start_time && $now <= $end_time) {
            $badge_text = "{$active_badge_text} {$discount}% Off";
        }

        return [
            'status' => $status,
            'offer_type' => $offer->offer_type,
            'start' => $offer->start_date,
            'end' => $offer->end_date,
            'discount' => $discount,
            'use_offers' => isset($offer->use_offers) ? (int) $offer->use_offers : 1,
            'background_color' => $bg_color,
            'badge_text' => $badge_text,
            'locations' => $locations,
            'countdown_locations' => $countdown_locations,
            'flash_override_type' => $flash_override_type,
            'countdown_format' => $countdown_format,
            'bogo_format' => $bogo_format,
            'remaining_usage' => isset($limit) && isset($past_usage) ? max(0, $limit - $past_usage) : 9999,
            'remaining_display' => isset($limit) && isset($past_usage) ? max(0, $limit - ($past_usage + promoforge_get_cart_qty($product_id))) : 9999,
        ];
    }

    return false; // No valid offers found
}


// Calculate discount amount
function promoforge_calculate_discount($regular_price, $discount)
{
    if ($regular_price <= 0 || $discount <= 0)
        return $regular_price;
    return round($regular_price - ($regular_price * ($discount / 100)), wc_get_price_decimals());
}

// Apply flash offer discount to all price types
add_filter('woocommerce_product_get_price', 'promoforge_discount_price', 20, 2);
add_filter('woocommerce_product_get_sale_price', 'promoforge_discount_price', 20, 2);
add_filter('woocommerce_product_variation_get_price', 'promoforge_discount_price', 20, 2);
add_filter('woocommerce_product_variation_get_sale_price', 'promoforge_discount_price', 20, 2);

// Apply flash offer discount to sale price
function promoforge_discount_price($price, $product)
{
    $offer_data = promoforge_get_offer_data($product);
    if (!$offer_data || $offer_data['status'] !== 'active')
        return $price;

    // Check global flag - if we are rendering the product page summary (e.g. table)
    // and the limit is reached (remaining_display <= 0), show regular price.
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    global $promoforge_rendering_product_summary;
    if (!empty($promoforge_rendering_product_summary) && isset($offer_data['remaining_display']) && $offer_data['remaining_display'] <= 0) {
        return $price;
    }

    $flash_override_type = $offer_data['flash_override_type'];

    $data = $product->get_data();
    $sale_price = $data['sale_price'];
    $regular_price = $data['regular_price'];
    $base_price = ($flash_override_type === 'sale' && $sale_price > 0) ? $sale_price : $regular_price;
    if ($base_price <= 0)
        return $price;

    $discount_price = promoforge_calculate_discount($base_price, $offer_data['discount']);

    // Check if we need to apply quantity-based mixed pricing
    // (Only if user is logged in and we have a limit)
    if (is_user_logged_in() && !empty($offer_data['use_offers'])) {

        // If NO remaining usage (all used up in past), return standard price
        if (isset($offer_data['remaining_usage']) && $offer_data['remaining_usage'] <= 0) {
            return $price;
        }

        // Check if we have remaining usage
        if (!empty($offer_data['remaining_usage'])) {
            // Get quantity in cart for this product
            $cart_qty = promoforge_get_cart_qty($product->get_id());

            // If we have items in cart, and they exceed the remaining limit
            if ($cart_qty > $offer_data['remaining_usage']) {
                $qty_at_discount = $offer_data['remaining_usage'];
                $qty_at_regular = $cart_qty - $qty_at_discount;

                // Calculate weighted average
                if ($cart_qty > 0) {
                    $total_price = ($qty_at_discount * $discount_price) + ($qty_at_regular * $base_price);
                    return $total_price / $cart_qty;
                }
            }
        }
    }

    return $discount_price;
}

add_filter('woocommerce_product_variation_get_regular_price', 'promoforge_discount_regular_price', 20, 2);
add_filter('woocommerce_product_get_regular_price', 'promoforge_discount_regular_price', 20, 2);
// Apply flash offer discount to regular price
function promoforge_discount_regular_price($price, $product)
{
    return $price;
}

// Get price HTML for a product, including flash offer discount
function promoforge_get_price_html($product)
{
    $offer_data = promoforge_get_offer_data($product);

    if (!$offer_data || $offer_data['status'] !== 'active') {
        return $product->get_price_html();
    }
    $flash_override_type = $offer_data['flash_override_type'] ?? 'sale';
    $data = $product->get_data();
    $sale_price = $data['sale_price'];
    $regular_price = $data['regular_price'];
    $base_price = ($flash_override_type === 'sale' && $sale_price > 0) ? $sale_price : $regular_price;
    $discounted_price = $product->get_price();

    if ($base_price && $discounted_price < $base_price) {
        return '<del>' . wc_price($base_price) . '</del> <ins>' . wc_price($discounted_price) . '</ins>';
    }

    return $product->get_price_html();
}



// Helper function to get current offer ID
function promoforge_get_current_offer_id()
{
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['from_offer'])) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return (int) $_GET['from_offer'];
    }

    // Check if in cart context
    foreach (WC()->cart->get_cart() as $cart_item) {
        if (isset($cart_item['from_offer'])) {
            return $cart_item['from_offer'];
        }
    }

    return false;
}


add_filter('woocommerce_get_price_html', 'promoforge_show_discounted_price_html', 20, 2);

// Helper function to show discounted price HTML
function promoforge_show_discounted_price_html($price_html, $product)
{
    if (!$product->is_type(['simple', 'variable', 'variation'])) {
        return $price_html;
    }
    $offer_data = promoforge_get_offer_data($product);
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (!$offer_data || ($offer_data['status'] === 'special' && !isset($_GET['from_offer']))) {
        return $price_html;
    }

    // Check usage limits
    if (isset($offer_data['remaining_display'])) {
        // If usage exceeds limit (e.g. cart has more than allowed), revert to default display (likely mixed or regular)
        if ($offer_data['remaining_display'] < 0) {
            return $price_html;
        }
        // If usage exactly meets limit, hide discount on Shop/Product pages (prevents new adds at discount)
        // But keep it for Cart/Checkout (shows discount for current item)
        if ($offer_data['remaining_display'] == 0 && !is_cart() && !is_checkout()) {
            return $price_html;
        }
    }

    $flash_override_type = $offer_data['flash_override_type'] ?? 'sale';
    // 🔷 ACTIVE — Show discounted price
    if ($offer_data['status'] === 'active') {
        if ($product->is_type('variable')) {
            $reg_prices = [];
            $disc_prices = [];

            foreach ($product->get_available_variations() as $variation) {
                $variation_obj = wc_get_product($variation['variation_id']);
                if (!$variation_obj)
                    continue;

                $data = $variation_obj->get_data();
                $sale_price = (float) $data['sale_price'];
                $regular_price = (float) $data['regular_price'];

                $base_price = ($flash_override_type === 'sale' && $sale_price > 0) ? $sale_price : $regular_price;

                if ($base_price <= 0)
                    continue;

                $reg_prices[] = $base_price;
                $disc_prices[] = promoforge_calculate_discount($base_price, $offer_data['discount']);
            }

            if (!empty($reg_prices) && !empty($disc_prices)) {
                $min_reg = min($reg_prices);
                $max_reg = max($reg_prices);
                $min_disc = min($disc_prices);
                $max_disc = max($disc_prices);

                $price_html = '<span class="price-regular"><del>' . wc_price($min_reg) . ' – ' . wc_price($max_reg) . '</del></span> ';
                $price_html .= '<span class="price-discount"><ins>' . wc_price($min_disc) . ' – ' . wc_price($max_disc) . '</ins></span>';
            }
        } else {
            $data = $product->get_data();
            $sale_price = (float) $data['sale_price'];
            $regular_price = (float) $data['regular_price'];

            $base_price = ($flash_override_type === 'sale' && $sale_price > 0) ? $sale_price : $regular_price;

            if ($base_price > 0) {
                $discounted_price = promoforge_calculate_discount($base_price, $offer_data['discount']);

                $price_html = '<span class="price-regular"><del>' . wc_price($base_price) . '</del></span> ';
                $price_html .= '<span class="price-discount"><ins>' . wc_price($discounted_price) . '</ins></span>';
            }
        }
    }

    return $price_html;
}


// Add hidden field to maintain offer parameter
add_action('woocommerce_before_add_to_cart_button', function () {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['from_offer'])) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        echo '<input type="hidden" name="from_offer" value="' . esc_attr((int) $_GET['from_offer']) . '">';
    }
});

// bogo offer

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('wc-add-to-cart-variation');
});


/**
 * Get total quantity of a product purchased by a user within a date range
 */
function promoforge_get_user_purchase_count($user_id, $product_id, $start_date, $end_date)
{
    global $wpdb;

    if (empty($start_date))
        $start_date = '2000-01-01 00:00:00';
    if (empty($end_date))
        $end_date = '2099-12-31 23:59:59';

    $sql = $wpdb->prepare(
        "SELECT SUM(qty.meta_value) 
        FROM {$wpdb->prefix}woocommerce_order_items as items
        JOIN {$wpdb->prefix}woocommerce_order_itemmeta as pid ON items.order_item_id = pid.order_item_id
        JOIN {$wpdb->prefix}woocommerce_order_itemmeta as qty ON items.order_item_id = qty.order_item_id
        JOIN {$wpdb->posts} as orders ON items.order_id = orders.ID
        WHERE orders.post_author = %d
        AND orders.post_type = 'shop_order'
        AND orders.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold')
        AND orders.post_date >= %s 
        AND orders.post_date <= %s
        AND pid.meta_key IN ('_product_id', '_variation_id')
        AND pid.meta_value = %d
        AND qty.meta_key = '_qty'",
        $user_id,
        $start_date,
        $end_date,
        $product_id
    );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
    $count = $wpdb->get_var($sql);

    return $count ? (int) $count : 0;
}

/**
 * Get quantity of a product currently in the cart
 */
function promoforge_get_cart_qty($product_id)
{
    if (!WC()->cart) {
        return 0;
    }

    $qty = 0;
    foreach (WC()->cart->get_cart() as $cart_item) {
        // Check for product ID or variation ID match
        if ($cart_item['product_id'] == $product_id || (isset($cart_item['variation_id']) && $cart_item['variation_id'] == $product_id)) {
            $qty += $cart_item['quantity'];
        }
    }
    return $qty;
}


// Require login for flash offers add to cart
add_filter('woocommerce_add_to_cart_validation', 'promoforge_validate_add_to_cart_login', 10, 3);

function promoforge_validate_add_to_cart_login($passed, $product_id, $quantity)
{
    if (is_user_logged_in()) {
        return $passed;
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        return $passed;
    }

    $offer_data = promoforge_get_offer_data($product);

    if ($offer_data && $offer_data['status'] === 'active') {
        // translators: %s: Login URL
        wc_add_notice(sprintf(__('You must be <a href="%s">logged in</a> to use this exclusive offer.', 'promoforge-smart-campaigns-for-woocommerce'), get_permalink(wc_get_page_id('myaccount'))), 'error');
        return false;
    }

    return $passed;
}


function promoforge_remove_woocommerce_variations_form()
{
  // Variable
  remove_action('woocommerce_variable_add_to_cart', 'woocommerce_variable_add_to_cart', 30);
  // Simple
  remove_action('woocommerce_simple_add_to_cart', 'woocommerce_simple_add_to_cart', 30);
  // Grouped
  remove_action('woocommerce_grouped_add_to_cart', 'woocommerce_grouped_add_to_cart', 30);
  // External
  remove_action('woocommerce_external_add_to_cart', 'woocommerce_external_add_to_cart', 30);
  
  // Common
  remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
  remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40);
}

function promoforge_should_use_table_display() {
    $options = get_option('flash_offers_options');
    return isset($options['variable_product_display']) && $options['variable_product_display'] === 'table';
}

add_action('wp', 'promoforge_maybe_remove_variations_form');
function promoforge_maybe_remove_variations_form() {
    if (promoforge_should_use_table_display()) {
        promoforge_remove_woocommerce_variations_form();
        // Remove theme's table if it exists
        remove_action('woocommerce_single_product_summary', 'custom_display_variable_product', 25);
    }
}

add_action('woocommerce_single_product_summary', 'promoforge_maybe_add_custom_display', 25);
function promoforge_maybe_add_custom_display() {
    if (promoforge_should_use_table_display()) {
        promoforge_custom_display_variable_product();
    }
}
function promoforge_custom_display_variable_product()
{
  global $product;

  // 1. VARIABLE PRODUCT
  if ($product->is_type('variable')) {
    echo '<table class="variable-product-table">
      <thead>
        <tr>
          <th>Variation</th>
          <th>Price</th>
          <th>Price/Unit</th>
          <th>Quantity</th>
          <th>Add to Cart</th>
        </tr>
      </thead>
      <tbody>';

    $available_variations = $product->get_available_variations();
    foreach ($available_variations as $variation) {
      $variation_obj = wc_get_product($variation['variation_id']);
      $price = $variation_obj->get_price();
      $attributes = $variation['attributes'];

      $is_in_stock = $variation_obj && $variation_obj->is_in_stock();
      
      // Calculate Price Per Unit (only if pa_pack-size exists)
      $pack_size_val = $attributes['attribute_pa_pack-size'] ?? '';
      
      // Sanitize pack size to get numeric part (e.g. "100 Tablets" -> 100)
      $pack_qty = (int) preg_replace('/[^0-9]/', '', $pack_size_val);
      $price_per_piece = ($pack_qty > 0) ? number_format($price / $pack_qty, 2) : '';

      // Generate Variation Description (e.g. "Color: Blue, Size: Large")
      $variation_specs = [];
      foreach ($attributes as $attr_name => $attr_value) {
          // Attribute names usually come as 'attribute_pa_color' or 'attribute_color'
          // Remove prefix to get slug
          $taxonomy = str_replace('attribute_', '', $attr_name);
          
          // Try to get proper label name from taxonomy
          $label = wc_attribute_label($taxonomy, $product);
          
          // Try to get proper term name
          $value = $attr_value;
          if (taxonomy_exists($taxonomy)) {
              $term = get_term_by('slug', $attr_value, $taxonomy);
              if ($term) {
                  $value = $term->name;
              }
          }
          
          if (!empty($value)) {
             $variation_specs[] = ucwords($value);
          }
      }
      $variation_html = implode(', ', $variation_specs);

      echo '<tr>';
      echo '<td>' . ((!empty($variation_html)) ? esc_html($variation_html) : esc_html__('Default', 'promoforge-smart-campaigns-for-woocommerce')) . '</td>';
      echo '<td class="price">' . wp_kses_post(wc_price($price)) . '</td>';
      
      echo '<td class="price-unit">';
      if (is_numeric($price_per_piece)) {
          echo wp_kses_post(wc_price($price_per_piece)) . ' /Piece';
      } else {
          echo wp_kses_post(wc_price($price)) . ' /Piece';
      }
      echo '</td>';
      
      echo '<td class="list-quantity"><input type="number" value="1" min="1" class="qty" id="qty_' . esc_attr($variation['variation_id']) . '" name="quantity_' . esc_attr($variation['variation_id']) . '"></td>';
      echo '<td>';
      ?>
      <form class="cart" action="<?php echo esc_url($product->get_permalink()); ?>" method="post">
        <input type="hidden" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>" />
        <input type="hidden" name="variation_id" value="<?php echo esc_attr($variation['variation_id']); ?>" />
        <?php
        foreach ($variation['attributes'] as $attribute_name => $attribute_value) {
          echo '<input type="hidden" name="attribute_' . esc_attr(str_replace('attribute_', '', $attribute_name)) . '" value="' . esc_attr($attribute_value) . '" />';
        }
        ?>
        <input type="hidden" name="quantity" id="quantity_<?php echo esc_attr($variation['variation_id']); ?>" value="1" />
        <button type="submit" class="single_add_to_cart_button button" <?php echo !$is_in_stock ? "disabled" : ""; ?>       <?php echo $is_in_stock ? 'onclick="updateQuantity(' . esc_js($variation['variation_id']) . ')"' : ''; ?>>
            <?php echo $is_in_stock ? esc_html($product->single_add_to_cart_text()) : esc_html__('Out of stock', 'promoforge-smart-campaigns-for-woocommerce'); ?>
        </button>
      </form>
      <?php
      echo '</td>';
      echo '</tr>';
    }
    echo '</tbody></table>';
  } 
  // 2. SIMPLE PRODUCT
  elseif ($product->is_type('simple')) {
      echo '<table class="variable-product-table">
      <thead>
        <tr>
          <th>Price</th>
          <th>Price/Unit</th>
          <th>Quantity</th>
          <th>Add to Cart</th>
        </tr>
      </thead>
      <tbody>';

      $price = $product->get_price();
      $is_in_stock = $product->is_in_stock();
      $pack_size_val = $product->get_attribute('pa_pack-size'); 

      echo '<tr>';
      echo '<td class="price">' . wp_kses_post(wc_price($price)) . '</td>';
      
      echo '<td class="price-unit">';
      if (is_numeric($price)) {
          echo wp_kses_post(wc_price($price)) . ' /Piece';
      } 
      echo '</td>';
      
      echo '<td class="list-quantity"><input type="number" value="1" min="1" class="qty" id="qty_' . esc_attr($product->get_id()) . '" name="quantity"></td>';
      echo '<td>';
      ?>
      <form class="cart" action="<?php echo esc_url($product->get_permalink()); ?>" method="post">
        <input type="hidden" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>" />
        <input type="hidden" name="quantity" id="quantity_<?php echo esc_attr($product->get_id()); ?>" value="1" />
        <button type="submit" class="single_add_to_cart_button button" <?php echo !$is_in_stock ? "disabled" : ""; ?> <?php echo $is_in_stock ? 'onclick="updateQuantity(' . esc_js($product->get_id()) . ')"' : ''; ?>>
            <?php echo $is_in_stock ? esc_html($product->single_add_to_cart_text()) : esc_html__('Out of stock', 'promoforge-smart-campaigns-for-woocommerce'); ?>
        </button>
      </form>
      <?php
      echo '</td></tr>';
      echo '</tbody></table>';
  }
  // 3. GROUPED PRODUCT
  elseif ($product->is_type('grouped')) {
      echo '<table class="variable-product-table">
      <thead>
        <tr>
          <th>Product</th>
          <th>Price</th>
          <th>Price/Unit</th>
          <th>Quantity</th>
          <th>Add to Cart</th>
        </tr>
      </thead>
      <tbody>';

      $children = $product->get_children();
      foreach($children as $child_id) {
          $child = wc_get_product($child_id);
          if(!$child) continue;
          
          $price = $child->get_price();
          $is_in_stock = $child->is_in_stock();
          $pack_size_val = $child->get_attribute('pa_pack-size');
          
          // Sanitize pack size to get numeric part
          $pack_qty = (int) preg_replace('/[^0-9]/', '', $pack_size_val);
          $price_per_piece = ($pack_qty > 0) ? number_format($price / $pack_qty, 2) : '-';

          echo '<tr>';
          echo '<td>' . esc_html($child->get_name()) . ' <small>(' . esc_html($pack_size_val) . ')</small></td>';
          echo '<td class="price">' . wp_kses_post(wc_price($price)) . '</td>';
          
          echo '<td class="price-unit">';
          if (is_numeric($price_per_piece)) {
              echo wp_kses_post(wc_price($price_per_piece)) . ' /Piece';
          } else {
              echo '-';
          }
          echo '</td>';
          
          echo '<td class="list-quantity"><input type="number" value="1" min="1" class="qty" name="quantity[' . esc_attr($child_id) . ']"></td>';
          echo '<td>';
          // Grouped adds to cart via main form usually, but individual adds work too
          echo '<a href="' . esc_url($child->add_to_cart_url()) . '" class="button single_add_to_cart_button">' . esc_html($child->add_to_cart_text()) . '</a>'; 
          echo '</td></tr>';
      }
      echo '</tbody></table>';
  }
  // 4. EXTERNAL PRODUCT
  elseif ($product->is_type('external')) {
      echo '<table class="variable-product-table">
      <thead>
        <tr>
          <th>Description</th>
          <th>Price</th>
          <th>Price/Unit</th>
          <th>Quantity</th>
          <th>Add to Cart</th>
        </tr>
      </thead>
      <tbody>';

      $price = $product->get_price();
      $pack_size_val = $product->get_attribute('pa_pack-size');
      
      echo '<tr>';
      echo '<td>' . esc_html($pack_size_val) . '</td>';
      echo '<td class="price">' . wp_kses_post(wc_price($price)) . '</td>';
      echo '<td class="price-unit">-</td>';
      echo '<td class="list-quantity">-</td>';
      echo '<td>';
      echo '<a href="' . esc_url($product->get_product_url()) . '" rel="nofollow" class="single_add_to_cart_button button">' . esc_html($product->get_button_text()) . '</a>';
      echo '</td></tr>';
      echo '</tbody></table>';
  }

  // No closing bracket for the function-wide table since we closed individual tables
  ?>
  <script>
    function updateQuantity(id) {
        var qtyInput = document.getElementById('qty_' + id);
        var hiddenInput = document.getElementById('quantity_' + id);
        if(qtyInput && hiddenInput) {
            hiddenInput.value = qtyInput.value;
        }
    }
  </script>
  <?php
}