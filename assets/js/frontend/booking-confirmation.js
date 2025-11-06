/**
 * Booking Confirmation JavaScript
 * 
 * JavaScript functions for booking confirmation page
 * Print, download, share and other operations
 */

(function ($) {
    'use strict';

    class BookingConfirmation {
        constructor() {
            this.container = $('.rv-booking-confirmation');
            this.printBtn = this.container.find('.rv-print-btn');
            this.downloadBtn = this.container.find('.rv-download-btn');
            this.shareBtn = this.container.find('.rv-share-btn');
            this.backBtn = this.container.find('.rv-back-btn');

            this.init();
        }

        init() {
            if (this.container.length === 0) {
                return;
            }


            // Event listeners
            this.bindEvents();
        }

        bindEvents() {
            // Print functionality
            this.printBtn.on('click', (e) => {
                e.preventDefault();
                this.printConfirmation();
            });

            // Download functionality
            this.downloadBtn.on('click', (e) => {
                e.preventDefault();
                this.downloadConfirmation();
            });

            // Share functionality
            this.shareBtn.on('click', (e) => {
                e.preventDefault();
                this.shareConfirmation();
            });

            // Back button
            this.backBtn.on('click', (e) => {
                e.preventDefault();
                this.goBack();
            });
        }

        /**
         * Print confirmation page
         */
        printConfirmation() {
            try {
                // Hide action buttons during print
                this.container.find('.rv-confirmation-actions').addClass('rv-print-hidden');

                // Print the page
                window.print();

                // Show action buttons again after print
                setTimeout(() => {
                    this.container.find('.rv-confirmation-actions').removeClass('rv-print-hidden');
                }, 1000);

            } catch (error) {
                console.error('[MHM Booking Confirmation] Print error:', error);
                this.showError(mhmRentivaBookingConfirmation?.i18n?.print_error || 'An error occurred during printing.');
            }
        }

        /**
         * Download confirmation as PDF
         */
        downloadConfirmation() {
            try {
                const bookingId = this.container.data('booking-id');
                if (!bookingId) {
                    this.showError(mhmRentivaBookingConfirmation?.i18n?.booking_id_not_found || 'Booking ID not found.');
                    return;
                }

                // Show loading
                this.downloadBtn.prop('disabled', true).text(mhmRentivaBookingConfirmation?.i18n?.downloading || 'Downloading...');

                // Create download URL
                const downloadUrl = ajaxurl + '?action=mhm_rentiva_download_booking&booking_id=' + bookingId + '&nonce=' + mhmRentivaBookingConfirmation.nonce;

                // Create temporary link and trigger download
                const link = document.createElement('a');
                link.href = downloadUrl;
                link.download = 'booking-' + bookingId + '.pdf';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                // Reset button
                setTimeout(() => {
                    this.downloadBtn.prop('disabled', false).text(mhmRentivaBookingConfirmation?.i18n?.download || 'Download');
                }, 2000);

            } catch (error) {
                console.error('[MHM Booking Confirmation] Download error:', error);
                this.showError(mhmRentivaBookingConfirmation?.i18n?.download_error || 'An error occurred during download.');
                this.downloadBtn.prop('disabled', false).text('Download');
            }
        }

        /**
         * Share confirmation
         */
        shareConfirmation() {
            try {
                const bookingId = this.container.data('booking-id');
                const shareUrl = window.location.href;

                if (navigator.share) {
                    // Use native share API if available
                    navigator.share({
                        title: (mhmRentivaBookingConfirmation?.i18n?.booking_confirmation || 'Booking Confirmation') + ' #' + bookingId,
                        text: mhmRentivaBookingConfirmation?.i18n?.view_booking_details || 'View your booking details.',
                        url: shareUrl
                    }).then(() => {
                    }).catch((error) => {
                    });
                } else {
                    // Fallback: Copy to clipboard
                    this.copyToClipboard(shareUrl);
                }
            } catch (error) {
                console.error('[MHM Booking Confirmation] Share error:', error);
                this.showError(mhmRentivaBookingConfirmation?.i18n?.share_error || 'An error occurred during sharing.');
            }
        }

        /**
         * Copy text to clipboard
         */
        copyToClipboard(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(() => {
                    this.showSuccess(mhmRentivaBookingConfirmation?.i18n?.link_copied || 'Link copied to clipboard!');
                }).catch(() => {
                    this.fallbackCopyToClipboard(text);
                });
            } else {
                this.fallbackCopyToClipboard(text);
            }
        }

        /**
         * Fallback copy to clipboard method
         */
        fallbackCopyToClipboard(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                document.execCommand('copy');
                this.showSuccess('Link copied to clipboard!');
            } catch (err) {
                this.showError(mhmRentivaBookingConfirmation?.i18n?.link_copy_failed || 'Link could not be copied.');
            }

            document.body.removeChild(textArea);
        }

        /**
         * Go back to previous page
         */
        goBack() {
            if (document.referrer) {
                window.history.back();
            } else {
                // Fallback to dashboard or home
                window.location.href = mhmRentivaBookingConfirmation.dashboardUrl || '/';
            }
        }

        /**
         * Show success message
         */
        showSuccess(message) {
            this.showMessage(message, 'success');
        }

        /**
         * Show error message
         */
        showError(message) {
            this.showMessage(message, 'error');
        }

        /**
         * Show message
         */
        showMessage(message, type) {
            const messageClass = type === 'success' ? 'rv-success-message' : 'rv-error-message';
            const messageEl = $('<div class="' + messageClass + '">' + message + '</div>');

            this.container.prepend(messageEl);

            // Auto remove after 3 seconds
            setTimeout(() => {
                messageEl.fadeOut(300, () => {
                    messageEl.remove();
                });
            }, 3000);
        }
    }

    // Initialize when document is ready
    $(document).ready(function () {
        // Check if we're on a booking confirmation page
        if ($('.rv-booking-confirmation').length > 0) {
            new BookingConfirmation();
        }
    });

})(jQuery);
