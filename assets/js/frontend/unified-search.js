/**
 * Unified Search Widget JS
 * 
 * Refactored for v4.20.x Hotfix:
 * - Removed local datepicker init (delegated to datepicker-init.js)
 * - Fixed syntax errors and bracket nesting.
 */
(function ($) {
    'use strict';

    class UnifiedSearch {
        constructor() {
            this.initOnLoadAudit();
            this.initTabs();
            this.initRentalConstraints();
            this.initDropoffMirror();
            // initTransferSearch removed (delegated to rentiva-transfer.js for parity)
        }

        initOnLoadAudit() {
            if (typeof mhmUnifiedSearch === 'undefined') return;
            console.group('[MHM Rentiva] Initial State Audit');
            console.log('Initial Service Type:', mhmUnifiedSearch.initial_service);
            console.log('REST URL:', mhmUnifiedSearch.restUrl);
            console.log('Dropdown Count:', $('select[name="pickup_location"] option').length - 1);
            console.groupEnd();
        }

        initRentalConstraints() {
            const self = this;
            const minDays = (typeof mhmUnifiedSearch !== 'undefined' && mhmUnifiedSearch.settings) ? mhmUnifiedSearch.settings.minRentalDays || 1 : 1;

            // 1. Pickup Date -> Return Date minDate constraint
            $(document).on('change', '.rv-unified-search [name="pickup_date"]', function () {
                const $picker = $(this);
                const $form = $picker.closest('form');
                const $returnPicker = $form.find('[name="return_date"]');

                if (typeof $.fn.datepicker === 'undefined') return;

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

        initDropoffMirror() {
            // In the rental tab, Teslim Etme (dropoff_location) always mirrors
            // Teslim Alma (pickup_location). Users cannot change it independently.
            const self = this;

            // Helper: lock and sync dropoff to current pickup value in a wrapper.
            function applyMirror($wrapper) {
                const $pickup        = $wrapper.find('[name="pickup_location"]');
                const $dropoff       = $wrapper.find('select[name="dropoff_location"]');
                const $dropoffHidden = $wrapper.find('input[type="hidden"][name="dropoff_location"]');
                if (!$dropoff.length) return;
                const val = $pickup.val() || '';
                $dropoff.val(val).prop('disabled', true);
                $dropoffHidden.val(val);
            }

            // On page load: lock every rental panel's dropoff immediately.
            $('.rv-unified-search').each(function () {
                applyMirror($(this));
            });

            // On pickup change: sync dropoff.
            $(document).on('change', '.rv-unified-search [name="pickup_location"]', function () {
                applyMirror($(this).closest('.rv-unified-search'));
            });

            // Store helper on instance so syncLocations can call it.
            self._applyDropoffMirror = applyMirror;
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
            if (typeof mhmUnifiedSearch === 'undefined' || !mhmUnifiedSearch.restUrl) return;
            const $dropdowns = $wrapper.find('select[name="pickup_location"], select[name="dropoff_location"], select[name="origin_id"], select[name="destination_id"]');

            if (!$dropdowns.length) return;

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
                    // Re-lock dropoff_location if we just synced a rental panel.
                    if (serviceType === 'rental' && self._applyDropoffMirror) {
                        self._applyDropoffMirror($wrapper);
                    }
                }
            });
        }

    }

    $(document).ready(function () {
        new UnifiedSearch();
    });

})(jQuery);
