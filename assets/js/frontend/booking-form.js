/**
 * Booking Form JavaScript
 * 
 * Advanced booking form - vehicle selection, addons, deposit system
 * Developed based on QuickBooking JS structure
 * 
 * @updated 2025-01-16 - Vehicle availability button control added
 * @updated 2025-01-16 - Alternative vehicle selection and styling improved
 */

(function ($) {
    'use strict';

    // Global jQuery UI DatePicker localization
    $(document).ready(function () {
        if (window.mhmRentivaBookingForm && window.mhmRentivaBookingForm.datepicker_options) {
            $.datepicker.setDefaults(MHMRentivaBookingForm.siteDatepickerOptions(window.mhmRentivaBookingForm.datepicker_options));
        }

        // Initialize all booking forms on the page
        $('.rv-booking-form-wrapper').each(function () {
            new MHMRentivaBookingForm($(this));
        });
    });

    class MHMRentivaBookingForm {
        constructor($container) {
            this.container = $container;
            this.form = this.container.find('.rv-booking-form-content');
            this.submitBtn = this.form.find('.rv-submit-btn');

            this.loadingEl = this.container.find('.rv-loading');
            this.errorEl = this.container.find('.rv-error-message');
            this.successEl = this.container.find('.rv-success-message');

            // Enable button at form start (terms checkbox will control it)
            this.submitBtn.prop('disabled', false);

            // Set by checkAvailability() when the vehicle is taken or the check
            // failed; nothing else may re-enable submit while it is set.
            this.availabilityBlocked = false;

            // Deposit system elements
            this.paymentTypeRadios = this.form.find('input[name="payment_type"]');
            this.paymentMethodRadios = this.form.find('input[name="payment_method"]');
            this.onlinePaymentDetails = this.form.find('.rv-online-payment-details');

            // Price display elements
            this.priceElements = {
                dailyPrice: this.container.find('.rv-daily-price'),
                weekendDiffAmount: this.container.find('.rv-weekend-diff-amount'),
                daysCount: this.container.find('.rv-days-count'),
                vehicleTotal: this.container.find('.rv-vehicle-total'),
                addonsTotal: this.container.find('.rv-addons-total'),
                totalAmount: this.container.find('.rv-total-amount'),
                depositAmount: this.container.find('.rv-deposit-amount'),
                remainingAmount: this.container.find('.rv-remaining-amount')
            };

            // Blocked dates for the currently selected vehicle (populated via AJAX).
            this.blockedDates = [];

            this.init();
        }

        init() {
            if (!this.form.length) return;

            // jQuery AJAX global settings - serialize arrays properly
            $.ajaxSetup({
                traditional: true
            });

            // Check if vehicle is pre-selected (from vehicle list/detail page)
            const preSelectedVehicleId = this.container.attr('data-vehicle-id');
            if (preSelectedVehicleId) {
                const $select = this.container.find('.rv-vehicle-select');
                if ($select.length) {
                    $select.val(preSelectedVehicleId);
                    // Trigger UI update for pre-selected vehicle
                    this.updateVehiclePreview($select[0]);
                }
                // Fetch blocked dates for the pre-selected vehicle.
                this.fetchAndApplyBlockedDates( preSelectedVehicleId );
            }

            this.initializeDatePickers();
            this.bindEvents();
            this.setupDateValidation();
            this.setupVehicleSelection();
            this.setupFormValidation();
            this.initializeDefaults();
        }

        bindEvents() {
            // Form submission - MULTIPLE EVENT BINDING
            this.form.on('submit', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.submitForm();
                return false;
            });

            // Convert form's type="submit" buttons to type="button"
            this.submitBtn.attr('type', 'button');

            // Submit button direct click handler - CLEAR ALL EVENT HANDLERS
            this.submitBtn.off(); // Clear all event handlers

            // Add new click handler - runs in capture phase
            this.submitBtn[0].addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                this.submitForm();
                return false;
            }, true); // true = capture phase


            // Vehicle selection change - set data-vehicle-id
            this.form.on('change', '.rv-vehicle-select', (e) => {
                const vehicleId = $(e.target).val();
                if (vehicleId) {
                    this.container.attr('data-vehicle-id', vehicleId);
                } else {
                    this.container.removeAttr('data-vehicle-id');
                }
            });

            // Favorite toggle within booking form
            this.form.on('click', '.rv-vehicle-card__favorite', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.handleFavoriteToggle($(e.currentTarget));
            });

            // Automatic calculation and availability check on date and time changes
            this.form.on('change', '.rv-pickup-date, .rv-dropoff-date, .rv-pickup-time, .rv-dropoff-time, .rv-vehicle-select', () => {
                // Check rental duration limits before other checks
                if (this.validateRentalLimits()) {
                    this.autoCheckAvailability();
                    this.autoCalculatePrice();
                }
            });

            // Automatic calculation on addon changes
            this.form.on('change', '.rv-addon-checkbox, .rv-addon-qty', () => {
                this.autoCalculatePrice();
            });

            // Update price calculation on payment type change
            this.form.on('change', 'input[name="payment_type"]', () => {
                this.autoCalculatePrice();
            });

            // Automatically update dropoff time when pickup time changes
            // Dropoff time is disabled and always matches pickup time
            this.form.on('change', '.rv-pickup-time', (e) => {
                const pickupTime = $(e.target).val();
                if (pickupTime) {
                    // Update both visible (disabled) select and hidden input
                    this.container.find('.rv-dropoff-time').val(pickupTime);
                    this.container.find('.rv-dropoff-time-hidden').val(pickupTime);
                } else {
                    this.container.find('.rv-dropoff-time').val('');
                    this.container.find('.rv-dropoff-time-hidden').val('');
                }
                // Don't do availability check on time change
            });

            // Initialize dropoff time on page load if pickup time is already selected
            const initialPickupTime = this.container.find('.rv-pickup-time').val();
            if (initialPickupTime) {
                this.container.find('.rv-dropoff-time').val(initialPickupTime);
                this.container.find('.rv-dropoff-time-hidden').val(initialPickupTime);
            }

            // Vehicle selection change
            this.form.on('change', '.rv-vehicle-select', (e) => {
                this.updateVehiclePreview(e.target);
                // ⭐ Check availability when vehicle changes
                this.autoCheckAvailability();
                // Fetch blocked dates for the newly selected vehicle.
                const selectedVehicleId = $( e.target ).val();
                this.fetchAndApplyBlockedDates( selectedVehicleId );
            });

            this.paymentMethodRadios.on('change', () => {
                this.updatePaymentMethodDisplay();
            });

            // Show gateway options when online payment method is selected
            this.paymentMethodRadios.on('change', (e) => {
                if ($(e.target).val() === 'online') {
                    this.onlinePaymentDetails.removeClass('rv-hidden');
                } else {
                    this.onlinePaymentDetails.addClass('rv-hidden');
                }
            });

            // Terms checkbox control - enable/disable submit button
            const termsCheckbox = this.container.find('.rv-terms-checkbox-input'); // Assumed class, added to PHP if needed or rely on ID if unique? Prefer class.
            if (termsCheckbox.length) {
                // Set initial state
                this.updateButtonState();

                // Update button state when checkbox changes
                termsCheckbox.on('change', () => {
                    this.updateButtonState();
                });
            }
        }

        updateButtonState() {
            const termsCheckbox = this.container.find('.rv-terms-checkbox-input');
            if (termsCheckbox.length) {
                const isChecked = termsCheckbox.is(':checked');
                this.submitBtn.prop('disabled', !isChecked || !!this.availabilityBlocked);
            }
        }

        handleFavoriteToggle($button) {
            const vehicleId = $button.data('vehicle-id');
            // Try to get config from global favorites or fallback to booking form config
            const favoritesConfig = window.mhmRentivaFavorites || {};
            const nonce = favoritesConfig.nonce || window.mhmRentivaBookingForm?.nonce;

            if (!vehicleId) {
                return;
            }

            if (!nonce) {
                MHMRentivaToast.show(favoritesConfig?.strings?.login_required || this.getMessage('login_required'), { type: 'error' });
                return;
            }

            $button.prop('disabled', true);
            const $icon = $button.find('svg'); // Target the SVG directly

            $.ajax({
                url: favoritesConfig.ajaxUrl || this.getAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'mhmrentiva_toggle_favorite',
                    vehicle_id: vehicleId,
                    nonce: nonce
                },
                success: (response) => {
                    if (response.success) {
                        const isAdded = response.data.action === 'added';

                        // Toggle classes
                        $button.toggleClass('is-favorited favorited', isAdded);
                        $icon.toggleClass('favorited', isAdded);

                        // Explicitly handle SVG fill for immediate visual feedback
                        if (isAdded) {
                            $icon.attr('fill', 'currentColor');
                            $icon.css('fill', 'currentColor'); // Force CSS if needed
                        } else {
                            $icon.attr('fill', 'none');
                            $icon.css('fill', 'none');
                        }

                        // Update ARIA attributes
                        $button.attr('aria-pressed', isAdded ? 'true' : 'false');
                        $button.attr('aria-label', isAdded ?
                            (favoritesConfig.strings?.remove_label || this.getMessage('remove_from_favorites')) :
                            (favoritesConfig.strings?.add_label || this.getMessage('add_to_favorites'))
                        );

                        const successMessage = isAdded ?
                            (favoritesConfig.strings?.added_label || this.getMessage('added_to_favorites')) :
                            (favoritesConfig.strings?.removed_label || this.getMessage('removed_from_favorites'));
                        MHMRentivaToast.show(successMessage, { type: 'success' });
                    } else {
                        MHMRentivaToast.show(response.data?.message || favoritesConfig.strings?.error || this.getMessage('error'), { type: 'error' });
                    }
                },
                error: () => {
                    MHMRentivaToast.show(favoritesConfig.strings?.error || this.getMessage('error'), { type: 'error' });
                },
                complete: () => {
                    $button.prop('disabled', false);
                }
            });
        }


        setupDateValidation() {
            // The site's today (localized), not the browser's UTC date.
            const todayStr = MHMRentivaBookingForm.siteToday();
            const pickupDate = this.container.find('.rv-pickup-date');
            const dropoffDate = this.container.find('.rv-dropoff-date');

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

                // ⭐ Check availability when date changes
                this.autoCheckAvailability();
            });

            // Check availability when dropoff date changes
            dropoffDate.on('change', (e) => {
                // ⭐ Check availability when date changes
                this.autoCheckAvailability();
            });
        }

        setupVehicleSelection() {
            const vehicleSelect = this.container.find('.rv-vehicle-select');
            const preview = this.container.find('.rv-selected-vehicle-preview');

            if (!vehicleSelect.length || !preview.length) return;

            // Hide preview on first load
            preview.addClass('rv-hidden');
        }

        setupFormValidation() {
            // Real-time validation
            this.form.find('input[required], select[required]').on('blur', (e) => {
                this.validateField(e.target);
            });

            // Note: Submit validation is handled in submitForm
        }

        initializeDatePickers() {
            if (window.mhmRentivaBookingForm && window.mhmRentivaBookingForm.datepicker_options) {
                const self = this;
                const options = {
                    ...MHMRentivaBookingForm.siteDatepickerOptions(window.mhmRentivaBookingForm.datepicker_options),
                    onSelect: function (dateText, inst) {
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
                };

                this.container.find('input.rv-date-input').each(function () {
                    const $input = $(this);
                    const originalValue = $input.val();

                    if (window.mhmRentivaBookingForm?.config?.advance_booking_days) {
                        options.maxDate = '+' + window.mhmRentivaBookingForm.config.advance_booking_days + 'd';
                    }

                    // Destroy existing instances to prevent "Missing instance data" errors
                    if ($input.hasClass('hasDatepicker') || $input.data('datepicker')) {
                        try { $input.datepicker('destroy'); } catch (e) { }
                        $input.removeClass('hasDatepicker').removeData('datepicker').off('.datepicker');
                    }

                    // Force initialization
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
            // Set default values
            if (window.mhmRentivaBookingForm?.default_payment) {
                this.form.find(`input[name="payment_type"][value="${window.mhmRentivaBookingForm.default_payment}"]`).prop('checked', true);
            }

            // Set initial online payment details display
            this.updatePaymentMethodDisplay();

            // Initialize dropoff time to match pickup time if pickup time is already selected
            const initialPickupTime = this.container.find('.rv-pickup-time').val();
            if (initialPickupTime) {
                this.container.find('.rv-dropoff-time').val(initialPickupTime);
                this.container.find('.rv-dropoff-time-hidden').val(initialPickupTime);
            }

            // If dates are pre-filled from URL params (search-to-booking context), trigger
            // price calculation immediately so the payment summary is not blank on load.
            const preFillPickupDate = this.container.find('.rv-pickup-date').val();
            const preFillDropoffDate = this.container.find('.rv-dropoff-date').val();
            if (preFillPickupDate && preFillDropoffDate) {
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
         * Refresh all jQuery UI datepicker instances in this form so that the
         * updated blockedDates list takes effect immediately.
         */
        refreshDatepickerBlockedDates() {
            this.container.find( 'input.rv-date-input' ).each( function () {
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

        updateVehiclePreview(selectElement) {
            const $select = $(selectElement);
            const $option = $select.find('option:selected');
            const preview = this.container.find('.rv-selected-vehicle-preview, .rv-selected-vehicle');
            const sidebar = this.container.find('.rv-checkout-sidebar, .rv-checkout-vehicle');
            const image = preview.find('.rv-vehicle-image, .rv-sv__img');
            const title = preview.find('.rv-vehicle-title');
            const category = preview.find('.rv-vehicle-category, .rv-sv__category');
            const ratingOverlay = preview.find('.rv-sv__rating-overlay');
            const price = preview.find('.rv-vehicle-price');

            if ($option.val()) {
                const vehiclePrice = $option.data('price');
                const vehicleImage = $option.data('image');
                const vehicleCategory = ($option.data('category') || '').toString().trim();
                const vehicleTitle = $option.text().split(' (')[0]; // Remove price part

                title.text(vehicleTitle);
                price.html(`<span class="rv-sv__price-amount rv-price-large">${this.formatMoney(vehiclePrice)}</span><span class="rv-sv__price-period"> ${this.getMessage('per_day')}</span>`);
                if (category.length) {
                    category.text(vehicleCategory);
                    category.toggleClass('rv-hidden', !vehicleCategory);
                }
                if (ratingOverlay.length) {
                    // The vehicle's real score, preformatted server-side; a vehicle
                    // nobody has rated shows none (same rule as the card).
                    const ratingCount = parseInt($option.attr('data-rating-count'), 10) || 0;
                    if (ratingCount > 0) {
                        ratingOverlay.find('.rv-sv__rating-value').text($option.attr('data-rating') || '');
                        ratingOverlay.find('.rv-sv__rating-count').text(($option.attr('data-rating-label') || '').trim());
                        ratingOverlay.removeClass('rv-hidden');
                    } else {
                        ratingOverlay.addClass('rv-hidden');
                    }
                }

                if (vehicleImage) {
                    image.attr('src', vehicleImage).removeClass('rv-hidden');
                } else {
                    image.addClass('rv-hidden');
                }

                const vehicleFeatures = $option.data('features') || [];
                const metaDiv = preview.find('.rv-sv__meta');
                if (metaDiv.length) {
                    metaDiv.empty();
                    vehicleFeatures.forEach(function(featureText) {
                        if (featureText) {
                            metaDiv.append('<span class="rv-sv__chip rv-chip">' + $('<div>').text(featureText).html() + '</span>');
                        }
                    });
                    metaDiv.toggleClass('rv-hidden', vehicleFeatures.length === 0);
                }

                preview.removeClass('rv-hidden');
                if (sidebar.length) sidebar.removeClass('rv-hidden');
            } else {
                preview.addClass('rv-hidden');
                if (ratingOverlay.length) {
                    ratingOverlay.addClass('rv-hidden');
                }
                if (sidebar.length) sidebar.addClass('rv-hidden');
            }
        }

        calculatePrice() {
            const formData = this.getFormData();

            const isValid = this.validateCalculationData(formData);

            if (!isValid) {
                return;
            }


            this.showLoading(true);
            this.hideMessages();

            // Serialize addons - empty arrays are skipped by jQuery
            const addonsParam = formData.addons && formData.addons.length > 0
                ? formData.addons
                : [0];  // Send [0] instead of empty, backend filters it

            const ajaxData = {
                action: 'mhmrentiva_calculate_price',
                nonce: window.mhmRentivaBookingForm?.nonce || '',
                vehicle_id: formData.vehicle_id,
                pickup_date: formData.pickup_date,
                dropoff_date: formData.dropoff_date,
                addons: addonsParam,
                addon_qty: formData.addon_qty || {},
                payment_type: this.form.find('input[name="payment_type"]:checked').val() || 'deposit'
            };

            // Manually resolve jQuery array serialization issue
            let requestData = new URLSearchParams();
            requestData.append('action', ajaxData.action);
            requestData.append('nonce', ajaxData.nonce);
            requestData.append('vehicle_id', ajaxData.vehicle_id);
            requestData.append('pickup_date', ajaxData.pickup_date);
            requestData.append('dropoff_date', ajaxData.dropoff_date);
            // The window the submission books: without the time the server
            // priced 10:00, and a pickup on another UTC weekday (00:00-02:59
            // in Istanbul) was quoted one total and charged another.
            if (formData.pickup_time) {
                requestData.append('pickup_time', formData.pickup_time);
            }
            requestData.append('payment_type', ajaxData.payment_type);

            // Addons'u array olarak ekle
            if (ajaxData.addons && ajaxData.addons.length > 0) {
                ajaxData.addons.forEach(addon_id => {
                    requestData.append('addons[]', addon_id);
                });
            } else {
                // Send even empty array
                requestData.append('addons[]', 0);
            }
            this.appendAddonQuantities(requestData, ajaxData.addon_qty);

            const finalData = requestData.toString();

            $.ajax({
                url: window.mhmRentivaBookingForm?.ajax_url || '',
                type: 'POST',
                data: finalData,
                contentType: 'application/x-www-form-urlencoded',
                success: (response) => {
                    this.showLoading(false);

                    if (response.success) {
                        this.updatePriceDisplay(response.data);
                        // The availability check runs alongside this request; a
                        // price answer must not undo its "unavailable".
                        this.submitBtn.prop('disabled', !!this.availabilityBlocked);
                    } else {
                        this.showError(response.data?.message || this.getMessage('error'));
                    }
                },
                error: (xhr, status, error) => {
                    this.showLoading(false);
                    this.showError(this.getMessage('error'));
                }
            });
        }

        appendAddonQuantities(requestData, quantities) {
            Object.keys(quantities || {}).forEach(addonId => {
                requestData.append('addon_qty[' + addonId + ']', quantities[addonId]);
            });
        }

        autoCalculatePrice() {
            // Auto calculation with debounce (faster for payment type changes)
            clearTimeout(this.autoCalculateTimeout);
            this.autoCalculateTimeout = setTimeout(() => {
                const formData = this.getFormData();
                if (this.validateCalculationData(formData)) {
                    this.calculatePrice();
                }
            }, 100);
        }

        autoCheckAvailability() {
            // Clear previous timeout
            if (this.autoAvailabilityTimeout) {
                clearTimeout(this.autoAvailabilityTimeout);
            }

            this.autoAvailabilityTimeout = setTimeout(() => {
                const formData = this.getFormData();

                // Check if vehicle AND dates are filled (time is optional for initial check)
                if (formData.vehicle_id && formData.pickup_date && formData.dropoff_date) {
                    this.calculatePrice(); // Check calculation even without time
                    if (formData.pickup_time && formData.dropoff_time) {
                        this.checkAvailability();
                    }
                }
            }, 300);
        }

        getFormData() {
            const addons = [];
            // Quantities of the ticked add-ons that have a quantity field
            // (sold more than once); the server clamps them and reads a
            // missing one as 1.
            const addonQty = {};
            const form = this.form;

            form.find('.rv-addon-checkbox:checked').each(function () {
                const addonId = parseInt($(this).val());
                addons.push(addonId);
                const $qty = form.find('.rv-addon-qty[data-addon-id="' + addonId + '"]');
                if ($qty.length) {
                    addonQty[addonId] = parseInt($qty.val(), 10) || 1;
                }
            });


            // Get Vehicle ID - check container first (when coming from vehicle list/detail page)
            let vehicle_id = this.container.attr('data-vehicle-id');
            if (!vehicle_id) {
                // Get from dropdown (manual selection)
                vehicle_id = this.container.find('.rv-vehicle-select').val();
            }
            // Also try alternative selectors
            if (!vehicle_id) {
                vehicle_id = this.form.find('select[name="vehicle_id"]').val();
            }
            if (!vehicle_id) {
                vehicle_id = this.form.find('input[name="vehicle_id"]').val();
            }

            const data = {
                vehicle_id: vehicle_id,
                pickup_date: this.container.find('.rv-pickup-date').val(),
                dropoff_date: this.container.find('.rv-dropoff-date').val(),
                pickup_time: this.container.find('.rv-pickup-time').val(),
                dropoff_time: this.container.find('.rv-dropoff-time-hidden').val() || this.container.find('.rv-pickup-time').val(), // Always match pickup time
                guests: this.container.find('.rv-guests').val() || 1,
                customer_first_name: this.container.find('.rv-customer-first-name').val(),
                terms_accepted: this.container.find('.rv-terms-checkbox-input').is(':checked') ? 'on' : '',
                customer_last_name: this.container.find('.rv-customer-last-name').val(),
                customer_email: this.container.find('.rv-customer-email').val(),
                customer_phone: this.container.find('.rv-customer-phone').val(),
                addons: addons,
                addon_qty: addonQty,
                payment_type: this.form.find('input[name="payment_type"]:checked').val() || this.form.find('input[name="payment_type"][type="hidden"]').val() || 'deposit',
                payment_method: this.form.find('input[name="payment_method"]:checked').val(),
                payment_gateway: this.form.find('input[name="payment_gateway"]:checked').val(),
                redirect_url: this.container.attr('data-redirect-url'),
                // Prefilled pickup location (hidden input rendered by booking-form.php); 0 when absent, as the server's int read.
                pickup_location_id: parseInt(this.container.find('input[name="pickup_location_id"]').val(), 10) || 0
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

            // Tarih validasyonu - Veriler getFormData içinde ISO'ya çevrildiği için 'yy-mm-dd' ile parse etmeliyiz
            try {
                const pickup = $.datepicker.parseDate('yy-mm-dd', data.pickup_date);
                const dropoff = $.datepicker.parseDate('yy-mm-dd', data.dropoff_date);

                if (dropoff <= pickup) {
                    this.showError(this.getMessage('dropoff_after_pickup'));
                    return false;
                }
            } catch (e) {
                // Eğer parse hatası alırsak (veri henüz çevrilmemişse vb.)
                return false;
            }

            return true;
        }

        updatePriceDisplay(data) {
            const currencySymbol = data.currency_symbol || this.getConfig('currency_symbol');

            // Every amount comes through WooCommerce's tax display: its wc_price()
            // markup, so a figure reads exactly as the checkout writes it --
            // decimals included, which the whole-unit formatter would round away
            // ("$401" for a $400,80 charge). The breakdown rows (display_lines)
            // use the same convention as the total, so they add up to it on a
            // store that enters prices without tax.
            const put = ($el, shown, raw) => {
                if (shown && typeof shown.html === 'string' && shown.html !== '') {
                    $el.html(shown.html);
                } else {
                    $el.text(this.formatMoney(raw, currencySymbol));
                }
            };
            const lines = data.display_lines || {};

            put(this.priceElements.dailyPrice, lines.vehicle_price, data.vehicle_price);

            this.priceElements.daysCount.text(data.days);

            if (data.weekend_extra && data.weekend_extra > 0) {
                put(this.priceElements.weekendDiffAmount, lines.weekend_extra, data.weekend_extra);
                this.container.find('.rv-weekend-summary').removeClass('rv-hidden');
            } else {
                this.container.find('.rv-weekend-summary').addClass('rv-hidden');
            }

            const hasAddons = data.addon_total > 0;
            const showDetailedBreakdown = hasAddons;

            if (showDetailedBreakdown) {
                put(this.priceElements.vehicleTotal, lines.vehicle_total, data.vehicle_total);
                this.container.find('.rv-vehicle-total-detailed').removeClass('rv-hidden');
            } else {
                this.container.find('.rv-vehicle-total-detailed').addClass('rv-hidden');
            }

            if (hasAddons) {
                put(this.priceElements.addonsTotal, lines.addon_total, data.addon_total);
                this.container.find('.rv-addons-price').removeClass('rv-hidden');
            } else {
                this.container.find('.rv-addons-price').addClass('rv-hidden');
            }

            // An amount due; a tax-exclusive one also says so ("(ex. VAT)"),
            // built and escaped server-side.
            const putDue = ($el, key, raw) => {
                put($el, data[key], raw);
                if (data[key] && data[key].tax_label) {
                    $el.append(data[key].tax_label);
                }
            };

            put(this.priceElements.totalAmount, data.display_total, data.total_price);
            // WooCommerce's own price suffix (with the ex-tax label when it
            // applies), inline after the total as WooCommerce writes it.
            if (data.price_suffix) {
                this.priceElements.totalAmount.append(data.price_suffix);
            }

            const paymentTypeRadio = this.form.find('input[name="payment_type"]:checked').val();
            const paymentTypeHidden = this.form.find('input[name="payment_type"][type="hidden"]').val();
            const paymentType = paymentTypeRadio || paymentTypeHidden || 'deposit';

            // The quote says 'full' when no deposit applies to this vehicle (its
            // rate, else the global setting, is 0): the whole total is charged,
            // so there is no deposit to show.
            const depositApplies = data.payment_type !== 'full';

            if (depositApplies && (paymentType === 'deposit' || data.deposit_amount !== undefined) &&
                data.deposit_amount !== undefined && data.deposit_amount > 0) {
                putDue(this.priceElements.depositAmount, 'display_deposit', data.deposit_amount);
                this.container.find('.rv-deposit-summary').removeClass('rv-hidden');

                if (data.remaining_amount !== undefined && data.remaining_amount > 0) {
                    putDue(this.priceElements.remainingAmount, 'display_remaining', data.remaining_amount);
                    this.container.find('.rv-remaining-summary').removeClass('rv-hidden');
                } else {
                    this.container.find('.rv-remaining-summary').addClass('rv-hidden');
                }
            } else {
                this.container.find('.rv-deposit-summary').addClass('rv-hidden');
                this.container.find('.rv-remaining-summary').addClass('rv-hidden');
            }
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
            const pickupDate = this.container.find('.rv-pickup-date').val();
            const dropoffDate = this.container.find('.rv-dropoff-date').val();

            if (!pickupDate || !dropoffDate) {
                return true; // Wait for both dates
            }

            const days = this.calculateDays(pickupDate, dropoffDate);
            const minDays = window.mhmRentivaBookingForm?.config?.min_days || 1;
            const maxDays = window.mhmRentivaBookingForm?.config?.max_days || 30;

            if (days < minDays) {
                const msg = this.getMessage('min_days_error').replace('%d', minDays);
                this.showError(msg);
                this.submitBtn.prop('disabled', true);
                return false;
            }

            if (maxDays > 0 && days > maxDays) {
                const msg = this.getMessage('max_days_error').replace('%d', maxDays);
                this.showError(msg);
                this.submitBtn.prop('disabled', true);
                return false;
            }

            // If valid, clear error (if it was a limit error) and enable button (if terms checked)
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
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                return diffDays;
            } catch (e) {
                // Fallback to basic calculation if format parsing fails
                // Normalize dates for constructor: replace / with - (D-M-Y is usually more reliable)
                const sStr = start.replace(/\//g, '-');
                const eStr = end.replace(/\//g, '-');

                const s = new Date(sStr);
                const e_date = new Date(eStr);

                if (isNaN(s.getTime()) || isNaN(e_date.getTime())) return 0;

                const diff = Math.abs(e_date - s);
                return Math.ceil(diff / (1000 * 60 * 60 * 24));
            }
        }

        updatePaymentMethodDisplay() {
            const paymentMethod = this.form.find('input[name="payment_method"]:checked').val();

            if (paymentMethod === 'online') {
                this.onlinePaymentDetails.removeClass('rv-hidden');
            } else {
                this.onlinePaymentDetails.addClass('rv-hidden');
            }
        }

        validateField(field) {
            const $field = $(field);
            const value = $field.val().trim();
            const isRequired = $field.prop('required');

            if (isRequired && !value) {
                $field.addClass('error');
                return false;
            }

            if (field.type === 'email' && value) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    $field.addClass('error');
                    return false;
                }
            }

            $field.removeClass('error');
            return true;
        }

        validateForm() {
            let isValid = true;
            const requiredFields = this.form.find('input[required], select[required]');

            requiredFields.each((index, field) => {
                const fieldValid = this.validateField(field);
                if (!fieldValid) {
                    isValid = false;
                }
            });

            const pickupDate = this.container.find('.rv-pickup-date').val();
            const dropoffDate = this.container.find('.rv-dropoff-date').val();
            const pickupTime = this.container.find('.rv-pickup-time').val();

            if (!pickupTime) {
                this.container.find('.rv-pickup-time').addClass('error');
                this.showError(this.getMessage('selectPickupTime'));
                isValid = false;
            }

            if (pickupDate && dropoffDate) {
                const pickup = new Date(pickupDate);
                const dropoff = new Date(dropoffDate);

                if (dropoff <= pickup) {
                    this.container.find('.rv-dropoff-date').addClass('error');
                    this.showError(this.getMessage('invalid_dates'));
                    isValid = false;
                }
            }

            // The form posts one hidden payment_type and renders no payment
            // type / method / gateway radios, so there is nothing to require here.

            const termsCheckbox = this.container.find('.rv-terms-checkbox-input');
            if (termsCheckbox.length) {
                if (!termsCheckbox.is(':checked')) {
                    this.showError(this.getMessage('terms_error'));
                    termsCheckbox.closest('.rv-terms-checkbox').addClass('error');
                    termsCheckbox.focus();
                    isValid = false;
                } else {
                    termsCheckbox.closest('.rv-terms-checkbox').removeClass('error');
                }
            }

            return isValid;
        }

        submitForm() {
            if (!this.validateForm()) {
                return;
            }

            this.showLoading(true);
            this.hideMessages();

            const formData = this.getFormData();

            // Build request using URLSearchParams to ensure addons array is serialized correctly
            // ($.ajaxSetup traditional:true would break addons[] serialization with spread operator)
            let requestData = new URLSearchParams();
            requestData.append('action', 'mhmrentiva_booking_form');
            requestData.append('nonce', window.mhmRentivaBookingForm?.nonce || '');

            // Append all scalar fields (skip undefined/null like jQuery does)
            Object.keys(formData).forEach(key => {
                if (key !== 'addons' && key !== 'addon_qty') {
                    const val = formData[key];
                    if (val !== null && val !== undefined) {
                        requestData.append(key, val);
                    }
                }
            });

            // Append addons as proper PHP array (addons[]=id)
            if (formData.addons && formData.addons.length > 0) {
                formData.addons.forEach(addonId => {
                    requestData.append('addons[]', addonId);
                });
            }
            this.appendAddonQuantities(requestData, formData.addon_qty);

            $.ajax({
                url: window.mhmRentivaBookingForm?.ajax_url || '',
                type: 'POST',
                data: requestData.toString(),
                contentType: 'application/x-www-form-urlencoded',
                success: (response) => {
                    this.showLoading(false);
                    if (response.success) {
                        if (response.data.redirect_url) {
                            window.location.href = response.data.redirect_url;
                        } else {
                            this.showSuccess(response.data.message || this.getMessage('booking_created'));
                            this.form[0].reset();
                            // Reset select2 if used, etc.
                        }
                    } else {
                        this.showError(response.data?.message || this.getMessage('error'));
                    }
                },
                error: (xhr, status, error) => {
                    this.showLoading(false);
                    this.showError(this.getMessage('error'));
                }
            });
        }

        checkAvailability() {
            // Logic similar to calculatePrice but checking availability
            // For now assuming calculatePrice handles basic validation/availability check logic on backend
            // or this method can be implemented if separate endpoint exists.
            // Original file didn't show full implementation so assumption is it reuses calculate logic or is placeholder.
            // Implemented as calling calculatePrice() in autoCheckAvailability for now.

            // UPDATE: Found checkAvailability implementation in old file. Re-implementing it.
            const formData = this.getFormData();

            if (!formData.vehicle_id || !formData.pickup_date || !formData.dropoff_date) {
                return;
            }

            // Element for availability status display
            let availabilityStatus = this.form.find('.rv-availability-status');

            if (!availabilityStatus.length) {
                availabilityStatus = $('<div class="rv-availability-status"></div>');

                const leftColumn = this.form.find('.rv-booking-form-left');
                const vehicleSection = leftColumn.find('.rv-vehicle-selection, .rv-selected-vehicle').first();
                const dateTimeSection = leftColumn.find('.rv-dates-times').first();

                if (vehicleSection.length) {
                    vehicleSection.after(availabilityStatus);
                } else if (dateTimeSection.length) {
                    dateTimeSection.before(availabilityStatus);
                } else if (leftColumn.length) {
                    leftColumn.prepend(availabilityStatus);
                } else {
                    this.form.prepend(availabilityStatus);
                }
            }

            // Loading status - Modern loading card
            availabilityStatus.removeClass('success error').addClass('loading');
            const checkingAvailabilityText = this.getMessage('checking_availability') || 'Checking availability...';
            availabilityStatus.html(`<div class="rv-loading-message"><span class="rv-spinner"></span> <span>${this.escapeHtml(checkingAvailabilityText)}</span></div>`);
            availabilityStatus.removeClass('hidden');


            // Debug log for AJAX data
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
                success: (response) => {
                    if (requestSeq !== this.availabilitySeq) {
                        return;
                    }
                    availabilityStatus.removeClass('hidden'); // Show in all cases

                    if (response.success) {
                        // Available - Modern success card
                        availabilityStatus.removeClass('loading error').addClass('success');
                        availabilityStatus.html(`
                                    <div class="rv-availability-success">
                                        ${window.mhmRentivaBookingForm?.icons?.success || '<svg class="rv-icon-check" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>'}
                                        <span>${this.getMessage('vehicle_available')}</span>
                                    </div>
                                `);

                        // ✅ Vehicle available - enable button
                        this.availabilityBlocked = false;
                        this.submitBtn.prop('disabled', false);
                    } else {
                        // Not available
                        let message = response.data?.message || this.getMessage('vehicle_not_available');
                        let alternativesHtml = '';

                        if (response.data?.alternatives && response.data.alternatives.length > 0) {
                            message = this.getMessage('vehicle_unavailable_with_alternatives');
                            alternativesHtml = `
                                        <div class="rv-alternatives-wrapper">
                                            <div class="rv-alternatives-title">${this.escapeHtml(this.getMessage('alternative_vehicles') || 'Alternative Vehicles')}</div>
                                            <div class="rv-alternatives-grid">
                                    `;

                            response.data.alternatives.slice(0, 2).forEach(vehicle => {
                                const currencySymbol = this.escapeHtml(this.getConfig('currency_symbol') || '');
                                const priceLabel = this.escapeHtml(this.getMessage('daily_price') || 'Daily Price');
                                const compactPrice = vehicle.price_per_day !== undefined
                                    ? this.formatMoney(vehicle.price_per_day, currencySymbol)
                                    : '-';

                                // Build feature meta badges (seats, transmission, fuel_type)
                                const metaBadges = [];
                                if (vehicle.seats)        metaBadges.push('<span class="rv-alt-meta-badge"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>' + this.escapeHtml(vehicle.seats) + '</span>');
                                if (vehicle.transmission) metaBadges.push('<span class="rv-alt-meta-badge"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 8v4l3 3"></path></svg>' + this.escapeHtml(vehicle.transmission) + '</span>');
                                if (vehicle.fuel_type)    metaBadges.push('<span class="rv-alt-meta-badge"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 22V8l9-6 9 6v14"></path><path d="M9 22V12h6v10"></path></svg>' + this.escapeHtml(vehicle.fuel_type) + '</span>');
                                const metaHtml = metaBadges.length ? '<div class="rv-alt-meta-badges">' + metaBadges.join('') + '</div>' : '';

                                alternativesHtml += `
                                            <div class="rv-alternative-vehicle-card" data-vehicle-id="${this.escapeHtml(vehicle.id)}">
                                                <div class="rv-alternative-vehicle-image">
                                                    <img src="${this.escapeHtml(vehicle.image || window.mhmRentivaBookingForm?.placeholder_image || '')}" alt="${this.escapeHtml(vehicle.title)}">
                                                </div>
                                                <div class="rv-alternative-vehicle-content">
                                                    <h5 class="rv-alternative-vehicle-title">${this.escapeHtml(vehicle.title)}</h5>
                                                    ${metaHtml}
                                                    <div class="rv-alternative-price-details rv-alternative-price-compact">
                                                        <span class="rv-alternative-price-label">${priceLabel}:</span>
                                                        <span class="rv-alternative-price-amount">${compactPrice}</span>
                                                    </div>
                                                    <button type="button" class="rv-select-alternative-btn" 
                                                        data-vehicle-id="${this.escapeHtml(vehicle.id)}"
                                                        data-vehicle-title="${this.escapeHtml(vehicle.title)}"
                                                        data-vehicle-price="${this.escapeHtml(vehicle.price_per_day)}"
                                                        data-vehicle-image="${this.escapeHtml(vehicle.image || '')}">
                                                        <span class="dashicons dashicons-car"></span>
                                                        ${this.escapeHtml(this.getMessage('select_this_vehicle'))}
                                                    </button>
                                                </div>
                                            </div>
                                        `;
                            });

                            alternativesHtml += `
                                            </div>
                                        </div>
                                    `;
                        }

                        // Not available - Modern error card
                        availabilityStatus.removeClass('loading success').addClass('error');
                        availabilityStatus.html(`
                                    <div class="rv-availability-error">
                                        <div class="rv-availability-error-header">
                                            <span class="dashicons dashicons-warning"></span>
                                            <strong>${this.escapeHtml(message)}</strong>
                                        </div>
                                        ${alternativesHtml}
                                    </div>
                                `);

                        // ❌ Block submit only when the server said the vehicle is
                        // taken. A refusal without one of these codes is a check
                        // that could not run (rate limit, nonce, bad input): show
                        // it, but do not lock a sale for dates that may be free --
                        // checkout re-checks under a lock anyway.
                        const takenCodes = ['unavailable', 'unavailable_with_alternatives', 'vehicle_unavailable', 'vehicle_not_found', 'service_unavailable'];
                        this.availabilityBlocked = takenCodes.includes(response.data?.code);
                        this.submitBtn.prop('disabled', this.availabilityBlocked);
                        if (!this.availabilityBlocked) {
                            this.updateButtonState();
                        }

                        // Alternative vehicle selection buttons
                        availabilityStatus.find('.rv-select-alternative-btn').on('click', (e) => {
                            const $btn = $(e.currentTarget);
                            const vehicleData = {
                                id: $btn.data('vehicle-id'),
                                title: $btn.data('vehicle-title'),
                                price: $btn.data('vehicle-price'),
                                image: $btn.data('vehicle-image')
                            };
                            this.selectAlternativeVehicle(vehicleData);
                        });
                    }
                },
                error: (xhr, status, error) => {
                    if (requestSeq !== this.availabilitySeq) {
                        return;
                    }
                    console.error('🔍 AJAX Error:', { xhr, status, error });
                    console.error('🔍 Response Text:', xhr.responseText);
                    // Error state - Modern error card
                    availabilityStatus.removeClass('loading success').addClass('error');
                    availabilityStatus.html(`
                                <div class="rv-availability-error">
                                    <div class="rv-availability-error-header">
                                        <span class="dashicons dashicons-warning"></span>
                                        <strong>${this.getMessage('availability_check_failed')}</strong>
                                    </div>
                                </div>
                            `);

                    // The check did not answer: say so, and lift any lock an
                    // earlier "taken" left behind -- nothing else will. The terms
                    // checkbox still applies; checkout re-checks under a lock.
                    this.availabilityBlocked = false;
                    this.submitBtn.prop('disabled', false);
                    this.updateButtonState();
                }
            });
        }

        selectAlternativeVehicle(vehicleData) {
            const vehicleId = vehicleData.id;
            const select = this.container.find('.rv-vehicle-select');

            if (select.length) {
                // If we have a dropdown, update it and it will trigger change/preview update
                select.val(vehicleId).trigger('change');
            } else {
                // If dropdown is hidden (specific vehicle mode), update hidden input and UI manually
                const hiddenInput = this.form.find('input[name="vehicle_id"]');
                if (hiddenInput.length) {
                    hiddenInput.val(vehicleId);
                }

                this.container.attr('data-vehicle-id', vehicleId);

                // Manually update the UI card since updateVehiclePreview works on select element
                this.updateVehicleUI(vehicleData);

                // Trigger auto calculation and availability check
                this.autoCalculatePrice();
                this.autoCheckAvailability();
            }
        }

        updateVehicleUI(vehicleData) {
            // Find active info card (static or preview)
            const card = this.container.find('.rv-selected-vehicle, .rv-selected-vehicle-preview');
            const sidebar = this.container.find('.rv-checkout-sidebar, .rv-checkout-vehicle');
            if (!card.length) return;

            const image = card.find('.rv-vehicle-image, .rv-sv__img');
            const title = card.find('.rv-vehicle-title');
            const price = card.find('.rv-vehicle-price');
            const excerpt = card.find('.rv-vehicle-excerpt');
            const features = card.find('.rv-vehicle-features');
            const ratings = card.find('.rv-vehicle-rating-block');

            // Update title and basic price
            if (title.length) title.text(vehicleData.title);
            if (price.length) {
                price.html(`<span class="rv-sv__price-amount rv-price-large">${this.formatMoney(vehicleData.price)}</span><span class="rv-sv__price-period"> ${this.getMessage('per_day')}</span>`);
            }

            // Update image
            if (image.length && vehicleData.image) {
                image.attr('src', vehicleData.image).removeClass('rv-hidden');
                image.closest('.rv-vehicle-image-wrapper').removeClass('rv-hidden');
            }

            // Hide/Clear parts we don't have for alternatives to prevent confusion
            if (excerpt.length) excerpt.addClass('rv-hidden');
            if (features.length) features.addClass('rv-hidden');
            if (ratings.length) ratings.addClass('rv-hidden');

            // Ensure the card is visible
            card.removeClass('rv-hidden');
            if (sidebar.length) sidebar.removeClass('rv-hidden');
        }

        // Helper methods: showError, showSuccess, showLoading, hideMessages,
        // formatPrice, getMessage, getFavoritesConfig, getAjaxUrl, calculateDays.

        showError(message) {
            // Check if message contains HTML - simplistic check
            if (message.includes('<') && message.includes('>')) {
                // If it looks like HTML, use html() but ensure content was sanitized before
                this.errorEl.html(message).removeClass('rv-hidden').removeClass('rv-hidden');
            } else {
                // Otherwise use text() for safety
                this.errorEl.text(message).removeClass('rv-hidden').removeClass('rv-hidden');
            }
            this.successEl.addClass('rv-hidden').addClass('rv-hidden');
            // Auto hide after 5 seconds
            setTimeout(() => {
                this.errorEl.fadeOut();
            }, 5000);
        }

        showSuccess(message) {
            // Check if message contains HTML - simplistic check
            if (message.includes('<') && message.includes('>')) {
                this.successEl.html(message).removeClass('rv-hidden').removeClass('rv-hidden');
            } else {
                this.successEl.text(message).removeClass('rv-hidden').removeClass('rv-hidden');
            }
            this.errorEl.addClass('rv-hidden').addClass('rv-hidden');
        }

        showLoading(isLoading) {
            if (isLoading) {
                this.loadingEl.removeClass('rv-hidden').removeClass('rv-hidden');
                this.submitBtn.prop('disabled', true);
                this.submitBtn.find('.rv-btn-loading').removeClass('rv-hidden');
            } else {
                this.loadingEl.addClass('rv-hidden').addClass('rv-hidden');
                this.submitBtn.prop('disabled', !!this.availabilityBlocked);
                this.submitBtn.find('.rv-btn-loading').addClass('rv-hidden');
            }
        }

        hideMessages() {
            this.errorEl.addClass('rv-hidden').addClass('rv-hidden');
            this.successEl.addClass('rv-hidden').addClass('rv-hidden');
        }

        showToast(message, type = 'success') {
            // Generic toast implementation or using a library if available
            // Fallback to alert if no toast lib
            // Or use showSuccess/showError
            if (type === 'error') this.showError(message);
            else this.showSuccess(message);
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

        getFavoritesConfig() {
            return window.mhmRentivaFavorites || {};
        }

        getAjaxUrl() {
            return window.mhmRentivaBookingForm?.ajax_url || '';
        }

        // calculateDays() was declared a second time here. A class body keeps
        // the LAST definition, so this naive `new Date(start)` one is what
        // actually ran, and the format-aware original above (which parses with
        // the configured datepicker format) was dead code. `new Date()` cannot
        // read the plugin's non-ISO date formats -- with dateFormat 'dd/mm/yy'
        // it returns Invalid Date and the day count came out NaN. Removed so
        // both call sites reach the implementation that handles the format.

        escapeHtml(text) {
            if (typeof text !== 'string') {
                if (text === null || text === undefined) return '';
                return String(text);
            }
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function (m) { return map[m]; });
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
