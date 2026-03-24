jQuery(function ($) {
    function movePaymentSection() {
        // 1. Identify Source and Destination
        var $payment = $('#payment'); // The payment methods box
        var $customerDetails = $('#customer_details'); // The left column wrapper

        // 2. Safety Check
        if ($payment.length && $customerDetails.length) {
            // 3. Move Logic: Only if not already moved to the correct location
            // We check if #payment is a direct child of #customer_details or within it
            if (!$customerDetails.find('#payment').length) {
                // Detach from right, append to left (after additional fields if possible, or just append)
                // If there is an additional fields section, maybe put it after that?
                // The user request says "append to #customer_details".
                // Usually #customer_details contains col-1 (billing) and col-2 (shipping) or similar.
                // Let's simply append to #customer_details as requested.
                $payment.detach().appendTo($customerDetails);
                console.log('MHM Rentiva: Payment section moved to left column.');
            }
        }
    }

    // --- COLLAPSIBLE ADDITIONAL INFO ---
    function initCollapsibleAdditionalInfo() {
        var $container = $('.woocommerce-additional-fields');
        var $heading = $container.find('h3');
        var $content = $container.find('.woocommerce-additional-fields__field-wrapper');

        // Safety check
        if (!$heading.length || !$content.length) return;

        // 1. Initial State: Closed
        $content.hide();
        $container.addClass('mhm-accordion-closed');

        // 2. Add Toggle Trigger
        // Remove existing handlers to avoid duplicates on AJAX reload
        $heading.off('click.mhmToggle').on('click.mhmToggle', function () {
            // Toggle Content
            $content.slideToggle(300);
            // Toggle Class for Arrow Rotation
            $container.toggleClass('mhm-accordion-open mhm-accordion-closed');
        });
    }

    // Run on Init
    initCollapsibleAdditionalInfo();

    // Move payment section logic...
    movePaymentSection();

    // Run after WooCommerce updates checkout (AJAX)
    // WooCommerce triggers 'updated_checkout' on document.body
    // --- MOVE CART LINK TO TOP OF ORDER REVIEW ---
    function moveCartLink() {
        var $linkWrapper = $('.mhm-return-cart-wrapper');
        var $orderReview = $('#order_review');

        // Safety check
        if ($linkWrapper.length && $orderReview.length) {
            // Move the wrapper to the very top of the #order_review container
            // This places it above the "Product / Subtotal" table
            $linkWrapper.detach().prependTo($orderReview);

            // Adjust styling for the new location
            $linkWrapper.css({
                'margin-bottom': '20px',
                'text-align': 'right',
                'width': '100%',
                'display': 'block'
            });
        }
    }

    // Run on Init
    moveCartLink();

    // Also run on 'updated_checkout' (in case WC refreshes fragments)
    $(document.body).on('updated_checkout', function () {
        movePaymentSection();
        initCollapsibleAdditionalInfo(); // Re-init collapse logic after AJAX
        moveCartLink();
    });
});
