import { useState, useEffect } from '@wordpress/element';
import ContactMessagesList from './ContactMessagesList';
import ContactMessageDetail from './ContactMessageDetail';

// The five statuses ContactMessagesList/the REST route actually understand.
// Anything else in the URL (typo'd, stale, hand-edited) falls back to '' (All)
// rather than reaching contactApi.list() as an unrecognised filter value.
const VALID_STATUSES = new Set( [ '', 'new', 'read', 'replied', 'trash' ] );
const normalizeStatus = ( value ) => ( VALID_STATUSES.has( value ) ? value : '' );

const readUrl = () => {
	const p = new URLSearchParams( window.location.search );
	return { id: parseInt( p.get( 'id' ) || '0', 10 ) || null, status: normalizeStatus( p.get( 'status' ) || '' ) };
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
