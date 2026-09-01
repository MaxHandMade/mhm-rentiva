import { __ } from '@wordpress/i18n';
import CopyValue from './CopyValue';

/**
 * One row's display value. Three shapes (boolean, copyable, plain) read better
 * as guards than as a nested ternary.
 *
 * @param {Object} row Row descriptor from the system report.
 * @return {JSX.Element|string} Node or text to render.
 */
function renderValue( row ) {
	if ( row.boolean ) {
		return row.value
			? __( 'Yes', 'mhm-rentiva' )
			: __( 'No', 'mhm-rentiva' );
	}
	if ( row.copyable ) {
		return <CopyValue value={ row.value } />;
	}
	return `${ row.value }${ row.suffix ?? '' }`;
}

export default function SystemTab( { data } ) {
	const sections = [
		[ __( 'WordPress Information', 'mhm-rentiva' ), data.wordpress ],
		[ __( 'PHP Information', 'mhm-rentiva' ),       data.php       ],
		[ __( 'Plugin Information', 'mhm-rentiva' ),    data.plugin    ],
		[ __( 'Database Information', 'mhm-rentiva' ),  data.database  ],
	];

	return (
		<div className="mhm-about-system mhm-about-cards-grid">
			{ sections.map( ( [ title, rows ] ) => (
				<div key={ title } className="mhm-widget rv-abt-card mhm-about-info-card">
					<h3>{ title }</h3>
					<dl>
						{ rows.map( ( row, i ) => (
							<div key={ i } className="mhm-info-row">
								<dt>{ row.label }</dt>
								<dd>
									{ renderValue( row ) }
								</dd>
							</div>
						) ) }
					</dl>
				</div>
			) ) }
		</div>
	);
}
