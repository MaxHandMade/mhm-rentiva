jQuery( document ).ready(
	function ($) {
		'use strict';

		const testResults = $( '#mhm-test-results' );
		const runBtn      = $( '#mhm-run-tests' );
		const clearBtn    = $( '#mhm-clear-tests' );

		if ( ! runBtn.length) {
			return;
		}

		runBtn.on(
			'click',
			function () {
				runBtn.prop( 'disabled', true ).addClass( 'updating-message' ).text( mhm_settings_testing.running_text );
				testResults.hide().empty();

				$.ajax(
					{
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'mhm_run_settings_tests',
							nonce: mhm_settings_testing.nonce
						},
						success: function (response) {
							if (response.success) {
								testResults.html( response.data ).fadeIn();
							} else {
								const errorMsg = response.data || mhm_settings_testing.error_text;
								testResults.html( '<div class="notice notice-error"><p>' + errorMsg + '</p></div>' ).fadeIn();
							}
						},
						error: function () {
							testResults.html( '<div class="notice notice-error"><p>' + mhm_settings_testing.error_text + '</p></div>' ).fadeIn();
						},
						complete: function () {
							runBtn.prop( 'disabled', false ).removeClass( 'updating-message' ).text( mhm_settings_testing.run_text );
						}
					}
				);
			}
		);

		clearBtn.on(
			'click',
			function () {
				testResults.fadeOut(
					function () {
						$( this ).empty();
					}
				);
			}
		);
	}
);
