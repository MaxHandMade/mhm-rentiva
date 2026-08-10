import { __ } from '@wordpress/i18n';

export default function StatsCards( { stats, currency } ) {
	if ( ! stats ) return null;

	const trend    = stats.new_trend || '';
	const trendUp  = trend.startsWith( '+' ) && trend !== '+0%';
	const avgSpend = Number( stats.avg_spend ?? 0 );

	const cards = [
		{
			value: stats.total ?? 0,
			label: __( 'Total Customers', 'mhm-rentiva' ),
		},
		{
			value: stats.new_this_month ?? 0,
			label: __( 'New This Month', 'mhm-rentiva' ),
			sub:   trend,
			tone:  trendUp ? 'up' : 'down',
		},
		{
			value: stats.active_90d ?? 0,
			label: __( 'Active Customers', 'mhm-rentiva' ),
			sub:   __( 'last 90 days', 'mhm-rentiva' ),
			tone:  'info',
		},
		{
			value: `${ currency ?? '' }${ avgSpend.toLocaleString() }`,
			label: __( 'Avg. Spend', 'mhm-rentiva' ),
			sub:   __( 'per customer', 'mhm-rentiva' ),
		},
	];

	return (
		<div className="rv-cust-kpis">
			{ cards.map( ( card ) => (
				<div key={ card.label } className="rv-cust-kpi">
					<div className="rv-cust-kpi__label">{ card.label }</div>
					<div className="rv-cust-kpi__row">
						<span className="rv-cust-kpi__value">{ card.value }</span>
						{ card.sub && (
							<span className={ `rv-cust-kpi__sub${ card.tone ? ` is-${ card.tone }` : '' }` }>
								{ card.sub }
							</span>
						) }
					</div>
				</div>
			) ) }
		</div>
	);
}
