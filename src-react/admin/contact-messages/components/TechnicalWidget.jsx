import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Widget from '../../../../vendor/mhm/ui-core/src-react/components/Widget';
import { contactApi } from '../api';

export default function TechnicalWidget( { id } ) {
	const [ open, setOpen ] = useState( false );
	const [ data, setData ] = useState( null );

	const toggle = async () => {
		if ( ! open && ! data ) {
			setData( await contactApi.technical( id ) );
		}
		setOpen( ! open );
	};

	return (
		<Widget
			title={ __( 'Technical record', 'mhm-rentiva' ) }
			actions={
				<button type="button" className="button" aria-expanded={ open } aria-controls="mhm-contact-tech" onClick={ toggle }>
					{ open ? __( 'Hide', 'mhm-rentiva' ) : __( 'Show', 'mhm-rentiva' ) }
				</button>
			}
		>
			<p>{ __( "The sender's IP address and browser. Only needed when investigating abuse.", 'mhm-rentiva' ) }</p>
			{ open && data && (
				<dl id="mhm-contact-tech" className="mhm-contact-messages__dl is-code">
					<dt>{ __( 'IP address', 'mhm-rentiva' ) }</dt>
					<dd>{ data.ip_address }</dd>
					<dt>{ __( 'Browser', 'mhm-rentiva' ) }</dt>
					<dd>{ data.user_agent }</dd>
				</dl>
			) }
		</Widget>
	);
}
