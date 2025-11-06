/**
 * My Account - JavaScript
 * 
 * WordPress Login bazlı müşteri hesap sayfası fonksiyonları
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
        }

        bindEvents() {
            // Hesap güncelleme formu
            $('#mhm-account-details-form').on('submit', (e) => this.handleAccountUpdate(e));

            // Favori toggle butonları
            $(document).on('click', '.favorite-toggle', (e) => this.handleFavoriteToggle(e));

            // Rezervasyon iptal
            $(document).on('click', '.cancel-booking', (e) => this.handleCancelBooking(e));

            // Receipt upload
            $(document).on('change', '.mhm-upload-receipt', (e) => this.handleReceiptUpload(e));
        }

        /**
         * Hesap bilgilerini güncelle
         */
        handleAccountUpdate(e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const $submitBtn = $form.find('button[type="submit"]');
            const formData = new FormData($form[0]);

            // Loading state
            $submitBtn.prop('disabled', true).text(this.config.i18n.loading);

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
                    $submitBtn.prop('disabled', false).text(window.mhmRentivaMyAccount?.strings?.save_changes || 'Save Changes');
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
         * Favori toggle
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
         * Rezervasyon iptal
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
                        // Sayfayı yenile
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        this.showMessage(response.data.message || this.config.i18n.error, 'error');
                        $btn.prop('disabled', false).text(window.mhmRentivaMyAccount?.strings?.cancel_booking || 'Cancel Booking');
                    }
                },
                error: () => {
                    this.showMessage(this.config.i18n.error, 'error');
                    $btn.prop('disabled', false).text('Cancel Booking');
                }
            });
        }

        /**
         * Favori sistemini başlat
         */
        initFavorites() {
            // Favorilere ekleme butonları
            $('.add-to-favorites').on('click', function (e) {
                e.preventDefault();
                // İşlem yapılacak
            });
        }

        /**
         * Form validasyonu
         */
        initAccountForm() {
            const $form = $('#mhm-account-details-form');

            if ($form.length === 0) {
                return;
            }

            // Şifre eşleşme kontrolü
            const $newPassword = $('#new_password');
            const $confirmPassword = $('#confirm_password');

            $confirmPassword.on('blur', function () {
                const newPwd = $newPassword.val();
                const confirmPwd = $confirmPassword.val();

                if (newPwd !== '' && confirmPwd !== '' && newPwd !== confirmPwd) {
                    $confirmPassword[0].setCustomValidity(window.mhmRentivaMyAccount?.strings?.passwords_do_not_match || 'Passwords do not match');
                } else {
                    $confirmPassword[0].setCustomValidity('');
                }
            });

            // Reset butonu
            $form.find('button[type="reset"]').on('click', function (e) {
                e.preventDefault();
                if (confirm(window.mhmRentivaMyAccount?.strings?.cancel_changes_confirm || 'Are you sure you want to cancel changes?')) {
                    $form[0].reset();
                }
            });
        }

        /**
         * Mesaj göster
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

            // 5 saniye sonra gizle
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

