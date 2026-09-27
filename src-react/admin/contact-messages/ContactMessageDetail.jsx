import { __ } from '@wordpress/i18n';

/**
 * Placeholder for Task 8, which replaces this with the real detail view
 * (message body, technical metadata, status actions). Kept minimal so
 * ContactMessagesApp's route to an id has somewhere to render and the bundle
 * builds; Task 8 owns the real implementation and its own tests.
 *
 * @param {Object}   props
 * @param {Function} props.onBack
 */
export default function ContactMessageDetail( { onBack } ) {
	return (
		<div className="mhm-contact-message-detail">
			<button type="button" className="button" onClick={ onBack }>
				{ __( '← Back', 'mhm-rentiva' ) }
			</button>
		</div>
	);
}
