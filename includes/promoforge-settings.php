<?php
if (!defined('ABSPATH')) {
    exit;
}

// Field callback function
function promoforge_message_field_callback()
{
    $options = get_option('flash_offers_options');
    $message = isset($options['message']) ? $options['message'] : 'Congratulations! You saved {amount} in this order';
    ?>
    <textarea name="flash_offers_options[message]" rows="5" cols="50"
        class="large-text"><?php echo esc_textarea($message); ?></textarea>
    <p class="description">
        <?php esc_html_e('Enter the flash offer message to be displayed.', 'promoforge-smart-campaigns-for-woocommerce'); ?>
    </p>
    <?php
}
// Display locations field for badge  callback function
function promoforge_locations_field_callback()
{
    $options = get_option('flash_offers_options');
    $shop_loop = isset($options['locations']['shop_loop']) ? 1 : 1;
    $product_page = isset($options['locations']['product_page']) ? 1 : 1;
    $category_page = isset($options['locations']['category_page']) ? 1 : 1;
    $home_page = isset($options['locations']['home_page']) ? 1 : 0;
    $other_page = isset($options['locations']['other_page']) ? 1 : 0;
    ?>
    <label>
        <input type="checkbox" name="flash_offers_options[locations][shop_loop]" value="1" <?php checked($shop_loop, 1); ?>>
        <?php esc_html_e('Shop/Archive Pages', 'promoforge-smart-campaigns-for-woocommerce'); ?>
    </label><br>
    <label>
        <input type="checkbox" name="flash_offers_options[locations][home_page]" value="1" <?php checked($home_page, 1); ?>>
        <?php esc_html_e('Home Page', 'promoforge-smart-campaigns-for-woocommerce'); ?>
    </label><br>
    <label>
        <input type="checkbox" name="flash_offers_options[locations][category_page]" value="1" <?php checked($category_page, 1); ?>>
        <?php esc_html_e('Category Pages', 'promoforge-smart-campaigns-for-woocommerce'); ?>
    </label><br>
    <label>
        <input type="checkbox" name="flash_offers_options[locations][product_page]" value="1" <?php checked($product_page, 1); ?>>
        <?php esc_html_e('Single Product Pages', 'promoforge-smart-campaigns-for-woocommerce'); ?>
    </label><br>
    <label>
        <input type="checkbox" name="flash_offers_options[locations][other_page]" value="1" <?php checked($other_page, 1); ?>>
        <?php esc_html_e('Other Pages', 'promoforge-smart-campaigns-for-woocommerce'); ?>
    </label>
    <?php
}

// Countdown locations field
function promoforge_countdown_locations_field_callback()
{
    $options = get_option('flash_offers_options');

    $shop_loop = isset($options['countdown_locations']['shop_loop']) ? 1 : 1;
    $product_page = isset($options['countdown_locations']['product_page']) ? 1 : 1;
    $category_page = isset($options['countdown_locations']['category_page']) ? 1 : 1;
    $home_page = isset($options['countdown_locations']['home_page']) ? 1 : 0;
    $other_page = isset($options['countdown_locations']['other_page']) ? 1 : 0;
    ?>
    <label>
        <input type="checkbox" name="flash_offers_options[countdown_locations][shop_loop]" value="1" <?php checked($shop_loop, 1); ?>>
        <?php esc_html_e('Shop/Archive Pages', 'promoforge-smart-campaigns-for-woocommerce'); ?>
    </label><br>
    <label>
        <input type="checkbox" name="flash_offers_options[countdown_locations][category_page]" value="1" <?php checked($category_page, 1); ?>>
        <?php esc_html_e('Category Pages', 'promoforge-smart-campaigns-for-woocommerce'); ?>
    </label><br>
    <label>
        <input type="checkbox" name="flash_offers_options[countdown_locations][home_page]" value="1" <?php checked($home_page, 1); ?>>
        <?php esc_html_e('Home Page', 'promoforge-smart-campaigns-for-woocommerce'); ?>
    </label><br>
    <label>
        <input type="checkbox" name="flash_offers_options[countdown_locations][product_page]" value="1" <?php checked($product_page, 1); ?>>
        <?php esc_html_e('Single Product Pages', 'promoforge-smart-campaigns-for-woocommerce'); ?>
    </label><br>
    <label>
        <input type="checkbox" name="flash_offers_options[countdown_locations][other_page]" value="1" <?php checked($other_page, 1); ?>>
        <?php esc_html_e('Other Pages', 'promoforge-smart-campaigns-for-woocommerce'); ?>
    </label>
    <?php
}

