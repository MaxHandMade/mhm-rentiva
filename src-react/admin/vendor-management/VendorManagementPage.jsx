import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const config = window.mhmRentivaVendorManagement || {};

export default function VendorManagementPage() {
	const [ tab,    setTab    ] = useState( config.initialTab  || 'pending' );
	const [ viewId, setViewId ] = useState( config.initialView || 0 );
	const [ notice, setNotice ] = useState( config.flash || null );

	// Flash from PHP (read before WP's common.js strips URL params).
	useEffect( () => {
		if ( config.flash ) {
			setNotice( config.flash );
		}
	}, [] );

	const switchTab = ( newTab ) => {
		setTab( newTab );
		setViewId( 0 );
		const url = new URL( window.location.href );
		url.searchParams.set( 'tab', newTab );
		url.searchParams.delete( 'view' );
		history.pushState( {}, '', url.toString() );
	};

	const openDetail = ( id ) => {
		setViewId( id );
		const url = new URL( window.location.href );
		url.searchParams.set( 'view', id );
		history.pushState( {}, '', url.toString() );
	};

	const closeDetail = () => {
		setViewId( 0 );
		const url = new URL( window.location.href );
		url.searchParams.delete( 'view' );
		history.pushState( {}, '', url.toString() );
	};

	return (
		<div className="mhm-vm-app">
			{ notice && (
				<div className={ `notice notice-${ notice.type } is-dismissible` }>
					<p>{ notice.message }</p>
					<button type="button" className="notice-dismiss" onClick={ () => setNotice( null ) } />
				</div>
			) }

			{ /* Tab switcher within React-managed tabs */ }
			{ viewId === 0 && (
				<div className="mhm-vm-tab-switcher" style={ { marginBottom: '16px' } }>
					<button
						type="button"
						className={ `button ${ tab === 'pending' ? 'button-primary' : '' }` }
						onClick={ () => switchTab( 'pending' ) }
					>
						{ __( 'Pending Applications', 'mhm-rentiva' ) }
					</button>
					{ ' ' }
					<button
						type="button"
						className={ `button ${ tab === 'iban_requests' ? 'button-primary' : '' }` }
						onClick={ () => switchTab( 'iban_requests' ) }
					>
						{ __( 'IBAN Requests', 'mhm-rentiva' ) }
						{ config.pendingIbanCount > 0 && (
							<span className="mhm-vm-badge-count"> ({ config.pendingIbanCount })</span>
						) }
					</button>
				</div>
			) }

			{ viewId > 0
				? <p>{ __( 'Detail view — coming in Task 7.', 'mhm-rentiva' ) } (ID: { viewId })</p>
				: tab === 'iban_requests'
					? <p>{ __( 'IBAN Requests tab — coming in Task 8.', 'mhm-rentiva' ) }</p>
					: <p>{ __( 'Pending Applications tab — coming in Task 6.', 'mhm-rentiva' ) }</p>
			}
		</div>
	);
}
