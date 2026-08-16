import { __ } from '@wordpress/i18n';
import StatusBadge from './StatusBadge';

export default function ShortcodeRow( { shortcode, pending, onCreate, onDelete } ) {
	const { slug, label, description, page_title, page_url, edit_url, status } = shortcode;
	return (
		<tr className={ pending ? 'rv-scp-row--pending' : '' }>
			<td>
				<code className="rv-scp-slug">[{ slug }]</code>
				<span className="rv-scp-label">{ label }</span>
				<span className="rv-scp-description">{ description }</span>
			</td>
			<td>
				{ page_url
					? <a href={ page_url } target="_blank" rel="noreferrer">{ page_title }</a>
					: <span className="rv-scp-no-page">—</span>
				}
			</td>
			<td>
				<StatusBadge status={ status } />
			</td>
			<td className="rv-scp-actions">
				{ pending && (
					<span className="spinner is-active" style={ { float: 'none', marginTop: 0 } } />
				) }
				{ ! pending && status === 'missing' && (
					<button
						type="button"
						className="rv-scp-btn is-primary"
						onClick={ () => onCreate( slug ) }
					>
						{ __( 'Create Page', 'mhm-rentiva' ) }
					</button>
				) }
				{ ! pending && status === 'active' && (
					<>
						<a className="rv-scp-btn" href={ edit_url } target="_blank" rel="noreferrer">
							{ __( 'Edit', 'mhm-rentiva' ) }
						</a>
						<a className="rv-scp-btn is-view" href={ page_url } target="_blank" rel="noreferrer">
							{ __( 'View', 'mhm-rentiva' ) }
						</a>
						<button
							type="button"
							className="rv-scp-btn is-danger"
							onClick={ () => onDelete( slug ) }
						>
							{ __( 'Remove', 'mhm-rentiva' ) }
						</button>
					</>
				) }
			</td>
		</tr>
	);
}
