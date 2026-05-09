import { createRoot } from '@wordpress/element';
import ErrorBoundary from '../../shared/components/ErrorBoundary';

// DashboardPage component is added in Faz 1a.
function DashboardPage() {
	return null;
}

const container = document.getElementById( 'mhm-rentiva-dashboard' );
if ( container ) {
	createRoot( container ).render(
		<ErrorBoundary>
			<DashboardPage />
		</ErrorBoundary>
	);
}
