import { __ } from '@wordpress/i18n';

export default function ConfirmModal( { modal, onCancel } ) {
	if ( ! modal ) return null;
	return (
		<div className="mhm-modal-overlay" onClick={ onCancel }>
			<div className="mhm-modal" onClick={ ( e ) => e.stopPropagation() }>
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
