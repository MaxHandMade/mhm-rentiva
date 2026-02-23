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
        console.warn('mhmRentivaAvailability object not defined in template, using default values');
    }

    class AvailabilityCalendar {
        constructor() {
            this.calendar = null;
            this.selectedDates = [];
            this.currentVehicleId = 0;
            this.currentStartMonth = '';
            this.monthsToShow = 1;
            this.isLoading = false;
            this.parseDate = (dateStr) => {
                if (!dateStr) return new Date();
                const parts = dateStr.split('-');
                if (parts.length === 3) {
                    return new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
                } else if (parts.length === 2) {
                    return new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, 1);
                }
                return new Date(dateStr);
            };

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
            $(document).on('change', '.rv-vehicle-dropdown', (e) => this.handleVehicleChange(e));

            // Change Vehicle button
            $(document).on('click', '.rv-switch-vehicle-btn', (e) => this.handleVehicleSwitch(e));

            // Fallback vehicle selector
            $(document).on('change', '#rv-availability-vehicle-select-fallback', (e) => this.handleVehicleChange(e));

            // Month navigation
            $(document).on('click', '.rv-control-btn', (e) => this.handleMonthNavigation(e));

            // Date selection
            $(document).on('click', '.rv-calendar-day:not(.rv-day-empty):not(.rv-past):not(.rv-status-booked):not(.rv-status-maintenance)', (e) => this.handleDateClick(e));

            // Booking button
            $(document).on('click', '.rv-book-now-btn', (e) => this.handleBookingClick(e));

            // Keyboard navigation
            $(document).on('keydown', '.rv-calendar-day', (e) => this.handleKeyboardNavigation(e));

            // Favorite button
            $(document).on('click', '.rv-vcal-favorite-btn', (e) => this.handleFavoriteClick(e));
        }

        initializeCalendar() {
            this.calendar = $('.rv-availability-calendar');
            if (this.calendar.length === 0) return;

            this.currentVehicleId = parseInt(this.calendar.data('vehicle-id')) || 0;
            this.currentStartMonth = this.calendar.data('start-month') || this.getCurrentMonth();
            this.monthsToShow = parseInt(this.calendar.data('months-to-show')) || 3;

            this.updateSelectedDatesDisplay();
            this.loadAvailabilityData();
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
            const i18n = window.mhmRentivaAvailability?.strings || {};
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
                                    <div style="color: #666; font-size: 14px;">${this.formatMoney(vehicle.price || 0)} ${perDay}</div>
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
                $('.rv-vehicle-image').attr('src', vehicleData.image);
                $('.rv-vehicle-image').attr('alt', vehicleData.title || '');
            }

            // Update vehicle name
            if (vehicleData.title) {
                $('.rv-vehicle-title').text(vehicleData.title);
            }

            // Update excerpt
            if (vehicleData.excerpt) {
                $('.rv-vehicle-excerpt').text(vehicleData.excerpt);
            }

            // Update Rating
            if (vehicleData.rating) {
                const avg = parseFloat(vehicleData.rating.average || 0);
                const count = parseInt(vehicleData.rating.count || 0);

                if (count > 0) {
                    $('.rv-stars svg').each(function (index) {
                        const starPos = index + 1;
                        if (starPos <= Math.floor(avg)) {
                            $(this).attr('fill', '#fbbf24').attr('stroke', '#d97706');
                        } else if (starPos === Math.ceil(avg) && (avg % 1 >= 0.5)) {
                            // Simplified half star handling for AJAX update
                            $(this).attr('fill', '#fbbf24').attr('stroke', '#d97706');
                        } else {
                            $(this).attr('fill', 'none').attr('stroke', '#cbd5e1');
                        }
                    });
                    $('.rv-rating-count').text('(' + count + ')');
                    $('.rv-vehicle-rating-block').show();
                } else {
                    $('.rv-vehicle-rating-block').hide();
                }
            }

            // Update vehicle features (Unified with SVGs)
            if (vehicleData.features && Array.isArray(vehicleData.features)) {
                const featuresHtml = vehicleData.features
                    .map(feature => `
                        <div class="rv-feature-item">
                            ${feature.svg}
                            <span class="rv-feature-text">${feature.text}</span>
                        </div>
                    `)
                    .join('');
                $('.rv-vehicle-features').html(featuresHtml);
            }

            // Update price
            if (vehicleData.price !== undefined || vehicleData.formatted_price) {
                const perDay = (window.mhmRentivaAvailability?.strings?.per_day || '/day');
                const formatted = vehicleData.formatted_price || this.formatMoney(vehicleData.price || 0, vehicleData.currency_symbol);
                $('.rv-vehicle-price').html(`${formatted} ${perDay}`);
            }

            // Update data attributes
            $('.rv-availability-calendar').attr('data-vehicle-id', vehicleData.id);

            // Update Favorite Button Status
            const $favBtn = $('.rv-vcal-favorite-btn');
            $favBtn.data('vehicle-id', vehicleData.id); // Update ID on button

            if (vehicleData.is_favorite) {
                $favBtn.addClass('favorited is-favorited');
                $favBtn.find('.rv-heart-icon').addClass('favorited').attr('fill', 'currentColor');
                $favBtn.attr('aria-pressed', 'true');
            } else {
                $favBtn.removeClass('favorited is-favorited');
                $favBtn.find('.rv-heart-icon').removeClass('favorited').attr('fill', 'none');
                $favBtn.attr('aria-pressed', 'false');
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
                $calendarItems.hide();

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
                $calendarItems.show();
                $unavailableMessage.hide();
                $badgeWrapper.hide();
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

            // FIX: Robust date arithmetic (Avoid string parsing bugs)
            const dateParts = this.currentStartMonth.split('-');
            let year = parseInt(dateParts[0]);
            let month = parseInt(dateParts[1]);

            if (action === 'prev') {
                month--;
                if (month < 1) {
                    month = 12;
                    year--;
                }
            } else if (action === 'next') {
                month++;
                if (month > 12) {
                    month = 1;
                    year++;
                }
            }

            this.currentStartMonth = `${year}-${String(month).padStart(2, '0')}`;
            this.loadAvailabilityData();
        }

        handleDateClick(e) {
            e.preventDefault();

            const $day = $(e.currentTarget);
            const date = $day.data('date');

            if (!date) return;

            // Block clicks on busy or past dates
            if ($day.hasClass('rv-status-booked') ||
                $day.hasClass('rv-status-maintenance') ||
                $day.hasClass('rv-status-partial') ||
                $day.hasClass('rv-status-unavailable') ||
                $day.hasClass('rv-past')) {
                return;
            }

            // Date selection logic
            if (this.selectedDates.length === 0) {
                // First date selection
                this.selectedDates = [date];
                $day.addClass('rv-selected rv-selected-start');
            } else if (this.selectedDates.length === 1) {
                // Second date selection (range)
                const startDate = this.selectedDates[0];

                if (date === startDate || date > startDate) {
                    // Selection complete (Single day or Range)
                    this.selectedDates = [startDate, date];
                    this.updateDateRangeSelection();

                    // Redirect to booking form
                    const baseUrl = window.mhmRentivaAvailability.bookingPageUrl;
                    const redirectUrl = `${baseUrl}${baseUrl.includes('?') ? '&' : '?'}vehicle_id=${this.currentVehicleId}&start_date=${startDate}&end_date=${date}`;
                    window.location.href = redirectUrl;
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
                MHMRentivaToast.show(window.mhmRentivaAvailability?.strings?.select_date_first || 'Please select a date first.', { type: 'warning' });
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
                const start = this.parseDate(startDate);
                const end = this.parseDate(endDate);

                for (let d = new Date(start.getTime()); d <= end; d.setDate(d.getDate() + 1)) {
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
            const start = this.parseDate(startDate);
            const end = this.parseDate(endDate);

            // Get vehicle price
            const vehiclePrice = parseFloat(this.calendar.attr('data-vehicle-price')) || 0;

            for (let d = new Date(start.getTime()); d <= end; d.setDate(d.getDate() + 1)) {
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

            $('.rv-price-total').text(this.formatMoney(totalPrice));
        }

        loadAvailabilityData() {
            if (this.isLoading || !this.currentVehicleId) return;

            this.showLoading(true);
            this.isLoading = true;

            const data = {
                action: 'mhm_rentiva_availability_unified',
                nonce: window.mhmRentivaAvailability.nonce,
                vehicle_id: this.currentVehicleId,
                start_month: this.currentStartMonth,
                months_to_show: this.monthsToShow
            };

            $.post(window.mhmRentivaAvailability.ajaxUrl, data)
                .done((response) => {
                    if (response.success) {
                        this.handleAvailabilityDataSuccess(response);
                        if (response.data.pricing_data) {
                            this.updatePricingDisplay(response.data.pricing_data);
                        }
                        // OPTIMIZATION: Check for license limits before substantial work
                        this.limitReached = response.data.limit_reached || false;
                    } else {
                        MHMRentivaToast.show(response.data?.message || window.mhmRentivaAvailability.strings.error, { type: 'error' });
                    }
                })
                .fail((response) => {
                    console.error('AJAX Error:', response);
                    MHMRentivaToast.show(window.mhmRentivaAvailability.strings.error, { type: 'error' });
                })
                .always(() => {
                    this.showLoading(false);
                    this.isLoading = false;
                });
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
                // Clear existing content to prevent duplication
                $monthContainer.empty().append(this.renderMonthHTML(monthData, monthKey));

                // Update month title
                if (monthData.month_name && monthData.year) {
                    $('.rv-month-name').text(monthData.month_name + ' ' + monthData.year);
                }

                // Update general statistics
                this.updateGeneralStats(availabilityData);

                // this.showNotification(response.data.message, 'success');
            } else {
                MHMRentivaToast.show(response.data?.message || window.mhmRentivaAvailability.messages.error, { type: 'error' });
            }
        }

        handleAvailabilityDataError(xhr, status, error) {
            console.error('Availability data error:', error);
            MHMRentivaToast.show(window.mhmRentivaAvailability.messages.error, { type: 'error' });
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
                                <span class="rv-price-amount">${this.formatMoney(priceData.day_price)}</span>
                                ${priceData.has_discount ? `<span class="rv-discount-badge">%${Math.round((priceData.discount_amount / priceData.base_price) * 100)}</span>` : ''}
                            </div>
                        `);
                    } else {
                        $priceContainer.find('.rv-price-amount').text(this.formatMoney(priceData.day_price));

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
            const monthParts = monthKey.split('-');
            const firstDay = new Date(parseInt(monthParts[0]), parseInt(monthParts[1]) - 1, 1);
            const firstDayOfWeek = firstDay.getDay(); // Sunday = 0, Monday = 1
            const adjustedFirstDay = firstDayOfWeek === 0 ? 6 : firstDayOfWeek - 1; // Monday = 0, Sunday = 6

            let html = '';

            // Weekday header
            const i18n = window.mhmRentivaAvailability?.strings || {};
            const translatedDays = i18n;

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

        // Legacy showNotification method removed in favor of MHMRentivaToast


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
            const date = this.parseDate(dateStr);
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
            return new Intl.NumberFormat(locale, {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(Number(price || 0));
        }

        formatMoney(price, symbolOverride = null) {
            const amount = this.formatPrice(price);
            const symbol = symbolOverride || window.mhmRentivaAvailability?.currencySymbol || '$';
            const position = window.mhmRentivaAvailability?.currencyPosition || 'right_space';

            switch (position) {
                case 'left':
                    return `${symbol}${amount}`;
                case 'left_space':
                    return `${symbol} ${amount}`;
                case 'right':
                    return `${amount}${symbol}`;
                case 'right_space':
                default:
                    return `${amount} ${symbol}`;
            }
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
            const start = this.parseDate(startDate);
            const end = this.parseDate(endDate);
            const diffTime = Math.abs(end - start);
            return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
        }


        handleFavoriteClick(e) {
            e.preventDefault();
            e.stopPropagation();

            const $btn = $(e.currentTarget);
            const vehicleId = $btn.data('vehicle-id');

            // Login check
            if (!window.mhmRentivaAvailability.isUserLoggedIn) {
                const loginMsg = window.mhmRentivaAvailability?.strings?.login_required || 'You must be logged in to add to favorites';
                MHMRentivaToast.show(loginMsg, { type: 'warning' });
                return;
            }

            // Determine active action - Unified action name
            const isFavorite = $btn.hasClass('favorited') || $btn.hasClass('is-favorited');

            // Add loading state
            $btn.prop('disabled', true).addClass('loading');
            const $icon = $btn.find('.rv-heart-icon');

            const data = {
                action: 'mhm_rentiva_toggle_favorite',
                vehicle_id: vehicleId,
                nonce: window.mhmRentivaAvailability.favoriteNonce || window.mhmRentivaAvailability.nonce
            };

            $.post(window.mhmRentivaAvailability.ajaxUrl, data)
                .done((response) => {
                    if (response.success) {
                        const isAdded = response.data.action === 'added';
                        const successMsg = response.data.message || (isAdded ? (window.mhmRentivaAvailability?.strings?.added_to_favorites || 'Added to favorites') : (window.mhmRentivaAvailability?.strings?.removed_from_favorites || 'Removed from favorites'));

                        // Toggle classes for UI feedback
                        $btn.toggleClass('favorited is-favorited', isAdded);
                        $icon.toggleClass('favorited', isAdded);

                        // SVG attribute update
                        if (isAdded) {
                            $icon.attr('fill', 'currentColor');
                            $btn.attr('aria-pressed', 'true');
                        } else {
                            $icon.attr('fill', 'none');
                            $btn.attr('aria-pressed', 'false');
                        }

                        MHMRentivaToast.show(successMsg, { type: 'success' });
                    } else {
                        const errorMsg = response.data.message || window.mhmRentivaAvailability?.strings?.error || 'An error occurred';
                        MHMRentivaToast.show(errorMsg, { type: 'error' });
                    }
                })
                .fail((xhr, status, error) => {
                    console.error('Favorite Toggle Error:', error, xhr.responseText);
                    MHMRentivaToast.show(window.mhmRentivaAvailability.strings?.error || 'Security or connection error', { type: 'error' });
                })
                .always(() => {
                    $btn.prop('disabled', false).removeClass('loading');
                });
        }

        /**
         * Enhanced notification system for Availability Calendar
         * Based on VehiclesList notification design
         */
        // Duplicate showNotification implementation removed

    }

    /**
     * Global-safe notification handler
     */
    function showGlobalNotification(message, type = 'info') {
        MHMRentivaToast.show(message, { type: type });
    }

    // Initialize when page loads
    $(document).ready(function () {
        const vCal = new AvailabilityCalendar();

        // Expose to global for coordination
        window.mhmRentivaAvailability = window.mhmRentivaAvailability || {};
        window.mhmRentivaAvailability.showNotification = (msg, type) => MHMRentivaToast.show(msg, { type: type });
    });

    // For global access
    window.AvailabilityCalendar = AvailabilityCalendar;

})(jQuery);
