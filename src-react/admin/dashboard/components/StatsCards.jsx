import { __ } from '@wordpress/i18n';
import StatsGrid from '../../../../vendor/mhm/ui-core/src-react/components/StatsGrid';
import { fmtAmount, fmtMoney as fmtMon } from '../../../shared/format';

/**
 * The kit takes a formatted delta line; the wording stays here, because it
 * is this product's copy, not the kit's. Since ui-core 0.12.0 the kit itself
 * renders the up/down direction mark, so `text` must stay plain -- no arrow,
 * no sign -- or the mark appears twice.
 *
 * `format: 'neutral'` is DashboardService::shape_delta()'s "no comparison
 * exists" case (current AND previous both zero -- nothing happened in
 * either period), not "zero trend": that stays returning nothing, there is
 * no delta to show. A genuine zero trend (previous > 0, 0% change) comes
 * through as `format: 'pct'`, `direction: 'none'` -- 'none' is not one of
 * the kit's DIRECTIONS, so since ui-core 0.13.0 (flat gets its own line) it
 * is mapped to 'flat' here rather than left to silently miss the kit's
 * direction check.
 *
 * @param {Object} delta { direction, format, value } from the REST payload.
 * @return {Object|undefined} { direction, text, label } for StatCard, or undefined.
 */
function toDelta( delta ) {
	if ( ! delta || delta.format === 'neutral' ) {
		return undefined;
	}
	const direction = delta.direction === 'none' ? 'flat' : delta.direction;
	// Accessible name for the delta line (kit 0.13.0): the kit has no text
	// domain and cannot translate "increase"/"decrease"/"no change" itself.
	let label = __( 'no change', 'mhm-rentiva' );
	if ( direction === 'up' ) {
		label = __( 'increase', 'mhm-rentiva' );
	} else if ( direction === 'down' ) {
		label = __( 'decrease', 'mhm-rentiva' );
	}
	const text =
		delta.format === 'pct'
			? `%${ Math.abs( delta.value ) } ${ __( 'this month', 'mhm-rentiva' ) }`
			: `+${ delta.value } ${ __( 'this month', 'mhm-rentiva' ) }`;

	return { direction, text, label };
}

export default function StatsCards( { metrics, deltas = {}, currency } ) {
	const fmt = ( n ) => fmtAmount( n, 0 );
	const fmtMoney = ( n ) => fmtMon( n, currency );

	const cards = [
		{
			label: __( 'Total Bookings', 'mhm-rentiva' ),
			value: fmt( metrics?.total_bookings ),
			icon: 'calendar-alt',
			delta: toDelta( deltas.bookings ),
			sub: `${ fmt( metrics?.bookings_this_month ) } ${ __( 'this month', 'mhm-rentiva' ) }`,
		},
		{
			label: __( 'Total Revenue', 'mhm-rentiva' ),
			value: fmtMoney( metrics?.total_revenue ),
			icon: 'money-alt',
			delta: toDelta( deltas.revenue ),
			sub: `${ fmtMoney( metrics?.monthly_revenue ) } ${ __( 'this month', 'mhm-rentiva' ) }`,
		},
		{
			label: __( 'Active Vehicles', 'mhm-rentiva' ),
			value: fmt( metrics?.available_vehicles ),
			icon: 'car',
			sub: `${ fmt( metrics?.total_vehicles ) } ${ __( 'total', 'mhm-rentiva' ) }`,
		},
		{
			// The value counts people who booked THIS MONTH, not the customer
			// population -- see the note this comment replaced in git history.
			label: __( 'Renting this month', 'mhm-rentiva' ),
			value: fmt( metrics?.total_customers_this_month ),
			icon: 'groups',
			delta: toDelta( deltas.customers ),
		},
	];

	return <StatsGrid cards={ cards } columns={ 4 } />;
}