// Badge style field callback function
function promoforge_bogo_offer_badge_callback()
{
    $options = get_option('flash_offers_options');
    $text = $options['bogo_offer_badge_text'] ?? 'BOGO Deal! 🔥';
    ?>
    <textarea name="flash_offers_options[bogo_offer_badge_text]" rows="2" cols="50"
        class="large-text"><?php echo esc_textarea($text); ?></textarea>
    <p class="description">
        <?php esc_html_e('Enter the BOGO offer badge text to be displayed.', 'promoforge-smart-campaigns-for-woocommerce'); ?>
    </p>
    <?php
}

// Callback: Active Badge
function promoforge_active_badge_text_callback()
{
    $options = get_option('flash_offers_options');
    $text = $options['active_badge_text'] ?? 'Hot Deal! 🔥';
    ?>
    <textarea name="flash_offers_options[active_badge_text]" rows="2" cols="50"
        class="large-text"><?php echo esc_textarea($text); ?></textarea>
    <?php
}

// Callback: Upcoming Badge
function promoforge_upcoming_badge_text_callback()
{
    $options = get_option('flash_offers_options');
    $text = $options['upcoming_badge_text'] ?? 'Coming Soon ⏳';
    ?>
    <textarea name="flash_offers_options[upcoming_badge_text]" rows="2" cols="50"
        class="large-text"><?php echo esc_textarea($text); ?></textarea>
    <?php
}

// Callback: Special Badge
function promoforge_special_badge_text_callback()
{
    $options = get_option('flash_offers_options');
    $text = $options['special_badge_text'] ?? 'Special Offer ✨';
    ?>
    <textarea name="flash_offers_options[special_badge_text]" rows="2" cols="50"
        class="large-text"><?php echo esc_textarea($text); ?></textarea>
    <?php
}

// Callback: Selections for override Price
function promoforge_render_price_type_field()
{
    $options = get_option('flash_offers_options');
    $value = $options['flash_override_type'] ?? 'regular'; ?>
    <select name="flash_offers_options[flash_override_type]">
        <option value="select"><?php esc_html_e('Select', 'promoforge-smart-campaigns-for-woocommerce'); ?></option>
        <option value="sale" <?php selected($value, 'sale'); ?>><?php esc_html_e('Sale Price', 'promoforge-smart-campaigns-for-woocommerce'); ?></option>
        <option value="regular" <?php selected($value, 'regular'); ?>><?php esc_html_e('Regular Price', 'promoforge-smart-campaigns-for-woocommerce'); ?></option>
    </select>
    <p class="description">
        <?php esc_html_e('Choose which price the offer should override.', 'promoforge-smart-campaigns-for-woocommerce'); ?>
    </p>
    <?php
}

// Callback: Selections for BOGO override Price
function promoforge_bogo_offer_render_price_type_field()
{
    $options = get_option('flash_offers_options');
    $value = $options['bogo_override_type'] ?? 'regular'; ?>
    <select name="flash_offers_options[bogo_override_type]">
        <option value="select"><?php esc_html_e('Select', 'promoforge-smart-campaigns-for-woocommerce'); ?></option>
        <option value="sale" <?php selected($value, 'sale'); ?>><?php esc_html_e('Sale Price', 'promoforge-smart-campaigns-for-woocommerce'); ?></option>
        <option value="regular" <?php selected($value, 'regular'); ?>><?php esc_html_e('Regular Price', 'promoforge-smart-campaigns-for-woocommerce'); ?></option>
    </select>
    <p class="description">
        <?php esc_html_e('Choose which price the offer should override.', 'promoforge-smart-campaigns-for-woocommerce'); ?>
    </p>
    <?php
}
// Callback: Variable Product Display Selection
function promoforge_variable_product_display_callback()
{
    $options = get_option('flash_offers_options');
    $display = $options['variable_product_display'] ?? 'default';
    ?>
    <select name="flash_offers_options[variable_product_display]">
        <option value="default" <?php selected($display, 'default'); ?>><?php esc_html_e('Default WooCommerce', 'promoforge-smart-campaigns-for-woocommerce'); ?></option>
        <option value="table" <?php selected($display, 'table'); ?>><?php esc_html_e('Table View', 'promoforge-smart-campaigns-for-woocommerce'); ?></option>
    </select>
    <p class="description">
        <?php esc_html_e('Choose how all products are displayed on single product pages.', 'promoforge-smart-campaigns-for-woocommerce'); ?>
    </p>
    <?php
}

