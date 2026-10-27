/**
 * Booking form (`[rentiva_booking_form]`, Aşama 2 PR C).
 *
 * Markup: templates/shortcodes/booking-form.php and
 * templates/partials/selected-vehicle-summary.php. Every lookup starts at the
 * instance root ([data-rvs-booking-form]) and goes through data-rvs-* hooks, so
 * two forms on one page never touch each other.
 *
 * - Add-on quantities come from the steppers (rvs-stepper.js): a stepper at
 *   zero disables its hidden fields, so the add-on is not in the request.
 * - The summary takes the breakdown the server renders (`breakdown_html`);
 *   without it the display_* amounts build a plain one (older answers).
 * - On a phone the summary is a bottom band; its toggle opens the summary.
 * - The jQuery UI datepicker popup wears `rvs-shell rvs-dp` from the moment a
 *   booking date field opens it until a field outside the shell opens it (the
 *   popup is shared with other forms; datepicker-init.js takes the skin off).
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
        if (window.mhmRentivaBookingForm && window.mhmRentivaBookingForm.datepicker_options) {
            $.datepicker.setDefaults(MHMRentivaBookingForm.siteDatepickerOptions(window.mhmRentivaBookingForm.datepicker_options));
        }

        $('[data-rvs-booking-form]').each(function () {
            new MHMRentivaBookingForm($(this));
        });
    });

    class MHMRentivaBookingForm {
        constructor($container) {
            this.container = $container;
            this.form = this.container.find('form').first();
            this.submitBtn = this.container.find('[data-rvs-submit]');

            // The template renders submit disabled in an editor preview; nothing
            // here may switch it on.
            this.locked = this.submitBtn.prop('disabled') === true;

            // Set by checkAvailability() when the vehicle is taken; nothing else
            // may re-enable submit while it is set.
            this.availabilityBlocked = false;
            this.submitting = false;

            // Blocked dates for the currently selected vehicle (populated via AJAX).
            this.blockedDates = [];

            this.init();
        }

        init() {
            if (!this.form.length) return;

            if (window.MHMRvsStepper) {
                window.MHMRvsStepper.init(this.container[0]);
            }

            // A vehicle known on load (Case A, or one picked from a list page).
            const preSelectedVehicleId = this.container.attr('data-vehicle-id');
            if (preSelectedVehicleId) {
                const $select = this.hook('vehicle-select');
                if ($select.length) {
                    $select.val(preSelectedVehicleId);
                    this.updateVehiclePreview($select[0]);
                }
                this.fetchAndApplyBlockedDates(preSelectedVehicleId);
            }

            this.initializeDatePickers();
            this.bindEvents();
            this.setupDateValidation();
            this.setupFormValidation();
            this.initializeDefaults();
        }

        // The instance's element(s) carrying data-rvs-<name>.
        hook(name) {
            return this.container.find('[data-rvs-' + name + ']');
        }

        bindEvents() {
            // The only submit path: the native submit is always prevented.
            this.form.on('submit', (e) => {
                e.preventDefault();
                this.submitForm();
            });
            this.submitBtn.on('click', (e) => {
                e.preventDefault();
                this.submitForm();
            });

            // Vehicle chosen from the select (Case B).
            this.container.on('change', '[data-rvs-vehicle-select]', (e) => {
                const vehicleId = $(e.target).val();
                if (vehicleId) {
                    this.container.attr('data-vehicle-id', vehicleId);
                } else {
                    this.container.removeAttr('data-vehicle-id');
                }
                this.updateVehiclePreview(e.target);
                this.fetchAndApplyBlockedDates(vehicleId);
                if (this.validateRentalLimits()) {
                    this.autoCheckAvailability();
                    this.autoCalculatePrice();
                }
            });

            // Dates and time: limits first, then availability and the quote.
            this.container.on('change', '[data-rvs-pickup-date], [data-rvs-dropoff-date], [data-rvs-pickup-time]', () => {
                this.updateSummaryDates();
                if (this.validateRentalLimits()) {
                    this.autoCheckAvailability();
                    this.autoCalculatePrice();
                }
            });

            // The return time follows the pickup time: the hidden copy is posted,
            // the disabled select only shows it.
            this.container.on('change', 'select[data-rvs-pickup-time]', (e) => {
                this.syncDropoffTime($(e.target).val());
            });
            this.syncDropoffTime(this.hook('pickup-time').val());

            // Add-ons: a box sold once, or a stepper.
            this.container.on('change', 'input[type="checkbox"][name="addons[]"]', () => {
                this.autoCalculatePrice();
            });
            this.container.on('rvs:stepper-change', () => {
                this.autoCalculatePrice();
            });

            // The phone's band: open or close the full summary.
            this.container.on('click', '[data-rvs-summary-toggle]', (e) => {
                this.toggleSummary($(e.currentTarget));
            });
        }

        syncDropoffTime(time) {
            const value = time || '';
            this.hook('dropoff-time-display').val(value);
            // Only a select drives it: a hidden pickup time (show_time_select="0")
            // has its own fixed return time.
            if (this.hook('pickup-time').is('select')) {
                this.hook('dropoff-time').val(value);
            }
        }

        toggleSummary($toggle) {
            const open = $toggle.attr('aria-expanded') !== 'true';
            $toggle.attr('aria-expanded', open ? 'true' : 'false');
            this.hook('summary-toggle-label').text(this.getMessage(open ? 'hide_summary' : 'show_summary'));
        }

        // Submit is on unless the form is a preview, a booking is being sent,
        // or the availability check said the vehicle is taken.
        updateButtonState() {
            this.submitBtn.prop('disabled', !!(this.locked || this.submitting || this.availabilityBlocked));
        }

        setupDateValidation() {
            // The site's today (localized), not the browser's UTC date.
            const todayStr = MHMRentivaBookingForm.siteToday();
            const pickupDate = this.hook('pickup-date');
            const dropoffDate = this.hook('dropoff-date');

            // Initial restrictions
            pickupDate.attr('min', todayStr);
            dropoffDate.attr('min', MHMRentivaBookingForm.addDays(todayStr, 1));

            // Update dropoff date when pickup date changes
            pickupDate.on('change', (e) => {
                const pickupValue = $(e.target).val();
                if (pickupValue) {
                    let dateObj = pickupDate.datepicker('getDate');

                    // Validation check before conversion
                    if (dateObj && !isNaN(dateObj.getTime())) {
                        // The picker date is a calendar date at local midnight: read
                        // its local Y-m-d, add the day on the string.
                        const pickupIso = MHMRentivaBookingForm.localIso(dateObj);
                        const minStr = MHMRentivaBookingForm.addDays(pickupIso, 1);
                        const nextDay = MHMRentivaBookingForm.localDate(minStr);

                        // Update min attribute (for hidden behavior) and datepicker option
                        try {
                            dropoffDate.attr('min', minStr);
                            dropoffDate.datepicker('option', 'minDate', nextDay);
                        } catch (err) {
                            console.warn('Date conversion failed, skipping min update');
                        }

                        // Clear dropoff date if it's before or equal to pickup date
                        const dropoffVal = dropoffDate.datepicker('getDate');
                        if (dropoffVal && dropoffVal <= dateObj) {
                            dropoffDate.val('').datepicker('setDate', null);
                        }

                        // Per-form "Default Days": prefill an empty return date N days
                        // after the pickup. Never overwrites a return date already chosen.
                        const defaultDays = this.defaultReturnDays();
                        if (defaultDays > 0 && !dropoffDate.val()) {
                            const prefill = MHMRentivaBookingForm.localDate(MHMRentivaBookingForm.addDays(pickupIso, defaultDays));
                            dropoffDate.datepicker('setDate', prefill);
                        }
                    }
                }

                this.autoCheckAvailability();
            });

            dropoffDate.on('change', () => {
                this.autoCheckAvailability();
            });
        }

        setupFormValidation() {
            this.form.find('input[required], select[required]').on('blur', (e) => {
                this.validateField(e.target);
            });
        }

        initializeDatePickers() {
            if (window.mhmRentivaBookingForm && window.mhmRentivaBookingForm.datepicker_options) {
                const self = this;
                const options = {
                    ...MHMRentivaBookingForm.siteDatepickerOptions(window.mhmRentivaBookingForm.datepicker_options),
                    onSelect: function () {
                        const $this = $(this);
                        setTimeout(() => {
                            $this.trigger('change');
                            $this.datepicker('hide');
                            $this.blur();
                        }, 50);
                    },
                    beforeShowDay: function (date) {
                        // Disable any date that is in the blocked list for the selected vehicle.
                        if ( self.blockedDates && self.blockedDates.length ) {
                            const pad = function ( n ) { return n < 10 ? '0' + n : n; };
                            const dateStr = date.getFullYear() + '-' + pad( date.getMonth() + 1 ) + '-' + pad( date.getDate() );
                            if ( self.blockedDates.indexOf( dateStr ) !== -1 ) {
                                return [ false, 'rv-blocked-date', '' ];
                            }
                        }
                        return [ true, '', '' ];
                    },
                    beforeShow: function (input, inst) {
                        // The popup lives on <body>, outside the shell: it takes the
                        // shell's tokens and the rvs-dp rules while this field has it.
                        $(input).datepicker('widget').addClass('rvs-shell rvs-dp');
                        setTimeout(() => {
                            const $btnPane = $(inst.dpDiv).find('.ui-datepicker-buttonpane');
                            $btnPane.find('.ui-datepicker-current').off('click').on('click', function () {
                                const today = MHMRentivaBookingForm.localDate(MHMRentivaBookingForm.siteToday());
                                const formatted = $.datepicker.formatDate(inst.settings.dateFormat || 'yy-mm-dd', today);
                                $(input).val(formatted).trigger('change');
                                $(input).datepicker('hide');
                                $(input).blur();
                            });
                        }, 10);
                    }
                    // No onClose removal: jQuery UI calls onClose as the fade-out
                    // starts, and without the skin the closing popup was painted by
                    // datepicker-custom.css where that sheet also loads. One popup
                    // serves every field on the page; a field outside the shell
                    // takes the skin off when it opens (datepicker-init.js).
                };

                this.hook('pickup-date').add(this.hook('dropoff-date')).each(function () {
                    const $input = $(this);
                    const originalValue = $input.val();

                    if (window.mhmRentivaBookingForm?.config?.advance_booking_days) {
                        // A '+Nd' string would count from the browser's today.
                        options.maxDate = MHMRentivaBookingForm.localDate(MHMRentivaBookingForm.addDays(MHMRentivaBookingForm.siteToday(), Number(window.mhmRentivaBookingForm.config.advance_booking_days)));
                    }

                    // Destroy existing instances to prevent "Missing instance data" errors
                    if ($input.hasClass('hasDatepicker') || $input.data('datepicker')) {
                        try { $input.datepicker('destroy'); } catch (e) { }
                        $input.removeClass('hasDatepicker').removeData('datepicker').off('.datepicker');
                    }

                    $input.datepicker(options);

                    if (originalValue) {
                        try {
                            let dateObj;
                            if (originalValue.match(/^\d{4}-\d{2}-\d{2}$/)) {
                                dateObj = $.datepicker.parseDate('yy-mm-dd', originalValue);
                            } else {
                                dateObj = $.datepicker.parseDate(options.dateFormat || 'yy-mm-dd', originalValue);
                            }
                            $input.datepicker('setDate', dateObj);
                        } catch (e) {
                            console.error('Date parsing failed:', e);
                        }
                    }
                });
            }
        }

        initializeDefaults() {
            // Dates prefilled from the URL (search-to-booking): quote at once so
            // the summary is not blank on load.
            if (this.hook('pickup-date').val() && this.hook('dropoff-date').val()) {
                if (this.validateRentalLimits()) {
                    this.autoCheckAvailability();
                    this.autoCalculatePrice();
                }
            }
        }

        /**
         * Fetch blocked dates for a given vehicle from the server and refresh the
         * jQuery UI datepicker instances so those dates become unselectable.
         *
         * @param {string|number} vehicleId
         */
        fetchAndApplyBlockedDates( vehicleId ) {
            if ( ! vehicleId ) {
                this.blockedDates = [];
                this.refreshDatepickerBlockedDates();
                return;
            }

            var restUrl = ( window.mhmRentivaBookingForm && window.mhmRentivaBookingForm.restUrl )
                ? window.mhmRentivaBookingForm.restUrl
                : '';
            if ( ! restUrl ) {
                this.blockedDates = [];
                this.refreshDatepickerBlockedDates();
                return;
            }

            $.ajax( {
                url:    restUrl + 'vehicles/' + parseInt( vehicleId, 10 ) + '/blocked-dates',
                method: 'GET',
                success: ( response ) => {
                    if ( response && response.success && Array.isArray( response.data ) ) {
                        this.blockedDates = response.data;
                    } else {
                        this.blockedDates = [];
                    }
                    this.refreshDatepickerBlockedDates();
                },
                error: () => {
                    this.blockedDates = [];
                    this.refreshDatepickerBlockedDates();
                }
            } );
        }

        /**
         * Refresh this form's datepickers so the updated blockedDates list takes
         * effect immediately.
         */
        refreshDatepickerBlockedDates() {
            this.hook( 'pickup-date' ).add( this.hook( 'dropoff-date' ) ).each( function () {
                const $input = $( this );
                if ( $input.hasClass( 'hasDatepicker' ) ) {
                    try {
                        $input.datepicker( 'refresh' );
                    } catch ( e ) {
                        // Datepicker not yet initialised; beforeShowDay will apply on first open.
                    }
                }
            } );
        }

        /**
         * Case B: the summary's vehicle frame filled from the chosen <option>
         * (data-image, data-features, data-price, data-permalink).
         */
        updateVehiclePreview(selectElement) {
            const $option = $(selectElement).find('option:selected');
            const $frame = this.hook('summary-vehicle');
            const $change = this.hook('change');

            if (!$option.val()) {
                $frame.prop('hidden', true);
                $change.prop('hidden', true).removeAttr('href');
                return;
            }

            const features = $option.data('features');
            this.showVehicle({
                title: $option.text().split(' (')[0],
                image: $option.attr('data-image') || '',
                specs: Array.isArray(features) ? features.filter(Boolean).join(' · ') : '',
                price: $option.attr('data-price'),
                permalink: $option.attr('data-permalink') || ''
            });
        }

        /**
         * Puts a vehicle into the summary: title, image, specs, the daily price
         * and the "Change" link back to its page. Values are set as text and
         * attributes, never parsed as markup.
         */
        showVehicle(vehicle) {
            const $frame = this.hook('summary-vehicle');
            if ($frame.length) {
                $frame.find('.rvs-booking__vehicle-title').text(vehicle.title || '');
                const $img = $frame.find('.rvs-booking__img');
                if (vehicle.image) {
                    $img.attr({ src: vehicle.image, alt: vehicle.title || '' }).removeAttr('srcset sizes').prop('hidden', false);
                } else {
                    $img.prop('hidden', true);
                }
                const $specs = $frame.find('.rvs-booking__specs');
                $specs.text(vehicle.specs || '').prop('hidden', !vehicle.specs);
                $frame.prop('hidden', false);
            }

            if (vehicle.price !== undefined && vehicle.price !== '') {
                this.hook('daily').text(this.formatMoney(vehicle.price));
            }

            const $change = this.hook('change');
            if (vehicle.permalink) {
                $change.attr('href', vehicle.permalink).prop('hidden', false);
            } else {
                $change.prop('hidden', true).removeAttr('href');
            }
        }

        // The summary's window line comes from each quote answer (window_text).
        // A window that lost a date has no line, and a quote still in flight for
        // the old window must not bring it back.
        updateSummaryDates() {
            if (this.hook('pickup-date').val() && this.hook('dropoff-date').val()) {
                return;
            }
            this.hook('summary-dates').text('');
            if (this.quoteSeq) {
                this.quoteSeq += 1;
                this.showLoading(false);
            }
        }

        calculatePrice() {
            const formData = this.getFormData();

            if (!this.validateCalculationData(formData)) {
                return;
            }

            this.showLoading(true);
            this.hideMessages();

            // Only the newest quote may speak: a burst of stepper presses sends
            // several, and a late older answer must not overwrite a newer one.
            const quoteSeq = this.quoteSeq = (this.quoteSeq || 0) + 1;

            const requestData = new URLSearchParams();
            requestData.append('action', 'mhmrentiva_calculate_price');
            requestData.append('nonce', window.mhmRentivaBookingForm?.nonce || '');
            requestData.append('vehicle_id', formData.vehicle_id);
            requestData.append('pickup_date', formData.pickup_date);
            requestData.append('dropoff_date', formData.dropoff_date);
            // The window the submission books: without the time the server
            // priced 10:00, and a pickup on another UTC weekday (00:00-02:59
            // in Istanbul) was quoted one total and charged another.
            if (formData.pickup_time) {
                requestData.append('pickup_time', formData.pickup_time);
            }
            requestData.append('payment_type', formData.payment_type);
            // No pickup location: the server takes the car's own point (PR S).
            // Display only: a form with enable_deposit="0" names no deposit.
            requestData.append('enable_deposit', this.container.attr('data-rvs-deposit') === '0' ? '0' : '1');

            if (formData.addons.length > 0) {
                formData.addons.forEach(addonId => {
                    requestData.append('addons[]', addonId);
                });
            } else {
                // The server filters the empty placeholder out.
                requestData.append('addons[]', 0);
            }
            this.appendAddonQuantities(requestData, formData.addon_qty);

            $.ajax({
                url: window.mhmRentivaBookingForm?.ajax_url || '',
                type: 'POST',
                data: requestData.toString(),
                contentType: 'application/x-www-form-urlencoded',
                traditional: true,
                success: (response) => {
                    if (quoteSeq !== this.quoteSeq) {
                        return;
                    }
                    this.showLoading(false);

                    if (response.success) {
                        this.updatePriceDisplay(response.data);
                        // The availability check runs alongside this request; a
                        // price answer must not undo its "unavailable".
                        this.updateButtonState();
                    } else {
                        this.showError(response.data?.message || this.getMessage('error_occurred'));
                    }
                },
                error: () => {
                    if (quoteSeq !== this.quoteSeq) {
                        return;
                    }
                    this.showLoading(false);
                    this.showError(this.getMessage('error_occurred'));
                }
            });
        }

        appendAddonQuantities(requestData, quantities) {
            Object.keys(quantities || {}).forEach(addonId => {
                requestData.append('addon_qty[' + addonId + ']', quantities[addonId]);
            });
        }

        autoCalculatePrice() {
            clearTimeout(this.autoCalculateTimeout);
            this.autoCalculateTimeout = setTimeout(() => {
                const formData = this.getFormData();
                if (this.validateCalculationData(formData)) {
                    this.calculatePrice();
                }
            }, 100);
        }

        autoCheckAvailability() {
            if (this.autoAvailabilityTimeout) {
                clearTimeout(this.autoAvailabilityTimeout);
            }

            this.autoAvailabilityTimeout = setTimeout(() => {
                const formData = this.getFormData();

                // Vehicle and both dates (time is optional for the quote).
                if (formData.vehicle_id && formData.pickup_date && formData.dropoff_date) {
                    this.calculatePrice();
                    if (formData.pickup_time && formData.dropoff_time) {
                        this.checkAvailability();
                    }
                }
            }, 300);
        }

        /**
         * The request contract (keys(addon_qty) ⊆ addons): an add-on is in when
         * its addons[] field is enabled -- a ticked box, a stepper above zero or
         * the hidden field of a required one (its box is disabled and nameless).
         * A quantity rides along only for an add-on that is in.
         */
        getFormData() {
            const $c = this.container;
            const addons = [];
            const addonQty = {};

            $c.find('input[name="addons[]"]').each(function () {
                if (this.disabled || (this.type === 'checkbox' && !this.checked)) {
                    return;
                }
                const addonId = parseInt(this.value, 10);
                if (addonId > 0 && addons.indexOf(addonId) === -1) {
                    addons.push(addonId);
                }
            });

            $c.find('input[name^="addon_qty["]').each(function () {
                const match = /^addon_qty\[(\d+)\]$/.exec(this.name);
                if (this.disabled || !match) {
                    return;
                }
                const addonId = parseInt(match[1], 10);
                if (addons.indexOf(addonId) !== -1) {
                    addonQty[addonId] = parseInt(this.value, 10) || 1;
                }
            });

            // The root's data-vehicle-id (Case A, or set on select), else the field.
            let vehicle_id = $c.attr('data-vehicle-id');
            if (!vehicle_id) {
                vehicle_id = this.hook('vehicle-select').val();
            }
            if (!vehicle_id) {
                vehicle_id = $c.find('input[name="vehicle_id"]').val();
            }

            const pickupTime = this.hook('pickup-time').val();
            const field = (name) => $c.find('input[name="' + name + '"]').val();

            const data = {
                vehicle_id: vehicle_id,
                pickup_date: this.hook('pickup-date').val(),
                dropoff_date: this.hook('dropoff-date').val(),
                pickup_time: pickupTime,
                // The return time is the pickup time (the server holds it so too).
                dropoff_time: this.hook('dropoff-time').val() || pickupTime,
                customer_first_name: field('customer_first_name'),
                customer_last_name: field('customer_last_name'),
                customer_email: field('customer_email'),
                customer_phone: field('customer_phone'),
                addons: addons,
                addon_qty: addonQty,
                payment_type: field('payment_type') || 'deposit',
                // No pickup location: the server takes it from the car.
                redirect_url: $c.attr('data-redirect-url')
            };

            // Post-process dates to ISO format for server compatibility
            const isoPickup = this.getIsoDate(data.pickup_date);
            const isoDropoff = this.getIsoDate(data.dropoff_date);

            if (isoPickup) data.pickup_date = isoPickup;
            if (isoDropoff) data.dropoff_date = isoDropoff;

            return data;
        }

        /**
         * Converts formatted date string to ISO (yy-mm-dd)
         */
        getIsoDate(dateStr) {
            if (!dateStr) return null;
            try {
                const options = window.mhmRentivaBookingForm?.datepicker_options || {};
                const currentFormat = options.dateFormat || 'yy-mm-dd';

                if (currentFormat === 'yy-mm-dd') return dateStr;

                const dateObj = $.datepicker.parseDate(currentFormat, dateStr);
                return $.datepicker.formatDate('yy-mm-dd', dateObj);
            } catch (e) {
                return dateStr;
            }
        }

        validateCalculationData(data) {
            if (!data.vehicle_id) {
                this.showError(this.getMessage('please_select_vehicle'));
                return false;
            }

            if (!data.pickup_date || !data.dropoff_date) {
                this.showError(this.getMessage('please_enter_dates'));
                return false;
            }

            // getFormData() already turned the dates into ISO.
            try {
                const pickup = $.datepicker.parseDate('yy-mm-dd', data.pickup_date);
                const dropoff = $.datepicker.parseDate('yy-mm-dd', data.dropoff_date);

                if (dropoff <= pickup) {
                    this.showError(this.getMessage('dropoff_after_pickup'));
                    return false;
                }
            } catch (e) {
                return false;
            }

            return true;
        }

        // The summary's breakdown slot: the container, not the partial's own
        // [data-rvs-breakdown] that a quote puts inside it.
        breakdownBox() {
            return this.container.find('[data-rvs-summary] > [data-rvs-breakdown]').first();
        }

        updatePriceDisplay(data) {
            const $box = this.breakdownBox();

            if (typeof data.breakdown_html === 'string' && data.breakdown_html !== '') {
                // Rendered and escaped by the server (partials/rvs/breakdown.php).
                $box.html(data.breakdown_html);
            } else {
                $box.empty().append(this.buildFallbackBreakdown(data));
            }

            this.updateBand(data);

            // Display only, server-built: the window line ("… → … · 3 days") and
            // the "Change" link to the vehicle page with this window.
            if (typeof data.window_text === 'string') {
                this.hook('summary-dates').text(data.window_text);
            }
            // The chosen car's pickup point ("Pickup and return: X"): Case B
            // learns the car only here. Text, never markup.
            if (typeof data.location_name === 'string') {
                const $where = this.hook('summary-location');
                $where.find('[data-rvs-summary-where]').text(data.location_name);
                $where.prop('hidden', data.location_name === '');
            }
            if (typeof data.change_url === 'string') {
                const $change = this.hook('change');
                if (data.change_url !== '') {
                    $change.attr('href', data.change_url).prop('hidden', false);
                } else {
                    $change.removeAttr('href').prop('hidden', true);
                }
            }
        }

        // An amount through WooCommerce's display markup when the answer has it
        // (decimals kept, as the checkout writes it), else the whole-unit format.
        putAmount($el, shown, raw, currencySymbol) {
            if (shown && typeof shown.html === 'string' && shown.html !== '') {
                $el.html(shown.html);
            } else {
                $el.text(this.formatMoney(raw, currencySymbol));
            }
            if (shown && shown.tax_label) {
                // "(ex. VAT)", built and escaped server-side.
                $el.append(shown.tax_label);
            }
            return $el;
        }

        // Whether this answer splits the total into a deposit and the rest.
        depositShown(data) {
            // The quote says 'full' when no deposit applies to this vehicle.
            return this.container.attr('data-rvs-deposit') !== '0' &&
                data.payment_type !== 'full' &&
                data.deposit_amount !== undefined && data.deposit_amount > 0;
        }

        /**
         * A plain breakdown from the display_* amounts, for an answer without
         * breakdown_html. Same classes as the server's partial.
         */
        buildFallbackBreakdown(data) {
            const symbol = data.currency_symbol || this.getConfig('currency_symbol');
            const lines = data.display_lines || {};
            const $root = $('<div class="rvs-breakdown" data-rvs-breakdown></div>');
            const $rows = $('<dl class="rvs-breakdown__rows"></dl>').appendTo($root);
            const row = (kind, label, shown, raw) => {
                const $row = $('<div class="rvs-breakdown__row"></div>').addClass('rvs-breakdown__row--' + kind);
                $('<dt></dt>').text(label).appendTo($row);
                this.putAmount($('<dd></dd>'), shown, raw, symbol).appendTo($row);
                $rows.append($row);
            };

            row('daily', this.getMessage('daily_price'), lines.vehicle_price, data.vehicle_price);
            if (data.addon_total > 0) {
                row('rental', this.getMessage('vehicle_total'), lines.vehicle_total, data.vehicle_total);
                row('addon', this.getMessage('addons_total'), lines.addon_total, data.addon_total);
            }

            const $total = $('<div class="rvs-breakdown__total"></div>').appendTo($root);
            $('<span class="rvs-breakdown__total-label"></span>').text(this.getMessage('total_amount')).appendTo($total);
            const $value = $('<span class="rvs-breakdown__total-value"></span>').appendTo($total);
            this.putAmount($('<span class="rvs-breakdown__amount"></span>'), data.display_total, data.total_price, symbol).appendTo($value);
            if (data.price_suffix) {
                // WooCommerce's own price suffix, built server-side.
                $('<span class="rvs-breakdown__suffix"></span>').html(data.price_suffix).appendTo($value);
            }

            if (this.depositShown(data)) {
                const $deposit = $('<div class="rvs-breakdown__deposit"></div>').appendTo($root);
                $('<span></span>').text(this.getMessage('deposit_amount')).appendTo($deposit);
                this.putAmount($('<strong></strong>'), data.display_deposit, data.deposit_amount, symbol).appendTo($deposit);

                if (data.remaining_amount !== undefined && data.remaining_amount > 0) {
                    const $rest = $('<div class="rvs-breakdown__pickup"></div>').appendTo($root);
                    $('<span></span>').text(this.getMessage('remaining_amount')).appendTo($rest);
                    this.putAmount($('<span></span>'), data.display_remaining, data.remaining_amount, symbol).appendTo($rest);
                }
            }

            return $root;
        }

        // The phone's band: the total, and "· <deposit label> <amount>" when the
        // summary shows a deposit row.
        updateBand(data) {
            const symbol = data.currency_symbol || this.getConfig('currency_symbol');
            this.putAmount(this.hook('total'), data.display_total ? { html: data.display_total.html } : null, data.total_price, symbol);

            const $bar = this.hook('bar-deposit').empty();
            const $row = this.breakdownBox().find('.rvs-breakdown__deposit').first();
            if (!$row.length || this.container.attr('data-rvs-deposit') === '0') {
                $bar.prop('hidden', true);
                return;
            }
            $bar.append(document.createTextNode(' · ' + $row.children().first().text().trim() + ' '));
            $bar.append($row.children().last().clone().contents());
            $bar.prop('hidden', false);
        }

        // The form's "Default Days", clamped into the rental limits
        // validateRentalLimits() enforces, so the return date the form fills in
        // never disables submit. 0 = no prefill (unset, or limits that cannot be met).
        defaultReturnDays() {
            const days = parseInt(this.container.data('default-days'), 10) || 0;
            if (days <= 0) return 0;

            const minDays = window.mhmRentivaBookingForm?.config?.min_days || 1;
            const maxDays = window.mhmRentivaBookingForm?.config?.max_days || 30;
            if (maxDays > 0 && minDays > maxDays) return 0;

            const raised = Math.max(days, minDays);
            return maxDays > 0 ? Math.min(raised, maxDays) : raised;
        }

        validateRentalLimits() {
            const pickupDate = this.hook('pickup-date').val();
            const dropoffDate = this.hook('dropoff-date').val();

            if (!pickupDate || !dropoffDate) {
                return true; // Wait for both dates
            }

            const days = this.calculateDays(pickupDate, dropoffDate);
            const minDays = window.mhmRentivaBookingForm?.config?.min_days || 1;
            const maxDays = window.mhmRentivaBookingForm?.config?.max_days || 30;

            if (days < minDays) {
                this.showError(this.getMessage('min_days_error').replace('%d', minDays));
                this.submitBtn.prop('disabled', true);
                return false;
            }

            if (maxDays > 0 && days > maxDays) {
                this.showError(this.getMessage('max_days_error').replace('%d', maxDays));
                this.submitBtn.prop('disabled', true);
                return false;
            }

            this.hideMessages();
            this.updateButtonState();
            return true;
        }

        // Helper to calculate days between dates
        calculateDays(start, end) {
            if (!start || !end) return 0;

            try {
                const options = window.mhmRentivaBookingForm?.datepicker_options || {};
                const format = options.dateFormat || 'yy-mm-dd';

                const startDate = $.datepicker.parseDate(format, start);
                const endDate = $.datepicker.parseDate(format, end);

                if (!startDate || !endDate) return 0;

                const diffTime = Math.abs(endDate - startDate);
                return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            } catch (e) {
                // Fallback to basic calculation if format parsing fails
                const s = new Date(start.replace(/\//g, '-'));
                const e_date = new Date(end.replace(/\//g, '-'));

                if (isNaN(s.getTime()) || isNaN(e_date.getTime())) return 0;

                return Math.ceil(Math.abs(e_date - s) / (1000 * 60 * 60 * 24));
            }
        }

        validateField(field) {
            const $field = $(field);
            const value = String($field.val() || '').trim();

            if ($field.prop('required') && !value) {
                $field.attr('aria-invalid', 'true');
                return false;
            }

            $field.removeAttr('aria-invalid');
            return true;
        }

        validateForm() {
            let isValid = true;

            this.form.find('input[required], select[required]').each((index, field) => {
                if (!this.validateField(field)) {
                    isValid = false;
                }
            });

            const $pickupTime = this.hook('pickup-time');
            if (!$pickupTime.val()) {
                $pickupTime.attr('aria-invalid', 'true');
                this.showError(this.getMessage('selectPickupTime'));
                isValid = false;
            }

            const pickup = this.getIsoDate(this.hook('pickup-date').val());
            const dropoff = this.getIsoDate(this.hook('dropoff-date').val());
            if (pickup && dropoff && dropoff <= pickup) {
                this.hook('dropoff-date').attr('aria-invalid', 'true');
                this.showError(this.getMessage('invalid_dates'));
                isValid = false;
            }

            // The payment type is chosen at the WooCommerce checkout (step 2):
            // the form posts one hidden payment_type and nothing else to require.
            return isValid;
        }

        // The submit button's busy state: disabled, aria-busy and "Processing...".
        setSubmitting(busy) {
            this.submitting = busy;
            const $btn = this.submitBtn;
            if (busy) {
                if ($btn.attr('data-rvs-label') === undefined) {
                    $btn.attr('data-rvs-label', $btn.text());
                }
                $btn.attr('aria-busy', 'true').text(this.getMessage('processing'));
            } else {
                if ($btn.attr('data-rvs-label') !== undefined) {
                    $btn.text($btn.attr('data-rvs-label')).removeAttr('data-rvs-label');
                }
                $btn.removeAttr('aria-busy');
            }
            this.updateButtonState();
        }

        submitForm() {
            if (this.submitting || this.locked) {
                return;
            }
            if (!this.validateForm()) {
                return;
            }

            this.setSubmitting(true);
            this.hideMessages();

            const formData = this.getFormData();

            const requestData = new URLSearchParams();
            requestData.append('action', 'mhmrentiva_booking_form');
            requestData.append('nonce', window.mhmRentivaBookingForm?.nonce || '');

            // Scalar fields (skip undefined/null like jQuery does)
            Object.keys(formData).forEach(key => {
                if (key !== 'addons' && key !== 'addon_qty') {
                    const val = formData[key];
                    if (val !== null && val !== undefined) {
                        requestData.append(key, val);
                    }
                }
            });

            // addons[]=id, then the quantities of those add-ons
            formData.addons.forEach(addonId => {
                requestData.append('addons[]', addonId);
            });
            this.appendAddonQuantities(requestData, formData.addon_qty);

            let leaving = false;
            $.ajax({
                url: window.mhmRentivaBookingForm?.ajax_url || '',
                type: 'POST',
                data: requestData.toString(),
                contentType: 'application/x-www-form-urlencoded',
                traditional: true,
                success: (response) => {
                    if (response.success) {
                        if (response.data.redirect_url) {
                            // Stays busy while the browser leaves for the cart.
                            leaving = true;
                            window.location.href = response.data.redirect_url;
                        } else {
                            this.showSuccess(response.data.message || this.getMessage('booking_created'));
                        }
                    } else {
                        this.showError(response.data?.message || this.getMessage('error_occurred'));
                    }
                },
                error: () => {
                    this.showError(this.getMessage('error_occurred'));
                },
                complete: () => {
                    if (!leaving) {
                        this.setSubmitting(false);
                    }
                }
            });
        }

        checkAvailability() {
            const formData = this.getFormData();

            if (!formData.vehicle_id || !formData.pickup_date || !formData.dropoff_date) {
                return;
            }

            const $status = this.hook('availability');
            this.setStatus($status, 'busy', this.getMessage('checking_availability'));

            const ajaxData = {
                action: 'mhmrentiva_check_availability',
                vehicle_id: formData.vehicle_id,
                pickup_date: formData.pickup_date,
                pickup_time: formData.pickup_time || '09:00',
                dropoff_date: formData.dropoff_date,
                dropoff_time: formData.dropoff_time || '09:00',
                nonce: window.mhmRentivaBookingForm?.nonce || ''
            };

            // Only the newest check may speak: dates can change while an older
            // request is still in flight, and its late answer must not overwrite
            // the newer one (in either direction).
            const requestSeq = this.availabilitySeq = (this.availabilitySeq || 0) + 1;

            $.ajax({
                url: window.mhmRentivaBookingForm?.ajaxUrl || '',
                type: 'POST',
                data: ajaxData,
                traditional: true,
                success: (response) => {
                    if (requestSeq !== this.availabilitySeq) {
                        return;
                    }

                    if (response.success) {
                        this.setStatus($status, 'ok', this.getMessage('vehicle_available'));
                        this.availabilityBlocked = false;
                        this.updateButtonState();
                        return;
                    }

                    const alternatives = Array.isArray(response.data?.alternatives) ? response.data.alternatives : [];
                    const message = alternatives.length
                        ? this.getMessage('vehicle_unavailable_with_alternatives')
                        : (response.data?.message || this.getMessage('vehicle_not_available'));
                    this.setStatus($status, 'error', message);
                    if (alternatives.length) {
                        $status.append(this.buildAlternatives(alternatives.slice(0, 2)));
                    }

                    // Block submit only when the server said the vehicle is
                    // taken. A refusal without one of these codes is a check
                    // that could not run (rate limit, nonce, bad input): show
                    // it, but do not lock a sale for dates that may be free --
                    // checkout re-checks under a lock anyway.
                    const takenCodes = ['unavailable', 'unavailable_with_alternatives', 'vehicle_unavailable', 'vehicle_not_found', 'service_unavailable'];
                    this.availabilityBlocked = takenCodes.includes(response.data?.code);
                    this.updateButtonState();
                },
                error: (xhr, status, error) => {
                    if (requestSeq !== this.availabilitySeq) {
                        return;
                    }
                    console.error('Availability check failed:', { status, error });
                    this.setStatus($status, 'error', this.getMessage('availability_check_failed'));

                    // The check did not answer: say so, and lift any lock an
                    // earlier "taken" left behind -- nothing else will. Checkout
                    // re-checks under a lock.
                    this.availabilityBlocked = false;
                    this.updateButtonState();
                }
            });
        }

        // The availability line: data-state="busy|ok|error" and a message as text.
        setStatus($status, state, message) {
            $status.attr('data-state', state).empty().prop('hidden', false);
            $('<p class="rvs-booking__status-text"></p>').text(message || '').appendTo($status);
        }

        /**
         * The suggested vehicles as rvs cards (assets/css/rvs/rvs-card.css). Every
         * server value goes in as text or an attribute; nothing is parsed as markup.
         */
        buildAlternatives(alternatives) {
            const $wrap = $('<div class="rvs-booking__alternatives"></div>');
            $('<p class="rvs-booking__alternatives-title"></p>').text(this.getMessage('alternative_vehicles')).appendTo($wrap);
            const $grid = $('<div class="rvs-card-grid"></div>').appendTo($wrap);
            $grid[0].style.setProperty('--rvs-cols', '2');

            alternatives.forEach((vehicle) => {
                const title = vehicle.title === undefined || vehicle.title === null ? '' : String(vehicle.title);
                const image = vehicle.image || window.mhmRentivaBookingForm?.placeholder_image || '';
                // Translated feature labels, joined by the server.
                const specs = typeof vehicle.specs === 'string' ? vehicle.specs : '';

                const $card = $('<article class="rvs-card"></article>').attr('data-vehicle-id', String(vehicle.id));
                if (image) {
                    const $media = $('<div class="rvs-card__media"></div>').appendTo($card);
                    $('<img class="rvs-card__img" loading="lazy">').attr({ src: String(image), alt: title }).appendTo($media);
                }
                const $body = $('<div class="rvs-card__body"></div>').appendTo($card);
                const $head = $('<div class="rvs-card__head"></div>').appendTo($body);
                $('<h4 class="rvs-card__title"></h4>').text(title).appendTo($head);
                if (specs) {
                    $('<p class="rvs-card__specs"></p>').text(specs).appendTo($body);
                }
                const $foot = $('<div class="rvs-card__foot"></div>').appendTo($body);
                const $price = $('<div class="rvs-card__price"></div>').appendTo($foot);
                $('<div class="rvs-card__amount"></div>')
                    .text(vehicle.price_per_day !== undefined ? this.formatMoney(vehicle.price_per_day) : '-')
                    .appendTo($price);
                $('<div class="rvs-card__per"></div>').text(this.getMessage('per_day')).appendTo($price);
                $('<button type="button" class="rvs-btn rvs-btn--primary rvs-card__select"></button>')
                    .text(this.getMessage('select_this_vehicle'))
                    .on('click', () => {
                        this.selectAlternativeVehicle({
                            id: vehicle.id,
                            title: title,
                            price: vehicle.price_per_day,
                            image: vehicle.image || '',
                            specs: specs,
                            permalink: vehicle.permalink || ''
                        });
                    })
                    .appendTo($foot);

                $grid.append($card);
            });

            return $wrap;
        }

        selectAlternativeVehicle(vehicleData) {
            const vehicleId = String(vehicleData.id);
            const $select = this.hook('vehicle-select');

            const listed = $select.find('option').filter(function () {
                return this.value === vehicleId;
            }).length > 0;

            if (listed) {
                // The select's change handler fills the summary and re-quotes.
                $select.val(vehicleId).trigger('change');
            } else {
                // A form for one vehicle, or a suggestion the select does not
                // list: swap the posted id and the summary.
                this.container.find('input[name="vehicle_id"]').val(vehicleId);
                this.container.attr('data-vehicle-id', vehicleId);
                this.showVehicle(vehicleData);
                this.fetchAndApplyBlockedDates(vehicleId);
                this.autoCalculatePrice();
                this.autoCheckAvailability();
            }

            // The server's link carries the window; the option's does not.
            if (vehicleData.permalink) {
                this.hook('change').attr('href', vehicleData.permalink).prop('hidden', false);
            }
        }

        // Messages under the form: data-state="error|ok", always as text unless
        // the server sent markup.
        showMessage(state, message) {
            const $el = this.hook('messages');
            const text = String(message || '');
            if (text.includes('<') && text.includes('>')) {
                $el.html(text);
            } else {
                $el.text(text);
            }
            $el.attr('data-state', state).prop('hidden', false);
        }

        showError(message) {
            this.showMessage('error', message);
        }

        showSuccess(message) {
            this.showMessage('ok', message);
        }

        hideMessages() {
            this.hook('messages').prop('hidden', true).removeAttr('data-state').empty();
        }

        // A quote in flight: the summary says it is busy and submit waits, so a
        // booking never goes out with a total about to change.
        showLoading(isLoading) {
            this.hook('summary').attr('aria-busy', isLoading ? 'true' : 'false');
            if (isLoading) {
                this.submitBtn.prop('disabled', true);
            } else {
                this.updateButtonState();
            }
        }

        // A value BookingForm::get_js_config() localizes under `config`. The
        // top-level read is a fallback for an older localized shape only.
        getConfig(key) {
            const data = window.mhmRentivaBookingForm || {};
            if (data.config && data.config[key] !== undefined) {
                return data.config[key];
            }
            return data[key];
        }

        // Whole units, like the form's PHP CurrencyHelper::format_price( $x, 0 ),
        // grouped with WooCommerce's thousand separator. Without one, the
        // site's JS locale groups instead.
        formatPrice(price) {
            const value = Number(price || 0);
            const sign = value < 0 ? '-' : '';

            return sign + this.formatAmount(Math.abs(value));
        }

        // A non-negative amount in whole units. Rounds half away from zero, like
        // PHP's number_format(), and never falls into exponent notation.
        formatAmount(amount) {
            const whole = Math.round(amount).toLocaleString('en-US', { useGrouping: false, maximumFractionDigits: 0 });
            const thousandSep = this.getConfig('thousand_separator');
            if (typeof thousandSep === 'string') {
                // A function, not a string: the separator comes from a free WC
                // text field and must not be read as a replacement pattern.
                return whole.replace(/\B(?=(\d{3})+(?!\d))/g, () => thousandSep);
            }

            return new Intl.NumberFormat(this.getConfig('locale') || 'en-US', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(Math.round(amount));
        }

        formatMoney(price, symbolOverride = null) {
            const value = Number(price || 0);
            // wc_price() puts the minus sign before the symbol.
            const sign = value < 0 ? '-' : '';
            const amount = this.formatAmount(Math.abs(value));
            const symbol = symbolOverride || this.getConfig('currency_symbol') || '';
            const position = this.getConfig('currency_position') || 'right_space';

            switch (position) {
                case 'left':
                    return `${sign}${symbol}${amount}`;
                case 'left_space':
                    return `${sign}${symbol} ${amount}`;
                case 'right':
                    return `${sign}${amount}${symbol}`;
                case 'right_space':
                default:
                    return `${sign}${amount} ${symbol}`;
            }
        }

        getMessage(key) {
            return window.mhmRentivaBookingForm?.strings?.[key] || key;
        }

    }

    // Export class for potential external use
    // Calendar-date helpers. Dates travel as Y-m-d strings; arithmetic runs in
    // UTC so neither the browser zone nor DST can shift the day.
    MHMRentivaBookingForm.addDays = function (iso, n) {
        const p = iso.split('-').map(Number);
        return new Date(Date.UTC(p[0], p[1] - 1, p[2] + n)).toISOString().slice(0, 10);
    };
    MHMRentivaBookingForm.localIso = function (date) {
        return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
    };
    MHMRentivaBookingForm.localDate = function (iso) {
        const p = iso.split('-').map(Number);
        return new Date(p[0], p[1] - 1, p[2]);
    };
    // The site's current date (localized from PHP); the browser date is only
    // a fallback when the localized value is missing.
    // jQuery UI reads numeric minDate/maxDate as day offsets from the BROWSER's
    // today. Turn them into dates counted from the site's today.
    MHMRentivaBookingForm.siteDatepickerOptions = function (options) {
        const out = { ...options };
        ['minDate', 'maxDate'].forEach(function (key) {
            if (typeof out[key] === 'number') {
                out[key] = MHMRentivaBookingForm.localDate(MHMRentivaBookingForm.addDays(MHMRentivaBookingForm.siteToday(), out[key]));
            }
        });
        return out;
    };
    MHMRentivaBookingForm.siteToday = function () {
        const localized = window.mhmRentivaBookingForm && window.mhmRentivaBookingForm.siteToday;
        return localized || MHMRentivaBookingForm.localIso(new Date());
    };

    window.MHMRentivaBookingForm = MHMRentivaBookingForm;

})(jQuery);
