/**
 * @jest-environment jsdom
 *
 * The browser twin of ColorMath::palette() must reproduce the PHP fixture rows.
 */
const twin = require( '../../assets/js/admin/accent-palette.js' );
const fixture = require( '../fixtures/accent-palette-cases.json' );

describe( 'accent palette twin', () => {
	test( 'matches ColorMath on every fixture row', () => {
		expect( fixture.cases ).toHaveLength( 16 );
		fixture.cases.forEach( ( row ) => {
			const out = twin.palette( row.accent );
			expect( out.tokens ).toEqual( row.php );
			expect( out.looped ).toEqual( row.looped );
		} );
	} );

	test( 'flags match the PHP fixture flags', () => {
		const flags = ( c ) => twin.palette( c ).flags;
		expect( flags( '#0067ff' ).text_darkened ).toBe( true );
		[ '#feb000', '#1cb098', '#ffee58', '#ffffff' ].forEach( ( c ) => expect( flags( c ).fill_low ).toBe( true ) );
		expect( flags( '#cc0000' ) ).toEqual( { text_darkened: false, fill_low: false } );
		expect( flags( '#2271b1' ) ).toEqual( { text_darkened: false, fill_low: false } );
	} );

	test( 'normalize table', () => {
		const rows = [
			[ '#ABC', '#aabbcc' ],
			[ '#2271b180', null ],
			[ 'rgb(34, 113, 177)', '#2271b1' ],
			[ 'rgba(34,113,177,0.5)', null ],
			[ 'var(--x)', null ],
			[ '#fff;}body{x}', null ],
			[ ' #2271b1 ', '#2271b1' ],
			// PHP trim() strips ASCII whitespace only; a no-break space stays and fails.
			[ String.fromCharCode( 0xa0 ) + '#2271b1', null ],
			[ '\t#2271b1\n', '#2271b1' ],
			[ String.fromCharCode( 11, 0 ) + '#2271b1' + String.fromCharCode( 0, 11 ), '#2271b1' ],
			[ 'rgb(300,0,0)', null ],
			[ null, null ],
			[ {}, null ],
			[ '', null ],
		];
		rows.forEach( ( [ input, expected ] ) => expect( twin.normalize( input ) ).toBe( expected ) );
	} );

	test( 'contrast reference values', () => {
		expect( twin.contrast( '#2271b1', '#ffffff' ) ).toBeCloseTo( 5.17, 2 );
		expect( twin.contrast( '#1e88e5', '#ffffff' ) ).toBeCloseTo( 3.68, 2 );
	} );
} );
