import { useState, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { rentivaApi } from '../../../shared/api/rentiva';

const { statuses, admin_url, nonces } = window.mhmRentivaMessages;

export default function BulkBar( { selected, onDone } ) {
	const [ action,     setAction ]     = useState( '' );
	const [ processing, setProcessing ] = useState( false );
	const deleteFormRef = useRef( null );

	if ( selected.length === 0 ) return null;

	const handleApply = async () => {
		let targetStatus = null;
		if ( action === 'mark_read' )                     targetStatus = 'answered';
		else if ( action === 'mark_unread' )              targetStatus = 'pending';
		else if ( action.startsWith( 'change_status:' ) ) targetStatus = action.split( ':' )[ 1 ];

		if ( ! targetStatus ) return;

		setProcessing( true );
		try {
			await Promise.all(
				selected.map( ( id ) => rentivaApi.messages.updateStatus( id, targetStatus ) )
			);
			onDone();
		} finally {
			setProcessing( false );
		}
	};

	const handleDelete = () => {
		if ( deleteFormRef.current ) {
			deleteFormRef.current.submit();
		}
	};

	return (
		<div className="mhm-bulk-bar">
			<span className="mhm-bulk-bar__count">
				{ selected.length }{ ' ' }{ __( 'selected', 'mhm-rentiva' ) }
			</span>

			<select value={ action } onChange={ ( e ) => setAction( e.target.value ) }>
				<option value="">{ __( 'Bulk action…', 'mhm-rentiva' ) }</option>
				<option value="mark_read">{ __( 'Mark as read', 'mhm-rentiva' ) }</option>
				<option value="mark_unread">{ __( 'Mark as unread', 'mhm-rentiva' ) }</option>
				{ Object.entries( statuses ).map( ( [ k, v ] ) => (
					<option key={ k } value={ `change_status:${ k }` }>
						{ v }
					</option>
				) ) }
			</select>

			{ action && (
				<button
					className="button button-secondary"
					disabled={ processing }
					onClick={ handleApply }
				>
					{ processing ? __( 'Processing…', 'mhm-rentiva' ) : __( 'Apply', 'mhm-rentiva' ) }
				</button>
			) }

			<button
				className="button button-link-delete"
				disabled={ processing }
				onClick={ handleDelete }
			>
				{ __( 'Delete selected', 'mhm-rentiva' ) }
			</button>

			{ /* Hidden delete form — browser redirect on submit */ }
			<form
				ref={ deleteFormRef }
				method="POST"
				action={ `${ admin_url }admin-post.php` }
				style={ { display: 'none' } }
			>
				<input type="hidden" name="action" value="mhm_rentiva_delete_messages" />
				<input type="hidden" name="nonce"  value={ nonces.delete } />
				{ selected.map( ( id ) => (
					<input key={ id } type="hidden" name="ids[]" value={ id } />
				) ) }
			</form>
		</div>
	);
}
