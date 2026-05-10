import { __ } from '@wordpress/i18n';

export default function MessageTable( { items, selected, onSelect, onSelectAll, onRowClick } ) {
	const allSelected = items.length > 0 && selected.length === items.length;

	return (
		<table className="mhm-messages-table widefat fixed striped">
			<thead>
				<tr>
					<th style={ { width: 32 } }>
						<input
							type="checkbox"
							checked={ allSelected }
							onChange={ ( e ) => onSelectAll( e.target.checked ? items.map( ( m ) => m.id ) : [] ) }
						/>
					</th>
					<th>{ __( 'Subject', 'mhm-rentiva' ) }</th>
					<th>{ __( 'Customer', 'mhm-rentiva' ) }</th>
					<th>{ __( 'Category', 'mhm-rentiva' ) }</th>
					<th>{ __( 'Status', 'mhm-rentiva' ) }</th>
					<th>{ __( 'Priority', 'mhm-rentiva' ) }</th>
					<th>{ __( 'Date', 'mhm-rentiva' ) }</th>
				</tr>
			</thead>
			<tbody>
				{ items.length === 0 && (
					<tr>
						<td colSpan={ 7 } className="mhm-empty">
							{ __( 'No messages found.', 'mhm-rentiva' ) }
						</td>
					</tr>
				) }
				{ items.map( ( msg ) => (
					<tr
						key={ msg.id }
						className={ ! msg.is_read ? 'mhm-unread' : '' }
					>
						<td onClick={ ( e ) => e.stopPropagation() }>
							<input
								type="checkbox"
								checked={ selected.includes( msg.id ) }
								onChange={ ( e ) => {
									onSelect( e.target.checked
										? [ ...selected, msg.id ]
										: selected.filter( ( id ) => id !== msg.id )
									);
								} }
							/>
						</td>
						<td onClick={ () => onRowClick( msg.id ) }>
							{ ! msg.is_read && <span className="mhm-unread-dot" /> }
							{ msg.subject }
						</td>
						<td onClick={ () => onRowClick( msg.id ) }>{ msg.customer_name }</td>
						<td onClick={ () => onRowClick( msg.id ) }>{ msg.category_label }</td>
						<td onClick={ () => onRowClick( msg.id ) }>
							<span className={ `mhm-status mhm-status--${ msg.status }` }>
								{ msg.status_label }
							</span>
						</td>
						<td onClick={ () => onRowClick( msg.id ) }>{ msg.priority ?? 'normal' }</td>
						<td onClick={ () => onRowClick( msg.id ) }>{ msg.date_human }</td>
					</tr>
				) ) }
			</tbody>
		</table>
	);
}
