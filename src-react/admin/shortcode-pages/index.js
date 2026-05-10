import { createRoot } from '@wordpress/element';
import ShortcodePagesPage from './ShortcodePagesPage';
import '../../shared/admin.css';
import './shortcode-pages.css';

const container = document.getElementById( 'mhm-shortcode-pages-root' );
if ( container ) {
	createRoot( container ).render( <ShortcodePagesPage /> );
}
