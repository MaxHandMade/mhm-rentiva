import { useState, useCallback } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useApi } from '../../../shared/hooks/useApi';
import { rentivaApi } from '../../../shared/api/rentiva';
import { fmtAmount, fmtMoney } from '../../../shared/format';

const statusLabel = ( status ) => ( {
	pending:     __( 'Pending',     'mhm-rentiva' ),
	confirmed:   __( 'Confirmed',   'mhm-rentiva' ),
	in_progress: __( 'In Progress', 'mhm-rentiva' ),
	completed:   __( 'Completed',   'mhm-rentiva' ),
	cancelled:   __( 'Cancelled',   'mhm-rentiva' ),
}[ status ] ?? status );

export default function RecentBookings( { initial, metrics, currency, adminUrl } ) {
	const [ page, setPage ] = useState( 1 );

	const fetchPage = useCallback(
		() => page === 1
			? Promise.resolve( initial )
			: rentivaApi.dashboard.getRecentBookings( page ),
		[ page ] // eslint-disable-line react-hooks/exhaustive-deps
	);

	const { data, loading, error } = useApi( fetchPage, initial, [ page ] );

	const items      = data?.items ?? [];
	const totalPages = data?.total_pages ?? 1;

	const fmt = ( n ) => fmtAmount( n, 0 );

	return (
		<div className="mhm-widget mhm-recent-bookings">
			<h3><span className="dashicons dashicons-calendar-alt" />{ __( 'Recent Bookings', 'mhm-rentiva' ) }</h3>

			{ /* Mini KPI row — values from localize data (same source as StatsCards) */ }
			<div className="mhm-kpi-row">
				<div className="mhm-kpi-box mhm-kpi-box--blue">
					<div className="mhm-kpi-box__value">{ fmt( metrics?.total_bookings ) }</div>
					<div className="mhm-kpi-box__label">{ __( 'Total', 'mhm-rentiva' ) }</div>
				</div>
				<div className="mhm-kpi-box mhm-kpi-box--green">
					<div className="mhm-kpi-box__value">{ fmt( metrics?.bookings_this_month ) }</div>
					<div className="mhm-kpi-box__label">{ __( 'This Month', 'mhm-rentiva' ) }</div>
				</div>
				<div className="mhm-kpi-box mhm-kpi-box--amber">
					<div className="mhm-kpi-box__value">{ fmtMoney( metrics?.total_revenue, currency, 0 ) }</div>
					<div className="mhm-kpi-box__label">{ __( 'Revenue', 'mhm-rentiva' ) }</div>
				</div>
			</div>

			{ loading && <Spinner /> }
			{ error   && <p className="mhm-error">{ __( 'Failed to load.', 'mhm-rentiva' ) }</p> }

			{ ! loading && ! error && ! items.length && (
				<p className="mhm-empty">{ __( 'No bookings yet.', 'mhm-rentiva' ) }</p>
			) }

			{ ! loading && ! error && !! items.length && (
				<table className="widefat fixed striped">
					<thead>
						<tr>
							<th>{ __( 'ID',       'mhm-rentiva' ) }</th>
							<th>{ __( 'Customer', 'mhm-rentiva' ) }</th>
							<th>{ __( 'Vehicle',  'mhm-rentiva' ) }</th>
							<th>{ __( 'Location', 'mhm-rentiva' ) }</th>
							<th>{ __( 'Pickup',   'mhm-rentiva' ) }</th>
							<th>{ __( 'Amount',   'mhm-rentiva' ) }</th>
							<th>{ __( 'Status',   'mhm-rentiva' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ items.map( ( b ) => (
							<tr key={ b.id }>
								<td>
									<a href={ `${ adminUrl }post.php?post=${ b.id }&action=edit` }>
										#{ b.display_id ?? b.id }
									</a>
								</td>
								<td>{ b.customer_name || '—' }</td>
								<td>
									{ [ b.vehicle_title, b.vehicle_plate ].filter( Boolean ).join( ' · ' ) || '—' }
								</td>
								<td>{ b.vehicle_location || '—' }</td>
								<td>{ b.pickup_date || '—' }</td>
								<td>{ b.total_price != null ? fmtMoney( b.total_price, currency, 0 ) : '—' }</td>
								<td>
									<span className={ `mhm-status mhm-status--${ b.status }` }>
										{ b.status_label ?? statusLabel( b.status ) }
									</span>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }

			{ totalPages > 1 && (
				<div className="mhm-pagination">
					<button
						className="mhm-pagination__btn"
						disabled={ page <= 1 || loading }
						onClick={ () => setPage( ( p ) => p - 1 ) }
					>
						{ __( '← Prev', 'mhm-rentiva' ) }
					</button>
					<span className="mhm-pagination__info">{ page } / { totalPages }</span>
					<button
						className="mhm-pagination__btn"
						disabled={ page >= totalPages || loading }
						onClick={ () => setPage( ( p ) => p + 1 ) }
					>
						{ __( 'Next →', 'mhm-rentiva' ) }
					</button>
				</div>
			) }
		</div>
	);
}
