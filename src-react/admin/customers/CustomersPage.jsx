import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { rentivaApi } from '../../shared/api/rentiva';
import StatsCards from './components/StatsCards';
import SearchBar from './components/SearchBar';
import FilterBar from './components/FilterBar';
import CustomerTable from './components/CustomerTable';
import Pagination from './components/Pagination';
import CustomerPanel from './components/CustomerPanel';

export default function CustomersPage() {
	const cfg = window.mhmRentivaCustomers ?? {};

	const [page, setPage] = useState( 1 );
	const [search, setSearch] = useState( '' );
	const [sortBy, setSortBy] = useState( 'last_booking' );
	const [sortDir, setSortDir] = useState( 'desc' );
	const [selected, setSelected] = useState( [] );
	const [panelId, setPanelId] = useState( null );
	const [data, setData] = useState( null );
	const [loading, setLoading] = useState( false );
	const [error, setError] = useState( null );

	const fetchList = useCallback( () => {
		setLoading( true );
		setError( null );
		rentivaApi.customers.getList( { page, search, sort_by: sortBy, sort_dir: sortDir } )
			.then( setData )
			.catch( () => setError( __( 'Failed to load customers.', 'mhm-rentiva' ) ) )
			.finally( () => setLoading( false ) );
	}, [page, search, sortBy, sortDir] );

	useEffect( () => {
		setSelected( [] );
		fetchList();
	}, [fetchList] );

	const handleSearchChange = ( v ) => {
		setPage( 1 );
		setSearch( v );
	};

	const handleSort = ( col ) => {
		if ( col === sortBy ) {
			setSortDir( ( d ) => ( d === 'asc' ? 'desc' : 'asc' ) );
		} else {
			setSortBy( col );
			setSortDir( 'desc' );
		}
		setPage( 1 );
	};

	const handleSelectAll = ( checked ) => {
		setSelected( checked ? ( data?.items ?? [] ).map( ( c ) => c.id ) : [] );
	};

	const handleSelect = ( id, checked ) => {
		setSelected( ( prev ) =>
			checked ? [...prev, id] : prev.filter( ( x ) => x !== id )
		);
	};

	const handleBulkDelete = () => {
		if ( selected.length === 0 ) return;
		// translators: %d = number of customers
		if ( ! window.confirm( `${ __( 'Delete', 'mhm-rentiva' ) } ${ selected.length } ${ __( 'customer(s)? This cannot be undone.', 'mhm-rentiva' ) }` ) ) return;
		rentivaApi.customers.bulkDelete( selected )
			.then( () => {
				setSelected( [] );
				fetchList();
			} )
			.catch( () => setError( __( 'Bulk delete failed.', 'mhm-rentiva' ) ) );
	};

	const items = data?.items ?? [];

	return (
		<div className="mhm-customers">
			<StatsCards stats={ cfg.stats } />

			<div className="mhm-customers__toolbar">
				<SearchBar value={ search } onChange={ handleSearchChange } />
				<FilterBar
					search={ search }
					selectedIds={ selected }
					nonce={ cfg.export_nonce }
					adminUrl={ cfg.admin_url }
					addCustomerUrl={ cfg.add_customer_url }
				/>
			</div>

			{ selected.length > 0 && (
				<div className="mhm-customers__bulk-bar">
					<span>{ selected.length } { __( 'selected', 'mhm-rentiva' ) }</span>
					<button type="button" className="button button-link-delete" onClick={ handleBulkDelete }>
						{ __( 'Delete Selected', 'mhm-rentiva' ) }
					</button>
				</div>
			) }

			{ error && <div className="mhm-customers__error">{ error }</div> }

			{ loading && <div className="mhm-customers__loading">{ __( 'Loading…', 'mhm-rentiva' ) }</div> }

			{ ! loading && (
				<>
					<CustomerTable
						items={ items }
						sortBy={ sortBy }
						sortDir={ sortDir }
						selected={ selected }
						onSort={ handleSort }
						onSelect={ handleSelect }
						onSelectAll={ handleSelectAll }
						onRowClick={ setPanelId }
					/>
					<Pagination
						page={ page }
						totalPages={ data?.total_pages ?? 1 }
						onPageChange={ setPage }
					/>
				</>
			) }

			<CustomerPanel panelId={ panelId } onClose={ () => setPanelId( null ) } />
		</div>
	);
}
