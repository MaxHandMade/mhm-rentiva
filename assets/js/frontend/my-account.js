(function ($) {
    'use strict';

    const FavoritesModule = {
        init() {
            this.$page = $('.mhm-rentiva-account-page');
            if (!this.$page.length) {
                return;
            }
            this.bindEvents();
        },

        bindEvents() {
            const self = this;

            $(document).on('click', '#clear-all-favorites', function (event) {
                event.preventDefault();
                self.clearAllFavorites($(this));
            });

            // Favorite toggle handled globally by vehicle-interactions.js
            // .rv-vehicle-card__favorite selector is dead.
        },

        /*
        toggleFavorite($button) {
             // Handled globally
        },
        */

        clearAllFavorites($button) {
            MHMRentivaToast.show(this.getString('error'), { type: 'error' });

            $button.prop('disabled', true).addClass('is-loading');

            this.sendRequest({
                action: 'mhm_rentiva_clear_favorites',
                nonce: window.mhmRentivaAccount?.nonce || ''
            })
                .done((response) => {
                    if (response.success) {
                        this.removeAllFavoriteCards();
                        this.updateFavoriteCounter(0);
                        MHMRentivaToast.show(response.data?.message || this.getString('favoritesCleared'), { type: 'success' });
                    } else {
                        MHMRentivaToast.show(response.data?.message || this.getString('error'), { type: 'error' });
                    }
                })
                .fail(() => {
                    MHMRentivaToast.show(this.getString('error'), { type: 'error' });
                })
                .always(() => {
                    $button.prop('disabled', false).removeClass('is-loading');
                });
        },

        sendRequest(data) {
            const ajaxUrl = window.mhmRentivaAccount?.ajaxUrl || window.mhmRentivaVehiclesGrid?.ajaxUrl;
            if (!ajaxUrl) {
                const deferred = $.Deferred();
                deferred.reject('missing_ajax_url');
                return deferred.promise();
            }

            return $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data,
                dataType: 'json'
            });
        },

        getString(key) {
            return window.mhmRentivaAccount?.i18n?.[key]
                || window.mhmRentivaVehiclesGrid?.i18n?.[key]
                || '';
        },

        handleFavoriteResponse($button, data) {
            if (!data || !data.vehicle_id) {
                return;
            }

            const $card = $('.rv-vehicle-card[data-vehicle-id="' + data.vehicle_id + '"]');
            if (!$card.length) {
                return;
            }

            if (data.action === 'removed') {
                $card.fadeOut(200, () => {
                    $card.remove();
                    this.updateFavoriteCounter(data.favorites_count || 0);
                    if (!$('.rv-vehicle-card').length) {
                        this.showEmptyState();
                    }
                });
            } else {
                $card.find('.rv-vehicle-card__favorite').addClass('is-favorited');
            }

            MHMRentivaToast.show(data.message || this.getString('success'), { type: 'success' });
        },

        removeAllFavoriteCards() {
            $('.rv-vehicle-card').fadeOut(200, function () {
                $(this).remove();
            });
            this.showEmptyState();
        },

        showEmptyState() {
            const $container = $('.account-section');
            if ($container.length && !$container.find('.rv-vehicle-card').length) {
                const emptyHtml = `
                    <div class="empty-state">
                        <div class="empty-icon">
                            ${window.mhmRentivaVehiclesList?.icons?.heart || '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>'}
                        </div>
                        <h3>${this.getString('no_favorites')}</h3>
                        <p>${this.getString('login_required')}</p>
                    </div>
                `;
                $container.html(emptyHtml);
            }
        },

        updateFavoriteCounter(count) {
            const text = count === 1 ? this.getString('favorites_count_single') : this.getString('favorites_count_plural');
            $('.view-all-link').text(count + ' ' + text);
        },

    };

    FavoritesModule.getString = function (key) {
        return window.mhmRentivaAccount?.i18n?.[key] || window.mhmRentivaVehiclesGrid?.i18n?.[key] || '';
    };

    $(document).ready(function () {
        FavoritesModule.init();
    });

})(jQuery);
/**
 * My Account - JavaScript
 * 
 * WordPress Login based customer account page functions
 * 
 * @since 4.0.0
 */

