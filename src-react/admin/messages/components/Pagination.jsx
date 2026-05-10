import { __ } from '@wordpress/i18n';

export default function Pagination( { page, totalPages, loading, onPageChange } ) {
	if ( totalPages <= 1 ) return null;

	return (
		<div className="mhm-pagination">
			<button
				className="mhm-pagination__btn button"
				disabled={ page <= 1 || loading }
				onClick={ () => onPageChange( page - 1 ) }
			>
				{ __( '← Prev', 'mhm-rentiva' ) }
			</button>
			<span className="mhm-pagination__info">
				{ page } / { totalPages }
			</span>
			<button
				className="mhm-pagination__btn button"
				disabled={ page >= totalPages || loading }
				onClick={ () => onPageChange( page + 1 ) }
			>
				{ __( 'Next →', 'mhm-rentiva' ) }
			</button>
		</div>
	);
}
