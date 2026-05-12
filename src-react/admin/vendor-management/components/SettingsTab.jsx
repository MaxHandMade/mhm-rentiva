import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { rentivaApi } from '../../../shared/api/rentiva';

const DEFAULTS = {
	payout_freeze: false,
	min_payout:    100,
	min_photos:    4,
	max_photos:    8,
	doc_max_mb:    5,
	min_year:      1990,
	bio_max_chars: 400,
	service_cities: [],
};

export default function SettingsTab( { onNotice } ) {
	const [ form,    setForm    ] = useState( DEFAULTS );
	const [ loading, setLoading ] = useState( true );
	const [ saving,  setSaving  ] = useState( false );

	useEffect( () => {
		rentivaApi.vendorManagement.getSettings()
			.then( ( res ) => {
				setForm( {
					payout_freeze:  !! res.payout_freeze,
					min_payout:     res.min_payout    ?? DEFAULTS.min_payout,
					min_photos:     res.min_photos    ?? DEFAULTS.min_photos,
					max_photos:     res.max_photos    ?? DEFAULTS.max_photos,
					doc_max_mb:     res.doc_max_mb    ?? DEFAULTS.doc_max_mb,
					min_year:       res.min_year      ?? DEFAULTS.min_year,
					bio_max_chars:  res.bio_max_chars ?? DEFAULTS.bio_max_chars,
					service_cities: Array.isArray( res.service_cities ) ? res.service_cities : DEFAULTS.service_cities,
				} );
				setLoading( false );
			} )
			.catch( () => {
				onNotice( { type: 'error', message: __( 'Failed to load settings.', 'mhm-rentiva' ) } );
				setLoading( false );
			} );
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	const set = ( key, val ) => setForm( ( prev ) => ( { ...prev, [ key ]: val } ) );

	const handleSave = ( e ) => {
		e.preventDefault();
		setSaving( true );
		// Convert service_cities textarea string to array.
		const payload = {
			...form,
			service_cities: typeof form.service_cities === 'string'
				? form.service_cities.split( '\n' ).map( s => s.trim() ).filter( Boolean )
				: form.service_cities,
		};
		rentivaApi.vendorManagement.saveSettings( payload )
			.then( () => {
				onNotice( { type: 'success', message: __( 'Settings saved.', 'mhm-rentiva' ) } );
				setSaving( false );
			} )
			.catch( () => {
				onNotice( { type: 'error', message: __( 'Failed to save settings.', 'mhm-rentiva' ) } );
				setSaving( false );
			} );
	};

	if ( loading ) return <p>{ __( 'Loading…', 'mhm-rentiva' ) }</p>;

	// service_cities display: join array to textarea string.
	const citiesText = Array.isArray( form.service_cities )
		? form.service_cities.join( '\n' )
		: form.service_cities;

	return (
		<div className="mhm-vm-settings-tab" style={ { maxWidth: '800px' } }>
			<h2>{ __( 'Vendor Marketplace Settings', 'mhm-rentiva' ) }</h2>
			<form onSubmit={ handleSave }>
				<table className="form-table">
					<tbody>
						<tr>
							<th>{ __( 'Global Payout Freeze', 'mhm-rentiva' ) }</th>
							<td>
								<label>
									<input
										type="checkbox"
										checked={ form.payout_freeze }
										onChange={ ( e ) => set( 'payout_freeze', e.target.checked ) }
									/>
									{ ' ' }{ __( 'Freeze all vendor payout requests site-wide', 'mhm-rentiva' ) }
								</label>
							</td>
						</tr>
						<tr>
							<th><label htmlFor="mhm-min-payout">{ __( 'Minimum Payout Amount', 'mhm-rentiva' ) }</label></th>
							<td>
								<input id="mhm-min-payout" type="number" min="0" step="1" style={ { width: '120px' } }
									value={ form.min_payout }
									onChange={ ( e ) => set( 'min_payout', parseFloat( e.target.value ) ) }
								/>
								<p className="description">{ __( 'Vendors must have at least this balance to request a payout.', 'mhm-rentiva' ) }</p>
							</td>
						</tr>
						<tr>
							<th><label htmlFor="mhm-min-photos">{ __( 'Min Vehicle Photos', 'mhm-rentiva' ) }</label></th>
							<td>
								<input id="mhm-min-photos" type="number" min="1" max="20" style={ { width: '80px' } }
									value={ form.min_photos }
									onChange={ ( e ) => set( 'min_photos', parseInt( e.target.value, 10 ) ) }
								/>
							</td>
						</tr>
						<tr>
							<th><label htmlFor="mhm-max-photos">{ __( 'Max Vehicle Photos', 'mhm-rentiva' ) }</label></th>
							<td>
								<input id="mhm-max-photos" type="number" min="1" max="30" style={ { width: '80px' } }
									value={ form.max_photos }
									onChange={ ( e ) => set( 'max_photos', parseInt( e.target.value, 10 ) ) }
								/>
							</td>
						</tr>
						<tr>
							<th><label htmlFor="mhm-doc-mb">{ __( 'Document Upload Limit (MB)', 'mhm-rentiva' ) }</label></th>
							<td>
								<input id="mhm-doc-mb" type="number" min="1" max="50" style={ { width: '80px' } }
									value={ form.doc_max_mb }
									onChange={ ( e ) => set( 'doc_max_mb', parseInt( e.target.value, 10 ) ) }
								/>
							</td>
						</tr>
						<tr>
							<th><label htmlFor="mhm-min-year">{ __( 'Minimum Vehicle Year', 'mhm-rentiva' ) }</label></th>
							<td>
								<input id="mhm-min-year" type="number" min="1900" max={ new Date().getFullYear() } style={ { width: '100px' } }
									value={ form.min_year }
									onChange={ ( e ) => set( 'min_year', parseInt( e.target.value, 10 ) ) }
								/>
							</td>
						</tr>
						<tr>
							<th><label htmlFor="mhm-bio-max">{ __( 'Vendor Bio Max Characters', 'mhm-rentiva' ) }</label></th>
							<td>
								<input id="mhm-bio-max" type="number" min="50" max="2000" style={ { width: '100px' } }
									value={ form.bio_max_chars }
									onChange={ ( e ) => set( 'bio_max_chars', parseInt( e.target.value, 10 ) ) }
								/>
							</td>
						</tr>
						<tr>
							<th><label htmlFor="mhm-service-cities">{ __( 'Service Area Cities', 'mhm-rentiva' ) }</label></th>
							<td>
								<textarea
									id="mhm-service-cities"
									rows="10"
									style={ { width: '320px', fontFamily: 'monospace' } }
									value={ citiesText }
									onChange={ ( e ) => set( 'service_cities', e.target.value ) }
								/>
								<p className="description">{ __( 'One city per line.', 'mhm-rentiva' ) }</p>
							</td>
						</tr>
					</tbody>
				</table>
				<p className="submit">
					<button type="submit" className="button button-primary" disabled={ saving }>
						{ saving ? __( 'Saving…', 'mhm-rentiva' ) : __( 'Save Settings', 'mhm-rentiva' ) }
					</button>
				</p>
			</form>
		</div>
	);
}
