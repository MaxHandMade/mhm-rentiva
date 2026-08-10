import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { rentivaApi } from '../../shared/api/rentiva';
import StatsCards from './components/StatsCards';
import SearchBar from './components/SearchBar';
import FilterBar from './components/FilterBar';
import CustomerTable from './components/CustomerTable';
import Pagination from './components/Pagination';
import CustomerPanel from './components/CustomerPanel';

const STATUS_FILTERS = [
	{ key: 'all',    label: __( 'All', 'mhm-rentiva' ) },
	{ key: 'active', label: __( 'Active', 'mhm-rentiva' ) },
	{ key: 'new',    label: __( 'New', 'mhm-rentiva' ) },
	{ key: 'vip',    label: __( 'VIP', 'mhm-rentiva' ) },
];

export default function CustomersPage() {
	const cfg = window.mhmRentivaCustomers ?? {};

	const [page, setPage] = useState( 1 );
	const [search, setSearch] = useState( '' );
	const [status, setStatus] = useState( 'all' );
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
		rentivaApi.customers.getList( { page, search, sort_by: sortBy, sort_dir: sortDir, status } )
			.then( setData )
			.catch( () => setError( __( 'Failed to load customers.', 'mhm-rentiva' ) ) )
			.finally( () => setLoading( false ) );
	}, [page, search, sortBy, sortDir, status] );

	useEffect( () => {
		setSelected( [] );
		fetchList();
	}, [fetchList] );

	const handleSearchChange = ( v ) => {
		setPage( 1 );
		setSearch( v );
	};

	const handleStatusChange = ( v ) => {
		setPage( 1 );
		setStatus( v );
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
				setPanelId( null );
				fetchList();
			} )
			.catch( () => setError( __( 'Bulk delete failed.', 'mhm-rentiva' ) ) );
	};

	const items = data?.items ?? [];
	const panelRow = items.find( ( c ) => c.id === panelId ) ?? null;

	return (
		<div className="mhm-customers rv-cust">
			<div className="rv-cust-topbar">
				<StatsCards stats={ cfg.stats } currency={ cfg.currency } />
				<FilterBar
					search={ search }
					selectedIds={ selected }
					nonce={ cfg.export_nonce }
					adminUrl={ cfg.admin_url }
					addCustomerUrl={ cfg.add_customer_url }
				/>
			</div>

			<div className="rv-cust-layout">
				<div className="rv-cust-main">
					<div className="rv-cust-card">
						<div className="rv-cust-toolbar">
							<SearchBar value={ search } onChange={ handleSearchChange } />
							<select
								className="rv-cust-status-filter"
								value={ status }
								onChange={ ( e ) => handleStatusChange( e.target.value ) }
								aria-label={ __( 'Filter by status', 'mhm-rentiva' ) }
							>
								{ STATUS_FILTERS.map( ( f ) => (
									<option key={ f.key } value={ f.key }>{ f.label }</option>
								) ) }
							</select>
						</div>

						{ selected.length > 0 && (
							<div className="rv-cust-bulk-bar">
								<span>{ selected.length } { __( 'selected', 'mhm-rentiva' ) }</span>
								<button type="button" className="button button-link-delete" onClick={ handleBulkDelete }>
									{ __( 'Delete Selected', 'mhm-rentiva' ) }
								</button>
							</div>
						) }

						{ error && <div className="rv-cust-error">{ error }</div> }
						{ loading && <div className="rv-cust-loading">{ __( 'Loading…', 'mhm-rentiva' ) }</div> }

						{ ! loading && (
							<CustomerTable
								items={ items }
								sortBy={ sortBy }
								sortDir={ sortDir }
								selected={ selected }
								panelId={ panelId }
								onSort={ handleSort }
								onSelect={ handleSelect }
								onSelectAll={ handleSelectAll }
								onRowClick={ setPanelId }
							/>
						) }
					</div>

					{ ! loading && (
						<Pagination
							page={ page }
							totalPages={ data?.total_pages ?? 1 }
							onPageChange={ setPage }
						/>
					) }
				</div>

				<CustomerPanel
					panelId={ panelId }
					row={ panelRow }
					currency={ cfg.currency }
					adminUrl={ cfg.admin_url }
					onClose={ () => setPanelId( null ) }
				/>
			</div>
		</div>
	);
}
