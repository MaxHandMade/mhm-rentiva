/**
 * Addon Booking JavaScript
 *
 * This file contains JavaScript functions for addon service booking.
 */

(function ($) {
	'use strict';

	/**
	 * Addon Booking Manager
	 */
	const AddonBooking = {

		/**
		 * Initialize addon booking functionality
		 */
		init: function () {
			this.bindEvents();
			this.calculateTotal();
		},

		/**
		 * Bind event listeners
		 */
		bindEvents: function () {
			// Addon checkbox change
			$( document ).on( 'change', '.addon-item input[type="checkbox"]', this.handleAddonToggle );

			// Quantity change
			$( document ).on( 'change', '.addon-quantity input[type="number"]', this.handleQuantityChange );

			// Quantity input
			$( document ).on( 'input', '.addon-quantity input[type="number"]', this.handleQuantityInput );
		},

		/**
		 * Handle addon toggle
		 */
		handleAddonToggle: function () {
			const $checkbox      = $( this );
			const $item          = $checkbox.closest( '.addon-item' );
			const $quantityInput = $item.find( '.addon-quantity input[type="number"]' );

			if ($checkbox.is( ':checked' )) {
				$quantityInput.prop( 'disabled', false ).val( 1 );
				$item.addClass( 'selected' );
			} else {
				$quantityInput.prop( 'disabled', true ).val( 0 );
				$item.removeClass( 'selected' );
			}

			AddonBooking.calculateTotal();
		},

		/**
		 * Handle quantity change
		 */
		handleQuantityChange: function () {
			const $input    = $( this );
			const $item     = $input.closest( '.addon-item' );
			const $checkbox = $item.find( 'input[type="checkbox"]' );
			const quantity  = parseInt( $input.val() ) || 0;

			if (quantity > 0) {
				$checkbox.prop( 'checked', true );
				$item.addClass( 'selected' );
			} else {
				$checkbox.prop( 'checked', false );
				$item.removeClass( 'selected' );
			}

			AddonBooking.calculateTotal();
		},

		/**
		 * Handle quantity input
		 */
		handleQuantityInput: function () {
			const $input = $( this );
			let value    = parseInt( $input.val() ) || 0;

			// Minimum 0, maximum 99
			if (value < 0) {
				value = 0;
			}
			if (value > 99) {
				value = 99;
			}

			$input.val( value );
		},

		/**
		 * Calculate total addon cost
		 */
		calculateTotal: function () {
			let total       = 0;
			const breakdown = [];

			$( '.addon-item' ).each(
				function () {
					const $item          = $( this );
					const $checkbox      = $item.find( 'input[type="checkbox"]' );
					const $quantityInput = $item.find( '.addon-quantity input[type="number"]' );

					if ($checkbox.is( ':checked' )) {
						const quantity  = parseInt( $quantityInput.val() ) || 0;
						const priceText = $item.find( '.addon-price' ).text();
						const price     = parseFloat( priceText.replace( /[^\d.,]/g, '' ).replace( ',', '.' ) ) || 0;
						const itemTotal = price * quantity;

						if (itemTotal > 0) {
							total += itemTotal;
							breakdown.push(
								{
									name: $item.find( '.addon-name' ).text(),
									quantity: quantity,
									price: price,
									total: itemTotal
								}
							);
						}
					}
				}
			);

			this.updateTotalDisplay( total, breakdown );
		},

		/**
		 * Update total display
		 */
		updateTotalDisplay: function (total, breakdown) {
			const $totalSection = $( '.addon-total' );
			const $breakdown    = $totalSection.find( '.total-breakdown' );
			const currency      = (window.mhmAddonBooking && window.mhmAddonBooking.currency) || '$';
			const locale        = (window.mhmAddonBooking && window.mhmAddonBooking.locale) || 'en-US';
			const strings       = (window.mhmAddonBooking && window.mhmAddonBooking.strings) || {};

			// Clear existing breakdown
			$breakdown.empty();

			// Add breakdown items
			breakdown.forEach(
				function (item) {
					const formattedTotal = item.total.toLocaleString(
						locale,
						{
							minimumFractionDigits: 2,
							maximumFractionDigits: 2
						}
					);

					// Simple escape for name
					const safeName = item.name.replace(
						/[&<>"']/g,
						function (m) {
							return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
						}
					);

					$breakdown.append(
						`
						< div class = "total-line" >
						< span > ${safeName} (${item.quantity}x) < / span >
						< span > ${currency}${formattedTotal} < / span >
						< / div >
						`
					);
				}
			);
			// Add final total
			if (breakdown.length > 0) {
				const formattedTotal = total.toLocaleString(
					locale,
					{
						minimumFractionDigits: 2,
						maximumFractionDigits: 2
					}
				);
				$breakdown.append(
					`
					< div class = "total-line final" >
						< span > ${strings.totalAddons || 'Total Add-ons'} < / span >
						< span > ${currency}${formattedTotal} < / span >
					< / div >
					`
				);
			} else {
				$breakdown.append(
					`
					< div class = "total-line" >
						< span > ${strings.noAddonsSelected || 'No add-ons selected'} < / span >
						< span > ${currency}0.00 < / span >
					< / div >
					`
				);
			}

			// Update hidden field if exists
			$( 'input[name="addon_total"]' ).val( total.toFixed( 2 ) );
		}
	};

	/**
	 * Initialize when document is ready
	 */
	$( document ).ready(
		function () {
			if ($( '.addon-booking-meta' ).length > 0) {
				AddonBooking.init();
			}
		}
	);

	/**
	 * Expose to global scope
	 */
	window.AddonBooking = AddonBooking;

})( jQuery );
