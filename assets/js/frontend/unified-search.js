/**
 * Unified Search Widget JS
 */
(function ($) {
    'use strict';

    class UnifiedSearch {
        constructor() {
            this.initOnLoadAudit();
            this.initTabs();
            this.initDatepickers();
            this.initRentalConstraints();
            this.initTransferSearch();
        }

        initOnLoadAudit() {
            console.group('[MHM Rentiva] Initial State Audit');
            console.log('Initial Service Type:', mhmUnifiedSearch.initial_service);
            console.log('REST URL:', mhmUnifiedSearch.restUrl);
            console.log('Dropdown Count:', $('select[name="pickup_location"] option').length - 1);
            console.groupEnd();
        }

        initRentalConstraints() {
            const self = this;
            const minDays = mhmUnifiedSearch.settings.minRentalDays || 1;

            // 1. Pickup Date -> Return Date minDate constraint
            $(document).on('change', '.rv-unified-search [name="pickup_date"]', function () {
                const $picker = $(this);
                const $form = $picker.closest('form');
                const $returnPicker = $form.find('[name="return_date"]');

                const selectedDate = $picker.datepicker('getDate');
                if (selectedDate) {
                    const minReturnDate = new Date(selectedDate.getTime());
                    minReturnDate.setDate(minReturnDate.getDate() + minDays);

                    $returnPicker.datepicker('option', 'minDate', minReturnDate);

                    // If current return date is earlier than new minDate, reset it
                    const currentReturnDate = $returnPicker.datepicker('getDate');
                    if (currentReturnDate && currentReturnDate < minReturnDate) {
                        $returnPicker.datepicker('setDate', minReturnDate);
                    }
                }
            });

            // 2. Pickup Time -> Return Time synchronization
            $(document).on('change', '.rv-unified-search [name="pickup_time"]', function () {
                const $timeSelect = $(this);
                const $form = $timeSelect.closest('form');
                const val = $timeSelect.val();

                // Update the visible (disabled) select
                $form.find('[name="return_time_display"]').val(val);
                // Update the hidden input for submission
                $form.find('[name="return_time"]').val(val);
            });
        }

        initTabs() {
            const self = this;
            $(document).on('click', '.rv-unified-search__tab', function (e) {
                e.preventDefault();
                const $btn = $(this);
                const target = $btn.data('target'); // 'rental' or 'transfer'
                const $wrapper = $btn.closest('.rv-unified-search');

                if (!$wrapper.length) return;

                // Toggle Buttons
                $wrapper.find('.rv-unified-search__tab').removeClass('is-active').attr('aria-selected', 'false');
                $btn.addClass('is-active').attr('aria-selected', 'true');

                // Toggle Panels
                $wrapper.find('.rv-unified-search__panel').removeClass('is-active');
                const $targetPanel = $wrapper.find('#' + $wrapper.attr('id') + '_panel_' + target);
                if ($targetPanel.length) {
                    $targetPanel.addClass('is-active');
                }

                // Dynamic Location Filtering
                self.syncLocations($wrapper, target);

                // Dispatch Event
                $(document).trigger('mhm-rentiva:tab-changed', [target]);
            });
        }

        syncLocations($wrapper, serviceType) {
            const $dropdowns = $wrapper.find('select[name="pickup_location"], select[name="dropoff_location"], select[name="origin_id"], select[name="destination_id"]');

            if (!$dropdowns.length || !mhmUnifiedSearch.restUrl) return;

            // Show loading state
            $dropdowns.prop('disabled', true);

            $.ajax({
                url: mhmUnifiedSearch.restUrl,
                data: { service_type: serviceType },
                method: 'GET',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', mhmUnifiedSearch.restNonce);
                },
                success: function (locations) {
                    $dropdowns.each(function () {
                        const $select = $(this);
                        const currentVal = $select.val();

                        // Clear existing options except placeholders
                        $select.find('option').not('[value=""]').remove();

                        // Add new options
                        locations.forEach(function (loc) {
                            $select.append($('<option>', {
                                value: loc.id,
                                text: loc.name
                            }));
                        });

                        // Restore value if still exists in new list
                        if (currentVal && $select.find('option[value="' + currentVal + '"]').length) {
                            $select.val(currentVal);
                        } else {
                            $select.val('');
                        }
                    });
                },
                complete: function () {
                    $dropdowns.prop('disabled', false);
                }
            });
        }

        initDatepickers() {
            const config = {
                dateFormat: 'yy-mm-dd',
                minDate: 0,
                showButtonPanel: true,
                closeText: 'Close',
                currentText: 'Today',
                beforeShow: function (input, inst) {
                    $('#ui-datepicker-div').addClass('rv-datepicker-skin');
                }
            };

            // Initialize on static elements
            $('.rv-unified-search .js-datepicker').datepicker(config);

            // Re-init for dynamic elements if necessary
            $(document).on('focus', '.js-datepicker:not(.hasDatepicker)', function () {
                $(this).datepicker(config);
            });
        }

        initTransferSearch() {
            const self = this;

            // Simple validation before submission
            $(document).on('submit', '.js-unified-transfer-form', function (e) {
                const $form = $(this);
                const originId = $form.find('[name="origin_id"]').val();
                const destId = $form.find('[name="destination_id"]').val();

                if (!originId || !destId) {
                    e.preventDefault();
                    MHMRentivaToast.show(mhmUnifiedSearch.i18n.error_text, { type: 'error' });
                }
            });

            // Route Validation Delegation
            $(document).on('change', '.js-unified-transfer-form [name="origin_id"], .js-unified-transfer-form [name="destination_id"]', function () {
                const $form = $(this).closest('form');
                const originId = $form.find('[name="origin_id"]').val();
                const destId = $form.find('[name="destination_id"]').val();

                if (originId && destId && originId === destId) {
                    MHMRentivaToast.show(mhmUnifiedSearch.i18n.same_location_error, { type: 'error' });
                    $form.find('[name="destination_id"]').val('');
                    return;
                }
            });
        }
    }

    $(document).ready(function () {
        new UnifiedSearch();
    });

})(jQuery);
