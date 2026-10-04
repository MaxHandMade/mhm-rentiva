import { useRef, useEffect } from '@wordpress/element';
import { __, sprintf, _n } from '@wordpress/i18n';

export default function ContactToolbar( { filters, total, onChange } ) {
	const types = window.mhmRentivaContactMessages?.types ?? {};
	const months = window.mhmRentivaContactMessages?.months ?? [];
	const timer = useRef( null );
	useEffect( () => () => clearTimeout( timer.current ), [] );

	return (
		<div className="mhm-contact-messages__toolbar">
			<div className="mhm-contact-messages__search">
				<label className="screen-reader-text" htmlFor="mhm-contact-search">{ __( 'Search contact messages', 'mhm-rentiva' ) }</label>
				<input
					id="mhm-contact-search"
					type="search"
					defaultValue={ filters.search }
					placeholder={ __( 'Name, e-mail or message…', 'mhm-rentiva' ) }
					onChange={ ( e ) => {
						const value = e.target.value;
						clearTimeout( timer.current );
						timer.current = setTimeout( () => onChange( { search: value } ), 300 );
					} }
				/>
				<svg aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
					<circle cx="11" cy="11" r="6.5" />
					<path d="M16 16l4 4" />
				</svg>
			</div>
			<label className="screen-reader-text" htmlFor="mhm-contact-type">{ __( 'Enquiry type', 'mhm-rentiva' ) }</label>
			<select id="mhm-contact-type" value={ filters.type } onChange={ ( e ) => onChange( { type: e.target.value } ) }>
				<option value="">{ __( 'All enquiry types', 'mhm-rentiva' ) }</option>
				{ Object.entries( types ).map( ( [ k, v ] ) => <option key={ k } value={ k }>{ v }</option> ) }
			</select>
			<label className="screen-reader-text" htmlFor="mhm-contact-period">{ __( 'Date', 'mhm-rentiva' ) }</label>
			<select id="mhm-contact-period" value={ filters.period } onChange={ ( e ) => onChange( { period: e.target.value } ) }>
				<option value="">{ __( 'All dates', 'mhm-rentiva' ) }</option>
				<option value="7d">{ __( 'Last 7 days', 'mhm-rentiva' ) }</option>
				<option value="30d">{ __( 'Last 30 days', 'mhm-rentiva' ) }</option>
				{ months.map( ( m ) => <option key={ m.value } value={ m.value }>{ m.label }</option> ) }
			</select>
			<span className="mhm-contact-messages__count">
				{ sprintf(
					/* translators: %d: number of messages in the current view. */
					_n( '%d message', '%d messages', total, 'mhm-rentiva' ),
					total
				) }
			</span>
		</div>
	);
}
