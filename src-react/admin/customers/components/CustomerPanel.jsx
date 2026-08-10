import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { rentivaApi } from '../../../shared/api/rentiva';
import { STATUS_LABELS, initials, avatarColors } from './CustomerTable';

export default function CustomerPanel( { panelId, row, currency, adminUrl, onClose } ) {
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
	}, [panelId, fetchDetail] );

	// Clear the selection on Escape.
	useEffect( () => {
		const handler = ( e ) => { if ( e.key === 'Escape' ) onClose(); };
		document.addEventListener( 'keydown', handler );
		return () => document.removeEventListener( 'keydown', handler );
	}, [onClose] );

	if ( ! panelId || ! row ) {
		return (
			<aside className="rv-cust-panel is-empty" aria-label={ __( 'Customer Details', 'mhm-rentiva' ) }>
				<div className="rv-cust-panel__placeholder">
					<span className="rv-cust-panel__placeholder-icon dashicons dashicons-admin-users" />
					{ __( 'Select a customer to see the details', 'mhm-rentiva' ) }
				</div>
			</aside>
		);
	}

	const [avBg, avColor] = avatarColors( row.id );
	const statusLabel     = STATUS_LABELS[ row.status ] ?? '';
	const profileUrl      = `${ adminUrl }admin.php?page=mhm-rentiva-customers&action=view&customer_id=${ row.id }`;

	return (
		<aside className="rv-cust-panel" aria-label={ __( 'Customer Details', 'mhm-rentiva' ) }>
			<div className="rv-cust-panel__card">
				<div className="rv-cust-panel__head">
					<span className="rv-cust-avatar is-lg" style={ { background: avBg, color: avColor } }>
						{ initials( row.name ) }
					</span>
					<div className="rv-cust-panel__title">
						<div className="rv-cust-panel__name">{ row.name }</div>
						<div className="rv-cust-panel__meta">
							{ detail?.registered ?? '…' }
							{ statusLabel ? ` · ${ statusLabel }` : '' }
						</div>
					</div>
					<button
						type="button"
						className="rv-cust-panel__close"
						onClick={ onClose }
						aria-label={ __( 'Close', 'mhm-rentiva' ) }
					>
						✕
					</button>
				</div>

				<div className="rv-cust-panel__contact">
					<div className="rv-cust-panel__line">
						<span>{ __( 'Email', 'mhm-rentiva' ) }</span>
						<span>{ row.email }</span>
					</div>
					<div className="rv-cust-panel__line">
						<span>{ __( 'Phone', 'mhm-rentiva' ) }</span>
						<span>{ row.phone || '—' }</span>
					</div>
					<div className="rv-cust-panel__line">
						<span>{ __( 'Address', 'mhm-rentiva' ) }</span>
						<span>{ detail?.address ?? row.address ?? '—' }</span>
					</div>
				</div>

				<div className="rv-cust-panel__stats">
					<div>
						<strong>{ row.booking_count }</strong>
						<span>{ __( 'bookings', 'mhm-rentiva' ) }</span>
					</div>
					<div>
						<strong>{ `${ currency ?? '' }${ row.total_spent }` }</strong>
						<span>{ __( 'total', 'mhm-rentiva' ) }</span>
					</div>
					<div>
						<strong>{ detail?.favorites_count ?? '…' }</strong>
						<span>{ __( 'favorites', 'mhm-rentiva' ) }</span>
					</div>
				</div>

				<div className="rv-cust-panel__body">
					<div className="rv-cust-panel__section-title">{ __( 'Recent bookings', 'mhm-rentiva' ) }</div>

					{ loading && <p className="rv-cust-loading">{ __( 'Loading…', 'mhm-rentiva' ) }</p> }
					{ error   && <p className="rv-cust-error">{ error }</p> }

					{ ! loading && ! error && ( detail?.recent_bookings?.length ? (
						detail.recent_bookings.map( ( b, i ) => (
							<div key={ i } className="rv-cust-panel__booking">
								<div>
									<div className="rv-cust-panel__booking-vehicle">
										{ b.id ? (
											<>
												<a href={ `${ adminUrl }post.php?post=${ b.id }&action=edit` }>{ b.reference ?? `#${ b.id }` }</a>
												{ ' · ' }
											</>
										) : null }
										{ b.vehicle }
									</div>
									<div className="rv-cust-panel__booking-date">{ b.date }</div>
								</div>
								<span className="rv-cust-panel__booking-amount">{ `${ currency ?? '' }${ b.amount }` }</span>
							</div>
						) )
					) : (
						<p className="rv-cust-panel__none">{ __( 'No bookings yet.', 'mhm-rentiva' ) }</p>
					) ) }

					<div className="rv-cust-panel__actions">
						<a href={ profileUrl } className="rv-cust-btn is-primary">
							{ __( 'Open Profile', 'mhm-rentiva' ) }
						</a>
						<a href={ `mailto:${ row.email }` } className="rv-cust-btn">
							{ __( 'Send Email', 'mhm-rentiva' ) }
						</a>
					</div>
				</div>
			</div>
		</aside>
	);
}
