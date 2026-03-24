/**
 * GDPR Consent Popup
 *
 * Handles accept/decline buttons on the GDPR notice popup.
 */
jQuery( document ).ready(
	function ($) {
		var notice = $( '#mhm-gdpr-notice' );

		if ( ! notice.length ) {
			return;
		}

		$( '#gdpr-accept' ).on(
			'click',
			function () {
				$.post(
					mhm_gdpr.ajax_url,
					{
						action: 'mhm_rentiva_consent_accept',
						nonce:  mhm_gdpr.nonce
					},
					function () {
						notice.fadeOut( 300 );
					}
				);
			}
		);

		$( '#gdpr-decline' ).on(
			'click',
			function () {
				notice.fadeOut( 300 );
			}
		);
	}
);