// Callback: Countdown Format Selection
function promoforge_countdown_format_field_callback()
{
    $options = get_option('flash_offers_options');
    $format = $options['countdown_format'] ?? 'format1';
    ?>
    <select name="flash_offers_options[countdown_format]">
        <option value="format1" <?php selected($format, 'format1'); ?>><?php esc_html_e('d h m s', 'promoforge-smart-campaigns-for-woocommerce'); ?></option>
        <option value="format2" <?php selected($format, 'format2'); ?>><?php esc_html_e('Day Hours Minutes Seconds', 'promoforge-smart-campaigns-for-woocommerce'); ?></option>
        <option value="format3" <?php selected($format, 'format3'); ?>><?php esc_html_e('00:00:00:00', 'promoforge-smart-campaigns-for-woocommerce'); ?></option>
    </select>
    <p class="description">
        <?php esc_html_e('Choose how the countdown timer should be displayed.', 'promoforge-smart-campaigns-for-woocommerce'); ?>
    </p>
    <?php
}



function promoforge_get_cart_product_qty($product_id)
{

    if (!WC()->cart) {
        return 0;
    }

    $qty = 0;

    foreach (WC()->cart->get_cart() as $cart_item) {
        if ($cart_item['product_id'] == $product_id) {
            $qty += $cart_item['quantity'];
        }
    }

    return $qty;
}

// Support for WooCommerce Blocks (e.g. Block Themes, Post Title, Product Image)
add_filter('render_block', 'promoforge_block_render_badge', 10, 2);

function promoforge_block_render_badge($block_content, $block)
{
    if (empty(trim($block_content))) {
        return $block_content;
    }

    $is_product_page = is_product();
    $target_blocks = ['core/post-title', 'woocommerce/product-title', 'woocommerce/product-image', 'core/post-thumbnail'];
    
    if (in_array($block['blockName'], $target_blocks, true)) {
        global $product;
        
        // Ensure we have a product object
        if (!$product || !is_a($product, 'WC_Product')) {
            $post_id = get_the_ID();
            if ($post_id && get_post_type($post_id) === 'product') {
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                $product = wc_get_product($post_id);
            }
        }

        if ($product && is_a($product, 'WC_Product')) {
            $product_id = $product->get_id();
            
            // Check if already rendered for this product to avoid duplicates within the same render cycle
            // BUT: for loop items, we might hit multiple times for different blocks. 
            // We want primarily:
            // - On Single Product: Before Title
            // - On Loops: Before Image
            
            $should_render = false;
            if ($is_product_page && strpos($block['blockName'], 'title') !== false) {
                 $should_render = true;
            } elseif (!$is_product_page && (strpos($block['blockName'], 'image') !== false || strpos($block['blockName'], 'thumbnail') !== false)) {
                 $should_render = true;
            }

            if ($should_render) {
                 // Check if already rendered via global flag (to prevent double rendering if multiple blocks match)
                 if (!empty($GLOBALS['promoforge_badge_rendered_' . $product_id])) {
                     return $block_content;
                 }

                // Capture Flash Offer Badge
                ob_start();
                promoforge_display_flash_offer_badge();
                $badge_html = ob_get_clean();

                // Capture Flash Countdown
                ob_start();
                promoforge_show_countdown();
                $countdown_html = ob_get_clean();

                // Capture BOGO Offer Badge
                ob_start();
                if (function_exists('promoforge_display_bogo_offer_badge')) {
                    promoforge_display_bogo_offer_badge();
                }
                $bogo_badge_html = ob_get_clean();
                
                // Capture BOGO Countdown
                ob_start();
                if (function_exists('promoforge_display_bogoffers_countdown')) {
                    promoforge_display_bogoffers_countdown();
                }
                $bogo_countdown_html = ob_get_clean();

                if ($badge_html || $bogo_badge_html || $countdown_html || $bogo_countdown_html) {
                     // Set global flag to prevent duplicate rendering in hooks
                     $GLOBALS['promoforge_badge_rendered_' . $product_id] = true;
                     
                     $wrapper_class = $is_product_page ? 'wao-title-badge-wrapper' : 'wao-loop-badge-wrapper';
                     $style = 'margin-bottom: 10px; display: block;';
                     
                     return '<div class="' . $wrapper_class . '" style="' . $style . '">' . 
                            $badge_html . $bogo_badge_html . 
                            $countdown_html . $bogo_countdown_html . 
                            '</div>' . $block_content;
                }
            }
        }
    }
    
    return $block_content;
}

