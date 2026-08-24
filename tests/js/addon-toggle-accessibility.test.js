/**
 * @jest-environment jsdom
 *
 * The Aktif/Pasif row toggle has to say what it is, not just look like it.
 *
 * The button carries its state in a data- attribute and in its visible label,
 * which is enough for a sighted operator: the word flips and the row dims. It
 * is not enough for assistive technology, which has no reason to treat a plain
 * <button> as a two-state control -- so pressing it announces a click and not a
 * state change. aria-pressed is what makes it a toggle button, and it has to
 * track the same optimistic flip the label does: the row answers immediately
 * and is put back if the server disagrees, so an aria-pressed that only moved
 * on success would disagree with the label for the length of the round trip,
 * and one that never reverted would lie exactly the way the label refuses to.
 */

describe( 'add-on row toggle accessibility state', () => {
	let resolveRequest;

	const setUp = ( markupEnabled ) => {
		jest.resetModules();

		document.body.innerHTML = `
			<div id="mhm-addons-root">
				<div class="rv-addon-list">
					<div class="rv-addon-row" data-addon-id="7">
						<span class="rv-addon-name">Navigasyon</span>
						<button type="button"
							class="rv-addon-status${ markupEnabled ? ' is-on' : '' }"
							data-enabled="${ markupEnabled ? '1' : '0' }"
							aria-pressed="${ markupEnabled ? 'true' : 'false' }">${
								markupEnabled ? 'Aktif' : 'Pasif'
							}</button>
					</div>
				</div>
			</div>
		`;

		window.mhmRentivaAddonsScreen = {
			ajaxUrl: '/wp-admin/admin-ajax.php',
			nonce: 'test-nonce',
			i18n: {
				active: 'Aktif',
				inactive: 'Pasif',
				genericError: 'Bir hata oluştu.',
				confirmDelete: '%s silinsin mi?',
			},
		};

		global.fetch = jest.fn(
			() =>
				new Promise( ( resolve ) => {
					resolveRequest = resolve;
				} )
		);

		require( '../../assets/js/admin/addons-screen.js' );
	};

	const button = () => document.querySelector( '.rv-addon-status' );

	const answer = ( body ) => {
		resolveRequest( { json: () => Promise.resolve( body ) } );
		// Let the fetch promise and its .then settle.
		return new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	};

	afterEach( () => {
		delete global.fetch;
		delete window.mhmRentivaAddonsScreen;
		document.body.innerHTML = '';
	} );

	it( 'flips aria-pressed with the label when the row is switched off', async () => {
		setUp( true );

		button().click();

		expect( button().textContent ).toBe( 'Pasif' );
		expect( button().getAttribute( 'aria-pressed' ) ).toBe( 'false' );

		await answer( { success: true } );

		expect( button().getAttribute( 'aria-pressed' ) ).toBe( 'false' );
	} );

	it( 'flips aria-pressed with the label when the row is switched on', async () => {
		setUp( false );

		button().click();

		expect( button().textContent ).toBe( 'Aktif' );
		expect( button().getAttribute( 'aria-pressed' ) ).toBe( 'true' );

		await answer( { success: true } );

		expect( button().getAttribute( 'aria-pressed' ) ).toBe( 'true' );
	} );

	it( 'puts aria-pressed back when the server refuses, exactly as it puts the label back', async () => {
		setUp( true );

		button().click();
		expect( button().getAttribute( 'aria-pressed' ) ).toBe( 'false' );

		await answer( { success: false, data: { message: 'Nope' } } );

		expect( button().textContent ).toBe( 'Aktif' );
		expect( button().getAttribute( 'aria-pressed' ) ).toBe(
			'true',
			'A reverted label with a stale aria-pressed is worse than neither: the two would disagree.'
		);
	} );
} );
