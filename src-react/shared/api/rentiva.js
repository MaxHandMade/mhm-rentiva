import apiFetch from '@wordpress/api-fetch';

const BASE = '/mhm-rentiva/v1';

export const rentivaApi = {
	dashboard: {
		getUpcoming:        ( page = 1 ) => apiFetch( { path: `${ BASE }/dashboard/upcoming?page=${ page }` } ),
		getRecentBookings:  ( page = 1 ) => apiFetch( { path: `${ BASE }/dashboard/recent-bookings?page=${ page }` } ),
		getRecentTransfers: ( page = 1 ) => apiFetch( { path: `${ BASE }/dashboard/recent-transfers?page=${ page }` } ),
	},
	reports: {
		getSummary: ( params ) => {
			const qs = new URLSearchParams( params ).toString();
			return apiFetch( { path: `${ BASE }/reports?${ qs }` } );
		},
	},
	customers: {
		getList: ( params ) => {
			const qs = new URLSearchParams( params ).toString();
			return apiFetch( { path: `${ BASE }/customers?${ qs }` } );
		},
		getDetail: ( id ) =>
			apiFetch( { path: `${ BASE }/customers/${ id }` } ),
		bulkDelete: ( ids ) =>
			apiFetch( { path: `${ BASE }/customers/bulk`, method: 'DELETE', data: { ids } } ),
	},
	messages: {
		getList: ( params ) => {
			const qs = new URLSearchParams( params ).toString();
			return apiFetch( { path: `${ BASE }/messages?${ qs }` } );
		},
		getThread: ( id ) =>
			apiFetch( { path: `${ BASE }/messages/${ id }` } ),
		updateStatus: ( id, status ) =>
			apiFetch( { path: `${ BASE }/messages/${ id }/status`, method: 'POST', data: { status } } ),
		reply: ( id, message, closeThread = false ) =>
			apiFetch( { path: `${ BASE }/messages/${ id }/reply`, method: 'POST', data: { message, close_thread: closeThread } } ),
	},
	about: {
		getData: () => apiFetch( { path: `${ BASE }/about` } ),
	},
	shortcodePages: {
		getList:    ()     => apiFetch( { path: `${ BASE }/shortcode-pages`,                      method: 'GET'    } ),
		createPage: (slug) => apiFetch( { path: `${ BASE }/shortcode-pages/${ slug }/create`,     method: 'POST'   } ),
		deletePage: (slug) => apiFetch( { path: `${ BASE }/shortcode-pages/${ slug }`,            method: 'DELETE' } ),
		clearCache: ()     => apiFetch( { path: `${ BASE }/shortcode-pages/clear-cache`,          method: 'POST'   } ),
		debug:      ()     => apiFetch( { path: `${ BASE }/shortcode-pages/debug`,                method: 'GET'    } ),
		reset:      ()     => apiFetch( { path: `${ BASE }/shortcode-pages/reset`,                method: 'POST'   } ),
	},
	vendorReports: {
		getList:   ( params ) => {
			const qs = new URLSearchParams( params ).toString();
			return apiFetch( { path: `${ BASE }/vendor-reports?${ qs }` } );
		},
		getDetail: ( id ) => apiFetch( { path: `${ BASE }/vendor-reports/${ id }` } ),
	},
};
