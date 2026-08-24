import { __ } from '@wordpress/i18n';

export default function SystemActions( { onClearCache, onDebug, onReset, debugLoading, query, onQueryChange } ) {
	return (
		<div className="rv-scp-toolbar">
			<button type="button" className="rv-scp-btn" onClick={ onClearCache }>
				{ __( 'Clear Cache', 'mhm-rentiva' ) }
			</button>
			<button type="button" className="rv-scp-btn" onClick={ onDebug } disabled={ debugLoading }>
				{ debugLoading
					? __( 'Scanning…', 'mhm-rentiva' )
					: __( 'Debug Search', 'mhm-rentiva' ) }
			</button>
			<button type="button" className="rv-scp-btn is-danger" onClick={ onReset }>
				{ __( 'Reset All', 'mhm-rentiva' ) }
			</button>
			<div className="rv-scp-search">
				<input
					type="search"
					placeholder={ __( 'Search shortcode or page…', 'mhm-rentiva' ) }
					value={ query }
					onChange={ ( e ) => onQueryChange( e.target.value ) }
				/>
			</div>
		</div>
	);
}
