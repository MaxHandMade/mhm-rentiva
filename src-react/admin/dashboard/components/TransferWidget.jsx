import { useState, useCallback } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useApi } from '../../../shared/hooks/useApi';
import { rentivaApi } from '../../../shared/api/rentiva';
import { fmtAmount, fmtMoney } from '../../../shared/format';

export default function TransferWidget( { initial, stats, currency, adminUrl } ) {
	const [ page, setPage ] = useState( 1 );

	const fetchPage = useCallback(
		() => page === 1
			? Promise.resolve( initial )
			: rentivaApi.dashboard.getRecentTransfers( page ),
		[ page ] // eslint-disable-line react-hooks/exhaustive-deps
	);

	const { data, loading, error } = useApi( fetchPage, initial, [ page ] );

	const items      = data?.items ?? [];
	const totalPages = data?.total_pages ?? 1;

	const fmt = ( n ) => fmtAmount( n, 0 );

	return (
		<div className="mhm-widget mhm-transfer-widget">
			<h3>{ __( 'Transfer Summary', 'mhm-rentiva' ) }</h3>

			{ /* Mini KPI row */ }
			<div className="mhm-kpi-row">
				<div className="mhm-kpi-box mhm-kpi-box--blue">
					<div className="mhm-kpi-box__value">{ fmt( stats?.total ) }</div>
					<div className="mhm-kpi-box__label">{ __( 'Total', 'mhm-rentiva' ) }</div>
				</div>
				<div className="mhm-kpi-box mhm-kpi-box--green">
					<div className="mhm-kpi-box__value">{ fmt( stats?.this_month ) }</div>
					<div className="mhm-kpi-box__label">{ __( 'This Month', 'mhm-rentiva' ) }</div>
				</div>
				<div className="mhm-kpi-box mhm-kpi-box--amber">
					<div className="mhm-kpi-box__value">{ fmtMoney( stats?.revenue_this_month, currency, 0 ) }</div>
					<div className="mhm-kpi-box__label">{ __( 'Revenue', 'mhm-rentiva' ) }</div>
				</div>
			</div>

			{ loading && <Spinner /> }
			{ error   && <p className="mhm-error">{ __( 'Failed to load.', 'mhm-rentiva' ) }</p> }

			{ ! loading && ! error && ! items.length && (
				<p className="mhm-empty">{ __( 'No transfers yet.', 'mhm-rentiva' ) }</p>
			) }

			{ ! loading && ! error && (
				<div className="mhm-card-list">
					{ items.map( ( t ) => (
						<div key={ t.id } className="mhm-card-list__item">
							<div className="mhm-card-list__top">
								<a
									className="mhm-card-list__id"
									href={ `${ adminUrl }post.php?post=${ t.id }&action=edit` }
								>{ t.display_id }</a>
								<span className="mhm-card-list__name">
									{ [ t.route_from, t.route_to ].filter( Boolean ).join( ' → ' ) || '—' }
								</span>
								<span className={ `mhm-status mhm-status--${ t.status }` }>
									{ t.status_label }
								</span>
							</div>
							<div className="mhm-card-list__sub">
								{ [ t.vehicle_title, t.vehicle_plate, t.datetime ]
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
