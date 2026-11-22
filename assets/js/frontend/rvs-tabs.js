/**
 * Rentiva front-end standard -- one row of page tabs with a "More" menu.
 *
 * The list is an ordinary <ul> of links (WooCommerce's account menu, the vendor
 * panel's). From 783px up, the items that do not fit move into a disclosure
 * button at the end of the row; the current page's item always stays in the
 * row. At 782px and below, or without this script, the row scrolls sideways.
 *
 * Lists: `.woocommerce-MyAccount-navigation > ul` and `[data-rvs-tabs]`.
 */
( function () {
	'use strict';

	const WIDE = '(min-width: 783px)';
	const config = window.mhmRentivaRvsTabs || {};
	let count = 0;

	function isCurrent( item ) {
		return item.classList.contains( 'is-active' ) || item.classList.contains( 'is-current' ) || !! item.querySelector( ':scope > a[aria-current="page"]' );
	}

	/**
	 * Which items stay in the row: in order, as many as fit beside the button,
	 * the current one among them whatever its place.
	 *
	 * @param {number[]} widths  Item widths, in order.
	 * @param {number}   current Index of the current item, or -1.
	 * @param {number}   room    Row width.
	 * @param {number}   button  Width of the button.
	 * @return {boolean[]} Per item, whether it stays.
	 */
	function fit( widths, current, room, button ) {
		const total = widths.reduce( ( sum, width ) => sum + width, 0 );
		if ( total <= room ) {
			return widths.map( () => true );
		}
		let left = room - button - ( current >= 0 ? widths[ current ] : 0 );
		let open = true;
		return widths.map( ( width, index ) => {
			if ( index === current ) {
				return true;
			}
			open = open && width <= left;
			if ( open ) {
				left -= width;
			}
			return open;
		} );
	}

	function setup( list ) {
		const items = Array.from( list.children ).filter( ( child ) => child.tagName === 'LI' );
		if ( items.length < 2 || list.dataset.rvsTabsReady ) {
			return;
		}
		list.dataset.rvsTabsReady = '1';
		count += 1;

		const more = document.createElement( 'li' );
		const button = document.createElement( 'button' );
		const menu = document.createElement( 'ul' );
		more.className = 'rvs-tabs-more';
		button.type = 'button';
		button.className = 'rvs-tabs-more__button';
		button.textContent = config.more || 'More';
		button.setAttribute( 'aria-expanded', 'false' );
		button.setAttribute( 'aria-controls', 'rvs-tabs-menu-' + count );
		menu.className = 'rvs-tabs-more__menu';
		menu.id = 'rvs-tabs-menu-' + count;
		menu.hidden = true;
		more.append( button, menu );

		function toggle( open ) {
			button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			menu.hidden = ! open;
		}

		function layout() {
			// Moving an element drops its focus; it is given back at the end.
			const focused = list.contains( document.activeElement ) ? document.activeElement : null;
			const wasOpen = ! menu.hidden;
			place();
			if ( ! focused ) {
				return;
			}
			const inMenu = menu.contains( focused );
			if ( inMenu && wasOpen ) {
				toggle( true );
			}
			const target = list.contains( focused ) && ( ! inMenu || wasOpen ) ? focused : ( list.contains( button ) ? button : items[ 0 ].querySelector( 'a' ) );
			if ( target ) {
				target.focus( { preventScroll: true } );
			}
		}

		function place() {
			toggle( false );
			more.remove();
			items.forEach( ( item ) => list.append( item ) );
			list.classList.remove( 'has-rvs-more' );
			const current = items.findIndex( isCurrent );

			if ( ! window.matchMedia( WIDE ).matches ) {
				const item = items[ current ];
				if ( item && list.scrollWidth > list.clientWidth ) {
					list.scrollLeft = item.offsetLeft - list.offsetLeft - ( list.clientWidth - item.offsetWidth ) / 2;
				}
				return;
			}

			// Measured in the row as it will be drawn: no scroll container, the
			// button beside the items.
			list.classList.add( 'has-rvs-more' );
			list.append( more );
			const widths = items.map( ( item ) => item.getBoundingClientRect().width );
			const stays = fit( widths, current, list.clientWidth, more.getBoundingClientRect().width );
			if ( stays.every( Boolean ) ) {
				more.remove();
				return;
			}
			items.forEach( ( item, index ) => {
				if ( ! stays[ index ] ) {
					menu.append( item );
				}
			} );
		}

		button.addEventListener( 'click', () => toggle( menu.hidden ) );
		more.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'Escape' && ! menu.hidden ) {
				toggle( false );
				button.focus();
			}
		} );
		more.addEventListener( 'focusout', ( event ) => {
			if ( event.relatedTarget && ! more.contains( event.relatedTarget ) ) {
				toggle( false );
			}
		} );
		document.addEventListener( 'click', ( event ) => {
			if ( ! more.contains( event.target ) ) {
				toggle( false );
			}
		} );

		layout();
		const wide = window.matchMedia( WIDE );
		if ( wide.addEventListener ) {
			wide.addEventListener( 'change', layout );
		}
		let width = list.clientWidth;
		const again = () => {
			if ( list.clientWidth !== width ) {
				width = list.clientWidth;
				// Outside the observer's own delivery: the new row may change its height.
				window.requestAnimationFrame( layout );
			}
		};
		if ( 'ResizeObserver' in window ) {
			new window.ResizeObserver( again ).observe( list );
		} else {
			window.addEventListener( 'resize', again );
		}
		if ( document.fonts && document.fonts.ready ) {
			document.fonts.ready.then( layout );
		}
	}

	function init() {
		document.querySelectorAll( '.woocommerce-MyAccount-navigation > ul, ul[data-rvs-tabs]' ).forEach( setup );
	}

	window.mhmRentivaRvsTabsFit = fit;

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
