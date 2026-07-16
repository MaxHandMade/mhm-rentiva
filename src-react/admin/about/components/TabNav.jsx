import { __ } from '@wordpress/i18n';

const TABS = [
	[ 'general',   __( 'General Information', 'mhm-rentiva' ) ],
	[ 'system',    __( 'System Information', 'mhm-rentiva' ) ],
	[ 'support',   __( 'Support', 'mhm-rentiva' ) ],
	[ 'developer', __( 'Developer', 'mhm-rentiva' ) ],
];

export default function TabNav( { activeTab, onSwitch } ) {
	return (
		<nav className="nav-tab-wrapper">
			{ TABS.map( ( [ id, label ] ) => (
				<a
					key={ id }
					href={ `?page=mhm-rentiva-about&tab=${ id }` }
					className={ `nav-tab${ activeTab === id ? ' nav-tab-active' : '' }` }
					onClick={ ( e ) => { e.preventDefault(); onSwitch( id ); } }
				>
					{ label }
				</a>
			) ) }
		</nav>
	);
}
