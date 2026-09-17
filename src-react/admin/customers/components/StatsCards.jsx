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
	// arrow; a flat trend never reaches here, it stays a plain sub line below.
	const trendMagnitude = trend.replace( /^[+-]/, '' );
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
			// Only a real movement earns a delta line; a flat trend reads as a sub.
			delta: rising || falling ? { direction: rising ? 'up' : 'down', text: trendMagnitude } : undefined,
			sub: rising || falling ? undefined : trend,
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
