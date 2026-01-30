jQuery(document).ready(function ($) {
    /**
     * Shortcode Logic: Redirects & Slider
     */
    // Redirect fixes for Special Offers inside Shortcode
    $(document).on('click', '.flash-special-offer-product .add_to_cart_button, .flash-special-offer-product a.woocommerce-LoopProduct-link', function (e) {
        var $productItem = $(this).closest('.flash-special-offer-product');
        var $container = $productItem.closest('.flash-special-offer-container');
        var offerId = $container.data('offer-id');

        // If no offerId found via container, try checking URL or other means if necessary
        if (!offerId) return;

        var url = $(this).attr('href');
        if (url && url.indexOf('from_offer=') === -1) {
            e.preventDefault();
            var separator = url.indexOf('?') !== -1 ? '&' : '?';
            window.location.href = url + separator + 'from_offer=' + offerId;
        }
    });

    // Auto slider for Special Offers
    if ($('.flash-special-offer-container.flash-offer-slider-enabled').length) {
        $('.flash-special-offer-container.flash-offer-slider-enabled .wao-products').each(function () {
            var $container = $(this).closest('.flash-special-offer-container');
            var columns = $container.data('columns') || 3;
            var productCount = $(this).children().length;

            if (productCount > 3) {
                $(this).slick({
                    slidesToShow: columns,
                    slidesToScroll: 1,
                    autoplay: true,
                    autoplaySpeed: 1500,
                    arrows: true,
                    dots: false,
                    infinite: true,
                    responsive: [{
                        breakpoint: 768,
                        settings: { slidesToShow: 2 }
                    }, {
                        breakpoint: 480,
                        settings: { slidesToShow: 1 }
                    }]
                });
            } else {
                $(this).addClass('wao-no-slider');
            }
        });
    }

    /**
     * Special Cart Logic: Single Product Page
     * Appends hidden input params when coming from a special offer
     */
    if (typeof wao_special_offer_params !== 'undefined' && wao_special_offer_params.offer_id) {
        var offerId = wao_special_offer_params.offer_id;

        // Add hidden field to maintain offer parameter in the cart form
        $('form.cart').append('<input type="hidden" name="from_offer" value="' + offerId + '">');

        // Handle AJAX add-to-cart redirect preserving the param
        $(document).on('added_to_cart', function (event, fragments, cart_hash, $button) {
            if (wao_special_offer_params.cart_redirect_url) {
                window.location.href = wao_special_offer_params.cart_redirect_url;
            }
        });
    }
});
