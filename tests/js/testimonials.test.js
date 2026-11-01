/**
 * @jest-environment jsdom
 */

/**
 * PR F: Load more clones the server's card and fills it with text, the slider's
 * indicators name their reviews, auto-rotate stops on hover and focus and never starts
 * under reduced motion, and two lists on a page stay separate.
 */

const card = ( extra = '' ) => `
	<article class="rvs-tcard" data-rvs-card ${ extra }>
		<span class="rvs-review-stars" role="img" aria-label="" data-rvs-stars></span>
		<p class="rvs-tcard__text" data-rvs-slot="text"></p>
		<div class="rvs-tcard__author">
			<span class="rvs-tcard__avatar" data-rvs-slot="initial"></span>
			<span class="rvs-tcard__info">
				<strong data-rvs-slot="name"></strong>
				<span data-rvs-slot="vehicle"></span>
				<span data-rvs-slot="date"></span>
			</span>
		</div>
	</article>`;

const instance = ( id, { carousel = false, rotate = '0', more = true, count = 2 } = {} ) => `
	<div class="rvs-testimonials" id="${ id }" data-rvs-testimonials data-layout="${ carousel ? 'carousel' : 'grid' }"
		data-limit="2" data-auto-rotate="${ rotate }" data-orderby="rating" data-seed="${ id.length }">
		<div data-rvs-items>
			<article class="rvs-tcard" data-rvs-card><p>first</p></article>
			${ 2 === count ? '<article class="rvs-tcard" data-rvs-card><p>second</p></article>' : '' }
		</div>
		${ carousel ? `
		<div class="rvs-testimonials__controls" data-rvs-controls ${ 2 === count ? '' : 'hidden' }>
			<button type="button" data-rvs-prev>prev</button>
			<div data-rvs-dots>
				<button type="button" aria-label="Review 1 of ${ count }" aria-current="true"><span></span></button>
				${ 2 === count ? '<button type="button" aria-label="Review 2 of 2"><span></span></button>' : '' }
			</div>
			<button type="button" data-rvs-next>next</button>
			${ '1' === rotate ? '<button type="button" data-rvs-pause aria-label="Pause automatic rotation"><svg data-rvs-pause-icon></svg><svg data-rvs-play-icon hidden></svg></button>' : '' }
		</div>` : '' }
		${ more ? `
		<div class="rvs-testimonials__more">
			<button type="button" data-rvs-load-more data-page="1">Load more reviews</button>
			<p role="alert" data-rvs-error hidden></p>
		</div>
		<div hidden data-rvs-card-template>${ card() }</div>` : '' }
		<span role="status" data-rvs-live></span>
	</div>`;

const flush = async () => {
	for ( let i = 0; i < 10; i++ ) {
		await Promise.resolve();
	}
};

const answer = ( body ) => Promise.resolve( { json: () => Promise.resolve( body ) } );

function load( html ) {
	document.body.innerHTML = html;
	jest.isolateModules( () => {
		require( '../../assets/js/frontend/testimonials.js' );
	} );
}

const row = ( over = {} ) => Object.assign( {
	text: 'Great car',
	initial: 'J',
	name: 'John D.',
	vehicle: 'Egea',
	date: '14/09/2026',
	rating: 4,
	stars_label: '4 out of 5 stars',
}, over );

