import { __, sprintf } from '@wordpress/i18n';
import StatusBadge from '../../../../vendor/mhm/ui-core/src-react/components/StatusBadge';

const TONE = { new: 'warning', read: 'neutral', replied: 'success' };

export default function ContactTable( { rows, selected, onToggle, onToggleAll, onOpen } ) {
	const all = rows.length > 0 && selected.length === rows.length;

	return (
		<>
			<thead>
				<tr>
					<th scope="col" className="check-column">
						<input type="checkbox" aria-label={ __( 'Select all messages', 'mhm-rentiva' ) } checked={ all } onChange={ ( e ) => onToggleAll( e.target.checked ) } />
					</th>
					<th scope="col">{ __( 'Sender', 'mhm-rentiva' ) }</th>
					<th scope="col">{ __( 'Message', 'mhm-rentiva' ) }</th>
					<th scope="col">{ __( 'Vehicle', 'mhm-rentiva' ) }</th>
					<th scope="col">{ __( 'Status', 'mhm-rentiva' ) }</th>
					<th scope="col">{ __( 'Date', 'mhm-rentiva' ) }</th>
					<th scope="col"><span className="screen-reader-text">{ __( 'Actions', 'mhm-rentiva' ) }</span></th>
				</tr>
			</thead>
			<tbody>
				{ rows.map( ( r ) => (
					<tr key={ r.id } className={ r.status === 'new' ? 'is-new' : undefined }>
						<td className="check-column">
							<input
								type="checkbox"
								/* translators: %s: sender name. */
								aria-label={ sprintf( __( 'Select the message from %s', 'mhm-rentiva' ), r.name ) }
								checked={ selected.includes( r.id ) }
								onChange={ () => onToggle( r.id ) }
							/>
						</td>
						<td>
							<div className="mhm-contact-messages__sender">
								<span className="mhm-contact-messages__avatar" aria-hidden="true">{ r.initials }</span>
								<span>
									<span className="mhm-contact-messages__name">
										{ r.status === 'new' && <span className="mhm-contact-messages__dot"><span className="screen-reader-text">{ __( 'New', 'mhm-rentiva' ) }</span></span> }
										{ r.name }
									</span>
									<span className="mhm-contact-messages__email">{ r.email }</span>
								</span>
							</div>
						</td>
						<td>
							<div className="mhm-contact-messages__meta">
								<span className="mhm-contact-messages__chip">{ r.type_label }</span>
								{ r.has_attachment && <span className="mhm-contact-messages__attach">{ __( 'Attachment', 'mhm-rentiva' ) }</span> }
								{ r.rating > 0 && (
									<span className="mhm-contact-messages__rating">
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
						<td>
							<button
								type="button"
								className="button"
								/* translators: %s: sender name. */
								aria-label={ sprintf( __( 'Open the message from %s', 'mhm-rentiva' ), r.name ) }
								onClick={ () => onOpen( r.id ) }
							>
								{ __( 'Open', 'mhm-rentiva' ) }
							</button>
						</td>
					</tr>
				) ) }
			</tbody>
		</>
	);
}
