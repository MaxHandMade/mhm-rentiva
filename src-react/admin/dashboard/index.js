import '../../shared/admin.css';
import './dashboard.css';
import { createRoot } from '@wordpress/element';
import { registerIcons } from '../../../vendor/mhm/ui-core/src-react/icons';
import ErrorBoundary from '../../shared/components/ErrorBoundary';
import DashboardPage from './DashboardPage';

/**
 * Rentiva's own icon concepts, for THIS bundle.
 *
 * 🔴 The JSX registry is not the PHP one. The package's PHP registry lives in
 * the winning copy and one call serves every plugin on the site; the JSX
 * registry lives inside whichever bundle registered it and reaches no other
 * bundle. So a product concept used by a React screen has to be registered by
 * that screen's own entry point — mhm-rentiva.php's registration does nothing
 * here.
 *
 * Only bundles that actually use a product concept need this. Bundles whose
 * cards use seed concepts only (`revenue`, `active`, `new`, `time`…) resolve
 * them without any registration.
 *
 * Same value as the PHP side, deliberately: `vehicles` must draw the same
 * glyph whether a strip is rendered in PHP or in React.
 */
registerIcons( { vehicles: 'car' } );

const container = document.getElementById( 'mhm-rentiva-dashboard' );
if ( container ) {
	createRoot( container ).render(
		<ErrorBoundary>
			<DashboardPage />
		</ErrorBoundary>
	);
}
