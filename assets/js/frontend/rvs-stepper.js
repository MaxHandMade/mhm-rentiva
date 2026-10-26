/**
 * Rentiva front-end standard -- quantity stepper.
 *
 * Markup (one per add-on):
 *   <div class="rvs-stepper" role="group" aria-labelledby="…" data-rvs-stepper="<id>" data-min="0" data-max="3">
 *     <button type="button" data-rvs-step="-1" aria-label="Decrease">−</button>
 *     <output aria-live="polite">1</output>
 *     <button type="button" data-rvs-step="1" aria-label="Increase">+</button>
 *     <input type="hidden" name="addons[]" value="<id>">
 *     <input type="hidden" name="addon_qty[<id>]" value="1">
 *   </div>
 *
 * The range is data-min..data-max (0..max, 1..max for a required add-on). At
 * zero both hidden fields are disabled, so the add-on is not in the request at
 * all. The button that reached a limit is disabled; if it had focus, focus
 * moves to the other button instead of falling to the page. Each change
 * dispatches `rvs:stepper-change` (bubbling) with detail { id, value }.
 *
 * window.MHMRvsStepper = { init( root ) } -- binds every [data-rvs-stepper]
 * under root (or root itself) once; calling it again is harmless.
 */
( function () {
	'use strict';

	if ( window.MHMRvsStepper ) {
		return;
	}

	const bound = new WeakSet();

	function toInt( value, fallback ) {
		const n = parseInt( value, 10 );
		return isNaN( n ) ? fallback : n;
	}

	function parts( el ) {
		const qty = el.querySelector( 'input[name^="addon_qty["]' );
		let id = el.getAttribute( 'data-rvs-stepper' ) || '';
		if ( ! id && qty ) {
			const match = /^addon_qty\[(.*)\]$/.exec( qty.name );
			id = match ? match[ 1 ] : '';
		}
		const min = Math.max( 0, toInt( el.getAttribute( 'data-min' ), 0 ) );
		return {
			id: id,
			min: min,
			max: Math.max( min, toInt( el.getAttribute( 'data-max' ), min ) ),
			dec: el.querySelector( '[data-rvs-step="-1"]' ),
			inc: el.querySelector( '[data-rvs-step="1"]' ),
			out: el.querySelector( 'output' ),
			qty: qty,
			addon: el.querySelector( 'input[name="addons[]"]' ),
		};
	}

	function current( p ) {
		if ( p.qty && p.qty.value !== '' ) {
			return toInt( p.qty.value, p.min );
		}
		return p.out ? toInt( p.out.textContent, p.min ) : p.min;
	}

	function render( p, value ) {
		if ( p.out ) {
			p.out.textContent = String( value );
		}
		if ( p.qty ) {
			p.qty.value = String( value );
			p.qty.disabled = value === 0;
		}
		if ( p.addon ) {
			p.addon.disabled = value === 0;
		}
		if ( p.dec ) {
			p.dec.disabled = value <= p.min;
		}
		if ( p.inc ) {
			p.inc.disabled = value >= p.max;
		}
	}

	function clamp( p, value ) {
		return Math.min( p.max, Math.max( p.min, value ) );
	}

	function bind( el ) {
		if ( bound.has( el ) ) {
			return;
		}
		bound.add( el );

		const initial = parts( el );
		render( initial, clamp( initial, current( initial ) ) );

		el.addEventListener( 'click', function ( event ) {
			const button = event.target.closest ? event.target.closest( '[data-rvs-step]' ) : null;
			if ( ! button || ! el.contains( button ) || button.disabled ) {
				return;
			}
			// Read the range on every press: the caller may change data-max.
			const p = parts( el );
			const before = current( p );
			const value = clamp( p, before + ( toInt( button.getAttribute( 'data-rvs-step' ), 0 ) < 0 ? -1 : 1 ) );
			if ( value === before ) {
				return;
			}
			const hadFocus = document.activeElement === button;
			render( p, value );
			if ( hadFocus && button.disabled ) {
				const other = button === p.dec ? p.inc : p.dec;
				if ( other && ! other.disabled ) {
					other.focus();
				}
			}
			el.dispatchEvent( new CustomEvent( 'rvs:stepper-change', {
				bubbles: true,
				detail: { id: p.id, value: value },
			} ) );
		} );
	}

	function init( root ) {
		const scope = root || document;
		if ( scope.nodeType === 1 && scope.hasAttribute( 'data-rvs-stepper' ) ) {
			bind( scope );
		}
		Array.prototype.forEach.call( scope.querySelectorAll( '[data-rvs-stepper]' ), bind );
	}

	window.MHMRvsStepper = {
		init: init,
	};
}() );
