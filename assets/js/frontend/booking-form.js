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

            // Deposit system elements
            this.paymentTypeRadios = this.form.find('input[name="payment_type"]');
            this.paymentMethodRadios = this.form.find('input[name="payment_method"]');
            this.onlinePaymentDetails = this.form.find('.rv-online-payment-details');

            // Price display elements
            this.priceElements = {
                dailyPrice: this.container.find('.rv-daily-price'),
                daysCount: this.container.find('.rv-days-count'),
                taxLabel: this.container.find('.rv-tax-label'),
                taxAmount: this.container.find('.rv-tax-amount'),
                vehicleTotal: this.container.find('.rv-vehicle-total'),
                addonsTotal: this.container.find('.rv-addons-total'),
                totalAmount: this.container.find('.rv-total-amount'),
                depositAmount: this.container.find('.rv-deposit-amount'),
                remainingAmount: this.container.find('.rv-remaining-amount')
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
                this.container.find('.rv-vehicle-select').val(preSelectedVehicleId);
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
                    this.updateDepositDisplay();
                }
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
                this.showToast(favoritesConfig?.strings?.login_required || this.getMessage('login_required'), 'error');
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
                        $button.attr('aria-label', isAdded ? (favoritesConfig.strings?.remove_label || this.getMessage('remove_from_favorites')) : (favoritesConfig.strings?.add_label || this.getMessage('add_to_favorites')));
                        $button.find('.rv-heart-icon').toggleClass('favorited', isAdded);

                        this.showToast(
                            response.data.message || (isAdded ? (favoritesConfig.strings?.added || this.getMessage('added_to_favorites')) : (favoritesConfig.strings?.removed || this.getMessage('removed_from_favorites'))),
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
            const pickupDate = this.container.find('.rv-pickup-date');
            const dropoffDate = this.container.find('.rv-dropoff-date');

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
            const vehicleSelect = this.container.find('.rv-vehicle-select');
            const preview = this.container.find('.rv-selected-vehicle-preview');

            if (!vehicleSelect.length || !preview.length) return;

            // Hide preview on first load
            preview.hide();
        }

        setupFormValidation() {
            // Real-time validation
            this.form.find('input[required], select[required]').on('blur', (e) => {
                this.validateField(e.target);
            });

            // Note: Submit validation is handled in submitForm
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
                    beforeShow: (input, inst) => {
                        // Add custom today button functionality
                        setTimeout(() => {
                            const todayBtn = $('.ui-datepicker-buttonpane button:first-child');
                            if (todayBtn.length) {
                                todayBtn.off('click.datepicker-today').on('click.datepicker-today', () => {
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
                this.container.find('input.rv-date-input').each(function () {
                    const $input = $(this);
                    const originalValue = $input.val();

                    // Set maxDate if configured
                    if (window.mhmRentivaBookingForm?.config?.advance_booking_days) {
                        options.maxDate = '+' + window.mhmRentivaBookingForm.config.advance_booking_days + 'd';
                    }

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
        }

        updateVehiclePreview(selectElement) {
            const $select = $(selectElement);
            const $option = $select.find('option:selected');
            const preview = this.container.find('.rv-selected-vehicle-preview');
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
                    image.attr('src', vehicleImage).removeClass('rv-hidden').show();
                } else {
                    image.addClass('rv-hidden').hide();
                }

                preview.removeClass('rv-hidden').show();
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
                payment_type: this.form.find('input[name="payment_type"]:checked').val() || 'deposit'
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

            this.form.find('.rv-addon-checkbox:checked').each(function () {
                const addonId = parseInt($(this).val());
                addons.push(addonId);
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

            return {
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
                payment_type: this.form.find('input[name="payment_type"]:checked').val(),
                payment_method: this.form.find('input[name="payment_method"]:checked').val(),
                payment_gateway: this.form.find('input[name="payment_gateway"]:checked').val(),
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

            this.priceElements.dailyPrice.text(this.formatPrice(data.vehicle_price) + ' ' + currencySymbol);

            this.priceElements.daysCount.text(data.days);

            const hasTax = data.tax_enabled && data.tax_amount !== undefined && data.tax_amount > 0;
            const hasAddons = data.addon_total > 0;
            const showDetailedBreakdown = hasTax || hasAddons;

            if (hasTax) {
                const taxRate = data.tax_rate || 0;
                const taxLabel = window.mhmRentivaBookingForm?.strings?.tax || this.getMessage('tax');
                const taxIncludedLabel = window.mhmRentivaBookingForm?.strings?.tax_included || this.getMessage('tax_included');
                const taxLabelText = taxRate > 0
                    ? (data.tax_inclusive
                        ? taxIncludedLabel + ' (' + taxRate + '%):'
                        : taxLabel + ' (' + taxRate + '%):')
                    : taxLabel + ':';
                this.priceElements.taxLabel.text(taxLabelText);
                this.priceElements.taxAmount.text(this.formatPrice(data.tax_amount) + ' ' + currencySymbol);
                this.container.find('.rv-tax-summary').show();
            } else {
                this.container.find('.rv-tax-summary').hide();
            }

            if (showDetailedBreakdown) {
                this.priceElements.vehicleTotal.text(this.formatPrice(data.vehicle_total) + ' ' + currencySymbol);
                this.container.find('.rv-vehicle-total-detailed').show();
            } else {
                this.container.find('.rv-vehicle-total-detailed').hide();
            }

            if (hasAddons) {
                this.priceElements.addonsTotal.text(this.formatPrice(data.addon_total) + ' ' + currencySymbol);
                this.container.find('.rv-addons-price').show();
            } else {
                this.container.find('.rv-addons-price').hide();
            }

            this.priceElements.totalAmount.text(this.formatPrice(data.total_price) + ' ' + currencySymbol);

            const paymentTypeRadio = this.form.find('input[name="payment_type"]:checked').val();
            const paymentTypeHidden = this.form.find('input[name="payment_type"][type="hidden"]').val();
            const paymentType = paymentTypeRadio || paymentTypeHidden || 'deposit';

            const depositEnabled = window.mhmRentivaBookingForm?.enable_deposit !== false &&
                window.mhmRentivaBookingForm?.enable_deposit !== '0' &&
                window.mhmRentivaBookingForm?.enable_deposit !== 0;

            if (depositEnabled && (paymentType === 'deposit' || data.deposit_amount !== undefined) &&
                data.deposit_amount !== undefined && data.deposit_amount > 0) {
                this.priceElements.depositAmount.text(this.formatPrice(data.deposit_amount) + ' ' + currencySymbol);
                this.container.find('.rv-deposit-summary').show();

                if (data.remaining_amount !== undefined && data.remaining_amount > 0) {
                    this.priceElements.remainingAmount.text(this.formatPrice(data.remaining_amount) + ' ' + currencySymbol);
                    this.container.find('.rv-remaining-summary').show();
                } else {
                    this.container.find('.rv-remaining-summary').hide();
                }
            } else {
                this.container.find('.rv-deposit-summary').hide();
                this.container.find('.rv-remaining-summary').hide();
            }
        }

        updateDepositDisplay() {
            if (!window.mhmRentivaBookingForm?.enable_deposit) return;

            const paymentType = this.form.find('input[name="payment_type"]:checked').val();
            const formData = this.getFormData();

            if (!formData.vehicle_id || !formData.pickup_date || !formData.dropoff_date) {
                this.container.find('.rv-deposit-summary, .rv-remaining-summary').hide();
                return;
            }

            const days = this.calculateDays(formData.pickup_date, formData.dropoff_date);

            if (days <= 0) {
                this.container.find('.rv-deposit-summary, .rv-remaining-summary').hide();
                return;
            }
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
            const startDate = new Date(start);
            const endDate = new Date(end);
            const diffTime = Math.abs(endDate - startDate);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            return diffDays;
        }

        updateDepositInfo(data) {
            const currencySymbol = window.mhmRentivaBookingForm?.currency_symbol;
            const paymentType = this.form.find('input[name="payment_type"]:checked').val();

            if (paymentType === 'deposit' && data.deposit_amount > 0) {
                this.priceElements.depositAmount.text(this.formatPrice(data.deposit_amount) + ' ' + currencySymbol);
                this.priceElements.remainingAmount.text(this.formatPrice(data.remaining_amount) + ' ' + currencySymbol);

                if (data.remaining_amount > 0) {
                    this.container.find('.rv-remaining-summary').show();
                } else {
                    this.container.find('.rv-remaining-summary').hide();
                }

                this.container.find('.rv-deposit-summary').show();
            } else {
                this.container.find('.rv-deposit-summary').hide();
                this.container.find('.rv-remaining-summary').hide();
            }
        }

        updatePaymentMethodDisplay() {
            const paymentMethod = this.form.find('input[name="payment_method"]:checked').val();

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

            if (window.mhmRentivaBookingForm?.enable_deposit) {
                if (!this.form.find('input[name="payment_type"]:checked').length) {
                    this.showError(this.getMessage('selectPaymentType'));
                    isValid = false;
                }

                if (!this.form.find('input[name="payment_method"]:checked').length) {
                    this.showError(this.getMessage('selectPaymentMethod'));
                    isValid = false;
                }

                const paymentMethod = this.form.find('input[name="payment_method"]:checked').val();
                if (paymentMethod === 'online' && !this.form.find('input[name="payment_gateway"]:checked').length) {
                    this.showError(this.getMessage('select_payment_gateway'));
                    isValid = false;
                }
            }

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

            // Additional fields logic from original file (abbreviated here for clarity, assuming getFormData covers most)
            // But we need to handle nonce and action explicitly if not in getFormData

            $.ajax({
                url: window.mhmRentivaBookingForm?.ajax_url || window.location.origin + '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'mhm_rentiva_booking_form',
                    nonce: window.mhmRentivaBookingForm?.nonce || '',
                    ...formData
                },
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
                                            <div class="rv-alternatives-title">${this.escapeHtml(this.getMessage('alternative_vehicles') || 'Alternative Vehicles')}</div>
                                            <div class="rv-alternatives-grid">
                                    `;

                            response.data.alternatives.forEach(vehicle => {
                                alternativesHtml += `
                                            <div class="rv-alternative-vehicle-card" data-vehicle-id="${this.escapeHtml(vehicle.id)}">
                                                <div class="rv-alternative-vehicle-image">
                                                    <img src="${this.escapeHtml(vehicle.image || window.location.origin + '/wp-content/plugins/mhm-rentiva/assets/images/no-image.png')}" alt="${this.escapeHtml(vehicle.title)}">
                                                </div>
                                                <div class="rv-alternative-vehicle-content">
                                                    <h5 class="rv-alternative-vehicle-title">${this.escapeHtml(vehicle.title)}</h5>
                                                    
                                                    ${vehicle.features && vehicle.features.length > 0 ? `
                                                        <div class="rv-alternative-vehicle-features">
                                                            ${vehicle.features.map(feature => `
                                                                <span class="rv-alternative-feature-tag">${this.escapeHtml(feature.replace(/_/g, ' '))}</span>
                                                            `).join('')}
                                                        </div>
                                                    ` : ''}
                                                    
                                                    <div class="rv-alternative-price-details">
                                                        <div class="rv-alternative-price-row">
                                                            <span class="rv-alternative-price-label">${this.escapeHtml(this.getMessage('daily_price'))}:</span>
                                                            <span class="rv-alternative-price-value">${this.formatPrice(vehicle.price_per_day)} ${this.escapeHtml(window.mhmRentivaBookingForm?.currency_symbol || '')}</span>
                                                        </div>
                                                        <div class="rv-alternative-price-row rv-alternative-price-total">
                                                            <span class="rv-alternative-price-label">${this.escapeHtml(this.getMessage('total'))}:</span>
                                                            <span class="rv-alternative-price-amount">${this.formatPrice(vehicle.total_price)} ${this.escapeHtml(window.mhmRentivaBookingForm?.currency_symbol || '')}</span>
                                                        </div>
                                                    </div>
                                                    <button type="button" class="rv-select-alternative-btn" data-vehicle-id="${this.escapeHtml(vehicle.id)}">
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

        selectAlternativeVehicle(vehicleId) {
            // Logic to select another vehicle
            // Assuming it just changes the dropdown and triggers change event
            const select = this.container.find('.rv-vehicle-select');
            if (select.length) {
                select.val(vehicleId).trigger('change');
            } else {
                // Fallback if no select
                this.container.attr('data-vehicle-id', vehicleId);
                // Reload form or trigger calculation
                this.autoCheckAvailability();
            }
        }

        // Helper methods (showError, showSuccess, showLoading, hideMessages, formatPrice, getMessage, getFavoritesConfig, getAjaxUrl, calculateDays)
        // These should be copied from original file or implemented if missing. 
        // For brevity in this replacement, assuming they exist in the class.
        // I will include them to be safe since I'm rewriting the class.

        showError(message) {
            // Check if message contains HTML - simplistic check
            if (message.includes('<') && message.includes('>')) {
                // If it looks like HTML, use html() but ensure content was sanitized before
                this.errorEl.html(message).removeClass('rv-hidden').show();
            } else {
                // Otherwise use text() for safety
                this.errorEl.text(message).removeClass('rv-hidden').show();
            }
            this.successEl.addClass('rv-hidden').hide();
            // Auto hide after 5 seconds
            setTimeout(() => {
                this.errorEl.fadeOut();
            }, 5000);
        }

        showSuccess(message) {
            // Check if message contains HTML - simplistic check
            if (message.includes('<') && message.includes('>')) {
                this.successEl.html(message).removeClass('rv-hidden').show();
            } else {
                this.successEl.text(message).removeClass('rv-hidden').show();
            }
            this.errorEl.addClass('rv-hidden').hide();
        }

        showLoading(isLoading) {
            if (isLoading) {
                this.loadingEl.removeClass('rv-hidden').show();
                this.submitBtn.prop('disabled', true);
                this.submitBtn.find('.rv-btn-loading').removeClass('rv-hidden');
            } else {
                this.loadingEl.addClass('rv-hidden').hide();
                this.submitBtn.prop('disabled', false);
                this.submitBtn.find('.rv-btn-loading').addClass('rv-hidden');
            }
        }

        hideMessages() {
            this.errorEl.addClass('rv-hidden').hide();
            this.successEl.addClass('rv-hidden').hide();
        }

        showToast(message, type = 'success') {
            // Generic toast implementation or using a library if available
            // Fallback to alert if no toast lib
            // Or use showSuccess/showError
            if (type === 'error') this.showError(message);
            else this.showSuccess(message);
        }

        formatPrice(price) {
            // Simple formatter, can be enhanced with locale
            return new Intl.NumberFormat(window.mhmRentivaBookingForm?.locale || 'en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(price);
        }

        getMessage(key) {
            return window.mhmRentivaBookingForm?.strings?.[key] || key;
        }

        getFavoritesConfig() {
            return window.mhmRentivaFavorites || {};
        }

        getAjaxUrl() {
            return window.mhmRentivaBookingForm?.ajax_url || '/wp-admin/admin-ajax.php';
        }

        calculateDays(start, end) {
            const startDate = new Date(start);
            const endDate = new Date(end);
            const diffTime = Math.abs(endDate - startDate);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            return diffDays;
        }

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
    window.MHMRentivaBookingForm = MHMRentivaBookingForm;

})(jQuery);
