import { __, sprintf, _n } from '@wordpress/i18n';
import Button from '../../../../vendor/mhm/ui-core/src-react/components/Button';
import ConfirmButton from '../../../../vendor/mhm/ui-core/src-react/components/ConfirmButton';
import { isTrashEnabled } from '../trash';

export default function ContactBulkBar( { count, inTrash, busy, onAction, onClear } ) {
	// Read inside the component (house pattern, ContactMessagesList.jsx:18): a
	// module-level read runs before a test can set the global.
	const trashEnabled = isTrashEnabled();

	const permanentDelete = ( action ) => (
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
			size="compact"
			disabled={ busy }
			onConfirm={ () => onAction( action ) }
		/>
	);

	return (
		<div role="region" aria-label={ __( 'Bulk actions', 'mhm-rentiva' ) } className="mhm-contact-messages__bulk">
			<strong>
				{ sprintf(
					/* translators: %d: number of selected messages. */
					_n( '%d message selected', '%d messages selected', count, 'mhm-rentiva' ),
					count
				) }
			</strong>
			<span className="mhm-contact-messages__bulk-sep" aria-hidden="true" />
			{ inTrash ? (
				<>
					<Button disabled={ busy } onClick={ () => onAction( 'restore' ) }>{ __( 'Restore', 'mhm-rentiva' ) }</Button>
					{ permanentDelete( 'delete' ) }
				</>
			) : (
				<>
					<Button disabled={ busy } onClick={ () => onAction( 'read' ) }>{ __( 'Mark as read', 'mhm-rentiva' ) }</Button>
					<Button disabled={ busy } onClick={ () => onAction( 'replied' ) }>{ __( 'Mark as replied', 'mhm-rentiva' ) }</Button>
					{ trashEnabled ? (
						<Button variant="danger" disabled={ busy } onClick={ () => onAction( 'trash' ) }>{ __( 'Move to trash', 'mhm-rentiva' ) }</Button>
					) : (
						// The site empties nothing: wp_trash_post() deletes the record
						// outright when EMPTY_TRASH_DAYS is falsy, so offering "Move to
						// trash" here would promise an undo that never happens (X3).
						permanentDelete( 'trash' )
					) }
				</>
			) }
			<span className="mhm-contact-messages__clear">
				<Button variant="plain" onClick={ onClear }>{ __( 'Clear selection', 'mhm-rentiva' ) }</Button>
			</span>
		</div>
	);
}
