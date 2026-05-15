import { __ } from '@wordpress/i18n';
import { fmtMoney } from '../../../shared/format';

export default function PendingPayments( { payments, currency, adminUrl } ) {
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
						<tr key={ `${ p.booking_id }-${ p.type ?? 'na' }` } className={ p.is_overdue ? 'mhm-overdue' : '' }>
							<td>
								<a href={ `${ adminUrl }post.php?post=${ p.booking_id }&action=edit` }>
									#{ p.display_id ?? p.booking_id }
								</a>
							</td>
							<td>{ p.customer_name || '—' }</td>
							<td>{ fmtMoney( p.amount, currency, 2 ) }</td>
							<td>
								{ p.type_label && (
									<span className={ `mhm-payment-type mhm-payment-type--${ p.type }` }>
										{ p.type_label }
									</span>
								) }
							</td>
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
