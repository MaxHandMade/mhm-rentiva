import { useState, useEffect } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import PageHeader from '../../../vendor/mhm/ui-core/src-react/components/PageHeader';
import DetailLayout from '../../../vendor/mhm/ui-core/src-react/components/DetailLayout';
import DetailList from '../../../vendor/mhm/ui-core/src-react/components/DetailList';
import Widget from '../../../vendor/mhm/ui-core/src-react/components/Widget';
import Button from '../../../vendor/mhm/ui-core/src-react/components/Button';
import ConfirmButton from '../../../vendor/mhm/ui-core/src-react/components/ConfirmButton';
import Notice from '../../../vendor/mhm/ui-core/src-react/components/Notice';
import SenderWidget from './components/SenderWidget';
import TechnicalWidget from './components/TechnicalWidget';
import { contactApi } from './api';
import { buildMailto } from './mailto';
import { isTrashEnabled } from './trash';

const TONE = { new: 'warning', read: 'neutral', replied: 'success' };
const IMAGE_EXT = /.(jpe?g|png|gif|webp|avif|bmp|svg)$/i;

const svgProps = { 'aria-hidden': 'true', focusable: 'false', fill: 'none', stroke: 'currentColor', strokeLinecap: 'round', strokeLinejoin: 'round' };
const ReplyIcon = () => (
	<svg { ...svgProps } width="15" height="15" viewBox="0 0 24 24" strokeWidth="2">
		<path d="M9 7L4.5 11.5 9 16" />
		<path d="M4.5 11.5H15a4.5 4.5 0 010 9h-2" />
	</svg>
);
const AttachmentIcon = ( { image } ) => (
	<svg { ...svgProps } width="20" height="20" viewBox="0 0 24 24" strokeWidth="1.8">
		{ image ? (
			<>
				<rect x="3.5" y="4.5" width="17" height="15" rx="2" />
				<circle cx="9" cy="10" r="1.8" />
				<path d="M20.5 16l-5-5-8 8.5" />
			</>
		) : (
			<>
				<path d="M14 3.5H7a2 2 0 00-2 2v13a2 2 0 002 2h10a2 2 0 002-2V8.5z" />
				<path d="M14 3.5v5h5" />
			</>
		) }
	</svg>
);

