import { useState, useEffect } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import PageHeader from '../../../vendor/mhm/ui-core/src-react/components/PageHeader';
import DetailLayout from '../../../vendor/mhm/ui-core/src-react/components/DetailLayout';
import DetailList from '../../../vendor/mhm/ui-core/src-react/components/DetailList';
import Widget from '../../../vendor/mhm/ui-core/src-react/components/Widget';
import ConfirmButton from '../../../vendor/mhm/ui-core/src-react/components/ConfirmButton';
import Notice from '../../../vendor/mhm/ui-core/src-react/components/Notice';
import SenderWidget from './components/SenderWidget';
import TechnicalWidget from './components/TechnicalWidget';
import { contactApi } from './api';
import { buildMailto } from './mailto';

const TONE = { new: 'warning', read: 'neutral', replied: 'success' };

export default function ContactMessageDetail( { id, onBack } ) {
	const [ msg, setMsg ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ mailed, setMailed ] = useState( false );

	useEffect( () => {
		let live = true;
		contactApi.get( id ).then( async ( data ) => {
			if ( ! live ) {
				return;
			}
			setMsg( data );
			if ( data.status === 'new' ) {
				const updated = await contactApi.markRead( id );
				if ( live ) {
					setMsg( ( m ) => ( { ...m, status: updated.status, status_label: updated.status_label } ) );
				}
			}
		} ).catch( () => live && setError( __( 'The message could not be loaded.', 'mhm-rentiva' ) ) );
		return () => {
			live = false;
		};
	}, [ id ] );

	const setStatus = async ( status ) => {
		try {
			const updated = await contactApi.setStatus( id, status );
			setMsg( ( m ) => ( { ...m, status: updated.status, status_label: updated.status_label } ) );
			if ( status === 'replied' ) {
				setMailed( false );
			}
		} catch {
			setError( __( 'The status could not be saved.', 'mhm-rentiva' ) );
		}
	};

	if ( error && ! msg ) {
		return <Notice tone="danger">{ error }</Notice>;
	}
	if ( ! msg ) {
		return <Spinner />;
	}

	const subject = sprintf(
		/* translators: %s: enquiry type label. */
		__( 'Re: %s', 'mhm-rentiva' ),
		msg.type_label
	);
	const details = [
		...msg.fields.map( ( f ) => ( { label: f.label, value: f.value } ) ),
		...( msg.vehicle ? [ { label: __( 'Vehicle', 'mhm-rentiva' ), value: msg.vehicle.title } ] : [] ),
	];

	const aside = (
		<>
			<SenderWidget msg={ msg } />
			<TechnicalWidget id={ id } />
			<Widget title={ __( 'Delete message', 'mhm-rentiva' ) }>
				<p>{ __( 'The message moves to the trash; WordPress empties the trash automatically.', 'mhm-rentiva' ) }</p>
				<ConfirmButton
					label={ __( 'Move to trash', 'mhm-rentiva' ) }
					confirmText={ sprintf(
						/* translators: %s: sender name. */
						__( 'Move the message from %s to the trash?', 'mhm-rentiva' ),
						msg.name
					) }
					confirmLabel={ __( 'Yes, move to trash', 'mhm-rentiva' ) }
					cancelLabel={ __( 'Cancel', 'mhm-rentiva' ) }
					busyText={ __( 'Moving…', 'mhm-rentiva' ) }
					variant="danger"
					onConfirm={ async () => {
						await contactApi.trash( id );
						onBack();
					} }
				/>
			</Widget>
		</>
	);

	return (
		<div className="mhm-contact-messages">
			<PageHeader
				title={ msg.name }
				back={ { label: __( 'Back to contact messages', 'mhm-rentiva' ), href: window.mhmRentivaContactMessages.pageUrl, onClick: ( e ) => {
					e.preventDefault();
					onBack();
				} } }
				badge={ { text: msg.status_label, tone: TONE[ msg.status ] } }
				meta={ `${ msg.type_label } · ${ msg.date_label }` }
				level={ 2 }
			/>
			{ error && <Notice tone="danger">{ error }</Notice> }
			<DetailLayout aside={ aside } asideLabel={ __( 'Sender and actions', 'mhm-rentiva' ) }>
				<Widget
					title={ __( 'Message', 'mhm-rentiva' ) }
					actions={
						<div className="mhm-contact-messages__actions">
							<button type="button" className="button-link" onClick={ () => setStatus( 'new' ) }>{ __( 'Mark unread', 'mhm-rentiva' ) }</button>
							{ msg.status !== 'replied' && (
								<button type="button" className="button" onClick={ () => setStatus( 'replied' ) }>{ __( 'Mark as replied', 'mhm-rentiva' ) }</button>
							) }
							{ msg.sender.email_linkable && (
								<a className="button button-primary" href={ buildMailto( msg.sender.email, subject ) } onClick={ () => setMailed( true ) }>
									{ __( 'Reply by e-mail', 'mhm-rentiva' ) }
								</a>
							) }
						</div>
					}
				>
					{ mailed && msg.status !== 'replied' && (
						<div role="status" className="mhm-contact-messages__mail-note">
							<span>{ __( 'Your e-mail app opened a reply draft. Once you have sent it, mark the message as replied.', 'mhm-rentiva' ) }</span>
							<button type="button" className="button" onClick={ () => setStatus( 'replied' ) }>{ __( 'Mark as replied', 'mhm-rentiva' ) }</button>
						</div>
					) }
					<p className="mhm-contact-messages__body">{ msg.content }</p>
					{ msg.attachment && (
						<div className="mhm-contact-messages__attachment">
							<span>{ msg.attachment.name }</span>
							{ msg.attachment.download_url
								? <a className="button" href={ msg.attachment.download_url } download>{ __( 'Open', 'mhm-rentiva' ) }</a>
								: <span className="mhm-contact-messages__muted">{ __( 'File not available', 'mhm-rentiva' ) }</span> }
						</div>
					) }
				</Widget>
				<Widget title={ __( 'Request details', 'mhm-rentiva' ) }>
					{ details.length === 0 ? (
						<p className="mhm-contact-messages__muted">{ __( 'No further details were filled in.', 'mhm-rentiva' ) }</p>
					) : (
						// emptyText is the per-VALUE placeholder, not an empty-list text (Fable plan I9).
						<DetailList columns={ 3 } items={ details } emptyText="—" />
					) }
				</Widget>
			</DetailLayout>
		</div>
	);
}
