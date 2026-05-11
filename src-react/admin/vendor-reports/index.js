import { createRoot } from '@wordpress/element';
import VendorReportsPage from './VendorReportsPage';
import '../../shared/admin.css';
import './vendor-reports.css';

const container = document.getElementById( 'mhm-vendor-reports-root' );
if ( container ) {
	createRoot( container ).render( <VendorReportsPage /> );
}
