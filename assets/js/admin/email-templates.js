/**
 * MHM Rentiva - Email Templates
 * JavaScript functionality for email templates page
 */

jQuery( document ).ready(
	function ($) {
		'use strict';

		// Email preview
		var emailPreview = {
			init: function () {
				this.bindEvents();
			},

			bindEvents: function () {
				// Preview buttons
				$( '.preview-email-btn' ).on(
					'click',
					function (e) {
						e.preventDefault();
						var templateKey = $( this ).data( 'template-key' );
						emailPreview.showPreview( templateKey );
					}
				);

				// Modal close
				$( '.email-preview-close, .email-preview-modal' ).on(
					'click',
					function (e) {
						if (e.target === this) {
							emailPreview.hidePreview();
						}
					}
				);

				// ESC key close
				$( document ).on(
					'keydown',
					function (e) {
						if (e.keyCode === 27) { // ESC
							emailPreview.hidePreview();
						}
					}
				);
			},

			showPreview: function (templateKey) {
				var modal = $(
					'<div class="email-preview-modal active">' +
					'<div class="email-preview-content">' +
					'<div class="email-preview-header">' +
					'<h3 class="email-preview-title">' + mhm_email_templates_vars.preview_email + '</h3>' +
					'<button class="email-preview-close">&times;</button>' +
					'</div>' +
					'<div class="email-preview-body">' +
					'<iframe class="email-preview-iframe" src="' +
					ajaxurl + '?action=mhm_email_preview&template=' + templateKey + '"></iframe>' +
					'</div>' +
					'</div>' +
					'</div>'
				);

				$( 'body' ).append( modal );
				$( 'body' ).addClass( 'modal-open' );
			},

			hidePreview: function () {
				$( '.email-preview-modal' ).remove();
				$( 'body' ).removeClass( 'modal-open' );
			}
		};

		// Test email sending
		var testEmail = {
			init: function () {
				this.bindEvents();
			},

			bindEvents: function () {
				// Test send buttons
				$( '.send-test-btn' ).on(
					'click',
					function (e) {
						e.preventDefault();
						var templateKey = $( this ).data( 'template-key' );
						testEmail.showTestForm( templateKey );
					}
				);

				// Test form submission
				$( document ).on(
					'submit',
					'.test-email-form',
					function (e) {
						e.preventDefault();
						var form        = $( this );
						var templateKey = form.data( 'template-key' );
						var email       = form.find( 'input[name="test_email"]' ).val();

						if ( ! email) {
							const alertMsg = (mhm_email_templates_vars.strings && mhm_email_templates_vars.strings.enterEmail) || 'Please enter email address';
							showNotice( alertMsg, 'warning' );
							return;
						}

						testEmail.sendTestEmail( templateKey, email, form );
					}
				);

				// Test form close
				$( document ).on(
					'click',
					'.close-test-form',
					function (e) {
						e.preventDefault();
						$( this ).closest( '.test-email-form' ).remove();
					}
				);
			},

			showTestForm: function (templateKey) {
				const sendTestTitle = (mhm_email_templates_vars.strings && mhm_email_templates_vars.strings.sendTestEmail) || 'Send Test Email';
				const emailLabel    = (mhm_email_templates_vars.strings && mhm_email_templates_vars.strings.emailAddress) || 'Email Address';
				const cancelText    = (mhm_email_templates_vars.strings && mhm_email_templates_vars.strings.cancel) || 'Cancel';

				var form = $(
					'<div class="test-email-form" data-template-key="' + templateKey + '">' +
					'<h4>' + sendTestTitle + '</h4>' +
					'<div class="form-group">' +
					'<label for="test_email">' + emailLabel + ':</label>' +
					'<input type="email" name="test_email" required placeholder="test@example.com">' +
					'</div>' +
					'<div class="form-actions">' +
					'<button type="submit" class="button button-primary">' + mhm_email_templates_vars.send_test + '</button>' +
					'<button type="button" class="button close-test-form">' + cancelText + '</button>' +
					'</div>' +
					'</div>'
				);

				// Remove existing form
				$( '.test-email-form' ).remove();

				// Add new form
				$( '.email-template-actions' ).first().after( form );
			},

			sendTestEmail: function (templateKey, email, form) {
				var submitBtn    = form.find( 'button[type="submit"]' );
				var originalText = submitBtn.text();

				submitBtn.prop( 'disabled', true ).text( mhm_email_templates_vars.processing );

				$.ajax(
					{
						url: mhm_email_templates_vars.ajax_url,
						type: 'POST',
						data: {
							action: 'mhm_send_test_email',
							template_key: templateKey,
							test_email: email,
							nonce: mhm_email_templates_vars.nonce
						},
						success: function (response) {
							if (response.success) {
								showNotice( mhm_email_templates_vars.test_email_sent, 'success' );
								form.remove();
							} else {
								showNotice( response.data || mhm_email_templates_vars.test_email_failed, 'error' );
							}
						},
						error: function () {
							showNotice( mhm_email_templates_vars.error_occurred, 'error' );
						},
						complete: function () {
							submitBtn.prop( 'disabled', false ).text( originalText );
						}
					}
				);
			}
		};

		// Email template settings
		var templateSettings = {
			init: function () {
				this.bindEvents();
			},

			bindEvents: function () {
				// Template editing
				$( '.edit-template-btn' ).on(
					'click',
					function (e) {
						e.preventDefault();
						var templateKey = $( this ).data( 'template-key' );
						templateSettings.editTemplate( templateKey );
					}
				);

				// Template saving
				$( document ).on(
					'submit',
					'.template-settings-form',
					function (e) {
						e.preventDefault();
						var form = $( this );
						templateSettings.saveTemplate( form );
					}
				);

				// Template reset
				$( '.reset-template-btn' ).on(
					'click',
					function (e) {
						e.preventDefault();
						if (confirm( 'Are you sure you want to reset this template to default settings?' )) {
							var templateKey = $( this ).data( 'template-key' );
							templateSettings.resetTemplate( templateKey );
						}
					}
				);
			},

			editTemplate: function (templateKey) {
				const editTitle    = (mhm_email_templates_vars.strings && mhm_email_templates_vars.strings.editTemplate) || 'Edit Template';
				const subjectLabel = (mhm_email_templates_vars.strings && mhm_email_templates_vars.strings.subject) || 'Subject';
				const contentLabel = (mhm_email_templates_vars.strings && mhm_email_templates_vars.strings.content) || 'Content';
				const saveText     = (mhm_email_templates_vars.strings && mhm_email_templates_vars.strings.save) || 'Save';
				const cancelText   = (mhm_email_templates_vars.strings && mhm_email_templates_vars.strings.cancel) || 'Cancel';

				// Show template editing modal
				var modal = $(
					'<div class="template-edit-modal active">' +
					'<div class="template-edit-content">' +
					'<div class="template-edit-header">' +
					'<h3>' + editTitle + '</h3>' +
					'<button class="template-edit-close">&times;</button>' +
					'</div>' +
					'<div class="template-edit-body">' +
					'<form class="template-settings-form" data-template-key="' + templateKey + '">' +
					'<div class="form-group">' +
					'<label>' + subjectLabel + ':</label>' +
					'<input type="text" name="subject" value="" required>' +
					'</div>' +
					'<div class="form-group">' +
					'<label>' + contentLabel + ':</label>' +
					'<textarea name="content" rows="10" required></textarea>' +
					'</div>' +
					'<div class="form-actions">' +
					'<button type="submit" class="button button-primary">' + saveText + '</button>' +
					'<button type="button" class="button template-edit-close">' + cancelText + '</button>' +
					'</div>' +
					'</form>' +
					'</div>' +
					'</div>' +
					'</div>'
				);

				$( 'body' ).append( modal );

				// Load template data
				templateSettings.loadTemplateData( templateKey, modal );
			},

			loadTemplateData: function (templateKey, modal) {
				$.ajax(
					{
						url: mhm_email_templates_vars.ajax_url,
						type: 'POST',
						data: {
							action: 'mhm_get_template_data',
							template_key: templateKey,
							nonce: mhm_email_templates_vars.nonce
						},
						success: function (response) {
							if (response.success) {
								modal.find( 'input[name="subject"]' ).val( response.data.subject || '' );
								modal.find( 'textarea[name="content"]' ).val( response.data.content || '' );
							}
						}
					}
				);
			},

			saveTemplate: function (form) {
				var submitBtn    = form.find( 'button[type="submit"]' );
				var originalText = submitBtn.text();
				var templateKey  = form.data( 'template-key' );

				submitBtn.prop( 'disabled', true ).text( mhm_email_templates_vars.processing );

				$.ajax(
					{
						url: mhm_email_templates_vars.ajax_url,
						type: 'POST',
						data: {
							action: 'mhm_save_template',
							template_key: templateKey,
							subject: form.find( 'input[name="subject"]' ).val(),
							content: form.find( 'textarea[name="content"]' ).val(),
							nonce: mhm_email_templates_vars.nonce
						},
						success: function (response) {
							if (response.success) {
								const successMsg = (mhm_email_templates_vars.strings && mhm_email_templates_vars.strings.templateSaved) || 'Template saved successfully!';
								showNotice( successMsg, 'success' );
								$( '.template-edit-modal' ).remove();
								location.reload();
							} else {
								showNotice( response.data || mhm_email_templates_vars.error_occurred, 'error' );
							}
						},
						error: function () {
							showNotice( mhm_email_templates_vars.error_occurred, 'error' );
						},
						complete: function () {
							submitBtn.prop( 'disabled', false ).text( originalText );
						}
					}
				);
			},

			resetTemplate: function (templateKey) {
				$.ajax(
					{
						url: mhm_email_templates_vars.ajax_url,
						type: 'POST',
						data: {
							action: 'mhm_reset_template',
							template_key: templateKey,
							nonce: mhm_email_templates_vars.nonce
						},
						success: function (response) {
							if (response.success) {
								const successMsg = (mhm_email_templates_vars.strings && mhm_email_templates_vars.strings.templateReset) || 'Template reset to default!';
								showNotice( successMsg, 'success' );
								location.reload();
							} else {
								showNotice( response.data || mhm_email_templates_vars.error_occurred, 'error' );
							}
						},
						error: function () {
							showNotice( mhm_email_templates_vars.error_occurred, 'error' );
						}
					}
				);
			}
		};

		// Email variables
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
				const actionUrl = mhm_email_templates_vars.admin_post_url;
				const nonce     = mhm_email_templates_vars.send_test_nonce;
				const tpl       = document.getElementById( templateKeyId ).value;
				const bid       = document.getElementById( bookingIdId ).value;
				const st        = document.getElementById( statusId ).value;
				const to        = document.getElementById( toId ).value;

				// Build and submit a detached form (avoid nested form issues)
				const form   = document.createElement( 'form' );
				form.method  = 'POST';
				form.action  = actionUrl;
				const fields = {
					action: 'mhm_rentiva_send_template_test',
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
				btn.prop( 'disabled', true ).text( mhm_email_templates_vars.processing || 'Loading...' );
				container.css( 'opacity', '0.5' );

				// Optional: Add spinner to container
				container.html( '<div style="text-align:center; padding:40px;"><span class="spinner is-active" style="float:none; margin:0;"></span> Loading preview...</div>' );

				$.ajax(
					{
						url: mhm_email_templates_vars.ajax_url,
						type: 'POST',
						data: {
							action: 'mhm_rentiva_preview_email_ajax',
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
				btn.prop( 'disabled', true ).text( mhm_email_templates_vars.processing || 'Sending...' );

				$.ajax(
					{
						url: mhm_email_templates_vars.ajax_url,
						type: 'POST',
						data: {
							action: 'mhm_rentiva_send_test_email_ajax',
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
		emailPreview.init();
		testEmail.init();
		templateSettings.init();
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

		if (typeof mhm_email_templates_vars !== 'undefined' && mhm_email_templates_vars.auto_refresh) {
			setInterval(
				function () {
					// Auto-update statistics (optional)
				},
				30000
			); // Every 30 seconds
		}
	}
);
