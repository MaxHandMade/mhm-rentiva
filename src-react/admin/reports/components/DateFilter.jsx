import { __ } from '@wordpress/i18n';

export default function DateFilter( { dateRange, onChange } ) {
	const handleStart = ( e ) => {
		const value = e.target.value;
		onChange( { ...dateRange, start: value } );
	};

	const handleEnd = ( e ) => {
		const value = e.target.value;
		if ( value < dateRange.start ) return; // end cannot precede start
		onChange( { ...dateRange, end: value } );
	};

	return (
		<div className="mhm-reports__date-filter">
			<label htmlFor="mhm-reports-start">
				{ __( 'Start Date', 'mhm-rentiva' ) }
			</label>
			<input
				type="date"
				id="mhm-reports-start"
				value={ dateRange.start }
				onChange={ handleStart }
			/>
			<label htmlFor="mhm-reports-end">
				{ __( 'End Date', 'mhm-rentiva' ) }
			</label>
			<input
				type="date"
				id="mhm-reports-end"
				value={ dateRange.end }
				onChange={ handleEnd }
			/>
		</div>
	);
}
