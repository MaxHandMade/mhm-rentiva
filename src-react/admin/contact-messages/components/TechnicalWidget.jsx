import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Widget from '../../../../vendor/mhm/ui-core/src-react/components/Widget';
import { contactApi } from '../api';

export default function TechnicalWidget( { id } ) {
	const [ open, setOpen ] = useState( false );
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );

	// Opening (not closing) a card that has no data yet fetches it. A failed
	// fetch leaves `data` empty, so the next open retries.
	const onToggle = async ( next ) => {
		setOpen( next );
		if ( ! next || data || loading ) {
			return;
		}
		setLoading( true );
		try {
			const result = await contactApi.technical( id );
			setData( result );
			setError( null );
		} catch {
			setError( __( 'The technical record could not be loaded.', 'mhm-rentiva' ) );
		} finally {
			setLoading( false );
		}
	};

	// Controlled and never stored: the IP and browser stay hidden on every load.
	return (
		<Widget
			level={ 2 }
			variant="plain"
			title={ __( 'Technical record', 'mhm-rentiva' ) }
			collapsible
			open={ open }
			onToggle={ onToggle }
		>
			<p>{ __( "The sender's IP address and browser. Only needed when investigating abuse.", 'mhm-rentiva' ) }</p>
			{ error && <p className="mhm-contact-messages__muted">{ error }</p> }
			<dl className="mhm-contact-messages__dl is-code">
				{ data && (
					<>
						<dt>{ __( 'IP address', 'mhm-rentiva' ) }</dt>
						<dd>{ data.ip_address }</dd>
						<dt>{ __( 'Browser', 'mhm-rentiva' ) }</dt>
						<dd>{ data.user_agent }</dd>
					</>
				) }
			</dl>
		</Widget>
	);
}
