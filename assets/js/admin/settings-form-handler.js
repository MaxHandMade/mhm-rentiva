/**
 * MHM Rentiva Settings Form Handler
 *
 * Prevents null values from being submitted to WordPress Settings API
 * This fixes PHP strlen() deprecation warnings
 *
 * @since 4.3.5
 */

jQuery( document ).ready(
	function ($) {
		'use strict';

		/**
		 * Clean all form inputs before submission
		 * Convert null/undefined values to empty strings
		 */
		function cleanFormInputs(form) {
			// Get all input, select, textarea elements (but NOT checkboxes)
			var $inputs = $( form ).find( 'input:not([type="checkbox"]), select, textarea' );

			$inputs.each(
				function () {
					var $input = $( this );
					var value  = $input.val();

					// Sadece null veya undefined ise boş string yap.
					// "0" değerini silmemeye dikkat et! (User Request Fix)
					if (value === null || value === undefined) {
						$input.val( '' );
					}
				}
			);

			if (window.mhm_rentiva_config ? .debug) {

			}
			return true;
		}

		/**
		 * Attach form submit handler to settings form
		 */
		$( '#mhm-settings-main-form' ).on(
			'submit',
			function (e) {
				if (window.mhm_rentiva_config ? .debug) {

				}
				cleanFormInputs( this );
			}
		);

		/**
		 * Also attach to any WordPress settings forms
		 */
		$( 'form[action="options.php"]' ).on(
			'submit',
			function (e) {
				if (window.mhm_rentiva_config ? .debug) {

				}
				cleanFormInputs( this );
			}
		);

		if (window.mhm_rentiva_config ? .debug) {

		}
	}
);
