import { __ } from '@wordpress/i18n';
import { fmtAmount, fmtMoney as fmtMon } from '../../../shared/format';

function DeltaLine( { delta, fallbackSub } ) {
	if ( ! delta || delta.format === 'neutral' ) {
		return <p className="mhm-stat-card__sub">{ fallbackSub }</p>;
	}
	const arrow = delta.direction === 'up' ? '↑' : delta.direction === 'down' ? '↓' : '';
	const text  = delta.format === 'pct'
		? `${ arrow } %${ Math.abs( delta.value ) } ${ __( 'this month', 'mhm-rentiva' ) }`
		: `+${ delta.value } ${ __( 'this month', 'mhm-rentiva' ) }`;
	return <p className={ `mhm-stat-card__delta mhm-stat-card__delta--${ delta.direction }` }>{ text }</p>;
}

export default function StatsCards( { metrics, deltas = {}, currency } ) {
	const fmt      = ( n ) => fmtAmount( n, 0 );
	const fmtMoney = ( n ) => fmtMon( n, currency );

	const cards = [
		{
			label: __( 'Total Bookings', 'mhm-rentiva' ),
			value: fmt( metrics?.total_bookings ),
			delta: deltas.bookings,
			sub:   `${ fmt( metrics?.bookings_this_month ) } ${ __( 'this month', 'mhm-rentiva' ) }`,
			icon:  'dashicons-calendar-alt',
		},
		{
			label: __( 'Total Revenue', 'mhm-rentiva' ),
			value: fmtMoney( metrics?.total_revenue ),
			delta: deltas.revenue,
			sub:   `${ fmtMoney( metrics?.monthly_revenue ) } ${ __( 'this month', 'mhm-rentiva' ) }`,
			icon:  'dashicons-money-alt',
		},
		{
			label: __( 'Active Vehicles', 'mhm-rentiva' ),
			value: fmt( metrics?.available_vehicles ),
			delta: null, // vehicles have no period delta — show total as neutral sub
			sub:   `${ fmt( metrics?.total_vehicles ) } ${ __( 'total', 'mhm-rentiva' ) }`,
			icon:  'dashicons-car',
		},
		{
			label: __( 'Customers', 'mhm-rentiva' ),
			value: fmt( metrics?.total_customers_this_month ),
			delta: deltas.customers,
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
						<DeltaLine delta={ card.delta } fallbackSub={ card.sub } />
					</div>
				</div>
			) ) }
		</div>
	);
}
