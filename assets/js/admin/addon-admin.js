/**
 * Ek Hizmetler Admin JavaScript
 */

(function ($) {
	'use strict';

	// Document ready
	$( document ).ready(
		function () {
			initAddonAdmin();
		}
	);

	function initAddonAdmin() {
		// Bulk actions confirmation
		initBulkActions();

		// Delete confirmation
		initDeleteConfirmations();

		// Price validation
		initPriceValidation();
	}

	function initBulkActions() {
		// Bulk enable/disable confirmation
		$( 'select[name="action"], select[name="action2"]' ).on(
			'change',
			function () {
				var action     = $( this ).val();
				var actionText = $( this ).find( 'option:selected' ).text();

				if (action === 'enable_addons' || action === 'disable_addons') {
					const confirmMsg = (mhmAddonAdmin.strings && mhmAddonAdmin.strings.confirm_bulk_enable) ||
					'Are you sure you want to enable the selected addons?';
					if ( ! confirm( confirmMsg )) {
						$( this ).val( '' );
						return false;
					}
				}
			}
		);

		// Bulk delete confirmation
		$( 'select[name="action"], select[name="action2"]' ).on(
			'change',
			function () {
				var action = $( this ).val();

				if (action === 'delete') {
					const confirmMsg = (mhmAddonAdmin.strings && mhmAddonAdmin.strings.confirm_bulk_delete) ||
					'Are you sure you want to delete the selected addons?';
					if ( ! confirm( confirmMsg )) {
						$( this ).val( '' );
						return false;
					}
				}
			}
		);
	}

	function initDeleteConfirmations() {
		// Row delete confirmation
		$( '.row-actions .delete a' ).on(
			'click',
			function (e) {
				const confirmMsg = (mhmAddonAdmin.strings && mhmAddonAdmin.strings.confirm_delete) ||
				'Are you sure you want to delete this addon?';
				if ( ! confirm( confirmMsg )) {
					e.preventDefault();
					return false;
				}
			}
		);
	}

	function initPriceValidation() {
		// Price field validation
		$( 'input[name="addon_price"]' ).on(
			'blur',
			function () {
				var price      = parseFloat( $( this ).val() );
				const errorMsg = (mhmAddonAdmin.strings && mhmAddonAdmin.strings.invalidPrice) || 'Enter a valid price (0 or greater)';

				if (isNaN( price ) || price < 0) {
					$( this ).addClass( 'error' );
					showFieldError( $( this ), errorMsg );
				} else {
					$( this ).removeClass( 'error' );
					hideFieldError( $( this ) );
				}
			}
		);

		// Real-time price formatting
		$( 'input[name="addon_price"]' ).on(
			'input',
			function () {
				var value     = $( this ).val();
				var formatted = formatPrice( value );
				if (formatted !== value) {
					$( this ).val( formatted );
				}
			}
		);
	}

	function formatPrice(value) {
		// Remove non-numeric characters except decimal point
		value = value.replace( /[^\d.,]/g, '' );

		// Replace comma with dot for decimal
		value = value.replace( ',', '.' );

		// Ensure only one decimal point
		var parts = value.split( '.' );
		if (parts.length > 2) {
			value = parts[0] + '.' + parts.slice( 1 ).join( '' );
		}

		return value;
	}

	function showFieldError(field, message) {
		hideFieldError( field );

		var errorDiv = $( '<div class="field-error" style="color: #dc3232; font-size: 12px; margin-top: 5px;">' + message + '</div>' );
		field.after( errorDiv );
	}

	function hideFieldError(field) {
		field.siblings( '.field-error' ).remove();
	}

	// Utility functions
	window.mhmAddonAdmin = {
		formatPrice: formatPrice,
		showFieldError: showFieldError,
		hideFieldError: hideFieldError
	};

})( jQuery );
