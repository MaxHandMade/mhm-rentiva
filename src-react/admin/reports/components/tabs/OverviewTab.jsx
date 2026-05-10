import { __ } from '@wordpress/i18n';
import { fmtMoney } from '../../../../shared/format';

function MetricRow( { label, value } ) {
	return (
		<div className="mhm-reports__metric">
			<span className="mhm-reports__metric__label">{ label }</span>
			<span className="mhm-reports__metric__value">{ value }</span>
		</div>
	);
}

export default function OverviewTab( { data, currency } ) {
	const cur = currency ?? '';
	const rev = data?.revenue ?? {};
	const bk  = data?.bookings ?? {};
	const veh = data?.vehicles?.summary ?? {};
	const cus = data?.customers ?? {};

	return (
		<div className="mhm-reports__tab-content mhm-reports__overview">
			<div className="mhm-reports__overview-grid">

				<section className="mhm-reports__overview-card">
					<h3>{ __( 'Revenue', 'mhm-rentiva' ) }</h3>
					<MetricRow
						label={ __( 'Total Revenue', 'mhm-rentiva' ) }
						value={ fmtMoney( rev.total, cur ) }
					/>
					<MetricRow
						label={ __( 'Avg Daily Revenue', 'mhm-rentiva' ) }
						value={ fmtMoney( rev.avg_daily, cur ) }
					/>
				</section>

				<section className="mhm-reports__overview-card">
					<h3>{ __( 'Bookings', 'mhm-rentiva' ) }</h3>
					<MetricRow
						label={ __( 'Total Bookings', 'mhm-rentiva' ) }
						value={ bk.total_bookings ?? 0 }
					/>
					<MetricRow
						label={ __( 'Cancellation Rate', 'mhm-rentiva' ) }
						value={ `${ bk.cancellation_rate ?? 0 }%` }
					/>
				</section>

				<section className="mhm-reports__overview-card">
					<h3>{ __( 'Vehicles', 'mhm-rentiva' ) }</h3>
					<MetricRow
						label={ __( 'Active Vehicles', 'mhm-rentiva' ) }
						value={ veh.active_vehicles ?? 0 }
					/>
					<MetricRow
						label={ __( 'Avg Occupancy', 'mhm-rentiva' ) }
						value={ `${ veh.avg_occupancy_rate ?? 0 }%` }
					/>
				</section>

				<section className="mhm-reports__overview-card">
					<h3>{ __( 'Customers', 'mhm-rentiva' ) }</h3>
					<MetricRow
						label={ __( 'Total Customers', 'mhm-rentiva' ) }
						value={ cus.lifecycle?.total_customers ?? 0 }
					/>
					<MetricRow
						label={ __( 'Loyalty Rate', 'mhm-rentiva' ) }
						value={ `${ cus.summary?.loyalty_rate ?? 0 }%` }
					/>
				</section>

			</div>
		</div>
	);
}
