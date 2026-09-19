import { __ } from '@wordpress/i18n';
import StatsGrid from '../../../../vendor/mhm/ui-core/src-react/components/StatsGrid';

export default function StatsBar( { stats } ) {
	if ( ! stats ) {
		return null;
	}

	const cards = [
		{ label: __( 'Total', 'mhm-rentiva' ), value: String( stats.total ?? 0 ), icon: 'admin-page' },
		{ label: __( 'Active', 'mhm-rentiva' ), value: String( stats.active ?? 0 ), icon: 'yes-alt' },
		{ label: __( 'Missing', 'mhm-rentiva' ), value: String( stats.missing ?? 0 ), icon: 'warning' },
	];

	return <StatsGrid cards={ cards } columns={ 3 } />;
}
