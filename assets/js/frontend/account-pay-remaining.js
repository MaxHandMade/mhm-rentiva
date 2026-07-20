/**
 * Account booking detail: "Pay Remaining Amount" button.
 *
 * Extracted from the inline block at the end of templates/account/booking-detail.php.
 * The booking id + nonce ride on the button's data attributes; the ajax URL and the
 * three button labels come from the localized mhmRentivaPayRemaining object.
 */
(function () {
	function init() {
		var btn = document.querySelector('.rv-pay-remaining-btn');
		if (!btn) {
			return;
		}

		var cfg  = window.mhmRentivaPayRemaining || {};
		var i18n = cfg.i18n || {};

		btn.addEventListener('click', function () {
			var bookingId   = this.dataset.bookingId;
			var nonce       = this.dataset.nonce;
			btn.disabled    = true;
			btn.textContent = i18n.processing || '';

			var formData = new FormData();
			formData.append('action', 'mhm_rentiva_pay_remaining');
			formData.append('booking_id', bookingId);
			formData.append('nonce', nonce);

			fetch(cfg.ajaxUrl || '', {
				method: 'POST',
				credentials: 'same-origin',
				body: formData
			})
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (data.success && data.data && data.data.payment_url) {
						window.location.href = data.data.payment_url;
					} else {
						var msg = (data.data && data.data.message)
							? data.data.message
							: (i18n.error || '');
						alert(msg);
						btn.disabled    = false;
						btn.textContent = i18n.payRemaining || '';
					}
				})
				.catch(function () {
					btn.disabled    = false;
					btn.textContent = i18n.payRemaining || '';
				});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
