/**
 * Booking Meta Box JavaScript
 * 
 * JavaScript functions for booking meta boxes
 */

(function ($) {
    'use strict';

    // Refund button handler
    function initRefundButton() {
        const refundBtn = document.getElementById('mhm-refund-btn');
        if (!refundBtn) return;

        const msgDiv = document.getElementById('mhm-refund-msg');

        refundBtn.addEventListener('click', async function () {
            const confirmText = (window.mhmRentivaAdmin && window.mhmRentivaAdmin.strings && window.mhmRentivaAdmin.strings.confirmRefund) || 'Are you sure you want to refund this payment?';
            if (!confirm(confirmText)) {
                return;
            }

            // Disable button and show loading
            refundBtn.disabled = true;
            msgDiv.style.display = 'block';
            msgDiv.className = 'notice notice-info inline';
            const processingText = (window.mhmRentivaAdmin && window.mhmRentivaAdmin.strings && window.mhmRentivaAdmin.strings.processingRefund) || 'Processing refund...';
            msgDiv.textContent = processingText;

            try {
                const response = await fetch(refundBtn.dataset.rest, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': refundBtn.dataset.nonce
                    },
                    body: JSON.stringify({
                        booking_id: parseInt(refundBtn.dataset.booking, 10)
                    })
                });

                const data = await response.json();

                if (!data.ok) {
                    const errorText = (window.mhmRentivaAdmin && window.mhmRentivaAdmin.strings && window.mhmRentivaAdmin.strings.refundError) || 'Refund error';
                    throw new Error(data.message || errorText);
                }

                msgDiv.className = 'notice notice-success inline';
                const successText = (window.mhmRentivaAdmin && window.mhmRentivaAdmin.strings && window.mhmRentivaAdmin.strings.refundCompleted) || 'Refund completed (or queued). Please refresh the page.';
                msgDiv.textContent = successText;

            } catch (error) {
                msgDiv.className = 'notice notice-error inline';
                msgDiv.textContent = error.message;
                refundBtn.disabled = false;
            }
        });
    }

    // Status change handler
    function initStatusChange() {
        const statusSelect = document.getElementById('mhm_booking_status');
        if (!statusSelect) return;

        statusSelect.addEventListener('change', function () {
            const newStatus = this.value;
            const currentStatus = this.dataset.currentStatus;

            // Status transition validation could be added here
            // Debug log removed
        });
    }

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function () {
        initRefundButton();
        initStatusChange();
    });

})(jQuery);
