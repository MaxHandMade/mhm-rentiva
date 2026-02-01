/**
 * VehicleMeta.js - Vehicle Meta Box JavaScript Functions
 * 
 * This file contains JavaScript functionality for VehicleMeta.php file.
 * Manages tab system, drag & drop, dynamic item add/remove functions.
 */

(function ($) {
    'use strict';

    // Global variables
    let removedDetails = [];
    let availableVehicleDetails = window.availableVehicleDetails || {};

    /**
     * Main function to run when DOM is loaded
     */
    $(document).ready(function () {
        initializeTabSystem();
        initializeAvailabilityDropdown();
        initializeInputEffects();
        initializeCheckboxGrid();
        initializeDragAndDrop();
        initializeDynamicFeatures();
        initializeDynamicEquipment();
        initializeDynamicDetails();
        initializeRemoveButtons();

    });

    /**
     * Tab system functionality
     */
    function initializeTabSystem() {
        const tabButtons = $('.mhm-tab-btn');
        const tabPanes = $('.mhm-tab-pane');

        tabButtons.on('click', function () {
            const targetTab = $(this).data('tab');

            // Remove all active classes
            tabButtons.removeClass('active');
            tabPanes.removeClass('active');

            // Add active class to clicked tab
            $(this).addClass('active');
            const targetPane = $('#' + targetTab + '-tab');

            if (targetPane.length) {
                targetPane.addClass('active');

                // Equipment tab special control
                if (targetTab === 'equipment') {
                }
            }

            // Save to LocalStorage
            localStorage.setItem('mhm_active_tab', targetTab);
        });

        // Restore saved tab
        restoreActiveTab();
    }

    /**
     * Restore saved active tab
     */
    function restoreActiveTab() {
        const savedTab = localStorage.getItem('mhm_active_tab');
        if (savedTab) {
            const savedButton = $(`[data-tab="${savedTab}"]`);
            const savedPane = $('#' + savedTab + '-tab');

            if (savedButton.length && savedPane.length) {
                $('.mhm-tab-btn').removeClass('active');
                $('.mhm-tab-pane').removeClass('active');
                savedButton.addClass('active');
                savedPane.addClass('active');
            }
        }
    }

    /**
     * Availability status dropdown functionality
     */
    function initializeAvailabilityDropdown() {
        $('#mhm_rentiva_available').on('change', function () {
            const value = $(this).val();
            const $select = $(this);

            // Visual feedback
            $select.css('transform', 'scale(1.02)');
            setTimeout(() => {
                $select.css('transform', 'scale(1)');
            }, 150);

            // Color change
            $select.removeClass('status-active status-passive status-maintenance');

            switch (value) {
                case 'active':
                    $select.addClass('status-active');
                    break;
                case 'passive':
                    $select.addClass('status-passive');
                    break;
                case 'maintenance':
                    $select.addClass('status-maintenance');
                    break;
            }
        });

        // Apply style based on current value when page loads
        const currentValue = $('#mhm_rentiva_available').val();
        if (currentValue) {
            $('#mhm_rentiva_available').trigger('change');
        }
    }

    /**
     * Input effects
     */
    function initializeInputEffects() {
        $('.mhm-field-group input, .mhm-field-group select').on('focus', function () {
            $(this).parent().css({
                'transform': 'translateY(-3px)',
                'box-shadow': '0 12px 30px rgba(0,115,170,0.2)'
            });
        }).on('blur', function () {
            $(this).parent().css({
                'transform': 'translateY(0)',
                'box-shadow': '0 8px 25px rgba(0,115,170,0.15)'
            });
        });

        // Special input validations
        initializePriceField();
        initializeSeatsField();
    }

    /**
     * Price field validation
     */
    function initializePriceField() {
        const priceInput = $('#mhm_rentiva_price_per_day');
        if (priceInput.length) {
            priceInput.on('input', function () {
                const value = parseFloat($(this).val());
                if (value > 0) {
                    $(this).parent().css('border-color', '#28a745');
                } else {
                    $(this).parent().css('border-color', '#e9ecef');
                }
            });
        }
    }

    /**
     * Seats field validation
     */
    function initializeSeatsField() {
        const seatsInput = $('#mhm_rentiva_seats');
        if (seatsInput.length) {
            seatsInput.on('input', function () {
                const value = parseInt($(this).val());
                if (value >= 1 && value <= 20) {
                    $(this).parent().css('border-color', '#28a745');
                } else {
                    $(this).parent().css('border-color', '#dc3545');
                }
            });
        }
    }

    /**
     * Checkbox grid interactions
     */
    function initializeCheckboxGrid() {
        $('.mhm-checkbox-item').each(function () {
            const item = $(this);
            const checkbox = item.find('input[type="checkbox"]');
            const label = item.find('label');

            if (checkbox.length && label.length) {
                // Toggle checkbox when label is clicked
                label.on('click', function () {
                    checkbox.prop('checked', !checkbox.prop('checked'));
                    updateCheckboxItemStyle(item, checkbox.prop('checked'));
                });

                // Update style when checkbox changes
                checkbox.on('change', function () {
                    updateCheckboxItemStyle(item, $(this).prop('checked'));
                });

                // Initial style update
                updateCheckboxItemStyle(item, checkbox.prop('checked'));
            }
        });
    }

    /**
     * Update checkbox item style
     */
    function updateCheckboxItemStyle(item, isChecked) {
        if (isChecked) {
            item.css({
                'background': 'rgba(0,115,170,0.1)',
                'border-color': '#0073aa',
                'transform': 'scale(1.02)'
            });
        } else {
            item.css({
                'background': 'rgba(255,255,255,0.7)',
                'border-color': '#e9ecef',
                'transform': 'scale(1)'
            });
        }
    }

    /**
     * Drag & Drop functionality
     */
    function initializeDragAndDrop() {
        const grids = ['details-grid', 'features-grid', 'equipment-grid'];

        grids.forEach(gridId => {
            const grid = $('#' + gridId);

            if (grid.length && typeof $.fn.sortable !== 'undefined') {
                grid.sortable({
                    items: '.mhm-detail-item, .mhm-checkbox-item',
                    placeholder: 'mhm-sortable-placeholder',
                    forcePlaceholderSize: true,
                    cursor: 'move',
                    opacity: 0.8,
                    update: function (event, ui) {
                        // Save when order changes
                        saveItemOrder(gridId);
                    }
                });
            }
        });
    }

    /**
     * Save sorting changes via AJAX
     */
    function saveItemOrder(gridId) {
        const grid = $('#' + gridId);
        const items = grid.find('.mhm-detail-item, .mhm-checkbox-item');
        const order = [];

        items.each(function () {
            const key = $(this).data('detail-key') || $(this).data('feature-key') || $(this).data('equipment-key');
            if (key) {
                order.push(key);
            }
        });

        // Send to server via AJAX
        $.ajax({
            url: window.ajaxurl || (window.mhmVehicleMeta?.ajaxUrl || window.location.origin + '/wp-admin/admin-ajax.php'),
            type: 'POST',
            data: {
                action: 'mhm_save_item_order',
                grid_type: gridId.replace('-grid', ''),
                order: order,
                post_id: $('#post_ID').val(),
                nonce: window.mhmVehicleMeta?.nonce || $('#mhm_rentiva_vehicle_meta_nonce').val()
            },
            success: function (response) {
                if (response.success) {
                    showDragSuccessMessage();
                } else {
                    const errorMsg = (window.mhmVehicleMeta && window.mhmVehicleMeta.strings && window.mhmVehicleMeta.strings.orderSaveError) || 'Failed to save order';
                    if (typeof console !== 'undefined' && console.error) {
                        console.error(errorMsg, response.data);
                    }
                }
            },
            error: function () {
                const errorMsg = (window.mhmVehicleMeta && window.mhmVehicleMeta.strings && window.mhmVehicleMeta.strings.ajaxError) || 'AJAX error: Failed to save order';
                if (typeof console !== 'undefined' && console.error) {
                    console.error(errorMsg);
                }
            }
        });
    }

    /**
     * Show drag success message
     */
    function showDragSuccessMessage() {
        const message = $('<div>').css({
            'position': 'fixed',
            'top': '20px',
            'right': '20px',
            'z-index': '10000',
            'background': '#4CAF50',
            'color': 'white',
            'padding': '10px 20px',
            'border-radius': '4px',
            'font-weight': 'bold',
            'box-shadow': '0 2px 4px rgba(0,0,0,0.2)'
        }).text(window.mhmVehicleMeta?.strings?.orderUpdated || 'Order updated!');

        $('body').append(message);

        setTimeout(() => {
            message.remove();
        }, 2000);
    }

    /**
     * Update form field names
     */
    function updateFormFieldNames(gridContainer) {
        const items = gridContainer.find('.mhm-detail-item, .mhm-checkbox-item');
        const gridId = gridContainer.attr('id');

        // Create or update order hidden field
        let orderField = $('#' + gridId + '_order');
        if (!orderField.length) {
            orderField = $('<input>').attr({
                'type': 'hidden',
                'id': gridId + '_order',
                'name': gridId + '_order'
            });

            // Add to grid container
            gridContainer.append(orderField);
        }

        // Collect field names according to current DOM order
        const fieldOrder = [];
        items.each(function (index) {
            const $this = $(this);
            const fieldKey = $this.attr('data-detail-key') ||
                $this.attr('data-key') ||
                $this.attr('data-feature-key') ||
                $this.attr('data-equipment-key');

            if (fieldKey) {
                fieldOrder.push(fieldKey);
            }
        });

        // Update hidden field with new order
        orderField.val(JSON.stringify(fieldOrder));
    }

    /**
     * Dynamic feature addition functionality
     */
    function initializeDynamicFeatures() {
        // Custom feature addition
        $('#add-custom-feature').on('click', function () {
            const featureName = prompt(window.mhmVehicleMeta?.strings?.enterNewFeature || 'Enter new feature name:');
            if (featureName && featureName.trim()) {
                const featureKey = 'custom_' + Date.now();
                const featureLabel = featureName.trim();
                addCustomFeature(featureKey, featureLabel);
            }
        });

        // Add feature from settings
        $('#add-from-settings').on('click', function () {
            showSettingsFeatureSelector();
        });
    }

    /**
     * Dynamic equipment addition functionality
     */
    function initializeDynamicEquipment() {
        // Custom equipment addition
        $('#add-custom-equipment').on('click', function () {
            const equipmentName = prompt(window.mhmVehicleMeta?.strings?.enterNewEquipment || 'Enter new equipment name:');
            if (equipmentName && equipmentName.trim()) {
                const equipmentKey = 'custom_' + Date.now();
                const equipmentLabel = equipmentName.trim();
                addCustomEquipment(equipmentKey, equipmentLabel);
            }
        });

        // Add equipment from settings
        $('#add-equipment-from-settings').on('click', function () {
            showSettingsEquipmentSelector();
        });
    }

    /**
     * Dynamic detail addition functionality
     */
    function initializeDynamicDetails() {
        // Custom detail addition
        $('#add-custom-detail').on('click', function () {
            const detailName = prompt(window.mhmVehicleMeta?.strings?.enterNewDetail || 'Enter new detail name:');
            if (detailName && detailName.trim()) {
                const detailKey = 'custom_' + Date.now();
                addCustomDetail(detailKey, detailName.trim(), '');
            }
        });

        // Add detail from settings
        $('#add-from-settings-detail').on('click', function () {
            showSettingsDetailSelector();
        });
    }

    /**
     * Remove buttons functionality
     */
    function initializeRemoveButtons() {
        $(document).on('click', '.remove-feature-btn', function () {
            const key = $(this).data('key');
            if (confirm(window.mhmVehicleMeta?.strings?.confirmRemoveFeature || 'Are you sure you want to remove this feature?')) {
                removeFeature(key);
            }
        });

        $(document).on('click', '.remove-equipment-btn', function () {
            const key = $(this).data('key');
            if (confirm(window.mhmVehicleMeta?.strings?.confirmRemoveEquipment || 'Are you sure you want to remove this equipment?')) {
                removeEquipment(key);
            }
        });

        $(document).on('click', '.remove-detail-btn', function () {
            const key = $(this).attr('data-detail-key') || $(this).attr('data-key');

            const item = $(`[data-detail-key="${key}"]`);

            if (item.length) {
                item.remove();

                // Add removed detail to list
                if (removedDetails.indexOf(key) === -1) {
                    removedDetails.push(key);
                }

                // Save removed details to hidden field
                updateRemovedDetails();

                // Update form field names
                const gridContainer = item.closest('.mhm-details-grid');
                if (gridContainer.length) {
                    updateFormFieldNames(gridContainer);
                }
            }
        });
    }

    /**
     * Escape HTML special characters
     */
    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, function (m) { return map[m]; });
    }

    /**
     * Add custom feature
     */
    function addCustomFeature(key, label) {
        const grid = $('#features-grid');
        const safeLabel = escapeHtml(label);
        const newItem = $('<div>').addClass('mhm-checkbox-item')
            .attr('data-feature-key', key)
            .html(`
                                    <input type="checkbox" id="feature_${key}" name="mhm_rentiva_features[]" value="${key}" checked />
                                    <label for="feature_${key}">${safeLabel}</label>
                                    <button type="button" class="remove-feature-btn" data-key="${key}" title="${window.mhmVehicleMeta?.strings?.remove || 'Remove'}" style="margin-left: auto; background: #dc3545; color: white; border: none; border-radius: 3px; padding: 2px 6px; font-size: 10px; cursor: pointer;">×</button>
                                 `);

        grid.append(newItem);
        setupCheckboxItemEvents(newItem);
        updateCheckboxItemStyle(newItem, true);
        makeItemDraggable(newItem);
    }

    /**
     * Add custom equipment
     */
    function addCustomEquipment(key, label) {
        const grid = $('#equipment-grid');
        const safeLabel = escapeHtml(label);
        const newItem = $('<div>').addClass('mhm-checkbox-item')
            .attr('data-equipment-key', key)
            .html(`
                                    <input type="checkbox" id="equipment_${key}" name="mhm_rentiva_equipment[]" value="${key}" checked />
                                    <label for="equipment_${key}">${safeLabel}</label>
                                    <button type="button" class="remove-equipment-btn" data-key="${key}" title="${window.mhmVehicleMeta?.strings?.remove || 'Remove'}" style="margin-left: auto; background: #dc3545; color: white; border: none; border-radius: 3px; padding: 2px 6px; font-size: 10px; cursor: pointer;">×</button>
                                 `);

        grid.append(newItem);
        setupCheckboxItemEvents(newItem);
        updateCheckboxItemStyle(newItem, true);
        makeItemDraggable(newItem);
    }

    /**
     * Add custom detail
     */
    function addCustomDetail(key, name, icon) {
        const grid = $('#details-grid');
        const safeName = escapeHtml(name);
        const newItem = $('<div>').addClass('mhm-detail-item')
            .attr('data-detail-key', key)
            .html(`
                                    <div class="mhm-detail-content">
                                        <label class="mhm-detail-label">${safeName}</label>
                                        <input type="hidden" name="mhm_rentiva_custom_details[${key}][label]" value="${safeName}" />
                                        <input type="text" name="mhm_rentiva_custom_details[${key}][value]" placeholder="${window.mhmVehicleMeta?.strings?.enterValue || 'Enter value'}" class="mhm-detail-input" />
                                        <button type="button" class="remove-detail-btn" data-detail-key="${key}" title="${window.mhmVehicleMeta?.strings?.remove || 'Remove'}" style="position: absolute; top: 8px; right: 8px; background: #dc3545; color: white; border: none; border-radius: 3px; padding: 4px 8px; font-size: 12px; cursor: pointer; z-index: 10;">×</button>
                                    </div>
                                 `);

        grid.append(newItem);
        makeItemDraggable(newItem);
    }

    /**
     * Remove feature
     */
    function removeFeature(key) {
        $(`[data-feature-key="${key}"]`).remove();
    }

    /**
     * Remove equipment
     */
    function removeEquipment(key) {
        $(`[data-equipment-key="${key}"]`).remove();
    }

    /**
     * Setup checkbox item events
     */
    function setupCheckboxItemEvents(item) {
        const checkbox = item.find('input[type="checkbox"]');
        const label = item.find('label');

        if (checkbox.length && label.length) {
            label.on('click', function () {
                checkbox.prop('checked', !checkbox.prop('checked'));
                updateCheckboxItemStyle(item, checkbox.prop('checked'));
            });

            checkbox.on('change', function () {
                updateCheckboxItemStyle(item, $(this).prop('checked'));
            });
        }
    }

    /**
     * Feature selector from settings
     */
    function showSettingsFeatureSelector() {
        showNotice(window.mhmVehicleMeta?.strings?.comingSoonCustomAdd || 'Coming soon! Use the Custom Add button for now.', 'info');
    }

    /**
     * Equipment selector from settings
     */
    function showSettingsEquipmentSelector() {
        showNotice(window.mhmVehicleMeta?.strings?.comingSoonCustomAdd || 'Coming soon! Use the Custom Add button for now.', 'info');
    }

    /**
     * Detail selector from settings
     */
    function showSettingsDetailSelector() {
        showNotice(window.mhmVehicleMeta?.strings?.redirectingToSettings || 'Redirecting to Vehicle Settings...', 'info');
        window.open('admin.php?page=vehicle-settings', '_blank');
    }

    /**
     * Update removed details
     */
    function updateRemovedDetails() {
        let hiddenField = $('#removed_details');
        if (!hiddenField.length) {
            hiddenField = $('<input>').attr({
                'type': 'hidden',
                'id': 'removed_details',
                'name': 'removed_details'
            });

            // Find form element
            const form = $('#post, #post-form, form').first();
            if (form.length) {
                form.append(hiddenField);
            }
        }

        const removedData = JSON.stringify(removedDetails);
        hiddenField.val(removedData);
    }

    /**
     * Detail toggle functionality
     */
    $(document).on('change', '.mhm-detail-checkbox', function () {
        const statusElement = $(this).parent().next('.mhm-detail-status');
        const strings = (window.mhmVehicleMeta && window.mhmVehicleMeta.strings) || {};
        if ($(this).prop('checked')) {
            statusElement.removeClass('mhm-status-unavailable')
                .addClass('mhm-status-available')
                .text(strings.available || 'Available');
        } else {
            statusElement.removeClass('mhm-status-available')
                .addClass('mhm-status-unavailable')
                .text(strings.notAvailable || 'Not Available');
        }
    });

    /**
     * Deposit input functionality
     */
    function initializeDepositInput() {
        $('.deposit-input').on('input', function () {
            const value = $(this).val();
            const helpText = $(this).next('.deposit-help');

            const strings = (window.mhmVehicleMeta && window.mhmVehicleMeta.strings) || {};

            // Format check and help text update
            if (value.match(/^%?(\d+(?:\.\d+)?)%?$/)) {
                helpText.removeClass('error').addClass('success');
                helpText.text('✓ ' + (strings.validFormat || 'Valid format'));
            } else if (value === '') {
                helpText.removeClass('error success');
                helpText.text(strings.depositFormatHelp || 'Fixed: 1000 | Percent: %20');
            } else {
                helpText.removeClass('success').addClass('error');
                helpText.text('✗ ' + (strings.invalidFormat || 'Invalid format'));
            }
        });

        // Update placeholder
        $('.deposit-input').attr('placeholder',
            window.mhmVehicleMeta?.strings?.depositPlaceholder || '1000 or %20'
        );
    }

    /**
     * Form submission animation
     */
    $('form#post').on('submit', function () {
        const submitButton = $('#publish');
        if (submitButton.length) {
            submitButton.css({
                'opacity': '0.7',
                'pointer-events': 'none'
            });
        }
    });

    /**
     * Show notice message
     */
    function showNotice(message, type) {
        type = type || 'info';
        var noticeClass = 'notice-' + type;
        // Basic escaping for message just in case
        var safeMessage = escapeHtml(message);
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible" style="position: fixed; top: 32px; right: 20px; z-index: 9999; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);"><p><strong>' + safeMessage + '</strong></p></div>');

        // Remove any existing notices first
        $('.notice').remove();

        // Add to body for better visibility
        $('body').append(notice);

        // Auto-dismiss after 5 seconds
        setTimeout(function () {
            notice.fadeOut(500, function () {
                notice.remove();
            });
        }, 5000);
    }

})(jQuery);
