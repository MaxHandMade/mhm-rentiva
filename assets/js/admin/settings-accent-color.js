/**
 * Settings > Frontend & Display > Colors: the accent color picker.
 *
 * Turns the plain text input into WordPress's wp-color-picker and keeps it
 * usable only while "Custom color" is chosen. Without JavaScript the form still
 * works: the input stays a plain text field and the sanitizer decides.
 *
 * PHP decides, this script only previews: every sentence comes printed from
 * FrontendColorsSection (labels in data-*, sentences as data-template with %s)
 * and is written back with textContent only. On a pick or a mode switch the
 * inert .rvs-shell preview is repainted with the browser twin of
 * ColorMath::palette() (window.mhmRentivaAccentPalette); on load PHP is right.
 */
( function ( $ ) {
	'use strict';

	// The eight token names the preview may receive; nothing else is written.
	var TOKENS = [ 'bg', 'accent-strong', 'accent-deep', 'accent-100', 'sel-text', 'on-accent', 'accent-text', 'accent-text-hover' ];
	var HEX = /^#[0-9a-f]{6}$/;

	$( function () {
		var $input = $( '.mhm-rentiva-accent-color' );
		if ( ! $input.length || 'function' !== typeof $.fn.wpColorPicker ) {
			return;
		}

		var palette = window.mhmRentivaAccentPalette;
		var preview = palette && 'function' === typeof palette.palette && 'function' === typeof palette.normalize;
		var $radios = $( 'input[name="mhmrentiva_settings[mhmrentiva_accent_mode]"]' );
		var $box = $input.closest( '.mhm-rentiva-accent' );
		var autoHex = preview ? palette.normalize( String( $box.attr( 'data-auto-hex' ) || '' ) ) : null;
		var autoSource = String( $box.attr( 'data-auto-source' ) || '' );
		var customSource = String( $box.attr( 'data-custom-source' ) || '' );

		function isCustom() {
			return 'custom' === $radios.filter( ':checked' ).val();
		}

		// What the preview should show. `raw` is Iris's colour on a change
		// (the input does not hold it yet); otherwise the input's value.
		function current( raw ) {
			if ( isCustom() ) {
				var hex = palette.normalize( undefined === raw ? String( $input.val() || '' ) : String( raw ) );
				if ( hex ) {
					return { hex: hex, label: customSource, button: true };
				}
				// Custom + empty or invalid: the sanitizer falls back to Automatic.
				return { hex: autoHex, label: autoSource, button: false };
			}
			return { hex: autoHex, label: autoSource, button: true };
		}

		function fill( template, value ) {
			return String( template || '' ).replace( '%s', function () {
				return value;
			} );
		}

		function paintButton( hex ) {
			var button = $input.closest( '.wp-picker-container' ).find( '.wp-color-result' ).get( 0 );
			if ( button && HEX.test( hex ) ) {
				button.style.backgroundColor = hex;
			}
		}

		function paint( state ) {
			if ( ! state.hex || ! HEX.test( state.hex ) ) {
				return;
			}
			var out = palette.palette( state.hex );
			var tokens = out.tokens || {};
			var flags = out.flags || {};

			$box.find( '.mhm-rentiva-accent-preview' ).each( function () {
				var el = this;
				TOKENS.forEach( function ( name ) {
					var value = tokens[ name ];
					if ( 'string' === typeof value && HEX.test( value ) ) {
						el.style.setProperty( '--rvs-' + name, value );
					}
				} );
			} );

			// Only a valid colour reaches the picker button; Iris keeps its own otherwise.
			if ( state.button ) {
				paintButton( state.hex );
			}

			var swatch = $box.find( '.mhm-rentiva-accent-swatch' ).get( 0 );
			if ( swatch ) {
				swatch.style.backgroundColor = state.hex;
			}
			$box.find( '.mhm-rentiva-accent-hex' ).each( function () {
				this.textContent = state.hex;
			} );
			$box.find( '.mhm-rentiva-accent-source' ).each( function () {
				var template = this.getAttribute( 'data-template' );
				$( this ).find( '.mhm-rentiva-accent-source-text' ).each( function () {
					this.textContent = fill( template, state.label );
				} );
			} );

			var $text = $box.find( '.mhm-rentiva-accent-notice--text' );
			var darker = tokens[ 'accent-text' ];
			if ( flags.text_darkened && 'string' === typeof darker && HEX.test( darker ) ) {
				$text.each( function () {
					this.textContent = fill( this.getAttribute( 'data-template' ), darker );
				} );
				$text.prop( 'hidden', false );
			} else {
				$text.prop( 'hidden', true );
			}
			$box.find( '.mhm-rentiva-accent-notice--fill' ).prop( 'hidden', ! flags.fill_low );
		}

		// Set once set-up is done: a change Iris may fire while it builds itself
		// is not a pick, and on load PHP is right.
		var ready = false;

		function repaint( raw ) {
			if ( preview && ready ) {
				paint( current( raw ) );
			}
		}

		$input.wpColorPicker( {
			change: function ( event, ui ) {
				repaint( ui && ui.color ? ui.color.toString() : undefined );
			},
			clear: function () {
				repaint();
			}
		} );

		var $picker = $input.closest( '.wp-picker-container' );

		function sync() {
			var custom = isCustom();
			$picker.find( 'button, input' ).prop( 'disabled', ! custom );
			$picker.toggleClass( 'is-disabled', ! custom );
		}

		$radios.on( 'change', function () {
			sync();
			// Start an empty custom picker from the site's colour, not from nothing.
			if ( isCustom() && '' === String( $input.val() || '' ) && autoHex ) {
				$input.wpColorPicker( 'color', autoHex );
			}
			repaint();
		} );
		$input.on( 'input', function () {
			repaint();
		} );

		sync();
		// On load PHP has painted the preview and the line; only the picker
		// button needs the colour (it stays empty on Automatic otherwise).
		if ( preview ) {
			var initial = current();
			if ( initial.button ) {
				paintButton( initial.hex );
			}
		}
		ready = true;

		// A disabled input is not posted; re-enable on save so an Automatic
		// save keeps the chosen custom color for later.
		$input.closest( 'form' ).on( 'submit', function () {
			$input.prop( 'disabled', false );
		} );
	} );
} )( jQuery );
