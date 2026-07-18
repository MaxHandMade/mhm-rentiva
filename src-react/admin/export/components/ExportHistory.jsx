import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { rentivaApi } from '../../../shared/api/rentiva';

export default function ExportHistory() {
	const [ history, setHistory ]   = useState( [] );
	const [ loading, setLoading ]   = useState( true );
	const [ error, setError ]       = useState( '' );
	const [ deleting, setDeleting ] = useState( null );

	const loadHistory = useCallback( async () => {
		setLoading( true );
		setError( '' );
		try {
			const result = await rentivaApi.export.getHistory();
			setHistory( result.history ?? [] );
		} catch ( err ) {
			setError( err?.message ?? __( 'Failed to load export history.', 'mhm-rentiva' ) );
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		loadHistory();
	}, [ loadHistory ] );

	const handleDelete = useCallback( async ( id ) => {
		setDeleting( id );
		try {
			await rentivaApi.export.deleteEntry( id );
			setHistory( ( prev ) => prev.filter( ( e ) => ( e.id ?? e.date ) !== id ) );
		} catch ( err ) {
			setError( err?.message ?? __( 'Delete failed.', 'mhm-rentiva' ) );
		} finally {
			setDeleting( null );
		}
	}, [] );

	if ( loading ) {
		return (
			<div className="mhm-export-history">
				<p className="mhm-export-history__loading">{ __( 'Loading history…', 'mhm-rentiva' ) }</p>
			</div>
		);
	}

	return (
		<div className="mhm-export-history">
			<h3 className="mhm-export-history__heading">{ __( 'Export History', 'mhm-rentiva' ) }</h3>

			{ error && (
				<div className="mhm-export-notice mhm-export-notice--error">{ error }</div>
			) }

			{ history.length === 0 ? (
				<p className="mhm-export-history__empty">
					{ __( 'No exports recorded yet.', 'mhm-rentiva' ) }
				</p>
			) : (
				<table className="mhm-export-history__table widefat fixed striped">
					<thead>
						<tr>
							<th>{ __( 'Date', 'mhm-rentiva' ) }</th>
							<th>{ __( 'Type', 'mhm-rentiva' ) }</th>
							<th>{ __( 'Format', 'mhm-rentiva' ) }</th>
							<th>{ __( 'Records', 'mhm-rentiva' ) }</th>
							<th>{ __( 'User', 'mhm-rentiva' ) }</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						{ history.map( ( entry ) => {
							const entryId = entry.id ?? entry.date;
							return (
								<tr key={ entryId }>
									<td>{ entry.date }</td>
									<td>{ entry.post_type ?? '—' }</td>
									<td>{ entry.format ?? '—' }</td>
									<td>{ entry.count ?? '—' }</td>
									<td>{ entry.user ?? '—' }</td>
									<td>
										<button
											type="button"
											className="button button-link-delete"
											onClick={ () => handleDelete( entryId ) }
											disabled={ deleting === entryId }
											aria-label={ __( 'Delete entry', 'mhm-rentiva' ) }
										>
											{ deleting === entryId
												? __( 'Deleting…', 'mhm-rentiva' )
												: __( 'Delete', 'mhm-rentiva' )
											}
										</button>
									</td>
								</tr>
							);
						} ) }
					</tbody>
				</table>
			) }
		</div>
	);
}
