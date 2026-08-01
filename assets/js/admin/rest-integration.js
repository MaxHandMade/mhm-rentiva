/**
 * Integration settings tab: the endpoint reference list and the settings reset.
 *
 * This file replaces rest-api-keys.js, which also drove an API-key manager.
 * That surface was removed: the screen issued credentials labelled READ / WRITE
 * / ADMIN while no REST route ever validated them, so a key it produced opened
 * nothing. Only the two behaviours that were actually wired remain here.
 */
(function ($) {
    'use strict';

    const integration = {
        strings: window.mhmRestIntegration?.strings || {},
        ajaxUrl: window.mhmRestIntegration?.ajax_url || '',
        nonce: window.mhmRestIntegration?.nonce || '',

        init: function () {
            this.bindEvents();
            this.loadEndpointsList();
        },

        bindEvents: function () {
            $(document).on('click', '#mhm-refresh-endpoints-btn', this.loadEndpointsList.bind(this));
            $(document).on('click', '#mhm-reset-rest-settings-btn', this.handleResetSettings.bind(this));
        },

        handleResetSettings: function (e) {
            e.preventDefault();

            if (!confirm(this.strings.confirm_reset)) {
                return;
            }

            const $btn = $('#mhm-reset-rest-settings-btn');
            const originalText = $btn.html();
            $btn.prop('disabled', true)
                .addClass('mhm-resetting')
                .html('<span class="dashicons dashicons-update mhm-spin"></span> ' + this.strings.resetting);

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhmrentiva_reset_rest_settings',
                    nonce: this.nonce
                },
                success: (response) => {
                    if (response.success) {
                        alert(this.strings.reset_success);

                        if (response.data && response.data.redirect) {
                            window.location.href = response.data.redirect;
                        } else {
                            window.location.reload();
                        }
                    } else {
                        alert(response.data?.message || this.strings.reset_failed);
                        $btn.prop('disabled', false).removeClass('mhm-resetting').html(originalText);
                    }
                },
                error: () => {
                    alert(this.strings.error_occurred);
                    $btn.prop('disabled', false).removeClass('mhm-resetting').html(originalText);
                }
            });
        },

        loadEndpointsList: function () {
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhmrentiva_list_endpoints',
                    nonce: this.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.renderEndpointsList(response.data.endpoints, response.data.namespace);
                    } else {
                        $('#mhm-endpoints-list').text(response.data?.message || this.strings.error_occurred);
                    }
                },
                error: () => {
                    $('#mhm-endpoints-list').text(this.strings.error_occurred);
                }
            });
        },

        renderEndpointsList: function (endpoints, namespace) {
            const $list = $('#mhm-endpoints-list');

            if (!endpoints || endpoints.length === 0) {
                $list.text('');
                return;
            }

            let html = '<p><strong>Namespace:</strong> <code>/wp-json/' + this.escapeHtml(namespace) + '</code></p>';
            html += '<table class="wp-list-table widefat fixed striped">';
            html += '<thead><tr><th>Method</th><th>Endpoint</th><th>Callback</th></tr></thead><tbody>';

            endpoints.forEach((endpoint) => {
                const methodClass = endpoint.method === 'GET' ? 'success'
                    : endpoint.method === 'POST' ? 'warning' : 'info';

                html += '<tr>';
                html += '<td><span class="status-' + methodClass + '">' + this.escapeHtml(endpoint.method) + '</span></td>';
                html += '<td><code>' + this.escapeHtml(endpoint.route) + '</code></td>';
                html += '<td><small>' + this.escapeHtml(endpoint.callback || '-') + '</small></td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            html += '<p><strong>Total:</strong> ' + endpoints.length + '</p>';

            $list.html(html);
        },

        escapeHtml: function (text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text ? String(text).replace(/[&<>"']/g, (m) => map[m]) : '';
        }
    };

    $(document).ready(function () {
        if ($('#mhm-endpoints-list').length) {
            integration.init();
        }
    });
})(jQuery);
