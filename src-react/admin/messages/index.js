import { createRoot } from '@wordpress/element';
import ErrorBoundary from '../../shared/components/ErrorBoundary';
import MessagesPage from './MessagesPage';
import '../../shared/admin.css';
import './messages.css';

const container = document.getElementById( 'mhm-messages-root' );
if ( container ) {
	createRoot( container ).render(
		<ErrorBoundary>
			<MessagesPage />
		</ErrorBoundary>
	);
}
