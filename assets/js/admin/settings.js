/**
 * Messages Settings JavaScript
 */

jQuery(document).ready(function ($) {
    initializeSettings();
});

/**
 * Initialize settings functionality
 */
function initializeSettings() {
    // Tab switching functionality
    initializeTabs();

    // Category management
    initializeCategoryManagement();

    // Status management
    initializeStatusManagement();

    // Email template management
    initializeEmailTemplates();
    
    // Reset to defaults functionality
    initializeResetToDefaults();
}

/**
 * Initialize tab switching
 */
function initializeTabs() {
    const navTabs = document.querySelectorAll('.nav-tab');
    const tabContents = document.querySelectorAll('.tab-content');

    navTabs.forEach(tab => {
        tab.addEventListener('click', function (e) {
            // Only intercept in-page tab anchors (href starts with '#').
            const href = this.getAttribute('href') || '';
            const isAnchor = href.indexOf('#') === 0;
            if (!isAnchor) {
                return; // allow normal navigation (e.g., email templates tabs)
            }
            e.preventDefault();

            // Remove active class from all tabs and contents
            navTabs.forEach(t => t.classList.remove('nav-tab-active'));
            tabContents.forEach(c => c.classList.remove('active'));

            // Add active class to clicked tab and corresponding content
            this.classList.add('nav-tab-active');
            const targetId = href.substring(1);
            const targetContent = document.getElementById(targetId);
            if (targetContent) {
                targetContent.classList.add('active');
            }
        });
    });
}

/**
 * Initialize category management
 */
function initializeCategoryManagement() {
    // Add category
    jQuery(document).on('click', '#add-category-btn', function () {
        const input = jQuery('#new-category-name');
        const name = input.val().trim();

        if (name === '') {
            const emptyMsg = (window.mhmRentivaSettings && window.mhmRentivaSettings.strings && window.mhmRentivaSettings.strings.categoryEmpty) || 'Category name cannot be empty';
            showNotification(emptyMsg, 'error');
            return;
        }

        if (isCategoryExists(name)) {
            const existsMsg = (window.mhmRentivaSettings && window.mhmRentivaSettings.strings && window.mhmRentivaSettings.strings.categoryExists) || 'This category already exists';
            showNotification(existsMsg, 'error');
            return;
        }

        addCategory(name);
        input.val('');
    });

    // Remove category
    jQuery(document).on('click', '.remove-category-btn', function () {
        const categoryItem = jQuery(this).closest('.mhm-category-item');
        const categoryName = categoryItem.find('.category-name').val();

        const confirmMsg = (window.mhmRentivaSettings && window.mhmRentivaSettings.strings && window.mhmRentivaSettings.strings.confirmDeleteCategory) || 'Are you sure you want to delete this category?';
        if (confirm(confirmMsg)) {
            categoryItem.remove();
            updateCategoryInputs();
        }
    });

    // Category name change
    jQuery(document).on('input', '.category-name', function () {
        updateCategoryInputs();
    });
}

/**
 * Initialize status management
 */
function initializeStatusManagement() {
    // Add status
    jQuery(document).on('click', '#add-status-btn', function () {
        const input = jQuery('#new-status-name');
        const name = input.val().trim();

        if (name === '') {
            const emptyMsg = (window.mhmRentivaSettings && window.mhmRentivaSettings.strings && window.mhmRentivaSettings.strings.statusEmpty) || 'Status name cannot be empty';
            showNotification(emptyMsg, 'error');
            return;
        }

        if (isStatusExists(name)) {
            const existsMsg = (window.mhmRentivaSettings && window.mhmRentivaSettings.strings && window.mhmRentivaSettings.strings.statusExists) || 'This status already exists';
            showNotification(existsMsg, 'error');
            return;
        }

        addStatus(name);
        input.val('');
    });

    // Remove status
    jQuery(document).on('click', '.remove-status-btn', function () {
        const statusItem = jQuery(this).closest('.mhm-status-item');
        const statusName = statusItem.find('.status-name').val();

        const confirmMsg = (window.mhmRentivaSettings && window.mhmRentivaSettings.strings && window.mhmRentivaSettings.strings.confirmDeleteStatus) || 'Are you sure you want to delete this status?';
        if (confirm(confirmMsg)) {
            statusItem.remove();
            updateStatusInputs();
        }
    });

    // Status name change
    jQuery(document).on('input', '.status-name', function () {
        updateStatusInputs();
    });
}

/**
 * Initialize email templates
 */
function initializeEmailTemplates() {
    // Template preview
    jQuery(document).on('click', '.template-preview-btn', function () {
        const templateType = jQuery(this).data('template');
        previewTemplate(templateType);
    });

    // Template reset
    jQuery(document).on('click', '.template-reset-btn', function () {
        const templateType = jQuery(this).data('template');
        const confirmMsg = (window.mhmRentivaSettings && window.mhmRentivaSettings.strings && window.mhmRentivaSettings.strings.confirmResetTemplate) || 'Are you sure you want to reset this template to default?';
        if (confirm(confirmMsg)) {
            resetTemplate(templateType);
        }
    });
}