export default function ContactMessageDetail( { id, onBack } ) {
	// Read inside the component (house pattern, ContactMessagesList.jsx:18): a
	// module-level read runs before a test can set the global.
	const trashEnabled = isTrashEnabled();
	const [ msg, setMsg ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ mailed, setMailed ] = useState( false );
	const [ restoring, setRestoring ] = useState( false );

	useEffect( () => {
		let live = true;
		contactApi.get( id ).then( ( data ) => {
			if ( ! live ) {
				return;
			}
			setMsg( data );
			// A trashed message is never auto-marked read: it is on its way out,
			// and mark-as-read is not one of the two actions the trash view offers.
			if ( data.status === 'new' && ! data.trashed ) {
				// Its own catch: a failed markRead must not overwrite an
				// already-loaded message with the "could not be loaded" notice.
				contactApi.markRead( id ).then( ( updated ) => {
					if ( live ) {
						setMsg( ( m ) => ( { ...m, status: updated.status, status_label: updated.status_label } ) );
					}
				} ).catch( () => {
					if ( live ) {
						setError( __( 'The message could not be marked as read.', 'mhm-rentiva' ) );
					}
				} );
			}
		} ).catch( () => {
			if ( live ) {
				setError( __( 'The message could not be loaded.', 'mhm-rentiva' ) );
			}
		} );
		return () => {
			live = false;
		};
	}, [ id ] );

	const setStatus = async ( status ) => {
		try {
			const updated = await contactApi.setStatus( id, status );
			setMsg( ( m ) => ( { ...m, status: updated.status, status_label: updated.status_label } ) );
			setError( null );
			if ( status === 'replied' ) {
				setMailed( false );
			}
		} catch {
			setError( __( 'The status could not be saved.', 'mhm-rentiva' ) );
		}
	};

	const restore = async () => {
		setRestoring( true );
		try {
			const { results } = await contactApi.bulk( [ id ], 'restore' );
			// bulk() answers HTTP 200 even when the action itself failed
			// (id not found, not in the trash any more, etc.) -- results[0].ok
			// is the real outcome, not the resolved promise.
			if ( ! results?.[ 0 ]?.ok ) {
				setError( __( 'The message could not be restored.', 'mhm-rentiva' ) );
				return;
			}
			onBack();
		} catch {
			setError( __( 'The message could not be restored.', 'mhm-rentiva' ) );
		} finally {
			setRestoring( false );
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
	const details = msg.fields.map( ( f ) => ( { label: f.label, value: f.value } ) );
	if ( msg.vehicle ) {
		// The artboard draws the vehicle right after the "Submitted" date.
		const vehicle = {
			label: __( 'Vehicle', 'mhm-rentiva' ),
			value: msg.vehicle.edit_url ? <a href={ msg.vehicle.edit_url }>{ msg.vehicle.title }</a> : msg.vehicle.title,
		};
		const after = msg.fields.findIndex( ( f ) => f.key === 'submitted' );
		details.splice( after < 0 ? details.length : after + 1, 0, vehicle );
	}
	const name = msg.name || '—';
	const meta = sprintf(
		/* translators: 1: enquiry type label, 2: date and time, 3: message number line. */
		__( '%1$s · %2$s · %3$s', 'mhm-rentiva' ),
		msg.type_label,
		msg.date_label_long || msg.date_label,
		sprintf(
			/* translators: %d: message number. */
			__( 'Message #%d', 'mhm-rentiva' ),
			msg.id
		)
	);
	const mailto = msg.sender.email_linkable ? buildMailto( msg.sender.email, subject ) : '';
	const attachmentMeta = msg.attachment ? [ msg.attachment.type_label, msg.attachment.size_label ].filter( Boolean ).join( ' · ' ) : '';

	const trashed = Boolean( msg.trashed );

	const aside = (
		<>
			<SenderWidget msg={ msg } />
			<TechnicalWidget id={ id } />
			{ trashed ? (
				<Widget level={ 2 } variant="plain" title={ __( 'Trashed message', 'mhm-rentiva' ) }>
					<p>{ __( 'This message is in the trash. Restore it, or delete it permanently.', 'mhm-rentiva' ) }</p>
					<div className="mhm-contact-messages__actions">
						<Button size="sm" disabled={ restoring } onClick={ restore }>{ __( 'Restore', 'mhm-rentiva' ) }</Button>
						<ConfirmButton
							label={ __( 'Delete permanently', 'mhm-rentiva' ) }
							confirmText={ sprintf(
								/* translators: %s: sender name. */
								__( 'Delete the message from %s permanently? This cannot be undone.', 'mhm-rentiva' ),
								name
							) }
							confirmLabel={ __( 'Yes, delete', 'mhm-rentiva' ) }
							cancelLabel={ __( 'Cancel', 'mhm-rentiva' ) }
							busyText={ __( 'Deleting…', 'mhm-rentiva' ) }
							variant="danger"
							size="compact"
							onConfirm={ async () => {
								try {
									const res = await contactApi.destroy( id );
									// destroy() also answers HTTP 200 with { deleted: false }
									// when the record was not actually removed (already gone,
									// not in the trash, etc.) -- do not treat that as success.
									if ( ! res?.deleted ) {
										setError( __( 'The message could not be deleted.', 'mhm-rentiva' ) );
										return;
									}
									onBack();
								} catch {
									setError( __( 'The message could not be deleted.', 'mhm-rentiva' ) );
								}
							} }
						/>
					</div>
				</Widget>
			) : (
				<Widget level={ 2 } variant="plain" title={ __( 'Delete message', 'mhm-rentiva' ) }>
					<p>{ trashEnabled
						? __( 'The message moves to the trash; WordPress empties the trash automatically.', 'mhm-rentiva' )
						: __( 'The message is deleted immediately; the trash is disabled on this site.', 'mhm-rentiva' ) }</p>
					<ConfirmButton
						label={ trashEnabled ? __( 'Move to trash', 'mhm-rentiva' ) : __( 'Delete permanently', 'mhm-rentiva' ) }
						confirmText={ trashEnabled
							? sprintf(
								/* translators: %s: sender name. */
								__( 'Move the message from %s to the trash?', 'mhm-rentiva' ),
								name
							)
							: sprintf(
								/* translators: %s: sender name. */
								__( 'Delete the message from %s permanently? This cannot be undone.', 'mhm-rentiva' ),
								name
							) }
						confirmLabel={ trashEnabled ? __( 'Yes, move to trash', 'mhm-rentiva' ) : __( 'Yes, delete permanently', 'mhm-rentiva' ) }
						cancelLabel={ __( 'Cancel', 'mhm-rentiva' ) }
						busyText={ trashEnabled ? __( 'Moving…', 'mhm-rentiva' ) : __( 'Deleting…', 'mhm-rentiva' ) }
						variant="danger"
							size="compact"
						onConfirm={ async () => {
							// wp_trash_post() itself deletes the record outright when
							// EMPTY_TRASH_DAYS is falsy (REST controller comment,
							// ContactMessagesRestController.php:269) -- same call either way.
							// A 200 with neither flag set (the record vanished between the
							// lookup and the core call) is not a success -- stay on screen.
							try {
								const res = await contactApi.trash( id );
								if ( res?.trashed || res?.deleted ) {
									onBack();
									return;
								}
								throw new Error( 'not_trashed' );
							} catch {
								setError( trashEnabled
									? __( 'The message could not be moved to the trash.', 'mhm-rentiva' )
									: __( 'The message could not be deleted.', 'mhm-rentiva' ) );
							}
						} }
					/>
				</Widget>
			) }
		</>
	);

	return (
		<div className="mhm-contact-messages mhmui-admin mhmui-admin-page">
			<PageHeader
				level={ 1 }
				title={ name }
				back={ { label: __( 'Back to contact messages', 'mhm-rentiva' ), href: window.mhmRentivaContactMessages?.pageUrl ?? '', onClick: ( e ) => {
					e.preventDefault();
					onBack();
				} } }
				badge={ { text: msg.status_label, tone: TONE[ msg.status ] } }
				meta={ meta }
			/>
			{ error && <Notice tone="danger">{ error }</Notice> }
			<DetailLayout aside={ aside } asideLabel={ __( 'Sender and actions', 'mhm-rentiva' ) }>
				<Widget
					level={ 2 }
					title={ __( 'Message', 'mhm-rentiva' ) }
					actions={
						trashed ? null : (
							<div className="mhm-contact-messages__actions">
								<Button variant="plain" onClick={ () => setStatus( 'new' ) }>{ __( 'Mark unread', 'mhm-rentiva' ) }</Button>
								{ msg.status !== 'replied' && (
									<Button onClick={ () => setStatus( 'replied' ) }>{ __( 'Mark as replied', 'mhm-rentiva' ) }</Button>
								) }
								{ mailto && (
									<Button variant="primary" href={ mailto } icon={ <ReplyIcon /> } onClick={ () => setMailed( true ) }>
										{ __( 'Reply by e-mail', 'mhm-rentiva' ) }
									</Button>
								) }
							</div>
						)
					}
				>
					{ /* Always present so a screen reader has a live region to announce
					     into; empty (and visually absent -- no CSS class) when there is
					     nothing to say. */ }
					<div role="status" aria-label={ __( 'Reply reminder', 'mhm-rentiva' ) }>
						{ mailed && msg.status !== 'replied' && (
							<div className="mhm-contact-messages__mail-note">
								<span>{ __( 'Your e-mail app opened a reply draft. Once you have sent it, mark the message as replied.', 'mhm-rentiva' ) }</span>
								<Button onClick={ () => setStatus( 'replied' ) }>{ __( 'Mark as replied', 'mhm-rentiva' ) }</Button>
							</div>
						) }
					</div>
					<p className="mhm-contact-messages__body">{ msg.content }</p>
					{ msg.attachment && (
						<div className="mhm-contact-messages__attachment-block">
							<h3 className="mhm-contact-messages__subhead">{ __( 'Attachment', 'mhm-rentiva' ) }</h3>
							<div className="mhm-contact-messages__attachment">
								<span className="mhm-contact-messages__attachment-icon" aria-hidden="true">
									<AttachmentIcon image={ IMAGE_EXT.test( msg.attachment.name ) } />
								</span>
								<div className="mhm-contact-messages__attachment-info">
									<span className="mhm-contact-messages__attachment-name">{ msg.attachment.name }</span>
									{ attachmentMeta && <span className="mhm-contact-messages__muted">{ attachmentMeta }</span> }
								</div>
								{ msg.attachment.download_url
									? <Button href={ msg.attachment.download_url }>{ __( 'Download', 'mhm-rentiva' ) }</Button>
									: <span className="mhm-contact-messages__muted">{ __( 'File not available', 'mhm-rentiva' ) }</span> }
							</div>
						</div>
					) }
				</Widget>
				<Widget level={ 2 } variant="plain" title={ __( 'Request details', 'mhm-rentiva' ) }>
					{ details.length === 0 ? (
						<p className="mhm-contact-messages__muted">{ __( 'No further details were filled in.', 'mhm-rentiva' ) }</p>
					) : (
						// emptyText is the per-VALUE placeholder, not an empty-list text (Fable plan I9).
						<DetailList columns={ 3 } items={ details } emptyText="—" />
					) }
					<span className="mhm-contact-messages__muted mhm-contact-messages__help">{ __( 'Fields left empty on the form are not shown.', 'mhm-rentiva' ) }</span>
				</Widget>
			</DetailLayout>
		</div>
	);
}
