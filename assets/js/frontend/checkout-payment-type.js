/**
 * Checkout deposit/full payment-type selector.
 *
 * Extracted from the inline block that WooCommerceBridge::display_payment_type_field()
 * echoed inside the order-review area. That area is re-rendered by WooCommerce on every
 * `update_checkout`, so the change handler is bound by delegation (survives re-renders)
 * and the selected-state visual is re-applied on `updated_checkout` — mirroring how the
 * inline script re-ran each time it was re-injected. The nonce + ajax fallback come from
 * the localized mhmCheckoutPaymentType object.
 */
jQuery(document).ready(function($) {
	var cfg = window.mhmCheckoutPaymentType || {};

	function ajaxUrl() {
		return (window.wc_checkout_params && wc_checkout_params.ajax_url) || cfg.ajaxUrl || '';
	}

	function updateSelectedState() {
		$('.mhm-payment-type-option').removeClass('selected');
		$('.mhm-payment-type-option:has(input[type="radio"]:checked)').addClass('selected');
	}

	// Initial visual state + one-time sync of the pre-selected type with the cart session.
	updateSelectedState();

	var $initialSelected = $('input[name="mhmrentiva_booking_payment_type"]:checked');
	if ($initialSelected.length) {
		$.post(ajaxUrl(), {
			action: 'mhmrentiva_update_booking_payment_type',
			payment_type: $initialSelected.val(),
			nonce: cfg.nonce
		});
	}

	// WooCommerce replaces the order-review markup on refresh; re-apply the visual state.
	$(document.body).on('updated_checkout', updateSelectedState);

	// Delegated so it keeps working after the order review is re-rendered.
	$(document).on('change', 'input[name="mhmrentiva_booking_payment_type"]', function() {
		var paymentType = $(this).val();

		// Update selected state immediately for better UX
		updateSelectedState();

		// Update cart price via AJAX
		$.ajax({
			url: ajaxUrl(),
			type: 'POST',
			data: {
				action: 'mhmrentiva_update_booking_payment_type',
				payment_type: paymentType,
				nonce: cfg.nonce
			},
			success: function(response) {
				if (response.success) {
					// Trigger cart update
					$('body').trigger('update_checkout');
				} else {
					console.error('MHM Rentiva: Payment type update failed', response);
				}
			},
			error: function(xhr, status, error) {
				console.error('MHM Rentiva: AJAX error', error);
			}
		});
	});
});
