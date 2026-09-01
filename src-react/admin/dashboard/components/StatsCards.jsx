import { __ } from '@wordpress/i18n';
import { fmtAmount, fmtMoney as fmtMon } from '../../../shared/format';

function DeltaLine( { delta, fallbackSub } ) {
	if ( ! delta || delta.format === 'neutral' ) {
		// A card with neither a delta nor a sub should render nothing, not an
		// empty paragraph holding open a line of whitespace.
		return fallbackSub ? <p className="mhm-stat-card__sub">{ fallbackSub }</p> : null;
	}
	const arrows = { up: '↑', down: '↓' };
	const arrow  = arrows[ delta.direction ] ?? '';
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
			// The value is people who booked THIS MONTH, not the customer
			// population. Labelling it "Customers" put the same word on two
			// screens counting different sets -- this card said 3 while the
			// Customers screen listed 11 accounts, and the two only overlapped
			// in 6 people. Naming the action removes the collision instead of
			// changing a number that is right for a dashboard.
			label: __( 'Renting this month', 'mhm-rentiva' ),
			value: fmt( metrics?.total_customers_this_month ),
			delta: deltas.customers,
			// No sub: it read "N new this month" where N was the card's own
			// value -- the query windows both to the current month, so they were
			// the same number by construction -- and DeltaLine only renders the
			// fallback when there is no delta, so it never reached the screen.
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
