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
            $(document).on('click', '#process-remaining-payment', this.handleRemainingPayment.bind(this));

            // Ödeme onaylama
            $(document).on('click', '#approve-payment', this.handleApprovePayment.bind(this));

            // Rezervasyon iptal etme
            $(document).on('click', '#cancel-booking', this.handleCancelBooking.bind(this));

            // İade işleme
            $(document).on('click', '#process-refund', this.handleProcessRefund.bind(this));

            // Durum güncelleme
            $(document).on('click', '#update-status', this.handleUpdateStatus.bind(this));
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
                    action: 'mhm_process_remaining_payment',
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
                    action: 'mhm_approve_payment',
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
                    action: 'mhm_cancel_booking',
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
                    action: 'mhm_process_refund',
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

        handleUpdateStatus(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const bookingId = $button.data('booking-id');

            this.showLoading($button);

            $.ajax({
                url: mhmDepositManagement.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhm_update_booking_status',
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
