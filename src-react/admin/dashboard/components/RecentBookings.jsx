import { __ } from '@wordpress/i18n';

const statusLabel = ( status ) => ( {
	pending:     __( 'Pending',     'mhm-rentiva' ),
	confirmed:   __( 'Confirmed',   'mhm-rentiva' ),
	in_progress: __( 'In Progress', 'mhm-rentiva' ),
	completed:   __( 'Completed',   'mhm-rentiva' ),
	cancelled:   __( 'Cancelled',   'mhm-rentiva' ),
}[ status ] ?? status );

export default function RecentBookings( { bookings, adminUrl } ) {
	if ( ! bookings?.length ) {
		return (
			<div className="mhm-widget mhm-recent-bookings">
				<h3>{ __( 'Recent Bookings', 'mhm-rentiva' ) }</h3>
				<p className="mhm-empty">{ __( 'No bookings yet.', 'mhm-rentiva' ) }</p>
			</div>
		);
	}

	return (
		<div className="mhm-widget mhm-recent-bookings">
			<h3>{ __( 'Recent Bookings', 'mhm-rentiva' ) }</h3>
			<table className="widefat fixed striped">
				<tbody>
					{ bookings.map( ( b ) => (
						<tr key={ b.id }>
							<td>
								<a href={ `${ adminUrl }post.php?post=${ b.id }&action=edit` }>
									#{ b.id }
								</a>
							</td>
							<td>{ b.customer_name || '—' }</td>
							<td>{ b.vehicle_title || '—' }</td>
							<td>{ b.pickup_date || '—' }</td>
							<td>
								<span className={ `mhm-status mhm-status--${ b.status }` }>
									{ statusLabel( b.status ) }
								</span>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}
