import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export default function CopyValue( { value } ) {
	const [ copied, setCopied ] = useState( false );

	const handleClick = () => {
		const onCopied = () => {
			setCopied( true );
			setTimeout( () => setCopied( false ), 2000 );
		};

		if ( navigator.clipboard && window.isSecureContext ) {
			// Only confirm on success; a silent failure must not show "Copied!".
			navigator.clipboard.writeText( value ).then( onCopied ).catch( () => {} );
		} else {
			const el = document.createElement( 'textarea' );
			el.value = value;
			el.style.cssText = 'position:fixed;left:-9999px;top:-9999px';
			document.body.appendChild( el );
			el.select();
			const ok = document.execCommand( 'copy' );
			document.body.removeChild( el );
			if ( ok ) {
				onCopied();
			}
		}
	};

	return (
		<span
			className="mhm-copy-value"
			onClick={ handleClick }
			title={ __( 'Click to copy', 'mhm-rentiva' ) }
			role="button"
			tabIndex={ 0 }
			onKeyDown={ ( e ) => e.key === 'Enter' && handleClick() }
		>
			{ value }
			{ copied && (
				<span className="mhm-copy-feedback">
					{ __( 'Copied!', 'mhm-rentiva' ) }
				</span>
			) }
		</span>
	);
}
