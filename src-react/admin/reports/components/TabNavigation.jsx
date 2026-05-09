import { __ } from '@wordpress/i18n';

const TABS = [
	{ key: 'overview',  label: __( 'Overview',        'mhm-rentiva' ) },
	{ key: 'revenue',   label: __( 'Revenue Report',  'mhm-rentiva' ) },
	{ key: 'bookings',  label: __( 'Booking Report',  'mhm-rentiva' ) },
	{ key: 'vehicles',  label: __( 'Vehicle Report',  'mhm-rentiva' ) },
	{ key: 'customers', label: __( 'Customer Report', 'mhm-rentiva' ) },
];

export default function TabNavigation( { activeTab, onChange } ) {
	return (
		<div className="nav-tab-wrapper mhm-reports__tabs">
			{ TABS.map( ( tab ) => (
				<button
					key={ tab.key }
					type="button"
					className={ `nav-tab${ activeTab === tab.key ? ' nav-tab-active' : '' }` }
					onClick={ () => onChange( tab.key ) }
				>
					{ tab.label }
				</button>
			) ) }
		</div>
	);
}
