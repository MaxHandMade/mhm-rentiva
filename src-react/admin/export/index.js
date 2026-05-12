import './export.css';
import { createRoot } from '@wordpress/element';
import ExportPage from './ExportPage';

const root = document.getElementById( 'mhm-export-root' );
if ( root ) {
	createRoot( root ).render( <ExportPage config={ window.mhmRentivaExport } /> );
}
