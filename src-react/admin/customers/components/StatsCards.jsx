import { __ } from '@wordpress/i18n';

export default function StatsCards( { stats } ) {
	if ( ! stats ) return null;

	const cards = [
		{
			icon:  'dashicons-groups',
			value: stats.total ?? 0,
			label: __( 'Total Customers', 'mhm-rentiva' ),
		},
		{
			icon:  'dashicons-heart',
			value: stats.active ?? 0,
			label: __( 'Active Customers', 'mhm-rentiva' ),
		},
		{
			icon:  'dashicons-plus-alt2',
			value: stats.new_this_month ?? 0,
			label: __( 'New This Month', 'mhm-rentiva' ),
		},
		{
			icon:  'dashicons-chart-line',
			value: stats.monthly_avg ?? 0,
			label: __( 'Monthly Average', 'mhm-rentiva' ),
		},
	];

	return (
		<div className="mhm-stats-grid">
			{ cards.map( ( card ) => (
				<div key={ card.label } className="mhm-stat-card">
					<span className={ `dashicons ${ card.icon }` } />
					<div className="mhm-stat-card__body">
						<p className="mhm-stat-card__label">{ card.label }</p>
						<p className="mhm-stat-card__value">{ card.value }</p>
					</div>
				</div>
			) ) }
		</div>
	);
}
