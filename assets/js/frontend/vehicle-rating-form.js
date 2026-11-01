/**
 * Rating form (PR F): field errors under their fields, a lasting answer, edit in
 * place, delete through the shared rvs dialog. One instance per [data-rvs-rating]:
 * nothing is looked up outside its root, so two forms on a page stay separate.
 *
 * The stars are native radios, so the arrow keys and the selected state are the
 * browser's. After a change the list and the summary come from the server: the page
 * reloads and the answer survives it in sessionStorage. Where storage is refused the
 * answer stays on the page instead, without the reload.
 *
 * Text only ever goes in as text.
 */

( function () {
	'use strict';

	const TIMEOUT = 15000;
	const NOTICE_KEY = 'mhmRentivaRatingNotice';

	const config = () => window.mhmVehicleRating || {};
	const text = ( key ) => ( config().strings || {} )[ key ] || '';
	const fill = ( template, ...values ) => values.reduce(
		( out, value, i ) => out.replace( '%' + ( i + 1 ) + '$s', String( value ) ),
		template
	);
	const EMAIL_SHAPE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

	function formatNumber( value ) {
		try {
			return new Intl.NumberFormat( document.documentElement.lang || undefined ).format( value );
		} catch ( error ) {
			return String( value );
		}
	}

	/** The server counts mb_strlen( trim() ): code points of the trimmed text. */
	function lengthOf( value ) {
		return Array.from( value.trim() ).length;
	}

	function post( data ) {
		const controller = new AbortController();
		const timer = setTimeout( () => controller.abort(), TIMEOUT );
		return fetch( config().ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: new URLSearchParams( data ),
			signal: controller.signal,
		} ).then( ( response ) => response.json() ).finally( () => clearTimeout( timer ) );
	}

	function storeNotice( vehicleId, notice ) {
		try {
			window.sessionStorage.setItem( NOTICE_KEY, JSON.stringify( Object.assign( { vehicleId }, notice ) ) );
			return true;
		} catch ( error ) {
			return false;
		}
	}

	function takeNotice( vehicleId ) {
		try {
			const raw = window.sessionStorage.getItem( NOTICE_KEY );
			if ( ! raw ) {
				return null;
			}
			const notice = JSON.parse( raw );
			if ( String( notice.vehicleId ) !== String( vehicleId ) ) {
				return null;
			}
			window.sessionStorage.removeItem( NOTICE_KEY );
			return notice;
		} catch ( error ) {
			return null;
		}
	}

	class RatingForm {
		constructor( root ) {
			this.root = root;
			this.vehicleId = root.getAttribute( 'data-vehicle-id' );
			this.status = root.querySelector( '[data-rvs-rating-status]' );
			this.form = root.querySelector( '[data-rvs-rating-form]' );
			this.dialog = root.querySelector( '[data-rvs-review-dialog]' );
			this.busy = false;
			this.deleteOpener = null;

			if ( this.form ) {
				this.initForm();
			}
			if ( this.dialog ) {
				this.initDialog();
			}
			root.addEventListener( 'click', ( event ) => this.handleCardClick( event ) );

			const notice = takeNotice( this.vehicleId );
			if ( notice ) {
				this.showNotice( notice.title, notice.message );
			}
		}

		/* Form ------------------------------------------------------------- */

		initForm() {
			this.comment = this.form.querySelector( 'textarea[name="comment"]' );
			this.count = this.form.querySelector( '[data-rvs-rating-count]' );
			this.alert = this.form.querySelector( '[data-rvs-rating-alert]' );
			this.submit = this.form.querySelector( '[type="submit"]' );
			this.cancel = this.form.querySelector( '[data-rvs-rating-cancel]' );
			this.heading = this.form.querySelector( '[data-rvs-rating-title]' );
			this.submitLabel = this.submit.textContent;
			this.headingLabel = this.heading ? this.heading.textContent : '';
			this.min = parseInt( this.comment.getAttribute( 'data-min-length' ), 10 ) || 0;
			this.max = parseInt( this.comment.getAttribute( 'data-max-length' ), 10 ) || 0;

			this.form.addEventListener( 'submit', ( event ) => {
				event.preventDefault();
				this.handleSubmit();
			} );
			this.form.addEventListener( 'input', ( event ) => {
				this.clearFieldError( event.target );
				if ( event.target === this.comment ) {
					this.updateCount();
				}
			} );
			this.form.addEventListener( 'change', ( event ) => this.clearFieldError( event.target ) );
			if ( this.cancel ) {
				this.cancel.addEventListener( 'click', () => this.leaveEdit() );
			}
			this.updateCount();
		}

		updateCount() {
			const written = formatNumber( lengthOf( this.comment.value ) );
			const max = formatNumber( this.max );
			this.count.textContent = this.min > 0
				? fill( text( 'count_min' ), written, max, formatNumber( this.min ) )
				: fill( text( 'count' ), written, max );
		}

		validate() {
			const errors = [];
			const name = this.form.elements.guest_name;
			const email = this.form.elements.guest_email;
			if ( name && ! name.value.trim() ) {
				errors.push( [ name, text( 'name_required' ) ] );
			}
			if ( email && ! EMAIL_SHAPE.test( email.value.trim() ) ) {
				errors.push( [ email, text( 'email_required' ) ] );
			}
			if ( ! this.form.querySelector( 'input[name="rating"]:checked' ) ) {
				errors.push( [ this.form.querySelector( '[data-rvs-rating-stars]' ), text( 'select_rating' ) ] );
			}
			const length = lengthOf( this.comment.value );
			if ( this.min > 0 && length < this.min ) {
				errors.push( [ this.comment, fill( text( 'comment_short' ), formatNumber( this.min ) ) ] );
			} else if ( this.max > 0 && length > this.max ) {
				errors.push( [ this.comment, fill( text( 'comment_long' ), formatNumber( this.max ) ) ] );
			}
			return errors;
		}

		/** @param {Element} target An input, or the stars' fieldset. */
		setFieldError( target, message ) {
			const field = target.closest( '[data-rvs-field]' );
			const error = document.createElement( 'span' );
			error.id = target.id + '-error';
			error.className = 'rvs-error';
			error.textContent = message;
			field.appendChild( error );
			if ( 'FIELDSET' !== target.tagName ) {
				target.setAttribute( 'aria-invalid', 'true' );
			}
			const described = target.getAttribute( 'aria-describedby' );
			target.setAttribute( 'aria-describedby', described ? described + ' ' + error.id : error.id );
		}

		clearFieldError( control ) {
			const field = control && control.closest ? control.closest( '[data-rvs-field]' ) : null;
			const error = field ? field.querySelector( '.rvs-error' ) : null;
			if ( ! error ) {
				return;
			}
			const target = field.matches( 'fieldset' ) ? field : field.querySelector( '[aria-invalid="true"]' );
			if ( target ) {
				target.removeAttribute( 'aria-invalid' );
				const rest = ( target.getAttribute( 'aria-describedby' ) || '' ).split( ' ' ).filter( ( id ) => id && id !== error.id );
				if ( rest.length ) {
					target.setAttribute( 'aria-describedby', rest.join( ' ' ) );
				} else {
					target.removeAttribute( 'aria-describedby' );
				}
			}
			error.remove();
		}

		clearErrors() {
			this.form.querySelectorAll( '[data-rvs-field] .rvs-error' ).forEach( ( error ) => this.clearFieldError( error ) );
			this.alert.hidden = true;
			this.alert.textContent = '';
		}

		showAlert( message ) {
			this.alert.textContent = '';
			const title = document.createElement( 'strong' );
			title.textContent = text( 'send_failed' );
			const body = document.createElement( 'span' );
			body.textContent = message;
			this.alert.append( title, body );
			this.alert.hidden = false;
		}

		handleSubmit() {
			if ( this.busy ) {
				return;
			}
			this.clearErrors();

			const errors = this.validate();
			if ( errors.length ) {
				errors.forEach( ( [ target, message ] ) => this.setFieldError( target, message ) );
				const first = errors[ 0 ][ 0 ];
				( 'FIELDSET' === first.tagName ? first.querySelector( 'input' ) : first ).focus();
				return;
			}

			const data = {
				action: 'mhmrentiva_submit_rating',
				vehicle_id: this.vehicleId,
				rating: this.form.querySelector( 'input[name="rating"]:checked' ).value,
				comment: this.comment.value,
				nonce: this.form.elements.nonce ? this.form.elements.nonce.value : config().nonce,
			};
			if ( this.form.elements.guest_name ) {
				data.guest_name = this.form.elements.guest_name.value;
				data.guest_email = this.form.elements.guest_email.value;
			}

			this.setBusy( true );
			post( data ).then( ( response ) => {
				this.setBusy( false );
				if ( ! response.success ) {
					this.showAlert( ( response.data && response.data.message ) || text( 'connection' ) );
					return;
				}
				this.answer( response.data || {} );
			} ).catch( () => {
				this.setBusy( false );
				this.showAlert( text( 'connection' ) );
			} );
		}

		setBusy( busy ) {
			this.busy = busy;
			this.submit.disabled = busy;
			if ( busy ) {
				this.submit.textContent = text( 'sending' );
			} else {
				this.submit.textContent = this.editing ? text( 'update' ) : this.submitLabel;
			}
		}

		answer( data ) {
			if ( data.reload && storeNotice( this.vehicleId, { title: data.title, message: data.message } ) ) {
				window.location.reload();
				return;
			}
			// A guest's review that waits for approval is listed for nobody: the form
			// empties and the answer stays. So does any answer without storage.
			this.form.reset();
			this.leaveEdit( false );
			this.updateCount();
			this.showNotice( data.title, data.message );
		}

		showNotice( title, message ) {
			this.status.textContent = '';
			const box = document.createElement( 'div' );
			box.className = 'rvs-notice rvs-notice--success';
			const heading = document.createElement( 'strong' );
			heading.textContent = title || '';
			const body = document.createElement( 'span' );
			body.textContent = message || '';
			box.append( heading, body );
			this.status.appendChild( box );
			this.status.setAttribute( 'tabindex', '-1' );
			this.status.focus();
		}

		/* Edit ------------------------------------------------------------- */

		enterEdit( button ) {
			if ( ! this.form ) {
				return;
			}
			this.editing = true;
			this.editOpener = button;
			this.clearErrors();
			this.status.textContent = '';
			this.form.hidden = false;
			this.comment.value = button.getAttribute( 'data-comment' ) || '';
			const star = this.form.querySelector( 'input[name="rating"][value="' + parseInt( button.getAttribute( 'data-rating' ), 10 ) + '"]' );
			if ( star ) {
				star.checked = true;
			}
			this.submit.textContent = text( 'update' );
			if ( this.heading ) {
				this.heading.textContent = text( 'edit_heading' );
			}
			this.cancel.hidden = false;
			this.updateCount();
			this.comment.focus();
		}

		leaveEdit( restoreFocus = true ) {
			if ( ! this.editing ) {
				return;
			}
			this.editing = false;
			this.clearErrors();
			this.form.reset();
			this.form.hidden = true;
			this.cancel.hidden = true;
			this.submit.textContent = this.submitLabel;
			if ( this.heading ) {
				this.heading.textContent = this.headingLabel;
			}
			this.updateCount();
			if ( restoreFocus && this.editOpener ) {
				this.editOpener.focus();
			}
		}

		/* Delete ----------------------------------------------------------- */

		handleCardClick( event ) {
			const target = event.target instanceof Element ? event.target : null;
			if ( ! target ) {
				return;
			}
			const edit = target.closest( '[data-rvs-review-edit]' );
			if ( edit ) {
				this.enterEdit( edit );
				return;
			}
			const remove = target.closest( '[data-rvs-review-delete]' );
			if ( remove ) {
				this.openDialog( remove );
			}
		}

		initDialog() {
			this.dialogConfirm = this.dialog.querySelector( '[data-rvs-review-dialog-confirm]' );
			this.dialogKeep = this.dialog.querySelector( '[data-rvs-review-dialog-keep]' );
			this.dialogMessage = this.dialog.querySelector( '[data-rvs-review-dialog-message]' );
			this.dialogKeep.addEventListener( 'click', () => window.mhmRentivaRvsDialog.close( this.dialog ) );
			this.dialogConfirm.addEventListener( 'click', () => this.confirmDelete() );
		}

		openDialog( opener ) {
			if ( ! this.dialog ) {
				return;
			}
			this.commentId = opener.closest( '[data-comment-id]' ).getAttribute( 'data-comment-id' );
			this.dialogMessage.hidden = true;
			this.dialogMessage.textContent = '';
			this.dialog.hidden = false;
			this.dialog.classList.add( 'is-open' );
			window.mhmRentivaRvsDialog.open( this.dialog, {
				opener,
				labelledBy: this.dialog.id + '-title',
				initialFocus: this.dialogKeep,
				onClose: () => {
					this.dialog.classList.remove( 'is-open' );
					this.dialog.hidden = true;
				},
			} );
		}

		confirmDelete() {
			if ( this.busy ) {
				return;
			}
			this.busy = true;
			this.dialogConfirm.disabled = true;
			const fail = ( message ) => {
				this.busy = false;
				this.dialogConfirm.disabled = false;
				this.dialogMessage.textContent = message;
				this.dialogMessage.hidden = false;
			};
			post( {
				action: 'mhmrentiva_delete_rating',
				comment_id: this.commentId,
				nonce: config().nonce,
			} ).then( ( response ) => {
				if ( ! response.success ) {
					fail( ( response.data && response.data.message ) || text( 'connection' ) );
					return;
				}
				storeNotice( this.vehicleId, { title: text( 'deleted_title' ), message: text( 'deleted' ) } );
				window.location.reload();
			} ).catch( () => fail( text( 'connection' ) ) );
		}
	}

	function init() {
		document.querySelectorAll( '[data-rvs-rating]' ).forEach( ( root ) => new RatingForm( root ) );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
