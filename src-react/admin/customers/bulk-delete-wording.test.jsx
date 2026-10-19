import { render, fireEvent, waitFor } from '@testing-library/react';
import { setLocaleData, resetLocaleData } from '@wordpress/i18n';

/**
 * Plan 0.4: the bulk-delete confirm was built from three pieces
 * (`Delete` + n + `customer(s)? ...`), so Turkish read "Sil 3 müşteri silinsin?"
 * and the count bar had no plural. Both are single _n() patterns now.
 */
jest.mock( '../../shared/api/rentiva', () => ( {
	rentivaApi: {
		customers: {
			getList: jest.fn( () => Promise.resolve( { items: [ { id: 1, name: 'A', email: 'a@x.test' }, { id: 2, name: 'B', email: 'b@x.test' } ], total: 2, pages: 1, stats: {} } ) ),
			bulkDelete: jest.fn( () => Promise.resolve( {} ) ),
		},
	},
} ) );

describe( 'customers bulk delete wording', () => {
	afterEach( () => resetLocaleData( undefined, 'mhm-rentiva' ) );

	test( 'the confirm and the count bar are whole translated sentences', async () => {
		setLocaleData(
			{
				'': { plural_forms: 'nplurals=2; plural=(n > 1);' },
				'Delete %d customer? This cannot be undone.': [ '%d müşteri silinsin mi? Bu işlem geri alınamaz.', '%d müşteri silinsin mi? Bu işlem geri alınamaz.' ],
				'%d selected': [ '%d seçili', '%d seçili' ],
			},
			'mhm-rentiva'
		);
		const confirm = jest.spyOn( window, 'confirm' ).mockReturnValue( false );
		const CustomersPage = require( './CustomersPage' ).default;

		const { container, findAllByRole } = render( <CustomersPage /> );
		const boxes = await findAllByRole( 'checkbox' );
		boxes.filter( ( b ) => ! b.closest( 'thead' ) ).slice( 0, 2 ).forEach( ( b ) => fireEvent.click( b ) );

		await waitFor( () => expect( container.textContent ).toContain( '2 seçili' ) );
		fireEvent.click( container.querySelector( '.rv-cust-bulk-bar button' ) );

		expect( confirm ).toHaveBeenCalledWith( '2 müşteri silinsin mi? Bu işlem geri alınamaz.' );
		confirm.mockRestore();
	} );
} );
