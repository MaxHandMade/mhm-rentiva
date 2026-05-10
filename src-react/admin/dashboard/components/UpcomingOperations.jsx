import { useState, useCallback } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useApi } from '../../../shared/hooks/useApi';
import { rentivaApi } from '../../../shared/api/rentiva';

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

			{ ! loading && items.map( ( op ) => (
				<div key={ op.id } className="mhm-ops-row">
					<span className={ `dashicons ${ op.type === 'transfer' ? 'dashicons-airplane' : 'dashicons-car' }` } />
					<span className="mhm-ops-row__id">{ op.display_id }</span>
					<span className="mhm-ops-row__date">
						{ op.start_date }{ op.start_time ? ` ${ op.start_time }` : '' }
					</span>
					<span className={ `mhm-status mhm-status--${ op.status }` }>{ op.status_label }</span>
				</div>
			) ) }

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
