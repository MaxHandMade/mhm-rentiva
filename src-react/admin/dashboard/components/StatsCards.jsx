import { __ } from '@wordpress/i18n';
import StatsGrid from '../../../../vendor/mhm/ui-core/src-react/components/StatsGrid';
import { fmtAmount, fmtMoney as fmtMon } from '../../../shared/format';

/**
 * The kit takes a formatted delta line; the arrow and the wording stay here,
 * because they are this product's copy, not the kit's.
 *
 * @param {Object} delta { direction, format, value } from the REST payload.
 * @return {Object|undefined} { direction, text } for StatCard, or undefined.
 */
function toDelta( delta ) {
	if ( ! delta || delta.format === 'neutral' ) {
		return undefined;
	}
	const arrows = { up: '↑', down: '↓' };
	const arrow = arrows[ delta.direction ] ?? '';
	const text =
		delta.format === 'pct'
			? `${ arrow } %${ Math.abs( delta.value ) } ${ __( 'this month', 'mhm-rentiva' ) }`
			: `+${ delta.value } ${ __( 'this month', 'mhm-rentiva' ) }`;

	return { direction: delta.direction, text };
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
