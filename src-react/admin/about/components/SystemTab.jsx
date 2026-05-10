import { __ } from '@wordpress/i18n';
import CopyValue from './CopyValue';

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
				<div key={ title } className="mhm-widget mhm-about-info-card">
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
										: row.copyable
											? <CopyValue value={ row.value } />
											: row.value }
								</dd>
							</div>
						) ) }
					</dl>
				</div>
			) ) }
		</div>
	);
}
