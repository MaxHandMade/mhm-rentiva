import { __ } from '@wordpress/i18n';

export default function StatsBar( { stats } ) {
	if ( ! stats ) return null;
	return (
		<div className="mhm-sc-stats">
			<div className="mhm-sc-stat-card">
				<span className="mhm-sc-stat-value">{ stats.total }</span>
				<span className="mhm-sc-stat-label">{ __( 'Total', 'mhm-rentiva' ) }</span>
			</div>
			<div className="mhm-sc-stat-card mhm-sc-stat-card--active">
				<span className="mhm-sc-stat-value">{ stats.active }</span>
				<span className="mhm-sc-stat-label">{ __( 'Active', 'mhm-rentiva' ) }</span>
			</div>
			<div className="mhm-sc-stat-card mhm-sc-stat-card--missing">
				<span className="mhm-sc-stat-value">{ stats.missing }</span>
				<span className="mhm-sc-stat-label">{ __( 'Missing', 'mhm-rentiva' ) }</span>
			</div>
		</div>
	);
}
