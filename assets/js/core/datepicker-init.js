/**
 * MHM Rentiva - Centralized Datepicker Initialization
 * 
 * Uses delegated event listeners to handle both static and dynamic inputs.
 * Ensures consistent look and feel (rv-datepicker-skin) across all forms.
 * 
 * @since 4.20.x
 */
(function ($) {
    'use strict';

    const initDatepicker = function (selector) {
        const config = {
            dateFormat: 'yy-mm-dd',
            minDate: 0,
            showButtonPanel: true,
            closeText: 'Close',
            currentText: 'Today',
            beforeShow: function (input, inst) {
                $('#ui-datepicker-div').addClass('rv-datepicker-skin');
            }
        };

        $(selector).each(function () {
            const $this = $(this);
            if (!$this.hasClass('hasDatepicker')) {
                $this.datepicker(config);
            }
        });
    };

    // 1. Initial Load
    $(document).ready(function () {
        initDatepicker('.js-datepicker');
    });

    // 2. Focus-based Dynamic Initialization
    $(document).on('focus', '.js-datepicker:not(.hasDatepicker)', function () {
        initDatepicker(this);
    });

})(jQuery);