/**
 * Add new category
 */
function addCategory(name) {
    const categoryList = jQuery('#category-list');
    const categoryIndex = categoryList.find('.mhm-category-item').length;

    const deleteText = (window.mhmRentivaSettings && window.mhmRentivaSettings.strings && window.mhmRentivaSettings.strings.delete) || 'Delete';
    const categoryHtml = `
                <div class="mhm-category-item">
                    <input type="text" name="categories[${categoryIndex}][name]" class="category-name" value="${escapeHtml(name)}" required>
                    <input type="color" name="categories[${categoryIndex}][color]" value="#2271b1">
                    <button type="button" class="remove-category-btn">${deleteText}</button>
                </div>
            `;

    categoryList.append(categoryHtml);
    updateCategoryInputs();
}

/**
 * Add new status
 */
function addStatus(name) {
    const statusList = jQuery('#status-list');
    const statusIndex = statusList.find('.mhm-status-item').length;

    const deleteText = (window.mhmRentivaSettings && window.mhmRentivaSettings.strings && window.mhmRentivaSettings.strings.delete) || 'Delete';
    const statusHtml = `
                <div class="mhm-status-item">
                    <input type="text" name="statuses[${statusIndex}][name]" class="status-name" value="${escapeHtml(name)}" required>
                    <input type="color" name="statuses[${statusIndex}][color]" value="#2271b1">
                    <button type="button" class="remove-status-btn">${deleteText}</button>
                </div>
            `;

    statusList.append(statusHtml);
    updateStatusInputs();
}

/**
 * Check if category exists
 */
function isCategoryExists(name) {
    let exists = false;
    jQuery('.category-name').each(function () {
        if (jQuery(this).val().toLowerCase() === name.toLowerCase()) {
            exists = true;
            return false;
        }
    });
    return exists;
}

/**
 * Check if status exists
 */
function isStatusExists(name) {
    let exists = false;
    jQuery('.status-name').each(function () {
        if (jQuery(this).val().toLowerCase() === name.toLowerCase()) {
            exists = true;
            return false;
        }
    });
    return exists;
}

/**
 * Update category inputs
 */
function updateCategoryInputs() {
    jQuery('#category-list .mhm-category-item').each(function (index) {
        jQuery(this).find('input[type="text"]').attr('name', `categories[${index}][name]`);
        jQuery(this).find('input[type="color"]').attr('name', `categories[${index}][color]`);
    });
}

/**
 * Update status inputs
 */
function updateStatusInputs() {
    jQuery('#status-list .mhm-status-item').each(function (index) {
        jQuery(this).find('input[type="text"]').attr('name', `statuses[${index}][name]`);
        jQuery(this).find('input[type="color"]').attr('name', `statuses[${index}][color]`);
    });
}

/**
 * Preview email template
 */
