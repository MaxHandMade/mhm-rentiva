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
            $.datepicker.setDefaults(window.mhmRentivaBookingForm.datepicker_options);
        }
    });

    class BookingForm {
        constructor() {
            this.container = $('.rv-booking-form');
            this.form = this.container.find('.rv-booking-form-content');
            this.submitBtn = this.form.find('.rv-submit-btn');

            this.loadingEl = $('.rv-loading');
            this.errorEl = $('.rv-error-message');
            this.successEl = $('.rv-success-message');

            // Enable button at form start (terms checkbox will control it)
            this.submitBtn.prop('disabled', false);

            // Deposit system elements
            this.paymentTypeRadios = $('input[name="payment_type"]');
            this.paymentMethodRadios = $('input[name="payment_method"]');
            this.onlinePaymentDetails = $('.rv-online-payment-details');

            // Price display elements
            this.priceElements = {
                dailyPrice: $('#rv-daily-price'),
                daysCount: $('#rv-days-count'),
                vehicleTotal: $('#rv-vehicle-total'),
                addonsTotal: $('#rv-addons-total'),
                totalAmount: $('#rv-total-amount'),
                depositAmount: $('#rv-deposit-amount'),
                remainingAmount: $('#rv-remaining-amount')
            };

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
                // Set the dropdown value if it exists
                $('#vehicle_id').val(preSelectedVehicleId);
            }

            this.initializeDatePickers();
            this.bindEvents();
            this.setupDateValidation();
            this.setupVehicleSelection();
            this.setupFormValidation();
            this.setupDepositSystem();
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
            this.form.on('change', '#vehicle_id', (e) => {
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
            this.form.on('change', '#pickup_date, #dropoff_date, #pickup_time, #dropoff_time, #vehicle_id', () => {
                this.autoCheckAvailability();
                this.autoCalculatePrice();
                this.updateDepositDisplay();
            });

            // Automatic calculation on addon changes
            this.form.on('change', '.rv-addon-checkbox', () => {
                this.autoCalculatePrice();
            });

            // Update price calculation and deposit fields on payment type change
            this.form.on('change', 'input[name="payment_type"]', () => {
                this.autoCalculatePrice();
                this.updateDepositDisplay();
            });

            // Automatically update dropoff time when pickup time changes
            // Dropoff time is disabled and always matches pickup time
            this.form.on('change', '#pickup_time', (e) => {
                const pickupTime = $(e.target).val();
                if (pickupTime) {
                    // Update both visible (disabled) select and hidden input
                    $('#dropoff_time').val(pickupTime);
                    $('#dropoff_time_hidden').val(pickupTime);
                } else {
                    $('#dropoff_time').val('');
                    $('#dropoff_time_hidden').val('');
                }
                // Don't do availability check on time change
            });

            // Initialize dropoff time on page load if pickup time is already selected
            const initialPickupTime = $('#pickup_time').val();
            if (initialPickupTime) {
                $('#dropoff_time').val(initialPickupTime);
                $('#dropoff_time_hidden').val(initialPickupTime);
            }

            // Vehicle selection change
            this.form.on('change', '#vehicle_id', (e) => {
                this.updateVehiclePreview(e.target);
                // ⭐ Check availability when vehicle changes
                this.autoCheckAvailability();
            });

            // Deposit system events
            this.paymentTypeRadios.on('change', () => {
                this.updateDepositDisplay();
            });

            this.paymentMethodRadios.on('change', () => {
                this.updatePaymentMethodDisplay();
            });

            // Show gateway options when online payment method is selected
            this.paymentMethodRadios.on('change', (e) => {
                if ($(e.target).val() === 'online') {
                    this.onlinePaymentDetails.show();
                } else {
                    this.onlinePaymentDetails.hide();
                }
            });

            // Terms checkbox control - enable/disable submit button
            const termsCheckbox = $('#rv-terms-accepted');
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
            const termsCheckbox = $('#rv-terms-accepted');
            if (termsCheckbox.length) {
                const isChecked = termsCheckbox.is(':checked');
                this.submitBtn.prop('disabled', !isChecked);
            }
        }

        handleFavoriteToggle($button) {
            const vehicleId = $button.data('vehicle-id');
            const favoritesConfig = this.getFavoritesConfig();

            if (!vehicleId) {
                return;
            }

            if (!favoritesConfig.nonce) {
                this.showToast(favoritesConfig?.strings?.login_required || 'Please log in to manage favorites.', 'error');
                return;
            }

            $button.prop('disabled', true);

            $.ajax({
                url: favoritesConfig.ajaxUrl || this.getAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'mhm_rentiva_toggle_favorite',
                    vehicle_id: vehicleId,
                    nonce: favoritesConfig.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const isAdded = response.data.action === 'added';
                        $button.toggleClass('is-favorited favorited', isAdded);
                        $button.attr('aria-pressed', isAdded ? 'true' : 'false');
                        $button.attr('aria-label', isAdded ? (favoritesConfig.strings?.remove_label || '') : (favoritesConfig.strings?.add_label || ''));
                        $button.find('.rv-heart-icon').toggleClass('favorited', isAdded);

                        this.showToast(
                            response.data.message || (isAdded ? favoritesConfig.strings?.added : favoritesConfig.strings?.removed),
                            'success'
                        );
                    } else {
                        this.showToast(response.data?.message || favoritesConfig.strings?.error || this.getMessage('error'), 'error');
                    }
                },
                error: () => {
                    this.showToast(favoritesConfig.strings?.error || this.getMessage('error'), 'error');
                },
                complete: () => {
                    $button.prop('disabled', false);
                }
            });
        }

        setupDateValidation() {
            const today = new Date().toISOString().split('T')[0];
            const pickupDate = $('#pickup_date');
            const dropoffDate = $('#dropoff_date');

            // Minimum date is today
            pickupDate.attr('min', today);
            dropoffDate.attr('min', today);

            // Update dropoff date when pickup date changes
            pickupDate.on('change', (e) => {
                const pickupValue = $(e.target).val();
                if (pickupValue) {
                    const nextDay = new Date(pickupValue);
                    nextDay.setDate(nextDay.getDate() + 1);
                    dropoffDate.attr('min', nextDay.toISOString().split('T')[0]);

                    // Clear dropoff date if it's before pickup date
                    if (dropoffDate.val() && dropoffDate.val() <= pickupValue) {
                        dropoffDate.val('');
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
            const vehicleSelect = $('#vehicle_id');
            const preview = $('.rv-selected-vehicle-preview');

            if (!vehicleSelect.length || !preview.length) return;

            // Hide preview on first load
            preview.hide();
        }

        setupFormValidation() {
            // Real-time validation
            this.form.find('input[required], select[required]').on('blur', (e) => {
                this.validateField(e.target);
            });

            // ❌ REMOVED: Double submit event handler issue
            // Form submit validation is already done in submitForm()
            // This handler was causing form to submit normally
        }

        setupDepositSystem() {
            if (!window.mhmRentivaBookingForm?.enable_deposit) return;

            this.updateDepositDisplay();
            this.updatePaymentMethodDisplay();
        }

        initializeDatePickers() {
            // Initialize jQuery UI DatePicker for date inputs
            if (window.mhmRentivaBookingForm && window.mhmRentivaBookingForm.datepicker_options) {
                const options = {
                    ...window.mhmRentivaBookingForm.datepicker_options,
                    // Add custom today button handler
                    beforeShow: function (input, inst) {
                        // Add custom today button functionality
                        setTimeout(function () {
                            const todayBtn = $('.ui-datepicker-buttonpane button:first-child');
                            if (todayBtn.length) {
                                todayBtn.off('click.datepicker-today').on('click.datepicker-today', function () {
                                    const today = new Date();
                                    const formattedDate = $.datepicker.formatDate(options.dateFormat || 'yy-mm-dd', today);
                                    $(input).val(formattedDate).trigger('change');
                                    $(input).datepicker('hide');
                                });
                            }
                        }, 100);
                    }
                };

                // Convert text date inputs to jQuery UI DatePicker
                $('input.rv-date-input').each(function () {
                    const $input = $(this);
                    const originalValue = $input.val();

                    // Initialize datepicker
                    $input.datepicker(options);

                    // Restore original value if exists
                    if (originalValue) {
                        $input.datepicker('setDate', originalValue);
                    }
                });
            }
        }

        initializeDefaults() {
            // Set default values
            if (window.mhmRentivaBookingForm?.default_payment) {
                $(`input[name="payment_type"][value="${window.mhmRentivaBookingForm.default_payment}"]`).prop('checked', true);
            }

            // Set initial online payment details display
            this.updatePaymentMethodDisplay();

            // Initialize dropoff time to match pickup time if pickup time is already selected
            const initialPickupTime = $('#pickup_time').val();
            if (initialPickupTime) {
                $('#dropoff_time').val(initialPickupTime);
                $('#dropoff_time_hidden').val(initialPickupTime);
            }
        }

        updateVehiclePreview(selectElement) {
            const $select = $(selectElement);
            const $option = $select.find('option:selected');
            const preview = $('.rv-selected-vehicle-preview');
            const image = preview.find('.rv-vehicle-image');
            const title = preview.find('.rv-vehicle-title');
            const price = preview.find('.rv-vehicle-price');

            if ($option.val()) {
                const vehiclePrice = $option.data('price');
                const vehicleImage = $option.data('image');
                const vehicleTitle = $option.text().split(' (')[0]; // Remove price part

                title.text(vehicleTitle);
                price.text(this.formatPrice(vehiclePrice) + ' ' + this.getMessage('per_day'));

                if (vehicleImage) {
                    image.attr('src', vehicleImage).show();
                } else {
                    image.hide();
                }

                preview.show();
            } else {
                preview.hide();
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
                action: 'mhm_rentiva_calculate_price',
                nonce: window.mhmRentivaBookingForm?.nonce || '',
                vehicle_id: formData.vehicle_id,
                pickup_date: formData.pickup_date,
                dropoff_date: formData.dropoff_date,
                addons: addonsParam,
                payment_type: $('input[name="payment_type"]:checked').val() || 'deposit'
            };

            // Manually resolve jQuery array serialization issue
            let requestData = new URLSearchParams();
            requestData.append('action', ajaxData.action);
            requestData.append('nonce', ajaxData.nonce);
            requestData.append('vehicle_id', ajaxData.vehicle_id);
            requestData.append('pickup_date', ajaxData.pickup_date);
            requestData.append('dropoff_date', ajaxData.dropoff_date);
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

            const finalData = requestData.toString();

            $.ajax({
                url: window.mhmRentivaBookingForm?.ajax_url || window.location.origin + '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: finalData,
                contentType: 'application/x-www-form-urlencoded',
                success: (response) => {
                    this.showLoading(false);

                    if (response.success) {
                        this.updatePriceDisplay(response.data);
                        this.submitBtn.prop('disabled', false);
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

        autoCalculatePrice() {
            // Auto calculation with debounce (faster for payment type changes)
            clearTimeout(this.autoCalculateTimeout);
            this.autoCalculateTimeout = setTimeout(() => {
                const formData = this.getFormData();
                if (this.validateCalculationData(formData)) {
                    this.calculatePrice();
                }
            }, 100); // Reduced from 500ms to 100ms
        }

        // ⭐ Auto availability check (on date change)
        autoCheckAvailability() {
            // Clear previous timeout
            if (this.autoAvailabilityTimeout) {
                clearTimeout(this.autoAvailabilityTimeout);
            }

            this.autoAvailabilityTimeout = setTimeout(() => {
                const formData = this.getFormData();

                // Check if vehicle, date AND time fields are filled
                if (formData.vehicle_id && formData.pickup_date && formData.dropoff_date && formData.pickup_time && formData.dropoff_time) {
                    this.checkAvailability();
                } else {
                }
            }, 300); // Check with 300ms delay
        }

        getFormData() {
            const addons = [];

            this.form.find('.rv-addon-checkbox:checked').each(function () {
                const addonId = parseInt($(this).val());
                addons.push(addonId);
            });


            // Get Vehicle ID - check container first (when coming from vehicle list/detail page)
            let vehicle_id = this.container.attr('data-vehicle-id');
            if (!vehicle_id) {
                // Get from dropdown (manual selection)
                vehicle_id = $('#vehicle_id').val();
            }
            // Also try alternative selectors
            if (!vehicle_id) {
                vehicle_id = $('select[name="vehicle_id"]').val();
            }
            if (!vehicle_id) {
                vehicle_id = $('input[name="vehicle_id"]').val();
            }

            return {
                vehicle_id: vehicle_id,
                pickup_date: $('#pickup_date').val(),
                dropoff_date: $('#dropoff_date').val(),
                pickup_time: $('#pickup_time').val(),
                dropoff_time: $('#dropoff_time_hidden').val() || $('#pickup_time').val(), // Always match pickup time
                guests: $('#guests').val() || 1,
                customer_first_name: $('#customer_first_name').val(),
                terms_accepted: $('#rv-terms-accepted').is(':checked') ? 'on' : '',
                customer_last_name: $('#customer_last_name').val(),
                customer_email: $('#customer_email').val(),
                customer_phone: $('#customer_phone').val(),
                addons: addons,
                payment_type: $('input[name="payment_type"]:checked').val(),
                payment_method: $('input[name="payment_method"]:checked').val(),
                payment_gateway: $('input[name="payment_gateway"]:checked').val(),
                redirect_url: this.container.attr('data-redirect-url')
            };
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

            // Tarih validasyonu
            const pickup = new Date(data.pickup_date);
            const dropoff = new Date(data.dropoff_date);

            if (dropoff <= pickup) {
                this.showError(this.getMessage('dropoff_after_pickup'));
                return false;
            }

            return true;
        }

        updatePriceDisplay(data) {
            const currencySymbol = data.currency_symbol || window.mhmRentivaBookingForm?.currency_symbol;

            // Daily price
            this.priceElements.dailyPrice.text(this.formatPrice(data.vehicle_price) + ' ' + currencySymbol);

            // Days count
            this.priceElements.daysCount.text(data.days);

            // Vehicle total
            this.priceElements.vehicleTotal.text(this.formatPrice(data.vehicle_total) + ' ' + currencySymbol);

            // Add-ons
            if (data.addon_total > 0) {
                this.priceElements.addonsTotal.text(this.formatPrice(data.addon_total) + ' ' + currencySymbol);
                $('.rv-addons-price').show();
            } else {
                $('.rv-addons-price').hide();
            }

            // Total amount
            this.priceElements.totalAmount.text(this.formatPrice(data.total_price) + ' ' + currencySymbol);

            // Deposit information
            const paymentType = $('input[name="payment_type"]:checked').val();
            if (paymentType === 'deposit' && data.deposit_amount !== undefined) {
                // If deposit payment is selected
                this.priceElements.depositAmount.text(this.formatPrice(data.deposit_amount) + ' ' + currencySymbol);
                this.priceElements.remainingAmount.text(this.formatPrice(data.remaining_amount) + ' ' + currencySymbol);
                $('.rv-deposit-summary').show();
                if (data.remaining_amount > 0) {
                    $('.rv-remaining-summary').show();
                } else {
                    $('.rv-remaining-summary').hide();
                }
            } else if (paymentType === 'full' && data.deposit_amount !== undefined) {
                // If full payment is selected - hide deposit fields completely
                $('.rv-deposit-summary').hide();
                $('.rv-remaining-summary').hide();
            } else {
                // If no payment type is selected, hide fields
                $('.rv-deposit-summary').hide();
                $('.rv-remaining-summary').hide();
            }
        }

        updateDepositDisplay() {
            if (!window.mhmRentivaBookingForm?.enable_deposit) return;

            const paymentType = $('input[name="payment_type"]:checked').val();
            const formData = this.getFormData();

            if (!formData.vehicle_id || !formData.pickup_date || !formData.dropoff_date) {
                $('.rv-deposit-summary, .rv-remaining-summary').hide();
                return;
            }

            // Calculate days count
            const days = this.calculateDays(formData.pickup_date, formData.dropoff_date);

            if (days <= 0) {
                $('.rv-deposit-summary, .rv-remaining-summary').hide();
                return;
            }

            // Deposit information already comes from calculatePrice()
            // No need to make separate AJAX call
            // updateDepositInfo() is automatically called when calculatePrice() is called
        }


        updateDepositInfo(data) {
            const currencySymbol = window.mhmRentivaBookingForm?.currency_symbol;
            const paymentType = $('input[name="payment_type"]:checked').val();

            if (paymentType === 'deposit' && data.deposit_amount > 0) {
                // If deposit payment is selected
                this.priceElements.depositAmount.text(this.formatPrice(data.deposit_amount) + ' ' + currencySymbol);
                this.priceElements.remainingAmount.text(this.formatPrice(data.remaining_amount) + ' ' + currencySymbol);

                // Show remaining amount if exists
                if (data.remaining_amount > 0) {
                    $('.rv-remaining-summary').show();
                } else {
                    $('.rv-remaining-summary').hide();
                }

                $('.rv-deposit-summary').show();
            } else {
                // If full payment is selected - hide deposit field
                $('.rv-deposit-summary').hide();
                $('.rv-remaining-summary').hide();
            }
        }

        updatePaymentMethodDisplay() {
            const paymentMethod = $('input[name="payment_method"]:checked').val();

            if (paymentMethod === 'online') {
                this.onlinePaymentDetails.show();
            } else {
                this.onlinePaymentDetails.hide();
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

            // Email validation
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

            // Tarih validasyonu
            const pickupDate = $('#pickup_date').val();
            const dropoffDate = $('#dropoff_date').val();
            const pickupTime = $('#pickup_time').val();

            // Pickup time validation (required)
            if (!pickupTime) {
                $('#pickup_time').addClass('error');
                this.showError(this.getMessage('selectPickupTime') || __('Please select pickup time.', 'mhm-rentiva'));
                isValid = false;
            }

            if (pickupDate && dropoffDate) {
                const pickup = new Date(pickupDate);
                const dropoff = new Date(dropoffDate);

                if (dropoff <= pickup) {
                    $('#dropoff_date').addClass('error');
                    this.showError(this.getMessage('invalid_dates'));
                    isValid = false;
                }
            }

            // Payment validation
            if (window.mhmRentivaBookingForm?.enable_deposit) {
                if (!$('input[name="payment_type"]:checked').length) {
                    this.showError(this.getMessage('selectPaymentType'));
                    isValid = false;
                }

                if (!$('input[name="payment_method"]:checked').length) {
                    this.showError(this.getMessage('selectPaymentMethod'));
                    isValid = false;
                }

                const paymentMethod = $('input[name="payment_method"]:checked').val();
                if (paymentMethod === 'online' && !$('input[name="payment_gateway"]:checked').length) {
                    this.showError(this.getMessage('select_payment_gateway'));
                    isValid = false;
                }
            }

            // ⭐ Terms & Conditions validation (only if checkbox exists and is required)
            const termsCheckbox = $('#rv-terms-accepted');
            if (termsCheckbox.length) {
                if (!termsCheckbox.is(':checked')) {
                    this.showError('You must accept the terms and conditions to complete your booking.');
                    termsCheckbox.closest('.rv-terms-checkbox').addClass('error');
                    termsCheckbox.focus();
                    isValid = false;
                } else {
                    // Clear error if was set before
                    termsCheckbox.closest('.rv-terms-checkbox').removeClass('error');
                }
            }

            return isValid;
        }

        submitForm() {

            // ⭐ Get form data FIRST (before validateForm is called!)
            const formData = this.getFormData();

            if (!this.validateForm()) {
                return false;
            }

            this.showLoading(true);
            this.hideMessages();

            // Prepare AJAX data - serialize arrays correctly
            const ajaxData = {
                action: 'mhm_rentiva_booking_form',
                nonce: window.mhmRentivaBookingForm?.nonce || '',
                ...formData
            };


            // Prepare URL encoded payload - send array fields with []
            const requestData = new URLSearchParams();
            Object.entries(ajaxData).forEach(([key, value]) => {
                if (Array.isArray(value)) {
                    if (key === 'addons') {
                        if (value.length > 0) {
                            value.forEach((addonId) => {
                                requestData.append('addons[]', addonId);
                            });
                        }
                    } else {
                        value.forEach((item) => {
                            requestData.append(`${key}[]`, item);
                        });
                    }
                } else if (value !== undefined && value !== null && value !== '') {
                    requestData.append(key, value);
                }
            });

            const finalData = requestData.toString();

            $.ajax({
                url: window.mhmRentivaBookingForm?.ajax_url || window.location.origin + '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: finalData,
                contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
                dataType: 'json',
                success: (response) => {
                    this.showLoading(false);

                    if (response.success) {
                        // Check if payment is required
                        if (response.data?.payment_required && response.data?.payment_url) {
                            // Redirect to payment page
                            this.showSuccess(response.data?.message || this.getMessage('redirecting_to_payment'));

                            setTimeout(() => {
                                // WooCommerce redirection should happen in the same window
                                if (response.data.payment_method === 'woocommerce' || response.data.payment_url.includes('checkout')) {
                                    window.location.href = response.data.payment_url;
                                } else {
                                    // Open other payment gateways in new window (if needed)
                                    this.openPaymentWindow(response.data.payment_url, response.data.redirect_url);
                                }
                            }, 1500);
                        } else {
                            // Direct success message and redirect
                            this.showSuccess(response.data?.message || this.getMessage('success'));

                            if (response.data?.confirmation_url) {
                                window.location.href = response.data.confirmation_url;
                                return;
                            }

                            if (response.data?.redirect_url) {
                                window.location.href = response.data.redirect_url;
                            }
                        }
                    } else {
                        this.showError(response.data?.message || this.getMessage('error'));
                    }
                },
                error: () => {
                    this.showLoading(false);
                    this.showError(this.getMessage('error'));
                }
            });

            return false;
        }

        showLoading(show) {
            if (show) {
                this.submitBtn.prop('disabled', true);
                this.submitBtn.find('.rv-btn-loading').show();
                this.submitBtn.find('.rv-btn-text').hide();
            } else {
                this.submitBtn.prop('disabled', false);
                this.submitBtn.find('.rv-btn-loading').hide();
                this.submitBtn.find('.rv-btn-text').show();
            }
        }

        showError(message) {
            this.errorEl.html(`<div class="rv-error-message">${message}</div>`).show();
            this.successEl.hide();

            // Auto hide after 8 seconds
            setTimeout(() => {
                this.errorEl.fadeOut();
            }, 8000);
        }

        showSuccess(message) {
            this.successEl.html(`<div class="rv-success-message">${message}</div>`).show();
            this.errorEl.hide();
        }

        hideMessages() {
            this.errorEl.hide();
            this.successEl.hide();
        }

        calculateDays(startDate, endDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            const diffTime = Math.abs(end - start);
            return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        }

        formatPrice(price) {
            const locale = this.convertLocaleFormat(window.mhmRentivaBookingForm?.locale || 'en-US');
            return new Intl.NumberFormat(locale, {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2
            }).format(price);
        }

        convertLocaleFormat(locale) {
            // Convert WordPress locale format (en_US) to JavaScript format (en-US)
            if (locale && locale.includes('_')) {
                return locale.replace('_', '-');
            }
            return locale || 'en-US';
        }

        getMessage(key) {
            return window.mhmRentivaBookingForm?.strings?.[key] || key;
        }

        getFavoritesConfig() {
            return window.mhmRentivaBookingForm?.favorites || {};
        }

        getAjaxUrl() {
            return window.mhmRentivaBookingForm?.ajax_url ||
                window.mhmRentivaBookingForm?.ajaxUrl ||
                (window.location.origin + '/wp-admin/admin-ajax.php');
        }

        showToast(message, type = 'info') {
            if (!message) {
                return;
            }

            const $notification = $(`<div class="rv-notification rv-notification--${type}">${message}</div>`);

            $('body').append($notification);

            setTimeout(() => {
                $notification.addClass('rv-notification--show');
            }, 50);

            setTimeout(() => {
                $notification.removeClass('rv-notification--show');
                setTimeout(() => $notification.remove(), 300);
            }, 3000);
        }

        /**
         * Check availability
         */
        checkAvailability() {
            const formData = this.getFormData();

            if (!formData.vehicle_id || !formData.pickup_date || !formData.dropoff_date) {
                return;
            }

            // Element for availability status display
            let availabilityStatus = this.form.find('.rv-availability-status');

            // Remove hidden class (if exists)
            availabilityStatus.removeClass('hidden');

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
            availabilityStatus.html(`<div class="rv-loading-message"><span class="rv-spinner"></span> <span>${this.getMessage('checking_availability')}</span></div>`);


            // Debug log for AJAX data
            const ajaxData = {
                action: 'mhm_rentiva_check_availability',
                vehicle_id: formData.vehicle_id,
                pickup_date: formData.pickup_date,
                pickup_time: formData.pickup_time || '09:00',
                dropoff_date: formData.dropoff_date,
                dropoff_time: formData.dropoff_time || '09:00',
                nonce: window.mhmRentivaBookingForm?.nonce || ''
            };

            $.ajax({
                url: window.mhmRentivaBookingForm?.ajaxUrl || window.location.origin + '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: ajaxData,
                success: (response) => {
                    availabilityStatus.removeClass('hidden'); // Show in all cases

                    if (response.success) {
                        // Available - Modern success card
                        availabilityStatus.removeClass('loading error').addClass('success');
                        availabilityStatus.html(`
                            <div class="rv-availability-success">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <span>${this.getMessage('vehicle_available')}</span>
                            </div>
                        `);

                        // ✅ Vehicle available - enable button
                        this.submitBtn.prop('disabled', false);
                    } else {
                        // Not available
                        let message = response.data?.message || this.getMessage('vehicle_not_available');
                        let alternativesHtml = '';

                        if (response.data?.alternatives && response.data.alternatives.length > 0) {
                            message = this.getMessage('vehicle_unavailable_with_alternatives');
                            alternativesHtml = `
                                <div class="rv-alternatives-wrapper">
                                    <div class="rv-alternatives-title">${this.getMessage('alternative_vehicles') || __('Alternative Vehicles', 'mhm-rentiva')}</div>
                                    <div class="rv-alternatives-grid">
                            `;

                            response.data.alternatives.forEach(vehicle => {
                                alternativesHtml += `
                                    <div class="rv-alternative-vehicle-card" data-vehicle-id="${vehicle.id}">
                                        <div class="rv-alternative-vehicle-image">
                                            <img src="${vehicle.image || window.location.origin + '/wp-content/plugins/mhm-rentiva/assets/images/no-image.png'}" alt="${vehicle.title}">
                                        </div>
                                        <div class="rv-alternative-vehicle-content">
                                            <h5 class="rv-alternative-vehicle-title">${vehicle.title}</h5>
                                            
                                            ${vehicle.features && vehicle.features.length > 0 ? `
                                                <div class="rv-alternative-vehicle-features">
                                                    ${vehicle.features.map(feature => `
                                                        <span class="rv-alternative-feature-tag">${feature.replace(/_/g, ' ')}</span>
                                                    `).join('')}
                                                </div>
                                            ` : ''}
                                            
                                            <div class="rv-alternative-price-details">
                                                <div class="rv-alternative-price-row">
                                                    <span class="rv-alternative-price-label">${this.getMessage('daily_price')}:</span>
                                                    <span class="rv-alternative-price-value">${this.formatPrice(vehicle.price_per_day)} ${window.mhmRentivaBookingForm?.currency_symbol || ''}</span>
                                                </div>
                                                <div class="rv-alternative-price-row rv-alternative-price-total">
                                                    <span class="rv-alternative-price-label">${this.getMessage('total')}:</span>
                                                    <span class="rv-alternative-price-amount">${this.formatPrice(vehicle.total_price)} ${window.mhmRentivaBookingForm?.currency_symbol || ''}</span>
                                                </div>
                                            </div>
                                            <button type="button" class="rv-select-alternative-btn" data-vehicle-id="${vehicle.id}">
                                                ${this.getMessage('select_this_vehicle')}
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
                                    <strong>${message}</strong>
                                </div>
                                ${alternativesHtml}
                            </div>
                        `);

                        // ❌ Vehicle not available - disable button
                        this.submitBtn.prop('disabled', true);

                        // Alternative vehicle selection buttons
                        availabilityStatus.find('.rv-select-alternative-btn').on('click', (e) => {
                            const vehicleId = $(e.target).data('vehicle-id');
                            this.selectAlternativeVehicle(vehicleId);
                        });
                    }
                },
                error: (xhr, status, error) => {
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

                    // ❌ Availability check failed - disable button
                    this.submitBtn.prop('disabled', true);
                }
            });
        }

        /**
         * Select alternative vehicle
         */
        selectAlternativeVehicle(vehicleId) {

            // Clear availability status
            this.form.find('.rv-availability-status').remove();

            // Reload form with new vehicle ID
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('vehicle_id', vehicleId);

            // Reload page
            window.location.href = currentUrl.toString();
        }

        /**
         * Open payment window
         */
        openPaymentWindow(paymentUrl, successUrl) {
            // Payment window dimensions
            const width = 800;
            const height = 600;
            const left = (screen.width - width) / 2;
            const top = (screen.height - height) / 2;

            // Open new window
            const paymentWindow = window.open(
                paymentUrl,
                'paymentWindow',
                `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`
            );

            if (!paymentWindow) {
                // Redirect directly if popup is blocked
                this.showError(this.getMessage('popup_blocked_redirecting'));
                setTimeout(() => {
                    window.location.href = paymentUrl;
                }, 2000);
                return;
            }

            // Check when payment window is closed
            const checkClosed = setInterval(() => {
                if (paymentWindow.closed) {
                    clearInterval(checkClosed);

                    // Check if payment was successful (via localStorage or cookie)
                    const paymentStatus = localStorage.getItem('mhm_rentiva_payment_status');

                    if (paymentStatus === 'success') {
                        // Successful payment
                        localStorage.removeItem('mhm_rentiva_payment_status');
                        this.showSuccess(this.getMessage('payment_completed'));

                        setTimeout(() => {
                            window.location.href = successUrl;
                        }, 2000);
                    } else if (paymentStatus === 'cancelled') {
                        // Payment cancelled
                        localStorage.removeItem('mhm_rentiva_payment_status');
                        this.showError(this.getMessage('payment_cancelled'));
                    } else {
                        // Unknown status
                        this.showError(this.getMessage('payment_status_unknown'));
                    }
                }
            }, 1000);

            // Stop automatic check after 30 minutes
            setTimeout(() => {
                clearInterval(checkClosed);
                if (!paymentWindow.closed) {
                    paymentWindow.close();
                }
            }, 30 * 60 * 1000);
        }
    }

    // Show messages from URL parameters
    function showUrlMessages() {
        const urlParams = new URLSearchParams(window.location.search);
        const booking = urlParams.get('booking');
        const message = urlParams.get('message');

        if (booking === 'ok') {
            const bookingId = urlParams.get('bid');
            const successMessage = bookingId
                ? `${window.mhmRentivaBookingForm?.strings?.booking_created_with_id || 'Your booking has been successfully created!'} ${bookingId}`
                : window.mhmRentivaBookingForm?.strings?.booking_created || 'Your booking has been successfully created!';

            $('.rv-success-message').text(successMessage).show();

            // Clean URL
            const newUrl = window.location.pathname;
            window.history.replaceState({}, document.title, newUrl);
        } else if (booking === 'error' && message) {
            $('.rv-error-message').text(decodeURIComponent(message)).show();

            // Clean URL
            const newUrl = window.location.pathname;
            window.history.replaceState({}, document.title, newUrl);
        }
    }

    // Make BookingForm class globally accessible
    window.BookingForm = BookingForm;

    // Initialize on page load
    jQuery(document).ready(function ($) {
        new BookingForm();
        showUrlMessages();
    });

})(jQuery);
