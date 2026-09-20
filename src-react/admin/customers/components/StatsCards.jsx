import { __, sprintf } from '@wordpress/i18n';
import StatsGrid from '../../../../vendor/mhm/ui-core/src-react/components/StatsGrid';
import { fmtMoney } from '../../../shared/format';

export default function StatsCards( { stats, currency } ) {
	if ( ! stats ) {
		return null;
	}

	// A raw signed percentage (float), not a formatted string -- see
	// CustomersOptimizer::calculate_trend(). Formatting the "%" sign here,
	// through __(), is what lets a translation move it: a string built in
	// PHP with '%' baked into it (the old shape) could never be reached by
	// the .po catalog.
	const trendValue = Number( stats.new_trend ?? 0 );
	const rising = trendValue > 0;
	const falling = trendValue < 0;
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
	// screen). A true zero (trendValue === 0) stays `flat` here exactly as
	// the old '+0%' special case did.
	let direction = 'flat';
	if ( rising ) {
		direction = 'up';
	} else if ( falling ) {
		direction = 'down';
	}
	const trendMagnitude = sprintf(
		/* translators: %s: percentage change against last month (magnitude only; direction is carried by the kit's arrow). */
		__( '%s%%', 'mhm-rentiva' ),
		Math.abs( trendValue )
	);
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

	return (
		<div className="mhm-kpi-strip">
			<StatsGrid cards={ cards } columns={ 4 } />
		</div>
	);
}
