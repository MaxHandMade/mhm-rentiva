import { __ } from '@wordpress/i18n';
import { fmtMoney } from '../../../../shared/format';

export default function CustomersTab( { data, currency } ) {
	const cur = currency ?? '';
	const summary   = data?.summary ?? {};
	const lifecycle = data?.lifecycle ?? {};
	const customers = ( data?.customers ?? [] ).slice( 0, 10 );

	return (
		<div className="mhm-reports__tab-content">

			{ /* KPI summary row */ }
			<div className="mhm-kpi-row" style={ { gridTemplateColumns: 'repeat(4, 1fr)' } }>
				<div className="mhm-kpi-box mhm-kpi-box--blue">
					<div className="mhm-kpi-box__value">{ lifecycle.total_customers ?? summary.total_customers ?? 0 }</div>
					<div className="mhm-kpi-box__label">{ __( 'Total Customers', 'mhm-rentiva' ) }</div>
				</div>
				<div className="mhm-kpi-box mhm-kpi-box--green">
					<div className="mhm-kpi-box__value">{ lifecycle.new_customers ?? 0 }</div>
					<div className="mhm-kpi-box__label">{ __( 'New Customers', 'mhm-rentiva' ) }</div>
				</div>
				<div className="mhm-kpi-box mhm-kpi-box--amber">
					<div className="mhm-kpi-box__value">{ summary.loyalty_rate ?? 0 }%</div>
					<div className="mhm-kpi-box__label">{ __( 'Loyalty Rate', 'mhm-rentiva' ) }</div>
				</div>
				<div className="mhm-kpi-box mhm-kpi-box--grey">
					<div className="mhm-kpi-box__value">{ fmtMoney( summary.avg_spending, cur ) }</div>
					<div className="mhm-kpi-box__label">{ __( 'Avg Spending', 'mhm-rentiva' ) }</div>
				</div>
			</div>

			{ /* Lifecycle split */ }
			<div className="mhm-reports__section">
				<h3 className="mhm-reports__section-title">{ __( 'Customer Lifecycle', 'mhm-rentiva' ) }</h3>
				<div className="mhm-kpi-row" style={ { gridTemplateColumns: 'repeat(3, 1fr)', marginBottom: 0 } }>
					<div className="mhm-kpi-box mhm-kpi-box--blue">
						<div className="mhm-kpi-box__value">{ lifecycle.total_customers ?? 0 }</div>
						<div className="mhm-kpi-box__label">{ __( 'Total', 'mhm-rentiva' ) }</div>
					</div>
					<div className="mhm-kpi-box mhm-kpi-box--green">
						<div className="mhm-kpi-box__value">{ lifecycle.new_customers ?? 0 }</div>
						<div className="mhm-kpi-box__label">{ __( 'New', 'mhm-rentiva' ) }</div>
					</div>
					<div className="mhm-kpi-box mhm-kpi-box--amber">
						<div className="mhm-kpi-box__value">{ lifecycle.returning_customers ?? 0 }</div>
						<div className="mhm-kpi-box__label">{ __( 'Returning', 'mhm-rentiva' ) }</div>
					</div>
				</div>
			</div>

			{ /* Top 10 Customers table */ }
			<div className="mhm-reports__section">
				<h3 className="mhm-reports__section-title">{ __( 'Top Customers by Spend', 'mhm-rentiva' ) }</h3>
				{ customers.length === 0 ? (
					<p className="mhm-empty">{ __( 'No customer data for this period.', 'mhm-rentiva' ) }</p>
				) : (
					<table className="mhm-reports__table">
						<thead>
							<tr>
								<th>{ __( 'Name', 'mhm-rentiva' ) }</th>
								<th>{ __( 'Email', 'mhm-rentiva' ) }</th>
								<th>{ __( 'Bookings', 'mhm-rentiva' ) }</th>
								<th>{ __( 'Total Spent', 'mhm-rentiva' ) }</th>
								<th>{ __( 'Last Booking', 'mhm-rentiva' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ customers.map( ( c, i ) => (
								<tr key={ i }>
									<td>{ c.name || '—' }</td>
									<td>{ c.email }</td>
									<td>{ c.booking_count }</td>
									<td>{ fmtMoney( c.total_spent, cur ) }</td>
									<td>{ c.last_booking_date || '—' }</td>
								</tr>
							) ) }
						</tbody>
					</table>
				) }
			</div>

		</div>
	);
}
