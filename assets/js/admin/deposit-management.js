/**
 * Depozito Yönetimi JavaScript
 * Admin paneli depozito sistemi için JavaScript işlemleri
 */

(function ($) {
    'use strict';

    class DepositManagement {
        constructor() {
            this.init();
        }

        init() {
            this.bindEvents();
        }

        bindEvents() {
            // Kalan tutar ödeme işlemi
            $(document).on('click', '#process-remaining-payment', (e) => this.handleRemainingPayment(e));

            // Ödeme onaylama
            $(document).on('click', '#approve-payment', (e) => this.handleApprovePayment(e));

            // Rezervasyon iptal etme
            $(document).on('click', '#cancel-booking', (e) => this.handleCancelBooking(e));

            // İade işleme
            $(document).on('click', '#process-refund', (e) => this.handleProcessRefund(e));

            // Durum güncelleme

            // Kalan tutar için ödeme linki gönder
            $(document).on('click', '#send-remaining-payment-link', (e) => this.handleSendPaymentLink(e));
        }

        handleRemainingPayment(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const bookingId = $button.data('booking-id');

            if (!this.confirmAction(mhmDepositManagement.strings.confirmRemainingPayment)) {
                return;
            }

            this.showLoading($button);

            $.ajax({
                url: mhmDepositManagement.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhmrentiva_process_remaining_payment',
                    nonce: mhmDepositManagement.nonce,
                    booking_id: bookingId
                },
                success: (response) => {
                    this.hideLoading($button);
                    if (response.success) {
                        this.showMessage('success', response.data.message);
                        this.refreshPage();
                    } else {
                        this.showMessage('error', response.data.message || mhmDepositManagement.strings.error);
                    }
                },
                error: () => {
                    this.hideLoading($button);
                    this.showMessage('error', mhmDepositManagement.strings.error);
                }
            });
        }

        handleSendPaymentLink(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const bookingId = $button.data('booking-id');

            this.showLoading($button);

            $.ajax({
                url: mhmDepositManagement.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhmrentiva_send_remaining_payment_link',
                    nonce: mhmDepositManagement.nonce,
                    booking_id: bookingId
                },
                success: (response) => {
                    this.hideLoading($button);
                    if (response.success) {
                        this.showMessage('success', response.data.message);
                        this.showPaymentLink(response.data.payment_url);
                    } else {
                        this.showMessage('error', response.data.message || mhmDepositManagement.strings.error);
                    }
                },
                error: () => {
                    this.hideLoading($button);
                    this.showMessage('error', mhmDepositManagement.strings.error);
                }
            });
        }

        showPaymentLink(url) {
            const copyLabel = mhmDepositManagement.strings.copyLink;
            const copiedLabel = mhmDepositManagement.strings.linkCopied;

            const $box = $(`
                <div class="notice notice-info">
                    <p>
                        <input type="text" readonly value="${url}" style="width: 70%;" onclick="this.select();" />
                        <button type="button" class="button mhm-copy-payment-link">${copyLabel}</button>
                    </p>
                </div>
            `);

            $box.find('.mhm-copy-payment-link').on('click', () => {
                // navigator.clipboard is undefined outside secure contexts (plain
                // http admin) - guard and fall back, and only confirm on success.
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(url).then(
                        () => this.showMessage('success', copiedLabel),
                        () => {}
                    );
                } else {
                    $box.find('input').trigger('focus').trigger('select');
                    if (document.execCommand('copy')) {
                        this.showMessage('success', copiedLabel);
                    }
                }
            });

            $('.deposit-management-metabox').prepend($box);
        }

        handleApprovePayment(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const bookingId = $button.data('booking-id');

            if (!this.confirmAction(mhmDepositManagement.strings.confirmApprovePayment)) {
                return;
            }

            this.showLoading($button);

            $.ajax({
                url: mhmDepositManagement.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhmrentiva_approve_payment',
                    nonce: mhmDepositManagement.nonce,
                    booking_id: bookingId
                },
                success: (response) => {
                    this.hideLoading($button);
                    if (response.success) {
                        this.showMessage('success', response.data.message);
                        this.refreshPage();
                    } else {
                        this.showMessage('error', response.data.message || mhmDepositManagement.strings.error);
                    }
                },
                error: () => {
                    this.hideLoading($button);
                    this.showMessage('error', mhmDepositManagement.strings.error);
                }
            });
        }

        handleCancelBooking(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const bookingId = $button.data('booking-id');

            if (!this.confirmAction(mhmDepositManagement.strings.confirmCancelBooking)) {
                return;
            }

            this.showLoading($button);

            $.ajax({
                url: mhmDepositManagement.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhmrentiva_deposit_cancel_booking',
                    nonce: mhmDepositManagement.nonce,
                    booking_id: bookingId
                },
                success: (response) => {
                    this.hideLoading($button);
                    if (response.success) {
                        this.showMessage('success', response.data.message);
                        this.refreshPage();
                    } else {
                        this.showMessage('error', response.data.message || mhmDepositManagement.strings.error);
                    }
                },
                error: () => {
                    this.hideLoading($button);
                    this.showMessage('error', mhmDepositManagement.strings.error);
                }
            });
        }

        handleProcessRefund(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const bookingId = $button.data('booking-id');

            if (!this.confirmAction(mhmDepositManagement.strings.confirmRefund)) {
                return;
            }

            this.showLoading($button);

            $.ajax({
                url: mhmDepositManagement.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhmrentiva_deposit_process_refund',
                    nonce: mhmDepositManagement.nonce,
                    booking_id: bookingId
                },
                success: (response) => {
                    this.hideLoading($button);
                    if (response.success) {
                        this.showMessage('success', response.data.message);
                        this.refreshPage();
                    } else {
                        this.showMessage('error', response.data.message || mhmDepositManagement.strings.error);
                    }
                },
                error: () => {
                    this.hideLoading($button);
                    this.showMessage('error', mhmDepositManagement.strings.error);
                }
            });
        }

        confirmAction(message) {
            return confirm(message || mhmDepositManagement.strings.confirmRefund);
        }

        showLoading($button) {
            $button.prop('disabled', true);
            $button.find('.dashicons').removeClass().addClass('dashicons dashicons-update');
            $button.find('.dashicons').css('animation', 'spin 1s linear infinite');
        }

        hideLoading($button) {
            $button.prop('disabled', false);
            $button.find('.dashicons').css('animation', '');
        }

        showMessage(type, message) {
            const dismissText = (mhmDepositManagement.strings && mhmDepositManagement.strings.dismiss) || 'Dismiss this notice';
            const $notice = $(`
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">${dismissText}</span>
                    </button>
                </div>
            `);

            $('.deposit-management-metabox').prepend($notice);

            // Otomatik kapatma
            setTimeout(() => {
                $notice.fadeOut();
            }, 5000);
        }

        refreshPage() {
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        }
    }

    // CSS animasyonu
    const style = document.createElement('style');
    style.textContent = `
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .deposit-action-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .notice {
            margin: 10px 0;
            padding: 10px;
            border-left: 4px solid #00a0d2;
        }
        
        .notice.notice-success {
            border-left-color: #46b450;
            background: #ecf7ed;
        }
        
        .notice.notice-error {
            border-left-color: #dc3232;
            background: #fbeaea;
        }
        
        .notice.notice-warning {
            border-left-color: #ffb900;
            background: #fff8e5;
        }
        
        .notice-dismiss {
            position: absolute;
            top: 0;
            right: 1px;
            border: none;
            margin: 0;
            padding: 9px;
            background: none;
            color: #787c82;
            cursor: pointer;
        }
        
        .notice-dismiss:before {
            background: none;
            color: #787c82;
            content: "\f153";
            display: block;
            font: normal 16px/20px dashicons;
            speak: none;
            height: 20px;
            text-align: center;
            width: 20px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
    `;
    document.head.appendChild(style);

    // Initialize
    $(document).ready(() => {
        new DepositManagement();
    });

})(jQuery);
