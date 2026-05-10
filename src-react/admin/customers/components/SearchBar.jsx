import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export default function SearchBar( { value, onChange } ) {
	const [local, setLocal] = useState( value );
	const timer = useRef( null );

	useEffect( () => {
		setLocal( value );
	}, [value] );

	const handleChange = ( e ) => {
		const v = e.target.value;
		setLocal( v );
		clearTimeout( timer.current );
		timer.current = setTimeout( () => onChange( v ), 300 );
	};

	return (
		<div className="mhm-customers__search">
			<input
				type="search"
				placeholder={ __( 'Search customers…', 'mhm-rentiva' ) }
				value={ local }
				onChange={ handleChange }
			/>
		</div>
	);
}
