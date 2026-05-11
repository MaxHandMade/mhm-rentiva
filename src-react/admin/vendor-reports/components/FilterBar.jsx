import { useState } from '@wordpress/element';
import { __ }       from '@wordpress/i18n';

const { statuses, contexts } = window.mhmRentivaVendorReports || {};

export default function FilterBar( { onFilter, initialStatus = 'open', initialContext = '' } ) {
	const [ status,  setStatus  ] = useState( initialStatus );
	const [ context, setContext ] = useState( initialContext );

	const handleSubmit = ( e ) => {
		e.preventDefault();
		onFilter( { status, context } );
	};

	const statusOptions  = { all: __( 'All statuses', 'mhm-rentiva' ), ...( statuses || {} ) };
	const contextOptions = { '': __( 'All contexts', 'mhm-rentiva' ), ...( contexts || {} ) };

	return (
		<form className="mhm-vr-filter-bar" onSubmit={ handleSubmit }>
			<select value={ status } onChange={ ( e ) => setStatus( e.target.value ) }>
				{ Object.entries( statusOptions ).map( ( [ key, label ] ) => (
					<option key={ key } value={ key }>{ label }</option>
				) ) }
			</select>

			<select value={ context } onChange={ ( e ) => setContext( e.target.value ) }>
				{ Object.entries( contextOptions ).map( ( [ key, label ] ) => (
					<option key={ key } value={ key }>{ label }</option>
				) ) }
			</select>

			<button type="submit" className="button">
				{ __( 'Filter', 'mhm-rentiva' ) }
			</button>
		</form>
	);
}
