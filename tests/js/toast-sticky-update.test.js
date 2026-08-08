/**
 * @jest-environment jsdom
 */

describe( 'MHMRentivaToast sticky lifecycle', () => {
	beforeEach( () => {
		jest.useFakeTimers();
		jest.resetModules();
		document.body.innerHTML = '';
		delete window.MHMRentivaToast;

		window.matchMedia = jest.fn().mockReturnValue( { matches: false } );
		window.requestAnimationFrame = ( callback ) => callback();

		require( '../../assets/js/frontend/toast.js' );
	} );

	afterEach( () => {
		window.MHMRentivaToast.clearAll();
		jest.runOnlyPendingTimers();
		jest.useRealTimers();
	} );

	test( 'a completed request replaces its sticky progress toast after the dedupe window', () => {
		const progressId = window.MHMRentivaToast.show( 'Adding to favorites...', {
			type: 'info',
			idempotencyKey: 'fav:add:3017',
			duration: 0,
		} );

		jest.advanceTimersByTime( 1500 );

		const completedId = window.MHMRentivaToast.show( 'Added to favorites.', {
			type: 'success',
			idempotencyKey: 'fav:add:3017',
			duration: 3000,
		} );

		expect( completedId ).toBe( progressId );
		expect( document.querySelectorAll( '.mhm-toast' ) ).toHaveLength( 1 );
		expect( document.querySelector( '.mhm-toast__message' ).textContent ).toBe( 'Added to favorites.' );
		expect( document.querySelector( '.mhm-toast' ).classList.contains( 'is-success' ) ).toBe( true );
	} );
} );
