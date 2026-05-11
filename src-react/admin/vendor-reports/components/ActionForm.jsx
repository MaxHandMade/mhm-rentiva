import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const { nonces, admin_url: adminUrl } = window.mhmRentivaVendorReports || {};

export default function ActionForm( { reportId } ) {
	const [ note, setNote ] = useState( '' );

	return (
		<div className="mhm-vr-action-form">
			<h3>{ __( 'Resolve this report', 'mhm-rentiva' ) }</h3>
			<form method="POST" action={ ( adminUrl || '' ) + 'admin-post.php' }>
				<input type="hidden" name="_mhm_vr_nonce" value={ nonces?.action || '' } />
				<input type="hidden" name="report_id"    value={ reportId } />

				<label htmlFor="mhm-vr-admin-note">
					<strong>{ __( 'Administrator Note', 'mhm-rentiva' ) }</strong>
				</label>
				<textarea
					id="mhm-vr-admin-note"
					name="admin_note"
					rows={ 4 }
					style={ { width: '100%', marginTop: '8px' } }
					placeholder={ __( 'Optional — explain your decision so the vendor knows why.', 'mhm-rentiva' ) }
					value={ note }
					onChange={ ( e ) => setNote( e.target.value ) }
				/>

				<div className="mhm-vr-action-buttons">
					<button type="submit" name="action" value="mhm_vendor_report_resolve" className="button button-primary">
						{ __( 'Mark as Resolved', 'mhm-rentiva' ) }
					</button>
					<button type="submit" name="action" value="mhm_vendor_report_reject" className="button">
						{ __( 'Reject', 'mhm-rentiva' ) }
					</button>
					<button type="submit" name="action" value="mhm_vendor_report_in_review" className="button button-link">
						{ __( 'Mark In Review', 'mhm-rentiva' ) }
					</button>
				</div>
			</form>
		</div>
	);
}
