import apiFetch from '@wordpress/api-fetch';
import { createApiClient } from '../../../vendor/mhm/ui-core/src-react/api/createApiClient';

/**
 * Rentiva's REST endpoint map.
 *
 * The client comes from mhm/ui-core; the map stays here. A shared package that
 * knew these routes would be asked to hold a second product's routes next to
 * them, so ui-core ships the factory and each plugin keeps its own map.
 */
const api = createApiClient( '/mhm-rentiva/v1', apiFetch );

export const rentivaApi = {
	dashboard: {
		getUpcoming: ( page = 1 ) => api.get( '/dashboard/upcoming', { page } ),
		getRecentBookings: ( page = 1 ) =>
			api.get( '/dashboard/recent-bookings', { page } ),
	},
	customers: {
		getList: ( params ) => api.get( '/customers', params ),
		getDetail: ( id ) => api.get( `/customers/${ id }` ),
		bulkDelete: ( ids ) => api.del( '/customers/bulk', { ids } ),
	},
	about: {
		getData: () => api.get( '/about' ),
	},
	shortcodePages: {
		getList: () => api.get( '/shortcode-pages' ),
		createPage: ( slug ) => api.post( `/shortcode-pages/${ slug }/create` ),
		deletePage: ( slug ) => api.del( `/shortcode-pages/${ slug }` ),
		clearCache: () => api.post( '/shortcode-pages/clear-cache' ),
		debug: () => api.get( '/shortcode-pages/debug' ),
		reset: () => api.post( '/shortcode-pages/reset' ),
	},
};
