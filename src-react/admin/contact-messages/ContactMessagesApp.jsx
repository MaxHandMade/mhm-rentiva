import { useState, useEffect } from '@wordpress/element';
import ContactMessagesList from './ContactMessagesList';
import ContactMessageDetail from './ContactMessageDetail';

const readUrl = () => {
	const p = new URLSearchParams( window.location.search );
	return { id: parseInt( p.get( 'id' ) || '0', 10 ) || null, status: p.get( 'status' ) || '' };
};

export default function ContactMessagesApp() {
	const [ route, setRoute ] = useState( readUrl );

	useEffect( () => {
		const onPop = () => setRoute( readUrl() );
		window.addEventListener( 'popstate', onPop );
		return () => window.removeEventListener( 'popstate', onPop );
	}, [] );

	const go = ( next ) => {
		const params = new URLSearchParams( { page: 'mhm-rentiva-contact-messages' } );
		if ( next.status ) {
			params.set( 'status', next.status );
		}
		if ( next.id ) {
			params.set( 'id', String( next.id ) );
		}
		window.history.pushState( null, '', `?${ params.toString() }` );
		setRoute( next );
		window.scrollTo( 0, 0 );
	};

	if ( route.id ) {
		return <ContactMessageDetail id={ route.id } onBack={ () => go( { status: route.status, id: null } ) } />;
	}

	return (
		<ContactMessagesList
			status={ route.status }
			onOpen={ ( id ) => go( { status: route.status, id } ) }
			onStatusChange={ ( status ) => go( { status, id: null } ) }
		/>
	);
}
