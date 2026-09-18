import { __ } from '@wordpress/i18n';
import StatsGrid from '../../../../vendor/mhm/ui-core/src-react/components/StatsGrid';
import { fmtMoney } from '../../../shared/format';

export default function StatsCards( { stats, currency } ) {
	if ( ! stats ) {
		return null;
	}

	const trend = String( stats.new_trend ?? '' );
	const rising = trend.startsWith( '+' ) && trend !== '+0%';
	const falling = trend.startsWith( '-' );
	// Since ui-core 0.12.0 the kit renders the up/down mark itself, so the
	// delta text must drop the sign that used to be this screen's own mark --
	// otherwise the sign and the kit's arrow duplicate the same cue. The
	// magnitude alone (e.g. "5%") is still meaningful next to the coloured
	// arrow.
	// Since ui-core 0.13.0 `flat` gets its own delta line (the kit's
	// DIRECTION_MARKS now covers it) instead of silently falling through to
	// a sub line and losing the number -- a flat trend now prints "→ 0%"
	// like the other stat cards, rather than hiding it (product decision:
	// printing the zero consistently everywhere beats hiding it on this one
	// screen).
	let direction = 'flat';
	if ( rising ) {
		direction = 'up';
	} else if ( falling ) {
		direction = 'down';
	}
	const trendMagnitude = trend.replace( /^[+-]/, '' ) || '0%';
	// Accessible name for the delta line (kit 0.13.0): the kit has no text
	// domain and cannot translate "increase"/"decrease"/"no change" itself.
	let trendLabel = __( 'no change', 'mhm-rentiva' );
	if ( direction === 'up' ) {
		trendLabel = __( 'increase', 'mhm-rentiva' );
	} else if ( direction === 'down' ) {
		trendLabel = __( 'decrease', 'mhm-rentiva' );
	}
	const avgSpend = Number( stats.avg_spend ?? 0 );

	const cards = [
		{
			label: __( 'Total Customers', 'mhm-rentiva' ),
			value: String( stats.total ?? 0 ),
			icon: 'groups',
		},
		{
			label: __( 'New This Month', 'mhm-rentiva' ),
			value: String( stats.new_this_month ?? 0 ),
			icon: 'plus-alt',
			delta: { direction, text: trendMagnitude, label: trendLabel },
		},
		{
			label: __( 'Active Customers', 'mhm-rentiva' ),
			value: String( stats.active_90d ?? 0 ),
			icon: 'yes-alt',
			sub: __( 'last 90 days', 'mhm-rentiva' ),
		},
		{
			label: __( 'Avg. Spend', 'mhm-rentiva' ),
			value: fmtMoney( avgSpend, currency ?? '' ),
			icon: 'money-alt',
			sub: __( 'per customer', 'mhm-rentiva' ),
		},
	];

	return <StatsGrid cards={ cards } columns={ 4 } />;
}
