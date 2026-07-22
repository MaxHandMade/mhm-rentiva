import { __ } from '@wordpress/i18n';
import { fmtAmount } from '../../../shared/format';

export default function StatusBreakdown( { items = [] } ) {
	return (
		<div className="mhm-widget rv-status-breakdown">
			<h3><span className="dashicons dashicons-chart-pie" />{ __( 'Booking Statuses', 'mhm-rentiva' ) }</h3>
			{ ! items.length && <p className="mhm-empty">{ __( 'No bookings yet.', 'mhm-rentiva' ) }</p> }
			{ !! items.length && (
				<ul className="rv-status-list">
					{ items.map( ( s ) => (
						<li key={ s.status } className="rv-status-list__row">
							<span className="rv-status-list__dot" style={ { background: s.dot } } />
							<span className="rv-status-list__label">{ s.label }</span>
							<span className="rv-status-list__count">{ fmtAmount( s.count, 0 ) }</span>
						</li>
					) ) }
				</ul>
			) }
		</div>
	);
}
