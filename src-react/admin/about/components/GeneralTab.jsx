import { __ } from '@wordpress/i18n';
import InfoCard from './InfoCard';

export default function GeneralTab( { data } ) {
	return (
		<div className="mhm-about-general mhm-about-cards-grid">
			<InfoCard title={ __( 'Plugin Information', 'mhm-rentiva' ) } rows={ data.plugin_info } />
			<InfoCard title={ __( 'Compatibility', 'mhm-rentiva' ) }      rows={ data.compatibility } />
			<InfoCard title={ __( 'Statistics', 'mhm-rentiva' ) }         rows={ data.stats } />
		</div>
	);
}
