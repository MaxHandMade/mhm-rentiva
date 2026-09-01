import { __, sprintf } from '@wordpress/i18n';

const VIA_LABELS = {
	shortcode: __( 'shortcode', 'mhm-rentiva' ),
	block:     __( 'block', 'mhm-rentiva' ),
	widget:    __( 'widget', 'mhm-rentiva' ),
};

export default function DebugPanel( { data, onClose } ) {
	if ( ! data ) {
		return null;
	}
	return (
		<div className="mhm-widget mhm-sc-debug-panel">
			<div className="mhm-sc-debug-header">
				<h3>{ __( 'Debug Search Results', 'mhm-rentiva' ) }</h3>
				<button className="rv-scp-btn" onClick={ onClose }>
					{ __( 'Close', 'mhm-rentiva' ) }
				</button>
			</div>
			<p className="mhm-sc-debug-meta">
				{ // translators: %d: number of pages scanned
			sprintf( __( 'Scanned %d pages.', 'mhm-rentiva' ), data.scanned_pages ) }
			</p>
			<table className="rv-scp-table rv-scp-debug-table">
				<thead>
					<tr>
						<th>{ __( 'Shortcode', 'mhm-rentiva' ) }</th>
						<th>{ __( 'Found In', 'mhm-rentiva' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ data.results.map( ( r ) => (
						<tr key={ r.slug } className={ r.found_in.length > 0 ? 'mhm-debug-found' : '' }>
							<td>
								<code className="rv-scp-slug">[{ r.slug }]</code>
								<span className="rv-scp-label"> { r.label }</span>
							</td>
							<td>
								{ r.found_in.length === 0
									? <em className="rv-scp-no-page">{ __( 'Not found', 'mhm-rentiva' ) }</em>
									: r.found_in.map( ( p ) => (
										<span key={ p.page_id } className="rv-scp-debug-hit">
											<a href={ p.page_url } target="_blank" rel="noreferrer">
												{ p.page_title }
											</a>
											{ ( p.via ?? [] ).map( ( v ) => (
												<span key={ v } className={ `rv-scp-via is-${ v }` }>
													{ VIA_LABELS[ v ] ?? v }
												</span>
											) ) }
										</span>
									) )
								}
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}
