import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import AboutPage from './AboutPage';
import ErrorBoundary from '../../shared/components/ErrorBoundary';
import '../../shared/admin.css';
import './about.css';

const { nonce } = window.mhmRentivaAbout ?? {};
if ( nonce ) {
	apiFetch.use( apiFetch.createNonceMiddleware( nonce ) );
}

const container = document.getElementById( 'mhm-about-root' );
if ( container ) {
	createRoot( container ).render(
		<ErrorBoundary>
			<AboutPage />
		</ErrorBoundary>
	);
}
