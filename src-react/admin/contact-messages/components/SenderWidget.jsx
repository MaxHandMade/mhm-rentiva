import { useState } from '@wordpress/element';
import { __, sprintf, _n } from '@wordpress/i18n';
import Widget from '../../../../vendor/mhm/ui-core/src-react/components/Widget';

export default function SenderWidget( { msg } ) {
	const [ copied, setCopied ] = useState( false );
	const { sender } = msg;

	const copy = async () => {
		try {
			await window.navigator.clipboard.writeText( sender.email );
			setCopied( true );
		} catch {
			setCopied( false );
		}
	};

	return (
		<Widget title={ __( 'Sender', 'mhm-rentiva' ) }>
			<div className="mhm-contact-messages__sender">
				<span className="mhm-contact-messages__avatar is-large" aria-hidden="true">{ msg.initials }</span>
				<strong>{ msg.name }</strong>
			</div>
			<dl className="mhm-contact-messages__dl">
				<dt>{ __( 'E-mail', 'mhm-rentiva' ) }</dt>
				<dd className="mhm-contact-messages__row">
					<span>{ sender.email }</span>
					<button type="button" className="button button-small" onClick={ copy }>
						{ copied ? __( 'Copied', 'mhm-rentiva' ) : __( 'Copy', 'mhm-rentiva' ) }
					</button>
				</dd>
				{ sender.phone && (
					<>
						<dt>{ __( 'Phone', 'mhm-rentiva' ) }</dt>
						<dd><a href={ `tel:${ sender.phone.replace( /[^\d+]/g, '' ) }` }>{ sender.phone }</a></dd>
					</>
				) }
			</dl>
			{ sender.customer_url && (
				<p><a href={ sender.customer_url }>{ __( 'This e-mail belongs to a registered customer — open customer record', 'mhm-rentiva' ) }</a></p>
			) }
			{ sender.other_count > 0 && (
				<p>
					{ sprintf(
						/* translators: %d: number of other messages from the same address. */
						_n( '%d more message from this address', '%d more messages from this address', sender.other_count, 'mhm-rentiva' ),
						sender.other_count
					) }
				</p>
			) }
		</Widget>
	);
}
