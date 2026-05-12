import { useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { rentivaApi } from '../../shared/api/rentiva';
import ExportCards from './components/ExportCards';
import AdvancedFilters from './components/AdvancedFilters';
import PreviewBar from './components/PreviewBar';
import ExportForm from './components/ExportForm';
import ExportHistory from './components/ExportHistory';

const POST_TYPES = [ 'vehicle_booking', 'vehicle', 'mhm_app_log' ];

export default function ExportPage( { config } ) {
	const [ activeType, setActiveType ]     = useState( 'vehicle_booking' );
	const [ dateFrom, setDateFrom ]         = useState( '' );
	const [ dateTo, setDateTo ]             = useState( '' );
	const [ dateRange, setDateRange ]       = useState( '' );
	const [ preview, setPreview ]           = useState( null );
	const [ previewLoading, setPreviewLoading ] = useState( false );
	const [ previewError, setPreviewError ] = useState( '' );

	const handleTypeChange = useCallback( ( type ) => {
		setActiveType( type );
		setPreview( null );
		setPreviewError( '' );
	}, [] );

	const handleRangeChange = useCallback( ( range, from, to ) => {
		setDateRange( range );
		setDateFrom( from );
		setDateTo( to );
		setPreview( null );
		setPreviewError( '' );
	}, [] );

	const handlePreview = useCallback( async () => {
		if ( ! POST_TYPES.includes( activeType ) ) {
			return;
		}
		setPreviewLoading( true );
		setPreviewError( '' );
		try {
			const data = { post_type: activeType };
			if ( dateFrom ) { data.date_from = dateFrom; }
			if ( dateTo )   { data.date_to   = dateTo;   }
			const result = await rentivaApi.export.preview( data );
			setPreview( result );
		} catch ( err ) {
			setPreviewError( err?.message ?? __( 'Preview failed.', 'mhm-rentiva' ) );
			setPreview( null );
		} finally {
			setPreviewLoading( false );
		}
	}, [ activeType, dateFrom, dateTo ] );

	const postTypeLabels = config.postTypes ?? {};
	const dateRanges     = config.dateRanges ?? [];
	const adminUrl       = config.adminUrl ?? '';
	const exportNonce    = config.exportNonce ?? '';

	return (
		<div className="mhm-export-spa">
			<ExportCards
				activeType={ activeType }
				postTypeLabels={ postTypeLabels }
				onSelect={ handleTypeChange }
			/>

			<AdvancedFilters
				dateRanges={ dateRanges }
				dateRange={ dateRange }
				dateFrom={ dateFrom }
				dateTo={ dateTo }
				onRangeChange={ handleRangeChange }
				onPreview={ handlePreview }
				previewLoading={ previewLoading }
			/>

			{ previewError && (
				<div className="mhm-export-notice mhm-export-notice--error">
					{ previewError }
				</div>
			) }

			{ preview !== null && (
				<PreviewBar count={ preview.count } sample={ preview.sample } />
			) }

			<ExportForm
				adminUrl={ adminUrl }
				exportNonce={ exportNonce }
				activeType={ activeType }
				dateFrom={ dateFrom }
				dateTo={ dateTo }
				disabled={ preview !== null && preview.count === 0 }
			/>

			<ExportHistory />
		</div>
	);
}
