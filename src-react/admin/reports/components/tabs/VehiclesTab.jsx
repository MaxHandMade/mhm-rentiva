import { __ } from '@wordpress/i18n';

export default function VehiclesTab( { data, currency } ) {
	const cur     = currency ?? '';
	const summary = data?.summary ?? {};
	const topVeh  = data?.top_vehicles ?? [];
	const cats    = data?.category_performance ?? [];

	return (
		<div className="mhm-reports__tab-content">

			{ /* KPI summary row */ }
			<div className="mhm-kpi-row" style={ { gridTemplateColumns: 'repeat(4, 1fr)' } }>
				<div className="mhm-kpi-box mhm-kpi-box--blue">
					<div className="mhm-kpi-box__value">{ summary.total_vehicles ?? 0 }</div>
					<div className="mhm-kpi-box__label">{ __( 'Total Vehicles', 'mhm-rentiva' ) }</div>
				</div>
				<div className="mhm-kpi-box mhm-kpi-box--green">
					<div className="mhm-kpi-box__value">{ summary.active_vehicles ?? 0 }</div>
					<div className="mhm-kpi-box__label">{ __( 'Active Vehicles', 'mhm-rentiva' ) }</div>
				</div>
				<div className="mhm-kpi-box mhm-kpi-box--amber">
					<div className="mhm-kpi-box__value">{ summary.avg_occupancy_rate ?? 0 }%</div>
					<div className="mhm-kpi-box__label">{ __( 'Avg Occupancy', 'mhm-rentiva' ) }</div>
				</div>
				<div className="mhm-kpi-box mhm-kpi-box--grey">
					<div className="mhm-kpi-box__value">{ cur }{ summary.total_revenue ?? 0 }</div>
					<div className="mhm-kpi-box__label">{ __( 'Total Revenue', 'mhm-rentiva' ) }</div>
				</div>
			</div>

			{ /* Top Vehicles table */ }
			<div className="mhm-reports__section">
				<h3 className="mhm-reports__section-title">{ __( 'Top Vehicles by Bookings', 'mhm-rentiva' ) }</h3>
				{ topVeh.length === 0 ? (
					<p className="mhm-empty">{ __( 'No vehicle data for this period.', 'mhm-rentiva' ) }</p>
				) : (
					<table className="mhm-reports__table">
						<thead>
							<tr>
								<th>{ __( 'Vehicle', 'mhm-rentiva' ) }</th>
								<th>{ __( 'Bookings', 'mhm-rentiva' ) }</th>
								<th>{ __( 'Revenue', 'mhm-rentiva' ) }</th>
								<th>{ __( 'Avg / Booking', 'mhm-rentiva' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ topVeh.map( ( v ) => (
								<tr key={ v.vehicle_id }>
									<td>{ v.vehicle_title }</td>
									<td>{ v.booking_count }</td>
									<td>{ cur }{ v.total_revenue }</td>
									<td>{ cur }{ v.avg_revenue_per_booking }</td>
								</tr>
							) ) }
						</tbody>
					</table>
				) }
			</div>

			{ /* Category Performance table */ }
			<div className="mhm-reports__section">
				<h3 className="mhm-reports__section-title">{ __( 'Category Performance', 'mhm-rentiva' ) }</h3>
				{ cats.length === 0 ? (
					<p className="mhm-empty">{ __( 'No category data for this period.', 'mhm-rentiva' ) }</p>
				) : (
					<table className="mhm-reports__table">
						<thead>
							<tr>
								<th>{ __( 'Category', 'mhm-rentiva' ) }</th>
								<th>{ __( 'Vehicles', 'mhm-rentiva' ) }</th>
								<th>{ __( 'Bookings', 'mhm-rentiva' ) }</th>
								<th>{ __( 'Revenue', 'mhm-rentiva' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ cats.map( ( c, i ) => (
								<tr key={ i }>
									<td>{ c.category_name }</td>
									<td>{ c.vehicle_count }</td>
									<td>{ c.booking_count }</td>
									<td>{ cur }{ c.total_revenue }</td>
								</tr>
							) ) }
						</tbody>
					</table>
				) }
			</div>

		</div>
	);
}
