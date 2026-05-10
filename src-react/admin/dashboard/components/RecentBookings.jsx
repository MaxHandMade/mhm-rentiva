import { useState, useCallback } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useApi } from '../../../shared/hooks/useApi';
import { rentivaApi } from '../../../shared/api/rentiva';

const statusLabel = ( status ) => ( {
	pending:     __( 'Pending',     'mhm-rentiva' ),
	confirmed:   __( 'Confirmed',   'mhm-rentiva' ),
	in_progress: __( 'In Progress', 'mhm-rentiva' ),
	completed:   __( 'Completed',   'mhm-rentiva' ),
	cancelled:   __( 'Cancelled',   'mhm-rentiva' ),
}[ status ] ?? status );

export default function RecentBookings( { initial, metrics, adminUrl } ) {
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

	const fmt      = ( n ) => Number( n ?? 0 ).toLocaleString();
	const fmtMoney = ( n ) => `${ Number( n ?? 0 ).toFixed( 0 ) }`;

	return (
		<div className="mhm-widget mhm-recent-bookings">
			<h3>{ __( 'Recent Bookings', 'mhm-rentiva' ) }</h3>

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
					<div className="mhm-kpi-box__value">{ fmtMoney( metrics?.total_revenue ) }</div>
					<div className="mhm-kpi-box__label">{ __( 'Revenue', 'mhm-rentiva' ) }</div>
				</div>
			</div>

			{ loading && <Spinner /> }
			{ error   && <p className="mhm-error">{ __( 'Failed to load.', 'mhm-rentiva' ) }</p> }

			{ ! loading && ! error && ! items.length && (
				<p className="mhm-empty">{ __( 'No bookings yet.', 'mhm-rentiva' ) }</p>
			) }

			{ ! loading && ! error && (
				<div className="mhm-card-list">
					{ items.map( ( b ) => (
						<div key={ b.id } className="mhm-card-list__item">
							<div className="mhm-card-list__top">
								<a
									className="mhm-card-list__id"
									href={ `${ adminUrl }post.php?post=${ b.id }&action=edit` }
								>
									#{ b.display_id ?? b.id }
								</a>
								<span className="mhm-card-list__name">{ b.customer_name || '—' }</span>
								<span className={ `mhm-status mhm-status--${ b.status }` }>
									{ b.status_label ?? statusLabel( b.status ) }
								</span>
							</div>
							<div className="mhm-card-list__sub">
								{ [ b.vehicle_title, b.vehicle_plate, b.pickup_date ]
									.filter( Boolean )
									.join( ' · ' ) }
							</div>
						</div>
					) ) }
				</div>
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
