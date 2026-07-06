import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { rentivaApi } from '../../../shared/api/rentiva';

export default function CommissionTab( { onNotice } ) {
	const [ data,    setData    ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ rate,    setRate    ] = useState( '' );
	const [ label,   setLabel   ] = useState( '' );
	const [ saving,  setSaving  ] = useState( false );

	const fetchCommission = () => {
		setLoading( true );
		rentivaApi.vendorManagement.getCommission()
			.then( ( res ) => { setData( res ); setLoading( false ); } )
			.catch( () => {
				onNotice( { type: 'error', message: __( 'Failed to load commission data.', 'mhm-rentiva' ) } );
				setLoading( false );
			} );
	};

	const [ tiers,      setTiers      ] = useState( [] );
	const [ tiersSaving, setTiersSaving ] = useState( false );

	const fetchTiers = () => {
		rentivaApi.vendorManagement.getCommissionTiers()
			.then( ( res ) => setTiers( res.tiers ) )
			.catch( () => onNotice( { type: 'error', message: __( 'Failed to load commission tiers.', 'mhm-rentiva' ) } ) );
	};

	useEffect( () => { fetchCommission(); fetchTiers(); }, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	const handleSave = ( e ) => {
		e.preventDefault();
		const parsed = parseFloat( rate );
		if ( isNaN( parsed ) || parsed < 0 || parsed > 100 ) {
			onNotice( { type: 'error', message: __( 'Rate must be between 0 and 100.', 'mhm-rentiva' ) } );
			return;
		}
		setSaving( true );
		rentivaApi.vendorManagement.saveCommission( { global_rate: parsed, policy_label: label } )
			.then( () => {
				onNotice( { type: 'success', message: __( 'Commission rate updated.', 'mhm-rentiva' ) } );
				setRate( '' );
				setLabel( '' );
				fetchCommission();
				setSaving( false );
			} )
			.catch( () => {
				onNotice( { type: 'error', message: __( 'Failed to save commission rate.', 'mhm-rentiva' ) } );
				setSaving( false );
			} );
	};

	const updateTierField = ( index, field, value ) => {
		setTiers( ( prev ) => prev.map( ( t, i ) => ( i === index ? { ...t, [ field ]: value } : t ) ) );
	};

	const handleSaveTiers = () => {
		const parsed = tiers.map( ( t ) => ( { threshold: parseFloat( t.threshold ), discount: parseFloat( t.discount ) } ) );
		if ( parsed.some( ( t ) => isNaN( t.threshold ) || isNaN( t.discount ) || t.threshold < 0 || t.discount < 0 || t.discount > 100 ) ) {
			onNotice( { type: 'error', message: __( 'All thresholds and discounts must be valid, non-negative numbers (discount max 100).', 'mhm-rentiva' ) } );
			return;
		}
		setTiersSaving( true );
		rentivaApi.vendorManagement.saveCommissionTiers( parsed )
			.then( () => {
				onNotice( { type: 'success', message: __( 'Commission tiers updated.', 'mhm-rentiva' ) } );
				fetchTiers();
			} )
			.catch( () => onNotice( { type: 'error', message: __( 'Failed to save commission tiers.', 'mhm-rentiva' ) } ) )
			.finally( () => setTiersSaving( false ) );
	};

	if ( loading ) return <p>{ __( 'Loading…', 'mhm-rentiva' ) }</p>;

	return (
		<div className="mhm-vm-commission-tab" style={ { maxWidth: '700px' } }>
			<h2>{ __( 'Platform Commission Rate', 'mhm-rentiva' ) }</h2>

			{ data?.current_rate != null ? (
				<p>
					<strong>{ __( 'Current active rate:', 'mhm-rentiva' ) }</strong>{ ' ' }
					<span style={ { fontSize: '1.4em', fontWeight: 700, color: '#1d4ed8' } }>
						{ parseFloat( data.current_rate ).toFixed( 2 ) }%
					</span>
				</p>
			) : (
				<div className="notice notice-warning inline">
					<p>{ __( 'No active commission policy found. Set one below.', 'mhm-rentiva' ) }</p>
				</div>
			) }

			<p>{ __( 'Setting a new rate creates a policy record effective immediately. Previous rates are preserved for audit.', 'mhm-rentiva' ) }</p>

			<form onSubmit={ handleSave } style={ { marginTop: '20px' } }>
				<table className="form-table">
					<tbody>
						<tr>
							<th><label htmlFor="mhm-commission-rate">{ __( 'New Commission Rate (%)', 'mhm-rentiva' ) }</label></th>
							<td>
								<input
									id="mhm-commission-rate"
									type="number"
									min="0" max="100" step="0.01"
									style={ { width: '100px' } }
									placeholder="15.00"
									value={ rate }
									onChange={ ( e ) => setRate( e.target.value ) }
									required
								/>
								<span className="description"> %</span>
							</td>
						</tr>
						<tr>
							<th><label htmlFor="mhm-commission-label">{ __( 'Label (optional)', 'mhm-rentiva' ) }</label></th>
							<td>
								<input
									id="mhm-commission-label"
									type="text"
									style={ { width: '280px' } }
									placeholder={ __( 'e.g. Q1 2026 standard rate', 'mhm-rentiva' ) }
									value={ label }
									onChange={ ( e ) => setLabel( e.target.value ) }
								/>
							</td>
						</tr>
					</tbody>
				</table>
				<p className="submit">
					<button type="submit" className="button button-primary" disabled={ saving || rate === '' }>
						{ saving ? __( 'Saving…', 'mhm-rentiva' ) : __( 'Save Commission Rate', 'mhm-rentiva' ) }
					</button>
				</p>
			</form>

			{ data?.history?.length > 0 && (
				<>
					<h3 style={ { marginTop: '2em' } }>{ __( 'Rate History', 'mhm-rentiva' ) }</h3>
					<table className="widefat fixed striped" style={ { maxWidth: '600px' } }>
						<thead>
							<tr>
								<th>{ __( 'Effective From', 'mhm-rentiva' ) }</th>
								<th style={ { width: '80px' } }>{ __( 'Rate', 'mhm-rentiva' ) }</th>
								<th>{ __( 'Label', 'mhm-rentiva' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ data.history.map( ( p, i ) => (
								<tr key={ i }>
									<td>{ p.effective_from }</td>
									<td>{ parseFloat( p.rate ).toFixed( 2 ) }%</td>
									<td>{ p.label || '—' }</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</>
			) }

			<h2 style={ { marginTop: '2.5em' } }>{ __( 'Volume Discount Tiers', 'mhm-rentiva' ) }</h2>
			<p>{ __( '30-day net cleared revenue thresholds that reduce the effective rate for vendors without a manual override.', 'mhm-rentiva' ) }</p>
			<table className="widefat fixed striped" style={ { maxWidth: '500px' } }>
				<thead>
					<tr>
						<th>{ __( '30-Day Revenue Threshold', 'mhm-rentiva' ) }</th>
						<th>{ __( 'Discount (points)', 'mhm-rentiva' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ tiers.map( ( t, i ) => (
						<tr key={ i }>
							<td>
								<input
									type="number" min="0" step="1"
									style={ { width: '140px' } }
									value={ t.threshold }
									onChange={ ( e ) => updateTierField( i, 'threshold', e.target.value ) }
								/>
							</td>
							<td>
								<input
									type="number" min="0" max="100" step="0.1"
									style={ { width: '90px' } }
									value={ t.discount }
									onChange={ ( e ) => updateTierField( i, 'discount', e.target.value ) }
								/>
								<span> %</span>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
			<p className="submit">
				<button type="button" className="button button-primary" onClick={ handleSaveTiers } disabled={ tiersSaving }>
					{ tiersSaving ? __( 'Saving…', 'mhm-rentiva' ) : __( 'Save Tiers', 'mhm-rentiva' ) }
				</button>
			</p>
		</div>
	);
}
