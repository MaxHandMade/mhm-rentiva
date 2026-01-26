/**
 * MHM Rentiva - Addon Settings
 * JavaScript functionality for addon settings page
 */

jQuery( document ).ready(
	function ($) {
		'use strict';

		// Create default addons handler
		$( '#create-default-addons' ).on(
			'click',
			function (e) {
				e.preventDefault();

				if ( ! confirm( mhmAddonSettings.strings.confirm_create )) {
					return;
				}

				const $button = $( this );
				$button.prop( 'disabled', true ).text( mhmAddonSettings.strings.creating );

				$.post(
					mhmAddonSettings.ajax_url,
					{
						action: 'mhm_create_default_addons',
						nonce: mhmAddonSettings.nonce
					}
				)
				.done(
					function (response) {
						if (response.success) {
							location.reload();
						} else {
							alert( mhmAddonSettings.strings.error );
							$button.prop( 'disabled', false ).text( mhmAddonSettings.strings.create_default );
						}
					}
				)
				.fail(
					function () {
						alert( mhmAddonSettings.strings.error );
						$button.prop( 'disabled', false ).text( mhmAddonSettings.strings.create_default );
					}
				);
			}
		);
	}
);
