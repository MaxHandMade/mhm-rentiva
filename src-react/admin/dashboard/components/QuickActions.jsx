import { __ } from '@wordpress/i18n';

export default function QuickActions( { adminUrl } ) {
	const actions = [
		{ label: __( 'Add New Booking', 'mhm-rentiva' ),  href: `${ adminUrl }post-new.php?post_type=vehicle_booking`, icon: 'dashicons-plus-alt' },
		{ label: __( 'All Bookings', 'mhm-rentiva' ),     href: `${ adminUrl }edit.php?post_type=vehicle_booking`,     icon: 'dashicons-list-view' },
		{ label: __( 'Add New Vehicle', 'mhm-rentiva' ),  href: `${ adminUrl }post-new.php?post_type=vehicle`,         icon: 'dashicons-plus-alt' },
		{ label: __( 'All Vehicles', 'mhm-rentiva' ),     href: `${ adminUrl }edit.php?post_type=vehicle`,             icon: 'dashicons-car' },
		{ label: __( 'Transfer', 'mhm-rentiva' ),         href: `${ adminUrl }admin.php?page=mhm-rentiva-transfer-locations`, icon: 'dashicons-airplane' },
		{ label: __( 'Reports', 'mhm-rentiva' ),          href: `${ adminUrl }admin.php?page=mhm-rentiva-reports`,     icon: 'dashicons-chart-bar' },
		{ label: __( 'Settings', 'mhm-rentiva' ),         href: `${ adminUrl }admin.php?page=mhm-rentiva-settings`,    icon: 'dashicons-admin-settings' },
		{ label: __( 'Customers', 'mhm-rentiva' ),        href: `${ adminUrl }admin.php?page=mhm-rentiva-customers`,   icon: 'dashicons-groups' },
		{ label: __( 'Vendors', 'mhm-rentiva' ),          href: `${ adminUrl }admin.php?page=mhm-rentiva-vendors`,     icon: 'dashicons-groups' },
		{ label: __( 'Messages', 'mhm-rentiva' ),         href: `${ adminUrl }admin.php?page=mhm-rentiva-messages`,    icon: 'dashicons-email' },
		{ label: __( 'Additional Services', 'mhm-rentiva' ), href: `${ adminUrl }edit.php?post_type=vehicle_addon`,       icon: 'dashicons-admin-plugins' },
		{ label: __( 'Export', 'mhm-rentiva' ),           href: `${ adminUrl }admin.php?page=mhm-rentiva-export`,      icon: 'dashicons-download' },
	];

	return (
		<div className="mhm-widget mhm-quick-actions">
			<h3><span className="dashicons dashicons-performance" />{ __( 'Quick Actions', 'mhm-rentiva' ) }</h3>
			<div className="mhm-quick-actions__grid">
				{ actions.map( ( a ) => (
					<a key={ a.label } href={ a.href } className="mhm-quick-actions__item">
						<span className={ `dashicons ${ a.icon }` } />
						{ a.label }
					</a>
				) ) }
			</div>
		</div>
	);
}
