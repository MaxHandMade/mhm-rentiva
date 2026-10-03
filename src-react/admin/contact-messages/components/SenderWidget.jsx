import { useState } from '@wordpress/element';
import { __, sprintf, _n } from '@wordpress/i18n';
import Widget from '../../../../vendor/mhm/ui-core/src-react/components/Widget';
import Button from '../../../../vendor/mhm/ui-core/src-react/components/Button';

const svgProps = { 'aria-hidden': 'true', focusable: 'false', fill: 'none', stroke: 'currentColor', strokeLinecap: 'round' };
const Chevron = () => (
	<svg { ...svgProps } width="14" height="14" viewBox="0 0 24 24" strokeWidth="2">
		<path d="M9 6l6 6-6 6" />
	</svg>
);
const CustomerIcon = () => (
	<svg { ...svgProps } width="16" height="16" viewBox="0 0 24 24" strokeWidth="1.8" strokeLinejoin="round">
		<circle cx="12" cy="8.5" r="3.5" />
		<path d="M5 20a7 7 0 0114 0" />
	</svg>
);

export default function SenderWidget( { msg } ) {
	const [ copied, setCopied ] = useState( false );
	const { sender } = msg;
	const pageUrl = window.mhmRentivaContactMessages?.pageUrl ?? '';

	const copy = async () => {
		try {
			await window.navigator.clipboard.writeText( sender.email );
			setCopied( true );
		} catch {
			setCopied( false );
		}
	};

	const othersHref = `${ pageUrl }${ pageUrl.includes( '?' ) ? '&' : '?' }search=${ encodeURIComponent( sender.email ) }`;
	const others = sender.other_count > 0
		? sprintf(
			/* translators: %d: number of other messages from the same address. */
			_n( '%d more message from this address', '%d more messages from this address', sender.other_count, 'mhm-rentiva' ),
			sender.other_count
		)
		: '';
	const hasLinks = sender.customer_url || ( others && sender.email );

	return (
		<Widget level={ 2 } variant="plain" title={ __( 'Sender', 'mhm-rentiva' ) }>
			<div className="mhm-contact-messages__sender">
				{ msg.initials && <span className="mhm-contact-messages__avatar is-large" aria-hidden="true">{ msg.initials }</span> }
				<strong>{ msg.name || '—' }</strong>
			</div>
			<dl className="mhm-contact-messages__dl">
				<div>
					<dt>{ __( 'E-mail', 'mhm-rentiva' ) }</dt>
					<dd className="mhm-contact-messages__row">
						{ sender.email && sender.email_linkable
							? <a href={ `mailto:${ sender.email }` }>{ sender.email }</a>
							: <span>{ sender.email || '—' }</span> }
						{ sender.email && (
							<Button variant="neutral" size="sm" onClick={ copy }>
								{ copied ? __( 'Copied', 'mhm-rentiva' ) : __( 'Copy', 'mhm-rentiva' ) }
							</Button>
						) }
					</dd>
				</div>
				{ sender.phone && (
					<div>
						<dt>{ __( 'Phone', 'mhm-rentiva' ) }</dt>
						<dd><a href={ `tel:${ sender.phone.replace( /[^\d+]/g, '' ) }` }>{ sender.phone }</a></dd>
					</div>
				) }
			</dl>
			{ hasLinks && (
				<div className="mhm-contact-messages__sender-links">
					{ sender.customer_url && (
						<>
							<span className="mhm-contact-messages__customer">
								<CustomerIcon />
								{ __( 'This e-mail belongs to a registered customer', 'mhm-rentiva' ) }
							</span>
							<a className="mhm-contact-messages__more" href={ sender.customer_url }>
								{ __( 'Open customer record', 'mhm-rentiva' ) }
								<Chevron />
							</a>
						</>
					) }
					{ others && sender.email && (
						<a className="mhm-contact-messages__more" href={ othersHref }>
							{ others }
							<Chevron />
						</a>
					) }
				</div>
			) }
		</Widget>
	);
}
