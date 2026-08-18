/**
 * @jest-environment jsdom
 *
 * The counters have to follow the toggle, or the screen contradicts itself.
 *
 * Switching a service on used to leave the KPI band and the list counter at the
 * figures the page was rendered with: three rows reading Aktif above a header
 * still reading "2 aktif · 3 toplam". Silence would have been fine -- the row
 * label and the dimming already say what happened -- but a stale number is not
 * silence, it is a wrong answer, and it is the one thing on the screen an
 * operator would trust over counting the rows themselves.
 *
 * The figures come back from the server rather than being recomputed here.
 * AddonStats is the single owner of them, cached and invalidated by the toggle
 * itself; deriving them again in the browser would be a second source that can
 * disagree, and two of the four (average price, total value) are formatted
 * currency this file has no business assembling.
 *
 * They are written on the server's answer, NOT on the click. The row flips
 * optimistically because a row that waits feels broken; a COUNTER that moves
 * optimistically would be asserting something it does not know yet, and would
 * have to be un-asserted on the revert.
 */

describe( 'add-on screen counters after a toggle', () => {
	let resolveRequest;

	const setUp = () => {
		jest.resetModules();

		document.body.innerHTML = `
			<div id="mhm-addons-root">
				<div class="mhm-stats-grid">
					<div class="mhm-stat-card" data-stat="total_addons">
						<div class="mhm-stat-card__body">
							<p class="mhm-stat-card__value">3</p>
							<p class="mhm-stat-card__sub">Tüm Servisler</p>
						</div>
					</div>
					<div class="mhm-stat-card" data-stat="active_addons">
						<div class="mhm-stat-card__body">
							<p class="mhm-stat-card__value">2</p>
							<p class="mhm-stat-card__sub">%67 aktif</p>
						</div>
					</div>
				</div>
					<div class="mhm-stat-card" data-stat="avg_price">
						<div class="mhm-stat-card__body">
							<p class="mhm-stat-card__value">$31,67</p>
						</div>
					</div>
					<div class="mhm-stat-card" data-stat="total_value">
						<div class="mhm-stat-card__body">
							<p class="mhm-stat-card__value">$95,00</p>
						</div>
					</div>
				<span class="rv-addon-count">2 aktif · 3 toplam</span>
				<div class="rv-addon-row" data-addon-id="7">
					<span class="rv-addon-name">Bebek Koltuğu</span>
					<button type="button" class="rv-addon-amount rv-addon-price-value" data-price="20">$20,00</button>
					<button type="button" class="rv-addon-status" data-enabled="0" aria-pressed="false">Pasif</button>
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
				activeShare: '%%%s aktif', // gercek TR cevirisi: sprintf kacisiyla birlikte
				countLabel: '%1$d aktif · %2$d toplam',
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

	const answer = ( body ) => {
		resolveRequest( { json: () => Promise.resolve( body ) } );
		return new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	};

	const read = () => ( {
		toplam: document.querySelector( '[data-stat="total_addons"] .mhm-stat-card__value' ).textContent,
		aktif: document.querySelector( '[data-stat="active_addons"] .mhm-stat-card__value' ).textContent,
		oran: document.querySelector( '[data-stat="active_addons"] .mhm-stat-card__sub' ).textContent,
		sayac: document.querySelector( '.rv-addon-count' ).textContent,
	} );

	const readPrices = () => ( {
		ortalama: document.querySelector( '[data-stat="avg_price"] .mhm-stat-card__value' ).textContent,
		toplamDeger: document.querySelector( '[data-stat="total_value"] .mhm-stat-card__value' ).textContent,
	} );

	const freshStats = {
		total_addons: 3,
		active_addons: 3,
		active_percentage: 100,
		avg_price: '$31,67',
		total_value: '$95,00',
	};

	afterEach( () => {
		delete global.fetch;
		delete window.mhmRentivaAddonsScreen;
		document.body.innerHTML = '';
	} );

	it( 'leaves the counters alone until the server has answered', () => {
		setUp();

		document.querySelector( '.rv-addon-status' ).click();

		expect( read() ).toEqual( {
			toplam: '3',
			aktif: '2',
			oran: '%67 aktif',
			sayac: '2 aktif · 3 toplam',
		} );
	} );

	it( 'writes the figures the server returns', async () => {
		setUp();

		document.querySelector( '.rv-addon-status' ).click();
		await answer( { success: true, data: { success: true, enabled: true, stats: freshStats } } );

		expect( read() ).toEqual( {
			toplam: '3',
			aktif: '3',
			oran: '%100 aktif',
			sayac: '3 aktif · 3 toplam',
		} );
	} );

	it( 'leaves the counters at the old figures when the server refuses', async () => {
		setUp();

		document.querySelector( '.rv-addon-status' ).click();
		await answer( { success: false, data: { success: false, message: 'Nope' } } );

		expect( read() ).toEqual( {
			toplam: '3',
			aktif: '2',
			oran: '%67 aktif',
			sayac: '2 aktif · 3 toplam',
		} );
	} );

	it( 'survives an answer that carries no stats', async () => {
		setUp();

		document.querySelector( '.rv-addon-status' ).click();
		await answer( { success: true, data: { success: true, enabled: true } } );

		expect( read().aktif ).toBe( '2' );
		expect( document.querySelector( '.rv-addon-status' ).textContent ).toBe( 'Aktif' );
	} );

	/**
	 * Fable's LOW-2: applyStats was only ever driven through the toggle, while
	 * the line the fix added sits in the INLINE PRICE callback. Average Price
	 * and Total Value are the two cards a price moves, and neither was asserted
	 * anywhere -- the same "the sweep's newest member is the one no test calls
	 * by name" shape this repo keeps hitting.
	 */
	it( 'follows an inline price edit, not just a toggle', async () => {
		setUp();

		document.querySelector( '.rv-addon-price-value' ).click();
		const input = document.querySelector( '.rv-addon-price-input' );
		input.value = '120';
		input.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Enter', bubbles: true } ) );

		await answer( {
			success: true,
			data: {
				formatted_price: '$120,00',
				stats: { ...freshStats, avg_price: '$65,00', total_value: '$195,00' },
			},
		} );

		expect( readPrices() ).toEqual( { ortalama: '$65,00', toplamDeger: '$195,00' } );
	} );

	it( 'leaves the price cards alone when the price save fails', async () => {
		setUp();

		document.querySelector( '.rv-addon-price-value' ).click();
		const input = document.querySelector( '.rv-addon-price-input' );
		input.value = '120';
		input.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Enter', bubbles: true } ) );

		await answer( { success: false, data: { message: 'Nope' } } );

		expect( readPrices() ).toEqual( { ortalama: '$31,67', toplamDeger: '$95,00' } );
	} );
} );
