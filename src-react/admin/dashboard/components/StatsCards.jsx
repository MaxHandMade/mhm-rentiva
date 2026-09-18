import { __, _x, sprintf } from '@wordpress/i18n';
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
 * `thisMonth` is the card's this-month figure, ALREADY FORMATTED by the
 * card's own formatter (a count, or a money amount for the revenue card).
 * The kit renders a card's delta line OR its sub line, never both, so a card
 * that used to carry that figure in `sub` lost it whenever a delta rendered
 * (since ui-core 0.12.0). Passing it here folds it into the delta text
 * (product decision 2026-09-18): "5% · 3 this month", Turkish "%5 · bu ay 3".
 * It is ONE sprintf pattern with numbered placeholders so a translator can
 * reorder the magnitude and the figure, and move the percent sign -- Turkish
 * puts it before the number. A flat trend (0%) takes the same pattern. The
 * `abs` format (last month had nothing, this month has something) has no
 * magnitude: its value IS the this-month figure, so the text is that figure,
 * formatted like the card. Omitted (the customers card, whose value already
 * is this month's count), the text is the magnitude alone through its own
 * translatable pattern, or for `abs` the count formatted like a count card.
 *
 * @param {Object} delta     { direction, format, value } from the REST payload.
 * @param {string} thisMonth Optional; the formatted this-month figure to fold in.
 * @return {Object|undefined} { direction, text, label } for StatCard, or undefined.
 */
function toDelta( delta, thisMonth ) {
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
	let text;
	if ( thisMonth === undefined && delta.format === 'pct' ) {
		// No figure to fold (the customers card: its value already IS this
		// month's count). The percent sign goes through the catalogue, never
		// concatenated -- a hard-coded `%${ n }` showed English users the
		// Turkish order ("%5 this month").
		text = sprintf(
			/* translators: %s: percentage change against last month, number only (the direction arrow is drawn separately). */
			__( '%s%% this month', 'mhm-rentiva' ),
			Math.abs( delta.value )
		);
	} else if ( thisMonth === undefined ) {
		// abs without a figure: the value is this month's count itself, formatted
		// like a count card. No "+": the kit's arrow already says "up".
		text = sprintf(
			/* translators: %s: this month's figure, already formatted (a count or a money amount); last month had none, so there is no percentage to show. */
			_x( '%s this month', 'dashboard KPI delta line', 'mhm-rentiva' ),
			fmtAmount( delta.value, 0 )
		);
	} else if ( delta.format === 'pct' ) {
		text = sprintf(
			/* translators: 1: percentage change against last month, number only (the direction arrow is drawn separately); 2: this month's figure, already formatted (a count or a money amount). */
			__( '%1$s%% · %2$s this month', 'mhm-rentiva' ),
			Math.abs( delta.value ),
			thisMonth
		);
	} else {
		text = sprintf(
			/* translators: %s: this month's figure, already formatted (a count or a money amount); last month had none, so there is no percentage to show. */
			_x( '%s this month', 'dashboard KPI delta line', 'mhm-rentiva' ),
			thisMonth
		);
	}

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
			delta: toDelta( deltas.bookings, fmt( metrics?.bookings_this_month ) ),
			sub: `${ fmt( metrics?.bookings_this_month ) } ${ __( 'this month', 'mhm-rentiva' ) }`,
		},
		{
			label: __( 'Total Revenue', 'mhm-rentiva' ),
			value: fmtMoney( metrics?.total_revenue ),
			icon: 'money-alt',
			delta: toDelta( deltas.revenue, fmtMoney( metrics?.monthly_revenue ) ),
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
