import { __, _x, sprintf } from '@wordpress/i18n';
import Button from '../../../../vendor/mhm/ui-core/src-react/components/Button';
import StatusBadge from '../../../../vendor/mhm/ui-core/src-react/components/StatusBadge';

const TONE = { new: 'warning', read: 'neutral', replied: 'success' };

// A plain left click opens the message in place; a modified or middle click
// keeps the browser's own link behaviour (new tab).
const isPlainLeftClick = ( e ) => e.button === 0 && ! e.metaKey && ! e.ctrlKey && ! e.shiftKey && ! e.altKey;

// A row with no name falls back to its address, then to a generic label, so the
// checkbox and the Open link never get an empty accessible name.
const selectLabel = ( r ) => {
	const who = r.name || r.email;
	return who
		? sprintf(
			/* translators: %s: sender name, or e-mail address when the sender gave no name. */
			__( 'Select the message from %s', 'mhm-rentiva' ),
			who
		)
		: __( 'Select this message', 'mhm-rentiva' );
};
const openLabel = ( r ) => {
	const who = r.name || r.email;
	return who
		? sprintf(
			/* translators: %s: sender name, or e-mail address when the sender gave no name. */
			__( 'Open the message from %s', 'mhm-rentiva' ),
			who
		)
		: __( 'Open this message', 'mhm-rentiva' );
};

export default function ContactTable( { rows, selected, onToggle, onToggleAll, onOpen } ) {
	const all = rows.length > 0 && selected.length === rows.length;
	const pageUrl = window.mhmRentivaContactMessages?.pageUrl ?? '';
	const openHref = ( id ) => `${ pageUrl }${ pageUrl.includes( '?' ) ? '&' : '?' }id=${ id }`;
	const open = ( e, id ) => {
		if ( isPlainLeftClick( e ) ) {
			e.preventDefault();
			onOpen( id );
		}
	};

	return (
		<>
			<thead>
				<tr>
					<th scope="col" className="check-column">
						<input type="checkbox" aria-label={ __( 'Select all messages', 'mhm-rentiva' ) } checked={ all } onChange={ ( e ) => onToggleAll( e.target.checked ) } />
					</th>
					<th scope="col" className="mhm-contact-messages__th-sender">{ __( 'Sender', 'mhm-rentiva' ) }</th>
					<th scope="col" className="mhm-contact-messages__th-message">{ __( 'Message', 'mhm-rentiva' ) }</th>
					<th scope="col" className="mhm-contact-messages__th-vehicle">{ __( 'Related vehicle', 'mhm-rentiva' ) }</th>
					<th scope="col" className="mhm-contact-messages__th-status">{ __( 'Status', 'mhm-rentiva' ) }</th>
					<th scope="col" className="mhm-contact-messages__th-date">{ __( 'Date', 'mhm-rentiva' ) }</th>
					<th scope="col" className="mhm-contact-messages__th-action"><span className="screen-reader-text">{ __( 'Actions', 'mhm-rentiva' ) }</span></th>
				</tr>
			</thead>
			<tbody>
				{ rows.map( ( r ) => (
					<tr key={ r.id } className={ r.status === 'new' ? 'is-new' : undefined }>
						<td className="check-column">
							<input
								type="checkbox"
								aria-label={ selectLabel( r ) }
								checked={ selected.includes( r.id ) }
								onChange={ () => onToggle( r.id ) }
							/>
						</td>
						<td>
							<div className="mhm-contact-messages__sender">
								{ r.initials && <span className="mhm-contact-messages__avatar" aria-hidden="true">{ r.initials }</span> }
								<span className="mhm-contact-messages__who">
									<span className="mhm-contact-messages__name">
										{ r.status === 'new' && <span className="mhm-contact-messages__dot"><span className="screen-reader-text">{ __( 'New', 'mhm-rentiva' ) }</span></span> }
										{ r.name ? <a href={ openHref( r.id ) } onClick={ ( e ) => open( e, r.id ) }>{ r.name }</a> : '—' }
									</span>
									<span className="mhm-contact-messages__email">{ r.email || '—' }</span>
								</span>
							</div>
						</td>
						<td className="mhm-contact-messages__msg">
							<div className="mhm-contact-messages__meta">
								<span className="mhm-contact-messages__chip">{ r.type_label }</span>
								{ r.has_attachment && (
									<span className="mhm-contact-messages__attach">
										<svg aria-hidden="true" focusable="false" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
											<path d="M20 11.5l-8.1 8.1a5 5 0 01-7.1-7.1l8.1-8.1a3.3 3.3 0 014.7 4.7l-8.1 8.1a1.7 1.7 0 01-2.4-2.4l7.4-7.4" />
										</svg>
										{ _x( 'Attachment', 'contact message', 'mhm-rentiva' ) }
									</span>
								) }
								{ r.rating > 0 && (
									<span className="mhm-contact-messages__rating">
										<span aria-hidden="true">★</span>
										{ sprintf(
											/* translators: %d: rating out of 5. */
											__( '%d/5', 'mhm-rentiva' ),
											r.rating
										) }
									</span>
								) }
							</div>
							<div className="mhm-contact-messages__snippet">{ r.snippet }</div>
						</td>
						<td className={ r.vehicle ? undefined : 'is-empty' }>{ r.vehicle ? r.vehicle.title : '—' }</td>
						<td><StatusBadge tone={ TONE[ r.status ] }>{ r.status_label }</StatusBadge></td>
						<td><time dateTime={ r.date_iso }>{ r.date_label }</time></td>
						<td className="mhm-contact-messages__open">
							<Button
								size="sm"
								href={ openHref( r.id ) }
								aria-label={ openLabel( r ) }
								onClick={ ( e ) => open( e, r.id ) }
							>
								{ __( 'Open', 'mhm-rentiva' ) }
							</Button>
						</td>
					</tr>
				) ) }
			</tbody>
		</>
	);
}
