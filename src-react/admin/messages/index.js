import { render } from '@wordpress/element';
import MessagesPage from './MessagesPage';
import '../../shared/admin.css';
import './messages.css';

const root = document.getElementById( 'mhm-messages-root' );
if ( root ) {
	render( <MessagesPage />, root );
}
