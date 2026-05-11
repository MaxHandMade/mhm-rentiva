import { useState, useEffect, useCallback } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { rentivaApi } from '../../../shared/api/rentiva';
import ReplyForm from './ReplyForm';

const { statuses } = window.mhmRentivaMessages;

export default function ThreadView( { messageId, onBack } ) {
	const [ thread,  setThread ]  = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error,   setError ]   = useState( null );
	const [ status,  setStatus ]  = useState( '' );
	const [ saving,  setSaving ]  = useState( false );

	const fetchThread = useCallback( async () => {
		setLoading( true );
		setError( null );
		try {
			const data = await rentivaApi.messages.getThread( messageId );
			const normalized = {
				subject:        data.message.post_title,
				customer_name:  data.meta.customer_name,
				customer_email: data.meta.customer_email,
				status:         data.meta.status,
				can_reply:      true,
				thread_messages: ( data.thread ?? [] ).map( ( msg ) => ( {
					id:            msg.ID,
					content:       msg.post_content,
					message_type:  msg.meta?.message_type ?? 'customer_to_admin',
					customer_name: msg.meta?.customer_name ?? data.meta.customer_name,
					admin_name:    msg.meta?.admin_name ?? '',
					date_full:     msg.post_date,
				} ) ),
			};
			setThread( normalized );
			setStatus( data.meta.status );
		} catch {
			setError( __( 'Failed to load thread.', 'mhm-rentiva' ) );
		} finally {
			setLoading( false );
		}
	}, [ messageId ] );

	useEffect( () => { fetchThread(); }, [ fetchThread ] );

	const handleStatusChange = async ( newStatus ) => {
		setSaving( true );
		try {
			await rentivaApi.messages.updateStatus( messageId, newStatus );
			setStatus( newStatus );
			fetchThread();
		} finally {
			setSaving( false );
		}
	};

	return (
		<div className="mhm-thread-view mhm-widget">
			<div className="mhm-thread-view__header">
				<button className="button" onClick={ onBack }>
					{ __( '← Back to Messages', 'mhm-rentiva' ) }
				</button>

				{ thread && (
					<>
						<span className="mhm-thread-view__title">
							{ thread.subject }
						</span>
						<span className="mhm-thread-view__meta">
							{ thread.customer_name } ({ thread.customer_email })
						</span>
						<select
							value={ status }
							disabled={ saving }
							onChange={ ( e ) => handleStatusChange( e.target.value ) }
						>
							{ Object.entries( statuses ).map( ( [ k, v ] ) => (
								<option key={ k } value={ k }>{ v }</option>
							) ) }
						</select>
					</>
				) }
			</div>

			{ loading && <Spinner /> }
			{ error   && <p className="mhm-error">{ error }</p> }

			{ ! loading && ! error && thread && (
				<>
					<div className="mhm-thread-messages">
						{ ( thread.thread_messages ?? [] ).map( ( msg ) => (
							<div
								key={ msg.id }
								className={ `mhm-thread-message mhm-thread-message--${
									msg.message_type === 'admin_to_customer' ? 'admin' : 'customer'
								}` }
							>
								<div>{ msg.content }</div>
								<div className="mhm-thread-message__meta">
									{ msg.message_type === 'admin_to_customer'
										? ( msg.admin_name || __( 'Admin', 'mhm-rentiva' ) )
										: msg.customer_name }
									{ ' · ' }{ msg.date_full }
								</div>
							</div>
						) ) }
					</div>

					{ thread.can_reply && (
						<ReplyForm messageId={ messageId } onReplySent={ fetchThread } />
					) }
				</>
			) }
		</div>
	);
}
