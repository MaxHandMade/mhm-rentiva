/**
 * The row of page tabs: what does not fit goes into "More", the current page's
 * tab stays, and a phone gets the scrolling row as it was.
 */
const path = require( 'path' );

const FILE = path.resolve( __dirname, '../../assets/js/frontend/rvs-tabs.js' );

function load( { labels, current = 0, room = 400, wide = true } ) {
	jest.resetModules();
	document.body.innerHTML = '<nav class="woocommerce-MyAccount-navigation"><ul>'
		+ labels.map( ( label, i ) => '<li' + ( i === current ? ' class="is-active"' : '' ) + '><a href="#' + i + '">' + label + '</a></li>' ).join( '' )
		+ '</ul></nav><p id="out" tabindex="0">x</p>';
	window.matchMedia = () => ( { matches: wide } );
	window.mhmRentivaRvsTabs = { more: 'Daha fazla' };
	const list = document.querySelector( 'ul' );
	Object.defineProperty( list, 'clientWidth', { value: room, configurable: true } );
	Object.defineProperty( list, 'scrollWidth', { value: labels.length * 100 } );
	// Every item is 100px wide, the button too.
	jest.spyOn( HTMLElement.prototype, 'getBoundingClientRect' ).mockReturnValue( { width: 100 } );
	require( FILE );
	return list;
}

const row = ( list ) => Array.from( list.children ).filter( ( li ) => ! li.classList.contains( 'rvs-tabs-more' ) ).map( ( li ) => li.textContent );
const menu = ( list ) => Array.from( list.querySelectorAll( '.rvs-tabs-more__menu > li' ) ).map( ( li ) => li.textContent );

afterEach( () => jest.restoreAllMocks() );

describe( 'rvs tabs', () => {
	it( 'leaves a row that fits alone', () => {
		const list = load( { labels: [ 'a', 'b', 'c', 'd' ] } );
		expect( row( list ) ).toEqual( [ 'a', 'b', 'c', 'd' ] );
		expect( list.querySelector( '.rvs-tabs-more' ) ).toBeNull();
	} );

	it( 'moves what does not fit into More, in order', () => {
		const list = load( { labels: [ 'a', 'b', 'c', 'd', 'e' ] } );
		expect( row( list ) ).toEqual( [ 'a', 'b', 'c' ] );
		expect( menu( list ) ).toEqual( [ 'd', 'e' ] );
		expect( list.querySelector( 'button' ).textContent ).toBe( 'Daha fazla' );
	} );

	it( 'keeps the current tab in the row', () => {
		const list = load( { labels: [ 'a', 'b', 'c', 'd', 'e' ], current: 4 } );
		expect( row( list ) ).toEqual( [ 'a', 'b', 'e' ] );
		expect( menu( list ) ).toEqual( [ 'c', 'd' ] );
	} );

	it( 'does not let a later short item jump over one that did not fit', () => {
		load( { labels: [ 'a', 'b' ] } );
		expect( window.mhmRentivaRvsTabsFit( [ 100, 300, 50, 100 ], 0, 400, 100 ) ).toEqual( [ true, false, false, false ] );
	} );

	it( 'opens and closes, and Escape gives the focus back', () => {
		const list = load( { labels: [ 'a', 'b', 'c', 'd', 'e' ] } );
		const button = list.querySelector( 'button' );
		const panel = list.querySelector( '.rvs-tabs-more__menu' );
		expect( button.getAttribute( 'aria-controls' ) ).toBe( panel.id );
		expect( panel.hidden ).toBe( true );
		button.click();
		expect( panel.hidden ).toBe( false );
		expect( button.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
		panel.querySelector( 'a' ).focus();
		panel.querySelector( 'a' ).dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Escape', bubbles: true } ) );
		expect( panel.hidden ).toBe( true );
		expect( document.activeElement ).toBe( button );
		button.click();
		document.getElementById( 'out' ).click();
		expect( panel.hidden ).toBe( true );
	} );

	it( 'closes when the focus leaves it', () => {
		const list = load( { labels: [ 'a', 'b', 'c', 'd', 'e' ] } );
		const button = list.querySelector( 'button' );
		button.click();
		button.focus();
		document.getElementById( 'out' ).focus();
		expect( list.querySelector( '.rvs-tabs-more__menu' ).hidden ).toBe( true );
	} );

	it( 'scrolls the current tab into view on a phone, with no More', () => {
		const list = load( { labels: [ 'a', 'b', 'c', 'd', 'e' ], current: 4, wide: false } );
		expect( list.querySelector( '.rvs-tabs-more' ) ).toBeNull();
		expect( row( list ) ).toEqual( [ 'a', 'b', 'c', 'd', 'e' ] );
		expect( list.classList.contains( 'has-rvs-more' ) ).toBe( false );
	} );

	it( 'sets a list up once', () => {
		const list = load( { labels: [ 'a', 'b', 'c', 'd', 'e' ] } );
		jest.resetModules();
		require( FILE );
		expect( list.querySelectorAll( '.rvs-tabs-more' ) ).toHaveLength( 1 );
	} );

	it( 'takes a list that asks for it', () => {
		jest.resetModules();
		document.body.innerHTML = '<ul data-rvs-tabs><li class="is-current"><a href="#1">a</a></li><li><a href="#2">b</a></li></ul>';
		window.matchMedia = () => ( { matches: true } );
		require( FILE );
		expect( document.querySelector( 'ul' ).dataset.rvsTabsReady ).toBe( '1' );
	} );
} );
