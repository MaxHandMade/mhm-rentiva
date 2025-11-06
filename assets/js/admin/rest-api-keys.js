/**
 * REST API Keys Management JavaScript
 */
(function($) {
    'use strict';

    const api = {
        strings: window.mhmRestApiKeys?.strings || {},
        ajaxUrl: window.mhmRestApiKeys?.ajax_url || '',
        nonce: window.mhmRestApiKeys?.nonce || '',

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.loadKeysList();
            this.loadEndpointsList();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Create API Key
            $(document).on('click', '#mhm-create-api-key-btn', this.handleCreateKey.bind(this));
            
            // Refresh Keys List
            $(document).on('click', '#mhm-refresh-keys-btn', this.loadKeysList.bind(this));
            
            // Refresh Endpoints List
            $(document).on('click', '#mhm-refresh-endpoints-btn', this.loadEndpointsList.bind(this));
            
            // Reset to Defaults
            $(document).on('click', '#mhm-reset-rest-settings-btn', this.handleResetSettings.bind(this));
            
            // Copy Key
            $(document).on('click', '.mhm-copy-key-btn', this.handleCopyKey.bind(this));
            
            // Revoke Key
            $(document).on('click', '.mhm-revoke-key-btn', this.handleRevokeKey.bind(this));
            
            // Delete Key
            $(document).on('click', '.mhm-delete-key-btn', this.handleDeleteKey.bind(this));
        },

        /**
         * Handle Create Key
         */
        handleCreateKey: function(e) {
            e.preventDefault();
            
            const $btn = $('#mhm-create-api-key-btn');
            const name = $('#new_key_name').val().trim();
            const permissions = [];
            
            $('input[name="new_key_permissions[]"]:checked').each(function() {
                permissions.push($(this).val());
            });
            
            if (!name) {
                alert(this.strings.key_name_required || 'Key name is required.');
                return;
            }
            
            if (permissions.length === 0) {
                alert(this.strings.permissions_required || 'Please select at least one permission.');
                return;
            }
            
            $btn.prop('disabled', true).text('Creating...');
            
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhm_create_api_key',
                    nonce: this.nonce,
                    name: name,
                    permissions: permissions
                },
                success: (response) => {
                    if (response.success) {
                        // Show new key in modal/dialog
                        this.showNewKeyModal(response.data.key);
                        
                        // Reset form
                        $('#new_key_name').val('');
                        $('input[name="new_key_permissions[]"]').prop('checked', false);
                        $('input[name="new_key_permissions[]"][value="read"]').prop('checked', true);
                        
                        // Reload keys list
                        this.loadKeysList();
                    } else {
                        alert(response.data?.message || this.strings.failed_create || 'Failed to create API key.');
                    }
                },
                error: () => {
                    alert(this.strings.error_occurred || 'An error occurred. Please try again.');
                },
                complete: () => {
                    $btn.prop('disabled', false).text(this.strings.create_key);
                }
            });
        },

        /**
         * Show new key modal
         */
        showNewKeyModal: function(keyData) {
            const modal = `
                <div id="mhm-new-key-modal">
                    <div class="modal-content">
                        <h2>${this.strings.create_key} - ${this.escapeHtml(keyData.name)}</h2>
                        <p><strong>⚠️ IMPORTANT:</strong> Copy this API key now. You won't be able to see it again!</p>
                        <div class="modal-key-display">
                            ${this.escapeHtml(keyData.key)}
                        </div>
                        <button type="button" class="button button-primary mhm-copy-full-key-btn" data-key="${keyData.key.replace(/"/g, '&quot;')}">${this.strings.copy}</button>
                        <button type="button" class="button mhm-close-modal-btn modal-close-btn">${this.strings.close}</button>
                    </div>
                </div>
            `;
            
            $('body').append(modal);
            
            // Copy button
            $(document).on('click', '.mhm-copy-full-key-btn', function() {
                const key = $(this).data('key');
                const strings = api.strings;
                navigator.clipboard.writeText(key).then(() => {
                    alert(strings.key_copied || 'API key copied to clipboard!');
                });
            });
            
            // Close button
            $(document).on('click', '.mhm-close-modal-btn, #mhm-new-key-modal', function(e) {
                if (e.target === this) {
                    $('#mhm-new-key-modal').remove();
                }
            });
        },

        /**
         * Load Keys List
         */
        loadKeysList: function() {
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhm_list_api_keys',
                    nonce: this.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.renderKeysList(response.data.keys);
                    } else {
                        $('#mhm-api-keys-list').html('<p>' + (response.data?.message || 'Failed to load API keys.') + '</p>');
                    }
                },
                error: () => {
                    $('#mhm-api-keys-list').html('<p>An error occurred. Please try again.</p>');
                }
            });
        },

        /**
         * Render Keys List
         */
        renderKeysList: function(keys) {
            if (!keys || keys.length === 0) {
                $('#mhm-api-keys-list').html('<p>' + this.strings.no_keys + '</p>');
                return;
            }
            
            let html = '<table class="wp-list-table widefat fixed striped">';
            html += '<thead><tr>';
            html += '<th>' + this.strings.key_name + '</th>';
            html += '<th>Key Preview</th>';
            html += '<th>' + this.strings.permissions + '</th>';
            html += '<th>' + this.strings.created + '</th>';
            html += '<th>' + this.strings.last_used + '</th>';
            html += '<th>' + this.strings.status + '</th>';
            html += '<th>' + this.strings.actions + '</th>';
            html += '</tr></thead><tbody>';
            
            keys.forEach((key) => {
                const createdDate = key.created_at ? new Date(key.created_at * 1000).toLocaleDateString() : '-';
                const lastUsedDate = key.last_used_at ? new Date(key.last_used_at * 1000).toLocaleDateString() : 'Never';
                const statusClass = key.status === 'active' ? 'success' : (key.status === 'expired' ? 'warning' : 'error');
                const statusText = key.status.charAt(0).toUpperCase() + key.status.slice(1);
                
                html += '<tr>';
                html += '<td><strong>' + this.escapeHtml(key.name) + '</strong></td>';
                html += '<td><code>' + this.escapeHtml(key.key_preview) + '</code></td>';
                html += '<td>' + key.permissions.join(', ') + '</td>';
                html += '<td>' + createdDate + '</td>';
                html += '<td>' + lastUsedDate + '</td>';
                html += '<td><span class="status-' + statusClass + '">' + statusText + '</span></td>';
                html += '<td>';
                if (key.status === 'active') {
                    html += '<button type="button" class="button button-small mhm-revoke-key-btn" data-key-id="' + this.escapeHtml(key.id) + '">' + this.strings.revoke + '</button> ';
                }
                    html += '<button type="button" class="button button-small mhm-delete-key-btn" data-key-id="' + this.escapeHtml(key.id) + '">' + this.strings.delete + '</button>';
                html += '</td>';
                html += '</tr>';
            });
            
            html += '</tbody></table>';
            
            $('#mhm-api-keys-list').html(html);
        },

        /**
         * Handle Copy Key
         */
        handleCopyKey: function(e) {
            e.preventDefault();
            const key = $(e.target).data('key');
            navigator.clipboard.writeText(key).then(() => {
                alert(this.strings.key_copied);
            });
        },

        /**
         * Handle Revoke Key
         */
        handleRevokeKey: function(e) {
            e.preventDefault();
            
            if (!confirm(this.strings.confirm_revoke)) {
                return;
            }
            
            const keyId = $(e.target).data('key-id');
            
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhm_revoke_api_key',
                    nonce: this.nonce,
                    key_id: keyId
                },
                success: (response) => {
                    if (response.success) {
                        this.loadKeysList();
                    } else {
                        alert(response.data?.message || this.strings.failed_revoke || 'Failed to revoke API key.');
                    }
                },
                error: () => {
                    alert(this.strings.error_occurred || 'An error occurred. Please try again.');
                }
            });
        },

        /**
         * Handle Delete Key
         */
        handleDeleteKey: function(e) {
            e.preventDefault();
            
            if (!confirm(this.strings.confirm_delete)) {
                return;
            }
            
            const keyId = $(e.target).data('key-id');
            
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhm_delete_api_key',
                    nonce: this.nonce,
                    key_id: keyId
                },
                success: (response) => {
                    if (response.success) {
                        this.loadKeysList();
                    } else {
                        alert(response.data?.message || this.strings.failed_delete || 'Failed to delete API key.');
                    }
                },
                error: () => {
                    alert(this.strings.error_occurred || 'An error occurred. Please try again.');
                }
            });
        },

        /**
         * Handle Reset to Defaults
         */
        handleResetSettings: function(e) {
            e.preventDefault();
            
            if (!confirm(this.strings.confirm_reset || 'Are you sure you want to reset all REST API settings to default values? This action cannot be undone.')) {
                return;
            }
            
            const $btn = $('#mhm-reset-rest-settings-btn');
            const originalText = $btn.html();
            $btn.prop('disabled', true).addClass('mhm-resetting').html('<span class="dashicons dashicons-update mhm-spin"></span> ' + (this.strings.resetting || 'Resetting...'));
            
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhm_reset_rest_settings',
                    nonce: this.nonce
                },
                success: (response) => {
                    if (response.success) {
                        alert(this.strings.reset_success || 'Settings reset to defaults successfully. Page will reload...');
                        
                        // Reload page to show new default values
                        if (response.data && response.data.redirect) {
                            window.location.href = response.data.redirect;
                        } else {
                            window.location.reload();
                        }
                    } else {
                        alert(response.data?.message || this.strings.reset_failed || 'Failed to reset settings to defaults.');
                        $btn.prop('disabled', false).html(originalText);
                    }
                },
                error: () => {
                    alert(this.strings.error_occurred || 'An error occurred. Please try again.');
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },

        /**
         * Load Endpoints List
         */
        loadEndpointsList: function() {
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhm_list_endpoints',
                    nonce: this.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.renderEndpointsList(response.data.endpoints, response.data.namespace);
                    } else {
                        $('#mhm-endpoints-list').html('<p>Failed to load endpoints.</p>');
                    }
                },
                error: () => {
                    $('#mhm-endpoints-list').html('<p>An error occurred. Please try again.</p>');
                }
            });
        },

        /**
         * Render Endpoints List
         */
        renderEndpointsList: function(endpoints, namespace) {
            if (!endpoints || endpoints.length === 0) {
                $('#mhm-endpoints-list').html('<p>No endpoints found.</p>');
                return;
            }
            
            let html = '<p><strong>Namespace:</strong> <code>/wp-json/' + this.escapeHtml(namespace) + '</code></p>';
            html += '<table class="wp-list-table widefat fixed striped">';
            html += '<thead><tr>';
            html += '<th>Method</th>';
            html += '<th>Endpoint</th>';
            html += '<th>Callback</th>';
            html += '</tr></thead><tbody>';
            
            endpoints.forEach((endpoint) => {
                const methodClass = endpoint.method === 'GET' ? 'success' : 
                                   endpoint.method === 'POST' ? 'warning' : 'info';
                
                html += '<tr>';
                html += '<td><span class="status-' + methodClass + '">' + this.escapeHtml(endpoint.method) + '</span></td>';
                html += '<td><code>' + this.escapeHtml(endpoint.route) + '</code></td>';
                html += '<td><small>' + this.escapeHtml(endpoint.callback || '-') + '</small></td>';
                html += '</tr>';
            });
            
            html += '</tbody></table>';
            html += '<p><strong>Total:</strong> ' + endpoints.length + ' endpoints</p>';
            
            $('#mhm-endpoints-list').html(html);
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text ? text.replace(/[&<>"']/g, (m) => map[m]) : '';
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        // Only initialize on settings page
        if ($('#mhm-api-keys-list-container').length || $('#mhm-endpoints-list').length) {
            api.init();
        }
    });

})(jQuery);

