import { __ } from '@wordpress/i18n';

export default function ConfirmModal( { modal, onCancel } ) {
	if ( ! modal ) {
		return null;
	}
	return (
		// The overlay is a click-away surface, not a control: role="presentation"
		// is the a11y-correct way to say so. The inner box is the actual dialog.
		<div
			className="mhm-modal-overlay"
			role="presentation"
			onClick={ ( e ) => {
				// Close only on the backdrop itself. Comparing target with
				// currentTarget removes the need to swallow clicks inside the
				// dialog, which is what made the dialog look interactive.
				if ( e.target === e.currentTarget ) {
					onCancel();
				}
			} }
		>
			<div
				className="mhm-modal"
				role="dialog"
				aria-modal="true"
				aria-label={ modal.title }
			>
				<h3>{ modal.title }</h3>
				<p>{ modal.message }</p>
				<div className="mhm-modal-actions">
					<button className="button button-secondary" onClick={ onCancel }>
						{ __( 'Cancel', 'mhm-rentiva' ) }
					</button>
					<button
						className={ modal.destructive ? 'button mhm-btn-danger' : 'button button-primary' }
						onClick={ modal.onConfirm }
					>
						{ modal.confirmLabel }
					</button>
				</div>
			</div>
		</div>
	);
}
