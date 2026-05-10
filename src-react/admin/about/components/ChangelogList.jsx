import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export default function ChangelogList( { items } ) {
	const [ expanded, setExpanded ] = useState(
		() => new Set(
			items.filter( ( r ) => r.type === 'current' ).map( ( r ) => r.version )
		)
	);

	const toggle = ( version ) => {
		setExpanded( ( prev ) => {
			const next = new Set( prev );
			next.has( version ) ? next.delete( version ) : next.add( version );
			return next;
		} );
	};

	return (
		<div className="mhm-changelog-list">
			{ items.map( ( release ) => {
				const isOpen = expanded.has( release.version );
				return (
					<div
						key={ release.version }
						className={ `mhm-changelog-item${ release.type === 'current' ? ' mhm-changelog-current' : '' }` }
					>
						<div
							className="mhm-changelog-header"
							onClick={ () => toggle( release.version ) }
							role="button"
							tabIndex={ 0 }
							onKeyDown={ ( e ) => e.key === 'Enter' && toggle( release.version ) }
						>
							<div className="mhm-changelog-version-info">
								<strong>v{ release.version }</strong>
								<span className="mhm-changelog-date">{ release.date }</span>
								{ release.type === 'current' && (
									<span className="mhm-current-badge">
										{ __( 'Current Version', 'mhm-rentiva' ) }
									</span>
								) }
							</div>
							<span className={ `dashicons ${ isOpen ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2' }` } />
						</div>
						{ isOpen && (
							<div className="mhm-changelog-content">
								<ul>
									{ release.changes.map( ( change, i ) => (
										<li key={ i }>{ change }</li>
									) ) }
								</ul>
							</div>
						) }
					</div>
				);
			} ) }
		</div>
	);
}