(function ($) {
    'use strict';

    class MyAccount {
        constructor() {
            this.config = window.mhmRentivaAccount || {};
            this.init();
        }

        init() {
            this.bindEvents();
            this.initAccountForm();
            this.initPasswordToggles();
        }

        bindEvents() {
            // Account update form
            $('#mhm-account-details-form').on('submit', (e) => this.handleAccountUpdate(e));

            // Booking cancellation
            $(document).on('click', '.cancel-booking', (e) => this.handleCancelBooking(e));

            // Receipt upload
            $(document).on('change', '.mhm-upload-receipt', (e) => this.handleReceiptUpload(e));

            // Receipt remove
            $(document).on('click', '.remove-receipt-btn', (e) => this.handleReceiptRemove(e));
        }



        /**
         * Upload receipt via AJAX
         */
        handleReceiptUpload(e) {
            const input = e.currentTarget;
            const bookingId = $(input).data('booking-id');
            const file = input.files[0];
            if (!file || !bookingId) return;

            const formData = new FormData();
            formData.append('action', 'mhm_rentiva_upload_receipt');
            formData.append('nonce', this.config.uploadNonce);
            formData.append('booking_id', bookingId);
            formData.append('receipt', file);

            const $label = $(input).closest('label');
            const originalText = $label.text();
            $label.addClass('is-loading');
            $label.contents().filter(function () { return this.nodeType === 3; }).first().replaceWith(this.config.i18n.uploading + ' ');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: (res) => {
                    if (res && res.success) {
                        MHMRentivaToast.show(this.config.i18n.upload_success, { type: 'success' });
                        setTimeout(() => window.location.reload(), 1200);
                    } else {
                        MHMRentivaToast.show((res && res.data && res.data.message) || this.config.i18n.upload_error, { type: 'error' });
                    }
                },
                error: () => {
                    MHMRentivaToast.show(this.config.i18n.upload_error, { type: 'error' });
                },
                complete: () => {
                    $label.removeClass('is-loading');
                    $label.contents().filter(function () { return this.nodeType === 3; }).first().replaceWith(originalText + ' ');
                    $(input).val('');
                }
            });
        }

        /**
         * Remove receipt
         */
        handleReceiptRemove(e) {
            e.preventDefault();

            if (!confirm(this.config.i18n.confirm_remove_receipt || 'Are you sure you want to remove this receipt?')) {
                return;
            }

            const $btn = $(e.currentTarget);
            const bookingId = $btn.data('booking-id');
            const $wrapper = $btn.closest('.receipt-wrapper');

            $btn.prop('disabled', true).addClass('is-loading');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhm_rentiva_remove_receipt',
                    nonce: this.config.uploadNonce,
                    booking_id: bookingId
                },
                success: (res) => {
                    if (res && res.success) {
                        MHMRentivaToast.show(res.data.message || 'Receipt removed successfully.', { type: 'success' });
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        MHMRentivaToast.show((res && res.data && res.data.message) || 'Failed to remove receipt.', { type: 'error' });
                        $btn.prop('disabled', false).removeClass('is-loading');
                    }
                },
                error: () => {
                    MHMRentivaToast.show('An error occurred.', { type: 'error' });
                    $btn.prop('disabled', false).removeClass('is-loading');
                }
            });
        }



        /**
         * Booking cancellation
         */
        handleCancelBooking(e) {
            e.preventDefault();

            if (!confirm(this.config.i18n.confirm)) {
                return;
            }

            const $btn = $(e.currentTarget);
            const bookingId = $btn.data('booking-id');

            $btn.prop('disabled', true).text(this.config.i18n.loading);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhm_rentiva_cancel_booking',
                    nonce: this.config.nonce,
                    booking_id: bookingId,
                },
                success: (response) => {
                    if (response.success) {
                        MHMRentivaToast.show(response.data.message || this.config.i18n.success, { type: 'success' });
                        // Refresh page
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        MHMRentivaToast.show(response.data.message || this.config.i18n.error, { type: 'error' });
                        $btn.prop('disabled', false).text(this.config.i18n.cancel_booking);
                    }
                },
                error: () => {
                    MHMRentivaToast.show(this.config.i18n.error, { type: 'error' });
                    $btn.prop('disabled', false).text(this.config.i18n.cancel_booking);
                }
            });
        }

        /**
         * Initialize favorites system
         */
        // Local favorites handler removed in favor of global interaction

        /**
         * Form validation
         */
        initAccountForm() {
            this.setupPasswordValidation('#mhm-account-details-form', '#new_password', '#confirm_password');
            this.setupPasswordValidation('#mhm-rentiva-register-form', '#rv_password', '#rv_password_confirm');

            // Reset button for account form
            $('#mhm-account-details-form').find('button[type="reset"]').on('click', (e) => {
                e.preventDefault();
                if (confirm(this.config.i18n.cancel_changes_confirm)) {
                    $('#mhm-account-details-form')[0].reset();
                }
            });
        }

        /**
         * Initialize password toggles wrapper
         */
        initPasswordToggles() {
            // Wait for WP/Woo scripts to inject buttons
            setTimeout(() => {
                $('input[type="password"]').each((i, el) => {
                    const $input = $(el);
                    const $wrapper = $input.closest('.form-field');

                    // Find the toggle button injected by WP or WooCommerce
                    // Usually it's immediately after the input or inside the wrapper
                    const $toggle = $input.next('.show-password-input, .wp-hide-pw')
                        || $wrapper.find('.show-password-input, .wp-hide-pw, .woocommerce-password-toggle');

                    if ($toggle.length) {
                        // Create a specific container for input + toggle
                        if (!$input.parent().hasClass('password-input-wrapper')) {
                            // Wrap input first
                            $input.wrap('<div class="password-input-wrapper"></div>');
                            // Move toggle inside wrapper
                            $input.parent().append($toggle);
                        }
                    }
                });
            }, 500); // Small delay to ensure other scripts ran
        }

        /**
         * Setup password validation for a form
         */
        setupPasswordValidation(formSelector, passSelector, confirmSelector) {
            const $form = $(formSelector);
            if ($form.length === 0) return;

            const $pass = $(passSelector);
            const $confirm = $(confirmSelector);

            const validate = () => {
                const pass = $pass.val();
                const confirm = $confirm.val();

                if (pass !== '' && confirm !== '' && pass !== confirm) {
                    $confirm[0].setCustomValidity(window.mhmRentivaMyAccount?.strings?.passwords_do_not_match || 'Passwords do not match');
                } else {
                    $confirm[0].setCustomValidity('');
                }
            };

            $confirm.on('blur input', validate);
            $pass.on('blur input', validate);
        }

    }

    // Initialize when DOM is ready
    $(document).ready(function () {
        new MyAccount();
    });

})(jQuery);