function previewTemplate(templateType) {
    const textarea = jQuery(`#${templateType}_template`);
    const content = textarea.val();

    if (content.trim() === '') {
        const emptyMsg = (window.mhmRentivaSettings && window.mhmRentivaSettings.strings && window.mhmRentivaSettings.strings.templateEmpty) || 'Template content is empty';
        showNotification(emptyMsg, 'warning');
        return;
    }

    const previewTitle = (window.mhmRentivaSettings && window.mhmRentivaSettings.strings && window.mhmRentivaSettings.strings.templatePreview) || 'Template Preview';

    // Create preview window
    const previewWindow = window.open('', '_blank', 'width=800,height=600');
    previewWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${previewTitle} - ${templateType}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .preview-content { border: 1px solid #ddd; padding: 20px; background: #fff; }
                .preview-header { background: #f8f9fa; padding: 10px; border-bottom: 1px solid #ddd; margin-bottom: 20px; }
            </style>
        </head>
        <body>
            <div class="preview-header">
                <h3>${previewTitle}: ${templateType}</h3>
            </div>
            <div class="preview-content">
                ${content.replace(/\n/g, '<br>')}
            </div>
        </body>
        </html>
    `);
    previewWindow.document.close();
}

/**
 * Reset template to default
 */
function resetTemplate(templateType) {
    // Get default template content (you might want to load this via AJAX)
    const strings = (window.mhmRentivaSettings && window.mhmRentivaSettings.strings) || {};
    const defaultTemplates = {
        'admin_new_message': strings.defaultNewMessage || 'New message received: {{subject}}',
        'customer_reply': strings.defaultReply || 'Reply to your message: {{subject}}',
        'customer_status_change': strings.defaultStatusChange || 'Message status changed: {{subject}}',
        'auto_reply': strings.defaultAutoReply || 'Your message received: {{subject}}'
    };

    const defaultContent = defaultTemplates[templateType] || '';
    jQuery(`#${templateType}_template`).val(defaultContent);

    const successMsg = strings.templateResetSuccess || 'Template reset to default';
    showNotification(successMsg, 'success');
}

/**
 * Escape HTML
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function (m) { return map[m]; });
}

/**
 * Initialize reset to defaults functionality
 */
function initializeResetToDefaults() {
    jQuery(document).on('click', '.mhm-reset-tab-btn', function(e) {
        e.preventDefault();
        
        const $btn = jQuery(this);
        const tab = $btn.data('tab');
        
        const strings = window.mhmRentivaSettings?.strings || {};
        const confirmMsg = strings.confirmResetTab || 'Are you sure you want to reset this tab\'s settings to default values? This action cannot be undone.';
        
        if (!confirm(confirmMsg)) {
            return;
        }
        
        const originalText = $btn.html();
        $btn.prop('disabled', true).addClass('mhm-resetting').html('<span class="dashicons dashicons-update mhm-spin"></span> ' + (strings.resetting || 'Resetting...'));
        
        jQuery.ajax({
            url: window.mhmRentivaSettings?.ajaxUrl || ajaxurl,
            type: 'POST',
            data: {
                action: 'mhm_reset_settings_tab',
                tab: tab,
                nonce: window.mhmRentivaSettings?.nonce || ''
            },
            success: function(response) {
                if (response.success) {
                    const successMsg = strings.resetSuccess || 'Settings reset to defaults successfully. Page will reload...';
                    alert(successMsg);
                    
                    // Reload page to show new default values
                    if (response.data && response.data.redirect) {
                        window.location.href = response.data.redirect;
                    } else {
                        window.location.reload();
                    }
                } else {
                    const errorMsg = response.data?.message || strings.resetFailed || 'Failed to reset settings to defaults.';
                    alert(errorMsg);
                    $btn.prop('disabled', false).removeClass('mhm-resetting').html(originalText);
                }
            },
            error: function() {
                const errorMsg = strings.errorOccurred || 'An error occurred. Please try again.';
                alert(errorMsg);
                $btn.prop('disabled', false).removeClass('mhm-resetting').html(originalText);
            }
        });
    });
}

/**
 * Show notification
 */
function showNotification(message, type) {
    // Create notification element
    const notification = jQuery('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');

    // Add to page
    jQuery('.wrap').prepend(notification);

    // Auto dismiss after 5 seconds
    setTimeout(function () {
        notification.fadeOut(function () {
            notification.remove();
        });
    }, 5000);

    // Add dismiss functionality
    notification.find('.notice-dismiss').on('click', function () {
        notification.fadeOut(function () {
            notification.remove();
        });
    });
}

/**
 * Form validation
 */
function validateSettingsForm() {
    let isValid = true;
    let errorMessages = [];

    // Check required fields
    const strings = (window.mhmRentivaSettings && window.mhmRentivaSettings.strings) || {};

    const requiredFields = [
        { selector: '#admin_email', message: strings.adminEmailRequired || 'Admin email address is required' },
        { selector: '#from_name', message: strings.fromNameRequired || 'Sender name is required' },
        { selector: '#from_email', message: strings.fromEmailRequired || 'Sender email address is required' }
    ];

    requiredFields.forEach(function (field) {
        const value = jQuery(field.selector).val().trim();
        if (value === '') {
            errorMessages.push(field.message);
            isValid = false;
        }
    });

    // Check email format
    const emailFields = ['#admin_email', '#from_email'];
    emailFields.forEach(function (field) {
        const value = jQuery(field).val().trim();
        if (value !== '' && !isValidEmail(value)) {
            const emailMsg = (window.mhmRentivaSettings && window.mhmRentivaSettings.strings && window.mhmRentivaSettings.strings.validEmail) || 'Enter a valid email address';
            errorMessages.push(emailMsg);
            isValid = false;
        }
    });

    // Check categories
    const categories = jQuery('#category-list .category-name');
    if (categories.length === 0) {
        errorMessages.push(strings.minOneCategory || 'At least one category must be defined');
        isValid = false;
    }

    // Check statuses
    const statuses = jQuery('#status-list .status-name');
    if (statuses.length === 0) {
        errorMessages.push(strings.minOneStatus || 'At least one status must be defined');
        isValid = false;
    }

    if (!isValid) {
        errorMessages.forEach(function (message) {
            showNotification(message, 'error');
        });
    }

    return isValid;
}

/**
 * Validate email format
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Form submission validation
jQuery('#mhm-messages-settings-form').on('submit', function (e) {
    if (!validateSettingsForm()) {
        e.preventDefault();
        return false;
    }
});
