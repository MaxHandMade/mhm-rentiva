import { __ } from '@wordpress/i18n';

const SORTABLE = [
	{ key: 'name',         label: __( 'Name', 'mhm-rentiva' ) },
	{ key: 'email',        label: __( 'Email', 'mhm-rentiva' ) },
	{ key: 'bookings',     label: __( 'Bookings', 'mhm-rentiva' ) },
	{ key: 'total_spent',  label: __( 'Total Spent', 'mhm-rentiva' ) },
	{ key: 'last_booking', label: __( 'Last Booking', 'mhm-rentiva' ) },
	{ key: 'date',         label: __( 'Registered', 'mhm-rentiva' ) },
];

export default function CustomerTable( { items, sortBy, sortDir, selected, onSort, onSelect, onSelectAll, onRowClick } ) {
	const allSelected = items.length > 0 && items.every( ( c ) => selected.includes( c.id ) );

	const SortTh = ( { col } ) => {
		const isActive = sortBy === col.key;
		const icon     = isActive ? ( sortDir === 'asc' ? '▲' : '▼' ) : '⇅';
		return (
			<th
				className={ `sortable${ isActive ? ' sort-active' : '' }` }
				onClick={ () => onSort( col.key ) }
			>
				{ col.label } <span className="sort-icon">{ icon }</span>
			</th>
		);
	};

	if ( items.length === 0 ) {
		return (
			<div className="mhm-customers__empty">
				{ __( 'No customers found.', 'mhm-rentiva' ) }
			</div>
		);
	}

	return (
		<div className="mhm-customers__table-wrap">
			<table className="mhm-customers__table">
				<thead>
					<tr>
						<th>
							<input
								type="checkbox"
								checked={ allSelected }
								onChange={ ( e ) => onSelectAll( e.target.checked ) }
							/>
						</th>
						{ SORTABLE.map( ( col ) => <SortTh key={ col.key } col={ col } /> ) }
						<th>{ __( 'Phone', 'mhm-rentiva' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ items.map( ( c ) => (
						<tr
							key={ c.id }
							className={ selected.includes( c.id ) ? 'selected' : '' }
							onClick={ () => onRowClick( c.id ) }
						>
							<td onClick={ ( e ) => e.stopPropagation() }>
								<input
									type="checkbox"
									checked={ selected.includes( c.id ) }
									onChange={ ( e ) => onSelect( c.id, e.target.checked ) }
								/>
							</td>
							<td>{ c.name }</td>
							<td>{ c.email }</td>
							<td>{ c.booking_count }</td>
							<td>{ c.total_spent }</td>
							<td>{ c.last_booking }</td>
							<td>{ c.created_date }</td>
							<td>{ c.phone }</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}
