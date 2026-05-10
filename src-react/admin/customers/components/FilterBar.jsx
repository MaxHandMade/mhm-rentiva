import { useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export default function FilterBar( { search, selectedIds, nonce, adminUrl, addCustomerUrl } ) {
	const formRef = useRef( null );

	const handleExport = () => {
		if ( formRef.current ) formRef.current.submit();
	};

	return (
		<div className="mhm-customers__toolbar" style={ { justifyContent: 'flex-end' } }>
			<a href={ addCustomerUrl } className="button button-primary">
				{ __( 'Add Customer', 'mhm-rentiva' ) }
			</a>
			<button type="button" className="button" onClick={ handleExport }>
				{ __( 'Export CSV', 'mhm-rentiva' ) }
			</button>
			<form ref={ formRef } method="POST" action={ `${ adminUrl }admin-post.php` } style={ { display: 'none' } }>
				<input type="hidden" name="action" value="mhm_rentiva_export_customers" />
				<input type="hidden" name="nonce"  value={ nonce } />
				<input type="hidden" name="search" value={ search } />
				{ selectedIds.map( ( id ) => (
					<input key={ id } type="hidden" name="ids[]" value={ id } />
				) ) }
			</form>
		</div>
	);
}
