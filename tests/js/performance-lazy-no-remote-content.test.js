/**
 * @jest-environment jsdom
 */

/**
 * performance.js lazy-loaded [data-lazy] elements of type "content" ($.get + .html()) and
 * "script" (a <script src> from data-lazy-src). Nothing in either plugin emits data-lazy, and
 * wp_kses_post lets authors without unfiltered_html add data-* attributes -- so the two types
 * were a way to inject markup or a remote script. They are no longer loaded.
 */
describe( 'performance lazy loading: no remote content or scripts', () => {
	let $;
	let perf;

	beforeAll( () => {
		$ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;
		$.get = jest.fn( () => $.Deferred().resolve( '<img src=x onerror="window.__rvInjected=1">' ).promise() );
		require( '../../assets/js/core/performance.js' );
		perf = window.MHMRentiva.Performance;
	} );

	const lazy = ( type ) => {
		document.body.innerHTML = '<div id="t"></div>';
		const el = document.getElementById( 't' );
		perf.lazyElements = new Map( [ [ el, { type, src: '//evil.example/x.js', loaded: false } ] ] );
		perf.handleLazyElement( el );
		return el;
	};

	test( 'a "script" element appends no script', () => {
		const before = document.head.querySelectorAll( 'script' ).length;
		lazy( 'script' );
		expect( document.head.querySelectorAll( 'script' ) ).toHaveLength( before );
	} );

	test( 'a "content" element fetches and inserts nothing', () => {
		const el = lazy( 'content' );
		expect( $.get ).not.toHaveBeenCalled();
		expect( el.querySelector( 'img' ) ).toBeNull();
	} );
} );
