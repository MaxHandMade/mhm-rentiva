/**
 * Contact form (PR F): topic selector, field errors under their fields, the
 * permanent success box. One instance per [data-rvs-contact]: nothing is looked
 * up outside its root, so two forms on a page stay separate.
 *
 * Text only ever goes in as text; the icon is the server's SVG, parsed inert.
 */

( function () {
	'use strict';

	const REDIRECT_DELAY = 1500;
	const EMAIL_SHAPE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

	const config = () => window.mhmContactForm || {};
	const text = ( key ) => ( config().messages || {} )[ key ] || '';

	function topicsOf( element, attribute ) {
		return ( element.getAttribute( attribute ) || '' ).split( ' ' ).filter( Boolean );
	}

	function warningIcon() {
		const svg = ( config().icons || {} ).warning;
		if ( ! svg ) {
			return null;
		}
		const icon = new DOMParser().parseFromString( svg, 'text/html' ).body.firstElementChild;
		return icon ? document.importNode( icon, true ) : null;
	}

	class ContactForm {
		constructor( root ) {
			this.root = root;
			this.form = root.querySelector( 'form' );
			this.status = root.querySelector( '.rvs-contact__status' );
			this.submit = this.form.querySelector( '[type="submit"]' );
			this.busy = false;

			this.form.addEventListener( 'submit', ( event ) => {
				event.preventDefault();
				this.handleSubmit();
			} );
			this.form.addEventListener( 'input', ( event ) => this.clearFieldError( event.target ) );
			this.form.addEventListener( 'change', ( event ) => {
				this.clearFieldError( event.target );
				if ( 'radio' === event.target.type && 'type' === event.target.name ) {
					this.applyTopic( event.target.value );
				}
			} );

			this.initTopic();
			this.initFile();
		}

		/* Topic ------------------------------------------------------------ */

		initTopic() {
			// A restored page can come back with another radio chosen than the server printed.
			const checked = this.form.querySelector( 'input[type="radio"][name="type"]:checked' );
			if ( checked ) {
				this.applyTopic( checked.value );
			}
		}

		applyTopic( topic ) {
			this.root.dataset.formType = topic;

			this.form.querySelectorAll( '[data-rvs-topics]' ).forEach( ( field ) => {
				const shown = topicsOf( field, 'data-rvs-topics' ).includes( topic );
				field.hidden = ! shown;
				// A hidden field is not sent: a booking chosen under "My booking" must
				// not travel with a general question.
				field.querySelectorAll( 'input, select, textarea' ).forEach( ( control ) => {
					control.disabled = ! shown;
					if ( ! shown ) {
						this.clearFieldError( control );
					}
				} );
			} );

			this.form.querySelectorAll( '[data-rvs-required-topics]' ).forEach( ( field ) => {
				const required = topicsOf( field, 'data-rvs-required-topics' ).includes( topic );
				const control = field.querySelector( 'input, select, textarea' );
				const label = field.querySelector( 'label' );
				if ( control ) {
					control.required = required;
				}
				if ( label ) {
					label.textContent = label.getAttribute( required ? 'data-rvs-label-required' : 'data-rvs-label-optional' );
				}
			} );
		}

		/* File ------------------------------------------------------------- */

		initFile() {
			const input = this.form.querySelector( 'input[type="file"]' );
			const remove = this.form.querySelector( '[data-rvs-file-remove]' );
			if ( ! input || ! remove ) {
				return;
			}
			const name = this.form.querySelector( '[data-rvs-file-name]' );
			const empty = name ? name.textContent : '';
			const showName = ( value ) => {
				if ( name ) {
					name.textContent = value || empty;
				}
			};
			input.addEventListener( 'change', () => {
				remove.hidden = 0 === input.files.length;
				showName( input.files.length ? input.files[ 0 ].name : '' );
			} );
			remove.addEventListener( 'click', () => {
				input.value = '';
				remove.hidden = true;
				showName( '' );
				this.clearFieldError( input );
				input.focus();
			} );
		}

		fileError( input ) {
			const file = input.files && input.files[ 0 ];
			if ( ! file ) {
				return '';
			}
			const extension = file.name.split( '.' ).pop().toLowerCase();
			if ( ! ( config().allowedFileTypes || [] ).includes( extension ) ) {
				return text( 'file_type' );
			}
			const max = config().maxFileSize || 0;
			return max > 0 && file.size > max ? text( 'file_size' ) : '';
		}

		/* Validation ------------------------------------------------------- */

		validate() {
			const errors = {};
			const required = ( config().messages || {} ).required || {};

			Array.from( this.form.elements ).forEach( ( control ) => {
				if ( control.disabled || ! control.name || errors[ control.name ] ) {
					return;
				}
				if ( 'file' === control.type ) {
					const message = this.fileError( control );
					if ( message ) {
						errors[ control.name ] = message;
					}
					return;
				}
				const empty = 'checkbox' === control.type ? ! control.checked : '' === control.value.trim();
				if ( control.required && empty ) {
					errors[ control.name ] = required[ control.name ] || text( 'required_fields' );
				} else if ( 'email' === control.type && ! empty && ! EMAIL_SHAPE.test( control.value.trim() ) ) {
					errors[ control.name ] = text( 'invalid_email' );
				}
			} );

			return errors;
		}

		controlFor( name ) {
			const control = this.form.elements[ name ];
			if ( ! control ) {
				return null;
			}
			const first = control instanceof Element ? control : control[ 0 ];
			return first && ! first.disabled && first.closest( '[data-rvs-field]' ) ? first : null;
		}

		/**
		 * Marks each field and moves focus to the first one. Returns what had no
		 * field to sit under.
		 */
		showFieldErrors( errors ) {
			const homeless = [];
			Object.keys( errors ).forEach( ( name ) => {
				const control = this.controlFor( name );
				if ( control ) {
					this.setFieldError( control, errors[ name ] );
				} else {
					homeless.push( errors[ name ] );
				}
			} );

			const first = this.form.querySelector( '[aria-invalid="true"]' );
			if ( first ) {
				first.focus();
			}
			return homeless;
		}

		setFieldError( control, message ) {
			this.clearFieldError( control );

			const error = document.createElement( 'span' );
			error.id = control.id + '-error';
			error.className = 'rvs-error';
			error.dataset.rvsErrorFor = control.name;
			const icon = warningIcon();
			if ( icon ) {
				error.appendChild( icon );
			}
			const body = document.createElement( 'span' );
			body.textContent = message;
			error.appendChild( body );
			control.closest( '[data-rvs-field]' ).appendChild( error );

			control.setAttribute( 'aria-invalid', 'true' );
			const described = control.getAttribute( 'aria-describedby' );
			control.setAttribute( 'aria-describedby', described ? described + ' ' + error.id : error.id );
		}

		clearFieldError( control ) {
			const field = control.closest ? control.closest( '[data-rvs-field]' ) : null;
			const error = field ? field.querySelector( '.rvs-error' ) : null;
			if ( ! error ) {
				return;
			}
			const first = this.controlFor( error.dataset.rvsErrorFor ) || control;
			first.removeAttribute( 'aria-invalid' );
			const rest = ( first.getAttribute( 'aria-describedby' ) || '' ).split( ' ' ).filter( ( id ) => id && id !== error.id );
			if ( rest.length ) {
				first.setAttribute( 'aria-describedby', rest.join( ' ' ) );
			} else {
				first.removeAttribute( 'aria-describedby' );
			}
			error.remove();
		}

		clearAllErrors() {
			this.form.querySelectorAll( '[data-rvs-field]' ).forEach( ( field ) => {
				const control = field.querySelector( 'input, select, textarea' );
				if ( control ) {
					this.clearFieldError( control );
				}
			} );
			this.clearAlert();
		}

		/* Form-wide message ------------------------------------------------ */

		clearAlert() {
			const alert = this.form.querySelector( '.rvs-notice--alert' );
			if ( alert ) {
				alert.remove();
			}
		}

		showAlert( message ) {
			this.clearAlert();
			const alert = document.createElement( 'div' );
			alert.className = 'rvs-notice rvs-notice--alert';
			alert.setAttribute( 'role', 'alert' );
			alert.textContent = message;
			this.form.insertBefore( alert, this.form.querySelector( '.rvs-contact__actions' ) );
		}

		/* Submit ----------------------------------------------------------- */

		handleSubmit() {
			if ( this.busy ) {
				return;
			}
			this.clearAllErrors();

			const errors = this.validate();
			if ( Object.keys( errors ).length ) {
				this.showFieldErrors( errors );
				return;
			}

			const formData = new FormData( this.form );
			formData.append( 'action', 'mhmrentiva_submit_contact_form' );
			formData.append( 'nonce', config().nonce );

			const label = this.submit.textContent;
			this.busy = true;
			this.submit.disabled = true;
			this.submit.textContent = text( 'submitting' );

			fetch( config().ajaxUrl, { method: 'POST', body: formData } )
				.then( ( response ) => response.json() )
				.then( ( data ) => {
					if ( data.success ) {
						this.showSuccess( data.data || {} );
						return;
					}
					this.showFailure( data.data || {} );
				} )
				.catch( () => this.showAlert( text( 'error' ) ) )
				.finally( () => {
					this.busy = false;
					this.submit.disabled = false;
					this.submit.textContent = label;
				} );
		}

		showFailure( data ) {
			const homeless = this.showFieldErrors( data.errors || {} );
			const anyMarked = this.form.querySelector( '[aria-invalid="true"]' );
			if ( homeless.length ) {
				this.showAlert( homeless.join( ' ' ) );
			} else if ( ! anyMarked ) {
				this.showAlert( data.message || text( 'error' ) );
			}
		}

		showSuccess( data ) {
			const number = parseInt( data.message_id, 10 );
			const heading = document.createElement( 'strong' );
			heading.textContent = number > 0 ? text( 'success_title' ).replace( '%s', String( number ) ) : text( 'success_title_plain' );

			// The block/widget "Success Message" setting wins over the default sentence.
			const message = this.root.dataset.successMessage || text( 'success_body' );
			const body = document.createElement( 'span' );
			body.textContent = message;

			const box = document.createElement( 'div' );
			box.className = 'rvs-notice rvs-notice--success';
			box.tabIndex = -1;
			box.appendChild( heading );
			box.appendChild( body );

			this.form.reset();
			this.form.hidden = true;
			this.status.appendChild( box );
			// The focused button went away with the form.
			box.focus();

			// Same-site only: the server validated it (wp_validate_redirect) before printing it.
			// Left after a short delay, so the box above is seen first.
			const redirect = this.root.dataset.redirectUrl;
			if ( redirect ) {
				setTimeout( () => window.location.assign( redirect ), REDIRECT_DELAY );
			}
		}
	}

	function init() {
		document.querySelectorAll( '[data-rvs-contact]' ).forEach( ( root ) => {
			if ( root.querySelector( 'form' ) ) {
				new ContactForm( root );
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
