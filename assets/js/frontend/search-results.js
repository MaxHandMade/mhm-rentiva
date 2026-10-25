/**
 * Search results (front-end standard, Aşama 2 PR 3).
 *
 * One instance per [data-rvs-results] root: filters, chips, sort, the grid/list
 * switch and the page buttons reload the results over AJAX, keeping the search
 * window and every filter; the header, chips, notice and empty states come back
 * as server-rendered HTML (the page's own partials). At 768 px and below the
 * filter column opens as a modal drawer. The comparison band follows the
 * `mhm-rentiva:compare-changed` event vehicle-interactions.js fires.
 */
( function ( $ ) {
	'use strict';

	const MOBILE = '(max-width: 768px)';
	const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

	function config() {
		return window.mhmRentivaSearchResults || {};
	}

	function t( key, fallback ) {
		const i18n = config().i18n || {};
		return i18n[ key ] || fallback;
	}

	function flag( value ) {
		const v = String( value === undefined || value === null ? '1' : value );
		return v === '1' || v === 'true' ? '1' : '0';
	}

	function stateOf( $root ) {
		let state = $root.data( 'rvsState' );
		if ( ! state ) {
			state = { loading: false, queued: null, timer: null, page: 1, drawer: null };
			$root.data( 'rvsState', state );
		}
		return state;
	}

	function currentView( $root ) {
		const pressed = $root.find( '[data-rvs-view][aria-pressed="true"]' ).data( 'rvsView' );
		if ( pressed === 'grid' || pressed === 'list' ) {
			return pressed;
		}
		return $root.data( 'defaultView' ) === 'list' ? 'list' : 'grid';
	}

	/**
	 * The filter form as the AJAX handler reads it: list fields as arrays.
	 */
	function formFields( $root ) {
		const out = {};
		// The search (window, keyword) first, then the filters.
		$root.find( '[data-rvs-window], [data-rvs-filters]' ).serializeArray().forEach( function ( item ) {
			if ( item.name.slice( -2 ) === '[]' ) {
				const key = item.name.slice( 0, -2 );
				( out[ key ] = out[ key ] || [] ).push( item.value );
			} else {
				out[ item.name ] = item.value;
			}
		} );
		return out;
	}

	function payload( $root, page ) {
		const sort = $root.find( '[data-rvs-sort]' );
		return Object.assign(
			{
				action: 'mhmrentiva_filter_results',
				nonce: config().nonce,
				layout: currentView( $root ),
				per_page: parseInt( $root.data( 'resultsPerPage' ), 10 ) || 12,
				page: page,
				base_url: window.location.href,
				show_favorite_button: flag( $root.data( 'showFavoriteButton' ) ),
				show_compare_button: flag( $root.data( 'showCompareButton' ) ),
				// The key the AJAX handler reads (it never read show_booking_btn).
				show_booking_button: flag( $root.data( 'showBookingBtn' ) ),
				show_price: flag( $root.data( 'showPrice' ) ),
				show_title: flag( $root.data( 'showTitle' ) ),
				show_features: flag( $root.data( 'showFeatures' ) ),
				show_rating: flag( $root.data( 'showRating' ) ),
				show_badges: flag( $root.data( 'showBadges' ) ),
			},
			// Without the sort control the page's default sort still applies.
			sort.length ? { [ sort.attr( 'name' ) ]: sort.val() } : { mhmrentiva_sort: String( $root.data( 'defaultSort' ) || '' ) },
			formFields( $root )
		);
	}

	function render( $root, data ) {
		const meta = data.meta || {};
		$root.find( '[data-rvs-grid]' ).html( data.html || '' );
		$root.find( '[data-rvs-pagination]' ).html( data.pagination || '' );
		$root.find( '[data-rvs-summary]' ).html( data.summary_html || '' );
		$root.find( '[data-rvs-chips]' ).html( data.chips_html || '' );
		$root.find( '[data-rvs-notice]' ).html( data.notice_html || '' );
		if ( meta.active_filters !== undefined ) {
			$root.find( '[data-rvs-filter-count]' ).text( meta.active_filters );
		}
		if ( meta.total !== undefined ) {
			$root.find( '[data-rvs-apply-count]' ).text( meta.total );
		}
		applyView( $root, currentView( $root ) );

		// Without `inert` the background was hidden control by control: controls
		// that just arrived (new cards) need the same treatment.
		const state = stateOf( $root );
		if ( state.drawer && ! ( 'inert' in HTMLElement.prototype ) ) {
			restoreBackground( state.drawer.background );
			state.drawer.background = setBackground( $root.find( '[data-rvs-drawer]' ).get( 0 ), true );
		}
	}

	function showError( $root, message ) {
		const $box = $( '<div class="rvs-empty" role="alert"></div>' );
		$box.append( $( '<h3></h3>' ).text( t( 'error', 'Error' ) ) );
		$box.append( $( '<p></p>' ).text( message ) );
		$box.append( $( '<button type="button" class="rvs-btn rvs-btn--primary"></button>' ).text( t( 'try_again', 'Try Again' ) ).on( 'click', function () {
			reload( $root, stateOf( $root ).page );
		} ) );
		$root.find( '[data-rvs-grid]' ).empty().append( $box );
	}

	/**
	 * Reload page `page` with the current filters. A request while one is in
	 * flight is queued (the last one wins), so no change is lost.
	 */
	function reload( $root, page ) {
		const state = stateOf( $root );
		state.page = page || 1;
		if ( state.loading ) {
			state.queued = state.page;
			return;
		}
		state.loading = true;
		$root.attr( 'aria-busy', 'true' );

		$.ajax( {
			url: config().ajax_url,
			type: 'POST',
			data: payload( $root, state.page ),
			success: function ( response ) {
				if ( response && response.success && response.data ) {
					render( $root, response.data );
				} else {
					showError( $root, ( response && response.data && response.data.message ) || t( 'error', 'Error' ) );
				}
			},
			error: function () {
				showError( $root, t( 'network_error', 'Network error. Please try again.' ) );
			},
			complete: function () {
				state.loading = false;
				$root.removeAttr( 'aria-busy' );
				if ( state.queued !== null ) {
					const next = state.queued;
					state.queued = null;
					reload( $root, next );
				}
			},
		} );
	}

	function applyView( $root, view ) {
		const $grid = $root.find( '[data-rvs-grid]' );
		$grid.toggleClass( 'rvs-card-grid', view === 'grid' ).toggleClass( 'rvs-card-list', view === 'list' );
		$grid.find( '[data-rvs-card]' ).removeClass( 'rvs-card--grid rvs-card--list' ).addClass( 'rvs-card--' + view );
		$root.find( '[data-rvs-view]' ).each( function () {
			$( this ).attr( 'aria-pressed', $( this ).data( 'rvsView' ) === view ? 'true' : 'false' );
		} );
	}

	function clearFilters( $root ) {
		const $form = $root.find( '[data-rvs-filters]' );
		$form.find( 'input[type="checkbox"]' ).prop( 'checked', false );
		$form.find( 'input[type="number"]' ).val( '' );
		reload( $root, 1 );
	}

	function clearChip( $root, name, value ) {
		const $form = $root.find( '[data-rvs-filters]' );
		$form.find( '[name]' ).filter( function () {
			return this.name === name;
		} ).each( function () {
			if ( this.type === 'checkbox' ) {
				if ( this.value === value ) {
					this.checked = false;
				}
			} else if ( this.type !== 'hidden' ) {
				this.value = '';
			}
		} );
		reload( $root, 1 );
	}

	/* Drawer --------------------------------------------------------------- */

	function setBackground( drawer, off ) {
		const changed = [];
		const useInert = 'inert' in HTMLElement.prototype;
		let node = drawer;
		while ( node && node.parentElement ) {
			Array.prototype.forEach.call( node.parentElement.children, function ( sibling ) {
				if ( sibling === node || sibling.tagName === 'SCRIPT' || sibling.tagName === 'STYLE' ) {
					return;
				}
				if ( off ) {
					if ( useInert ) {
						if ( ! sibling.inert ) {
							sibling.inert = true;
							changed.push( { el: sibling, inert: true } );
						}
					} else if ( sibling.getAttribute( 'aria-hidden' ) !== 'true' ) {
						sibling.setAttribute( 'aria-hidden', 'true' );
						const tabbables = Array.prototype.map.call( sibling.querySelectorAll( FOCUSABLE ), function ( el ) {
							const before = el.getAttribute( 'tabindex' );
							el.setAttribute( 'tabindex', '-1' );
							return { el: el, before: before };
						} );
						changed.push( { el: sibling, hidden: true, tabbables: tabbables } );
					}
				}
			} );
			node = node.parentElement;
		}
		return changed;
	}

	function restoreBackground( changed ) {
		( changed || [] ).forEach( function ( entry ) {
			if ( entry.inert ) {
				entry.el.inert = false;
			}
			if ( entry.hidden ) {
				entry.el.removeAttribute( 'aria-hidden' );
				entry.tabbables.forEach( function ( item ) {
					if ( item.before === null ) {
						item.el.removeAttribute( 'tabindex' );
					} else {
						item.el.setAttribute( 'tabindex', item.before );
					}
				} );
			}
		} );
	}

	function openDrawer( $root, opener ) {
		const state = stateOf( $root );
		const $drawer = $root.find( '[data-rvs-drawer]' );
		if ( ! $drawer.length || state.drawer ) {
			return;
		}
		const drawer = $drawer.get( 0 );
		const title = drawer.querySelector( '.rvs-results__filters-title' );

		$drawer.addClass( 'is-open' ).attr( { role: 'dialog', 'aria-modal': 'true' } );
		if ( title ) {
			title.setAttribute( 'tabindex', '-1' );
		}
		$( opener ).attr( 'aria-expanded', 'true' );
		$( 'body' ).addClass( 'rvs-drawer-open' );

		state.drawer = { opener: opener, background: setBackground( drawer, true ) };
		( title || drawer.querySelector( FOCUSABLE ) || drawer ).focus();
	}

	function closeDrawer( $root ) {
		const state = stateOf( $root );
		if ( ! state.drawer ) {
			return;
		}
		const $drawer = $root.find( '[data-rvs-drawer]' );
		restoreBackground( state.drawer.background );
		$drawer.removeClass( 'is-open' ).removeAttr( 'role aria-modal' );
		$drawer.find( '.rvs-results__filters-title' ).removeAttr( 'tabindex' );
		$( 'body' ).removeClass( 'rvs-drawer-open' );
		const opener = state.drawer.opener;
		state.drawer = null;
		if ( opener ) {
			$( opener ).attr( 'aria-expanded', 'false' );
			opener.focus();
		}
	}

	function trapFocus( $root, event ) {
		const state = stateOf( $root );
		if ( ! state.drawer ) {
			return;
		}
		if ( event.key === 'Escape' ) {
			event.preventDefault();
			closeDrawer( $root );
			return;
		}
		if ( event.key !== 'Tab' ) {
			return;
		}
		const drawer = $root.find( '[data-rvs-drawer]' ).get( 0 );
		const all = Array.prototype.slice.call( drawer.querySelectorAll( FOCUSABLE ) );
		const visible = all.filter( function ( el ) {
			return !! ( el.offsetWidth || el.offsetHeight || el.getClientRects().length );
		} );
		// A layout-less environment reports nothing visible: use every control.
		const items = visible.length ? visible : all;
		if ( ! items.length ) {
			return;
		}
		const first = items[ 0 ];
		const last = items[ items.length - 1 ];
		if ( event.shiftKey && ( document.activeElement === first || ! drawer.contains( document.activeElement ) || document.activeElement === drawer.querySelector( '.rvs-results__filters-title' ) ) ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && document.activeElement === last ) {
			event.preventDefault();
			first.focus();
		}
	}

	/* Comparison band ------------------------------------------------------- */

	function renderBand( band, items, max ) {
		const count = items.length;
		const format = count === 1 ? t( 'band_one', '%1$s vehicle selected · up to %2$s' ) : t( 'band_many', '%1$s vehicles selected · up to %2$s' );
		const limit = max || parseInt( band.getAttribute( 'data-max' ), 10 ) || 3;
		band.setAttribute( 'data-count', String( count ) );
		band.setAttribute( 'data-max', String( limit ) );
		$( band ).find( '[data-rvs-band-count]' ).text( format.replace( '%1$s', count ).replace( '%2$s', limit ) );
		$( band ).find( '[data-rvs-band-names]' ).text( items.map( function ( item ) {
			return item.title;
		} ).join( ', ' ) );
	}

	/* Wiring ---------------------------------------------------------------- */

	function init( $root ) {
		if ( $root.data( 'rvsReady' ) ) {
			return;
		}
		$root.data( 'rvsReady', true );
		const state = stateOf( $root );

		$root.on( 'change', '[data-rvs-filters] input[type="checkbox"]', function () {
			reload( $root, 1 );
		} );
		$root.on( 'input', '[data-rvs-filters] input[type="number"]', function () {
			clearTimeout( state.timer );
			state.timer = setTimeout( function () {
				reload( $root, 1 );
			}, 500 );
		} );
		$root.on( 'submit', '[data-rvs-filters]', function ( e ) {
			e.preventDefault();
			reload( $root, 1 );
		} );
		$root.on( 'change', '[data-rvs-sort]', function () {
			reload( $root, 1 );
		} );
		$root.on( 'click', '[data-rvs-clear]', function ( e ) {
			e.preventDefault();
			clearFilters( $root );
		} );
		$root.on( 'click', '[data-rvs-chip]', function ( e ) {
			e.preventDefault();
			clearChip( $root, String( $( this ).data( 'name' ) ), String( $( this ).data( 'value' ) ) );
		} );
		$root.on( 'click', '[data-rvs-view]', function () {
			const view = $( this ).data( 'rvsView' );
			if ( view === 'grid' || view === 'list' ) {
				applyView( $root, view );
			}
		} );
		$root.on( 'click', '[data-rvs-page]', function ( e ) {
			e.preventDefault();
			reload( $root, parseInt( $( this ).data( 'rvsPage' ), 10 ) || 1 );
			const top = $root.find( '.rvs-results__main' ).get( 0 );
			if ( top && top.scrollIntoView ) {
				top.scrollIntoView( { block: 'start' } );
			}
		} );
		// "Change" scrolls to a search strip on the same page instead of leaving.
		$root.on( 'click', '[data-rvs-change]', function ( e ) {
			const strip = document.querySelector( '[data-rvs-search]' );
			if ( ! strip ) {
				return;
			}
			e.preventDefault();
			closeDrawer( $root );
			if ( strip.scrollIntoView ) {
				strip.scrollIntoView( { block: 'start' } );
			}
			const field = strip.querySelector( 'select, input:not([type="hidden"])' );
			if ( field ) {
				field.focus();
			}
		} );

		$root.on( 'click', '[data-rvs-drawer-open]', function () {
			openDrawer( $root, this );
		} );
		$root.on( 'click', '[data-rvs-drawer-close], [data-rvs-drawer-apply]', function () {
			closeDrawer( $root );
		} );
		$( document ).on( 'keydown', function ( e ) {
			trapFocus( $root, e );
		} );
		if ( window.matchMedia ) {
			const query = window.matchMedia( MOBILE );
			const leave = function () {
				if ( ! query.matches ) {
					closeDrawer( $root );
				}
			};
			if ( query.addEventListener ) {
				query.addEventListener( 'change', leave );
			}
		}
	}

	$( function () {
		$( '[data-rvs-results]' ).each( function () {
			init( $( this ) );
		} );
	} );

	$( document ).on( 'mhm-rentiva:compare-changed', function ( event, data ) {
		if ( ! data || ! Array.isArray( data.items ) ) {
			return;
		}
		document.querySelectorAll( '[data-rvs-compare-band]' ).forEach( function ( band ) {
			renderBand( band, data.items, data.max );
		} );
	} );
}( jQuery ) );
