/**
 * Vehicle Comparison JavaScript
 * 
 * Araç karşılaştırma tablosu için interaktif özellikler
 */

(function ($) {
    'use strict';

    class VehicleComparison {
        constructor() {
            this.container = $('.rv-vehicle-comparison');
            this.maxVehicles = parseInt(this.container.data('max-vehicles')) || 4;
            this.currentVehicles = [];
            this.features = window.mhmRentivaVehicleComparison?.features;

            // Initialize configuration if not exists
            if (!window.mhmRentivaVehicleComparison) {
                this.initializeConfiguration();
            }

            if ((!this.features || Object.keys(this.features).length === 0) && this.container.length > 0) {
                const dataFeatures = this.container.data('features');
                if (dataFeatures) {
                    try {
                        this.features = typeof dataFeatures === 'string' ? JSON.parse(dataFeatures) : dataFeatures;
                        if (!window.mhmRentivaVehicleComparison) {
                            window.mhmRentivaVehicleComparison = {};
                        }
                        window.mhmRentivaVehicleComparison.features = this.features;
                    } catch (error) {
                        this.features = {};
                    }
                }
            }

            if (this.container.length === 0) return;

            this.init();
        }

        init() {
            this.bindEvents();
            this.initializeCurrentVehicles();
            this.updateComparisonState(); // Set initial state
            this.adjustTableWidth(); // Adjust table width on page load
        }

        initializeConfiguration() {
            // Configuration will be set by PHP localization
            window.mhmRentivaVehicleComparison = {
                ajax_url: ajaxurl || window.location.origin + '/wp-admin/admin-ajax.php',
                nonce: '',
                strings: {
                    loading: 'Loading...',
                    error: 'An error occurred.',
                    vehicleAdded: 'Vehicle added to comparison',
                    vehicleRemoved: 'Vehicle removed from comparison',
                    maxVehiclesReached: 'Maximum number of vehicles reached',
                    noVehiclesToCompare: 'No vehicles found to compare',
                    addVehicle: 'Add Vehicle',
                    removeVehicle: 'Remove',
                    bookNow: 'Make Reservation',
                    one_vehicle_compared: '1 vehicle being compared',
                    multiple_vehicles_compared: '%d vehicles being compared'
                },
                features: {},
                availableVehicles: []
            };
        }

        bindEvents() {
            // Add vehicle button
            this.container.on('click', '.rv-add-vehicle-btn', (e) => {
                e.preventDefault();
                this.addVehicle();
            });

            // Remove vehicle button
            this.container.on('click', '.rv-remove-vehicle, .rv-remove-vehicle-btn', (e) => {
                e.preventDefault();
                const vehicleId = $(e.target).closest('.rv-remove-vehicle, .rv-remove-vehicle-btn').data('vehicle-id');
                this.removeVehicle(vehicleId);
            });

            // Vehicle select change
            this.container.on('change', '#rv-add-vehicle-select', (e) => {
                const selectedVehicleId = $(e.target).val();
                if (selectedVehicleId) {
                    this.addVehicle(selectedVehicleId);
                }
            });

            // Keyboard navigation
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.hideMessages();
                }
            });

            // Window resize event
            $(window).on('resize', () => {
                this.adjustTableWidth();
            });
        }

        initializeCurrentVehicles() {
            // Detect existing vehicles on page load
            this.currentVehicles = [];

            // Find existing vehicles from DOM
            this.container.find('[data-vehicle-id]').each((index, element) => {
                const vehicleId = $(element).data('vehicle-id');
                if (vehicleId && !this.currentVehicles.includes(vehicleId)) {
                    this.currentVehicles.push(vehicleId);
                }
            });

        }

        addVehicle(vehicleId = null) {
            if (!vehicleId) {
                vehicleId = this.container.find('#rv-add-vehicle-select').val();
            }

            if (!vehicleId) {
                this.showError(window.mhmVehicleComparison?.strings?.please_select_vehicle || 'Please select a vehicle');
                return;
            }

            vehicleId = parseInt(vehicleId);

            // Check maximum vehicle count
            if (this.currentVehicles.length >= this.maxVehicles) {
                this.showError(window.mhmVehicleComparison?.strings?.max_vehicles_reached || 'Maximum number of vehicles reached');
                return;
            }

            // Check if vehicle is already added
            const vehicleIds = this.currentVehicles.map(v => v.id || v);

            if (vehicleIds.includes(vehicleId)) {
                this.showError(window.mhmVehicleComparison?.strings?.vehicle_already_added || 'This vehicle is already in comparison');
                return;
            }

            this.showLoading(true);
            this.hideMessages();

            $.ajax({
                url: window.mhmRentivaVehicleComparison?.ajax_url || ajaxurl,
                type: 'POST',
                data: {
                    action: 'mhm_rentiva_add_vehicle_to_comparison',
                    nonce: window.mhmRentivaVehicleComparison?.nonce || '',
                    vehicle_id: vehicleId,
                    current_vehicles: this.currentVehicles.map(v => v.id || v),
                    max_vehicles: this.maxVehicles
                },
                success: (response) => {
                    this.showLoading(false);

                    if (response.success) {
                        this.currentVehicles.push(response.data.vehicle);
                        this.updateComparisonTable(response.data.vehicle);
                        this.updateComparisonState(); // Update state
                        this.showSuccess(response.data.message);

                        // Select'i temizle
                        this.container.find('#rv-add-vehicle-select').val('');

                        // Update dropdown - hide added vehicles
                        this.updateAddVehicleForm();
                    } else {
                        this.showError(response.data?.message || (window.mhmVehicleComparison?.strings?.vehicle_add_error || 'Vehicle could not be added'));
                    }
                },
                error: () => {
                    this.showLoading(false);
                    this.showError(window.mhmVehicleComparison?.strings?.vehicle_add_failed || 'An error occurred while adding the vehicle');
                }
            });
        }

        removeVehicle(vehicleId) {
            if (!vehicleId) return;

            vehicleId = parseInt(vehicleId);

            this.showLoading(true);
            this.hideMessages();

            $.ajax({
                url: window.mhmRentivaVehicleComparison?.ajax_url || ajaxurl,
                type: 'POST',
                data: {
                    action: 'mhm_rentiva_remove_vehicle_from_comparison',
                    nonce: window.mhmRentivaVehicleComparison?.nonce || '',
                    vehicle_id: vehicleId
                },
                success: (response) => {
                    this.showLoading(false);

                    if (response.success) {
                        // Remove from DOM (currentVehicles array is also updated)
                        this.removeVehicleFromDOM(vehicleId);

                        // Update add vehicle form
                        this.updateAddVehicleForm();

                        // Update state
                        this.updateComparisonState();

                        this.showSuccess(response.data.message);

                        // If no vehicles left, reload page
                        if (this.currentVehicles.length === 0) {
                            setTimeout(() => {
                                location.reload();
                            }, 2000);
                        }
                    } else {
                        this.showError(response.data?.message || (window.mhmVehicleComparison?.strings?.vehicle_remove_failed || 'Vehicle could not be removed'));
                    }
                },
                error: () => {
                    this.showLoading(false);
                    this.showError(window.mhmVehicleComparison?.strings?.vehicle_remove_error || 'An error occurred while removing the vehicle');
                }
            });
        }

        updateComparisonTable(vehicleData) {
            const layout = this.container.hasClass('rv-layout-table') ? 'table' : 'cards';
            if (layout === 'table') {
                this.addVehicleToTable(vehicleData);
            } else {
                this.addVehicleToCards(vehicleData);
            }
        }

        addVehicleToTable(vehicleData) {
            // Recreate table each time
            this.createComparisonTable();

            // Adjust table width dynamically
            this.adjustTableWidth();

            // Update comparison count
            this.updateComparisonCount();
        }

        createComparisonTable() {

            // Hide "No Vehicles" message
            this.container.find('.rv-no-vehicles').hide();

            // Show add vehicle section (if hidden)
            this.container.find('.rv-add-vehicle-section').show();

            // Create only table HTML (preserve add vehicle section)
            const vehicleCount = this.currentVehicles.length;
            const countText = vehicleCount === 1
                ? '1 vehicle being compared'
                : `${vehicleCount} vehicles being compared`;

            const tableHtml = `
                <!-- Comparison Header -->
                <div class="rv-comparison-header">
                    <h3 class="rv-comparison-title">Vehicle Comparison</h3>
                    <div class="rv-comparison-count">${countText}</div>
                </div>

                <!-- Comparison Table -->
                <div class="rv-comparison-container">
                    <div class="rv-comparison-table-wrapper">
                        <table class="rv-comparison-table">
                            <thead>
                                <tr>
                                    <th class="rv-feature-column">Feature</th>
                                    ${this.createVehicleHeaders()}
                                </tr>
                            </thead>
                            <tbody>
                                ${this.createFeatureRows()}
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Messages -->
                <div class="rv-messages">
                    <div class="rv-success-message" style="display: none;"></div>
                    <div class="rv-error-message" style="display: none;"></div>
                </div>
            `;

            // Remove old table
            this.container.find('.rv-comparison-header').remove();
            this.container.find('.rv-comparison-container').remove();
            this.container.find('.rv-messages').remove();

            // Add new table (after add vehicle section)
            this.container.find('.rv-add-vehicle-section').after(tableHtml);

            // Restart add vehicle form
            this.initializeAddVehicleForm();
        }

        initializeAddVehicleForm() {
            // Load vehicle list
            // this.loadAvailableVehicles(); // Kaldırıldı

            // this.updateAddVehicleForm(); // Kaldırıldı

            // Add vehicle butonuna event listener ekle
            this.container.find('.rv-add-vehicle-btn').off('click').on('click', () => {
                const vehicleId = this.container.find('#rv-add-vehicle-select').val();
                if (vehicleId) {
                    this.addVehicle(parseInt(vehicleId));
                }
            });
        }


        updateAddVehicleForm() {
            const $select = this.container.find('#rv-add-vehicle-select');
            const $options = $select.find('option:not(:first)');


            // First activate and show all options
            $options.each((index, option) => {
                $(option).prop('disabled', false).show();
            });

            // Disable existing vehicles
            if (this.currentVehicles && this.currentVehicles.length > 0) {
                $options.each((index, option) => {
                    const vehicleId = parseInt($(option).val());
                    if (this.currentVehicles.includes(vehicleId)) {
                        $(option).prop('disabled', true);
                    }
                });
            }

            // Select'i temizle
            $select.val('');
        }

        createVehicleHeaders() {
            let headersHtml = '';

            this.currentVehicles.forEach(vehicle => {
                headersHtml += `
                    <th class="rv-vehicle-column">
                        <div class="rv-vehicle-header">
                            ${vehicle.image_url ? `<img src="${vehicle.image_url}" alt="${vehicle.title}" class="rv-vehicle-image">` : ''}
                            <h4>${vehicle.title}</h4>
                            <a href="${vehicle.permalink}" class="rv-book-now-btn">
                                ${window.mhmRentivaVehicleComparison?.strings?.bookNow || 'Make Reservation'}
                            </a>
                            <button type="button" class="rv-remove-vehicle" data-vehicle-id="${vehicle.id}">
                                <span class="dashicons dashicons-dismiss"></span>
                            </button>
                        </div>
                    </th>
                `;
            });

            return headersHtml;
        }

        createFeatureRows() {
            const features = this.getComparisonFeatures();
            let rowsHtml = '';


            Object.keys(features).forEach(featureKey => {
                // Skip Features and Equipment rows - they are already shown in separate rows
                if (featureKey === 'features' || featureKey === 'equipment') {
                    return;
                }

                rowsHtml += `
                    <tr class="rv-feature-row" data-feature="${featureKey}">
                        <td class="rv-feature-name">${features[featureKey]}</td>
                `;

                // Add data cell for each vehicle
                this.currentVehicles.forEach(vehicle => {
                    const value = vehicle.features && vehicle.features[featureKey] ? vehicle.features[featureKey] : '-';
                    rowsHtml += `<td class="rv-feature-value">${this.formatFeatureValue(featureKey, value, vehicle)}</td>`;
                });

                rowsHtml += `</tr>`;
            });

            return rowsHtml;
        }

        getComparisonFeatures() {
            if (this.features && Object.keys(this.features).length > 0) {
                return this.features;
            }

            return {};
        }

        formatFeatureValue(featureKey, value, vehicle) {
            // Special formatting for price
            if (featureKey === 'price_per_day' && value > 0) {
                const currency = vehicle.features.currency_symbol || '$';
                return `<span class="rv-price">${parseFloat(value).toLocaleString()} ${currency}</span>`;
            }

            // Handle Features and Equipment arrays specially
            if (featureKey === 'features' && Array.isArray(value)) {
                return this.formatFeatureList(value);
            }

            if (featureKey === 'equipment' && Array.isArray(value)) {
                return this.formatEquipmentList(value);
            }

            // Truncate long texts
            if (typeof value === 'string' && value.length > 50) {
                const shortValue = value.substring(0, 47) + '...';
                return `<span class="rv-feature-text" title="${value}">${shortValue}</span>`;
            }

            // Return value as is
            return `<span class="rv-feature-text">${value}</span>`;
        }

        formatFeatureList(features) {
            if (!Array.isArray(features) || features.length === 0) {
                return '<span class="rv-feature-text">-</span>';
            }

            const featureLabels = {
                'air_conditioning': 'Air Conditioning',
                'power_steering': 'Power Steering',
                'abs_brakes': 'ABS Brakes',
                'airbags': 'Airbags',
                'central_locking': 'Central Locking',
                'electric_windows': 'Electric Windows',
                'power_mirrors': 'Power Mirrors',
                'fog_lights': 'Fog Lights',
                'cruise_control': 'Cruise Control',
                'bluetooth': 'Bluetooth',
                'usb_port': 'USB Port',
                'navigation': 'Navigation',
                'sunroof': 'Sunroof',
                'leather_seats': 'Leather Seats',
                'heated_seats': 'Heated Seats'
            };

            const formattedFeatures = features.map(feature => {
                const label = featureLabels[feature] || feature.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                return `<span class="rv-feature-badge">${label}</span>`;
            });

            return `<div class="rv-feature-list">${formattedFeatures.join('')}</div>`;
        }

        formatEquipmentList(equipment) {
            if (!Array.isArray(equipment) || equipment.length === 0) {
                return '<span class="rv-feature-text">-</span>';
            }

            const equipmentLabels = {
                'spare_tire': 'Spare Tire',
                'jack': 'Jack',
                'first_aid_kit': 'First Aid Kit',
                'fire_extinguisher': 'Fire Extinguisher',
                'warning_triangle': 'Warning Triangle',
                'jumper_cables': 'Jumper Cables',
                'ice_scraper': 'Ice Scraper',
                'car_cover': 'Car Cover',
                'child_seat': 'Child Seat',
                'gps_tracker': 'GPS Tracker',
                'dashcam': 'Dashcam',
                'phone_holder': 'Phone Holder',
                'charger': 'Charger',
                'cleaning_kit': 'Cleaning Kit',
                'emergency_kit': 'Emergency Kit'
            };

            const formattedEquipment = equipment.map(item => {
                const label = equipmentLabels[item] || item.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                return `<span class="rv-equipment-badge">${label}</span>`;
            });

            return `<div class="rv-equipment-list">${formattedEquipment.join('')}</div>`;
        }

        addVehicleToCards(vehicleData) {
            const $cardsContainer = this.container.find('.rv-comparison-cards');
            const $newCard = this.createVehicleCard(vehicleData);
            $cardsContainer.append($newCard);

            // Update comparison count
            this.updateComparisonCount();
        }

        createTableHeader(vehicleData) {
            // Show all features by default
            const showRemoveButtons = true;
            const showImages = true;
            const showBookingButtons = true;

            let headerHtml = '<th class="rv-vehicle-column">';
            headerHtml += '<div class="rv-vehicle-header">';

            if (showRemoveButtons) {
                headerHtml += `<button type="button" class="rv-remove-vehicle-btn" data-vehicle-id="${vehicleData.id}">`;
                headerHtml += '<span class="dashicons dashicons-no-alt"></span>';
                headerHtml += '</button>';
            }

            if (showImages && vehicleData.image_url) {
                headerHtml += '<div class="rv-vehicle-image">';
                headerHtml += `<img src="${vehicleData.image_url}" alt="${vehicleData.title}" loading="lazy">`;
                headerHtml += '</div>';
            }

            headerHtml += `<h4 class="rv-vehicle-title">${vehicleData.title}</h4>`;

            if (showBookingButtons) {
                headerHtml += `<a href="${vehicleData.permalink}" class="rv-book-now-btn">${window.mhmVehicleComparison?.strings?.make_booking || 'Make Booking'}</a>`;
            }

            headerHtml += '</div>';
            headerHtml += '</th>';

            return $(headerHtml);
        }

        createTableCell(vehicleData, featureKey) {
            const value = vehicleData.features[featureKey] || '-';
            let cellHtml = '<td class="rv-feature-value">';

            // Special formatting
            if (featureKey === 'price_per_day') {
                if (value > 0) {
                    cellHtml += '<span class="rv-price">';
                    cellHtml += this.formatNumber(value);
                    cellHtml += ` ${vehicleData.features.currency_symbol}`;
                    cellHtml += '</span>';
                } else {
                    cellHtml += '<span class="rv-no-price">-</span>';
                }
            } else if (featureKey === 'seats') {
                cellHtml += `<span class="rv-seats">${value} kişi</span>`;
            } else if (featureKey === 'doors') {
                cellHtml += `<span class="rv-doors">${value} kapı</span>`;
            } else if (['air_conditioning', 'gps', 'bluetooth', 'usb_port', 'sunroof'].includes(featureKey)) {
                const isYes = ['evet', 'yes', '1', 'true'].includes(value.toLowerCase());
                cellHtml += `<span class="rv-feature-status ${isYes ? 'available' : 'not-available'}">`;
                cellHtml += isYes ? '<span class="dashicons dashicons-yes-alt"></span>' : '<span class="dashicons dashicons-dismiss"></span>';
                cellHtml += '</span>';
            } else {
                cellHtml += `<span class="rv-feature-text">${value}</span>`;
            }

            cellHtml += '</td>';
            return $(cellHtml);
        }

        createVehicleCard(vehicleData) {
            const showRemoveButtons = this.container.find('.rv-remove-vehicle-btn').length > 0;
            const showImages = this.container.find('.rv-vehicle-image').length > 0;
            const showBookingButtons = this.container.find('.rv-book-now-btn').length > 0;

            let cardHtml = '<div class="rv-vehicle-card">';
            cardHtml += '<div class="rv-card-header">';

            if (showRemoveButtons) {
                cardHtml += `<button type="button" class="rv-remove-vehicle-btn" data-vehicle-id="${vehicleData.id}">`;
                cardHtml += '<span class="dashicons dashicons-no-alt"></span>';
                cardHtml += '</button>';
            }

            if (showImages && vehicleData.image_url) {
                cardHtml += '<div class="rv-vehicle-image">';
                cardHtml += `<img src="${vehicleData.image_url}" alt="${vehicleData.title}" loading="lazy">`;
                cardHtml += '</div>';
            }

            cardHtml += `<h4 class="rv-vehicle-title">${vehicleData.title}</h4>`;

            if (showBookingButtons) {
                cardHtml += `<a href="${vehicleData.permalink}" class="rv-book-now-btn">${window.mhmVehicleComparison?.strings?.make_booking || 'Make Booking'}</a>`;
            }

            cardHtml += '</div>';
            cardHtml += '<div class="rv-card-features">';

            // Add features
            const features = this.getComparisonFeatures();
            Object.keys(features).forEach(featureKey => {
                const featureLabel = features[featureKey];
                const value = vehicleData.features[featureKey] || '-';

                cardHtml += '<div class="rv-feature-item">';
                cardHtml += `<span class="rv-feature-label">${featureLabel}:</span>`;
                cardHtml += '<span class="rv-feature-value">';

                // Special formatting
                if (featureKey === 'price_per_day') {
                    if (value > 0) {
                        cardHtml += '<span class="rv-price">';
                        cardHtml += this.formatNumber(value);
                        cardHtml += ` ${vehicleData.features.currency_symbol}`;
                        cardHtml += '</span>';
                    } else {
                        cardHtml += '<span class="rv-no-price">-</span>';
                    }
                } else if (featureKey === 'seats') {
                    cardHtml += `<span class="rv-seats">${value} kişi</span>`;
                } else if (featureKey === 'doors') {
                    cardHtml += `<span class="rv-doors">${value} kapı</span>`;
                } else if (['air_conditioning', 'gps', 'bluetooth', 'usb_port', 'sunroof'].includes(featureKey)) {
                    const isYes = ['evet', 'yes', '1', 'true'].includes(value.toLowerCase());
                    cardHtml += `<span class="rv-feature-status ${isYes ? 'available' : 'not-available'}">`;
                    cardHtml += isYes ? '<span class="dashicons dashicons-yes-alt"></span>' : '<span class="dashicons dashicons-dismiss"></span>';
                    cardHtml += '</span>';
                } else {
                    cardHtml += `<span class="rv-feature-text">${value}</span>`;
                }

                cardHtml += '</span>';
                cardHtml += '</div>';
            });

            cardHtml += '</div>';
            cardHtml += '</div>';

            return $(cardHtml);
        }

        removeVehicleFromDOM(vehicleId) {
            // IMPORTANT: Also remove from currentVehicles array
            this.currentVehicles = this.currentVehicles.filter(vehicle => {
                const id = typeof vehicle === 'object' ? vehicle.id : vehicle;
                return id !== vehicleId;
            });


            // Completely remove vehicle column in table layout
            const $vehicleColumn = this.container.find(`[data-vehicle-id="${vehicleId}"]`).closest('th');
            if ($vehicleColumn.length) {
                // Find column index
                const columnIndex = $vehicleColumn.index();

                // Remove column from header
                $vehicleColumn.remove();

                // Remove cells for this vehicle from all feature rows
                this.container.find('tbody tr').each(function () {
                    const $row = $(this);
                    const $cells = $row.find('td');

                    // If column index is valid, remove that cell
                    if (columnIndex >= 0 && columnIndex < $cells.length) {
                        $cells.eq(columnIndex).remove();
                    }
                });
            }

            // Remove vehicle card in cards layout
            this.container.find(`[data-vehicle-id="${vehicleId}"]`).closest('.rv-vehicle-card').remove();

            // Readjust table width
            this.adjustTableWidth();

            // Update comparison count
            this.updateComparisonCount();
        }

        updateAddVehicleForm() {
            const $select = this.container.find('#rv-add-vehicle-select');

            // First clear all options (except first option)
            $select.find('option:not(:first)').remove();

            // Get current vehicle IDs
            const currentVehicleIds = this.currentVehicles.map(vehicle => {
                return typeof vehicle === 'object' ? vehicle.id : vehicle;
            });


            // Re-add all available vehicles (only those not in comparison)
            let availableVehicles = window.mhmRentivaVehicleComparison?.availableVehicles;

            // If availableVehicles is not available, get from container data attribute
            if (!availableVehicles && this.container.data('all-vehicles')) {
                availableVehicles = this.container.data('all-vehicles');
            }


            if (availableVehicles && availableVehicles.length > 0) {
                availableVehicles.forEach(vehicle => {
                    const vehicleId = vehicle.id || vehicle;
                    if (!currentVehicleIds.includes(vehicleId)) {
                        $select.append(`<option value="${vehicleId}">${vehicle.title || vehicle}</option>`);
                    }
                });
            } else {
            }


            // Hide form if maximum vehicle count is reached
            if (this.currentVehicles.length >= this.maxVehicles) {
                this.container.find('.rv-add-vehicle-section').hide();
            } else {
                this.container.find('.rv-add-vehicle-section').show();
            }
        }

        updateComparisonCount() {
            const count = this.currentVehicles.length;
            const countText = count === 1 ?
                (window.mhmRentivaVehicleComparison?.one_vehicle_compared || '1 vehicle being compared') :
                (window.mhmRentivaVehicleComparison?.multiple_vehicles_compared?.replace('%d', count) || `${count} vehicles being compared`);
            this.container.find('.rv-comparison-count').text(countText);
        }

        adjustTableWidth() {
            const $table = this.container.find('.rv-comparison-table');
            const $wrapper = this.container.find('.rv-comparison-table-wrapper');

            if ($table.length === 0) return;

            // Calculate vehicle count
            const vehicleCount = this.currentVehicles.length;

            if (vehicleCount === 0) return;

            // Make table width responsive - without scrollbar
            $table.css({
                'width': '100%',
                'table-layout': 'auto',
                'min-width': 'auto',
                'overflow': 'visible',
                'height': 'auto',
                'max-height': 'none',
                'overflow-y': 'visible',
                'overflow-x': 'visible',
                'position': 'static',
                'display': 'table'
            });

            // Adjust wrapper width - without scrollbar
            $wrapper.css({
                'width': '100%',
                'max-width': '100%',
                'overflow': 'visible',
                'height': 'auto',
                'max-height': 'none',
                'overflow-y': 'visible',
                'overflow-x': 'visible'
            });

            // Distribute vehicle columns equally
            const availableWidth = $wrapper.width();
            const featureColumnWidth = Math.min(200, availableWidth * 0.25); // Feature column 25%
            const remainingWidth = availableWidth - featureColumnWidth;
            const vehicleColumnWidth = Math.max(150, remainingWidth / vehicleCount); // Each vehicle column equal

            // Set column widths
            $table.find('.rv-feature-column').css('width', featureColumnWidth + 'px');
            $table.find('.rv-vehicle-column').css('width', vehicleColumnWidth + 'px');

            // Remove main container width - use full width
            this.container.css({
                'min-width': 'auto',
                'width': '100%',
                'max-width': 'none',
                'overflow': 'visible',
                'height': 'auto',
                'max-height': 'none',
                'overflow-y': 'visible',
                'overflow-x': 'visible'
            });

            // Different behavior at responsive breakpoints
            if (window.innerWidth <= 768) {
                const mobileFeatureWidth = 150;
                const mobileVehicleWidth = 200;
                const mobileMinWidth = mobileFeatureWidth + (vehicleCount * mobileVehicleWidth);
                $table.css({
                    'min-width': mobileMinWidth + 'px',
                    'width': mobileMinWidth + 'px'
                });
            }

        }

        getFeatureKeyFromRow($row) {
            // Extract feature key from table row
            const featureName = $row.find('.rv-feature-name').text().trim();
            const features = this.getComparisonFeatures();

            for (const [key, label] of Object.entries(features)) {
                if (label === featureName) {
                    return key;
                }
            }

            return null;
        }

        getComparisonFeatures() {
            const configured = window.mhmRentivaVehicleComparison?.features;
            if (configured && Object.keys(configured).length > 0) {
                return configured;
            }

            return {};
        }

        formatNumber(number) {
            const locale = window.mhmVehicleComparison?.locale || 'en-US';
            return new Intl.NumberFormat(locale, {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(number);
        }

        showLoading(show) {
            if (show) {
                this.container.find('.rv-add-vehicle-btn').prop('disabled', true).text(window.mhmVehicleComparison?.strings?.loading || 'Loading...');
            } else {
                this.container.find('.rv-add-vehicle-btn').prop('disabled', false).text(window.mhmVehicleComparison?.strings?.add_vehicle || 'Add Vehicle');
            }
        }

        showSuccess(message) {
            this.hideMessages();
            this.container.find('.rv-success-message').text(message).removeClass('rv-hidden').show();
            this.container.find('.rv-error-message').addClass('rv-hidden').hide();

            // Auto-hide after 3 seconds
            setTimeout(() => {
                this.hideMessages();
            }, 3000);
        }

        showError(message) {
            this.hideMessages();
            this.container.find('.rv-error-message').text(message).removeClass('rv-hidden').show();
            this.container.find('.rv-success-message').addClass('rv-hidden').hide();

            // Auto-hide after 5 seconds
            setTimeout(() => {
                this.hideMessages();
            }, 5000);
        }

        hideMessages() {
            this.container.find('.rv-success-message, .rv-error-message').addClass('rv-hidden').hide();
        }

        updateComparisonState() {
            // Sadece comparison content'i kontrol et
            const $comparisonContent = this.container.find('.rv-comparison-content');

            if (this.currentVehicles.length === 0) {
                $comparisonContent.hide();
            } else {
                $comparisonContent.show();
            }
        }

        // Public methods for external use
        addVehicleById(vehicleId) {
            this.addVehicle(vehicleId);
        }

        removeVehicleById(vehicleId) {
            this.removeVehicle(vehicleId);
        }

        getCurrentVehicles() {
            return [...this.currentVehicles];
        }

        clearComparison() {
            this.currentVehicles.forEach(vehicleId => {
                this.removeVehicle(vehicleId);
            });
        }
    }

    // Initialize when document is ready
    $(document).ready(function () {
        new VehicleComparison();
    });

    // Make VehicleComparison class globally available
    window.VehicleComparison = VehicleComparison;

})(jQuery);
