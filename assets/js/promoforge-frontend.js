jQuery(document).ready(function ($) {
    /**
     * Shortcode Logic: Redirects & Slider
     */
    // Redirect fixes for Special Offers inside Shortcode
    $(document).on('click', '.promoforge-special-offer-product .add_to_cart_button, .promoforge-special-offer-product a.woocommerce-LoopProduct-link', function (e) {
        var $productItem = $(this).closest('.promoforge-special-offer-product');
        var $container = $productItem.closest('.promoforge-special-offer-container');
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
    if ($('.promoforge-special-offer-container.promoforge-offer-slider-enabled').length) {
        $('.promoforge-special-offer-container.promoforge-offer-slider-enabled .promoforge-products').each(function () {
            var $container = $(this).closest('.promoforge-special-offer-container');
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
                $(this).addClass('promoforge-no-slider');
            }
        });
    }

    /**
     * Special Cart Logic: Single Product Page
     * Appends hidden input params when coming from a special offer
     */
    if (typeof promoforge_special_offer_params !== 'undefined' && promoforge_special_offer_params.offer_id) {
        var offerId = promoforge_special_offer_params.offer_id;

        // Add hidden field to maintain offer parameter in the cart form
        $('form.cart').append('<input type="hidden" name="from_offer" value="' + offerId + '">');

        // Handle AJAX add-to-cart redirect preserving the param
        $(document).on('added_to_cart', function (event, fragments, cart_hash, $button) {
            if (promoforge_special_offer_params.cart_redirect_url) {
                window.location.href = promoforge_special_offer_params.cart_redirect_url;
            }
        });
    }
});

function updateQuantity(id) {
    var qtyInput = document.getElementById('qty_' + id);
    var hiddenInput = document.getElementById('quantity_' + id);
    if (qtyInput && hiddenInput) {
        hiddenInput.value = qtyInput.value;
    }
}
