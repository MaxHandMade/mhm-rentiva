/**
 * Vehicle comparison (PR E): remove, "only differences", and what follows from them.
 *
 * Every [data-rvs-comparison] keeps its own state and touches nothing outside its
 * root. The server draws the page; this script only recomputes what a removal
 * changes, by the drawing's rule: a cell differs when its value occurs once in the
 * row. Below two vehicles the visitor's list reloads (the server's empty state);
 * a fixed list (vehicle_ids) never touches the visitor's list and shows the
 * empty state it was printed with.
 *
 * Toggles go through MHMRentivaCompare (vehicle-interactions.js): one at a time,
 * so two quick clicks cannot overwrite each other's cookie.
 */
( function ( $ ) {
	'use strict';

	const READY = 'rvsCmpReady';

	function config() {
		return window.mhmRentivaVehicleComparison || {};
	}

	function string( key ) {
		return ( config().strings || {} )[ key ] || '';
	}

	function all( root, selector ) {
		return Array.prototype.slice.call( root.querySelectorAll( selector ) );
	}

	function toastError( message ) {
		if ( window.MHMRentivaToast ) {
			window.MHMRentivaToast.show( message, { type: 'error' } );
		}
	}

	function vehicleIds( root ) {
		return all( root, '[data-rvs-cmp-vehicle]' ).map( ( el ) => el.getAttribute( 'data-vehicle-id' ) );
	}

	function isFixed( root ) {
		return '1' === root.getAttribute( 'data-fixed' );
	}

	function setHidden( el, hidden ) {
		el.hidden = hidden;
	}

	/**
	 * Highlights, cues and the rows and group headings that show.
	 */
	function recalc( root ) {
		const only = root.querySelector( '[data-rvs-cmp-only]' );
		const onlyDifferences = !! ( only && only.checked );
		const byRow = {};

		all( root, '[data-rvs-cmp-cell]' ).forEach( ( cell ) => {
			const key = cell.getAttribute( 'data-row' );
			( byRow[ key ] = byRow[ key ] || [] ).push( cell );
		} );

		const hiddenRow = {};
		Object.keys( byRow ).forEach( ( key ) => {
			const cells = byRow[ key ];
			const values = cells.map( ( cell ) => cell.getAttribute( 'data-row-value' ) );

			cells.forEach( ( cell, index ) => {
				const different = cells.length > 1 && 1 === values.filter( ( value ) => value === values[ index ] ).length;
				cell.classList.toggle( 'is-different', different );
				const cue = cell.querySelector( '[data-rvs-cmp-cue]' );
				if ( cue ) {
					setHidden( cue, ! different );
				}
			} );

			const allSame = values.every( ( value ) => value === values[ 0 ] );
			const allEmpty = cells.every( ( cell ) => '1' === cell.getAttribute( 'data-empty' ) );
			hiddenRow[ key ] = allEmpty || ( onlyDifferences && allSame );
		} );

		all( root, '[data-rvs-cmp-row]' ).forEach( ( row ) => {
			setHidden( row, !! hiddenRow[ row.getAttribute( 'data-rvs-cmp-row' ) ] );
		} );

		let anyRow = false;
		all( root, '[data-rvs-cmp-group]' ).forEach( ( group ) => {
			const visible = all( group, '[data-rvs-cmp-row]' ).some( ( row ) => ! row.hidden );
			anyRow = anyRow || visible;
			all( group, '[data-rvs-cmp-group-head]' ).forEach( ( head ) => setHidden( head, ! visible ) );
		} );

		const none = root.querySelector( '[data-rvs-cmp-none]' );
		if ( none ) {
			setHidden( none, anyRow || ! onlyDifferences );
		}
	}

	/**
	 * The fixed list's empty state, already on the page.
	 */
	function showEmpty( root ) {
		[ '[data-rvs-cmp-body]', '[data-rvs-cmp-meta]', '[data-rvs-cmp-only-label]' ].forEach( ( selector ) => {
			all( root, selector ).forEach( ( el ) => setHidden( el, true ) );
		} );
		const empty = root.querySelector( '[data-rvs-cmp-empty]' );
		if ( empty ) {
			setHidden( empty, false );
		}
	}

	/**
	 * The header line, the add column and the differences, for the vehicles left.
	 */
	function refresh( root ) {
		const count = vehicleIds( root ).length;

		if ( count < 2 ) {
			if ( isFixed( root ) ) {
				showEmpty( root );
			} else {
				api.reload();
			}
			return;
		}

		let headers = {};
		try {
			headers = JSON.parse( root.getAttribute( 'data-headers' ) || '{}' );
		} catch ( e ) {
			headers = {};
		}
		const meta = root.querySelector( '[data-rvs-cmp-meta]' );
		if ( meta && headers[ count ] ) {
			meta.textContent = headers[ count ];
		}

		const room = count < parseInt( root.getAttribute( 'data-max-vehicles' ), 10 );
		all( root, '[data-rvs-cmp-add], [data-rvs-cmp-filler]' ).forEach( ( el ) => setHidden( el, ! room ) );

		const hasAdd = !! root.querySelector( '[data-rvs-cmp-add]' ) && room;
		all( root, '[data-rvs-cmp-span]' ).forEach( ( el ) => el.setAttribute( 'colspan', String( 1 + count + ( hasAdd ? 1 : 0 ) ) ) );

		recalc( root );
	}

	function drop( root, vehicleId ) {
		all( root, '[data-rvs-cmp-col]' ).forEach( ( el ) => {
			if ( el.getAttribute( 'data-vehicle-id' ) === String( vehicleId ) ) {
				el.parentNode.removeChild( el );
			}
		} );
		refresh( root );

		// The button that had the focus is gone.
		const next = root.querySelector( '[data-rvs-cmp-remove]' ) || root.querySelector( '[data-rvs-cmp-only]' );
		if ( next && ! next.closest( '[hidden]' ) ) {
			next.focus();
		}
	}

	function remove( root, button ) {
		const vehicleId = button.getAttribute( 'data-vehicle-id' );

		if ( isFixed( root ) ) {
			drop( root, vehicleId );
			return;
		}
		if ( ! window.MHMRentivaCompare ) {
			toastError( string( 'error' ) );
			return;
		}

		button.disabled = true;
		window.MHMRentivaCompare.toggle( vehicleId, config().toggle_nonce, {
			success( response ) {
				if ( ! response || ! response.success ) {
					button.disabled = false;
					toastError( ( response && response.data && response.data.message ) || string( 'error' ) );
					return;
				}
				// Not on the list any more (another tab, another comparison): the toggle
				// just added it back. Only the server's page can be right now.
				if ( 'removed' !== response.data.action ) {
					api.reload();
					return;
				}
				drop( root, vehicleId );
			},
			error() {
				button.disabled = false;
				toastError( string( 'error' ) );
			},
		} );
	}

	function start( root ) {
		if ( root.dataset[ READY ] ) {
			return;
		}
		root.dataset[ READY ] = '1';

		root.addEventListener( 'click', ( event ) => {
			const button = event.target.closest ? event.target.closest( '[data-rvs-cmp-remove]' ) : null;
			if ( button && root.contains( button ) && ! button.disabled ) {
				event.preventDefault();
				remove( root, button );
			}
		} );

		root.addEventListener( 'change', ( event ) => {
			if ( event.target.matches && event.target.matches( '[data-rvs-cmp-only]' ) ) {
				recalc( root );
			}
		} );

		recalc( root );
	}

	function roots() {
		return all( document, '[data-rvs-comparison]' ).filter( ( root ) => ! root.closest( '[data-rv-render="preview"]' ) );
	}

	const api = {
		init() {
			roots().forEach( start );
		},
		// Named so a test can watch it; jsdom cannot.
		reload() {
			window.location.reload();
		},
	};

	window.mhmRentivaComparisonClient = api;

	// Another comparison on the page, or another control, changed the visitor's list.
	$( document ).on( 'mhm-rentiva:compare-changed', ( event, data ) => {
		if ( ! data || ! Array.isArray( data.items ) ) {
			return;
		}
		const listed = data.items.map( ( item ) => String( item.id ) );
		roots().forEach( ( root ) => {
			if ( isFixed( root ) ) {
				return;
			}
			vehicleIds( root ).forEach( ( id ) => {
				if ( -1 === listed.indexOf( id ) ) {
					drop( root, id );
				}
			} );
		} );
	} );

	$( () => api.init() );
}( jQuery ) );
