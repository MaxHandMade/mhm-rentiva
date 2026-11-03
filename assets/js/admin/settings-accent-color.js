/**
 * Settings > Frontend & Display > Colors: the accent color picker.
 *
 * Turns the plain text input into WordPress's wp-color-picker and keeps it
 * usable only while "Custom color" is chosen. Without JavaScript the form still
 * works: the input stays a plain text field and the sanitizer decides.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var $input = $( '.mhm-rentiva-accent-color' );
		if ( ! $input.length || 'function' !== typeof $.fn.wpColorPicker ) {
			return;
		}

		$input.wpColorPicker();

		var $radios = $( 'input[name="mhmrentiva_settings[mhmrentiva_accent_mode]"]' );
		var $picker = $input.closest( '.wp-picker-container' );

		function sync() {
			var custom = 'custom' === $radios.filter( ':checked' ).val();
			$picker.find( 'button, input' ).prop( 'disabled', ! custom );
			$picker.toggleClass( 'is-disabled', ! custom );
		}

		$radios.on( 'change', sync );
		sync();

		// A disabled input is not posted; re-enable on save so an Automatic
		// save keeps the chosen custom color for later.
		$input.closest( 'form' ).on( 'submit', function () {
			$input.prop( 'disabled', false );
		} );
	} );
} )( jQuery );
