import { __ } from '@wordpress/i18n';
import { fmtAmount, fmtMoney } from '../../../shared/format';

export default function StatsCards( { statsCards, currency } ) {
	const cards = [
		{
			label: __( 'Total Bookings', 'mhm-rentiva' ),
			value: fmtAmount( statsCards?.total_bookings, 0 ),
			icon:  'dashicons-calendar-alt',
		},
		{
			label: __( 'Monthly Revenue', 'mhm-rentiva' ),
			value: fmtMoney( statsCards?.monthly_revenue, currency ),
			icon:  'dashicons-money-alt',
		},
		{
			label: __( 'Active Bookings', 'mhm-rentiva' ),
			value: statsCards?.active_bookings ?? '—',
			icon:  'dashicons-car',
		},
		{
			label: __( 'Occupancy Rate', 'mhm-rentiva' ),
			value: `${ statsCards?.occupancy_rate ?? '0' }%`,
			icon:  'dashicons-chart-pie',
		},
	];

	return (
		<div className="mhm-stats-grid">
			{ cards.map( ( card ) => (
				<div key={ card.label } className="mhm-stat-card">
					<span className={ `dashicons ${ card.icon }` } />
					<div className="mhm-stat-card__body">
						<p className="mhm-stat-card__value">{ card.value }</p>
						<p className="mhm-stat-card__label">{ card.label }</p>
					</div>
				</div>
			) ) }
		</div>
	);
}
