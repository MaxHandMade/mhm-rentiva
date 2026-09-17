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
			delta: rising || falling ? { direction: rising ? 'up' : 'down', text: trend } : undefined,
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
