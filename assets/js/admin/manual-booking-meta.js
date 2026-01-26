/**
 * Manuel Rezervasyon Meta Box JavaScript
 */

(function ($) {
	'use strict';

	const ManualBooking = {
		init: function () {
			this.bindEvents();
			this.setupDateDefaults();
		},

		bindEvents: function () {
			// Fiyat hesaplama butonu
			$( document ).on( 'click', '#mhm-calculate-price', this.calculatePrice );

			// Rezervasyon oluşturma butonu
			$( document ).on( 'click', '#mhm-create-booking', this.createBooking );

			// Araç seçimi değiştiğinde
			$( document ).on( 'change', '#mhm_manual_vehicle_id', this.onVehicleChange );

			// Tarih değişikliklerinde otomatik hesaplama
			$( document ).on( 'change', '#mhm_manual_pickup_date, #mhm_manual_dropoff_date', this.onDateChange );

			// Ödeme türü değiştiğinde
			$( document ).on( 'change', '#mhm_manual_payment_type', this.onPaymentTypeChange );

			// Ek hizmetler seçimi değiştiğinde
			$( document ).on( 'change', '.mhm-addon-checkbox', this.onAddonChange );

			// Müşteri seçimi değiştiğinde
			$( document ).on( 'change', '#mhm_manual_customer_id', this.onCustomerChange );
		},

		setupDateDefaults: function () {
			// Bugünün tarihini alış tarihi olarak ayarla
			const today = new Date().toISOString().split( 'T' )[0];
			$( '#mhm_manual_pickup_date' ).val( today );

			// Yarının tarihini teslim tarihi olarak ayarla
			const tomorrow = new Date();
			tomorrow.setDate( tomorrow.getDate() + 1 );
			$( '#mhm_manual_dropoff_date' ).val( tomorrow.toISOString().split( 'T' )[0] );
		},

		onVehicleChange: function () {
			const vehicleId = $( this ).val();
			if (vehicleId) {
				const $option = $( this ).find( 'option:selected' );
				const price   = $option.data( 'price' );

				// Araç bilgilerini göster
				ManualBooking.showVehicleInfo( $option.text(), price );

				// Fiyat hesaplama alanını göster
				$( '.mhm-price-calculation' ).show();
			} else {
				$( '.mhm-price-calculation' ).hide();
			}
		},

		onDateChange: function () {
			const pickupDate  = $( '#mhm_manual_pickup_date' ).val();
			const dropoffDate = $( '#mhm_manual_dropoff_date' ).val();

			if (pickupDate && dropoffDate) {
				// Tarih doğrulama
				if (new Date( dropoffDate ) <= new Date( pickupDate )) {
					ManualBooking.showMessage( 'error', mhmManualBooking.text.dropoffAfterPickup || 'Dropoff date must be after pickup date.' );
					return;
				}

				// Otomatik fiyat hesaplama (eğer araç seçilmişse)
				if ($( '#mhm_manual_vehicle_id' ).val()) {
					ManualBooking.calculatePrice();
				}
			}
		},

		onPaymentTypeChange: function () {
			// Ödeme türü değiştiğinde fiyat hesaplama alanını güncelle
			if ($( '#mhm_manual_vehicle_id' ).val()) {
				ManualBooking.calculatePrice();
			}
		},

		onAddonChange: function () {
			// Ek hizmetler toplamını hesapla
			ManualBooking.calculateAddonTotal();

			// Eğer araç seçilmişse genel fiyat hesaplamasını da güncelle
			if ($( '#mhm_manual_vehicle_id' ).val()) {
				ManualBooking.calculatePrice();
			}
		},

		onCustomerChange: function () {
			const customerId        = $( '#mhm_manual_customer_id' ).val();
			const newCustomerFields = $( '#mhm_new_customer_fields' );

			if (customerId === 'new_customer') {
				newCustomerFields.show();
				// Yeni müşteri alanlarını zorunlu yap
				newCustomerFields.find( 'input' ).prop( 'required', true );
			} else {
				newCustomerFields.hide();
				// Yeni müşteri alanlarını zorunlu olmaktan çıkar
				newCustomerFields.find( 'input' ).prop( 'required', false );
			}
		},

		calculateAddonTotal: function () {
			let total             = 0;
			const $selectedAddons = $( '.mhm-addon-checkbox:checked' );

			// Gün sayısını hesapla
			const pickupDate  = $( '#mhm_manual_pickup_date' ).val();
			const dropoffDate = $( '#mhm_manual_dropoff_date' ).val();
			let days          = 1;

			if (pickupDate && dropoffDate) {
				const start = new Date( pickupDate );
				const end   = new Date( dropoffDate );
				days        = Math.ceil( (end - start) / (1000 * 60 * 60 * 24) );
				if (days <= 0) {
					days = 1;
				}
			}

			$selectedAddons.each(
				function () {
					const price = parseFloat( $( this ).data( 'price' ) ) || 0;
					total      += price * days; // Günlük hesaplama
				}
			);

			// Toplam tutarı göster/gizle
			const $addonTotal       = $( '.mhm-addon-total' );
			const $addonTotalAmount = $( '.mhm-addon-total-amount' );

			if (total > 0) {
				$addonTotal.show();
				const formattedTotal = total.toLocaleString(
					mhmManualBooking.locale,
					{
						minimumFractionDigits: 2,
						maximumFractionDigits: 2
					}
				);
				$addonTotalAmount.text( formattedTotal + ' ' + mhmManualBooking.currency );
			} else {
				$addonTotal.hide();
			}
		},

		showVehicleInfo: function (vehicleName, price) {
			const selectedVehicleText = mhmManualBooking.text.selectedVehicle || 'Selected Vehicle';
			const vehicleText         = mhmManualBooking.text.vehicle || 'Vehicle';
			const dailyPriceText      = mhmManualBooking.text.dailyPrice || 'Daily Price';
			const notSpecifiedText    = mhmManualBooking.text.notSpecified || 'Not specified';

			let infoHtml = `
				< div class = "mhm-vehicle-info" >
					< h5 > ${selectedVehicleText} < / h5 >
					< div class = "mhm-vehicle-details" >
						< div class = "mhm-vehicle-detail" >
							< strong > ${vehicleText}: < / strong >
							< span > ${vehicleName} < / span >
						< / div >
						< div class = "mhm-vehicle-detail" >
							< strong > ${dailyPriceText}: < / strong >
							< span > ${price ? price + ' ' + mhmManualBooking.currency : notSpecifiedText} < / span >
						< / div >
					< / div >
				< / div >
			`;

			$( '.mhm-vehicle-info' ).remove();
			$( '.mhm-booking-fields' ).append( infoHtml );
		},

		calculatePrice: function () {
			const vehicleId   = $( '#mhm_manual_vehicle_id' ).val();
			const pickupDate  = $( '#mhm_manual_pickup_date' ).val();
			const pickupTime  = $( '#mhm_manual_pickup_time' ).val();
			const dropoffDate = $( '#mhm_manual_dropoff_date' ).val();
			const dropoffTime = $( '#mhm_manual_dropoff_time' ).val();
			const paymentType = $( '#mhm_manual_payment_type' ).val();

			if ( ! vehicleId || ! pickupDate || ! dropoffDate) {
				ManualBooking.showMessage( 'error', mhmManualBooking.text.fillAllFields || 'Please fill all required fields.' );
				return;
			}

			// Loading state
			$( '#mhm-calculate-price' ).prop( 'disabled', true ).text( mhmManualBooking.text.calculating );
			$( '.mhm-manual-booking-form' ).addClass( 'mhm-calculating' );

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
						action: 'mhm_rentiva_calculate_manual_booking',
						nonce: mhmManualBooking.nonce,
						vehicle_id: vehicleId,
						pickup_date: pickupDate,
						pickup_time: pickupTime,
						dropoff_date: dropoffDate,
						dropoff_time: dropoffTime,
						payment_type: paymentType,
						selected_addons: selectedAddons
					},
					success: function (response) {
						if (response.success) {
							ManualBooking.displayPriceCalculation( response.data );
							$( '#mhm-create-booking' ).show();
							ManualBooking.showMessage( 'success', mhmManualBooking.text.priceCalculated || 'Price calculated.' );
						} else {
							ManualBooking.showMessage( 'error', response.data.message || mhmManualBooking.text.error );
						}
					},
					error: function () {
						ManualBooking.showMessage( 'error', mhmManualBooking.text.error );
					},
					complete: function () {
						$( '#mhm-calculate-price' ).prop( 'disabled', false ).text( mhmManualBooking.text.calculatePrice || 'Calculate Price' );
						$( '.mhm-manual-booking-form' ).removeClass( 'mhm-calculating' );
					}
				}
			);
		},

		displayPriceCalculation: function (data) {
			const currency  = mhmManualBooking.currency;
			const text      = mhmManualBooking.text;
			const priceHtml = `
				< div class = "mhm-price-item" >
					< span class = "mhm-price-label" > ${text.rentalDays || 'Rental Days'}: < / span >
					< span class = "mhm-price-value" > ${data.days} ${text.days || 'days'} < / span >
				< / div >
				< div class = "mhm-price-item" >
					< span class = "mhm-price-label" > ${text.dailyPrice || 'Daily Price'}: < / span >
					< span class = "mhm-price-value" > ${data.price_per_day} ${currency} < / span >
				< / div >
				< div class = "mhm-price-item" >
					< span class = "mhm-price-label" > ${text.vehicleTotal || 'Vehicle Total'}: < / span >
					< span class = "mhm-price-value" > ${data.vehicle_total || data.total_amount} ${currency} < / span >
				< / div >
				${data.addon_total > 0 ? `
					< div class = "mhm-price-item" >
					< span class = "mhm-price-label" > ${text.addons || 'Add-ons'}: < / span >
					< span class = "mhm-price-value" > ${data.addon_total} ${currency} < / span >
					< / div >
					` : ''}
				< div class = "mhm-price-item" >
					< span class = "mhm-price-label" > ${text.grandTotal || 'Grand Total'}: < / span >
					< span class = "mhm-price-value" > ${data.final_total || data.total_amount} ${currency} < / span >
				< / div >
				${data.payment_type === 'deposit' ? `
					< div class = "mhm-price-item" >
					< span class = "mhm-price-label" > ${text.deposit || 'Deposit'}: < / span >
					< span class = "mhm-price-value" > ${data.deposit_amount} ${currency} < / span >
					< / div >
					< div class = "mhm-price-item" >
					< span class = "mhm-price-label" > ${text.remaining || 'Remaining'}: < / span >
					< span class = "mhm-price-value" > ${data.remaining_amount} ${currency} < / span >
					< / div >
					` : ''}
			`;

			$( '.mhm-price-details' ).html( priceHtml );
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
				action: 'mhm_rentiva_create_manual_booking',
				nonce: mhmManualBooking.nonce,
				vehicle_id: $( '#mhm_manual_vehicle_id' ).val(),
				customer_id: $( '#mhm_manual_customer_id' ).val(),
				pickup_date: $( '#mhm_manual_pickup_date' ).val(),
				pickup_time: $( '#mhm_manual_pickup_time' ).val(),
				dropoff_date: $( '#mhm_manual_dropoff_date' ).val(),
				dropoff_time: $( '#mhm_manual_dropoff_time' ).val(),
				guests: $( '#mhm_manual_guests' ).val(),
				payment_type: $( '#mhm_manual_payment_type' ).val(),
				payment_method: $( '#mhm_manual_payment_method' ).val(),
				status: $( '#mhm_manual_status' ).val(),
				notes: $( '#mhm_manual_notes' ).val(),
				selected_addons: selectedAddons
			};

			// Eğer yeni müşteri seçildiyse bilgilerini ekle
			if ($( '#mhm_manual_customer_id' ).val() === 'new_customer') {
				formData.new_customer_first_name = $( '#mhm_new_customer_first_name' ).val();
				formData.new_customer_last_name  = $( '#mhm_new_customer_last_name' ).val();
				formData.new_customer_email      = $( '#mhm_new_customer_email' ).val();
				formData.new_customer_phone      = $( '#mhm_new_customer_phone' ).val();
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
				'#mhm_manual_vehicle_id',
				'#mhm_manual_customer_id',
				'#mhm_manual_pickup_date',
				'#mhm_manual_dropoff_date'
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
			const pickupDate  = new Date( $( '#mhm_manual_pickup_date' ).val() );
			const dropoffDate = new Date( $( '#mhm_manual_dropoff_date' ).val() );

			if (dropoffDate <= pickupDate) {
				ManualBooking.showMessage( 'error', mhmManualBooking.text.dropoffAfterPickup || 'Dropoff date must be after pickup date.' );
				isValid = false;
			}

			return isValid;
		},

		showMessage: function (type, message) {
			// Önceki mesajları kaldır
			$( '.mhm-message' ).remove();

			const messageHtml = ` < div class = "mhm-message ${type}" > ${message} < / div > `;
			$( '.mhm-manual-booking-form' ).prepend( messageHtml );

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
