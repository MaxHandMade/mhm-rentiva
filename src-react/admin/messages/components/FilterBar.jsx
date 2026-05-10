import { __ } from '@wordpress/i18n';
import { useRef, useEffect } from '@wordpress/element';

const { statuses, categories, priorities } = window.mhmRentivaMessages;

export default function FilterBar( { filters, onChange } ) {
	const searchRef = useRef( null );
	const timerRef  = useRef( null );

	useEffect( () => () => clearTimeout( timerRef.current ), [] );

	const handleSearch = ( e ) => {
		const val = e.target.value;
		clearTimeout( timerRef.current );
		timerRef.current = setTimeout( () => onChange( { search: val } ), 300 );
	};

	return (
		<div className="mhm-filter-bar">
			<input
				ref={ searchRef }
				type="search"
				className="regular-text"
				placeholder={ __( 'Search messages…', 'mhm-rentiva' ) }
				defaultValue={ filters.search }
				onChange={ handleSearch }
			/>

			<select
				value={ filters.status }
				onChange={ ( e ) => onChange( { status: e.target.value } ) }
			>
				<option value="">{ __( 'All statuses', 'mhm-rentiva' ) }</option>
				{ Object.entries( statuses ).map( ( [ k, v ] ) => (
					<option key={ k } value={ k }>{ v }</option>
				) ) }
			</select>

			<select
				value={ filters.category }
				onChange={ ( e ) => onChange( { category: e.target.value } ) }
			>
				<option value="">{ __( 'All categories', 'mhm-rentiva' ) }</option>
				{ Object.entries( categories ).map( ( [ k, v ] ) => (
					<option key={ k } value={ k }>{ v }</option>
				) ) }
			</select>

			<select
				value={ filters.priority }
				onChange={ ( e ) => onChange( { priority: e.target.value } ) }
			>
				<option value="">{ __( 'All priorities', 'mhm-rentiva' ) }</option>
				{ Object.entries( priorities ).map( ( [ k, v ] ) => (
					<option key={ k } value={ k }>{ v }</option>
				) ) }
			</select>
		</div>
	);
}
