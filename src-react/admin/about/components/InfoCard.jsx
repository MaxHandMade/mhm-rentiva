import { __ } from '@wordpress/i18n';

/**
 * One row's display value. Kept out of JSX so the boolean branch does not have
 * to be a nested ternary.
 *
 * @param {Object} row Row descriptor from the system report.
 * @return {string} Text to render.
 */
function formatValue( row ) {
	if ( row.boolean ) {
		return row.value
			? __( 'Yes', 'mhm-rentiva' )
			: __( 'No', 'mhm-rentiva' );
	}
	return `${ row.value }${ row.suffix ?? '' }`;
}

export default function InfoCard( { title, rows, accent = false } ) {
	return (
		<div className={ `mhm-widget rv-abt-card mhm-about-info-card${ accent ? ' is-accent' : '' }` }>
			<h3>{ title }</h3>
			<dl>
				{ rows.map( ( row, i ) => (
					<div key={ i } className="mhm-info-row">
						<dt>{ row.label }</dt>
						<dd>{ formatValue( row ) }</dd>
					</div>
				) ) }
			</dl>
		</div>
	);
}
