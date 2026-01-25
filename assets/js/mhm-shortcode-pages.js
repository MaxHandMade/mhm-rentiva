/**
 * MHM Rentiva Shortcode Pages Admin Scripts
 */
const MHMRentivaShortcodes = (function () {
    'use strict';

    // Config will be provided via wp_localize_script (mhmShortcodePages)
    const config = window.mhmShortcodePages || {};

    const setupFormData = (actionKey) => {
        const formData = new FormData();
        formData.append('action', config.actions[actionKey]);
        formData.append('nonce', config.nonces[actionKey]);
        return formData;
    };

    return {
        clearCache: async function () {
            if (!confirm(config.i18n.confirmClearCache)) return;

            const data = setupFormData('clearCache');
            try {
                const resp = await fetch(config.ajaxUrl, { method: 'POST', body: data });
                const json = await resp.json();
                if (json.success) {
                    location.reload();
                } else {
                    alert(json.data?.message || 'Error');
                }
            } catch (e) {
                console.error('MHM Rentiva: Clear Cache Error', e);
            }
        },

        createPage: async function (shortcode) {
            if (!confirm(config.i18n.confirmCreatePage)) return;

            const btn = event.target;
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = config.i18n.creatingText;

            const data = setupFormData('createPage');
            data.append('shortcode', shortcode);

            try {
                const resp = await fetch(config.ajaxUrl, { method: 'POST', body: data });
                const json = await resp.json();
                if (json.success) {
                    if (confirm(config.i18n.confirmGoToEditor)) {
                        window.open(json.data.edit_url, '_blank');
                    }
                    location.reload();
                } else {
                    alert(json.data?.message || 'Error');
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            } catch (e) {
                console.error('MHM Rentiva: Create Page Error', e);
                btn.disabled = false;
                btn.textContent = originalText;
            }
        },

        deletePage: async function (pageId, title) {
            if (!confirm(config.i18n.confirmDeletePage + '\n' + title)) return;

            const data = setupFormData('deletePage');
            data.append('page_id', pageId);

            try {
                const resp = await fetch(config.ajaxUrl, { method: 'POST', body: data });
                const json = await resp.json();
                if (json.success) {
                    location.reload();
                } else {
                    alert(json.data?.message || 'Error');
                }
            } catch (e) {
                console.error('MHM Rentiva: Delete Page Error', e);
            }
        },

        debugSearch: async function () {
            const data = setupFormData('debugSearch');
            try {
                const resp = await fetch(config.ajaxUrl, { method: 'POST', body: data });
                const json = await resp.json();
                if (json.success) {
                    let msg = json.data.message + '\n\n';
                    if (json.data.pages && json.data.pages.length > 0) {
                        json.data.pages.forEach(p => msg += `ID: ${p.id} - ${p.title}\n`);
                    }
                    alert(msg);
                } else {
                    alert(json.data?.message || 'Error');
                }
            } catch (e) {
                console.error('MHM Rentiva: Debug Search Error', e);
            }
        }
    };
})();
