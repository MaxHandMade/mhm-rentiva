import { __ } from '@wordpress/i18n';

export default function StatusBadge( { status } ) {
	return (
		<span className={ `rv-scp-badge rv-scp-badge--${ status }` }>
			{ status === 'active'
				? __( 'Active', 'mhm-rentiva' )
				: __( 'Missing', 'mhm-rentiva' ) }
		</span>
	);
}
