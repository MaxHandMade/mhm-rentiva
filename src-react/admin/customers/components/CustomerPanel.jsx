import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { rentivaApi } from '../../../shared/api/rentiva';

export default function CustomerPanel( { panelId, onClose } ) {
	const [detail,  setDetail]  = useState( null );
	const [loading, setLoading] = useState( false );
	const [error,   setError]   = useState( null );

	const fetchDetail = useCallback( () => {
		if ( ! panelId ) return;
		setLoading( true );
		setError( null );
		rentivaApi.customers.getDetail( panelId )
			.then( setDetail )
			.catch( () => setError( __( 'Could not load customer details.', 'mhm-rentiva' ) ) )
			.finally( () => setLoading( false ) );
	}, [panelId] );

	useEffect( () => {
		if ( panelId ) {
			setDetail( null );
			fetchDetail();
		}
	}, [panelId] );

	// Close on Escape key.
	useEffect( () => {
		const handler = ( e ) => { if ( e.key === 'Escape' ) onClose(); };
		document.addEventListener( 'keydown', handler );
		return () => document.removeEventListener( 'keydown', handler );
	}, [onClose] );

	const isOpen = !! panelId;

	return (
		<>
			<div
				className={ `mhm-customer-panel-overlay${ isOpen ? ' is-open' : '' }` }
				onClick={ onClose }
			/>
			<aside className={ `mhm-customer-panel${ isOpen ? ' is-open' : '' }` } role="dialog" aria-label={ __( 'Customer Details', 'mhm-rentiva' ) }>
				<div className="mhm-customer-panel__header">
					<strong>{ __( 'Customer Details', 'mhm-rentiva' ) }</strong>
					<button type="button" className="mhm-customer-panel__close" onClick={ onClose }>✕</button>
				</div>

				{ loading && <p className="mhm-customers__loading">{ __( 'Loading…', 'mhm-rentiva' ) }</p> }
				{ error   && <p className="mhm-customers__error">{ error }</p> }

				{ ! loading && ! error && detail && (
					<>
						<div className="mhm-customer-panel__field">
							<div className="mhm-customer-panel__label">{ __( 'Name', 'mhm-rentiva' ) }</div>
							<div className="mhm-customer-panel__value">{ detail.name }</div>
						</div>
						<div className="mhm-customer-panel__field">
							<div className="mhm-customer-panel__label">{ __( 'Email', 'mhm-rentiva' ) }</div>
							<div className="mhm-customer-panel__value">{ detail.email }</div>
						</div>
						<div className="mhm-customer-panel__field">
							<div className="mhm-customer-panel__label">{ __( 'Phone', 'mhm-rentiva' ) }</div>
							<div className="mhm-customer-panel__value">{ detail.phone || '—' }</div>
						</div>
						<div className="mhm-customer-panel__field">
							<div className="mhm-customer-panel__label">{ __( 'Address', 'mhm-rentiva' ) }</div>
							<div className="mhm-customer-panel__value">{ detail.address || '—' }</div>
						</div>
						<div className="mhm-customer-panel__field">
							<div className="mhm-customer-panel__label">{ __( 'Registered', 'mhm-rentiva' ) }</div>
							<div className="mhm-customer-panel__value">{ detail.registered }</div>
						</div>

						<div className="mhm-customer-panel__stats">
							<div>
								<div className="mhm-customer-panel__stat-label">{ __( 'Total Bookings', 'mhm-rentiva' ) }</div>
								<div className="mhm-customer-panel__stat-value">{ detail.booking_count }</div>
							</div>
							<div>
								<div className="mhm-customer-panel__stat-label">{ `${ __( 'Total Spent', 'mhm-rentiva' ) } (${ detail.currency })` }</div>
								<div className="mhm-customer-panel__stat-value">{ detail.total_spent }</div>
							</div>
							<div>
								<div className="mhm-customer-panel__stat-label">{ __( 'First Booking', 'mhm-rentiva' ) }</div>
								<div className="mhm-customer-panel__stat-value" style={ { fontSize: 13 } }>{ detail.first_booking || '—' }</div>
							</div>
							<div>
								<div className="mhm-customer-panel__stat-label">{ __( 'Last Booking', 'mhm-rentiva' ) }</div>
								<div className="mhm-customer-panel__stat-value" style={ { fontSize: 13 } }>{ detail.last_booking || '—' }</div>
							</div>
						</div>

						<div className="mhm-customer-panel__actions">
							<a
								href={ `admin.php?page=mhm-rentiva-customers&action=edit&customer_id=${ detail.id }` }
								className="button button-primary"
							>
								{ __( 'Edit', 'mhm-rentiva' ) }
							</a>
							<a
								href={ `edit.php?post_type=vehicle_booking&customer_email=${ encodeURIComponent( detail.email ) }` }
								className="button"
							>
								{ __( 'View Bookings', 'mhm-rentiva' ) }
							</a>
						</div>
					</>
				) }
			</aside>
		</>
	);
}
