import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const EXPERTISE = [
	{
		title: __( 'WordPress Development', 'mhm-rentiva' ),
		desc:  __( 'Custom plugins, theme development, performance optimization', 'mhm-rentiva' ),
	},
	{
		title: __( 'E-commerce Solutions', 'mhm-rentiva' ),
		desc:  __( 'WooCommerce customizations, payment integrations', 'mhm-rentiva' ),
	},
	{
		title: __( 'Reservation Systems', 'mhm-rentiva' ),
		desc:  __( 'Hotel, restaurant, car rental and event reservations', 'mhm-rentiva' ),
	},
];

const PROJECTS = [
	{
		title: __( 'MHM E-commerce Package', 'mhm-rentiva' ),
		desc:  __( 'Comprehensive WooCommerce-based e-commerce solution', 'mhm-rentiva' ),
	},
	{
		title: __( 'MHM Vehicle Reservation', 'mhm-rentiva' ),
		desc:  __( 'Professional vehicle rental and reservation management system.', 'mhm-rentiva' ),
	},
];

function SystemReportCard( { report } ) {
	const [ copied, setCopied ] = useState( false );

	const handleCopy = () => {
		const onCopied = () => {
			setCopied( true );
			setTimeout( () => setCopied( false ), 2000 );
		};

		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( report ).then( onCopied );
		} else {
			const el = document.createElement( 'textarea' );
			el.value = report;
			el.style.cssText = 'position:fixed;left:-9999px;top:-9999px';
			document.body.appendChild( el );
			el.select();
			document.execCommand( 'copy' );
			document.body.removeChild( el );
			onCopied();
		}
	};

	const handleDownload = () => {
		const blob = new Blob( [ report ], { type: 'text/plain' } );
		const url  = URL.createObjectURL( blob );
		const a    = document.createElement( 'a' );
		a.href     = url;
		a.download = 'mhm-rentiva-system-report.txt';
		document.body.appendChild( a );
		a.click();
		document.body.removeChild( a );
		URL.revokeObjectURL( url );
	};

	return (
		<div className="mhm-widget rv-abt-card rv-abt-report">
			<h3>{ __( 'System Report', 'mhm-rentiva' ) }</h3>
			<p className="rv-abt-report__hint">
				{ __( 'Copy the report below into your support request.', 'mhm-rentiva' ) }
			</p>
			<textarea
				id="rv-abt-report-text"
				className="rv-abt-report__text"
				readOnly
				value={ report }
				aria-label={ __( 'System Report', 'mhm-rentiva' ) }
			/>
			<div className="rv-abt-report__actions">
				<button type="button" className="rv-abt-btn" onClick={ handleCopy }>
					{ copied ? __( 'Copied!', 'mhm-rentiva' ) : __( 'Copy Report', 'mhm-rentiva' ) }
				</button>
				<button type="button" className="rv-abt-btn" onClick={ handleDownload }>
					{ __( 'Download', 'mhm-rentiva' ) }
				</button>
			</div>
		</div>
	);
}

export default function DeveloperTab( { data, report } ) {
	return (
		<div className="mhm-about-developer">

			<div className="mhm-widget rv-abt-card mhm-developer-header">
				{ data.logo_url && (
					<img
						src={ data.logo_url }
						alt={ __( 'MHM Logo', 'mhm-rentiva') }
						className="mhm-developer-logo"
						onError={ ( e ) => { e.currentTarget.style.display = 'none'; } }
					/>
				) }
				<div className="mhm-developer-details">
					<h3>{ __( 'MHM (MaxHandMade)', 'mhm-rentiva' ) }</h3>
					<p className="mhm-developer-tagline">
						{ __( 'WordPress Expertise and Custom Software Solutions', 'mhm-rentiva' ) }
					</p>
					<div className="mhm-developer-stats">
						<span>{ __( '10+ Years Experience', 'mhm-rentiva' ) }</span>
						<span>{ __( '500+ Projects', 'mhm-rentiva' ) }</span>
						<span>{ __( '100% Customer Satisfaction', 'mhm-rentiva' ) }</span>
					</div>
				</div>
			</div>

			<div className="mhm-widget rv-abt-card">
				<h3>{ __( 'Our Expertise', 'mhm-rentiva' ) }</h3>
				<div className="mhm-about-grid-3">
					{ EXPERTISE.map( ( item ) => (
						<div key={ item.title } className="mhm-expertise-item">
							<h4>{ item.title }</h4>
							<p>{ item.desc }</p>
						</div>
					) ) }
				</div>
			</div>

			<div className="mhm-widget rv-abt-card">
				<h3>{ __( 'Contact', 'mhm-rentiva' ) }</h3>
				<dl className="mhm-contact-grid">
					<div className="mhm-info-row">
						<dt>{ __( 'Website:', 'mhm-rentiva' ) }</dt>
						<dd><a href={ data.company_website } target="_blank" rel="noreferrer">{ data.company_website }</a></dd>
					</div>
					<div className="mhm-info-row">
						<dt>{ __( 'Email:', 'mhm-rentiva' ) }</dt>
						<dd><a href={ `mailto:${ data.support_email }` }>{ data.support_email }</a></dd>
					</div>
					<div className="mhm-info-row">
						<dt>{ __( 'Phone:', 'mhm-rentiva' ) }</dt>
						<dd><a href={ `tel:${ data.phone.replace( /\s/g, '' ) }` }>{ data.phone }</a></dd>
					</div>
					<div className="mhm-info-row">
						<dt>{ __( 'Address:', 'mhm-rentiva' ) }</dt>
						<dd>{ __( 'Kocaeli - Turkey 41400', 'mhm-rentiva' ) }</dd>
					</div>
				</dl>
			</div>

			<SystemReportCard report={ report } />

			<div className="mhm-widget rv-abt-card">
				<h3>{ __( 'Our Other Projects', 'mhm-rentiva' ) }</h3>
				<div className="mhm-about-grid-2">
					{ PROJECTS.map( ( item ) => (
						<div key={ item.title } className="mhm-project-item">
							<h4>{ item.title }</h4>
							<p>{ item.desc }</p>
							<a href={ data.company_website } target="_blank" rel="noreferrer" className="rv-abt-btn rv-abt-btn--small">
								{ __( 'Learn More', 'mhm-rentiva' ) }
							</a>
						</div>
					) ) }
				</div>
			</div>

		</div>
	);
}
