import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { rentivaApi } from '../../../shared/api/rentiva';

const STATUS_COLORS = { active: '#2e7d32', suspended: '#c62828' };

const LIFECYCLE_LABELS = () => ( {
	active:         __( 'Active', 'mhm-rentiva' ),
	paused:         __( 'Paused', 'mhm-rentiva' ),
	expired:        __( 'Expired', 'mhm-rentiva' ),
	withdrawn:      __( 'Withdrawn', 'mhm-rentiva' ),
	pending_review: __( 'Pending Review', 'mhm-rentiva' ),
} );

const POST_STATUS_LABELS = () => ( {
	publish: __( 'Published', 'mhm-rentiva' ),
	pending: __( 'Pending', 'mhm-rentiva' ),
	draft:   __( 'Draft', 'mhm-rentiva' ),
	private: __( 'Private', 'mhm-rentiva' ),
} );

const PAYOUT_STATUS_LABELS = () => ( {
	pending:  __( 'Pending', 'mhm-rentiva' ),
	approved: __( 'Approved', 'mhm-rentiva' ),
	rejected: __( 'Rejected', 'mhm-rentiva' ),
} );

const EVENT_LABELS = () => ( {
	pause:    __( 'Paused', 'mhm-rentiva' ),
	withdraw: __( 'Withdrawn', 'mhm-rentiva' ),
	cancel:   __( 'Booking cancelled', 'mhm-rentiva' ),
	complete: __( 'Booking completed', 'mhm-rentiva' ),
	cron:     __( 'Scheduled recalculation', 'mhm-rentiva' ),
} );

const fmtAmount = ( n ) => Number( n || 0 ).toLocaleString( undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 } );

