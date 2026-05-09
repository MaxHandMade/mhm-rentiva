import apiFetch from '@wordpress/api-fetch';

const BASE = '/mhm-rentiva/v1';

export const rentivaApi = {
	dashboard: {
		getUpcoming: ( page = 1 ) => apiFetch( { path: `${ BASE }/dashboard/upcoming?page=${ page }` } ),
	},
	// Extended per page as fazes are implemented.
};
