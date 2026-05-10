import { __ } from '@wordpress/i18n';

export default function StatsCards( { stats } ) {
	if ( ! stats ) return null;
	const cards = [
		{
			icon:  'dashicons-groups',
			value: stats.total ?? 0,
			label: __( 'Total Customers', 'mhm-rentiva' ),
			color: '#2563eb',
			bg:    '#eff6ff',
		},
		{
			icon:  'dashicons-heart',
			value: stats.active ?? 0,
			label: __( 'Active Customers', 'mhm-rentiva' ),
			color: '#059669',
			bg:    '#ecfdf5',
		},
		{
			icon:  'dashicons-plus-alt2',
			value: stats.new_this_month ?? 0,
			label: __( 'New This Month', 'mhm-rentiva' ),
			color: '#d97706',
			bg:    '#fffbeb',
		},
		{
			icon:  'dashicons-chart-line',
			value: stats.monthly_avg ?? 0,
			label: __( 'Monthly Average', 'mhm-rentiva' ),
			color: '#7c3aed',
			bg:    '#f5f3ff',
		},
	];

	return (
		<div className="mhm-stats-cards">
			{ cards.map( ( card, i ) => (
				<div key={ i } className="mhm-widget" style={ { display: 'flex', alignItems: 'center', gap: 12 } }>
					<div style={ { width: 40, height: 40, borderRadius: 10, background: card.bg, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 } }>
						<span className={ `dashicons ${ card.icon }` } style={ { color: card.color, fontSize: 20, width: 20, height: 20 } } />
					</div>
					<div>
						<div style={ { fontSize: 22, fontWeight: 700, lineHeight: 1.2 } }>{ card.value }</div>
						<div style={ { fontSize: 11, color: '#646970' } }>{ card.label }</div>
					</div>
				</div>
			) ) }
		</div>
	);
}
