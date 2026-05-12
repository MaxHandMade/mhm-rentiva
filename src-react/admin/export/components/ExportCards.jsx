import { __ } from '@wordpress/i18n';

const CARD_META = {
	vehicle_booking: {
		label: __( 'Bookings', 'mhm-rentiva' ),
		icon:  'dashicons-calendar-alt',
		desc:  __( 'All reservation records', 'mhm-rentiva' ),
	},
	vehicle: {
		label: __( 'Vehicles', 'mhm-rentiva' ),
		icon:  'dashicons-car',
		desc:  __( 'Vehicle listings and details', 'mhm-rentiva' ),
	},
	mhm_app_log: {
		label: __( 'App Logs', 'mhm-rentiva' ),
		icon:  'dashicons-text-page',
		desc:  __( 'Application event log', 'mhm-rentiva' ),
	},
};

export default function ExportCards( { activeType, postTypeLabels, onSelect } ) {
	const types = Object.keys( CARD_META );

	return (
		<div className="mhm-export-cards">
			{ types.map( ( type ) => {
				const meta  = CARD_META[ type ];
				const label = postTypeLabels[ type ] ?? meta.label;
				const isActive = activeType === type;

				return (
					<button
						key={ type }
						type="button"
						className={ `mhm-export-card${ isActive ? ' mhm-export-card--active' : '' }` }
						onClick={ () => onSelect( type ) }
						aria-pressed={ isActive }
					>
						<span className={ `dashicons ${ meta.icon } mhm-export-card__icon` } aria-hidden="true" />
						<span className="mhm-export-card__label">{ label }</span>
						<span className="mhm-export-card__desc">{ meta.desc }</span>
					</button>
				);
			} ) }
		</div>
	);
}
