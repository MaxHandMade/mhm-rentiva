/**
 * Availability Calendar JavaScript
 * 
 * @package MHMRentiva
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    // Create default values if JavaScript object doesn't exist
    if (typeof window.mhmRentivaAvailability === 'undefined') {
        // Use WordPress global ajaxurl variable
        const ajaxUrl = window.ajaxurl || (window.location.origin + '/wp-admin/admin-ajax.php');

        window.mhmRentivaAvailability = {
            ajaxUrl: ajaxUrl,
            nonce: '',
            currencySymbol: window.mhmRentivaAvailability?.currencySymbol || '$',
            pluginUrl: window.mhmRentivaAvailability?.pluginUrl || '',
            dateFormat: window.mhmRentivaAvailability?.dateFormat || 'Y-m-d',
            timeFormat: window.mhmRentivaAvailability?.timeFormat || 'H:i',
            locale: window.mhmRentivaAvailability?.locale || 'en_US',
            messages: {
                error: window.mhmRentivaAvailability?.messages?.error || 'An error occurred.',
                success: window.mhmRentivaAvailability?.messages?.success || 'Operation successful.'
            }
        };
        console.warn('⚠️ mhmRentivaAvailability object not defined in template, using default values');
    }

    class AvailabilityCalendar {
        constructor() {
            this.calendar = null;
            this.selectedDates = [];
            this.currentVehicleId = 0;
            this.currentStartMonth = '';
            this.monthsToShow = 1;
            this.isLoading = false;

            this.init();
        }

        init() {
            this.bindEvents();
            this.initializeCalendar();
        }

        escapeHtml(text) {
            if (!text) return '';
            return String(text)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        bindEvents() {
            // Vehicle change
            $(document).on('change', '.rv-vehicle-dropdown', this.handleVehicleChange.bind(this));

            // Change Vehicle button
            $(document).on('click', '.rv-switch-vehicle-btn', this.handleVehicleSwitch.bind(this));

            // Fallback vehicle selector
            $(document).on('change', '#rv-availability-vehicle-select-fallback', this.handleVehicleChange.bind(this));

            // Month navigation
            $(document).on('click', '.rv-control-btn', this.handleMonthNavigation.bind(this));

            // Date selection
            $(document).on('click', '.rv-calendar-day:not(.rv-day-empty):not(.rv-past):not(.rv-status-booked):not(.rv-status-maintenance)', this.handleDateClick.bind(this));

            // Booking button
            $(document).on('click', '.rv-book-now-btn', this.handleBookingClick.bind(this));

            // Keyboard navigation
            $(document).on('keydown', '.rv-calendar-day', this.handleKeyboardNavigation.bind(this));

            // Favorite button
            $(document).on('click', '.rv-favorite-btn', this.handleFavoriteClick.bind(this));
        }

        initializeCalendar() {
            this.calendar = $('.rv-availability-calendar');
            if (this.calendar.length === 0) return;

            this.currentVehicleId = parseInt(this.calendar.data('vehicle-id')) || 0;
            this.currentStartMonth = this.calendar.data('start-month') || this.getCurrentMonth();
            this.monthsToShow = parseInt(this.calendar.data('months-to-show')) || 3;

            this.updateSelectedDatesDisplay();
        }

        handleVehicleChange(e) {
            const vehicleId = parseInt($(e.target).val());
            if (vehicleId === this.currentVehicleId) return;

            this.currentVehicleId = vehicleId;
            this.selectedDates = [];

            // Update vehicle name
            this.updateVehicleName(vehicleId);

            this.loadAvailabilityData();
        }

        handleVehicleSwitch(event) {
            const $btn = $(event.target).closest('.rv-switch-vehicle-btn');
            const vehiclesData = $btn.data('vehicles');

            if (vehiclesData && vehiclesData.length > 1) {
                // Simple vehicle selection modal
                this.showVehicleSelectionModal(vehiclesData);
            }
        }

        showVehicleSelectionModal(vehicles) {

            // Get localized strings
            const strings = window.mhmRentivaAvailability?.available?.strings || window.mhmRentivaAvailability?.messages || {};
            // Fallback object for strings directly injected
            const fallbackStrings = window.mhmRentivaAvailability?.strings || {};

            // Merge strings (prioritize specific strings)
            const i18n = { ...strings, ...fallbackStrings };
            const title = i18n.select_vehicle || 'Select Vehicle';
            const closeBtn = i18n.close || 'Close';
            const perDay = i18n.per_day || '/day';
            const currencySymbol = window.mhmRentivaAvailability?.currencySymbol || '$';

            // Create modal HTML
            const modalHtml = `
                <div class="rv-vehicle-selection-modal" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.5);
                    z-index: 9999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                ">
                    <div style="
                        background: white;
                        padding: 20px;
                        border-radius: 8px;
                        max-width: 400px;
                        width: 90%;
                        max-height: 80vh;
                        overflow-y: auto;
                    ">
                        <h3 style="margin: 0 0 15px 0;">${title}</h3>
                        <div class="rv-vehicle-list">
                            ${vehicles.map(vehicle => `
                                <div class="rv-vehicle-option" style="
                                    padding: 10px;
                                    border: 1px solid #ddd;
                                    border-radius: 6px;
                                    margin-bottom: 10px;
                                    cursor: pointer;
                                    transition: all 0.2s ease;
                                " data-vehicle-id="${vehicle.id}">
                                    <strong>${this.escapeHtml(vehicle.title)}</strong>
                                    <div style="color: #666; font-size: 14px;">${this.formatPrice(vehicle.price || 0)} ${perDay}</div>
                                </div>
                            `).join('')}
                        </div>
                        <button class="rv-close-modal" style="
                            margin-top: 15px;
                            padding: 8px 16px;
                            background: #0073aa;
                            color: white;
                            border: none;
                            border-radius: 4px;
                            cursor: pointer;
                        ">${closeBtn}</button>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);

            // Bind events
            $('.rv-vehicle-option').on('click', (e) => {
                const vehicleId = $(e.currentTarget).data('vehicle-id');
                this.currentVehicleId = vehicleId;

                // Update vehicle info
                this.updateVehicleInfo(vehicleId);

                // Load calendar data
                this.loadAvailabilityData();

                $('.rv-vehicle-selection-modal').remove();
            });

            $('.rv-close-modal').on('click', () => {
                $('.rv-vehicle-selection-modal').remove();
            });

            // Close on backdrop click
            $('.rv-vehicle-selection-modal').on('click', (e) => {
                if (e.target === e.currentTarget) {
                    $('.rv-vehicle-selection-modal').remove();
                }
            });
        }

        updateVehicleInfo(vehicleId) {
            // Update vehicle info via AJAX
            const data = {
                action: 'mhm_rentiva_get_vehicle_info',
                vehicle_id: vehicleId,
                nonce: window.mhmRentivaAvailability.nonce
            };

            $.post(window.mhmRentivaAvailability.ajaxUrl, data)
                .done((response) => {
                    if (response.success && response.data) {
                        this.updateVehicleDisplay(response.data);
                    }
                })
                .fail((xhr, status, error) => {
                    // Handle error silently
                });
        }

        updateVehicleDisplay(vehicleData) {
            // Update vehicle image
            if (vehicleData.image) {
                $('.rv-vehicle-img').attr('src', vehicleData.image);
            }

            // Update vehicle name
            if (vehicleData.title) {
                $('.rv-vehicle-title').text(vehicleData.title);
            }

            // Update vehicle features
            if (vehicleData.specs) {
                const specsHtml = Object.entries(vehicleData.specs)
                    .map(([key, value]) => `<span class="rv-spec-badge">${value}</span>`)
                    .join('');
                $('.rv-vehicle-specs').html(specsHtml);
            }

            // Update price
            if (vehicleData.price) {
                const perDay = (window.mhmRentivaAvailability?.strings?.per_day || '/day');
                // price already formatted from backend as string or raw number? 
                // In backend ajax_get_vehicle_info, it returns "number_format" string.
                // The frontend expects it to be the formatted string.
                // However, to include currency symbol properly, we might need to check if backend sends symbol or not.
                // Looking at AvailabilityCalendar.php:849 => 'price' => number_format($price, 0, ',', '.')
                // So it is just a number string. We need to add symbol.
                const currencySymbol = window.mhmRentivaAvailability?.currencySymbol || '$';
                $('.rv-vehicle-price').text(vehicleData.price + ' ' + currencySymbol + perDay);
            }

            // Update data attributes
            $('.rv-availability-calendar').attr('data-vehicle-id', vehicleData.id);
            $('.rv-availability-calendar').attr('data-vehicle-price', vehicleData.price);

            // Update Favorite Button Status
            const $favBtn = $('.rv-favorite-btn');
            $favBtn.data('vehicle-id', vehicleData.id); // Update ID on button

            if (vehicleData.is_favorite) {
                $favBtn.addClass('active');
                $favBtn.find('.dashicons').css('color', '#e74c3c');
            } else {
                $favBtn.removeClass('active');
                $favBtn.find('.dashicons').css('color', '');
            }

            // Handle Availability Status
            this.updateAvailabilityStatus(vehicleData);
        }

        updateAvailabilityStatus(vehicleData) {
            const $container = $('.rv-availability-calendar');
            // Changed selector to look in header
            const $badgeWrapper = $('.rv-vehicle-header .rv-badge-wrapper');
            // Updated selector to find grid items directly (wrapper removed)
            const $calendarItems = $container.find('.rv-availability-grid, .rv-calendar-hint, .rv-calendar-controls, .rv-availability-legend');
            const $unavailableMessage = $('.rv-calendar-unavailable-message');

            // Update or Create Badge
            if (vehicleData.is_available === false) {
                // Show badge
                if ($badgeWrapper.length === 0) {
                    // Create badge wrapper with inline layout styles instead of absolute
                    const badgeHtml = `
                        <div class="rv-badge-wrapper" style="display: inline-flex; align-items: center; margin-right: 10px;">
                            <span class="rv-badge rv-badge--unavailable" style="background-color: #ef4444; color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                                ${this.escapeHtml(vehicleData.status_text)}
                            </span>
                        </div>
                    `;

                    // Insert before the favorite button in the header
                    const $favoriteBtn = $('.rv-favorite-btn');
                    if ($favoriteBtn.length > 0) {
                        $(badgeHtml).insertBefore($favoriteBtn);
                    } else {
                        // Fallback if no favorite button
                        $('.rv-vehicle-header').append(badgeHtml);
                    }
                } else {
                    $badgeWrapper.find('.rv-badge').text(vehicleData.status_text);
                    $badgeWrapper.show();
                }

                // Hide Calendar, Show Unavailable Message
                $calendarWrapper.hide();

                if ($unavailableMessage.length === 0) {
                    $container.append(`
                        <div class="rv-calendar-unavailable-message" style="text-align: center; padding: 40px; background: #fff; border: 1px solid #ddd; border-radius: 8px; margin-top: 20px;">
                            <div style="font-size: 48px; margin-bottom: 20px;">🚫</div>
                            <h3 style="color: #e74c3c; margin-bottom: 10px;">${window.mhmRentivaAvailability?.strings?.unavailable || 'Vehicle Unavailable'}</h3>
                            <p>${window.mhmRentivaAvailability?.strings?.outOfOrderMessage || 'This vehicle is currently out of order and cannot be booked. Please choose another vehicle.'}</p>
                            ${this.getSwitchVehicleButtonHtml()}
                        </div>
                    `);

                    // Re-bind switch button event if it's dynamic
                    // The document level event delegation should handle it
                } else {
                    $unavailableMessage.show();
                }

            } else {
                // Hide badge
                $badgeWrapper.hide();

                // Show Calendar, Hide Message
                $calendarWrapper.show();
                $unavailableMessage.hide();
            }
        }

        getSwitchVehicleButtonHtml() {
            // Retrieve vehicles list from existing button if available, or we might need it from data
            const existingBtn = $('.rv-switch-vehicle-btn').first();
            if (existingBtn.length > 0) {
                const vehiclesData = existingBtn.attr('data-vehicles'); // use attr to get string
                if (vehiclesData) {
                    return `<button class="rv-switch-vehicle-btn rv-btn rv-btn-primary" type="button" data-vehicles='${vehiclesData}' style="margin-top: 20px;">${window.mhmRentivaAvailability?.strings?.chooseAnother || 'Choose Another Vehicle'}</button>`;
                }
            }
            return '';
        }

        handleMonthNavigation(e) {
            e.preventDefault();

            if (this.isLoading) return;

            const action = $(e.currentTarget).data('action');
            const currentDate = new Date(this.currentStartMonth + '-01');

            if (action === 'prev') {
                currentDate.setMonth(currentDate.getMonth() - 1);
            } else if (action === 'next') {
                currentDate.setMonth(currentDate.getMonth() + 1);
            }

            this.currentStartMonth = this.formatMonth(currentDate);
            this.loadAvailabilityData();
        }

        handleDateClick(e) {
            e.preventDefault();

            const $day = $(e.currentTarget);
            const date = $day.data('date');

            if (!date) return;

            // Date selection logic
            if (this.selectedDates.length === 0) {
                // First date selection
                this.selectedDates = [date];
                $day.addClass('rv-selected rv-selected-start');
            } else if (this.selectedDates.length === 1) {
                // Second date selection (range)
                const startDate = this.selectedDates[0];

                if (date === startDate) {
                    // Same date selected - Treat as single day booking (Start = End)
                    this.selectedDates = [startDate, date];
                    this.updateDateRangeSelection();

                    // Auto open booking modal
                    this.openBookingModal(this.currentVehicleId, startDate, date);
                } else if (date > startDate) {
                    // Valid range
                    this.selectedDates = [startDate, date];
                    this.updateDateRangeSelection();

                    // Auto open booking modal
                    this.openBookingModal(this.currentVehicleId, startDate, date);
                } else {
                    // New start date
                    this.selectedDates = [date];
                    $('.rv-calendar-day').removeClass('rv-selected rv-selected-start rv-selected-end rv-selected-range');
                    $day.addClass('rv-selected rv-selected-start');
                }
            } else {
                // Start new selection
                this.selectedDates = [date];
                $('.rv-calendar-day').removeClass('rv-selected rv-selected-start rv-selected-end rv-selected-range');
                $day.addClass('rv-selected rv-selected-start');
            }

            this.updateSelectedDatesDisplay();
        }

        handleKeyboardNavigation(e) {
            const $day = $(e.currentTarget);
            const key = e.which;

            // Enter or Space key
            if (key === 13 || key === 32) {
                e.preventDefault();
                $day.trigger('click');
            }
        }

        handleBookingClick(e) {
            e.preventDefault();

            if (this.selectedDates.length === 0) {
                this.showNotification(window.mhmRentivaAvailability?.strings?.select_date_first || 'Please select a date first.', 'warning');
                return;
            }

            // Open booking form modal
            const startDate = this.selectedDates[0];
            const endDate = this.selectedDates[1] || startDate;
            const vehicleId = this.currentVehicleId;

            this.openBookingModal(vehicleId, startDate, endDate);
        }

        updateDateRangeSelection() {
            $('.rv-calendar-day').removeClass('rv-selected rv-selected-start rv-selected-end rv-selected-range');

            if (this.selectedDates.length === 2) {
                const startDate = this.selectedDates[0];
                const endDate = this.selectedDates[1];

                $(`.rv-calendar-day[data-date="${startDate}"]`).addClass('rv-selected rv-selected-start');
                $(`.rv-calendar-day[data-date="${endDate}"]`).addClass('rv-selected rv-selected-end');

                // Mark days in range
                const start = new Date(startDate);
                const end = new Date(endDate);

                for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                    const dateStr = this.formatDate(d);
                    if (dateStr !== startDate && dateStr !== endDate) {
                        $(`.rv-calendar-day[data-date="${dateStr}"]`).addClass('rv-selected rv-selected-range');
                    }
                }
            }
        }

        updateSelectedDatesDisplay() {
            const $selectedDates = $('.rv-selected-dates');

            if (this.selectedDates.length === 0) {
                $selectedDates.hide();
                return;
            }

            $selectedDates.show();

            const startDate = this.selectedDates[0];
            const endDate = this.selectedDates[1] || startDate;

            // Show date range
            $('.rv-start-date').text(this.formatDisplayDate(startDate));
            $('.rv-end-date').text(this.formatDisplayDate(endDate));

            // Calculate day count
            const daysCount = this.calculateDaysBetween(startDate, endDate);
            $('.rv-days-count').text(daysCount);

            // Calculate total price
            this.calculateTotalPrice(startDate, endDate);

            // Enable booking button
            $('.rv-book-now-btn').prop('disabled', false);
        }

        calculateTotalPrice(startDate, endDate) {
            let totalPrice = 0;
            const start = new Date(startDate);
            const end = new Date(endDate);

            // Get vehicle price
            const vehiclePrice = parseFloat(this.calendar.attr('data-vehicle-price')) || 0;

            for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                // Use only vehicle price
                totalPrice += vehiclePrice;
            }

            // If price is 0, get vehicle price directly
            if (totalPrice === 0) {
                const daysDiff = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
                // Get vehicle price from calendar element
                const vehiclePrice = parseFloat(this.calendar.data('vehicle-price')) || 1400; // Default price
                totalPrice = daysDiff * vehiclePrice;
            }

            $('.rv-price-total').text(this.formatPrice(totalPrice));
        }

        loadAvailabilityData() {
            if (this.isLoading || !this.currentVehicleId) return;

            // JavaScript object is now always available

            this.showLoading(true);
            this.isLoading = true;

            const data = {
                action: 'mhm_rentiva_availability_data',
                nonce: window.mhmRentivaAvailability.nonce,
                vehicle_id: this.currentVehicleId,
                start_month: this.currentStartMonth,
                months_to_show: this.monthsToShow
            };

            $.post(window.mhmRentivaAvailability.ajaxUrl, data)
                .done((response) => {
                    this.handleAvailabilityDataSuccess(response);
                    this.loadPricingData();
                })
                .fail(this.handleAvailabilityDataError.bind(this))
                .always(() => {
                    this.showLoading(false);
                    this.isLoading = false;
                });
        }

        loadPricingData() {
            if (!this.currentVehicleId) return;

            const data = {
                action: 'mhm_rentiva_availability_pricing',
                nonce: window.mhmRentivaAvailability.nonce,
                vehicle_id: this.currentVehicleId,
                start_month: this.currentStartMonth,
                months_to_show: this.monthsToShow
            };

            $.post(window.mhmRentivaAvailability.ajaxUrl, data)
                .done(this.handlePricingDataSuccess.bind(this))
                .fail(this.handlePricingDataError.bind(this));
        }

        handleAvailabilityDataSuccess(response) {
            if (response.success && response.data.availability_data) {
                const availabilityData = response.data.availability_data;
                const monthKey = Object.keys(availabilityData)[0];
                const monthData = availabilityData[monthKey];

                // Period title removed

                // Update month container
                let $monthContainer = $('.rv-month-container');

                // If container doesn't exist (e.g. was empty state), create it
                if ($monthContainer.length === 0) {
                    const $grid = $('.rv-availability-grid');
                    // Remove no data message if exists
                    $grid.find('.rv-no-data-message').remove();

                    // Create container
                    $grid.append(`<div class="rv-month-container" data-month="${monthKey}"></div>`);
                    $monthContainer = $grid.find('.rv-month-container');
                }

                $monthContainer.attr('data-month', monthKey);
                $monthContainer.html(this.renderMonthHTML(monthData, monthKey));

                // Update month title
                if (monthData.month_name && monthData.year) {
                    $('.rv-month-name').text(monthData.month_name + ' ' + monthData.year);
                }

                // Update general statistics
                this.updateGeneralStats(availabilityData);

                // this.showNotification(response.data.message, 'success');
            } else {
                this.showNotification(response.data?.message || window.mhmRentivaAvailability.messages.error, 'error');
            }
        }

        handleAvailabilityDataError(xhr, status, error) {
            console.error('Availability data error:', error);
            this.showNotification(window.mhmRentivaAvailability.messages.error, 'error');
        }

        handlePricingDataSuccess(response) {
            if (response.success && response.data.pricing_data) {
                this.updatePricingDisplay(response.data.pricing_data);
            }
        }

        handlePricingDataError(xhr, status, error) {
            console.error('Pricing data error:', error);
        }

        updateCalendarDisplay(availabilityData) {
            // Not used for single month, managed in handleAvailabilityDataSuccess
        }

        updatePricingDisplay(pricingData) {
            Object.keys(pricingData).forEach(monthKey => {
                const monthData = pricingData[monthKey];
                const $monthContainer = $(`.rv-month-container[data-month="${monthKey}"]`);

                if ($monthContainer.length === 0) return;

                Object.keys(monthData.days).forEach(date => {
                    const priceData = monthData.days[date];
                    const $day = $monthContainer.find(`.rv-calendar-day[data-date="${date}"]`);

                    if ($day.length === 0) return;

                    // Update price data
                    $day.attr('data-price', priceData.day_price);

                    if (priceData.has_discount) {
                        $day.attr('data-discount', priceData.discount_amount);
                    }

                    // Update price display
                    const $priceContainer = $day.find('.rv-day-price');
                    if ($priceContainer.length === 0) {
                        $day.append(`
                            <div class="rv-day-price">
                                <span class="rv-price-amount">${this.formatPrice(priceData.day_price)}</span>
                                ${priceData.has_discount ? `<span class="rv-discount-badge">%${Math.round((priceData.discount_amount / priceData.base_price) * 100)}</span>` : ''}
                            </div>
                        `);
                    } else {
                        $priceContainer.find('.rv-price-amount').text(this.formatPrice(priceData.day_price));

                        if (priceData.has_discount) {
                            const discountPercent = Math.round((priceData.discount_amount / priceData.base_price) * 100);
                            $priceContainer.find('.rv-discount-badge').text(`%${discountPercent}`).show();
                        } else {
                            $priceContainer.find('.rv-discount-badge').hide();
                        }
                    }
                });
            });
        }

        updateGeneralStats(availabilityData) {
            // Statistics are shown below calendar, no update needed here
        }

        renderMonthHTML(monthData, monthKey) {
            const days = monthData.days || {};

            // Find which day of the week the first day of the month is
            const firstDay = new Date(monthKey + '-01');
            const firstDayOfWeek = firstDay.getDay(); // Sunday = 0, Monday = 1
            const adjustedFirstDay = firstDayOfWeek === 0 ? 6 : firstDayOfWeek - 1; // Monday = 0, Sunday = 6

            let html = '';

            // Weekday header
            const strings = window.mhmRentivaAvailability?.available?.strings || window.mhmRentivaAvailability?.messages || {}; // Fallback logic
            // Note: PHP injects 'strings' into the localization object now

            const translatedDays = window.mhmRentivaAvailability?.strings || {};

            // Month Header (Restored)
            if (monthData.month_name && monthData.year) {
                html += `
                    <div class="rv-month-header">
                        <h4 class="rv-month-title">${monthData.month_name} ${monthData.year}</h4>
                    </div>
                `;
            }

            html += '<div class="rv-calendar-weekdays">';
            html += '<div class="rv-weekday">' + (translatedDays.monday || 'Mon') + '</div>';
            html += '<div class="rv-weekday">' + (translatedDays.tuesday || 'Tue') + '</div>';
            html += '<div class="rv-weekday">' + (translatedDays.wednesday || 'Wed') + '</div>';
            html += '<div class="rv-weekday">' + (translatedDays.thursday || 'Thu') + '</div>';
            html += '<div class="rv-weekday">' + (translatedDays.friday || 'Fri') + '</div>';
            html += '<div class="rv-weekday">' + (translatedDays.saturday || 'Sat') + '</div>';
            html += '<div class="rv-weekday">' + (translatedDays.sunday || 'Sun') + '</div>';
            html += '</div>';

            // Calendar days
            html += '<div class="rv-calendar-days">';

            // Placeholder for empty days
            for (let i = 0; i < adjustedFirstDay; i++) {
                html += '<div class="rv-calendar-day rv-day-empty"></div>';
            }

            // Show days of month
            Object.keys(days).forEach(date => {
                const dayData = days[date];
                const dayNumber = dayData.day_number;
                const status = dayData.status;
                const isWeekend = dayData.is_weekend;
                const isToday = dayData.is_today;
                const isPast = dayData.is_past;

                let dayClasses = ['rv-calendar-day', 'rv-status-' + status];
                if (isWeekend) dayClasses.push('rv-weekend');
                if (isToday) dayClasses.push('rv-today');
                if (isPast) dayClasses.push('rv-past');

                html += `
                    <div class="${dayClasses.join(' ')}" data-date="${date}" data-price="0">
                        <span class="rv-day-number">${dayNumber}</span>
                    </div>
                `;
            });

            html += '</div>';
            return html;
        }

        showLoading(show) {
            const $loading = $('.rv-availability-loading');
            const $calendar = $('.rv-availability-grid');

            if (show) {
                $loading.show();
                $calendar.css('opacity', '0.5');
            } else {
                $loading.hide();
                $calendar.css('opacity', '1');
            }
        }

        showNotification(message, type = 'info') {
            // Simple notification system
            const $notification = $(`
                <div class="rv-notification rv-notification--${type}">
                    <span class="rv-notification-message">${message}</span>
                    <button type="button" class="rv-notification-close">&times;</button>
                </div>
            `);

            $('body').append($notification);

            // Auto close after 5 seconds
            setTimeout(() => {
                $notification.fadeOut(() => $notification.remove());
            }, 5000);

            // Manual close
            $notification.find('.rv-notification-close').on('click', () => {
                $notification.fadeOut(() => $notification.remove());
            });
        }

        openBookingModal(vehicleId, startDate, endDate) {
            // Create booking form modal
            const modalHtml = `
                <div id="rv-booking-modal" class="rv-modal-overlay">
                    <div class="rv-modal-content">
                        <div class="rv-modal-header">
                            <h3>${window.mhmRentivaAvailability?.strings?.booking_form || 'Booking Form'}</h3>
                            <button class="rv-modal-close">&times;</button>
                        </div>
                        <div class="rv-modal-body">
                            <div class="rv-booking-form-loading">${window.mhmRentivaAvailability?.strings?.loading || 'Loading...'}</div>
                            <div class="rv-booking-form" data-vehicle-id="${vehicleId}" data-start-date="${startDate}" data-end-date="${endDate}"></div>
                        </div>
                    </div>
                </div>
            `;

            // Add modal to page
            $('body').append(modalHtml);

            // Show modal
            $('#rv-booking-modal').css('display', 'flex').hide().fadeIn();

            // Load booking form via AJAX
            this.loadBookingForm(vehicleId, startDate, endDate);

            // Close events
            $('#rv-booking-modal .rv-modal-close, #rv-booking-modal .rv-modal-overlay').on('click', (e) => {
                if (e.target !== e.currentTarget && !$(e.target).hasClass('rv-modal-close')) return;

                $('#rv-booking-modal').fadeOut(() => {
                    $('#rv-booking-modal').remove();
                });
            });
        }

        loadBookingForm(vehicleId, startDate, endDate) {
            // Load booking form via AJAX
            $.ajax({
                url: window.mhmRentivaAvailability.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhm_rentiva_load_booking_form',
                    vehicle_id: vehicleId,
                    start_date: startDate,
                    end_date: endDate,
                    nonce: window.mhmRentivaAvailability.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $('.rv-booking-form').html(response.data.form_html);
                        $('.rv-booking-form-loading').hide();

                        // Force Layout Fixes via JS (Bypass CSS Conflicts)
                        $('#rv-booking-modal .rv-selected-vehicle').css({
                            'display': 'flex',
                            'flex-direction': 'column',
                            'height': 'auto',
                            'align-items': 'center',
                            'padding': '0',
                            'border': 'none',
                            'box-shadow': 'none'
                        });

                        $('#rv-booking-modal .rv-vehicle-info').css({
                            'display': 'flex',
                            'flex-direction': 'column',
                            'width': '100%',
                            'align-items': 'center'
                        });

                        $('#rv-booking-modal .rv-vehicle-image-wrapper').css({
                            'width': '100%',
                            'height': 'auto',
                            'max-height': '220px',
                            'margin': '0 0 15px 0',
                            'flex': 'none'
                        });

                        $('#rv-booking-modal .rv-vehicle-image').css({
                            'width': '100%',
                            'height': '100%',
                            'object-fit': 'contain'
                        });

                        $('#rv-booking-modal .rv-vehicle-details').css({
                            'width': '100%',
                            'padding': '0',
                            'margin': '0',
                            'flex': 'none'
                        });

                        $('#rv-booking-modal .rv-vehicle-features').css({
                            'display': 'flex',
                            'flex-direction': 'row',
                            'flex-wrap': 'wrap',
                            'gap': '8px',
                            'width': '100%',
                            'margin-bottom': '15px',
                            'justify-content': 'center'
                        });

                        $('#rv-booking-modal .rv-field-group').css({
                            'grid-template-columns': '1fr'
                        });

                        // Hide potential ghost elements
                        $('#ui-datepicker-div').hide();


                        // Bind event handlers for form in modal
                        this.bindModalFormEvents();

                        // Initialize BookingForm JavaScript
                        if (typeof window.BookingForm !== 'undefined') {
                            new window.BookingForm();
                        } else {
                            // If BookingForm is not available, it should already be loaded on page
                        }
                    } else {
                        $('.rv-booking-form').html('<p>Booking form could not be loaded: ' + (response.data?.message || window.mhmRentivaAvailability?.strings?.unknown_error || 'Unknown error') + '</p>');
                        $('.rv-booking-form-loading').hide();
                    }
                },
                error: (xhr, status, error) => {
                    $('.rv-booking-form').html('<p>Booking form could not be loaded. Error: ' + error + '</p>');
                    $('.rv-booking-form-loading').hide();
                }
            });
        }

        // Update vehicle name
        updateVehicleName(vehicleId) {
            const $dropdown = $('.rv-vehicle-dropdown, #rv-availability-vehicle-select-fallback');
            const selectedOption = $dropdown.find('option:selected');
            const vehicleName = selectedOption.text();

            // Update vehicle name in card
            $('.rv-vehicle-title').text(vehicleName);

            // Update vehicle price
            const vehiclePrice = selectedOption.data('price') || 0;
            this.calendar.attr('data-vehicle-price', vehiclePrice);

            // Update prices in calendar days
            this.updateCalendarPrices(vehiclePrice);
        }

        // Update prices in calendar days
        updateCalendarPrices(vehiclePrice) {
            $('.rv-calendar-day:not(.rv-day-empty)').each(function () {
                const $day = $(this);

                // Use only vehicle price
                const dayPrice = vehiclePrice;

                // Update price
                $day.attr('data-price', dayPrice);

                // Update visual price
                const currencySymbol = window.mhmRentivaAvailability?.currency_symbol || window.mhmRentivaConfig?.currency || '$';
                const priceText = dayPrice > 0 ? `${currencySymbol}${dayPrice.toLocaleString('en-US')}` : `${currencySymbol}0`;
                $day.find('.rv-day-price').text(priceText);
            });

            // If selected dates exist, recalculate total price
            if (this.selectedDates.length > 0) {
                const startDate = this.selectedDates[0];
                const endDate = this.selectedDates[this.selectedDates.length - 1];
                this.calculateTotalPrice(startDate, endDate);
            }
        }

        // Bind event handlers for form in modal
        bindModalFormEvents() {
            // Automatically update return time when pickup time changes
            $(document).on('change', '#rv-booking-modal #pickup_time', (e) => {
                const pickupTime = $(e.target).val();
                if (pickupTime) {
                    const $dropoffSelect = $('#rv-booking-modal #dropoff_time');
                    $dropoffSelect.prop('disabled', false);
                    $dropoffSelect.html(`<option value="${pickupTime}" selected>${pickupTime}</option>`);
                }
            });
        }

        // Helper functions
        getCurrentMonth() {
            const now = new Date();
            return this.formatMonth(now);
        }

        formatMonth(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            return `${year}-${month}`;
        }

        formatDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        formatDisplayDate(dateStr) {
            const date = new Date(dateStr);
            const locale = this.convertLocaleFormat(window.mhmRentivaAvailability?.locale || 'en-US');
            const dateFormat = window.mhmRentivaAvailability?.date_format || 'd.m.Y';

            // Convert WordPress date format to JavaScript format
            const jsFormat = this.convertWordPressDateFormat(dateFormat);

            return date.toLocaleDateString(locale, {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
        }

        formatPrice(price) {
            const locale = this.convertLocaleFormat(window.mhmRentivaAvailability?.locale || 'en-US');
            const currencySymbol = window.mhmRentivaAvailability?.currencySymbol || '$';

            // Format number
            const formattedNumber = new Intl.NumberFormat(locale, {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(price);

            // Append/Prepend symbol manually to avoid force-changing currency code
            return `${formattedNumber} ${currencySymbol}`;
        }

        convertLocaleFormat(locale) {
            // Convert WordPress locale format (en_US) to JavaScript format (en-US)
            if (locale && locale.includes('_')) {
                return locale.replace('_', '-');
            }
            return locale || 'en-US';
        }

        convertWordPressDateFormat(wpFormat) {
            // Convert WordPress date format to JavaScript Intl.DateTimeFormat options
            const formatMap = {
                'd': '2-digit',    // Day with leading zeros
                'j': 'numeric',    // Day without leading zeros
                'm': '2-digit',    // Month with leading zeros
                'n': 'numeric',    // Month without leading zeros
                'Y': 'numeric',    // Full year
                'y': '2-digit'     // Two digit year
            };

            // Simple format conversion (moment.js can be used for more advanced version)
            return {
                day: wpFormat.includes('d') ? '2-digit' : 'numeric',
                month: wpFormat.includes('m') ? '2-digit' : 'numeric',
                year: wpFormat.includes('Y') ? 'numeric' : '2-digit'
            };
        }

        calculateDaysBetween(startDate, endDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            const diffTime = Math.abs(end - start);
            return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
        }


        handleFavoriteClick(e) {
            e.preventDefault();
            e.stopPropagation();

            const $btn = $(e.currentTarget);
            const vehicleId = $btn.data('vehicle-id');

            // Check if user is logged in
            if (!window.mhmRentivaAvailability.isUserLoggedIn) {
                this.showNotification(window.mhmRentivaAvailability?.strings?.error || 'Please login to add favorites.', 'error');
                return;
            }

            // Determine active action
            const isFavorite = $btn.hasClass('active');
            const action = isFavorite ? 'mhm_rentiva_remove_favorite' : 'mhm_rentiva_add_favorite';

            // Add loading state
            $btn.addClass('loading');

            const data = {
                action: action,
                vehicle_id: vehicleId,
                nonce: window.mhmRentivaAvailability.accountNonce
            };

            $.post(window.mhmRentivaAvailability.ajaxUrl, data)
                .done((response) => {
                    if (response.success) {
                        if (action === 'mhm_rentiva_add_favorite') {
                            $btn.addClass('active');
                            $btn.find('.dashicons').css('color', '#e74c3c'); // Red heart
                            this.showNotification(response.data.message || 'Added to favorites', 'success');
                        } else {
                            $btn.removeClass('active');
                            $btn.find('.dashicons').css('color', ''); // Reset color
                            this.showNotification(response.data.message || 'Removed from favorites', 'success');
                        }
                    } else {
                        this.showNotification(response.data.message || window.mhmRentivaAvailability.messages.error, 'error');
                    }
                })
                .fail((xhr, status, error) => {
                    this.showNotification(window.mhmRentivaAvailability.messages.error, 'error');
                })
                .always(() => {
                    $btn.removeClass('loading');
                });
        }

    }

    // Initialize when page loads
    $(document).ready(function () {
        new AvailabilityCalendar();
    });

    // For global access
    window.AvailabilityCalendar = AvailabilityCalendar;

})(jQuery);
