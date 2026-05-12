import '../../shared/admin.css';
import './export.css';
import { createRoot } from '@wordpress/element';
import ErrorBoundary from '../../shared/components/ErrorBoundary';
import ExportPage from './ExportPage';

const root = document.getElementById( 'mhm-export-root' );
if ( root ) {
	createRoot( root ).render(
		<ErrorBoundary>
			<ExportPage config={ window.mhmRentivaExport ?? {} } />
		</ErrorBoundary>
	);
}
