import { useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/** Display labels for the formats Export::allowed_formats() can return. */
const FORMAT_LABELS = {
	csv:  'CSV',
	json: 'JSON',
};

export default function ExportForm( {
	adminUrl,
	exportNonce,
	activeType,
	dateFrom,
	dateTo,
	formats = [ 'csv' ],
	format = 'csv',
	onFormatChange,
	disabled,
} ) {
	const formRef = useRef( null );

	const handleExport = () => {
		if ( formRef.current ) {
			formRef.current.submit();
		}
	};

	// Only worth a control when there is a choice to make. With a single format
	// the screen stays exactly as it was — one button, no extra chrome.
	const showPicker = formats.length > 1;
	const label      = FORMAT_LABELS[ format ] ?? format.toUpperCase();

	return (
		<div className="mhm-export-form">
			{ showPicker && (
				<div
					className="mhm-export-form__formats"
					role="group"
					aria-label={ __( 'Export format', 'mhm-rentiva' ) }
				>
					<span className="mhm-export-form__formats-label">
						{ __( 'Format', 'mhm-rentiva' ) }
					</span>
					{ formats.map( ( value ) => (
						<label key={ value } className="mhm-export-form__format">
							<input
								type="radio"
								name="mhm-export-format"
								value={ value }
								checked={ format === value }
								onChange={ () => onFormatChange?.( value ) }
							/>
							{ FORMAT_LABELS[ value ] ?? value.toUpperCase() }
						</label>
					) ) }
				</div>
			) }

			<button
				type="button"
				className="button button-primary mhm-export-form__btn"
				onClick={ handleExport }
				disabled={ disabled }
			>
				<span className="dashicons dashicons-download" aria-hidden="true" />
				{ sprintf(
					/* translators: %s: export file format, e.g. CSV or JSON. */
					__( 'Export %s', 'mhm-rentiva' ),
					label
				) }
			</button>

			{ disabled && (
				<p className="mhm-export-form__notice">
					{ __( 'No records to export with the current filters.', 'mhm-rentiva' ) }
				</p>
			) }

			<form
				ref={ formRef }
				method="POST"
				action={ `${ adminUrl }admin-post.php` }
				style={ { display: 'none' } }
			>
				<input type="hidden" name="action"       value="mhm_rentiva_export" />
				<input type="hidden" name="nonce"        value={ exportNonce } />
				<input type="hidden" name="post_type"    value={ activeType } />
				<input type="hidden" name="format"       value={ format } />
				{ dateFrom && <input type="hidden" name="date_from" value={ dateFrom } /> }
				{ dateTo   && <input type="hidden" name="date_to"   value={ dateTo } /> }
			</form>
		</div>
	);
}
