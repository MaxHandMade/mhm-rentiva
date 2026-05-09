import { createRoot } from '@wordpress/element';
import ErrorBoundary from '../../shared/components/ErrorBoundary';
import DashboardPage from './DashboardPage';

const container = document.getElementById( 'mhm-rentiva-dashboard' );
if ( container ) {
	createRoot( container ).render(
		<ErrorBoundary>
			<DashboardPage />
		</ErrorBoundary>
	);
}
