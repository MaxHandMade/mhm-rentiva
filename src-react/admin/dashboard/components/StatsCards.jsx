import { __ } from '@wordpress/i18n';

export default function StatsCards( { metrics, currency } ) {
	const fmt      = ( n ) => Number( n ?? 0 ).toLocaleString();
	const fmtMoney = ( n ) => `${ currency }${ Number( n ?? 0 ).toFixed( 2 ) }`;

	const cards = [
		{
			label: __( 'Total Bookings', 'mhm-rentiva' ),
			value: fmt( metrics?.total_bookings ),
			sub:   `${ fmt( metrics?.bookings_this_month ) } ${ __( 'this month', 'mhm-rentiva' ) }`,
			icon:  'dashicons-calendar-alt',
		},
		{
			label: __( 'Total Revenue', 'mhm-rentiva' ),
			value: fmtMoney( metrics?.total_revenue ),
			sub:   `${ fmtMoney( metrics?.monthly_revenue ) } ${ __( 'this month', 'mhm-rentiva' ) }`,
			icon:  'dashicons-money-alt',
		},
		{
			label: __( 'Active Vehicles', 'mhm-rentiva' ),
			value: fmt( metrics?.available_vehicles ),
			sub:   `${ fmt( metrics?.total_vehicles ) } ${ __( 'total', 'mhm-rentiva' ) }`,
			icon:  'dashicons-car',
		},
		{
			label: __( 'Customers', 'mhm-rentiva' ),
			value: fmt( metrics?.total_customers_this_month ),
			sub:   `${ fmt( metrics?.new_customers_this_month ) } ${ __( 'new this month', 'mhm-rentiva' ) }`,
			icon:  'dashicons-groups',
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
						<p className="mhm-stat-card__sub">{ card.sub }</p>
					</div>
				</div>
			) ) }
		</div>
	);
}
