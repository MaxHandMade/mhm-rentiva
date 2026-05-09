import apiFetch from '@wordpress/api-fetch';

const BASE = '/mhm-rentiva/v1';

export const rentivaApi = {
	dashboard: {
		getUpcoming: ( page = 1 ) => apiFetch( { path: `${ BASE }/dashboard/upcoming?page=${ page }` } ),
	},
	reports: {
		getSummary: ( params ) => {
			const qs = new URLSearchParams( params ).toString();
			return apiFetch( { path: `${ BASE }/reports?${ qs }` } );
		},
	},
};
