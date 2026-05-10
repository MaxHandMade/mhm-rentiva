import { useState } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { rentivaApi } from '../../../shared/api/rentiva';

export default function ReplyForm( { messageId, onReplySent } ) {
	const [ body,       setBody ]       = useState( '' );
	const [ submitting, setSubmitting ] = useState( false );
	const [ error,      setError ]      = useState( null );

	const handleSubmit = async ( closeThread ) => {
		if ( ! body.trim() ) return;
		setSubmitting( true );
		setError( null );
		try {
			await rentivaApi.messages.reply( messageId, body, closeThread );
			setBody( '' );
			onReplySent();
		} catch ( err ) {
			setError( __( 'Failed to send reply.', 'mhm-rentiva' ) );
		} finally {
			setSubmitting( false );
		}
	};

	return (
		<div className="mhm-reply-form">
			<textarea
				value={ body }
				onChange={ ( e ) => setBody( e.target.value ) }
				placeholder={ __( 'Write your reply…', 'mhm-rentiva' ) }
				disabled={ submitting }
			/>
			{ error && <p className="mhm-error">{ error }</p> }
			<div className="mhm-reply-form__actions">
				{ submitting && <Spinner /> }
				<button
					className="button button-primary"
					disabled={ submitting || ! body.trim() }
					onClick={ () => handleSubmit( false ) }
				>
					{ __( 'Send (keep open)', 'mhm-rentiva' ) }
				</button>
				<button
					className="button button-secondary"
					disabled={ submitting || ! body.trim() }
					onClick={ () => handleSubmit( true ) }
				>
					{ __( 'Send + Close', 'mhm-rentiva' ) }
				</button>
			</div>
		</div>
	);
}
