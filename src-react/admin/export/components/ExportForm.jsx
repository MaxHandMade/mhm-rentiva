import { useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export default function ExportForm( { adminUrl, exportNonce, activeType, dateFrom, dateTo, disabled } ) {
	const formRef = useRef( null );

	const handleExport = () => {
		if ( formRef.current ) {
			formRef.current.submit();
		}
	};

	return (
		<div className="mhm-export-form">
			<button
				type="button"
				className="button button-primary mhm-export-form__btn"
				onClick={ handleExport }
				disabled={ disabled }
			>
				<span className="dashicons dashicons-download" aria-hidden="true" />
				{ __( 'Export CSV', 'mhm-rentiva' ) }
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
				{ dateFrom && <input type="hidden" name="date_from" value={ dateFrom } /> }
				{ dateTo   && <input type="hidden" name="date_to"   value={ dateTo } /> }
			</form>
		</div>
	);
}
