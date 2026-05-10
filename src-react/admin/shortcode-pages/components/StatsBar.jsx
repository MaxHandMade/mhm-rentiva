import { __ } from '@wordpress/i18n';

export default function StatsBar( { stats } ) {
	if ( ! stats ) return null;
	return (
		<div className="mhm-sc-stats">
			<div className="mhm-stat-card">
				<span className="mhm-stat-value">{ stats.total }</span>
				<span className="mhm-stat-label">{ __( 'Total', 'mhm-rentiva' ) }</span>
			</div>
			<div className="mhm-stat-card mhm-stat-card--active">
				<span className="mhm-stat-value">{ stats.active }</span>
				<span className="mhm-stat-label">{ __( 'Active', 'mhm-rentiva' ) }</span>
			</div>
			<div className="mhm-stat-card mhm-stat-card--missing">
				<span className="mhm-stat-value">{ stats.missing }</span>
				<span className="mhm-stat-label">{ __( 'Missing', 'mhm-rentiva' ) }</span>
			</div>
		</div>
	);
}
