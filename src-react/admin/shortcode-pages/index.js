import { createRoot } from '@wordpress/element';
import { registerIcons } from '../../../vendor/mhm/ui-core/src-react/icons';
import ErrorBoundary from '../../shared/components/ErrorBoundary';
import ShortcodePagesPage from './ShortcodePagesPage';
import '../../shared/admin.css';
import './shortcode-pages.css';

// The JSX registry lives in this bundle only (see dashboard/index.js): the
// product concepts this screen's cards use are registered here, with the
// same values as IconConcepts::MAP.
registerIcons( { pages: 'admin-page', missing: 'warning' } );

const container = document.getElementById( 'mhm-shortcode-pages-root' );
if ( container ) {
	createRoot( container ).render(
		<ErrorBoundary>
			<ShortcodePagesPage />
		</ErrorBoundary>
	);
}
