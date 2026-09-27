import '../../shared/admin.css';
import './contact-messages.css';
import { createRoot } from '@wordpress/element';
import { registerIcons } from '../../../vendor/mhm/ui-core/src-react/icons';
import ErrorBoundary from '../../shared/components/ErrorBoundary';
import ContactMessagesApp from './ContactMessagesApp';

// This bundle's product concept (the JSX registry is per bundle; see
// dashboard/index.js). Same glyph as IconConcepts::MAP['messages'].
registerIcons( { messages: 'email' } );

const container = document.getElementById( 'mhm-contact-messages-root' );
if ( container ) {
	createRoot( container ).render(
		<ErrorBoundary>
			<ContactMessagesApp />
		</ErrorBoundary>
	);
}
