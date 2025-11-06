/**
 * Messages Settings JavaScript
 */

jQuery(document).ready(function ($) {
    'use strict';

    // Tab functionality
    initTabs();

    // Category management
    initCategoryManagement();

    // Status management
    initStatusManagement();

    // Form validation
    initFormValidation();

    /**
     * Initialize tab functionality
     */
    function initTabs() {
        // Tab switching via URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        const activeSubtab = urlParams.get('subtab') || 'email';

        // Show active subtab content
        showSubtab(activeSubtab);

        // Handle subtab clicks (both .nav-tab and .mhm-subtab)
        $('.nav-tab, .mhm-subtab').on('click', function (e) {
            e.preventDefault();
            const href = $(this).attr('href');
            if (href) {
                const urlParams = new URLSearchParams(href.split('?')[1]);
                const subtabId = urlParams.get('subtab');
                if (subtabId) {
                    showSubtab(subtabId);
                }
            }
        });
    }

    /**
     * Show specific subtab content
     */
    function showSubtab(subtabId) {
        // Hide all subtab contents
        $('.tab-content, .mhm-subtab-content').removeClass('active').hide();

        // Remove active class from all subtabs
        $('.nav-tab, .mhm-subtab').removeClass('nav-tab-active active');

        // Show selected subtab content (try both class names for compatibility)
        $('#messages-' + subtabId + ', #' + subtabId).addClass('active').show();

        // Add active class to corresponding subtab
        $('.nav-tab[href*="subtab=' + subtabId + '"], .mhm-subtab[href*="subtab=' + subtabId + '"]').addClass('nav-tab-active active');
    }

    /**
     * Initialize category management
     */
    function initCategoryManagement() {
        let categoryIndex = $('.mhm-category-item').length;

        // Add new category
        $('#add-category-btn').on('click', function () {
            const categoryName = $('#new-category-name').val().trim();

            if (!categoryName) {
                showNotice(window.mhmMessagesSettings?.strings?.enterCategoryName || 'Please enter a category name', 'warning');
                return;
            }

            // Check if category already exists
            const existingCategories = [];
            $('.category-name').each(function () {
                existingCategories.push($(this).val().toLowerCase());
            });

            if (existingCategories.includes(categoryName.toLowerCase())) {
                showNotice(window.mhmMessagesSettings?.strings?.categoryExists || 'This category already exists', 'error');
                return;
            }

            // Generate unique key
            const categoryKey = categoryName.toLowerCase().replace(/[^a-z0-9]/g, '_');

            // Add new category item
            const deleteText = (window.mhmMessagesSettings && window.mhmMessagesSettings.strings && window.mhmMessagesSettings.strings.delete) || 'Delete';
            const newCategoryHtml = `
                <div class="mhm-category-item">
                    <input type="text" name="mhm_rentiva_messages_settings[categories][${categoryKey}]" 
                           value="${categoryName}" class="category-name" required>
                    <button type="button" class="remove-category-btn">${deleteText}</button>
                </div>
            `;

            $('#category-list').append(newCategoryHtml);
            $('#new-category-name').val('');

            // Bind remove event to new button
            $('.remove-category-btn').off('click').on('click', function () {
                $(this).closest('.mhm-category-item').remove();
            });

            categoryIndex++;
        });

        // Remove category
        $('.remove-category-btn').on('click', function () {
            if (confirm(window.mhmMessagesSettings?.strings?.confirmDeleteCategory || 'Are you sure you want to delete this category?')) {
                $(this).closest('.mhm-category-item').remove();
            }
        });
    }

    /**
     * Initialize status management
     */
    function initStatusManagement() {
        let statusIndex = $('.mhm-status-item').length;

        // Add new status
        $('#add-status-btn').on('click', function () {
            const statusName = $('#new-status-name').val().trim();
            const strings = (window.mhmMessagesSettings && window.mhmMessagesSettings.strings) || {};

            if (!statusName) {
                showNotice(strings.enterStatusName || 'Please enter a status name', 'warning');
                return;
            }

            // Check if status already exists
            const existingStatuses = [];
            $('.status-name').each(function () {
                existingStatuses.push($(this).val().toLowerCase());
            });

            if (existingStatuses.includes(statusName.toLowerCase())) {
                showNotice(strings.statusExists || 'This status already exists', 'error');
                return;
            }

            // Generate unique key
            const statusKey = statusName.toLowerCase().replace(/[^a-z0-9]/g, '_');

            // Add new status item
            const deleteText = (window.mhmMessagesSettings && window.mhmMessagesSettings.strings && window.mhmMessagesSettings.strings.delete) || 'Delete';
            const newStatusHtml = `
                <div class="mhm-status-item">
                    <input type="text" name="mhm_rentiva_messages_settings[statuses][${statusKey}]" 
                           value="${statusName}" class="status-name" required>
                    <button type="button" class="remove-status-btn">${deleteText}</button>
                </div>
            `;

            $('#status-list').append(newStatusHtml);
            $('#new-status-name').val('');

            // Bind remove event to new button
            $('.remove-status-btn').off('click').on('click', function () {
                $(this).closest('.mhm-status-item').remove();
            });

            statusIndex++;
        });

        // Remove status
        $('.remove-status-btn').on('click', function () {
            const confirmMsg = (window.mhmMessagesSettings && window.mhmMessagesSettings.strings && window.mhmMessagesSettings.strings.confirmDeleteStatus) || 'Are you sure you want to delete this status?';
            if (confirm(confirmMsg)) {
                $(this).closest('.mhm-status-item').remove();
            }
        });
    }

    /**
     * Initialize form validation
     */
    function initFormValidation() {
        $('#mhm-messages-settings-form').on('submit', function (e) {
            let isValid = true;
            let errorMessage = '';

            // Validate email fields
            const adminEmail = $('input[name*="[admin_email]"]').val().trim();
            const fromEmail = $('input[name*="[from_email]"]').val().trim();

            const strings = (window.mhmMessagesSettings && window.mhmMessagesSettings.strings) || {};

            if (adminEmail && !isValidEmail(adminEmail)) {
                isValid = false;
                errorMessage += (strings.validAdminEmail || 'Enter a valid admin email address') + '.\n';
            }

            if (fromEmail && !isValidEmail(fromEmail)) {
                isValid = false;
                errorMessage += (strings.validFromEmail || 'Enter a valid sender email address') + '.\n';
            }

            // Validate numeric fields
            const maxMessages = parseInt($('input[name*="[dashboard_widget_max_messages]"]').val());
            if (maxMessages && (maxMessages < 1 || maxMessages > 20)) {
                isValid = false;
                errorMessage += (strings.maxMessagesRange || 'Widget max messages must be between 1-20') + '.\n';
            }

            // Validate categories and statuses
            const categoryNames = [];
            $('.category-name').each(function () {
                const name = $(this).val().trim();
                if (name) {
                    if (categoryNames.includes(name.toLowerCase())) {
                        isValid = false;
                        errorMessage += (strings.duplicateCategory || 'Duplicate category names are not allowed') + '.\n';
                    }
                    categoryNames.push(name.toLowerCase());
                }
            });

            const statusNames = [];
            $('.status-name').each(function () {
                const name = $(this).val().trim();
                if (name) {
                    if (statusNames.includes(name.toLowerCase())) {
                        isValid = false;
                        errorMessage += (strings.duplicateStatus || 'Duplicate status names are not allowed') + '.\n';
                    }
                    statusNames.push(name.toLowerCase());
                }
            });

            if (!isValid) {
                e.preventDefault();
                showNotice((strings.formErrors || 'Form errors') + ':\n' + errorMessage, 'error');
                return false;
            }

            // Show loading state
            $('.button-primary').prop('disabled', true).text(strings.saving || 'Saving...');
        });
    }

    /**
     * Email validation helper
     */
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Auto-save functionality (optional)
     */
    function initAutoSave() {
        let autoSaveTimeout;

        $('.tab-content input, .tab-content textarea').on('input', function () {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(function () {
                // Auto-save implementation can be added here
                // Debug log kaldırıldı
            }, 2000);
        });
    }

    /**
     * Keyboard shortcuts
     */
    function initKeyboardShortcuts() {
        $(document).on('keydown', function (e) {
            // Ctrl+S to save
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                $('#mhm-messages-settings-form').submit();
            }

            // Tab navigation with arrow keys
            if (e.altKey && (e.key === 'ArrowLeft' || e.key === 'ArrowRight')) {
                e.preventDefault();
                const currentTab = $('.nav-tab-active').index();
                const tabs = $('.nav-tab');

                let newIndex;
                if (e.key === 'ArrowLeft') {
                    newIndex = currentTab > 0 ? currentTab - 1 : tabs.length - 1;
                } else {
                    newIndex = currentTab < tabs.length - 1 ? currentTab + 1 : 0;
                }

                tabs.eq(newIndex).click();
            }
        });
    }

    /**
     * Show notice message
     */
    function showNotice(message, type) {
        type = type || 'info';
        var noticeClass = 'notice-' + type;
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible" style="position: fixed; top: 32px; right: 20px; z-index: 9999; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);"><p><strong>' + message + '</strong></p></div>');

        // Remove any existing notices first
        $('.notice').remove();

        // Add to body for better visibility
        $('body').append(notice);

        // Auto-dismiss after 5 seconds
        setTimeout(function () {
            notice.fadeOut(500, function () {
                notice.remove();
            });
        }, 5000);
    }

    // Initialize additional features
    initAutoSave();
    initKeyboardShortcuts();
});
