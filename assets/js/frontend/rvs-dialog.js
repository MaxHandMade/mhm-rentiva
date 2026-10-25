/**
 * Rentiva front-end standard -- modal dialog.
 *
 * One dialog at a time. open() gives the element dialog semantics, makes the
 * rest of the page inert (or, where `inert` is missing, aria-hidden with its
 * controls out of the tab order, restored exactly on close), moves focus in,
 * keeps Tab inside and closes on Escape. close() puts everything back and
 * returns focus to the control that opened it.
 *
 * Used by the results page's filter drawer and the vehicle page's gallery.
 *
 * window.mhmRentivaRvsDialog = { open( el, options ), close( el ), current(), refresh() }
 * options: { opener, labelledBy, initialFocus, bodyClass, onClose }
 */
( function () {
	'use strict';

	if ( window.mhmRentivaRvsDialog ) {
		return;
	}

	const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

	let active = null;

	function useInert() {
		return 'inert' in HTMLElement.prototype;
	}

	function hideBackground( el ) {
		const changed = [];
		const inert = useInert();
		let node = el;
		while ( node && node.parentElement ) {
			Array.prototype.forEach.call( node.parentElement.children, function ( sibling ) {
				if ( sibling === node || sibling.tagName === 'SCRIPT' || sibling.tagName === 'STYLE' ) {
					return;
				}
				if ( inert ) {
					if ( ! sibling.inert ) {
						sibling.inert = true;
						changed.push( { el: sibling, inert: true } );
					}
				} else if ( sibling.getAttribute( 'aria-hidden' ) !== 'true' ) {
					const before = sibling.getAttribute( 'aria-hidden' );
					sibling.setAttribute( 'aria-hidden', 'true' );
					const tabbables = Array.prototype.map.call( sibling.querySelectorAll( FOCUSABLE ), function ( item ) {
						const was = item.getAttribute( 'tabindex' );
						item.setAttribute( 'tabindex', '-1' );
						return { el: item, before: was };
					} );
					changed.push( { el: sibling, hidden: true, before: before, tabbables: tabbables } );
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
				if ( entry.before === null ) {
					entry.el.removeAttribute( 'aria-hidden' );
				} else {
					entry.el.setAttribute( 'aria-hidden', entry.before );
				}
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

	function current() {
		// A dialog the page removed (an AJAX re-render) no longer counts.
		if ( active && ! document.documentElement.contains( active.el ) ) {
			restoreBackground( active.background );
			document.body.classList.remove( active.bodyClass );
			active = null;
		}
		return active ? active.el : null;
	}

	function open( el, options ) {
		const opts = options || {};
		if ( ! el || current() ) {
			return;
		}
		const focusTarget = opts.initialFocus || el.querySelector( FOCUSABLE ) || el;
		const addedTabindex = ! focusTarget.hasAttribute( 'tabindex' ) && ( focusTarget === el || ! focusTarget.matches( FOCUSABLE ) );

		el.setAttribute( 'role', 'dialog' );
		el.setAttribute( 'aria-modal', 'true' );
		if ( opts.labelledBy ) {
			el.setAttribute( 'aria-labelledby', opts.labelledBy );
		}
		if ( addedTabindex ) {
			focusTarget.setAttribute( 'tabindex', '-1' );
		}

		active = {
			el: el,
			opener: opts.opener || null,
			onClose: typeof opts.onClose === 'function' ? opts.onClose : null,
			bodyClass: opts.bodyClass || 'rvs-dialog-open',
			labelled: !! opts.labelledBy,
			focusTarget: addedTabindex ? focusTarget : null,
			background: hideBackground( el ),
		};
		document.body.classList.add( active.bodyClass );
		focusTarget.focus();
	}

	function close( el ) {
		if ( ! active || ( el && el !== active.el ) ) {
			return;
		}
		const state = active;
		active = null;

		restoreBackground( state.background );
		state.el.removeAttribute( 'role' );
		state.el.removeAttribute( 'aria-modal' );
		if ( state.labelled ) {
			state.el.removeAttribute( 'aria-labelledby' );
		}
		if ( state.focusTarget ) {
			state.focusTarget.removeAttribute( 'tabindex' );
		}
		document.body.classList.remove( state.bodyClass );
		if ( state.onClose ) {
			state.onClose();
		}
		if ( state.opener && document.documentElement.contains( state.opener ) ) {
			state.opener.focus();
		}
	}

	/**
	 * Re-hide the background after content arrived behind the dialog (only
	 * matters without `inert`, where controls are hidden one by one).
	 */
	function refresh() {
		if ( ! current() || useInert() ) {
			return;
		}
		restoreBackground( active.background );
		active.background = hideBackground( active.el );
	}

	function onKeydown( event ) {
		if ( ! current() ) {
			return;
		}
		if ( event.key === 'Escape' ) {
			event.preventDefault();
			close( active.el );
			return;
		}
		if ( event.key !== 'Tab' ) {
			return;
		}
		const el = active.el;
		const all = Array.prototype.slice.call( el.querySelectorAll( FOCUSABLE ) );
		const visible = all.filter( function ( item ) {
			return !! ( item.offsetWidth || item.offsetHeight || item.getClientRects().length );
		} );
		// A layout-less environment reports nothing visible: use every control.
		const items = visible.length ? visible : all;
		if ( ! items.length ) {
			event.preventDefault();
			return;
		}
		const first = items[ 0 ];
		const last = items[ items.length - 1 ];
		const focused = document.activeElement;
		if ( event.shiftKey && ( focused === first || items.indexOf( focused ) === -1 ) ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && ( focused === last || ! el.contains( focused ) ) ) {
			event.preventDefault();
			first.focus();
		}
	}

	document.addEventListener( 'keydown', onKeydown );

	window.mhmRentivaRvsDialog = {
		open: open,
		close: close,
		current: current,
		refresh: refresh,
	};
}() );
