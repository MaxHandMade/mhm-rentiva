/**
 * Manuel Rezervasyon Meta Box JavaScript
 */

(function ($) {
	'use strict';

	const ManualBooking = {
		/**
		 * Format an amount with the canonical currency symbol, placement and
		 * separators — all of which PHP sources from WooCommerce. Every price in
		 * this panel used to be printed as `${amount} ${symbol}`, which pinned the
		 * symbol to the right no matter what woocommerce_currency_pos said, and
		 * took separators from the browser locale rather than from WooCommerce.
		 */
		formatMoney: function (amount) {
			const cfg      = ( typeof mhmManualBooking !== 'undefined' ) ? mhmManualBooking : {};
			const symbol   = cfg.currency || '';
			const fmt      = cfg.currencyFormat || {};
			const position = fmt.position || 'right_space';
			const decimals = ( fmt.decimals === undefined ) ? 2 : Number( fmt.decimals );
			const decSep   = fmt.decimalSeparator || ',';
			const thouSep  = fmt.thousandSeparator || '.';

			const fixed = Number( amount || 0 ).toFixed( decimals );
			const parts = fixed.split( '.' );
			const int   = parts[0].replace( /\B(?=(\d{3})+(?!\d))/g, thouSep );
			const value = decimals > 0 ? int + decSep + parts[1] : int;

			switch ( position ) {
				case 'left':        return symbol + value;
				case 'left_space':  return symbol + ' ' + value;
				case 'right':       return value + symbol;
				case 'right_space':
				default:            return value + ' ' + symbol;
			}
		},

		init: function () {
			this.bindEvents();
			this.setupDateDefaults();
			this.calculateAddonTotal();
		},

		bindEvents: function () {
			// Fiyat hesaplama butonu
			$( document ).on( 'click', '#mhm-calculate-price', this.calculatePrice );

			// Rezervasyon oluşturma butonu
			$( document ).on( 'click', '#mhm-create-booking', this.createBooking );

			// Araç seçimi değiştiğinde
			$( document ).on( 'change', '#mhmrentiva_manual_vehicle_id', this.onVehicleChange );

			// Tarih değişikliklerinde otomatik hesaplama
			// Times as well as dates: the pickup/return time changes the rental
			// day count, so a time edited after the price was calculated left
			// a total on screen that no longer matched the form.
			$( document ).on( 'change', '#mhmrentiva_manual_pickup_date, #mhmrentiva_manual_dropoff_date, #mhmrentiva_manual_pickup_time, #mhmrentiva_manual_dropoff_time', this.onDateChange );

			// Ödeme türü değiştiğinde
			$( document ).on( 'change', '#mhmrentiva_manual_payment_type', this.onPaymentTypeChange );

			// Ek hizmetler seçimi değiştiğinde
			$( document ).on( 'change', '.mhm-addon-checkbox, .mhm-addon-qty', this.onAddonChange );

			// Müşteri seçimi değiştiğinde
			$( document ).on( 'change', '#mhmrentiva_manual_customer_id', this.onCustomerChange );

			// The new customer's state control follows the chosen country.
			$( document ).on( 'change', '#mhmrentiva_new_customer_country', function () {
				ManualBooking.renderStateField( $( this ).val() );
			} );
		},

		setupDateDefaults: function () {
			// Bugünün tarihini alış tarihi olarak ayarla
			// "Today" is the site's today (localized), not the browser's UTC date.
			const today = ManualBooking.siteToday();
			$( '#mhmrentiva_manual_pickup_date' ).val( today );

			// Yarının tarihini teslim tarihi olarak ayarla
			$( '#mhmrentiva_manual_dropoff_date' ).val( ManualBooking.addDays( today, 1 ) );
		},

		// Calendar arithmetic on a Y-m-d string (UTC, so no DST or local-zone drift).
		addDays: function ( iso, n ) {
			const parts = iso.split( '-' ).map( Number );
			return new Date( Date.UTC( parts[0], parts[1] - 1, parts[2] + n ) ).toISOString().slice( 0, 10 );
		},

		siteToday: function () {
			if ( typeof mhmManualBooking !== 'undefined' && mhmManualBooking.siteToday ) {
				return mhmManualBooking.siteToday;
			}
			const now = new Date();
			return now.getFullYear() + '-' + String( now.getMonth() + 1 ).padStart( 2, '0' ) + '-' + String( now.getDate() ).padStart( 2, '0' );
		},

		onVehicleChange: function () {
			const vehicleId = $( this ).val();

			// Whatever is on screen was calculated for the vehicle that was
			// selected a moment ago, so it cannot survive this change --
			// otherwise vehicle A's total sits under vehicle B's name with a
			// live "Create Booking" button beneath it.
			// displayPriceCalculation() reveals the panel again as soon as a
			// fresh price comes back.
			ManualBooking.invalidatePriceCalculation();

			if (vehicleId) {
				const $option = $( this ).find( 'option:selected' );
				const price   = $option.data( 'price' );

				// Araç bilgilerini göster
				ManualBooking.showVehicleInfo( $option.text(), price );

				// Recalculate immediately when the dates are already filled
				// in, so changing vehicle does not leave an empty panel.
				if ($( '#mhmrentiva_manual_pickup_date' ).val() && $( '#mhmrentiva_manual_dropoff_date' ).val()) {
					ManualBooking.calculatePrice();
				}
			}
		},

		onDateChange: function () {
			const pickupDate  = $( '#mhmrentiva_manual_pickup_date' ).val();
			const dropoffDate = $( '#mhmrentiva_manual_dropoff_date' ).val();

			// A cleared date leaves nothing to price: the breakdown on screen (or
			// one still on its way) belongs to dates that are no longer there.
			if ( ! pickupDate || ! dropoffDate ) {
				ManualBooking.invalidatePriceCalculation();
				return;
			}

			if (pickupDate && dropoffDate) {
				// Tarih doğrulama
				if (new Date( dropoffDate ) <= new Date( pickupDate )) {
					// Drop the old breakdown too: it was calculated for the
					// previous dates and must not stay on screen beside
					// invalid new ones.
					ManualBooking.invalidatePriceCalculation();
					ManualBooking.showMessage( 'error', mhmManualBooking.text.dropoffAfterPickup || 'Dropoff date must be after pickup date.' );
					return;
				}

				// Otomatik fiyat hesaplama (eğer araç seçilmişse)
				if ($( '#mhmrentiva_manual_vehicle_id' ).val()) {
					ManualBooking.calculatePrice();
				}
			}
		},

		onPaymentTypeChange: function () {
			// Ödeme türü değiştiğinde fiyat hesaplama alanını güncelle
			if ($( '#mhmrentiva_manual_vehicle_id' ).val()) {
				ManualBooking.calculatePrice();
			}
		},

		onAddonChange: function () {
			// Ek hizmetler toplamını hesapla
			ManualBooking.calculateAddonTotal();

			// Eğer araç seçilmişse genel fiyat hesaplamasını da güncelle
			if ($( '#mhmrentiva_manual_vehicle_id' ).val()) {
				ManualBooking.calculatePrice();
			}
		},

		onCustomerChange: function () {
			const customerId        = $( '#mhmrentiva_manual_customer_id' ).val();
			const newCustomerFields = $( '#mhmrentiva_new_customer_fields' );

			// Only these four are required: the card also holds the optional
			// country and state, which a blanket `input` selector made required.
			const required = $( '#mhmrentiva_new_customer_first_name, #mhmrentiva_new_customer_last_name, #mhmrentiva_new_customer_email, #mhmrentiva_new_customer_phone' );

			if (customerId === 'new_customer') {
				newCustomerFields.removeClass( 'mhm-hidden' );
				required.prop( 'required', true );
			} else {
				newCustomerFields.addClass( 'mhm-hidden' );
				required.prop( 'required', false );
			}
		},

		/**
		 * Rebuild the state control for a country, as WooCommerce checkout
		 * does: a state list is a select, an empty list hides the field, no
		 * list is free text. The id and name stay, the value starts empty, so
		 * a state never travels with a country it does not belong to.
		 */
		renderStateField: function ( countryCode ) {
			const data   = window.mhmManualBookingLocations || {};
			const labels = data.labels || {};
			const states = ( data.states || {} )[ countryCode ];
			const $field = $( '#mhmrentiva_new_customer_state_field' );
			const $old   = $( '#mhmrentiva_new_customer_state' );
			let $control;

			if ( ! countryCode || ( data.noState || [] ).indexOf( countryCode ) !== -1 ) {
				$field.addClass( 'mhm-hidden' );
				$control = $( '<input type="text">' ).val( '' );
			} else if ( states ) {
				$field.removeClass( 'mhm-hidden' );
				$control = $( '<select>' );
				$control.append( $( '<option>' ).val( '' ).text( labels.selectState || '' ) );
				Object.keys( states ).forEach( ( code ) => {
					$control.append( $( '<option>' ).val( code ).text( states[ code ] ) );
				} );
				$( '#mhmrentiva_new_customer_state_label' ).text( labels.state || '' );
			} else {
				$field.removeClass( 'mhm-hidden' );
				$control = $( '<input type="text">' ).val( '' ).attr( 'placeholder', labels.regionPlaceholder || '' );
				$( '#mhmrentiva_new_customer_state_label' ).text( labels.region || '' );
			}

			$control.attr( {
				id: 'mhmrentiva_new_customer_state',
				name: 'mhmrentiva_new_customer_state',
				class: $control.is( 'select' ) ? 'mhm-field-select' : 'mhm-field-input',
			} );
			$old.replaceWith( $control );
		},

		/**
		 * Quantities of the ticked add-ons that have a quantity field (sold
		 * more than once), by add-on id. The server clamps them and reads a
		 * missing one as 1.
		 */
		addonQuantities: function () {
			const quantities = {};
			$( '.mhm-addon-checkbox:checked' ).each(
				function () {
					const $qty = $( '.mhm-addon-qty[data-addon-id="' + $( this ).val() + '"]' );
					if ( $qty.length ) {
						quantities[ $( this ).val() ] = parseInt( $qty.val(), 10 ) || 1;
					}
				}
			);
			return quantities;
		},

		calculateAddonTotal: function () {
			let total             = 0;
			const $selectedAddons = $( '.mhm-addon-checkbox:checked' );

			const days       = ManualBooking.rentalDays();
			const quantities = ManualBooking.addonQuantities();
			$selectedAddons.each(
				function () {
					const price    = parseFloat( $( this ).attr( 'data-price' ) ) || 0;
					const quantity = quantities[ $( this ).val() ] || 1;
					// Same rule as AddonPricingCalculator::calculate(): only a daily
					// service multiplies by the days. This screen sends no passenger
					// count, so the server prices a per-passenger service for one.
					const perDay = 'per_day' === ( $( this ).attr( 'data-pricing-type' ) || 'per_day' );
					total       += price * ( perDay ? days : 1 ) * quantity;
				}
			);

			// Toplam tutarı göster/gizle
			const $addonTotal       = $( '.mhm-addon-total' );
			const $addonTotalAmount = $( '.mhm-addon-total-amount' );

			if (total > 0) {
				$addonTotal.removeClass( 'mhm-hidden' );
				$addonTotalAmount.text( ManualBooking.formatMoney( total ) );
			} else {
				$addonTotal.addClass( 'mhm-hidden' );
			}
		},

		/**
		 * Rental days as the server counts them (SiteTime::rental_days): whole
		 * days between pickup and return date-times, at least one. Built on the
		 * wall clock (UTC fields), so a DST change cannot shift the count.
		 */
		rentalDays: function () {
			const toWall = function ( date, time ) {
				const d = String( date || '' ).split( '-' ).map( Number );
				const t = String( time || '00:00' ).split( ':' ).map( Number );
				return Date.UTC( d[0], ( d[1] || 1 ) - 1, d[2] || 1, t[0] || 0, t[1] || 0 );
			};
			const pickupDate  = $( '#mhmrentiva_manual_pickup_date' ).val();
			const dropoffDate = $( '#mhmrentiva_manual_dropoff_date' ).val();
			if ( ! pickupDate || ! dropoffDate ) {
				return 1;
			}
			const start = toWall( pickupDate, $( '#mhmrentiva_manual_pickup_time' ).val() );
			const end   = toWall( dropoffDate, $( '#mhmrentiva_manual_dropoff_time' ).val() );
			return Math.max( 1, Math.floor( ( end - start ) / 86400000 ) );
		},

		showVehicleInfo: function (vehicleName, price) {
			const selectedVehicleText = mhmManualBooking.text.selectedVehicle || 'Selected Vehicle';
			const vehicleText         = mhmManualBooking.text.vehicle || 'Vehicle';
			const dailyPriceText      = mhmManualBooking.text.dailyPrice || 'Daily Price';
			const notSpecifiedText    = mhmManualBooking.text.notSpecified || 'Not specified';

			const infoHtml = `
					<h5>${selectedVehicleText}</h5>
					<div class="mhm-vehicle-details">
						<div class="mhm-vehicle-detail">
							<strong>${vehicleText}:</strong>
							<span>${$('<span>').text(vehicleName).html()}</span>
						</div>
						<div class="mhm-vehicle-detail">
							<strong>${dailyPriceText}:</strong>
							<span>${$('<span>').text(price ? ManualBooking.formatMoney(price) : notSpecifiedText).html()}</span>
						</div>
					</div>
			`;

			// One fixed slot (rendered by PHP under the two selects): its content
			// is replaced, never appended, so a vehicle change cannot stack boxes.
			$( '#mhm-vehicle-info' ).html( infoHtml ).removeClass( 'mhm-hidden' );
		},

		calculatePrice: function () {
			const vehicleId   = $( '#mhmrentiva_manual_vehicle_id' ).val();
			const pickupDate  = $( '#mhmrentiva_manual_pickup_date' ).val();
			const pickupTime  = $( '#mhmrentiva_manual_pickup_time' ).val();
			const dropoffDate = $( '#mhmrentiva_manual_dropoff_date' ).val();
			const dropoffTime = $( '#mhmrentiva_manual_dropoff_time' ).val();
			const paymentType = $( '#mhmrentiva_manual_payment_type' ).val();

			if ( ! vehicleId || ! pickupDate || ! dropoffDate) {
				ManualBooking.invalidatePriceCalculation();
				ManualBooking.showMessage( 'error', mhmManualBooking.text.fillAllFields || 'Please fill all required fields.' );
				return;
			}

			// Loading state
			$( '#mhm-calculate-price' ).prop( 'disabled', true ).text( mhmManualBooking.text.calculating );
			$( '.mhm-manual-booking-form' ).addClass( 'mhm-calculating' );

			// Every input change asks again; answers can arrive out of order. Only
			// the latest request may paint the panel, or a slow earlier answer
			// (other dates, add-ons or payment type) overwrites the right figures.
			ManualBooking.priceRequest = ( ManualBooking.priceRequest || 0 ) + 1;
			const request              = ManualBooking.priceRequest;
			const isLatest             = function () {
				return request === ManualBooking.priceRequest;
			};

			// Seçilen ek hizmetleri al
			const selectedAddons = [];
			$( '.mhm-addon-checkbox:checked' ).each(
				function () {
					selectedAddons.push( $( this ).val() );
				}
			);

			$.ajax(
				{
					url: mhmManualBooking.ajaxUrl,
					type: 'POST',
					data: {
						action: 'mhmrentiva_calculate_manual_booking',
						nonce: mhmManualBooking.nonce,
						vehicle_id: vehicleId,
						pickup_date: pickupDate,
						pickup_time: pickupTime,
						dropoff_date: dropoffDate,
						dropoff_time: dropoffTime,
						payment_type: paymentType,
						selected_addons: selectedAddons,
						addon_qty: ManualBooking.addonQuantities()
					},
					success: function (response) {
						if ( ! isLatest() ) {
							return;
						}
						if (response.success) {
							ManualBooking.displayPriceCalculation( response.data );
							$( '#mhm-create-booking' ).removeClass( 'mhm-hidden' );
							ManualBooking.showMessage( 'success', mhmManualBooking.text.priceCalculated || 'Price calculated.' );
						} else {
							// Clear the previous breakdown. Leaving it on screen
							// showed a price calculated for OTHER dates directly
							// beneath the new ones -- change the dates to a range
							// the vehicle is already booked for, the request
							// fails, and the earlier range's total sits there
							// looking like the answer. The booking itself was
							// never at risk (the server recomputes the price from
							// the submitted dates and ignores anything the
							// browser sends), but the operator quotes what they
							// see. The create button goes with it: an
							// uncalculated booking must not be submittable.
							ManualBooking.invalidatePriceCalculation();
							ManualBooking.showMessage( 'error', response.data.message || mhmManualBooking.text.error );
						}
					},
					error: function () {
						if ( ! isLatest() ) {
							return;
						}
						ManualBooking.invalidatePriceCalculation();
						ManualBooking.showMessage( 'error', mhmManualBooking.text.error );
					},
					complete: function () {
						if ( ! isLatest() ) {
							return;
						}
						$( '#mhm-calculate-price' ).prop( 'disabled', false ).text( mhmManualBooking.text.calculatePrice || 'Calculate Price' );
						$( '.mhm-manual-booking-form' ).removeClass( 'mhm-calculating' );
					}
				}
			);
		},

		/**
		 * Drop a breakdown that no longer describes what is on the form, and
		 * hide the create button with it.
		 *
		 * Called whenever a calculation fails and whenever an input that
		 * feeds the price changes. The alternative -- leaving the last good
		 * figures on screen -- means the operator reads a total belonging to
		 * dates, a vehicle or add-ons that are no longer selected.
		 */
		invalidatePriceCalculation: function () {
			// An answer still on its way was asked for the form as it was; it
			// must not repaint the panel (or reopen Create) for the form as it is.
			// Its complete() is skipped too, so the busy state is cleared here.
			ManualBooking.priceRequest = ( ManualBooking.priceRequest || 0 ) + 1;
			$( '#mhm-calculate-price' ).prop( 'disabled', false ).text( mhmManualBooking.text.calculatePrice || 'Calculate Price' );
			$( '.mhm-manual-booking-form' ).removeClass( 'mhm-calculating' );

			$( '.mhm-price-details' ).empty();
			$( '.mhm-price-calculation' ).addClass( 'mhm-hidden' );
			$( '#mhm-create-booking' ).addClass( 'mhm-hidden' );
		},

		displayPriceCalculation: function (data) {
			const money = ManualBooking.formatMoney;
			const text  = mhmManualBooking.text;
			const priceHtml = `
				<div class="mhm-price-item">
					<span class="mhm-price-label">${text.rentalDays || 'Rental Days'}:</span>
					<span class="mhm-price-value">${data.days} ${text.days || 'days'}</span>
				</div>
				<div class="mhm-price-item">
					<span class="mhm-price-label">${text.dailyPrice || 'Daily Price'}:</span>
					<span class="mhm-price-value">${money(data.price_per_day)}</span>
				</div>
				${data.weekend_surcharge > 0 ? `
					<div class="mhm-price-item">
					<span class="mhm-price-label">${text.weekendDifference || 'Weekend Difference:'} ${data.weekend_days ? `(${( text.weekendDaysCount || '%d weekend day(s)' ).replace( '%d', data.weekend_days )})` : ''}</span>
					<span class="mhm-price-value">+${money(data.weekend_surcharge)}</span>
					</div>
					` : ''}
				<div class="mhm-price-item">
					<span class="mhm-price-label">${text.vehicleTotal || 'Vehicle Total'}:</span>
					<span class="mhm-price-value">${money(data.vehicle_total || data.total_amount)}</span>
				</div>
				${data.addon_total > 0 ? `
					<div class="mhm-price-item">
					<span class="mhm-price-label">${text.addons || 'Add-ons'}:</span>
					<span class="mhm-price-value">${money(data.addon_total)}</span>
					</div>
					` : ''}
				<div class="mhm-price-item mhm-price-item--total">
					<span class="mhm-price-label">${text.grandTotal || 'Grand Total'}:</span>
					<span class="mhm-price-value">${money(data.final_total || data.total_amount)}</span>
				</div>
				${data.payment_type === 'deposit' ? `
					<div class="mhm-price-item">
					<span class="mhm-price-label">${text.deposit || 'Deposit'}:</span>
					<span class="mhm-price-value">${money(data.deposit_amount)}</span>
					</div>
					<div class="mhm-price-item">
					<span class="mhm-price-label">${text.remaining || 'Remaining'}:</span>
					<span class="mhm-price-value">${money(data.remaining_amount)}</span>
					</div>
					` : ''}
			`;

			$( '.mhm-price-details' ).html( priceHtml );
			$( '.mhm-price-calculation' ).removeClass( 'mhm-hidden' );

			// Sync addon total display from AJAX data
			if ( data.addon_total > 0 ) {
				$( '.mhm-addon-total' ).removeClass( 'mhm-hidden' );
				$( '.mhm-addon-total-amount' ).text( ManualBooking.formatMoney( parseFloat( data.addon_total ) ) );
			}
		},

		createBooking: function () {
			// Form validasyonu
			if ( ! ManualBooking.validateForm()) {
				return;
			}

			// Seçilen ek hizmetleri al
			const selectedAddons = [];
			$( '.mhm-addon-checkbox:checked' ).each(
				function () {
					selectedAddons.push( $( this ).val() );
				}
			);

			const formData = {
				action: 'mhmrentiva_create_manual_booking',
				nonce: mhmManualBooking.nonce,
				vehicle_id: $( '#mhmrentiva_manual_vehicle_id' ).val(),
				customer_id: $( '#mhmrentiva_manual_customer_id' ).val(),
				pickup_date: $( '#mhmrentiva_manual_pickup_date' ).val(),
				pickup_time: $( '#mhmrentiva_manual_pickup_time' ).val(),
				dropoff_date: $( '#mhmrentiva_manual_dropoff_date' ).val(),
				dropoff_time: $( '#mhmrentiva_manual_dropoff_time' ).val(),
				payment_type: $( '#mhmrentiva_manual_payment_type' ).val(),
				payment_method: $( '#mhmrentiva_manual_payment_method' ).val(),
				// The element's id, not its name. Reading the name here matched
				// nothing, and jQuery.param() encodes a missing value as an
				// empty string rather than dropping the key -- so the booking
				// was stored with an empty status, invisible to the conflict
				// check.
				status: $( '#mhmrentiva_manual_booking_status' ).val(),
				notes: $( '#mhmrentiva_manual_notes' ).val(),
				selected_addons: selectedAddons,
				addon_qty: ManualBooking.addonQuantities()
			};

			// Eğer yeni müşteri seçildiyse bilgilerini ekle
			if ($( '#mhmrentiva_manual_customer_id' ).val() === 'new_customer') {
				formData.new_customer_first_name = $( '#mhmrentiva_new_customer_first_name' ).val();
				formData.new_customer_last_name  = $( '#mhmrentiva_new_customer_last_name' ).val();
				formData.new_customer_email      = $( '#mhmrentiva_new_customer_email' ).val();
				formData.new_customer_phone      = $( '#mhmrentiva_new_customer_phone' ).val();
				formData.new_customer_country    = $( '#mhmrentiva_new_customer_country' ).val() || '';
				// A hidden field (a country WooCommerce gives no state) sends none.
				formData.new_customer_state      = $( '#mhmrentiva_new_customer_state_field' ).hasClass( 'mhm-hidden' )
					? ''
					: ( $( '#mhmrentiva_new_customer_state' ).val() || '' );
			}

			// Loading state
			$( '#mhm-create-booking' ).prop( 'disabled', true ).text( mhmManualBooking.text.creating || 'Creating...' );
			$( '.mhm-manual-booking-form' ).addClass( 'mhm-calculating' );

			$.ajax(
				{
					url: mhmManualBooking.ajaxUrl,
					type: 'POST',
					data: formData,
					success: function (response) {
						if (response.success) {
							ManualBooking.showMessage( 'success', response.data.message );

							// 2 saniye sonra rezervasyon sayfasına yönlendir
							setTimeout(
								function () {
									window.location.href = response.data.redirect_url;
								},
								2000
							);
						} else {
							ManualBooking.showMessage( 'error', response.data.message || mhmManualBooking.text.error );
						}
					},
					error: function () {
						ManualBooking.showMessage( 'error', mhmManualBooking.text.error );
					},
					complete: function () {
						$( '#mhm-create-booking' ).prop( 'disabled', false ).text( mhmManualBooking.text.createBooking || 'Create Booking' );
						$( '.mhm-manual-booking-form' ).removeClass( 'mhm-calculating' );
					}
				}
			);
		},

		validateForm: function () {
			let isValid = true;

			// Gerekli alanları kontrol et
			const requiredFields = [
				'#mhmrentiva_manual_vehicle_id',
				'#mhmrentiva_manual_customer_id',
				'#mhmrentiva_manual_pickup_date',
				'#mhmrentiva_manual_dropoff_date'
			];

			requiredFields.forEach(
				function (field) {
					const $field = $( field );
					if ( ! $field.val()) {
						$field.addClass( 'error' );
						isValid = false;
					} else {
						$field.removeClass( 'error' );
					}
				}
			);

			// Tarih doğrulama
			const pickupDate  = new Date( $( '#mhmrentiva_manual_pickup_date' ).val() );
			const dropoffDate = new Date( $( '#mhmrentiva_manual_dropoff_date' ).val() );

			if (dropoffDate <= pickupDate) {
				ManualBooking.showMessage( 'error', mhmManualBooking.text.dropoffAfterPickup || 'Dropoff date must be after pickup date.' );
				isValid = false;
			}

			return isValid;
		},

		showMessage: function (type, message) {
			// Önceki mesajları kaldır
			$( '.mhm-message' ).remove();

			const $msg = $( '<div>' )
				.addClass( 'mhm-message ' + type )
				.text( message );
			// The sidebar's live region, above the price; the form's top otherwise.
			const $slot = $( '.mhm-booking-messages' );
			if ( $slot.length ) {
				$slot.append( $msg );
			} else {
				$( '.mhm-manual-booking-form' ).prepend( $msg );
			}

			// 5 saniye sonra mesajı kaldır (success için)
			if (type === 'success') {
				setTimeout(
					function () {
						$( '.mhm-message' ).fadeOut();
					},
					5000
				);
			}
		}
	};

	// Sayfa yüklendiğinde başlat
	$( document ).ready(
		function () {
			ManualBooking.init();
		}
	);

})( jQuery );
