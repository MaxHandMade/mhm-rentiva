import { useState, useEffect } from '@wordpress/element';

/**
 * Fetch hook with loading state, error handling, and a refetch trigger.
 *
 * @param {Function} fetchFn     - Stable function that returns a Promise (use module-scope functions from rentiva.js).
 * @param {*}        initialData - Pre-populated data from wp_localize_script to avoid first-paint spinner.
 * @param {Array}    deps        - Additional effect dependencies (e.g. filter values that should re-trigger fetch).
 * @return {{ data, loading, error, refetch }}
 */
export function useApi( fetchFn, initialData = null, deps = [] ) {
	const [ data, setData ]       = useState( initialData );
	const [ loading, setLoading ] = useState( initialData === null );
	const [ error, setError ]     = useState( null );
	const [ trigger, setTrigger ] = useState( 0 );

	useEffect( () => {
		setLoading( true );
		fetchFn()
			.then( setData )
			.catch( setError )
			.finally( () => setLoading( false ) );
	}, [ ...deps, trigger ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const refetch = () => setTrigger( ( t ) => t + 1 );

	return { data, loading, error, refetch };
}
