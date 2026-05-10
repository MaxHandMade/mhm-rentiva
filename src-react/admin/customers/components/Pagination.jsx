import { __ } from '@wordpress/i18n';

export default function Pagination( { page, totalPages, onPageChange } ) {
	if ( totalPages <= 1 ) return null;
	return (
		<div className="mhm-pagination" style={ { display: 'flex', alignItems: 'center', gap: 8, marginTop: 12 } }>
			<button
				className="button"
				disabled={ page <= 1 }
				onClick={ () => onPageChange( page - 1 ) }
			>
				{ __( '← Previous', 'mhm-rentiva' ) }
			</button>
			<span>
				{ `${ __( 'Page', 'mhm-rentiva' ) } ${ page } ${ __( 'of', 'mhm-rentiva' ) } ${ totalPages }` }
			</span>
			<button
				className="button"
				disabled={ page >= totalPages }
				onClick={ () => onPageChange( page + 1 ) }
			>
				{ __( 'Next →', 'mhm-rentiva' ) }
			</button>
		</div>
	);
}
