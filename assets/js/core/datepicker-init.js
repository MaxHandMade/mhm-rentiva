/**
 * MHM Rentiva - Centralized Datepicker Initialization
 *
 * Uses delegated event listeners to handle both static and dynamic inputs.
 * Ensures consistent look and feel across all forms: rv-datepicker-skin, and
 * the rvs skin (rvs-shell rvs-dp) for fields inside the front-end standard.
 * Reads localized strings from mhmDatepickerL10n (injected by DatepickerAssets).
 *
 * The field shows the site's date format (l10n.dateFormat, WordPress
 * `date_format` converted by DatepickerAssets::js_date_format()) and submits
 * Y-m-d: a field the server rendered as a pair (visible text in the site format,
 * then a hidden named `data-rvs-date-value` input) is adopted as it is;
 * otherwise, on init, a named `.js-datepicker` hands its `name` to a hidden input
 * right after it (data-rvs-date-value), which jQuery UI keeps in Y-m-d
 * (altField/altFormat). The visible input keeps its id, classes and data-*
 * hooks, so labels, minDate and change handlers stay on it. A server-filled
 * Y-m-d value is shown in the site format on init.
 *
 * Scripts that read or write a date use window.mhmRentivaDatepicker.value() /
 * .setValue() -- always Y-m-d, whatever the field shows.
 *
 * @since 4.20.x
 */
(function ($) {
    'use strict';

    // Merge server-side l10n with defaults
    var l10n = window.mhmDatepickerL10n || {};

    var ISO = 'yy-mm-dd';
    var displayFormat = l10n.dateFormat || ISO;

    // Names a site format may show (MM, M, DD, D).
    var names = {
        monthNames: l10n.monthNames || undefined,
        monthNamesShort: l10n.monthNamesShort || undefined,
        dayNames: l10n.dayNames || undefined,
        dayNamesShort: l10n.dayNamesShort || undefined
    };

    // Set jQuery UI Datepicker global defaults FIRST (before any instance is created)
    if ($.datepicker) {
        $.datepicker.setDefaults({
            closeText: l10n.closeText || 'Close',
            currentText: l10n.currentText || 'Today',
            monthNames: names.monthNames,
            monthNamesShort: names.monthNamesShort,
            dayNames: names.dayNames,
            dayNamesShort: names.dayNamesShort,
            dayNamesMin: l10n.dayNamesMin || undefined,
            firstDay: parseInt(l10n.firstDay, 10) || 1,
            isRTL: l10n.isRTL === '1' || l10n.isRTL === true
        });
    }

    var config = {
        dateFormat: displayFormat,
        altFormat: ISO,
        minDate: 0,
        showButtonPanel: true,
        closeText: l10n.closeText || 'Close',
        currentText: l10n.currentText || 'Today',
        monthNames: names.monthNames,
        monthNamesShort: names.monthNamesShort,
        dayNames: names.dayNames,
        dayNamesShort: names.dayNamesShort,
        dayNamesMin: l10n.dayNamesMin || undefined,
        firstDay: parseInt(l10n.firstDay, 10) || 1,
        isRTL: l10n.isRTL === '1' || l10n.isRTL === true,
        showOtherMonths: true,
        selectOtherMonths: true,
        // One popup (#ui-datepicker-div, on <body>) serves every field on the
        // page. A field inside the front-end standard (.rvs-shell) dresses it in
        // the rvs skin (rvs-datepicker.css) -- the booking form's look -- and any
        // other field takes the skin off again. Set on show, not removed on
        // close: the popup fades out in the look it was opened with.
        beforeShow: function (input, inst) {
            var $widget = inst && inst.dpDiv ? inst.dpDiv : $(input).datepicker('widget');
            var rvs = $(input).closest('.rvs-shell').length > 0;
            $widget.addClass('rv-datepicker-skin').toggleClass('rvs-shell rvs-dp', rvs);
        }
    };

    function parseIso(value) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || '').trim());
        if (!m) {
            return null;
        }
        var date = new Date(+m[1], +m[2] - 1, +m[3]);
        return date.getMonth() === +m[2] - 1 ? date : null;
    }

    function toIso(date) {
        var pad = function (n) {
            return String(n).padStart(2, '0');
        };
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    }

    function display(date) {
        return $.datepicker.formatDate(displayFormat, date, names);
    }

    // The visible text in the site format, or null.
    function parseDisplay(text) {
        try {
            return $.datepicker.parseDate(displayFormat, text, names) || null;
        } catch (e) {
            return null;
        }
    }

    // The hidden Y-m-d input of a prepared field.
    function valueField($input) {
        return $input.data('rvsDateValue') || null;
    }

    /**
     * Splits a field once: the hidden input takes the name and the Y-m-d value,
     * the visible one shows the site format.
     */
    function prepare($input) {
        if (valueField($input) || !$.datepicker) {
            return;
        }
        // The server rendered the pair already (templates call
        // DatepickerAssets::display_date()): adopt its hidden value and show
        // that date -- the same text the server printed, unless the page and
        // the format disagree (a cached page), when the value wins.
        var $rendered = $input.next('input[type="hidden"][data-rvs-date-value]');
        if ($rendered.length && !$input.attr('name')) {
            var stored = parseIso($rendered.val());
            $input.data('rvsDateValue', $rendered);
            $input.val(stored ? display(stored) : '');
            return;
        }
        var date = parseIso($input.val());
        var $value = $('<input type="hidden" data-rvs-date-value>');
        var name = $input.attr('name');
        if (name) {
            $value.attr('name', name);
            $input.removeAttr('name');
        }
        $value.val(date ? toIso(date) : '');
        $input.after($value).data('rvsDateValue', $value);
        if (date) {
            $input.val(display(date));
        }
    }

    var initDatepicker = function (selector) {
        $(selector).each(function () {
            var $this = $(this);
            if (!$this.hasClass('hasDatepicker')) {
                prepare($this);
                $this.datepicker($.extend({}, config, { altField: valueField($this) || '' }));
            }
        });
    };

    // A typed date: a valid one is stored, an emptied field clears the value,
    // anything else shows the stored date again (the field never disagrees
    // with what it submits).
    $(document).on('change', '.js-datepicker', function () {
        var $input = $(this);
        var $value = valueField($input);
        if (!$value) {
            return;
        }
        var text = String($input.val() || '').trim();
        if (text === '') {
            $value.val('');
            return;
        }
        var date = parseDisplay(text);
        if (date) {
            $value.val(toIso(date));
            return;
        }
        var stored = parseIso($value.val());
        $input.val(stored ? display(stored) : '');
    });

    window.mhmRentivaDatepicker = {
        init: initDatepicker,

        /**
         * The field's date as Y-m-d ('' when empty). Read from what the field
         * shows, so a change handler bound before this script's still sees a
         * date just typed.
         */
        value: function (input) {
            var $input = $(input).first();
            var $value = valueField($input);
            if (!$value) {
                return String($input.val() || '');
            }
            var text = String($input.val() || '').trim();
            if (text === '') {
                return '';
            }
            var date = parseDisplay(text);
            return date ? toIso(date) : String($value.val() || '');
        },

        /**
         * Sets the field's date from Y-m-d ('' clears it). No change event.
         */
        setValue: function (input, iso) {
            var $input = $(input).first();
            var $value = valueField($input);
            var date = parseIso(iso);
            if (!$value) {
                $input.val(date ? toIso(date) : '');
                return;
            }
            $value.val(date ? toIso(date) : '');
            $input.val(date ? display(date) : '');
        }
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
