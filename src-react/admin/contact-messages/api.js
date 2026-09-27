import apiFetch from '@wordpress/api-fetch';
import { createApiClient } from '../../../vendor/mhm/ui-core/src-react/api/createApiClient';

// Page-local on purpose (plan R-1): the add-on bundles shared/api/rentiva.js,
// so a new key there would change its committed bundles on every Lite merge.
const api = createApiClient( '/mhm-rentiva/v1', apiFetch );

export const contactApi = {
	list: ( params ) => api.get( '/contact-messages', params ),
	get: ( id ) => api.get( `/contact-messages/${ id }` ),
	technical: ( id ) => api.get( `/contact-messages/${ id }/technical` ),
	setStatus: ( id, status ) => api.post( `/contact-messages/${ id }/status`, { status } ),
	markRead: ( id ) => api.post( `/contact-messages/${ id }/read` ),
	bulk: ( ids, action ) => api.post( '/contact-messages/bulk', { ids, action } ),
	trash: ( id ) => api.del( `/contact-messages/${ id }` ),
	destroy: ( id ) => api.del( `/contact-messages/${ id }?force=1` ),
};
