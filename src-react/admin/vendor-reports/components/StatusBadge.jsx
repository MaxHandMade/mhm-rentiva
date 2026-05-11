export default function StatusBadge( { status, label } ) {
	return (
		<span className={ `mhm-vr-badge mhm-vr-badge--${ status }` }>
			{ label }
		</span>
	);
}
