import { useState, useCallback } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useApi } from '../../../shared/hooks/useApi';
import { rentivaApi } from '../../../shared/api/rentiva';

const formatShortDate = ( dateStr ) => {
	if ( ! dateStr ) return '';
	const d = new Date( dateStr );
	if ( isNaN( d.getTime() ) ) return dateStr;
	return d.toLocaleDateString( undefined, { day: '2-digit', month: 'short' } );
};

export default function UpcomingOperations( { initial } ) {
	const [ page, setPage ] = useState( 1 );

	const fetchPage = useCallback(
		() => page === 1 ? Promise.resolve( initial ) : rentivaApi.dashboard.getUpcoming( page ),
		[ page ] // eslint-disable-line react-hooks/exhaustive-deps
	);

	const { data, loading, error } = useApi( fetchPage, initial, [ page ] );

	const items      = data?.items ?? [];
	const totalPages = data?.total_pages ?? 1;

	return (
		<div className="mhm-widget mhm-upcoming-ops">
			<h3><span className="dashicons dashicons-clock" />{ __( 'Upcoming Operations', 'mhm-rentiva' ) }</h3>

			{ loading && <Spinner /> }
			{ error   && <p className="mhm-error">{ __( 'Failed to load.', 'mhm-rentiva' ) }</p> }

			{ ! loading && ! error && ! items.length && (
				<p className="mhm-empty">{ __( 'No upcoming operations in the next 7 days.', 'mhm-rentiva' ) }</p>
			) }

			{ ! loading && items.map( ( op ) => {
				const isTransfer = op.type === 'transfer';
				const primary    = isTransfer
					? `${ op.origin || '' } → ${ op.destination || '' }`.trim()
					: ( op.vehicle_title || '' ) + ( op.vehicle_plate ? ` · ${ op.vehicle_plate }` : '' );
				const startFmt = formatShortDate( op.start_date ) + ( op.start_time ? ` ${ op.start_time }` : '' );
				const endFmt   = ! isTransfer ? formatShortDate( op.end_date ) : '';

				return (
					<div key={ op.id } className="mhm-upcoming-ops__item">
						<div className="mhm-upcoming-ops__header">
							<span className="mhm-upcoming-ops__icon">
								<span className={ `dashicons ${ isTransfer ? 'dashicons-airplane' : 'dashicons-car' }` } />
							</span>
							<span className="mhm-upcoming-ops__id">#{ op.display_id }</span>
							{ op.status_label && (
								<span className={ `mhm-upcoming-ops__status mhm-upcoming-ops__status--${ op.status }` }>{ op.status_label }</span>
							) }
						</div>
						<div className="mhm-upcoming-ops__body">
							<div className="mhm-upcoming-ops__primary">{ primary }</div>
							<div className="mhm-upcoming-ops__dates">
								{ startFmt }
								{ endFmt && <> → { endFmt }</> }
							</div>
							{ op.customer_name && (
								<div className="mhm-upcoming-ops__customer">{ op.customer_name }</div>
							) }
						</div>
					</div>
				);
			} ) }

			{ totalPages > 1 && (
				<div className="mhm-ops-pagination">
					<button
						disabled={ page <= 1 || loading }
						onClick={ () => setPage( ( p ) => p - 1 ) }
					>
						{ __( '← Prev', 'mhm-rentiva' ) }
					</button>
					<span>{ page } / { totalPages }</span>
					<button
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
