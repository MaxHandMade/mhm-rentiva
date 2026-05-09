import { useState, useCallback, useEffect } from '@wordpress/element';
import { rentivaApi } from '../../shared/api/rentiva';
import StatsCards    from './components/StatsCards';
import DateFilter    from './components/DateFilter';
import TabNavigation from './components/TabNavigation';
import ReportContent from './components/ReportContent';

export default function ReportsPage() {
	const data = window.mhmRentivaReports ?? {};

	const [dateRange, setDateRange] = useState( {
		start: data.defaultStart ?? '',
		end:   data.defaultEnd   ?? '',
	} );
	const [activeTab, setActiveTab] = useState( 'overview' );
	const [cache,     setCache]     = useState( {} );
	const [loading,   setLoading]   = useState( false );
	const [error,     setError]     = useState( null );

	const fetchTab = useCallback(
		( tab ) => {
			const key = `${ tab }_${ dateRange.start }_${ dateRange.end }`;
			if ( key in cache ) return;
			setLoading( true );
			rentivaApi.reports
				.getSummary( { tab, start_date: dateRange.start, end_date: dateRange.end } )
				.then( ( result ) => setCache( ( prev ) => ( { ...prev, [key]: result.data } ) ) )
				.catch( setError )
				.finally( () => setLoading( false ) );
		},
		[dateRange, cache]
	);

	// Fetch initial tab + re-fetch when date range changes.
	useEffect( () => {
		fetchTab( activeTab );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [dateRange] );

	const handleDateChange = ( newRange ) => {
		setCache( {} );
		setDateRange( newRange );
	};

	const handleTabChange = ( tab ) => {
		setActiveTab( tab );
		fetchTab( tab );
	};

	const cacheKey    = `${ activeTab }_${ dateRange.start }_${ dateRange.end }`;
	const currentData = cache[ cacheKey ] ?? null;

	return (
		<div className="mhm-reports">
			<StatsCards statsCards={ data.statsCards } currency={ data.currency ?? '' } />
			<DateFilter
				dateRange={ dateRange }
				onChange={ handleDateChange }
			/>
			<TabNavigation activeTab={ activeTab } onChange={ handleTabChange } />
			<ReportContent
				tab={ activeTab }
				data={ currentData }
				loading={ loading }
				error={ error }
				currency={ data.currency ?? '' }
			/>
		</div>
	);
}
