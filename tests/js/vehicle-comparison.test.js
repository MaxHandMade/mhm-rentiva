/**
 * @jest-environment jsdom
 */

/**
 * PR E (R18, R19, R20, m11, m13, ledger): the comparison's client. Markup follows
 * templates/shortcodes/vehicle-comparison.php. Toggles go through the queue in
 * vehicle-interactions.js, loaded here for real.
 */
describe( 'vehicle comparison client', () => {
	let $;
	let calls;
	let toast;
	let reload;

	const cell = ( id, row, value, text ) => `<td data-rvs-cmp-col data-vehicle-id="${ id }" data-row="${ row }" data-rvs-cmp-cell data-row-value="${ value }" data-empty="${ '' === value ? '1' : '0' }"><span>${ text || value }</span><span data-rvs-cmp-cue hidden>differs</span></td>`;
	const head = ( id ) => `<th role="columnheader" data-rvs-cmp-col data-rvs-cmp-vehicle data-vehicle-id="${ id }"><button type="button" data-rvs-cmp-remove data-vehicle-id="${ id }">Remove</button></th>`;
	const row = ( key, ids, values ) => `<tr data-rvs-cmp-row="${ key }"><th>${ key }</th>${ ids.map( ( id, i ) => cell( id, key, values[ i ] ) ).join( '' ) }<td data-rvs-cmp-filler hidden></td></tr>`;

	const table = ( { ids = [ 1, 2, 3 ], fixed = '0', max = 3, add = true } = {} ) => `
		<section data-rvs-comparison data-fixed="${ fixed }" data-max-vehicles="${ max }" data-headers='{"2":"2 vehicles · up to 3","3":"3 vehicles · up to 3"}'>
			<p data-rvs-cmp-meta>${ ids.length } vehicles · up to 3</p>
			<label data-rvs-cmp-only-label><input type="checkbox" data-rvs-cmp-only></label>
			<div data-rvs-cmp-body>
				<table role="table">
					<thead><tr><td></td>${ ids.map( head ).join( '' ) }${ add ? '<td data-rvs-cmp-add hidden><a href="/results">Add</a></td>' : '' }</tr></thead>
					<tbody data-rvs-cmp-group>
						<tr data-rvs-cmp-group-head><th data-rvs-cmp-span colspan="4">Details</th></tr>
						${ row( 'fuel', ids, [ 'petrol', 'petrol', 'diesel' ] ) }
						${ row( 'seats', ids, [ '5', '5', '5' ] ) }
						${ row( 'luggage', ids, [ '', '', '2' ] ) }
					</tbody>
					<tbody data-rvs-cmp-group>
						<tr data-rvs-cmp-group-head><th data-rvs-cmp-span colspan="4">Features</th></tr>
						${ row( 'free_cancellation', ids, [ '24', '24', '24' ] ) }
					</tbody>
				</table>
				<p data-rvs-cmp-none hidden>No differences</p>
			</div>
			${ '1' === fixed ? '<div data-rvs-cmp-empty hidden>Empty</div>' : '' }
		</section>`;

	const boot = ( html ) => {
		document.body.innerHTML = html;
		calls.length = 0;
		window.mhmRentivaComparisonClient.init();
	};
	const root = ( i = 0 ) => document.querySelectorAll( '[data-rvs-comparison]' )[ i ];
	const removeButton = ( id, r = root() ) => r.querySelector( `[data-rvs-cmp-remove][data-vehicle-id="${ id }"]` );
	const click = ( el ) => el.dispatchEvent( new MouseEvent( 'click', { bubbles: true, cancelable: true } ) );
	const only = ( on, r = root() ) => {
		const box = r.querySelector( '[data-rvs-cmp-only]' );
		box.checked = on;
		box.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	};
	const rowEl = ( key, r = root() ) => r.querySelector( `[data-rvs-cmp-row="${ key }"]` );
	const cellOf = ( key, id, r = root() ) => r.querySelector( `[data-row="${ key }"][data-vehicle-id="${ id }"]` );
	const removed = ( items, action = 'removed' ) => ( { success: true, data: { action, items: items.map( ( id ) => ( { id, title: 'T' + id } ) ), is_in_compare: false } } );

	beforeAll( () => {
		$ = require( 'jquery' );
		global.jQuery = $;
		global.$ = $;
		window.jQuery = $;
		calls = [];
		$.ajax = jest.fn( ( options ) => {
			calls.push( options );
		} );
		toast = { show: jest.fn() };
		global.MHMRentivaToast = toast;
		window.MHMRentivaToast = toast;
		global.mhmrentiva_vars = { ajax_url: '/ajax', nonce: 'n', forward_keys: [ 'mhmrentiva_pickup_date' ], i18n: { error: 'ERR', network_error: 'NET' } };
		window.mhmrentiva_vars = global.mhmrentiva_vars;
		window.mhmRentivaVehicleComparison = { toggle_nonce: 'cmp-nonce', strings: { error: 'CMP-ERR' } };
		require( '../../assets/js/frontend/vehicle-interactions.js' );
		require( '../../assets/js/frontend/vehicle-comparison.js' );
		reload = jest.spyOn( window.mhmRentivaComparisonClient, 'reload' ).mockImplementation( () => {} );
	} );

	beforeEach( () => {
		toast.show.mockClear();
		reload.mockClear();
	} );

	test( 'highlights_a_value_that_occurs_once_with_a_text_cue_and_keeps_all_rows_by_default', () => {
		boot( table() );

		expect( cellOf( 'fuel', 3 ).classList.contains( 'is-different' ) ).toBe( true );
		expect( cellOf( 'fuel', 3 ).querySelector( '[data-rvs-cmp-cue]' ).hidden ).toBe( false );
		expect( cellOf( 'fuel', 1 ).classList.contains( 'is-different' ) ).toBe( false );
		expect( cellOf( 'fuel', 1 ).querySelector( '[data-rvs-cmp-cue]' ).hidden ).toBe( true );
		expect( root().querySelector( '[data-rvs-cmp-only]' ).checked ).toBe( false );
		expect( rowEl( 'seats' ).hidden ).toBe( false );
	} );

	test( 'only_differences_hides_all_same_rows_and_the_virtual_row_and_its_empty_group_heading', () => {
		boot( table() );
		const featuresHead = root().querySelectorAll( '[data-rvs-cmp-group-head]' )[ 1 ];

		only( true );

		expect( rowEl( 'seats' ).hidden ).toBe( true );
		expect( rowEl( 'free_cancellation' ).hidden ).toBe( true );
		expect( featuresHead.hidden ).toBe( true );
		expect( rowEl( 'fuel' ).hidden ).toBe( false );
		expect( cellOf( 'fuel', 3 ).classList.contains( 'is-different' ) ).toBe( true );

		only( false );

		expect( rowEl( 'seats' ).hidden ).toBe( false );
		expect( featuresHead.hidden ).toBe( false );
	} );

	test( 'says_so_when_nothing_differs', () => {
		boot( table() );
		root().querySelectorAll( '[data-row="fuel"], [data-row="luggage"]' ).forEach( ( c ) => c.setAttribute( 'data-row-value', 'x' ) );

		only( true );

		expect( root().querySelector( '[data-rvs-cmp-none]' ).hidden ).toBe( false );
	} );

	test( 'list_mode_removal_toggles_then_drops_the_column_and_updates_count_room_and_differences', () => {
		boot( table() );

		click( removeButton( 3 ) );

		expect( calls ).toHaveLength( 1 );
		expect( calls[ 0 ].data ).toEqual( { action: 'mhmrentiva_toggle_compare', vehicle_id: '3', nonce: 'cmp-nonce' } );
		// Nothing leaves the screen before the server says so.
		expect( cellOf( 'fuel', 3 ) ).not.toBeNull();

		calls[ 0 ].success( removed( [ 1, 2 ] ) );

		expect( cellOf( 'fuel', 3 ) ).toBeNull();
		expect( root().querySelector( '[data-rvs-cmp-vehicle][data-vehicle-id="3"]' ) ).toBeNull();
		expect( root().querySelector( '[data-rvs-cmp-meta]' ).textContent ).toBe( '2 vehicles · up to 3' );
		// Room again: the add column and its filler come back, the headings span them.
		expect( root().querySelector( '[data-rvs-cmp-add]' ).hidden ).toBe( false );
		expect( rowEl( 'fuel' ).querySelector( '[data-rvs-cmp-filler]' ).hidden ).toBe( false );
		expect( root().querySelector( '[data-rvs-cmp-span]' ).getAttribute( 'colspan' ) ).toBe( '4' );
		// Both left are petrol: no highlight now, and "only differences" would hide it.
		expect( cellOf( 'fuel', 1 ).classList.contains( 'is-different' ) ).toBe( false );
		// Only the vehicle that had the value: a row empty for all that remain goes.
		expect( rowEl( 'luggage' ).hidden ).toBe( true );
		only( true );
		expect( rowEl( 'fuel' ).hidden ).toBe( true );
		expect( reload ).not.toHaveBeenCalled();
	} );

	test( 'list_mode_below_two_reloads', () => {
		boot( table( { ids: [ 1, 2 ] } ) );

		click( removeButton( 2 ) );
		calls[ 0 ].success( removed( [ 1 ] ) );

		expect( reload ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'fixed_mode_removal_never_touches_the_list_and_below_two_shows_the_empty_state_without_reload', () => {
		boot( table( { fixed: '1', add: false } ) );

		click( removeButton( 3 ) );
		expect( calls ).toHaveLength( 0 );
		expect( cellOf( 'fuel', 3 ) ).toBeNull();
		expect( root().querySelector( '[data-rvs-cmp-meta]' ).textContent ).toBe( '2 vehicles · up to 3' );
		expect( root().querySelector( '[data-rvs-cmp-empty]' ).hidden ).toBe( true );

		click( removeButton( 2 ) );

		expect( calls ).toHaveLength( 0 );
		expect( reload ).not.toHaveBeenCalled();
		expect( root().querySelector( '[data-rvs-cmp-empty]' ).hidden ).toBe( false );
		expect( root().querySelector( '[data-rvs-cmp-body]' ).hidden ).toBe( true );
		expect( root().querySelector( '[data-rvs-cmp-meta]' ).hidden ).toBe( true );
	} );

	test( 'two_instances_keep_their_own_state', () => {
		boot( table( { fixed: '1', add: false } ) + table( { fixed: '1', add: false } ) );

		only( true, root( 1 ) );
		click( removeButton( 3, root( 0 ) ) );

		expect( rowEl( 'seats', root( 0 ) ).hidden ).toBe( false );
		expect( rowEl( 'seats', root( 1 ) ).hidden ).toBe( true );
		expect( cellOf( 'fuel', 3, root( 1 ) ) ).not.toBeNull();
		expect( root( 1 ).querySelector( '[data-rvs-cmp-meta]' ).textContent ).toBe( '3 vehicles · up to 3' );
	} );

	test( 'a_list_comparison_follows_the_list_when_another_control_removes_a_vehicle_and_a_fixed_one_does_not', () => {
		boot( table() + table( { fixed: '1', add: false } ) );

		$( document ).trigger( 'mhm-rentiva:compare-changed', [ { items: [ { id: 1 }, { id: 2 } ] } ] );

		expect( cellOf( 'fuel', 3, root( 0 ) ) ).toBeNull();
		expect( cellOf( 'fuel', 3, root( 1 ) ) ).not.toBeNull();
	} );

	test( 'a_refused_removal_toasts_the_servers_message_and_stays', () => {
		boot( table() );

		click( removeButton( 3 ) );
		calls[ 0 ].success( { success: false, data: { message: 'Nope' } } );

		expect( toast.show ).toHaveBeenCalledWith( 'Nope', { type: 'error' } );
		expect( cellOf( 'fuel', 3 ) ).not.toBeNull();
		expect( removeButton( 3 ).disabled ).toBe( false );
		expect( reload ).not.toHaveBeenCalled();
	} );

	test( 'a_network_error_toasts_a_translated_message_and_stays', () => {
		boot( table() );

		click( removeButton( 3 ) );
		calls[ 0 ].error();

		expect( toast.show ).toHaveBeenCalledWith( 'CMP-ERR', { type: 'error' } );
		expect( cellOf( 'fuel', 3 ) ).not.toBeNull();
		expect( removeButton( 3 ).disabled ).toBe( false );
	} );

	test( 'a_toggle_that_added_instead_of_removed_reloads', () => {
		boot( table() );

		click( removeButton( 3 ) );
		calls[ 0 ].success( removed( [ 1, 2, 3 ], 'added' ) );

		expect( reload ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'quick_removals_go_one_at_a_time', () => {
		boot( table() );

		click( removeButton( 3 ) );
		click( removeButton( 2 ) );

		expect( calls ).toHaveLength( 1 );
		calls[ 0 ].success( removed( [ 1, 2 ] ) );
		expect( calls ).toHaveLength( 2 );
		expect( calls[ 1 ].data.vehicle_id ).toBe( '2' );
		calls[ 1 ].success( removed( [ 1 ] ) );
	} );

	test( 'a_comparison_in_an_editor_preview_is_not_started', () => {
		document.body.innerHTML = `<div data-rv-render="preview">${ table() }</div>`;
		calls.length = 0;
		window.mhmRentivaComparisonClient.init();

		click( removeButton( 3 ) );

		expect( calls ).toHaveLength( 0 );
	} );
} );

describe( 'vehicle interactions: the compare queue', () => {
	let $;
	let calls;
	let toast;

	const boxes = () => document.querySelectorAll( 'input[data-rvs-compare]' );
	const box = ( id ) => `<label><input type="checkbox" data-rvs-compare data-vehicle-id="${ id }" data-nonce="n"></label>`;
	const tick = ( el ) => {
		el.checked = ! el.checked;
		$( el ).trigger( 'change' );
	};
	const answer = ( items, inCompare = true ) => ( { success: true, data: { is_in_compare: inCompare, items: items.map( ( id ) => ( { id, title: 'T' } ) ), max: 3 } } );

	beforeEach( async () => {
		jest.resetModules();
		document.body.innerHTML = box( 1 ) + box( 2 ) + box( 3 ) + '<a data-rvs-compare-link href="http://localhost/compare/">C</a>';
		$ = require( 'jquery' );
		global.jQuery = $;
		global.$ = $;
		window.jQuery = $;
		calls = [];
		$.ajax = jest.fn( ( options ) => {
			calls.push( options );
		} );
		toast = { show: jest.fn() };
		global.MHMRentivaToast = toast;
		window.MHMRentivaToast = toast;
		global.mhmrentiva_vars = {
			ajax_url: '/ajax',
			nonce: 'n',
			compare_page_url: 'http://localhost/compare/',
			forward_keys: [ 'mhmrentiva_pickup_date', 'mhmrentiva_return_date' ],
			i18n: { error: 'ERR', network_error: 'NET', view_comparison: 'View', added_to_compare: 'Added' },
		};
		window.mhmrentiva_vars = global.mhmrentiva_vars;
		window.history.pushState( {}, '', '/results/?mhmrentiva_pickup_date=2026-12-01&mhmrentiva_return_date=2026-12-04&other=1' );
		require( '../../assets/js/frontend/vehicle-interactions.js' );
		await new Promise( ( resolve ) => $( resolve ) );
	} );

	test( 'rapid_ticks_send_one_request_at_a_time_and_the_last_answer_sets_every_box', () => {
		const changed = jest.fn();
		$( document ).on( 'mhm-rentiva:compare-changed.t', ( e, d ) => changed( d ) );

		tick( boxes()[ 0 ] );
		tick( boxes()[ 1 ] );
		tick( boxes()[ 2 ] );

		expect( calls ).toHaveLength( 1 );

		calls[ 0 ].success( answer( [ 1 ] ) );
		expect( calls ).toHaveLength( 2 );
		expect( changed ).not.toHaveBeenCalled();
		expect( boxes()[ 2 ].checked ).toBe( true );

		calls[ 1 ].success( answer( [ 1, 2 ] ) );
		expect( calls ).toHaveLength( 3 );

		calls[ 2 ].success( answer( [ 1, 2, 3 ] ) );
		expect( changed ).toHaveBeenCalledTimes( 1 );
		expect( changed.mock.calls[ 0 ][ 0 ].items ).toHaveLength( 3 );
		expect( Array.from( boxes() ).map( ( b ) => b.checked ) ).toEqual( [ true, true, true ] );

		$( document ).off( '.t' );
	} );

	test( 'a_failed_request_does_not_stop_the_queue', () => {
		tick( boxes()[ 0 ] );
		tick( boxes()[ 1 ] );

		calls[ 0 ].error();

		expect( boxes()[ 0 ].checked ).toBe( false );
		expect( toast.show ).toHaveBeenCalledWith( 'NET', { type: 'error' } );
		expect( calls ).toHaveLength( 2 );
	} );

	test( 'errors_read_the_translated_strings', () => {
		tick( boxes()[ 0 ] );

		calls[ 0 ].success( { success: false, data: {} } );

		expect( toast.show ).toHaveBeenCalledWith( 'ERR', { type: 'error' } );
	} );

	test( 'the_toast_link_carries_the_rental_window_and_nothing_else', () => {
		tick( boxes()[ 0 ] );

		calls[ 0 ].success( answer( [ 1 ] ) );

		const options = toast.show.mock.calls[ toast.show.mock.calls.length - 1 ][ 1 ];
		const url = new URL( options.action.href );
		expect( url.searchParams.get( 'mhmrentiva_pickup_date' ) ).toBe( '2026-12-01' );
		expect( url.searchParams.get( 'mhmrentiva_return_date' ) ).toBe( '2026-12-04' );
		expect( url.searchParams.has( 'other' ) ).toBe( false );
	} );

	test( 'the_band_link_carries_the_rental_window', () => {
		const href = new URL( document.querySelector( '[data-rvs-compare-link]' ).getAttribute( 'href' ) );

		expect( href.searchParams.get( 'mhmrentiva_pickup_date' ) ).toBe( '2026-12-01' );
	} );

	test( 'the_script_holds_no_english_of_its_own', () => {
		const source = require( 'fs' ).readFileSync( require( 'path' ).join( __dirname, '../../assets/js/frontend/vehicle-interactions.js' ), 'utf8' );

		expect( source ).not.toMatch( /'Error'|'Network error'|Adding to|Removing from|Added to|Removed from/ );
	} );
} );
