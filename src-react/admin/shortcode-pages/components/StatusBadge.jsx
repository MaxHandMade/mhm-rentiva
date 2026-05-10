import { __ } from '@wordpress/i18n';

export default function StatusBadge( { status } ) {
	return (
		<span className={ `mhm-sc-badge mhm-sc-badge--${ status }` }>
			{ status === 'active'
				? __( 'Active', 'mhm-rentiva' )
				: __( 'Missing', 'mhm-rentiva' ) }
		</span>
	);
}
