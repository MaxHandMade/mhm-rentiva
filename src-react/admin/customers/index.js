import '../../shared/admin.css';
import './customers.css';
import { createRoot } from '@wordpress/element';
import ErrorBoundary from '../../shared/components/ErrorBoundary';
import CustomersPage from './CustomersPage';

const container = document.getElementById( 'mhm-customers-root' );
if ( container ) {
	createRoot( container ).render(
		<ErrorBoundary>
			<CustomersPage />
		</ErrorBoundary>
	);
}
