import { __ } from '@wordpress/i18n';

const SORTABLE = [
	{ key: 'name',        label: __( 'Customer', 'mhm-rentiva' ) },
	{ key: 'bookings',    label: __( 'Bookings', 'mhm-rentiva' ), align: 'right' },
	{ key: 'total_spent', label: __( 'Total Spent', 'mhm-rentiva' ), align: 'right' },
];

// Avatar background/foreground pairs cycled by customer id.
const AVATAR_PALETTE = [
	['#e5f0fb', '#135e96'],
	['#e4f6e9', '#0a6b1e'],
	['#fdf0e4', '#a15b1e'],
	['#f3e8fb', '#6b2fa0'],
	['#fbe9f1', '#9e2b63'],
	['#e9f6f6', '#0f6b6b'],
	['#eef2f7', '#41505f'],
	['#fcf3d6', '#8a6d1b'],
];

export const STATUS_LABELS = {
	vip:    __( 'VIP', 'mhm-rentiva' ),
	new:    __( 'New', 'mhm-rentiva' ),
	active: __( 'Active', 'mhm-rentiva' ),
};

export function initials( name ) {
	return String( name || '' )
		.trim()
		.split( /\s+/ )
		.map( ( w ) => w.charAt( 0 ) )
		.slice( 0, 2 )
		.join( '' )
		.toUpperCase();
}

export function avatarColors( id ) {
	return AVATAR_PALETTE[ Math.abs( id ) % AVATAR_PALETTE.length ];
}

export default function CustomerTable( { items, sortBy, sortDir, selected, panelId, onSort, onSelect, onSelectAll, onRowClick } ) {
	const allSelected = items.length > 0 && items.every( ( c ) => selected.includes( c.id ) );

	const SortTh = ( { col } ) => {
		const isActive = sortBy === col.key;
		let icon = '⇅';
		if ( isActive ) {
			icon = sortDir === 'asc' ? '▲' : '▼';
		}
		return (
			<th
				className={ `rv-cust-th sortable${ isActive ? ' sort-active' : '' }${ col.align === 'right' ? ' is-right' : '' }` }
				onClick={ () => onSort( col.key ) }
			>
				{ col.label } <span className="sort-icon">{ icon }</span>
			</th>
		);
	};

	if ( items.length === 0 ) {
		return (
			<div className="rv-cust-empty">
				{ __( 'No customers found.', 'mhm-rentiva' ) }
			</div>
		);
	}

	return (
		<div className="rv-cust-table-wrap">
			<table className="rv-cust-table">
				<thead>
					<tr>
						<th className="rv-cust-th is-check">
							<input
								type="checkbox"
								checked={ allSelected }
								onChange={ ( e ) => onSelectAll( e.target.checked ) }
								aria-label={ __( 'Select all customers', 'mhm-rentiva' ) }
							/>
						</th>
						<SortTh col={ SORTABLE[0] } />
						<th className="rv-cust-th">{ __( 'Phone', 'mhm-rentiva' ) }</th>
						<SortTh col={ SORTABLE[1] } />
						<SortTh col={ SORTABLE[2] } />
						<th className="rv-cust-th">{ __( 'Status', 'mhm-rentiva' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ items.map( ( c ) => {
						const [avBg, avColor] = avatarColors( c.id );
						return (
							<tr
								key={ c.id }
								className={ `${ selected.includes( c.id ) ? 'is-checked ' : '' }${ panelId === c.id ? 'is-open' : '' }` }
								onClick={ () => onRowClick( c.id ) }
							>
								<td className="is-check" onClick={ ( e ) => e.stopPropagation() }>
									<input
										type="checkbox"
										checked={ selected.includes( c.id ) }
										onChange={ ( e ) => onSelect( c.id, e.target.checked ) }
										aria-label={ c.name }
									/>
								</td>
								<td>
									<div className="rv-cust-who">
										<span className="rv-cust-avatar" style={ { background: avBg, color: avColor } }>
											{ initials( c.name ) }
										</span>
										<span className="rv-cust-who__text">
											<span className="rv-cust-who__name">{ c.name }</span>
											<span className="rv-cust-who__email">{ c.email }</span>
										</span>
									</div>
								</td>
								<td className="rv-cust-phone">{ c.phone }</td>
								<td className="is-right">{ c.booking_count }</td>
								{ /* Already canonical (symbol + WooCommerce placement) from PHP. */ }
								<td className="is-right rv-cust-total">{ c.total_spent }</td>
								<td>
									{ STATUS_LABELS[ c.status ] ? (
										<span className={ `rv-cust-tag is-${ c.status }` }>{ STATUS_LABELS[ c.status ] }</span>
									) : (
										<span className="rv-cust-tag is-none">—</span>
									) }
								</td>
							</tr>
						);
					} ) }
				</tbody>
			</table>
		</div>
	);
}
