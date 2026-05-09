import { createRoot } from '@wordpress/element';
import ErrorBoundary  from '../../shared/components/ErrorBoundary';
import ReportsPage    from './ReportsPage';

const container = document.getElementById( 'mhm-reports-root' );
if ( container ) {
	createRoot( container ).render(
		<ErrorBoundary>
			<ReportsPage />
		</ErrorBoundary>
	);
}
