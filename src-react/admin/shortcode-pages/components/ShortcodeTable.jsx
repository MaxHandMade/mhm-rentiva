import { __ } from '@wordpress/i18n';
import ShortcodeRow from './ShortcodeRow';

export default function ShortcodeTable( { shortcodes, pendingSlugs, onCreate, onDelete } ) {
	return (
		<table className="wp-list-table widefat fixed striped mhm-sc-table">
			<thead>
				<tr>
					<th>{ __( 'Shortcode', 'mhm-rentiva' ) }</th>
					<th>{ __( 'Page', 'mhm-rentiva' ) }</th>
					<th>{ __( 'Status', 'mhm-rentiva' ) }</th>
					<th>{ __( 'Actions', 'mhm-rentiva' ) }</th>
				</tr>
			</thead>
			<tbody>
				{ shortcodes.map( ( sc ) => (
					<ShortcodeRow
						key={ sc.slug }
						shortcode={ sc }
						pending={ pendingSlugs.has( sc.slug ) }
						onCreate={ onCreate }
						onDelete={ onDelete }
					/>
				) ) }
			</tbody>
		</table>
	);
}
