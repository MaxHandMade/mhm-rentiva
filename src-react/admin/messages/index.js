import { render } from '@wordpress/element';
import MessagesPage from './MessagesPage';
import './messages.css';

const root = document.getElementById( 'mhm-messages-root' );
if ( root ) {
	render( <MessagesPage />, root );
}
