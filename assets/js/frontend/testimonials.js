/**
 * Testimonials (PR F): "Load more" and the slider. One instance per
 * [data-rvs-testimonials]; nothing is looked up outside its root, so two lists on a
 * page stay separate.
 *
 * A card added by "Load more" is a clone of the server's own card, kept hidden in the page, and the
 * review's words go in as textContent: no markup is ever built from a string.
 *
 * The slider is a scroll-snap track, so touch, wheel and the keyboard (the track is
 * focusable) scroll it natively. The buttons and dots only move that scroll position.
 * Auto-rotate has a visible pause/play button, stops while the pointer or focus is
 * inside and on touch, and never starts under prefers-reduced-motion.
 */

( function () {
	'use strict';

	const TIMEOUT = 15000;
	const ROTATE_MS = 5000;

	const config = () => window.mhmRentivaTestimonials || {};
	const text = ( key ) => ( config().strings || {} )[ key ] || '';
	const fill = ( template, ...values ) => values.reduce(
		( out, value, i ) => out.replace( '%' + ( i + 1 ) + '$s', String( value ) ),
		template
	);

	const reducedMotion = () => !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );

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

	/** A data-* setting of the root, as a string ('' when absent). */
	const setting = ( root, name, fallback ) => {
		const value = root.getAttribute( 'data-' + name );
		return null === value || '' === value ? fallback : value;
	};

	const SVG = 'http://www.w3.org/2000/svg';

	/** One star, as partials/rvs/review-stars.php draws it. */
	function star( filled ) {
		const svg = document.createElementNS( SVG, 'svg' );
		svg.setAttribute( 'class', 'rvs-review-stars__star' + ( filled ? ' is-filled' : '' ) );
		[ [ 'width', '14' ], [ 'height', '14' ], [ 'viewBox', '0 0 24 24' ], [ 'stroke', 'currentColor' ],
			[ 'stroke-width', '2' ], [ 'aria-hidden', 'true' ], [ 'focusable', 'false' ] ].forEach( ( [ name, value ] ) => svg.setAttribute( name, value ) );
		const path = document.createElementNS( SVG, 'path' );
		path.setAttribute( 'd', 'M12 3l2.8 5.9 6.2.9-4.5 4.4 1.1 6.3L12 17.6l-5.6 2.9 1.1-6.3L3 9.8l6.2-.9z' );
		svg.appendChild( path );
		return svg;
	}

	class Testimonials {
		constructor( root ) {
			this.root = root;
			this.items = root.querySelector( '[data-rvs-items]' );
			this.button = root.querySelector( '[data-rvs-load-more]' );
			this.error = root.querySelector( '[data-rvs-error]' );
			this.live = root.querySelector( '[data-rvs-live]' );
			this.template = root.querySelector( '[data-rvs-card-template]' );
			this.dots = root.querySelector( '[data-rvs-dots]' );
			this.prev = root.querySelector( '[data-rvs-prev]' );
			this.next = root.querySelector( '[data-rvs-next]' );
			this.controls = root.querySelector( '[data-rvs-controls]' );
			this.pauseButton = root.querySelector( '[data-rvs-pause]' );
			this.userPaused = false;
			this.busy = false;
			this.current = 0;
			this.timer = null;
			this.paused = false;

			if ( this.button ) {
				this.button.addEventListener( 'click', () => this.loadMore() );
			}
			if ( this.dots && this.items ) {
				this.initSlider();
			}
		}

		/* Slider ------------------------------------------------------------ */

		slides() {
			return Array.from( this.items.querySelectorAll( '[data-rvs-card]' ) );
		}

		initSlider() {
			this.prev.addEventListener( 'click', () => this.step( -1 ) );
			this.next.addEventListener( 'click', () => this.step( 1 ) );
			this.dots.addEventListener( 'click', ( event ) => {
				const dot = event.target.closest( 'button' );
				if ( dot ) {
					this.goTo( Array.from( this.dots.children ).indexOf( dot ) );
				}
			} );

			let frame = 0;
			this.items.addEventListener( 'scroll', () => {
				if ( frame ) {
					return;
				}
				frame = window.requestAnimationFrame( () => {
					frame = 0;
					this.syncFromScroll();
				} );
			}, { passive: true } );

			const hold = () => {
				this.paused = true;
				this.stopRotate();
			};
			const release = () => {
				this.paused = false;
				this.startRotate();
			};
			if ( this.pauseButton ) {
				this.pauseButton.addEventListener( 'click', () => this.setUserPaused( ! this.userPaused ) );
				// A touch has no "leave": it pauses until the visitor starts it again.
				// The button itself is left out: its own click decides, or a tap on it would
				// pause here and the click would toggle it straight back.
				this.root.addEventListener( 'touchstart', ( event ) => {
					if ( ! this.pauseButton.contains( event.target ) ) {
						this.setUserPaused( true );
					}
				}, { passive: true } );
			}
			this.root.addEventListener( 'mouseenter', hold );
			this.root.addEventListener( 'mouseleave', release );
			this.root.addEventListener( 'focusin', hold );
			this.root.addEventListener( 'focusout', ( event ) => {
				if ( ! this.root.contains( event.relatedTarget ) ) {
					release();
				}
			} );
			document.addEventListener( 'visibilitychange', () => {
				if ( document.hidden ) {
					this.stopRotate();
				} else {
					this.startRotate();
				}
			} );
			if ( window.matchMedia ) {
				const query = window.matchMedia( '(prefers-reduced-motion: reduce)' );
				const onChange = () => {
					this.showPauseButton();
					return query.matches ? this.stopRotate() : this.startRotate();
				};
				if ( query.addEventListener ) {
					query.addEventListener( 'change', onChange );
				}
			}
			this.showPauseButton();

			this.startRotate();
		}

		/** Nothing rotates under reduced motion, so there is nothing to pause. */
		showPauseButton() {
			if ( this.pauseButton ) {
				this.pauseButton.hidden = reducedMotion();
			}
		}

		setUserPaused( paused ) {
			this.userPaused = paused;
			if ( this.pauseButton ) {
				const label = text( paused ? 'play' : 'pause' );
				if ( label ) {
					this.pauseButton.setAttribute( 'aria-label', label );
				}
				// SVG elements have no .hidden property: the attribute is what the CSS reads.
				this.pauseButton.querySelector( '[data-rvs-pause-icon]' ).toggleAttribute( 'hidden', paused );
				this.pauseButton.querySelector( '[data-rvs-play-icon]' ).toggleAttribute( 'hidden', ! paused );
			}
			if ( paused ) {
				this.stopRotate();
			} else {
				this.startRotate();
			}
		}

		step( by ) {
			const total = this.slides().length;
			this.goTo( ( this.current + by + total ) % total );
		}

		goTo( index ) {
			const slides = this.slides();
			const slide = slides[ index ];
			if ( ! slide ) {
				return;
			}
			this.current = index;
			this.mark();
			this.items.scrollTo( {
				left: slide.offsetLeft - this.items.offsetLeft,
				behavior: reducedMotion() ? 'auto' : 'smooth',
			} );
		}

		/** The dot that matches the scroll position, once a swipe or key moved it. */
		syncFromScroll() {
			const slides = this.slides();
			const left = this.items.scrollLeft + this.items.offsetLeft;
			const atEnd = this.items.scrollLeft + this.items.clientWidth >= this.items.scrollWidth - 2;
			let nearest = 0;
			let best = Infinity;
			slides.forEach( ( slide, i ) => {
				const distance = Math.abs( slide.offsetLeft - left );
				if ( distance < best ) {
					best = distance;
					nearest = i;
				}
			} );
			if ( atEnd ) {
				// The last cards cannot reach the left edge: a dot chosen among the
				// visible ones stays, otherwise the end of the track is the last card.
				const chosen = slides[ this.current ];
				this.current = chosen && chosen.offsetLeft >= left - 2 && this.current >= nearest ? this.current : slides.length - 1;
			} else {
				this.current = nearest;
			}
			this.mark();
		}

		mark() {
			Array.from( this.dots.children ).forEach( ( dot, i ) => {
				if ( i === this.current ) {
					dot.setAttribute( 'aria-current', 'true' );
				} else {
					dot.removeAttribute( 'aria-current' );
				}
			} );
		}

		startRotate() {
			this.stopRotate();
			const wanted = '1' === this.root.getAttribute( 'data-auto-rotate' );
			if ( ! wanted || this.paused || this.userPaused || reducedMotion() || document.hidden || this.slides().length < 2 ) {
				return;
			}
			this.timer = setInterval( () => this.step( 1 ), ROTATE_MS );
		}

		stopRotate() {
			if ( this.timer ) {
				clearInterval( this.timer );
				this.timer = null;
			}
		}

		/** One dot per card, after "Load more" added some. */
		rebuildDots() {
			if ( ! this.dots ) {
				return;
			}
			const total = this.slides().length;
			const label = text( 'slideLabel' );
			while ( this.dots.children.length < total ) {
				const number = this.dots.children.length + 1;
				const dot = document.createElement( 'button' );
				dot.type = 'button';
				dot.className = 'rvs-testimonials__dot';
				dot.setAttribute( 'aria-label', label ? fill( label, number, total ) : String( number ) );
				dot.appendChild( document.createElement( 'span' ) );
				this.dots.appendChild( dot );
			}
			Array.from( this.dots.children ).forEach( ( dot, i ) => {
				if ( label ) {
					dot.setAttribute( 'aria-label', fill( label, i + 1, total ) );
				}
			} );
			this.mark();
			// A list that began with one review had its controls hidden.
			if ( this.controls ) {
				this.controls.hidden = total < 2;
			}
			this.startRotate();
		}

		/* Load more --------------------------------------------------------- */

		loadMore() {
			if ( this.busy || ! this.template ) {
				return;
			}
			this.busy = true;
			const label = this.button.textContent;
			this.button.disabled = true;
			this.button.setAttribute( 'aria-busy', 'true' );
			this.button.textContent = text( 'loading' ) || label;
			this.error.hidden = true;
			this.error.textContent = '';

			const page = parseInt( this.button.getAttribute( 'data-page' ), 10 ) || 1;
			post( {
				action: 'mhmrentiva_load_testimonials',
				nonce: config().nonce || '',
				page: page + 1,
				limit: setting( this.root, 'limit', '5' ),
				rating: setting( this.root, 'rating', '' ),
				vehicle_id: setting( this.root, 'vehicle-id', '' ),
				orderby: setting( this.root, 'orderby', 'date' ),
				order: setting( this.root, 'order', 'DESC' ),
				seed: setting( this.root, 'seed', '' ),
			} ).then( ( response ) => {
				if ( ! response.success ) {
					throw new Error( ( response.data && response.data.message ) || '' );
				}
				const cards = response.data.cards || [];
				cards.forEach( ( card ) => this.items.appendChild( this.build( card ) ) );
				this.button.setAttribute( 'data-page', String( page + 1 ) );
				this.rebuildDots();
				if ( cards.length && this.live ) {
					this.live.textContent = text( 'loaded' );
				}
				if ( ! response.data.has_more || ! cards.length ) {
					this.button.closest( '.rvs-testimonials__more' ).hidden = true;
				}
			} ).catch( () => {
				this.error.textContent = text( 'error' );
				this.error.hidden = false;
			} ).finally( () => {
				this.busy = false;
				this.button.disabled = false;
				this.button.removeAttribute( 'aria-busy' );
				this.button.textContent = label;
			} );
		}

		/** The server's own card, filled with text. A part the row lacks is removed. */
		build( card ) {
			const node = this.template.firstElementChild.cloneNode( true );
			const put = ( slot, value ) => {
				const el = node.querySelector( '[data-rvs-slot="' + slot + '"]' );
				if ( ! el ) {
					return;
				}
				if ( value ) {
					el.textContent = value;
				} else {
					el.remove();
				}
			};
			put( 'text', card.text ? '“' + card.text + '”' : '' );
			put( 'initial', card.initial );
			put( 'name', card.name );
			put( 'vehicle', card.vehicle );
			put( 'date', card.date );

			const stars = node.querySelector( '[data-rvs-stars]' );
			if ( stars ) {
				const rating = parseInt( card.rating, 10 ) || 0;
				if ( rating > 0 ) {
					stars.setAttribute( 'aria-label', card.stars_label || '' );
					for ( let i = 0; i < 5; i++ ) {
						stars.appendChild( star( i < rating ) );
					}
				} else {
					stars.remove();
				}
			}
			return node;
		}
	}

	function init() {
		document.querySelectorAll( '[data-rvs-testimonials]' ).forEach( ( root ) => new Testimonials( root ) );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
