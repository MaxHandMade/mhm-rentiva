import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export default function AdvancedFilters( {
	dateRanges,
	dateRange,
	dateFrom,
	dateTo,
	onRangeChange,
	onPreview,
	previewLoading,
} ) {
	const [ open, setOpen ] = useState( false );

	const handleRangeSelect = ( e ) => {
		const selected = e.target.value;
		const found    = Array.isArray( dateRanges )
			? dateRanges.find( ( r ) => r.value === selected )
			: null;

		if ( found ) {
			onRangeChange( selected, found.date_from ?? '', found.date_to ?? '' );
		} else {
			onRangeChange( '', '', '' );
		}
	};

	const handleFromChange = ( e ) => {
		onRangeChange( 'custom', e.target.value, dateTo );
	};

	const handleToChange = ( e ) => {
		onRangeChange( 'custom', dateFrom, e.target.value );
	};

	return (
		<div className="mhm-export-filters">
			<button
				type="button"
				className="mhm-export-filters__toggle"
				onClick={ () => setOpen( ( v ) => ! v ) }
				aria-expanded={ open }
			>
				<span className="dashicons dashicons-filter" aria-hidden="true" />
				{ __( 'Date Filters', 'mhm-rentiva' ) }
				<span className={ `dashicons dashicons-arrow-${ open ? 'up' : 'down' }-alt2 mhm-export-filters__chevron` } aria-hidden="true" />
			</button>

			{ open && (
				<div className="mhm-export-filters__body">
					{ Array.isArray( dateRanges ) && dateRanges.length > 0 && (
						<div className="mhm-export-filters__row">
							<label className="mhm-export-filters__label" htmlFor="mhm-export-range">
								{ __( 'Preset range', 'mhm-rentiva' ) }
							</label>
							<select
								id="mhm-export-range"
								className="mhm-export-filters__select"
								value={ dateRange }
								onChange={ handleRangeSelect }
							>
								<option value="">{ __( '— All time —', 'mhm-rentiva' ) }</option>
								{ dateRanges.map( ( r ) => (
									<option key={ r.value } value={ r.value }>
										{ r.label }
									</option>
								) ) }
							</select>
						</div>
					) }

					<div className="mhm-export-filters__row mhm-export-filters__row--dates">
						<div className="mhm-export-filters__field">
							<label className="mhm-export-filters__label" htmlFor="mhm-export-from">
								{ __( 'From', 'mhm-rentiva' ) }
							</label>
							<input
								id="mhm-export-from"
								type="date"
								className="mhm-export-filters__input"
								value={ dateFrom }
								onChange={ handleFromChange }
							/>
						</div>
						<div className="mhm-export-filters__field">
							<label className="mhm-export-filters__label" htmlFor="mhm-export-to">
								{ __( 'To', 'mhm-rentiva' ) }
							</label>
							<input
								id="mhm-export-to"
								type="date"
								className="mhm-export-filters__input"
								value={ dateTo }
								onChange={ handleToChange }
							/>
						</div>
					</div>

					<div className="mhm-export-filters__actions">
						<button
							type="button"
							className="button button-secondary"
							onClick={ onPreview }
							disabled={ previewLoading }
						>
							{ previewLoading
								? __( 'Loading…', 'mhm-rentiva' )
								: __( 'Preview count', 'mhm-rentiva' )
							}
						</button>
					</div>
				</div>
			) }
		</div>
	);
}
