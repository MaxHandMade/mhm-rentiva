/**
 * MHM Rentiva - Ek Hizmetler Listesi
 * Ek hizmetler listesi için JavaScript işlevselliği
 */

jQuery( document ).ready(
	function ($) {
		'use strict';

		// Inline fiyat düzenleme
		var inlineEdit = {
			init: function () {
				this.bindEvents();
			},

			bindEvents: function () {
				// Fiyat üzerine çift tıklama
				$( document ).on(
					'dblclick',
					'.addon-price-display',
					function () {
						inlineEdit.startEdit( $( this ) );
					}
				);

				// Enter tuşu ile kaydet
				$( document ).on(
					'keypress',
					'.addon-price-input',
					function (e) {
						if (e.which === 13) { // Enter
							inlineEdit.saveEdit( $( this ) );
						}
					}
				);

				// Escape tuşu ile iptal
				$( document ).on(
					'keyup',
					'.addon-price-input',
					function (e) {
						if (e.which === 27) { // Escape
							inlineEdit.cancelEdit( $( this ) );
						}
					}
				);

				// Input dışına tıklama ile kaydet
				$( document ).on(
					'blur',
					'.addon-price-input',
					function () {
						inlineEdit.saveEdit( $( this ) );
					}
				);
			},

			startEdit: function ($element) {
				var addonId      = $element.data( 'addon-id' );
				var currentPrice = $element.data( 'price' );

				$element.html(
					'<input type="number" class="addon-price-input" value="' + currentPrice + '" min="0" step="0.01" style="width: 80px;" />'
				);

				$element.find( '.addon-price-input' ).focus().select();
			},

			saveEdit: function ($input) {
				var $element = $input.closest( '.addon-price-display' );
				var addonId  = $element.data( 'addon-id' );
				var newPrice = parseFloat( $input.val() );

				if (isNaN( newPrice ) || newPrice < 0) {
					const invalidMsg = (mhm_addon_list_vars.strings && mhm_addon_list_vars.strings.invalidPrice) || 'Invalid price value!';
					showNotice( invalidMsg, 'error' );
					inlineEdit.cancelEdit( $input );
					return;
				}

				// AJAX ile fiyatı güncelle
				$.ajax(
					{
						url: mhm_addon_list_vars.ajax_url,
						type: 'POST',
						data: {
							action: 'mhm_update_addon_price',
							addon_id: addonId,
							price: newPrice,
							nonce: mhm_addon_list_vars.nonce
						},
						success: function (response) {
							if (response.success) {
								// Başarılı güncelleme
								var currency       = mhm_addon_list_vars.currency;
								var formattedPrice = newPrice.toLocaleString(
									mhm_addon_list_vars.locale || 'en-US',
									{
										minimumFractionDigits: 2,
										maximumFractionDigits: 2
									}
								) + ' ' + currency;
								$element.html( formattedPrice );
								$element.data( 'price', newPrice );
							} else {
								const errorMsg   = (mhm_addon_list_vars.strings && mhm_addon_list_vars.strings.priceUpdateError) || 'Error updating price';
								const unknownMsg = (mhm_addon_list_vars.strings && mhm_addon_list_vars.strings.unknownError) || 'Unknown error';
								showNotice( errorMsg + ': ' + (response.data.message || unknownMsg), 'error' );
								inlineEdit.cancelEdit( $input );
							}
						},
						error: function () {
							const errorMsg = (mhm_addon_list_vars.strings && mhm_addon_list_vars.strings.priceUpdateError) || 'Error updating price!';
							showNotice( errorMsg, 'error' );
							inlineEdit.cancelEdit( $input );
						}
					}
				);
			},

			cancelEdit: function ($input) {
				var $element      = $input.closest( '.addon-price-display' );
				var originalPrice = $element.data( 'price' );
				var currency      = mhm_addon_list_vars.currency || 'USD';

				// Orijinal fiyatı geri yükle
				var formattedPrice = originalPrice.toLocaleString(
					mhm_addon_list_vars.locale || 'en-US',
					{
						minimumFractionDigits: 2,
						maximumFractionDigits: 2
					}
				) + ' ' + currency;
				$element.html( formattedPrice );
			}
		};

		// Toplu işlemler
		var bulkActions = {
			init: function () {
				this.bindEvents();
			},

			bindEvents: function () {
				// Toplu işlem formu gönderimi
				$( '#bulk-action-selector-top, #bulk-action-selector-bottom' ).on(
					'change',
					function () {
						var action = $( this ).val();
						if (action && action !== '-1') {
							$( '.bulk-actions .button' ).prop( 'disabled', false );
						} else {
							$( '.bulk-actions .button' ).prop( 'disabled', true );
						}
					}
				);

				// Toplu işlem butonları
				$( '.bulk-actions .button' ).on(
					'click',
					function (e) {
						e.preventDefault();
						var action        = $( '#bulk-action-selector-top' ).val();
						var selectedItems = $( 'input[name="addon[]"]:checked' );

						if (selectedItems.length === 0) {
							showNotice( mhm_addon_list_vars.no_items_selected, 'warning' );
							return;
						}

						if (action === 'enable_addons') {
							bulkActions.enableAddons( selectedItems );
						} else if (action === 'disable_addons') {
							bulkActions.disableAddons( selectedItems );
						} else if (action === 'delete') {
							bulkActions.deleteAddons( selectedItems );
						}
					}
				);

				// Tümünü seç/seçme
				$( '#cb-select-all-1, #cb-select-all-2' ).on(
					'change',
					function () {
						var isChecked = $( this ).is( ':checked' );
						$( 'input[name="addon[]"]' ).prop( 'checked', isChecked );
						bulkActions.updateBulkActions();
					}
				);

				// Tekil seçim
				$( 'input[name="addon[]"]' ).on(
					'change',
					function () {
						bulkActions.updateBulkActions();
					}
				);
			},

			updateBulkActions: function () {
				var selectedCount = $( 'input[name="addon[]"]:checked' ).length;
				var totalCount    = $( 'input[name="addon[]"]' ).length;

				if (selectedCount > 0) {
					$( '.bulk-actions .button' ).prop( 'disabled', false );
					$( '.bulk-actions .selected-count' ).text( selectedCount + ' ' + mhm_addon_list_vars.items_selected );
				} else {
					$( '.bulk-actions .button' ).prop( 'disabled', true );
					$( '.bulk-actions .selected-count' ).text( '' );
				}

				// Tümünü seç checkbox'ını güncelle
				if (selectedCount === totalCount) {
					$( '#cb-select-all-1, #cb-select-all-2' ).prop( 'checked', true );
				} else if (selectedCount === 0) {
					$( '#cb-select-all-1, #cb-select-all-2' ).prop( 'checked', false );
				} else {
					$( '#cb-select-all-1, #cb-select-all-2' ).prop( 'indeterminate', true );
				}
			},

			enableAddons: function (selectedItems) {
				if ( ! confirm( mhm_addon_list_vars.confirm_enable )) {
					return;
				}

				var addonIds = selectedItems.map(
					function () {
						return $( this ).val();
					}
				).get();

				bulkActions.performBulkAction( 'enable_addons', addonIds );
			},

			disableAddons: function (selectedItems) {
				if ( ! confirm( mhm_addon_list_vars.confirm_disable )) {
					return;
				}

				var addonIds = selectedItems.map(
					function () {
						return $( this ).val();
					}
				).get();

				bulkActions.performBulkAction( 'disable_addons', addonIds );
			},

			deleteAddons: function (selectedItems) {
				if ( ! confirm( mhm_addon_list_vars.confirm_delete )) {
					return;
				}

				var addonIds = selectedItems.map(
					function () {
						return $( this ).val();
					}
				).get();

				bulkActions.performBulkAction( 'delete', addonIds );
			},

			performBulkAction: function (action, addonIds) {
				var $button      = $( '.bulk-actions .button' );
				var originalText = $button.text();

				$button.prop( 'disabled', true ).text( mhm_addon_list_vars.processing );

				$.ajax(
					{
						url: mhm_addon_list_vars.ajax_url,
						type: 'POST',
						data: {
							action: 'mhm_bulk_addon_action',
							bulk_action: action,
							addon_ids: addonIds,
							nonce: mhm_addon_list_vars.nonce
						},
						success: function (response) {
							if (response.success) {
								// Sayfayı yenile
								location.reload();
							} else {
								showNotice( response.data || mhm_addon_list_vars.error_occurred, 'error' );
							}
						},
						error: function () {
							showNotice( mhm_addon_list_vars.error_occurred, 'error' );
						},
						complete: function () {
							$button.prop( 'disabled', false ).text( originalText );
						}
					}
				);
			}
		};

		// Filtreleme ve arama
		var filtering = {
			init: function () {
				this.bindEvents();
			},

			bindEvents: function () {
				// Filtreleme formu
				$( '.filter-controls form' ).on(
					'submit',
					function (e) {
						e.preventDefault();
						filtering.applyFilters();
					}
				);

				// Filtreleri temizle
				$( '.filter-controls .clear-filters' ).on(
					'click',
					function (e) {
						e.preventDefault();
						filtering.clearFilters();
					}
				);

				// Hızlı filtreleme
				$( '.filter-controls select' ).on(
					'change',
					function () {
						filtering.applyFilters();
					}
				);
			},

			applyFilters: function () {
				var form     = $( '.filter-controls form' );
				var formData = form.serialize();

				// URL'yi güncelle
				var url    = new URL( window.location );
				var params = new URLSearchParams( formData );

				// Mevcut parametreleri temizle
				url.searchParams.delete( 'addon_status' );
				url.searchParams.delete( 'addon_category' );
				url.searchParams.delete( 's' );

				// Yeni parametreleri ekle
				params.forEach(
					function (value, key) {
						if (value) {
							url.searchParams.set( key, value );
						}
					}
				);

				window.location.href = url.toString();
			},

			clearFilters: function () {
				var url = new URL( window.location );
				url.searchParams.delete( 'addon_status' );
				url.searchParams.delete( 'addon_category' );
				url.searchParams.delete( 's' );
				window.location.href = url.toString();
			}
		};

		// Hızlı düzenleme
		var quickEdit = {
			init: function () {
				this.bindEvents();
			},

			bindEvents: function () {
				// Hızlı düzenleme butonları
				$( '.quick-edit-addon' ).on(
					'click',
					function (e) {
						e.preventDefault();
						var addonId = $( this ).data( 'addon-id' );
						quickEdit.showQuickEdit( addonId );
					}
				);

				// Hızlı düzenleme kaydet
				$( '.quick-edit-save' ).on(
					'click',
					function (e) {
						e.preventDefault();
						quickEdit.saveQuickEdit();
					}
				);

				// Hızlı düzenleme iptal
				$( '.quick-edit-cancel' ).on(
					'click',
					function (e) {
						e.preventDefault();
						quickEdit.hideQuickEdit();
					}
				);
			},

			showQuickEdit: function (addonId) {
				// Hızlı düzenleme modal'ını göster
				// Bu kısım modal implementasyonuna göre değişebilir
				// Debug log kaldırıldı
			},

			saveQuickEdit: function () {
				// Hızlı düzenleme verilerini kaydet
				// Debug log kaldırıldı
			},

			hideQuickEdit: function () {
				// Hızlı düzenleme modal'ını gizle
				// Debug log kaldırıldı
			}
		};

		// İstatistik kartları animasyonu
		var statsAnimation = {
			init: function () {
				this.animateStats();
			},

			animateStats: function () {
				$( '.stat-card' ).each(
					function (index) {
						$( this ).css( 'animation-delay', (index * 0.1) + 's' );
					}
				);
			}
		};

		// Lisans uyarısı
		var licenseWarning = {
			init: function () {
				this.checkLicenseLimits();
			},

			checkLicenseLimits: function () {
				// Lisans limitlerini kontrol et
				if (typeof mhm_addon_list_vars !== 'undefined' && mhm_addon_list_vars.license_limit_reached) {
					this.showLicenseWarning();
				}
			},

			showLicenseWarning: function () {
				var warning = $(
					'<div class="notice notice-warning is-dismissible">' +
					'<p><strong>' + mhm_addon_list_vars.license_warning_title + '</strong> ' +
					mhm_addon_list_vars.license_warning_message + '</p>' +
					'</div>'
				);

				$( '.mhm-addon-stats-cards' ).before( warning );
			}
		};

		// Başlatma
		inlineEdit.init();
		bulkActions.init();
		filtering.init();
		quickEdit.init();
		statsAnimation.init();
		licenseWarning.init();

		// Sayfa yüklendiğinde istatistikleri güncelle
		/**
		 * Show notice message
		 */
		function showNotice(message, type) {
			type            = type || 'info';
			var noticeClass = 'notice-' + type;
			var notice      = $( '<div class="notice ' + noticeClass + ' is-dismissible" style="position: fixed; top: 32px; right: 20px; z-index: 9999; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);"><p><strong>' + message + '</strong></p></div>' );

			// Remove any existing notices first
			$( '.notice' ).remove();

			// Add to body for better visibility
			$( 'body' ).append( notice );

			// Auto-dismiss after 5 seconds
			setTimeout(
				function () {
					notice.fadeOut(
						500,
						function () {
							notice.remove();
						}
					);
				},
				5000
			);
		}

		if (typeof mhm_addon_list_vars !== 'undefined' && mhm_addon_list_vars.auto_refresh) {
			setInterval(
				function () {
					// İstatistikleri otomatik güncelle (isteğe bağlı)
				},
				30000
			); // 30 saniyede bir
		}
	}
);
