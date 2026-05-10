import { __ } from '@wordpress/i18n';

export default function PendingPayments( { payments, adminUrl } ) {
	if ( ! payments?.length ) {
		return (
			<div className="mhm-widget mhm-pending-payments">
				<h3><span className="dashicons dashicons-money-alt" />{ __( 'Pending Payments', 'mhm-rentiva' ) }</h3>
				<p className="mhm-empty">{ __( 'No pending payments.', 'mhm-rentiva' ) }</p>
			</div>
		);
	}

	return (
		<div className="mhm-widget mhm-pending-payments">
			<h3><span className="dashicons dashicons-money-alt" />{ __( 'Pending Payments', 'mhm-rentiva' ) }</h3>
			<table className="widefat fixed striped">
				<tbody>
					{ payments.map( ( p ) => (
						<tr key={ p.booking_id } className={ p.is_overdue ? 'mhm-overdue' : '' }>
							<td>
								<a href={ `${ adminUrl }post.php?post=${ p.booking_id }&action=edit` }>
									#{ p.booking_id }
								</a>
							</td>
							<td>{ p.customer_name || '—' }</td>
							<td>{ p.amount }</td>
							<td>{ p.deadline }</td>
							<td>
								<span className={ `mhm-status mhm-status--${ p.status }` }>
									{ p.status_label }
								</span>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}
