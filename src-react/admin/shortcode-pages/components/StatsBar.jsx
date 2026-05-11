import { __ } from '@wordpress/i18n';

export default function StatsBar( { stats } ) {
	if ( ! stats ) return null;
	return (
		<div className="mhm-stats-grid mhm-sc-stats">
			<div className="mhm-stat-card">
				<span className="dashicons dashicons-shortcode" />
				<div className="mhm-stat-card__body">
					<p className="mhm-stat-card__value">{ stats.total }</p>
					<p className="mhm-stat-card__label">{ __( 'Total', 'mhm-rentiva' ) }</p>
				</div>
			</div>
			<div className="mhm-stat-card">
				<span className="dashicons dashicons-yes-alt" />
				<div className="mhm-stat-card__body">
					<p className="mhm-stat-card__value">{ stats.active }</p>
					<p className="mhm-stat-card__label">{ __( 'Active', 'mhm-rentiva' ) }</p>
				</div>
			</div>
			<div className="mhm-stat-card">
				<span className="dashicons dashicons-warning" />
				<div className="mhm-stat-card__body">
					<p className="mhm-stat-card__value">{ stats.missing }</p>
					<p className="mhm-stat-card__label">{ __( 'Missing', 'mhm-rentiva' ) }</p>
				</div>
			</div>
		</div>
	);
}
