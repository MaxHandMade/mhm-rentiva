import { __ } from '@wordpress/i18n';

export default function SystemActions( { onClearCache, onDebug, onReset, debugLoading } ) {
	return (
		<div className="mhm-sc-system-actions">
			<button className="button button-secondary" onClick={ onClearCache }>
				{ __( 'Clear Cache', 'mhm-rentiva' ) }
			</button>
			<button className="button button-secondary" onClick={ onDebug } disabled={ debugLoading }>
				{ debugLoading
					? __( 'Scanning…', 'mhm-rentiva' )
					: __( 'Debug Search', 'mhm-rentiva' ) }
			</button>
			<button className="button mhm-btn-danger" onClick={ onReset }>
				{ __( 'Reset All', 'mhm-rentiva' ) }
			</button>
		</div>
	);
}
