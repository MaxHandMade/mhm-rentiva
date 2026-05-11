import { __, sprintf } from '@wordpress/i18n';

export default function DebugPanel( { data, onClose } ) {
	if ( ! data ) return null;
	return (
		<div className="mhm-widget mhm-sc-debug-panel">
			<div className="mhm-sc-debug-header">
				<h3>{ __( 'Debug Search Results', 'mhm-rentiva' ) }</h3>
				<button className="button button-small" onClick={ onClose }>
					{ __( 'Close', 'mhm-rentiva' ) }
				</button>
			</div>
			<p className="mhm-sc-debug-meta">
				{ // translators: %d: number of pages scanned
			sprintf( __( 'Scanned %d pages.', 'mhm-rentiva' ), data.scanned_pages ) }
			</p>
			<table className="wp-list-table widefat fixed striped">
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
								<code>[ { r.slug } ]</code>
								<span className="mhm-sc-label"> { r.label }</span>
							</td>
							<td>
								{ r.found_in.length === 0
									? <em>{ __( 'Not found', 'mhm-rentiva' ) }</em>
									: r.found_in.map( ( p, i ) => (
										<span key={ p.page_id }>
											<a href={ p.page_url } target="_blank" rel="noreferrer">
												{ p.page_title }
											</a>
											{ i < r.found_in.length - 1 ? ', ' : '' }
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
