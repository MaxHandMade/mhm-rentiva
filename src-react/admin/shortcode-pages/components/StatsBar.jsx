import { __ } from '@wordpress/i18n';

export default function StatsBar( { stats } ) {
	if ( ! stats ) {
		return null;
	}
	const cards = [
		{ key: 'total',   value: stats.total,   label: __( 'Total', 'mhm-rentiva' ) },
		{ key: 'active',  value: stats.active,  label: __( 'Active', 'mhm-rentiva' ),  tone: 'is-active' },
		{ key: 'missing', value: stats.missing, label: __( 'Missing', 'mhm-rentiva' ), tone: 'is-missing' },
	];
	return (
		<div className="rv-scp-kpis">
			{ cards.map( ( c ) => (
				<div key={ c.key } className={ `rv-scp-kpi${ c.tone ? ` ${ c.tone }` : '' }` }>
					<div className="rv-scp-kpi__label">{ c.label }</div>
					<div className="rv-scp-kpi__value">{ c.value }</div>
				</div>
			) ) }
		</div>
	);
}
