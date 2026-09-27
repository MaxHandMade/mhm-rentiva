import { useState, useId } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Widget from '../../../../vendor/mhm/ui-core/src-react/components/Widget';
import { contactApi } from '../api';

export default function TechnicalWidget( { id } ) {
	const [ open, setOpen ] = useState( false );
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );
	const panelId = useId();

	const toggle = async () => {
		if ( loading ) {
			return;
		}
		if ( ! open && ! data ) {
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
		}
		setOpen( ! open );
	};

	return (
		<Widget
			title={ __( 'Technical record', 'mhm-rentiva' ) }
			actions={
				<button type="button" className="button" aria-expanded={ open } aria-controls={ panelId } disabled={ loading } onClick={ toggle }>
					{ open ? __( 'Hide', 'mhm-rentiva' ) : __( 'Show', 'mhm-rentiva' ) }
				</button>
			}
		>
			<p>{ __( "The sender's IP address and browser. Only needed when investigating abuse.", 'mhm-rentiva' ) }</p>
			{ error && <p className="mhm-contact-messages__muted">{ error }</p> }
			{ /* Always rendered (with an id `aria-controls` can always resolve to);
			     `hidden` keeps it out of view/the a11y tree until opened. */ }
			<dl id={ panelId } className="mhm-contact-messages__dl is-code" hidden={ ! open }>
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
