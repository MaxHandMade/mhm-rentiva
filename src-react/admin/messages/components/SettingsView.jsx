import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const { settings, admin_url, nonces } = window.mhmRentivaMessages;
const OPTION = 'mhm_rentiva_messages_settings';

function field( key ) {
	return `${ OPTION }[${ key }]`;
}

export default function SettingsView( { onBack } ) {
	const params  = new URLSearchParams( window.location.search );
	const saved   = params.get( 'settings_saved' ) === '1';

	const [ tab,        setTab ]        = useState( 'email' );
	const [ categories, setCategories ] = useState( Object.entries( settings.categories ?? {} ) );
	const [ statuses,   setStatuses ]   = useState( Object.entries( settings.statuses   ?? {} ) );
	const [ newCat,     setNewCat ]     = useState( '' );
	const [ newStatus,  setNewStatus ]  = useState( '' );

	const addCategory = () => {
		if ( ! newCat.trim() ) return;
		const key = newCat.toLowerCase().replace( /\s+/g, '_' ).replace( /[^a-z0-9_]/g, '' );
		if ( categories.some( ( [ k ] ) => k === key ) ) return;
		setCategories( [ ...categories, [ key, newCat.trim() ] ] );
		setNewCat( '' );
	};

	const addStatus = () => {
		if ( ! newStatus.trim() ) return;
		const key = newStatus.toLowerCase().replace( /\s+/g, '_' ).replace( /[^a-z0-9_]/g, '' );
		if ( statuses.some( ( [ k ] ) => k === key ) ) return;
		setStatuses( [ ...statuses, [ key, newStatus.trim() ] ] );
		setNewStatus( '' );
	};

	return (
		<div className="mhm-settings-view">
			<div style={ { marginBottom: 12 } }>
				<button className="button" onClick={ onBack }>
					{ __( '← Back to Messages', 'mhm-rentiva' ) }
				</button>
			</div>

			{ saved && (
				<div className="notice notice-success inline">
					<p>{ __( 'Settings saved.', 'mhm-rentiva' ) }</p>
				</div>
			) }

			<nav className="nav-tab-wrapper">
				{ [ [ 'email', __( 'Email', 'mhm-rentiva' ) ],
				    [ 'categories', __( 'Categories', 'mhm-rentiva' ) ],
				    [ 'statuses', __( 'Statuses', 'mhm-rentiva' ) ],
				].map( ( [ id, label ] ) => (
					<a
						key={ id }
						href="#"
						className={ `nav-tab${ tab === id ? ' nav-tab-active' : '' }` }
						onClick={ ( e ) => { e.preventDefault(); setTab( id ); } }
					>
						{ label }
					</a>
				) ) }
			</nav>

			<form method="POST" action={ `${ admin_url }admin-post.php` }>
				<input type="hidden" name="action" value="mhm_rentiva_save_messages_settings" />
				<input type="hidden" name="nonce"  value={ nonces.settings } />

				<div className="mhm-widget mhm-tab-content">
				{ /* Email tab */ }
				{ tab === 'email' && (
					<table className="form-table">
						<tbody>
							<tr>
								<th>{ __( 'Admin Email', 'mhm-rentiva' ) }</th>
								<td>
									<input
										type="email"
										name={ field( 'admin_email' ) }
										defaultValue={ settings.admin_email ?? '' }
										className="regular-text"
									/>
								</td>
							</tr>
							<tr>
								<th>{ __( 'From Name', 'mhm-rentiva' ) }</th>
								<td>
									<input
										type="text"
										name={ field( 'from_name' ) }
										defaultValue={ settings.from_name ?? '' }
										className="regular-text"
									/>
								</td>
							</tr>
							<tr>
								<th>{ __( 'From Email', 'mhm-rentiva' ) }</th>
								<td>
									<input
										type="email"
										name={ field( 'from_email' ) }
										defaultValue={ settings.from_email ?? '' }
										className="regular-text"
									/>
								</td>
							</tr>
							<tr>
								<th>{ __( 'Notifications', 'mhm-rentiva' ) }</th>
								<td>
									{ [
										[ 'email_admin_notifications',    __( 'Notify admin on new message', 'mhm-rentiva' ) ],
										[ 'email_customer_notifications', __( 'Notify customer on reply', 'mhm-rentiva' ) ],
										[ 'email_reply_notifications',    __( 'Notify on admin reply', 'mhm-rentiva' ) ],
										[ 'email_status_change_notifications', __( 'Notify on status change', 'mhm-rentiva' ) ],
									].map( ( [ key, label ] ) => (
										<label key={ key } style={ { display: 'block', marginBottom: 4 } }>
											<input
												type="checkbox"
												name={ field( key ) }
												value="1"
												defaultChecked={ !! settings[ key ] }
											/>
											{ ' ' }{ label }
										</label>
									) ) }
								</td>
							</tr>
						</tbody>
					</table>
				) }

				{ /* Categories tab */ }
				{ tab === 'categories' && (
					<div>
						{ categories.map( ( [ key, name ], idx ) => (
							<div key={ key } className="mhm-category-item">
								<input
									type="text"
									name={ `${ OPTION }[categories][${ key }]` }
									value={ name }
									onChange={ ( e ) => {
										const next = [ ...categories ];
										next[ idx ] = [ key, e.target.value ];
										setCategories( next );
									} }
									className="regular-text"
								/>
								<button
									type="button"
									className="button button-link-delete"
									onClick={ () => setCategories( categories.filter( ( _, i ) => i !== idx ) ) }
								>
									{ __( 'Remove', 'mhm-rentiva' ) }
								</button>
							</div>
						) ) }
						<div className="mhm-add-item-row">
							<input
								type="text"
								value={ newCat }
								onChange={ ( e ) => setNewCat( e.target.value ) }
								placeholder={ __( 'New category name', 'mhm-rentiva' ) }
								className="regular-text"
							/>
							<button type="button" className="button" onClick={ addCategory }>
								{ __( 'Add', 'mhm-rentiva' ) }
							</button>
						</div>
					</div>
				) }

				{ /* Statuses tab */ }
				{ tab === 'statuses' && (
					<div>
						{ statuses.map( ( [ key, name ], idx ) => (
							<div key={ key } className="mhm-status-item">
								<input
									type="text"
									name={ `${ OPTION }[statuses][${ key }]` }
									value={ name }
									onChange={ ( e ) => {
										const next = [ ...statuses ];
										next[ idx ] = [ key, e.target.value ];
										setStatuses( next );
									} }
									className="regular-text"
								/>
								<button
									type="button"
									className="button button-link-delete"
									onClick={ () => setStatuses( statuses.filter( ( _, i ) => i !== idx ) ) }
								>
									{ __( 'Remove', 'mhm-rentiva' ) }
								</button>
							</div>
						) ) }
						<div className="mhm-add-item-row">
							<input
								type="text"
								value={ newStatus }
								onChange={ ( e ) => setNewStatus( e.target.value ) }
								placeholder={ __( 'New status name', 'mhm-rentiva' ) }
								className="regular-text"
							/>
							<button type="button" className="button" onClick={ addStatus }>
								{ __( 'Add', 'mhm-rentiva' ) }
							</button>
						</div>
					</div>
				) }

				<p className="submit">
					<button type="submit" className="button button-primary">
						{ __( 'Save Settings', 'mhm-rentiva' ) }
					</button>
				</p>
			</div>
			</form>
		</div>
	);
}