/**
 * Register the action hook for flash offer badge
 */
add_action('woocommerce_before_shop_loop_item', 'promoforge_display_flash_offer_badge', 5); // Raised priority to show before image
add_action('woocommerce_single_product_summary', 'promoforge_display_flash_offer_badge', 4);

function promoforge_display_flash_offer_badge()
{

    // Removed is_user_logged_in check


    global $product, $wpdb;
    if (!$product)
        return;

    $product_id = $product->get_id();
    
    // Check if valid product ID
    if (!$product_id) return;
    
    // Check if already rendered via block filter
    if (!empty($GLOBALS['promoforge_badge_rendered_' . $product_id])) {
        return;
    }
    $user_id = get_current_user_id();

    // Get active offers
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $offers = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT o.*
             FROM {$wpdb->prefix}promoforge_flash_offers o
             INNER JOIN {$wpdb->prefix}promoforge_offer_products p
                ON o.id = p.offer_id
             WHERE p.product_id = %d
             AND o.end_date > %s",
            $product_id,
            current_time('mysql')
        )
    );

    if (empty($offers))
        return;

    $valid_offer = null;

    foreach ($offers as $offer) {

        $limit = !empty($offer->use_offers) ? (int) $offer->use_offers : 1;

        // ✅ Past orders usage
        $past_usage = 0;
        if ($user_id) {
            $past_usage = promoforge_get_user_purchase_count(
                $user_id,
                $product_id,
                null,
                $offer->end_date
            );
        }

        // ✅ Cart usage
        $cart_usage = promoforge_get_cart_product_qty($product_id);

        $total_used = $past_usage + $cart_usage;

        // ❌ Limit crossed
        if ($total_used >= $limit) {
            continue;
        }

        // ✅ Offer still usable
        $valid_offer = $offer;
        break;
    }

    if (!$valid_offer)
        return;

    // Badge data
    $offer_data = promoforge_get_offer_data($product);
    if (empty($offer_data['badge_text']))
        return;

    // Location check
    $locations = $offer_data['locations'];
    $show =
        (is_shop() && !empty($locations['shop_loop'])) ||
        (is_front_page() && !empty($locations['home_page'])) ||
        (is_product_category() && !empty($locations['category_page'])) ||
        (is_product() && !empty($locations['product_page'])) ||
        !empty($GLOBALS['promoforge_special_offer_context']); // Force show for special offer shortcode

    if (!$show)
        return;

    // Inject CSS to hide default sale badge when custom badge is shown
    global $promoforge_hide_sale_badge_ids;
    $promoforge_hide_sale_badge_ids[] = $product_id;

    echo '<span class="flash-offer-badge active"
        style="background:' . esc_attr($offer_data['background_color']) . '">
        ' . esc_html($offer_data['badge_text']) . '
    </span>';
}



