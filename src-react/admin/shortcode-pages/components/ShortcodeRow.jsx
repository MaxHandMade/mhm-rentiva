import { __ } from '@wordpress/i18n';
import StatusBadge from './StatusBadge';

export default function ShortcodeRow( { shortcode, pending, onCreate, onDelete } ) {
	const { slug, label, description, page_title, page_url, edit_url, status } = shortcode;
	return (
		<tr className={ pending ? 'mhm-sc-row--pending' : '' }>
			<td>
				<code className="mhm-sc-slug">[ { slug } ]</code>
				<span className="mhm-sc-label">{ label }</span>
				<span className="mhm-sc-description">{ description }</span>
			</td>
			<td>
				{ page_url
					? <a href={ page_url } target="_blank" rel="noreferrer">{ page_title }</a>
					: <span className="mhm-sc-no-page">—</span>
				}
			</td>
			<td>
				<StatusBadge status={ status } />
			</td>
			<td className="mhm-sc-actions">
				{ pending && (
					<span className="spinner is-active" style={ { float: 'none', marginTop: 0 } } />
				) }
				{ ! pending && status === 'missing' && (
					<button
						className="button button-small button-primary"
						onClick={ () => onCreate( slug ) }
					>
						{ __( 'Create Page', 'mhm-rentiva' ) }
					</button>
				) }
				{ ! pending && status === 'active' && (
					<>
						<a className="button button-small" href={ edit_url } target="_blank" rel="noreferrer">
							{ __( 'Edit', 'mhm-rentiva' ) }
						</a>
						<a className="button button-small" href={ page_url } target="_blank" rel="noreferrer">
							{ __( 'View', 'mhm-rentiva' ) }
						</a>
						<button
							className="button button-small mhm-btn-danger-outline"
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
