jQuery(window).on('elementor/frontend/init', function () {
    // Vehicle Card Widget Handler
    elementorFrontend.hooks.addAction('frontend/element_ready/mhm-vehicle-card.default', function ($scope, $) {
        // Widget initialization code if needed
        // For example, initializing sliders or other dynamic elements within the card
    });

    // Vehicles List Widget Handler
    elementorFrontend.hooks.addAction('frontend/element_ready/mhm-vehicles-list.default', function ($scope, $) {
        // Widget initialization code if needed
    });

    // Booking Form Widget Handler
    elementorFrontend.hooks.addAction('frontend/element_ready/mhm-booking-form.default', function ($scope, $) {
        // Widget initialization code if needed
        // The booking form itself might have its own JS initialization which should handle itself,
        // but we can add specific Elementor-related adjustments here.
    });
});
