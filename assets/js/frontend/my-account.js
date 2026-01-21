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

            $(document).on('click', '.rv-vehicle-card__favorite', function (event) {
                event.preventDefault();
                event.stopPropagation();
                self.toggleFavorite($(this));
            });
        },

        toggleFavorite($button) {
            const vehicleId = $button.data('vehicle-id');
            if (!vehicleId) {
                return;
            }

            const isFavorited = $button.hasClass('is-favorited');
            const action = isFavorited ? 'mhm_rentiva_remove_favorite' : 'mhm_rentiva_add_favorite';

            this.sendRequest({
                action,
                vehicle_id: vehicleId,
                nonce: window.mhmRentivaAccount?.nonce || ''
            })
                .done((response) => {
                    if (response.success) {
                        this.handleFavoriteResponse($button, response.data);
                    } else {
                        this.showNotification(response.data?.message || this.getString('error'), 'error');
                    }
                })
                .fail((jqXHR) => {
                    this.showNotification(this.getString('error'), 'error');
                });
        },

        clearAllFavorites($button) {
            if (!window.mhmRentivaAccount?.ajaxUrl) {
                this.showNotification(this.getString('error'), 'error');
                return;
            }

            $button.prop('disabled', true).addClass('is-loading');

            this.sendRequest({
                action: 'mhm_rentiva_clear_favorites',
                nonce: window.mhmRentivaAccount?.nonce || ''
            })
                .done((response) => {
                    if (response.success) {
                        this.removeAllFavoriteCards();
                        this.updateFavoriteCounter(0);
                        this.showNotification(response.data?.message || this.getString('favoritesCleared'), 'success');
                    } else {
                        this.showNotification(response.data?.message || this.getString('error'), 'error');
                    }
                })
                .fail(() => {
                    this.showNotification(this.getString('error'), 'error');
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

            this.showNotification(data.message || this.getString('success'), 'success');
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
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                            </svg>
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

        showNotification(message, type) {
            type = type || 'info';
            const $notification = $('<div class="rv-notification rv-notification--' + type + '">' + message + '</div>');
            $('body').append($notification);

            setTimeout(function () {
                $notification.addClass('rv-notification--show');
            }, 100);

            setTimeout(function () {
                $notification.removeClass('rv-notification--show');
                setTimeout(function () {
                    $notification.remove();
                }, 300);
            }, 3000);
        }
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
            this.initFavorites();
            this.initAccountForm();
            this.initPasswordToggles();
        }

        bindEvents() {
            // Account update form
            $('#mhm-account-details-form').on('submit', (e) => this.handleAccountUpdate(e));

            // Favorite toggle buttons
            $(document).on('click', '.favorite-toggle', (e) => this.handleFavoriteToggle(e));

            // Booking cancellation
            $(document).on('click', '.cancel-booking', (e) => this.handleCancelBooking(e));

            // Receipt upload
            $(document).on('change', '.mhm-upload-receipt', (e) => this.handleReceiptUpload(e));

            // Receipt remove
            $(document).on('click', '.remove-receipt-btn', (e) => this.handleReceiptRemove(e));
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

            if (!bookingId) {
                this.showMessage(this.config.i18n.error, 'error');
                return;
            }

            $btn.prop('disabled', true).addClass('is-loading');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhm_rentiva_update_account',
                    nonce: this.config.nonce,
                    display_name: formData.get('display_name'),
                    first_name: formData.get('first_name'),
                    last_name: formData.get('last_name'),
                    phone: formData.get('phone'),
                    address: formData.get('address'),
                },
                success: (response) => {
                    if (response.success) {
                        this.showMessage(response.data.message || this.config.i18n.savedSuccessfully, 'success');
                    } else {
                        this.showMessage(response.data.message || this.config.i18n.error, 'error');
                    }
                },
                error: () => {
                    this.showMessage(this.config.i18n.error, 'error');
                },
                complete: () => {
                    $submitBtn.prop('disabled', false).text(this.config.i18n.save_changes);
                }
            });
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
                        this.showMessage(this.config.i18n.upload_success, 'success');
                        setTimeout(() => window.location.reload(), 1200);
                    } else {
                        this.showMessage((res && res.data && res.data.message) || this.config.i18n.upload_error, 'error');
                    }
                },
                error: () => {
                    this.showMessage(this.config.i18n.upload_error, 'error');
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
                        this.showMessage(res.data.message || 'Receipt removed successfully.', 'success');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        this.showMessage((res && res.data && res.data.message) || 'Failed to remove receipt.', 'error');
                        $btn.prop('disabled', false).removeClass('is-loading');
                    }
                },
                error: () => {
                    this.showMessage('An error occurred.', 'error');
                    $btn.prop('disabled', false).removeClass('is-loading');
                }
            });
        }

        /**
         * Favorite toggle
         */
        handleFavoriteToggle(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const vehicleId = $btn.data('vehicle-id');
            const isFavorite = $btn.hasClass('is-favorite');
            const action = isFavorite ? 'mhm_rentiva_remove_favorite' : 'mhm_rentiva_add_favorite';

            $btn.prop('disabled', true);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: action,
                    nonce: this.config.nonce,
                    vehicle_id: vehicleId,
                },
                success: (response) => {
                    if (response.success) {
                        if (isFavorite) {
                            $btn.removeClass('is-favorite');
                            $btn.find('.icon').text('🤍');
                            this.showMessage(this.config.i18n.removedFromFavorites, 'success');
                        } else {
                            $btn.addClass('is-favorite');
                            $btn.find('.icon').text('❤️');
                            this.showMessage(this.config.i18n.addedToFavorites, 'success');
                        }
                    } else {
                        this.showMessage(response.data.message || this.config.i18n.error, 'error');
                    }
                },
                error: () => {
                    this.showMessage(this.config.i18n.error, 'error');
                },
                complete: () => {
                    $btn.prop('disabled', false);
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
                        this.showMessage(response.data.message || this.config.i18n.success, 'success');
                        // Refresh page
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        this.showMessage(response.data.message || this.config.i18n.error, 'error');
                        $btn.prop('disabled', false).text(this.config.i18n.cancel_booking);
                    }
                },
                error: () => {
                    this.showMessage(this.config.i18n.error, 'error');
                    $btn.prop('disabled', false).text(this.config.i18n.cancel_booking);
                }
            });
        }

        /**
         * Initialize favorites system
         */
        initFavorites() {
            // Add to favorites buttons
            $('.add-to-favorites').on('click', function (e) {
                e.preventDefault();
                // Action to be performed
            });
        }

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

        /**
         * Show message
         */
        showMessage(message, type = 'success') {
            const $messages = $('.account-messages');

            if ($messages.length === 0) {
                return;
            }

            $messages
                .removeClass('success error')
                .addClass(type)
                .html(`<p>${message}</p>`)
                .slideDown(300);

            // Hide after 5 seconds
            setTimeout(() => {
                $messages.slideUp(300);
            }, 5000);
        }
    }

    // Initialize when DOM is ready
    $(document).ready(function () {
        new MyAccount();
    });

})(jQuery);