export default function VendorDetailPage( { vendorId, onBack } ) {
	const [ vendor,  setVendor  ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error,   setError   ] = useState( null );
	const [ cityValue,  setCityValue  ] = useState( '' );
	const [ citySaving, setCitySaving ] = useState( false );
	const [ cityMsg,    setCityMsg    ] = useState( null );
	const [ rateValue,  setRateValue  ] = useState( '' );
	const [ rateSaving, setRateSaving ] = useState( false );
	const [ rateMsg,    setRateMsg    ] = useState( null );

	const cities = ( window.mhmRentivaVendorManagement || {} ).cities || [];

	useEffect( () => {
		let active = true;
		setLoading( true );
		setError( null );
		setCityMsg( null );
		rentivaApi.vendorManagement.getVendorDetail( vendorId )
			.then( ( res ) => { if ( active ) { setVendor( res.vendor ); setCityValue( res.vendor.city || '' ); setRateValue( res.vendor.commission_rate != null ? String( res.vendor.commission_rate ) : '' ); setLoading( false ); } } )
			.catch( () => { if ( active ) { setError( __( 'Failed to load vendor.', 'mhm-rentiva' ) ); setLoading( false ); } } );
		return () => { active = false; };
	}, [ vendorId ] );

	const saveCity = () => {
		setCitySaving( true );
		setCityMsg( null );
		rentivaApi.vendorManagement.updateVendorCity( vendorId, cityValue )
			.then( ( res ) => {
				setVendor( ( v ) => ( { ...v, city: res.city } ) );
				setCityMsg( { type: 'success', text: __( 'City updated.', 'mhm-rentiva' ) } );
			} )
			.catch( () => setCityMsg( { type: 'error', text: __( 'Could not update the city.', 'mhm-rentiva' ) } ) )
			.finally( () => setCitySaving( false ) );
	};

	const saveRate = () => {
		setRateSaving( true );
		setRateMsg( null );
		const parsed = rateValue === '' ? null : parseFloat( rateValue );
		if ( parsed !== null && ( isNaN( parsed ) || parsed < 0 || parsed > 100 ) ) {
			setRateMsg( { type: 'error', text: __( 'Rate must be between 0 and 100.', 'mhm-rentiva' ) } );
			setRateSaving( false );
			return;
		}
		rentivaApi.vendorManagement.updateVendorCommissionRate( vendorId, parsed )
			.then( ( res ) => {
				setVendor( ( v ) => ( { ...v, commission_rate: res.rate } ) );
				setRateMsg( { type: 'success', text: __( 'Commission rate updated.', 'mhm-rentiva' ) } );
			} )
			.catch( () => setRateMsg( { type: 'error', text: __( 'Could not update the commission rate.', 'mhm-rentiva' ) } ) )
			.finally( () => setRateSaving( false ) );
	};

	const lifecycleLabels    = LIFECYCLE_LABELS();
	const postStatusLabels   = POST_STATUS_LABELS();
	const payoutStatusLabels = PAYOUT_STATUS_LABELS();
	const eventLabels        = EVENT_LABELS();
	const payoutsUrl         = ( window.mhmRentivaVendorManagement || {} ).payoutsUrl || '';

	return (
		<div className="mhm-vm-vendor-detail">
			<p>
				<button type="button" className="button" onClick={ onBack }>
					{ __( '← Back to vendors', 'mhm-rentiva' ) }
				</button>
			</p>

			{ loading && <p>{ __( 'Loading…', 'mhm-rentiva' ) }</p> }
			{ error && <div className="notice notice-error"><p>{ error }</p></div> }

			{ ! loading && ! error && vendor && (
				<>
					<h2 style={ { display: 'flex', alignItems: 'center', gap: '12px', marginTop: 0 } }>
						{ vendor.display_name }
						<span style={ {
							fontSize: '13px',
							fontWeight: 600,
							color: STATUS_COLORS[ vendor.status ] || '#555',
						} }>
							{ vendor.status === 'suspended'
								? __( 'Suspended', 'mhm-rentiva' )
								: __( 'Active', 'mhm-rentiva' ) }
						</span>
					</h2>

					<table className="widefat striped" style={ { maxWidth: '640px', marginBottom: '24px' } }>
						<tbody>
							<tr><th style={ { width: '180px' } }>{ __( 'Email', 'mhm-rentiva' ) }</th><td>{ vendor.email || '—' }</td></tr>
							<tr><th>{ __( 'Phone', 'mhm-rentiva' ) }</th><td>{ vendor.phone || '—' }</td></tr>
							<tr>
								<th>{ __( 'City', 'mhm-rentiva' ) }</th>
								<td>
									{ cities.length > 0 ? (
										<span style={ { display: 'inline-flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' } }>
											<select
												value={ cityValue }
												onChange={ ( e ) => setCityValue( e.target.value ) }
												disabled={ citySaving }
											>
												<option value="">{ __( 'Select a city...', 'mhm-rentiva' ) }</option>
												{ cities.map( ( c ) => (
													<option key={ c } value={ c }>{ c }</option>
												) ) }
											</select>
											<button
												type="button"
												className="button button-small"
												onClick={ saveCity }
												disabled={ citySaving || cityValue === '' || cityValue === ( vendor.city || '' ) }
											>
												{ citySaving ? __( 'Saving…', 'mhm-rentiva' ) : __( 'Save', 'mhm-rentiva' ) }
											</button>
											{ cityMsg && (
												<span style={ { fontSize: '12px', color: cityMsg.type === 'error' ? '#c62828' : '#2e7d32' } }>
													{ cityMsg.text }
												</span>
											) }
										</span>
									) : ( vendor.city || '—' ) }
								</td>
							</tr>
							<tr><th>{ __( 'Reliability Score', 'mhm-rentiva' ) }</th><td>{ vendor.reliability_score ?? '—' }</td></tr>
							<tr><th>{ __( 'IBAN', 'mhm-rentiva' ) }</th><td>{ vendor.iban_masked || '—' }</td></tr>
							<tr>
								<th>{ __( 'Commission Rate Override', 'mhm-rentiva' ) }</th>
								<td>
									<span style={ { display: 'inline-flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' } }>
										<input
											type="number"
											min="0" max="100" step="0.01"
											style={ { width: '90px' } }
											placeholder={ __( 'Use global/tier rate', 'mhm-rentiva' ) }
											value={ rateValue }
											onChange={ ( e ) => setRateValue( e.target.value ) }
											disabled={ rateSaving }
										/>
										<span>%</span>
										<button
											type="button"
											className="button button-small"
											onClick={ saveRate }
											disabled={ rateSaving }
										>
											{ rateSaving ? __( 'Saving…', 'mhm-rentiva' ) : __( 'Save', 'mhm-rentiva' ) }
										</button>
										{ rateMsg && (
											<span style={ { fontSize: '12px', color: rateMsg.type === 'error' ? '#c62828' : '#2e7d32' } }>
												{ rateMsg.text }
											</span>
										) }
									</span>
								</td>
							</tr>
							<tr><th>{ __( 'Approved At', 'mhm-rentiva' ) }</th><td>{ vendor.approved_at || '—' }</td></tr>
						</tbody>
					</table>

					{ /* Lifetime stats strip */ }
					<div style={ { display: 'flex', gap: '16px', flexWrap: 'wrap', marginBottom: '24px' } }>
						<div className="mhm-vm-stat-card" style={ { flex: '1 1 160px', background: '#f6f7f7', border: '1px solid #dcdcde', borderRadius: '4px', padding: '12px 16px' } }>
							<div style={ { fontSize: '12px', color: '#646970', textTransform: 'uppercase' } }>{ __( 'Available Balance', 'mhm-rentiva' ) }</div>
							<div style={ { fontSize: '20px', fontWeight: 600 } }>{ fmtAmount( vendor.balance ) }</div>
						</div>
						<div className="mhm-vm-stat-card" style={ { flex: '1 1 160px', background: '#f6f7f7', border: '1px solid #dcdcde', borderRadius: '4px', padding: '12px 16px' } }>
							<div style={ { fontSize: '12px', color: '#646970', textTransform: 'uppercase' } }>{ __( 'Completed Bookings', 'mhm-rentiva' ) }</div>
							<div style={ { fontSize: '20px', fontWeight: 600 } }>{ vendor.stats ? vendor.stats.completed : 0 }</div>
						</div>
						<div className="mhm-vm-stat-card" style={ { flex: '1 1 160px', background: '#f6f7f7', border: '1px solid #dcdcde', borderRadius: '4px', padding: '12px 16px' } }>
							<div style={ { fontSize: '12px', color: '#646970', textTransform: 'uppercase' } }>{ __( 'Refunds', 'mhm-rentiva' ) }</div>
							<div style={ { fontSize: '20px', fontWeight: 600 } }>{ vendor.stats ? vendor.stats.refunded : 0 }</div>
						</div>
					</div>

					{ /* Payout history */ }
					<h3 style={ { display: 'flex', alignItems: 'center', gap: '10px' } }>
						{ __( 'Payout History', 'mhm-rentiva' ) }
						{ payoutsUrl && (
							<a href={ payoutsUrl } style={ { fontSize: '13px', fontWeight: 400 } }>{ __( 'View all →', 'mhm-rentiva' ) }</a>
						) }
					</h3>
					{ vendor.payouts && vendor.payouts.length > 0 ? (
						<table className="wp-list-table widefat fixed striped" style={ { marginBottom: '24px' } }>
							<thead>
								<tr>
									<th style={ { width: '130px' } }>{ __( 'Date', 'mhm-rentiva' ) }</th>
									<th style={ { width: '140px' } }>{ __( 'Amount', 'mhm-rentiva' ) }</th>
									<th>{ __( 'Status', 'mhm-rentiva' ) }</th>
								</tr>
							</thead>
							<tbody>
								{ vendor.payouts.map( ( p ) => (
									<tr key={ p.id }>
										<td>{ p.date }</td>
										<td>{ fmtAmount( p.amount ) }</td>
										<td>{ payoutStatusLabels[ p.status ] || p.status }</td>
									</tr>
								) ) }
							</tbody>
						</table>
					) : (
						<p className="mhm-vm-empty" style={ { marginBottom: '24px' } }>{ __( 'No payout requests yet.', 'mhm-rentiva' ) }</p>
					) }

					{ /* Reliability / penalty history */ }
					<h3>{ __( 'Reliability & Penalty History', 'mhm-rentiva' ) }</h3>
					{ vendor.score_history && vendor.score_history.length > 0 ? (
						<table className="wp-list-table widefat fixed striped" style={ { marginBottom: '24px' } }>
							<thead>
								<tr>
									<th style={ { width: '150px' } }>{ __( 'Date', 'mhm-rentiva' ) }</th>
									<th>{ __( 'Event', 'mhm-rentiva' ) }</th>
									<th style={ { width: '90px' } }>{ __( 'Change', 'mhm-rentiva' ) }</th>
									<th style={ { width: '90px' } }>{ __( 'Score', 'mhm-rentiva' ) }</th>
								</tr>
							</thead>
							<tbody>
								{ vendor.score_history.map( ( h, idx ) => (
									<tr key={ idx }>
										<td>{ h.ts }</td>
										<td>
											{ eventLabels[ h.event_type ] || h.event_type }
											{ h.vehicle_title ? ` — ${ h.vehicle_title }` : '' }
										</td>
										<td style={ { color: h.delta < 0 ? '#c62828' : '#2e7d32', fontWeight: 600 } }>
											{ h.delta > 0 ? `+${ h.delta }` : h.delta }
										</td>
										<td>{ h.score_after }</td>
									</tr>
								) ) }
							</tbody>
						</table>
					) : (
						<p className="mhm-vm-empty" style={ { marginBottom: '24px' } }>{ __( 'No penalties or reliability events recorded.', 'mhm-rentiva' ) }</p>
					) }

					<h3>{ __( 'Vehicles', 'mhm-rentiva' ) }</h3>
					{ vendor.vehicles && vendor.vehicles.length > 0 ? (
						<table className="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th>{ __( 'Title', 'mhm-rentiva' ) }</th>
									<th style={ { width: '120px' } }>{ __( 'Post Status', 'mhm-rentiva' ) }</th>
									<th style={ { width: '140px' } }>{ __( 'Lifecycle', 'mhm-rentiva' ) }</th>
									<th style={ { width: '80px' } }>{ __( 'Edit', 'mhm-rentiva' ) }</th>
								</tr>
							</thead>
							<tbody>
								{ vendor.vehicles.map( ( v ) => (
									<tr key={ v.id }>
										<td>{ v.title || `#${ v.id }` }</td>
										<td>{ postStatusLabels[ v.status ] || v.status }</td>
										<td>{ lifecycleLabels[ v.lifecycle ] || v.lifecycle }</td>
										<td>
											{ v.edit_link
												? <a href={ v.edit_link }>{ __( 'Edit', 'mhm-rentiva' ) }</a>
												: '—' }
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					) : (
						<p className="mhm-vm-empty">{ __( 'This vendor has no vehicles.', 'mhm-rentiva' ) }</p>
					) }
				</>
			) }
		</div>
	);
}
