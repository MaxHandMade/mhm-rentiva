/**
 * MHM Rentiva PayPal Payment Integration
 * PayPal SDK entegrasyonu ve frontend işlemleri
 */

(function ($) {
    'use strict';

    const MHMPayPal = {

        init: function () {
            this.bindEvents();
            this.initializePayPal();
        },

        bindEvents: function () {
            // PayPal buton container kontrolü
            $(document).on('mhm_booking_form_loaded', this.initializePayPal.bind(this));

            // Payment method değiştiğinde
            $(document).on('change', 'input[name="payment_method"]', this.handlePaymentMethodChange.bind(this));
        },

        initializePayPal: function () {
            const $container = $('#paypal-button-container');

            if ($container.length === 0 || $container.hasClass('paypal-initialized')) {
                return;
            }

            // PayPal SDK yüklü mü kontrol et
            if (typeof paypal === 'undefined') {
                this.loadPayPalSDK();
                return;
            }

            this.renderPayPalButton();
        },

        loadPayPalSDK: function () {
            const $container = $('#paypal-button-container');

            if ($container.length === 0) {
                return;
            }

            $container.addClass('paypal-loading');

            // PayPal SDK'yi dinamik olarak yükle
            const script = document.createElement('script');
            script.src = this.getPayPalSDKUrl();
            script.onload = () => {
                $container.removeClass('paypal-loading');
                this.renderPayPalButton();
            };
            script.onerror = () => {
                $container.removeClass('paypal-loading');
                this.showError(mhmPayPalConfig?.strings?.sdkLoadError || 'PayPal SDK could not be loaded. Please refresh the page.');
            };

            document.head.appendChild(script);
        },

        getPayPalSDKUrl: function () {
            // PayPal SDK URL'ini oluştur
            const clientId = mhmPayPalConfig?.clientId || '';
            const currency = mhmPayPalConfig?.currency || 'USD';
            const testMode = mhmPayPalConfig?.testMode || false;

            let url = 'https://www.paypal.com/sdk/js?';

            if (clientId) {
                url += 'client-id=' + encodeURIComponent(clientId) + '&';
            }

            url += 'currency=' + encodeURIComponent(currency);

            if (testMode) {
                url += '&components=buttons&disable-funding=credit,card';
            } else {
                url += '&components=buttons';
            }

            return url;
        },

        renderPayPalButton: function () {
            const $container = $('#paypal-button-container');

            if ($container.length === 0 || typeof paypal === 'undefined') {
                return;
            }

            // Önceki butonları temizle
            $container.empty();
            $container.addClass('paypal-initialized');

            try {
                paypal.Buttons({
                    createOrder: this.createPayPalOrder.bind(this),
                    onApprove: this.onPayPalApprove.bind(this),
                    onError: this.onPayPalError.bind(this),
                    onCancel: this.onPayPalCancel.bind(this),
                    style: {
                        layout: 'vertical',
                        color: 'blue',
                        shape: 'rect',
                        label: 'paypal'
                    }
                }).render('#paypal-button-container');
            } catch (error) {
                console.error('PayPal Button Render Error:', error);
                this.showError(mhmPayPalConfig?.strings?.buttonRenderError || 'PayPal buttons could not be created.');
            }
        },

        createPayPalOrder: function (data, actions) {
            return new Promise((resolve, reject) => {
                // Booking ID'yi al
                const bookingId = this.getCurrentBookingId();

                if (!bookingId) {
                    this.showError(mhmPayPalConfig?.strings?.noBookingId || 'Booking information not found.');
                    reject(new Error('No booking ID'));
                    return;
                }

                // PayPal order oluşturmak için AJAX çağrısı
                $.ajax({
                    url: mhmPayPalConfig?.ajaxUrl || window.location.origin + '/wp-admin/admin-ajax.php',
                    type: 'POST',
                    data: {
                        action: 'mhm_paypal_create_order',
                        booking_id: bookingId,
                        nonce: mhmPayPalConfig?.nonce || ''
                    },
                    success: (response) => {
                        if (response.success && response.data?.order_id) {
                            resolve(response.data.order_id);
                        } else {
                            const error = response.data?.message || (mhmPayPalConfig?.strings?.orderCreateError || 'PayPal order creation error');
                            this.showError(error);
                            reject(new Error(error));
                        }
                    },
                    error: (xhr, status, error) => {
                        console.error('PayPal Create Order AJAX Error:', error);
                        this.showError(mhmPayPalConfig?.strings?.serverConnectionError || 'Server connection error.');
                        reject(new Error('AJAX Error'));
                    }
                });
            });
        },

        onPayPalApprove: function (data, actions) {
            // Payment approved, start capture process
            this.showLoading(mhmPayPalConfig?.strings?.processingPayment || 'Processing payment...');

            const bookingId = this.getCurrentBookingId();

            $.ajax({
                url: mhmPayPalConfig?.ajaxUrl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'mhm_paypal_capture_payment',
                    order_id: data.orderID,
                    booking_id: bookingId,
                    nonce: mhmPayPalConfig?.nonce || ''
                },
                success: (response) => {
                    this.hideLoading();

                    if (response.success) {
                        this.showSuccess(mhmPayPalConfig?.strings?.paymentSuccess || 'Payment completed successfully!');

                        // Redirect after successful payment
                        setTimeout(() => {
                            window.location.href = mhmPayPalConfig?.successUrl ||
                                (window.location.origin + '/payment-success');
                        }, 2000);
                    } else {
                        const error = response.data?.message || (mhmPayPalConfig?.strings?.paymentProcessError || 'Payment processing error');
                        this.showError(error);
                    }
                },
                error: (xhr, status, error) => {
                    this.hideLoading();
                    console.error('PayPal Capture AJAX Error:', error);
                    this.showError(mhmPayPalConfig?.strings?.paymentProcessFailed || 'An error occurred while processing payment.');
                }
            });
        },

        onPayPalError: function (err) {
            console.error('PayPal Error:', err);
            this.showError(mhmPayPalConfig?.strings?.paymentError || 'An error occurred during PayPal payment.');
        },

        onPayPalCancel: function (data) {
            this.showError(mhmPayPalConfig?.strings?.paymentCancelled || 'PayPal payment was cancelled.');
        },

        handlePaymentMethodChange: function (e) {
            const $radio = $(e.currentTarget);
            const method = $radio.val();

            const $paypalCard = $('#paypal-card');
            const $paypalContainer = $('#paypal-button-container');

            if (method === 'paypal') {
                $paypalCard.show();
                // PayPal butonlarını yeniden başlat
                if (!$paypalContainer.hasClass('paypal-initialized')) {
                    this.initializePayPal();
                }
            } else {
                $paypalCard.hide();
            }
        },

        getCurrentBookingId: function () {
            // URL'den booking ID'yi al
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('booking_id') ||
                $('#booking_id').val() ||
                $('input[name="booking_id"]').val();
        },

        showLoading: function (message) {
            this.hideMessages();

            const $container = $('#paypal-button-container').parent();
            const $loading = $('<div>', {
                class: 'paypal-loading',
                text: message
            });

            $container.append($loading);
        },

        hideLoading: function () {
            $('.paypal-loading').remove();
        },

        showSuccess: function (message) {
            this.hideMessages();

            const $container = $('#paypal-button-container').parent();
            const $success = $('<div>', {
                class: 'paypal-success',
                text: message
            });

            $container.append($success);

            // 5 saniye sonra otomatik gizle
            setTimeout(() => {
                $success.fadeOut(300, function () {
                    $(this).remove();
                });
            }, 5000);
        },

        showError: function (message) {
            this.hideMessages();

            const $container = $('#paypal-button-container').parent();
            const $error = $('<div>', {
                class: 'paypal-error',
                text: message
            });

            $container.append($error);

            // 10 saniye sonra otomatik gizle
            setTimeout(() => {
                $error.fadeOut(300, function () {
                    $(this).remove();
                });
            }, 10000);
        },

        hideMessages: function () {
            $('.paypal-success, .paypal-error, .paypal-loading').remove();
        },

        // Utility functions
        debounce: function (func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        throttle: function (func, limit) {
            let inThrottle;
            return function () {
                const args = arguments;
                const context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        },

        // PayPal SDK configuration
        getSDKConfig: function () {
            return {
                'client-id': mhmPayPalConfig?.clientId || '',
                'currency': mhmPayPalConfig?.currency || 'USD',
                'locale': mhmPayPalConfig?.locale || 'en_US',
                'components': 'buttons',
                'disable-funding': mhmPayPalConfig?.testMode ? 'credit,card' : '',
                'enable-funding': 'paypal',
                'buyer-country': 'US'
            };
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        // PayPal config kontrolü
        if (typeof mhmPayPalConfig === 'undefined') {
            window.mhmPayPalConfig = {
                ajaxUrl: window.location.origin + '/wp-admin/admin-ajax.php',
                nonce: '',
                clientId: '',
                currency: 'USD',
                testMode: true,
                locale: 'en_US',
                successUrl: window.location.origin + '/payment-success'
            };
        }

        MHMPayPal.init();
    });

    // Make it globally available for debugging
    window.MHMPayPal = MHMPayPal;

})(jQuery);
