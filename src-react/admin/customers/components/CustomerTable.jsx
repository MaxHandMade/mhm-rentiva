import { __ } from '@wordpress/i18n';
import DataTable from '../../../../vendor/mhm/ui-core/src-react/components/DataTable';
import SortHeader from '../../../../vendor/mhm/ui-core/src-react/components/SortHeader';
import SelectAll from '../../../../vendor/mhm/ui-core/src-react/components/SelectAll';
import RowSelect from '../../../../vendor/mhm/ui-core/src-react/components/RowSelect';
import CustomerBadges from './CustomerBadges';

const SORTABLE = [
	{ key: 'name',        label: __( 'Customer', 'mhm-rentiva' ) },
	{ key: 'bookings',    label: __( 'Bookings', 'mhm-rentiva' ), align: 'end' },
	{ key: 'total_spent', label: __( 'Total Spent', 'mhm-rentiva' ), align: 'end' },
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

export default function CustomerTable( { items, sort, isSelected, selectState, panelId, onSort, onToggle, onSelectAll, onRowClick } ) {
	const sortTh = ( col ) => (
		<SortHeader
			label={ col.label }
			sortKey={ col.key }
			sort={ sort }
			onSort={ onSort }
			align={ col.align }
			className="rv-cust-th"
		/>
	);

	if ( items.length === 0 ) {
		return (
			<div className="rv-cust-empty">
				{ __( 'No customers found.', 'mhm-rentiva' ) }
			</div>
		);
	}

	return (
		<DataTable caption={ __( 'Customers', 'mhm-rentiva' ) } captionHidden className="rv-cust-table">
				<thead>
					<tr>
						<th scope="col" className="rv-cust-th is-check mhmui-table__select">
							<SelectAll
								state={ selectState }
								onChange={ onSelectAll }
								label={ __( 'Select all customers', 'mhm-rentiva' ) }
								disabled={ items.length === 0 }
							/>
						</th>
						{ sortTh( SORTABLE[ 0 ] ) }
						<th scope="col" className="rv-cust-th">{ __( 'Phone', 'mhm-rentiva' ) }</th>
						{ sortTh( SORTABLE[ 1 ] ) }
						{ sortTh( SORTABLE[ 2 ] ) }
						<th scope="col" className="rv-cust-th">{ __( 'Status', 'mhm-rentiva' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ items.map( ( c ) => {
						const [avBg, avColor] = avatarColors( c.id );
						return (
							<tr
								key={ c.id }
								className={ panelId === c.id ? 'is-open' : undefined }
								onClick={ () => onRowClick( c.id ) }
							>
								<td className="is-check mhmui-table__select" onClick={ ( e ) => e.stopPropagation() }>
									<RowSelect
										checked={ isSelected( c.id ) }
										onChange={ () => onToggle( c.id ) }
										label={ c.name }
									/>
								</td>
								<td>
									<div className="rv-cust-who">
										<span className="rv-cust-avatar" style={ { background: avBg, color: avColor } }>
											{ initials( c.name ) }
										</span>
										<span className="rv-cust-who__text">
											<span className="rv-cust-who__name">
												{ c.name }
												<CustomerBadges badges={ c.badges } />
											</span>
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
		</DataTable>
	);
}
