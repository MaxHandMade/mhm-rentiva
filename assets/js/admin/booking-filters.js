/**
 * Auto-submit filters on change for Bookings list
 */
(function($){
    'use strict';
    $(function(){
        if (typeof pagenow === 'undefined' || pagenow !== 'edit-vehicle_booking') return;
        var $form = $('#posts-filter');
        var selectors = [
            'select[name="m"],
            select[name="mhm_booking_status"],
            select[name="mhm_payment_status"],
            select[name="mhm_payment_gateway"]'
        ];
        $(selectors.join(',')).on('change', function(){
            // Keep focus on list
            $form.find('input[name="filter_action"]').trigger('click');
        });
        // Enter in Booking ID / License Plate submits
        $('input[name="mhm_booking_id"], input[name="mhm_license_plate"]').on('keydown', function(e){
            if (e.key === 'Enter') {
                e.preventDefault();
                $form.find('input[name="filter_action"]').trigger('click');
            }
        });
    });
})(jQuery);


