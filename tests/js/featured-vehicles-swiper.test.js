/**
 * @jest-environment jsdom
 */

/**
 * Featured vehicles: Swiper is mounted with a selector string, not an element.
 * In the Elementor editor the widget nodes live in the preview iframe while the
 * jQuery wrappers come from the parent realm, so Swiper's `instanceof HTMLElement`
 * check on an element argument fails silently. The selector branch has no such check.
 */
describe( 'featured vehicles swiper init', () => {
	let $;

	const markup = () => `
		<div class="mhm-featured-swiper" data-swiper='{"columns":3}'>
			<div class="swiper-wrapper">
				<div class="swiper-slide">1</div>
				<div class="swiper-slide">2</div>
				<div class="swiper-slide">3</div>
			</div>
			<div class="swiper-pagination"></div>
		</div>`;

	const run = () => {
		$( document ).trigger( 'mhm-rentiva-reinit-sliders' );
	};

	beforeAll( async () => {
		$ = require( 'jquery' );
		global.jQuery = window.jQuery = $;
		require( '../../assets/js/frontend/featured-vehicles.js' );
		await new Promise( ( r ) => $( r ) );
	} );

	beforeEach( () => {
		document.body.innerHTML = '';
		window.Swiper = jest.fn( function ( sel ) {
			if ( typeof sel !== 'string' ) {
				throw new Error( 'stub: element passed' );
			}
			const found = document.querySelectorAll( sel );
			if ( found.length === 0 ) {
				return { initialized: undefined };
			}
			if ( found.length > 1 ) {
				return Array.from( found ).map( ( el ) => ( { initialized: true, el } ) );
			}
			found[ 0 ].classList.add( 'swiper-initialized' );
			return { initialized: true, el: found[ 0 ] };
		} );
	} );

	test( 'passes_selector_string', () => {
		document.body.innerHTML = markup();
		run();
		expect( window.Swiper ).toHaveBeenCalledTimes( 1 );
		const [ sel, cfg ] = window.Swiper.mock.calls[ 0 ];
		expect( typeof sel ).toBe( 'string' );
		expect( document.querySelector( sel ) ).toBe( document.querySelector( '.mhm-featured-swiper' ) );
		expect( cfg.pagination.el.startsWith( '[data-rv-swiper="' ) ).toBe( true );
		expect( cfg.pagination.el.endsWith( ' .swiper-pagination' ) ).toBe( true );
	} );

	test( 'two_containers_get_distinct_ids', () => {
		document.body.innerHTML = markup() + markup();
		run();
		const ids = Array.from( document.querySelectorAll( '.mhm-featured-swiper' ) ).map( ( el ) =>
			el.getAttribute( 'data-rv-swiper' )
		);
		expect( ids[ 0 ] ).toBeTruthy();
		expect( ids[ 0 ] ).not.toBe( ids[ 1 ] );
		expect( window.Swiper ).toHaveBeenCalledTimes( 2 );
	} );

	test( 'warns_when_not_initialised', () => {
		document.body.innerHTML = markup();
		window.Swiper.mockImplementation( () => ( { initialized: undefined } ) );
		run();
		expect( console.warn ).toHaveBeenCalledTimes( 1 );
		expect( console ).toHaveWarned();

		console.warn.mockClear();
		document.body.innerHTML = markup();
		window.Swiper.mockImplementation( () => [ {}, {} ] );
		run();
		expect( console.warn ).toHaveBeenCalledTimes( 1 );
		expect( console ).toHaveWarned();
	} );

	test( 'rerendered_node_gets_fresh_id', () => {
		document.body.innerHTML = markup();
		run();
		const first = document.querySelector( '.mhm-featured-swiper' );
		const oldId = first.getAttribute( 'data-rv-swiper' );
		// Elementor re-render: a copy carrying the old id, without swiper-initialized.
		const copy = first.outerHTML.replace( ' swiper-initialized', '' );
		document.body.innerHTML = copy;
		const node = document.querySelector( '.mhm-featured-swiper' );
		expect( node.classList.contains( 'swiper-initialized' ) ).toBe( false );
		expect( node.getAttribute( 'data-rv-swiper' ) ).toBe( oldId );
		window.Swiper.mockClear();
		run();
		expect( node.getAttribute( 'data-rv-swiper' ) ).not.toBe( oldId );
		expect( window.Swiper ).toHaveBeenCalledTimes( 1 );
		expect( node.classList.contains( 'swiper-initialized' ) ).toBe( true );
	} );
} );
