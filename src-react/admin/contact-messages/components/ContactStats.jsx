import { __ } from '@wordpress/i18n';
import StatsGrid from '../../../../vendor/mhm/ui-core/src-react/components/StatsGrid';

export default function ContactStats( { stats } ) {
	return (
		<StatsGrid
			cards={ [
				{ label: __( 'New', 'mhm-rentiva' ), value: stats.new, icon: 'messages', tone: stats.new > 0 ? 'warning' : undefined },
				{ label: __( 'Awaiting reply', 'mhm-rentiva' ), value: stats.awaiting, icon: 'pending' },
				{ label: __( 'Last 7 days', 'mhm-rentiva' ), value: stats.last_7_days, icon: 'time' },
				{ label: __( 'Total', 'mhm-rentiva' ), value: stats.total, icon: 'total' },
			] }
		/>
	);
}
