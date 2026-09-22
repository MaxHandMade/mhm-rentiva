import { __ } from '@wordpress/i18n';
import StatsGrid from '../../../../vendor/mhm/ui-core/src-react/components/StatsGrid';

export default function StatsBar( { stats } ) {
	if ( ! stats ) {
		return null;
	}

	const cards = [
		{ label: __( 'Total', 'mhm-rentiva' ), value: String( stats.total ?? 0 ), icon: 'pages' },
		{ label: __( 'Active', 'mhm-rentiva' ), value: String( stats.active ?? 0 ), icon: 'active' },
		{ label: __( 'Missing', 'mhm-rentiva' ), value: String( stats.missing ?? 0 ), icon: 'missing' },
	];

	return (
		<div className="mhm-kpi-strip">
			<StatsGrid cards={ cards } columns={ 3 } />
		</div>
	);
}
