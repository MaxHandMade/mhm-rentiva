import { __ } from '@wordpress/i18n';

export default function InfoCard( { title, rows } ) {
	return (
		<div className="mhm-widget mhm-about-info-card">
			<h3>{ title }</h3>
			<dl>
				{ rows.map( ( row, i ) => (
					<div key={ i } className="mhm-info-row">
						<dt>{ row.label }</dt>
						<dd>
							{ row.boolean
								? ( row.value
									? __( 'Yes', 'mhm-rentiva' )
									: __( 'No', 'mhm-rentiva' ) )
								: `${ row.value }${ row.suffix ?? '' }` }
						</dd>
					</div>
				) ) }
			</dl>
		</div>
	);
}