describe( 'testimonials', () => {
	let reduced;

	beforeEach( () => {
		jest.useFakeTimers();
		reduced = false;
		window.matchMedia = () => ( { get matches() { return reduced; }, addEventListener: () => {} } );
		window.mhmRentivaTestimonials = {
			ajaxUrl: '/ajax',
			nonce: 'n1',
			strings: { loading: 'Loading...', loaded: 'More reviews loaded.', error: 'Could not load.', slideLabel: 'Review %1$s of %2$s', pause: 'Pause automatic rotation', play: 'Start automatic rotation' },
		};
		window.HTMLElement.prototype.scrollTo = jest.fn();
		window.requestAnimationFrame = ( fn ) => setTimeout( fn, 0 );
		global.fetch = jest.fn();
	} );

	afterEach( () => {
		jest.useRealTimers();
		document.body.innerHTML = '';
	} );

	test( 'load more fills a clone with text only and sends the list\'s own order', async () => {
		global.fetch.mockReturnValue( answer( { success: true, data: { cards: [ row( { text: '<img src=x onerror=alert(1)>', name: '<b>Eve</b> K.' } ) ], has_more: true } } ) );
		load( instance( 'a' ) );

		document.querySelector( '[data-rvs-load-more]' ).click();
		await flush();

		const body = new URLSearchParams( global.fetch.mock.calls[ 0 ][ 1 ].body );
		expect( body.get( 'orderby' ) ).toBe( 'rating' );
		expect( body.get( 'page' ) ).toBe( '2' );
		expect( body.get( 'nonce' ) ).toBe( 'n1' );

		const cards = document.querySelectorAll( '[data-rvs-items] [data-rvs-card]' );
		expect( cards ).toHaveLength( 3 );
		const added = cards[ 2 ];
		expect( added.querySelector( 'img' ) ).toBeNull();
		expect( added.querySelector( 'b' ) ).toBeNull();
		expect( added.querySelector( '[data-rvs-slot="text"]' ).textContent ).toBe( '“<img src=x onerror=alert(1)>”' );
		expect( added.querySelector( '[data-rvs-slot="name"]' ).textContent ).toBe( '<b>Eve</b> K.' );
		// Five stars drawn by the script, four filled, labelled.
		expect( added.querySelectorAll( '.rvs-review-stars__star' ) ).toHaveLength( 5 );
		expect( added.querySelectorAll( '.is-filled' ) ).toHaveLength( 4 );
		expect( added.querySelector( '.rvs-review-stars' ).getAttribute( 'aria-label' ) ).toBe( '4 out of 5 stars' );
		expect( document.querySelector( '[data-rvs-live]' ).textContent ).toBe( 'More reviews loaded.' );
		expect( document.querySelector( '[data-rvs-load-more]' ).getAttribute( 'data-page' ) ).toBe( '2' );
		expect( document.querySelector( '[data-rvs-load-more]' ).disabled ).toBe( false );
	} );

	test( 'a row without a rating, vehicle or date loses those parts', async () => {
		global.fetch.mockReturnValue( answer( { success: true, data: { cards: [ row( { rating: 0, vehicle: '', date: '' } ) ], has_more: false } } ) );
		load( instance( 'a' ) );

		document.querySelector( '[data-rvs-load-more]' ).click();
		await flush();

		const added = document.querySelectorAll( '[data-rvs-items] [data-rvs-card]' )[ 2 ];
		expect( added.querySelector( '.rvs-review-stars' ) ).toBeNull();
		expect( added.querySelector( '[data-rvs-slot="vehicle"]' ) ).toBeNull();
		expect( added.querySelector( '[data-rvs-slot="date"]' ) ).toBeNull();
		// No more to load: the button's box is hidden.
		expect( document.querySelector( '.rvs-testimonials__more' ).hidden ).toBe( true );
	} );

	test( 'a refused request shows the error and keeps the button', async () => {
		global.fetch.mockReturnValue( answer( { success: false, data: { message: 'Security check failed.' } } ) );
		load( instance( 'a' ) );

		document.querySelector( '[data-rvs-load-more]' ).click();
		await flush();

		const error = document.querySelector( '[data-rvs-error]' );
		expect( error.hidden ).toBe( false );
		expect( error.textContent ).toBe( 'Could not load.' );
		expect( document.querySelector( '[data-rvs-load-more]' ).disabled ).toBe( false );
		expect( document.querySelectorAll( '[data-rvs-items] [data-rvs-card]' ) ).toHaveLength( 2 );
	} );

	test( 'two lists add to their own items', async () => {
		global.fetch.mockReturnValue( answer( { success: true, data: { cards: [ row() ], has_more: true } } ) );
		load( instance( 'a' ) + instance( 'bb' ) );

		document.querySelectorAll( '[data-rvs-load-more]' )[ 1 ].click();
		await flush();

		expect( document.querySelectorAll( '#a [data-rvs-items] [data-rvs-card]' ) ).toHaveLength( 2 );
		expect( document.querySelectorAll( '#bb [data-rvs-items] [data-rvs-card]' ) ).toHaveLength( 3 );
		expect( new URLSearchParams( global.fetch.mock.calls[ 0 ][ 1 ].body ).get( 'seed' ) ).toBe( '2' );
	} );

	test( 'new cards in a slider get indicators that name them', async () => {
		global.fetch.mockReturnValue( answer( { success: true, data: { cards: [ row() ], has_more: true } } ) );
		load( instance( 'a', { carousel: true } ) );

		document.querySelector( '[data-rvs-load-more]' ).click();
		await flush();

		const dots = document.querySelectorAll( '[data-rvs-dots] button' );
		expect( dots ).toHaveLength( 3 );
		expect( Array.from( dots ).map( ( d ) => d.getAttribute( 'aria-label' ) ) ).toEqual( [ 'Review 1 of 3', 'Review 2 of 3', 'Review 3 of 3' ] );
		expect( dots[ 0 ].getAttribute( 'aria-current' ) ).toBe( 'true' );
	} );

	test( 'the buttons and dots move the slider and mark the current one', () => {
		load( instance( 'a', { carousel: true, more: false } ) );
		const dots = document.querySelectorAll( '[data-rvs-dots] button' );

		document.querySelector( '[data-rvs-next]' ).click();
		expect( dots[ 1 ].getAttribute( 'aria-current' ) ).toBe( 'true' );
		expect( dots[ 0 ].hasAttribute( 'aria-current' ) ).toBe( false );
		expect( window.HTMLElement.prototype.scrollTo ).toHaveBeenCalled();

		// Past the last one it comes round.
		document.querySelector( '[data-rvs-next]' ).click();
		expect( dots[ 0 ].getAttribute( 'aria-current' ) ).toBe( 'true' );

		dots[ 1 ].click();
		expect( dots[ 1 ].getAttribute( 'aria-current' ) ).toBe( 'true' );
	} );

	test( 'auto-rotate steps, stops on hover and on focus, and resumes', () => {
		load( instance( 'a', { carousel: true, rotate: '1', more: false } ) );
		const root = document.getElementById( 'a' );
		const dots = document.querySelectorAll( '[data-rvs-dots] button' );

		jest.advanceTimersByTime( 5000 );
		expect( dots[ 1 ].getAttribute( 'aria-current' ) ).toBe( 'true' );

		root.dispatchEvent( new Event( 'mouseenter' ) );
		jest.advanceTimersByTime( 20000 );
		expect( dots[ 1 ].getAttribute( 'aria-current' ) ).toBe( 'true' );

		root.dispatchEvent( new Event( 'mouseleave' ) );
		jest.advanceTimersByTime( 5000 );
		expect( dots[ 0 ].getAttribute( 'aria-current' ) ).toBe( 'true' );

		root.dispatchEvent( new Event( 'focusin', { bubbles: true } ) );
		jest.advanceTimersByTime( 20000 );
		expect( dots[ 0 ].getAttribute( 'aria-current' ) ).toBe( 'true' );
	} );

	test( 'auto-rotate never starts under reduced motion or when it was not asked for', () => {
		reduced = true;
		load( instance( 'a', { carousel: true, rotate: '1', more: false } ) );
		jest.advanceTimersByTime( 30000 );
		expect( document.querySelector( '[data-rvs-dots] button' ).getAttribute( 'aria-current' ) ).toBe( 'true' );

		reduced = false;
		load( instance( 'b', { carousel: true, rotate: '0', more: false } ) );
		jest.advanceTimersByTime( 30000 );
		expect( document.querySelector( '[data-rvs-dots] button' ).getAttribute( 'aria-current' ) ).toBe( 'true' );
	} );

	test( 'a list that began with one review gets its controls once Load more adds cards', async () => {
		global.fetch.mockReturnValue( answer( { success: true, data: { cards: [ row(), row( { text: 'Third' } ) ], has_more: true } } ) );
		load( instance( 'a', { carousel: true, count: 1, rotate: '1' } ) );
		const controls = document.querySelector( '[data-rvs-controls]' );
		expect( controls.hidden ).toBe( true );

		document.querySelector( '[data-rvs-load-more]' ).click();
		await flush();

		expect( controls.hidden ).toBe( false );
		const dots = document.querySelectorAll( '[data-rvs-dots] button' );
		expect( dots ).toHaveLength( 3 );
		expect( dots[ 2 ].getAttribute( 'aria-label' ) ).toBe( 'Review 3 of 3' );
		// Rotation began with the cards: it steps.
		jest.advanceTimersByTime( 5000 );
		expect( dots[ 1 ].getAttribute( 'aria-current' ) ).toBe( 'true' );
	} );

	test( 'the pause button stops and restarts the rotation and says which it will do', () => {
		load( instance( 'a', { carousel: true, rotate: '1', more: false } ) );
		const button = document.querySelector( '[data-rvs-pause]' );
		const dots = document.querySelectorAll( '[data-rvs-dots] button' );

		button.click();
		expect( button.getAttribute( 'aria-label' ) ).toBe( 'Start automatic rotation' );
		expect( button.querySelector( '[data-rvs-play-icon]' ).hasAttribute( 'hidden' ) ).toBe( false );
		expect( button.querySelector( '[data-rvs-pause-icon]' ).hasAttribute( 'hidden' ) ).toBe( true );
		jest.advanceTimersByTime( 30000 );
		expect( dots[ 0 ].getAttribute( 'aria-current' ) ).toBe( 'true' );

		button.click();
		expect( button.getAttribute( 'aria-label' ) ).toBe( 'Pause automatic rotation' );
		jest.advanceTimersByTime( 5000 );
		expect( dots[ 1 ].getAttribute( 'aria-current' ) ).toBe( 'true' );
	} );

	test( 'a touch pauses the rotation until the visitor starts it again', () => {
		load( instance( 'a', { carousel: true, rotate: '1', more: false } ) );
		const root = document.getElementById( 'a' );
		const dots = document.querySelectorAll( '[data-rvs-dots] button' );

		root.dispatchEvent( new Event( 'touchstart', { bubbles: true } ) );
		root.dispatchEvent( new Event( 'mouseleave' ) );
		jest.advanceTimersByTime( 30000 );
		expect( dots[ 0 ].getAttribute( 'aria-current' ) ).toBe( 'true' );
		expect( document.querySelector( '[data-rvs-pause]' ).getAttribute( 'aria-label' ) ).toBe( 'Start automatic rotation' );
	} );

	test( 'the pause button is not shown under reduced motion', () => {
		reduced = true;
		load( instance( 'a', { carousel: true, rotate: '1', more: false } ) );
		expect( document.querySelector( '[data-rvs-pause]' ).hidden ).toBe( true );
	} );
} );
