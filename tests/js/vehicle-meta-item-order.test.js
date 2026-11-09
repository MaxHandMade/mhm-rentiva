/**
 * @jest-environment jsdom
 */
/**
 * PR 4 (Araç Düzenle): each item grid is sortable over its own items, and the
 * new order goes into that grid's hidden "<grid>_order" field, saved per
 * vehicle with the Update. Before: features and equipment matched no sortable
 * item, their order was never collected, and the AJAX saver failed its nonce
 * (Codex PR 4 plan Y2).
 */
describe( 'vehicle edit: item order goes into the hidden order fields', () => {
	let $;
	let sortables;
	let ajax;

	const items = ( cls, attr, keys ) =>
		keys.map( ( k ) => `<div class="${ cls }" ${ attr }="${ k }"><label><input type="checkbox"> ${ k }</label></div>` ).join( '' );

	beforeEach( async () => {
		jest.resetModules();
		$ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;
		window.mhmVehicleMeta = { strings: {} };
		sortables = {};
		// jQuery UI is not in jsdom: record what each grid was made sortable with.
		$.fn.sortable = function ( opts ) {
			sortables[ this.attr( 'id' ) ] = opts;
			return this;
		};
		ajax = jest.fn();
		$.ajax = ajax;
		document.body.innerHTML = `
			<form id="post">
				<div class="mhm-details-grid" id="details-grid">${ items( 'mhm-detail-item', 'data-detail-key', [ 'price_per_day', 'seats' ] ) }</div>
				<div class="mhm-features-grid" id="features-grid">${ items( 'mhm-feature-item', 'data-feature-key', [ 'a', 'b', 'c' ] ) }</div>
				<div class="mhm-equipment-grid" id="equipment-grid">${ items( 'mhm-equipment-item', 'data-equipment-key', [ 'x', 'y' ] ) }</div>
				<input type="hidden" id="details-grid_order" name="details-grid_order" value="">
				<input type="hidden" id="features-grid_order" name="features-grid_order" value="">
				<input type="hidden" id="equipment-grid_order" name="equipment-grid_order" value="">
			</form>`;
		require( '../../assets/js/components/vehicle-meta.js' );
		await new Promise( ( r ) => $( r ) );
	} );

	test( 'each grid is sortable over its own items', () => {
		expect( sortables[ 'details-grid' ].items ).toBe( '.mhm-detail-item[data-detail-key]' );
		expect( sortables[ 'features-grid' ].items ).toBe( '.mhm-feature-item[data-feature-key]' );
		expect( sortables[ 'equipment-grid' ].items ).toBe( '.mhm-equipment-item[data-equipment-key]' );
		expect( $( '#features-grid' ).find( sortables[ 'features-grid' ].items ) ).toHaveLength( 3 );
	} );

	test( 'each grid writes its own DOM order into its hidden field', () => {
		// The user drags "c" to the top of the features and "y" above "x".
		$( '#features-grid' ).prepend( $( '#features-grid [data-feature-key="c"]' ) );
		$( '#equipment-grid' ).prepend( $( '#equipment-grid [data-equipment-key="y"]' ) );

		sortables[ 'features-grid' ].update();
		sortables[ 'equipment-grid' ].update();

		expect( JSON.parse( $( '#features-grid_order' ).val() ) ).toEqual( [ 'c', 'a', 'b' ] );
		expect( JSON.parse( $( '#equipment-grid_order' ).val() ) ).toEqual( [ 'y', 'x' ] );
		expect( $( '#details-grid_order' ).val() ).toBe( '' );
		expect( $( 'input[name="features-grid_order"]' ) ).toHaveLength( 1 );
	} );

	test( 'no ajax request is made on sort', () => {
		sortables[ 'details-grid' ].update();

		expect( ajax ).not.toHaveBeenCalled();
		expect( JSON.parse( $( '#details-grid_order' ).val() ) ).toEqual( [ 'price_per_day', 'seats' ] );
	} );
} );