// Helper function to show countdown
add_action('woocommerce_before_shop_loop_item_title', 'promoforge_show_countdown', 5); // Raised priority
add_action('woocommerce_single_product_summary', 'promoforge_show_countdown', 4);
function promoforge_show_countdown()
{
    // Removed is_user_logged_in check

    global $product;
    if (!$product) {
        return;
    }
    
    // Check if already rendered via block filter
    if (!empty($GLOBALS['promoforge_badge_rendered_' . $product->get_id()])) {
        return;
    }

    $offer_data = promoforge_get_offer_data($product);

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (!$offer_data || ($offer_data['status'] === 'special' && !isset($_GET['from_offer']))) {
        return;
    }

    // Check usage limits
    if (isset($offer_data['remaining_display']) && $offer_data['remaining_display'] <= 0) {
        return;
    }

    $locations = $offer_data['countdown_locations'];
    $show_on_shop = is_shop() && !empty($locations['shop_loop']);
    $show_on_category = is_product_category() && !empty($locations['category_page']);
    $is_other_location = !is_shop() && !is_product_category() && !is_product() && !empty($locations['other_page']);
    $show_on_single = is_product() && !empty($locations['product_page']);
    $wp_timezone = wp_timezone();
    if ($show_on_shop || $show_on_category || $is_other_location || $show_on_single) {
        if ($offer_data['status'] === 'upcoming' && !empty($offer_data['start'])) {
            $start = new DateTime($offer_data['start'], $wp_timezone);
            $start->setTimezone(new DateTimeZone('UTC'));
            echo '<div class="flash-offer-countdown-timer upcoming-offer" 
                  data-start="' . esc_attr($start->format('Y-m-d\TH:i:s\Z')) . '" 
                  data-product-id="' . esc_attr($product->get_id()) . '">' . esc_html__('Starts soon', 'promoforge-smart-campaigns-for-woocommerce') . '</div>';
        } elseif (!empty($offer_data['end'])) {
            $end = new DateTime($offer_data['end'], $wp_timezone);
            $end->setTimezone(new DateTimeZone('UTC'));
            echo '<div class="flash-offer-countdown-timer" 
                  data-end="' . esc_attr($end->format('Y-m-d\TH:i:s\Z')) . '" 
                  data-product-id="' . esc_attr($product->get_id()) . '">' . esc_html__('Ending soon', 'promoforge-smart-campaigns-for-woocommerce') . '</div>';
        }
    }
}

// Remove sale badge if flash offer exists
add_filter('woocommerce_sale_flash', 'promoforge_disable_woocommerce_sale_flash', 10, 3);
function promoforge_disable_woocommerce_sale_flash($html, $post, $product)
{
    $offer_data = promoforge_get_offer_data($product);
    if ($offer_data) {
        return ''; // remove the sale badge if flash offer exists
    }
    return $html; // default badge if no flash offer
}

add_action('woocommerce_before_shop_loop_item', 'promoforge_remove_flash_sale_badge_css');
add_action('woocommerce_before_single_product_summary', 'promoforge_remove_flash_sale_badge_css');

function promoforge_remove_flash_sale_badge_css()
{
    global $product;
    if (!$product)
        return;

    // Get BOGO data
    $offer_data = promoforge_get_offer_data($product);

    // If variation has no offer, check parent
    if (empty($offer_data) && $product->is_type('variation')) {
        $parent = wc_get_product($product->get_parent_id());
        if ($parent) {
            $offer_data = promoforge_get_offer_data($parent);
        }
    }

    // ✅ Only if this product has an offer → hide badge for THIS product only
    if (!empty($offer_data)) {
        global $promoforge_hide_sale_badge_ids;
        $promoforge_hide_sale_badge_ids[] = $product->get_id();
    }
}
// Color picker field callback function
function promoforge_render_color_picker()
{
    $options = get_option('flash_offers_options');
    $badge_color = $options['badge_bg_color'] ?? '#00a99d';
    ?>
    <input type="text" name="flash_offers_options[badge_bg_color]" id="flash_offer_badge_bg_color"
        class="flash-offer-color-field" value="<?php echo esc_attr($badge_color); ?>" />
    <?php
}

// Settings page
add_action('admin_menu', function () {
    add_submenu_page(
        'offers',  // parent slug (same as we used for the Offers top-level menu)
        esc_html__('Offers Settings', 'promoforge-smart-campaigns-for-woocommerce'), // Page title
        esc_html__('Settings', 'promoforge-smart-campaigns-for-woocommerce'),        // Menu title
        'manage_options',                      // Capability
        'flash-offers-settings',               // Menu slug
        'promoforge_settings_page'           // Callback function
    );
});


// Settings page content
function promoforge_settings_page()
{
    ?>
    <div class="wrap flash-offers-settings-wrap">
        <h1><?php esc_html_e('Flash Offers Settings', 'promoforge-smart-campaigns-for-woocommerce'); ?></h1>
        <?php settings_errors(); ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('flash_offers_settings_group');
            do_settings_sections('flash-offers-settings');
            submit_button('Apply');
            ?>
        </form>
    </div>
    <?php
}

// Global flag to indicate we are rendering product summary
add_action('woocommerce_single_product_summary', function () {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $GLOBALS['promoforge_rendering_product_summary'] = true;
}, 1);
add_action('woocommerce_single_product_summary', function () {
    $GLOBALS['promoforge_rendering_product_summary'] = false;
}, 1000);
