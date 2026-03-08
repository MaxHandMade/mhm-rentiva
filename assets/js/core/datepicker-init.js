/**
 * MHM Rentiva - Centralized Datepicker Initialization
 * 
 * Uses delegated event listeners to handle both static and dynamic inputs.
 * Ensures consistent look and feel (rv-datepicker-skin) across all forms.
 * Reads localized strings from mhmDatepickerL10n (injected by DatepickerAssets).
 * 
 * @since 4.20.x
 */
(function ($) {
    'use strict';

    // Merge server-side l10n with defaults
    var l10n = window.mhmDatepickerL10n || {};

    // Set jQuery UI Datepicker global defaults FIRST (before any instance is created)
    if ($.datepicker) {
        $.datepicker.setDefaults({
            closeText: l10n.closeText || 'Close',
            currentText: l10n.currentText || 'Today',
            monthNames: l10n.monthNames || undefined,
            dayNamesMin: l10n.dayNamesMin || undefined,
            firstDay: parseInt(l10n.firstDay, 10) || 1,
            isRTL: l10n.isRTL === '1' || l10n.isRTL === true
        });
    }

    var config = {
        dateFormat: 'yy-mm-dd',
        minDate: 0,
        showButtonPanel: true,
        closeText: l10n.closeText || 'Close',
        currentText: l10n.currentText || 'Today',
        monthNames: l10n.monthNames || undefined,
        dayNamesMin: l10n.dayNamesMin || undefined,
        firstDay: parseInt(l10n.firstDay, 10) || 1,
        isRTL: l10n.isRTL === '1' || l10n.isRTL === true,
        showOtherMonths: true,
        selectOtherMonths: true,
        beforeShow: function (input, inst) {
            $('#ui-datepicker-div').addClass('rv-datepicker-skin');
        }
    };

    var initDatepicker = function (selector) {
        $(selector).each(function () {
            var $this = $(this);
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
