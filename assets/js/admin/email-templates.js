/**
 * MHM Rentiva - Email Templates
 * JavaScript functionality for email templates page
 */

jQuery( document ).ready(
	function ($) {
		'use strict';

		// Test email sending
		// Two objects lived here until 6.1.5: `testEmail` and `templateSettings`.
		// They bound to .send-test-btn, .test-email-form, .edit-template-btn,
		// .template-settings-form and .reset-template-btn -- five classes with ZERO
		// producers anywhere in this plugin -- and posted four AJAX actions, three of
		// which had no wp_ajax_ registration and the fourth of which was spelled
		// without the `_ajax` suffix the registered handler uses. Nothing could ever
		// reach them. The live test-email path is `sendTestEmail` below, which posts
		// to admin-post.php with send_test_nonce.
		var emailVariables = {
			init: function () {
				this.bindEvents();
			},

			bindEvents: function () {
				// Variable click
				$( '.variable-item' ).on(
					'click',
					function () {
						var variable = $( this ).text();
						emailVariables.insertVariable( variable );
					}
				);
			},

			insertVariable: function (variable) {
				// Insert variable into active textarea
				var activeTextarea = $( 'textarea:focus' );
				if (activeTextarea.length === 0) {
					activeTextarea = $( 'textarea' ).first();
				}

				var currentValue = activeTextarea.val();
				var cursorPos    = activeTextarea.prop( 'selectionStart' );
				var newValue     = currentValue.substring( 0, cursorPos ) + variable + currentValue.substring( cursorPos );

				activeTextarea.val( newValue );
				activeTextarea.focus();

				// Update cursor position
				var newCursorPos = cursorPos + variable.length;
				activeTextarea.prop( 'selectionStart', newCursorPos );
				activeTextarea.prop( 'selectionEnd', newCursorPos );
			}
		};

		// Tab management - Use PHP tab system, no JavaScript interference
		var tabManagement = {
			init: function () {
				// Allow tabs to work as normal links
				// No JavaScript interference
			}
		};

		// Statistics cards animation
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

		// Send test email handler (moved from inline script in EmailTemplates.php)
		var sendTestEmail = {
			init: function () {
				this.bindEvents();
			},

			bindEvents: function () {
				// Settings page send button
				$( '#mhm-send-template-btn-settings' ).on(
					'click',
					function (e) {
						e.preventDefault();
						sendTestEmail.submitForm( 'mhm-template-key-settings', 'mhm-booking-id-settings', 'mhm-new-status-settings', 'mhm-send-to-settings' );
					}
				);

				// Main page send button
				$( '#mhm-send-template-btn' ).on(
					'click',
					function (e) {
						e.preventDefault();
						sendTestEmail.submitForm( 'mhm-template-key', 'mhm-booking-id', 'mhm-new-status', 'mhm-send-to' );
					}
				);
			},

			submitForm: function (templateKeyId, bookingIdId, statusId, toId) {
				const actionUrl = mhmrentiva_email_templates_vars.admin_post_url;
				const nonce     = mhmrentiva_email_templates_vars.send_test_nonce;
				const tpl       = document.getElementById( templateKeyId ).value;
				const bid       = document.getElementById( bookingIdId ).value;
				const st        = document.getElementById( statusId ).value;
				const to        = document.getElementById( toId ).value;

				// Build and submit a detached form (avoid nested form issues)
				const form   = document.createElement( 'form' );
				form.method  = 'POST';
				form.action  = actionUrl;
				const fields = {
					action: 'mhmrentiva_send_template_test',
					_wpnonce: nonce,
					template_key: tpl,
					booking_id: bid,
					new_status: st,
					to: to
				};

				Object.keys( fields ).forEach(
					function (k) {
						const input = document.createElement( 'input' );
						input.type  = 'hidden';
						input.name  = k;
						input.value = fields[k] || '';
						form.appendChild( input );
					}
				);

				document.body.appendChild( form );
				form.submit();
			}
		};

		// Email Preview Tab Logic (AJAX)
		var emailPreviewTab = {
			init: function () {
				this.bindEvents();
			},

			bindEvents: function () {
				// Preview Button
				$( '#mhm-preview-btn' ).on(
					'click',
					function (e) {
						e.preventDefault();
						emailPreviewTab.loadPreview( $( this ) );
					}
				);

				// Send Test Button (Preview Tab)
				$( '#mhm-preview-send-btn' ).on(
					'click',
					function (e) {
						e.preventDefault();
						emailPreviewTab.sendTest( $( this ) );
					}
				);
			},

			loadPreview: function (btn) {
				var container    = $( '#mhm-preview-result-container' );
				var template     = $( '#mhm-preview-template-key' ).val();
				var bookingId    = $( '#mhm-preview-booking-id' ).val();
				var newStatus    = $( '#mhm-preview-new-status' ).val();
				var nonce        = btn.data( 'nonce' );
				var originalText = btn.text(); // Store original text FIRST

				// Allow empty booking ID - will use mock data
				// if (!bookingId) {
				//     showNotice('Please enter a Booking ID.', 'error');
				//     return;
				// }

				// Loading State
				btn.data( 'original-text', originalText ); // Store for restoration
				btn.prop( 'disabled', true ).text( mhmrentiva_email_templates_vars.processing || 'Loading...' );
				container.css( 'opacity', '0.5' );

				// Optional: Add spinner to container
				container.html( '<div style="text-align:center; padding:40px;"><span class="spinner is-active" style="float:none; margin:0;"></span> Loading preview...</div>' );

				$.ajax(
					{
						url: mhmrentiva_email_templates_vars.ajax_url,
						type: 'POST',
						data: {
							action: 'mhmrentiva_preview_email_ajax',
							nonce: nonce,
							template_key: template,
							booking_id: bookingId,
							new_status: newStatus
						},
						success: function (response) {
							if (response.success) {
								// Render HTML
								var html = '<div style="background: #f9f9f9; border: 1px solid #ddd; padding: 20px;">' +
								'<h3>Subject: ' + response.data.subject + '</h3>' +
								'<hr>' +
								'<div style="background: white; border: 1px solid #ccc; padding: 15px;">' +
								response.data.html +
								'</div></div>';
								container.html( html );
							} else {
								container.html( '<div class="notice notice-error inline"><p>' + (response.data || 'Error loading preview') + '</p></div>' );
								showNotice( response.data || 'Error loading preview', 'error' );
							}
						},
						error: function () {
							container.html( '<div class="notice notice-error inline"><p>Connection error.</p></div>' );
						},
						complete: function () {
							btn.prop( 'disabled', false ).text( btn.data( 'original-text' ) || 'Preview' );
							container.css( 'opacity', '1' );
						}
					}
				);
			},

			sendTest: function (btn) {
				var template     = $( '#mhm-preview-template-key' ).val();
				var bookingId    = $( '#mhm-preview-booking-id' ).val();
				var newStatus    = $( '#mhm-preview-new-status' ).val(); // Not used directly in send, but maybe in context
				var to           = $( '#mhm-preview-send-to' ).val();
				var nonce        = btn.data( 'nonce' );
				var originalText = btn.text();

				if ( ! to) {
					showNotice( 'Please enter an email address.', 'warning' );
					return;
				}

				// Loading State
				btn.prop( 'disabled', true ).text( mhmrentiva_email_templates_vars.processing || 'Sending...' );

				$.ajax(
					{
						url: mhmrentiva_email_templates_vars.ajax_url,
						type: 'POST',
						data: {
							action: 'mhmrentiva_send_test_email_ajax',
							nonce: nonce,
							template_key: template,
							booking_id: bookingId,
							new_status: newStatus,
							to: to
						},
						success: function (response) {
							if (response.success) {
								showNotice( response.data || 'Email sent successfully!', 'success' );
							} else {
								showNotice( response.data || 'Failed to send email.', 'error' );
							}
						},
						error: function () {
							showNotice( 'Connection error.', 'error' );
						},
						complete: function () {
							btn.prop( 'disabled', false ).text( originalText );
						}
					}
				);
			}
		};

		// Initialize
		emailVariables.init();
		tabManagement.init();
		statsAnimation.init();
		sendTestEmail.init();
		emailPreviewTab.init(); // New init logic

		// Modal close events
		$( document ).on(
			'click',
			'.template-edit-close, .template-edit-modal',
			function (e) {
				if (e.target === this) {
					$( '.template-edit-modal' ).remove();
				}
			}
		);

		// ESC key modal close
		$( document ).on(
			'keydown',
			function (e) {
				if (e.keyCode === 27) { // ESC
					$( '.email-preview-modal, .template-edit-modal' ).remove();
				}
			}
		);

		// Update statistics when page loads
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

		if (typeof mhmrentiva_email_templates_vars !== 'undefined' && mhmrentiva_email_templates_vars.auto_refresh) {
			setInterval(
				function () {
					// Auto-update statistics (optional)
				},
				30000
			); // Every 30 seconds
		}
	}
);
