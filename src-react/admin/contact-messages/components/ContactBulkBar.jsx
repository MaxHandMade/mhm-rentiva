import { __, sprintf, _n } from '@wordpress/i18n';
import ConfirmButton from '../../../../vendor/mhm/ui-core/src-react/components/ConfirmButton';
import { isTrashEnabled } from '../trash';

export default function ContactBulkBar( { count, inTrash, busy, onAction, onClear } ) {
	// Read inside the component (house pattern, ContactMessagesList.jsx:18): a
	// module-level read runs before a test can set the global.
	const trashEnabled = isTrashEnabled();

	return (
		<div role="region" aria-label={ __( 'Bulk actions', 'mhm-rentiva' ) } className="mhm-contact-messages__bulk">
			<strong>
				{ sprintf(
					/* translators: %d: number of selected messages. */
					_n( '%d message selected', '%d messages selected', count, 'mhm-rentiva' ),
					count
				) }
			</strong>
			{ inTrash ? (
				<>
					<button type="button" className="button" disabled={ busy } onClick={ () => onAction( 'restore' ) }>{ __( 'Restore', 'mhm-rentiva' ) }</button>
					<ConfirmButton
						label={ __( 'Delete permanently', 'mhm-rentiva' ) }
						confirmText={ sprintf(
							/* translators: %d: number of selected messages. */
							_n( 'Delete %d message permanently? This cannot be undone.', 'Delete %d messages permanently? This cannot be undone.', count, 'mhm-rentiva' ),
							count
						) }
						confirmLabel={ __( 'Yes, delete', 'mhm-rentiva' ) }
						cancelLabel={ __( 'Cancel', 'mhm-rentiva' ) }
						busyText={ __( 'Deleting…', 'mhm-rentiva' ) }
						variant="danger"
						disabled={ busy }
						onConfirm={ () => onAction( 'delete' ) }
					/>
				</>
			) : (
				<>
					<button type="button" className="button" disabled={ busy } onClick={ () => onAction( 'read' ) }>{ __( 'Mark as read', 'mhm-rentiva' ) }</button>
					<button type="button" className="button" disabled={ busy } onClick={ () => onAction( 'replied' ) }>{ __( 'Mark as replied', 'mhm-rentiva' ) }</button>
					{ trashEnabled ? (
						<button type="button" className="button mhm-button-danger" disabled={ busy } onClick={ () => onAction( 'trash' ) }>{ __( 'Move to trash', 'mhm-rentiva' ) }</button>
					) : (
						// The site empties nothing: wp_trash_post() deletes the record
						// outright when EMPTY_TRASH_DAYS is falsy, so offering "Move to
						// trash" here would promise an undo that never happens (X3).
						<ConfirmButton
							label={ __( 'Delete permanently', 'mhm-rentiva' ) }
							confirmText={ sprintf(
								/* translators: %d: number of selected messages. */
								_n( 'Delete %d message permanently? This cannot be undone.', 'Delete %d messages permanently? This cannot be undone.', count, 'mhm-rentiva' ),
								count
							) }
							confirmLabel={ __( 'Yes, delete', 'mhm-rentiva' ) }
							cancelLabel={ __( 'Cancel', 'mhm-rentiva' ) }
							busyText={ __( 'Deleting…', 'mhm-rentiva' ) }
							variant="danger"
							disabled={ busy }
							onConfirm={ () => onAction( 'trash' ) }
						/>
					) }
				</>
			) }
			<button type="button" className="button-link mhm-contact-messages__clear" onClick={ onClear }>{ __( 'Clear selection', 'mhm-rentiva' ) }</button>
		</div>
	);
}
