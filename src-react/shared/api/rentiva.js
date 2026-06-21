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
	export: {
		getHistory:  ()       => apiFetch( { path: `${ BASE }/admin/export/history` } ),
		deleteEntry: ( id )   => apiFetch( { path: `${ BASE }/admin/export/${ id }`, method: 'DELETE' } ),
		preview:     ( data ) => apiFetch( { path: `${ BASE }/admin/export/preview`, method: 'POST', data } ),
	},
	vendorManagement: {
		getApplications: ( params ) => {
			const qs = new URLSearchParams( params ).toString();
			return apiFetch( { path: `${ BASE }/vendors/applications?${ qs }` } );
		},
		getApplication: ( id ) => apiFetch( { path: `${ BASE }/vendors/applications/${ id }` } ),
		approveApplication: ( id ) => apiFetch( { path: `${ BASE }/vendors/applications/${ id }/approve`, method: 'POST' } ),
		rejectApplication: ( id, reason ) => apiFetch( {
			path: `${ BASE }/vendors/applications/${ id }/reject`,
			method: 'POST',
			data: { reason },
		} ),
		getIbanRequests: () => apiFetch( { path: `${ BASE }/vendors/iban-requests` } ),
		approveIban: ( vendorId ) => apiFetch( { path: `${ BASE }/vendors/iban-requests/${ vendorId }/approve`, method: 'POST' } ),
		rejectIban: ( vendorId ) => apiFetch( { path: `${ BASE }/vendors/iban-requests/${ vendorId }/reject`, method: 'POST' } ),
		getVendors: ( params ) => {
			const qs = new URLSearchParams( params ).toString();
			return apiFetch( { path: `${ BASE }/vendors/vendors?${ qs }` } );
		},
		getVendorDetail: ( id ) => apiFetch( { path: `${ BASE }/vendors/vendors/${ id }` } ),
		suspendVendor: ( id ) => apiFetch( { path: `${ BASE }/vendors/vendors/${ id }/suspend`, method: 'POST' } ),
		unsuspendVendor: ( id ) => apiFetch( { path: `${ BASE }/vendors/vendors/${ id }/unsuspend`, method: 'POST' } ),
		getCommission: () => apiFetch( { path: `${ BASE }/vendors/commission` } ),
		saveCommission: ( data ) => apiFetch( { path: `${ BASE }/vendors/commission`, method: 'POST', data } ),
		getSettings: () => apiFetch( { path: `${ BASE }/vendors/settings` } ),
		saveSettings: ( data ) => apiFetch( { path: `${ BASE }/vendors/settings`, method: 'POST', data } ),
	},
};
