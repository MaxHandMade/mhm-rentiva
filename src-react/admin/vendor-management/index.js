import { createRoot } from '@wordpress/element';
import VendorManagementPage from './VendorManagementPage';
import '../../shared/admin.css';
import './vendor-management.css';

const container = document.getElementById( 'mhm-vendor-management-root' );
if ( container ) {
	createRoot( container ).render( <VendorManagementPage /> );
}
