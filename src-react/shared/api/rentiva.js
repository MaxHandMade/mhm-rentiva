import apiFetch from '@wordpress/api-fetch';

const BASE = '/mhm-rentiva/v1';

export const rentivaApi = {
	dashboard: {
		getStats: () => apiFetch( { path: `${ BASE }/dashboard/stats` } ),
	},
	// Extended per page as fazes are implemented.
};
