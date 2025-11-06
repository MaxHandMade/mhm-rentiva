/**
 * MHM Rentiva - Customers Page JavaScript
 * 
 * This file contains interactive features for the customers page.
 * Filtering, search, bulk actions and AJAX operations.
 */

(function ($) {
    'use strict';

    // Global variables
    let customersData = {};
    let isLoading = false;

    /**
     * Main function to run when DOM is loaded
     */
    $(document).ready(function () {
        initializeCustomersPage();
        bindEvents();
        initializeFilters();
        initializeBulkActions();
        loadCustomerStats();
    });

    /**
     * Initialize customers page
     */
    function initializeCustomersPage() {
        // Check page loading status
        if ($('.customers-page').length === 0) {
            return;
        }

        // Show loading state
        showLoadingState();

        // Load customer data
        loadCustomersData();
    }

    /**
     * Bind event listeners
     */
    function bindEvents() {
        // Filter form submission
        $(document).on('submit', '.customers-table-filters form', handleFilterSubmit);

        // Filter changes
        $(document).on('change', '.customers-table-filters select, .customers-table-filters input', handleFilterChange);

        // Bulk actionlar sadece Customers sayfasında aktif olmalı
        if ($('.customers-page').length) {
            $(document).on('change', '#bulk-action-selector-top, #bulk-action-selector-bottom', handleBulkActionChange);
            $(document).on('click', '#doaction, #doaction2', handleBulkActionSubmit);
        }

        // Customer row click
        $(document).on('click', '.customer-row', handleCustomerRowClick);

        // Export button
        $(document).on('click', '.export-customers', handleExportCustomers);

        // Refresh button
        $(document).on('click', '.refresh-customers', handleRefreshCustomers);

        // Pagination links
        $(document).on('click', '.tablenav-pages a', handlePaginationClick);

        // Search input
        $(document).on('input', '.customers-search-input', debounce(handleSearchInput, 300));

        // Customer details modal
        $(document).on('click', '.view-customer-details', handleViewCustomerDetails);

        // Modal close
        $(document).on('click', '.modal-close, .modal-overlay', handleModalClose);
    }

    /**
     * Filtreleri başlat
     */
    function initializeFilters() {
        // URL parametrelerinden filtreleri yükle
        const urlParams = new URLSearchParams(window.location.search);

        // Tarih filtresi
        const dateFrom = urlParams.get('date_from');
        const dateTo = urlParams.get('date_to');

        if (dateFrom) {
            $('input[name="date_from"]').val(dateFrom);
        }
        if (dateTo) {
            $('input[name="date_to"]').val(dateTo);
        }

        // Durum filtresi
        const status = urlParams.get('status');
        if (status) {
            $('select[name="status"]').val(status);
        }

        // Arama filtresi
        const search = urlParams.get('search');
        if (search) {
            $('.customers-search-input').val(search);
        }
    }

    /**
     * Bulk actions'ları başlat
     */
    function initializeBulkActions() {
        // Bulk action seçeneklerini güncelle
        updateBulkActionOptions();

        // Checkbox'ları kontrol et
        updateBulkActionButtons();
    }

    /**
     * Müşteri istatistiklerini yükle
     */
    function loadCustomerStats() {
        if ($('.customer-stats-grid').length === 0) {
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mhm_rentiva_get_customer_stats',
                nonce: window.mhm_rentiva_customers.nonce
            },
            success: function (response) {
                if (response.success) {
                    updateCustomerStats(response.data);
                } else {
                    const errorMsg = (window.mhm_rentiva_customers && window.mhm_rentiva_customers.strings && window.mhm_rentiva_customers.strings.statsError) || 'Error loading statistics';
                    showError(errorMsg + ': ' + response.data);
                }
            },
            error: function () {
                const errorMsg = (window.mhm_rentiva_customers && window.mhm_rentiva_customers.strings && window.mhm_rentiva_customers.strings.statsError) || 'An error occurred while loading statistics';
                showError(errorMsg);
            }
        });
    }

    /**
     * Müşteri verilerini yükle
     */
    function loadCustomersData() {
        if (isLoading) {
            return;
        }

        isLoading = true;

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mhm_rentiva_get_customers_data',
                nonce: window.mhm_rentiva_customers.nonce,
                filters: getCurrentFilters()
            },
            success: function (response) {
                if (response.success) {
                    customersData = response.data;
                    updateCustomersTable(response.data);
                    hideLoadingState();
                } else {
                    const errorMsg = (window.mhm_rentiva_customers && window.mhm_rentiva_customers.strings && window.mhm_rentiva_customers.strings.loadError) || 'Error loading customer data';
                    showError(errorMsg + ': ' + response.data);
                    hideLoadingState();
                }
            },
            error: function () {
                const errorMsg = (window.mhm_rentiva_customers && window.mhm_rentiva_customers.strings && window.mhm_rentiva_customers.strings.loadError) || 'An error occurred while loading customer data';
                showError(errorMsg);
                hideLoadingState();
            },
            complete: function () {
                isLoading = false;
            }
        });
    }

    /**
     * Filtre formu gönderimi
     */
    function handleFilterSubmit(e) {
        e.preventDefault();

        const filters = getCurrentFilters();
        const url = new URL(window.location);

        // URL parametrelerini güncelle
        Object.keys(filters).forEach(key => {
            if (filters[key]) {
                url.searchParams.set(key, filters[key]);
            } else {
                url.searchParams.delete(key);
            }
        });

        // Sayfayı yenile
        window.location.href = url.toString();
    }

    /**
     * Filtre değişikliği
     */
    function handleFilterChange() {
        // Debounced olarak filtreleri uygula
        debounce(applyFilters, 500)();
    }

    /**
     * Bulk action değişikliği
     */
    function handleBulkActionChange() {
        updateBulkActionButtons();
    }

    /**
     * Bulk action gönderimi
     */
    function handleBulkActionSubmit(e) {
        e.preventDefault();

        const action = $('#bulk-action-selector-top').val() || $('#bulk-action-selector-bottom').val();
        const selectedCustomers = $('input[name="customer[]"]:checked').map(function () {
            return $(this).val();
        }).get();

        if (!action || action === '-1') {
            const selectMsg = (window.mhm_rentiva_customers && window.mhm_rentiva_customers.strings && window.mhm_rentiva_customers.strings.selectAction) || 'Please select an action';
            showError(selectMsg);
            return;
        }

        if (selectedCustomers.length === 0) {
            const selectMsg = (window.mhm_rentiva_customers && window.mhm_rentiva_customers.strings && window.mhm_rentiva_customers.strings.selectCustomer) || 'Please select at least one customer';
            showError(selectMsg);
            return;
        }

        // Onay iste
        const actionText = getBulkActionText(action);
        const confirmMsg = (window.mhm_rentiva_customers && window.mhm_rentiva_customers.strings && window.mhm_rentiva_customers.strings.confirmBulkAction) ||
            `Are you sure you want to perform "${actionText}" action for ${selectedCustomers.length} customer(s)?`;
        if (!confirm(confirmMsg.replace('%d', selectedCustomers.length).replace('%s', actionText))) {
            return;
        }

        // Bulk action'ı gerçekleştir
        performBulkAction(action, selectedCustomers);
    }

    /**
     * Müşteri satırı tıklama
     */
    function handleCustomerRowClick(e) {
        // Link tıklamalarını engelle
        if ($(e.target).is('a') || $(e.target).closest('a').length) {
            return;
        }

        const customerId = $(this).data('customer-id');
        if (customerId) {
            viewCustomerDetails(customerId);
        }
    }

    /**
     * Export müşteriler
     */
    function handleExportCustomers(e) {
        e.preventDefault();

        const selectedCustomers = $('input[name="customer[]"]:checked').map(function () {
            return $(this).val();
        }).get();

        const filters = getCurrentFilters();

        // Export URL'ini oluştur
        const exportUrl = new URL(ajaxurl);
        exportUrl.searchParams.set('action', 'mhm_rentiva_export_customers');
        exportUrl.searchParams.set('nonce', window.mhm_rentiva_customers.nonce);

        if (selectedCustomers.length > 0) {
            exportUrl.searchParams.set('customer_ids', selectedCustomers.join(','));
        }

        Object.keys(filters).forEach(key => {
            if (filters[key]) {
                exportUrl.searchParams.set(key, filters[key]);
            }
        });

        // Export'u başlat
        window.open(exportUrl.toString(), '_blank');

        const successMsg = (window.mhm_rentiva_customers && window.mhm_rentiva_customers.strings && window.mhm_rentiva_customers.strings.exportStarted) || 'Export process started';
        showSuccess(successMsg);
    }

    /**
     * Müşterileri yenile
     */
    function handleRefreshCustomers(e) {
        e.preventDefault();

        showLoadingState();
        loadCustomersData();
        loadCustomerStats();
    }

    /**
     * Pagination tıklama
     */
    function handlePaginationClick(e) {
        e.preventDefault();

        const url = $(this).attr('href');
        if (url) {
            window.location.href = url;
        }
    }

    /**
     * Arama inputu
     */
    function handleSearchInput() {
        const searchTerm = $(this).val();
        const url = new URL(window.location);

        if (searchTerm) {
            url.searchParams.set('search', searchTerm);
        } else {
            url.searchParams.delete('search');
        }

        // Debounced olarak aramayı uygula
        debounce(() => {
            window.location.href = url.toString();
        }, 500)();
    }

    /**
     * Müşteri detaylarını görüntüle
     */
    function handleViewCustomerDetails(e) {
        e.preventDefault();

        const customerId = $(this).data('customer-id');
        if (customerId) {
            viewCustomerDetails(customerId);
        }
    }

    /**
     * Modal kapatma
     */
    function handleModalClose(e) {
        e.preventDefault();
        closeModal();
    }

    /**
     * Yardımcı fonksiyonlar
     */

    /**
     * Mevcut filtreleri al
     */
    function getCurrentFilters() {
        return {
            date_from: $('input[name="date_from"]').val(),
            date_to: $('input[name="date_to"]').val(),
            status: $('select[name="status"]').val(),
            search: $('.customers-search-input').val(),
            page: $('input[name="paged"]').val() || 1
        };
    }

    /**
     * Filtreleri uygula
     */
    function applyFilters() {
        showLoadingState();
        loadCustomersData();
    }

    /**
     * Bulk action seçeneklerini güncelle
     */
    function updateBulkActionOptions() {
        const bulkText = (window.mhm_rentiva_customers && window.mhm_rentiva_customers.bulkActions) || {};
        const options = [
            { value: '-1', text: bulkText.bulk_actions || 'Bulk Actions' },
            { value: 'export', text: bulkText.export || 'Export' },
            { value: 'delete', text: bulkText.delete || 'Delete' },
            { value: 'mark_active', text: bulkText.mark_active || 'Mark as Active' },
            { value: 'mark_inactive', text: bulkText.mark_inactive || 'Mark as Inactive' }
        ];

        const selectors = ['#bulk-action-selector-top', '#bulk-action-selector-bottom'];

        selectors.forEach(selector => {
            const $select = $(selector);
            if ($select.length) {
                $select.empty();
                options.forEach(option => {
                    $select.append(`<option value="${option.value}">${option.text}</option>`);
                });
            }
        });
    }

    /**
     * Bulk action butonlarını güncelle
     */
    function updateBulkActionButtons() {
        const selectedCount = $('input[name="customer[]"]:checked').length;
        const action = $('#bulk-action-selector-top').val() || $('#bulk-action-selector-bottom').val();

        $('.bulkactions .button').prop('disabled', selectedCount === 0 || !action || action === '-1');

        // Seçili müşteri sayısını göster
        if (selectedCount > 0) {
            const selectedText = (window.mhm_rentiva_customers && window.mhm_rentiva_customers.strings && window.mhm_rentiva_customers.strings.selected) || 'selected';
            $('.bulkactions .selected-count').remove();
            $('.bulkactions').prepend(`<span class="selected-count" style="margin-right: 10px; color: #667eea; font-weight: 500;">${selectedCount} ${selectedText}</span>`);
        } else {
            $('.bulkactions .selected-count').remove();
        }
    }

    /**
     * Bulk action'ı gerçekleştir
     */
    function performBulkAction(action, customerIds) {
        showLoadingState();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mhm_rentiva_bulk_action_customers',
                nonce: window.mhm_rentiva_customers.nonce,
                bulk_action: action,
                customer_ids: customerIds
            },
            success: function (response) {
                if (response.success) {
                    showSuccess(response.data.message || 'Operation completed successfully.');
                    loadCustomersData();
                    loadCustomerStats();
                } else {
                    const errorMsg = (window.mhm_rentiva_customers && window.mhm_rentiva_customers.strings && window.mhm_rentiva_customers.strings.actionError) || 'Error performing action';
                    showError(response.data || errorMsg);
                }
                hideLoadingState();
            },
            error: function () {
                const errorMsg = (window.mhm_rentiva_customers && window.mhm_rentiva_customers.strings && window.mhm_rentiva_customers.strings.actionError) || 'An error occurred while performing action';
                showError(errorMsg);
                hideLoadingState();
            }
        });
    }

    /**
     * Bulk action metnini al
     */
    function getBulkActionText(action) {
        const actions = (window.mhm_rentiva_customers && window.mhm_rentiva_customers.bulkActions) || {
            'export': 'Export',
            'delete': 'Delete',
            'mark_active': 'Mark as Active',
            'mark_inactive': 'Mark as Inactive'
        };

        return actions[action] || action;
    }

    /**
     * Müşteri detaylarını görüntüle
     */
    function viewCustomerDetails(customerId) {
        showLoadingState();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mhm_rentiva_get_customer_details',
                nonce: window.mhm_rentiva_customers.nonce,
                customer_id: customerId
            },
            success: function (response) {
                if (response.success) {
                    showCustomerModal(response.data);
                } else {
                    const errorMsg = (window.mhm_rentiva_customers && window.mhm_rentiva_customers.strings && window.mhm_rentiva_customers.strings.detailsError) || 'Error loading customer details';
                    showError(errorMsg + ': ' + response.data);
                }
                hideLoadingState();
            },
            error: function () {
                const errorMsg = (window.mhm_rentiva_customers && window.mhm_rentiva_customers.strings && window.mhm_rentiva_customers.strings.detailsError) || 'An error occurred while loading customer details';
                showError(errorMsg);
                hideLoadingState();
            }
        });
    }

    /**
     * Müşteri modalını göster
     */
    function showCustomerModal(customerData) {
        const strings = (window.mhm_rentiva_customers && window.mhm_rentiva_customers.strings) || {};
        const modalHtml = `
            <div class="customer-modal-overlay modal-overlay">
                <div class="customer-modal">
                    <div class="modal-header">
                        <h3>${strings.customerDetails || 'Customer Details'}</h3>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="customer-details">
                            <div class="detail-row">
                                <label>${strings.name || 'Name'}:</label>
                                <span>${customerData.name || '-'}</span>
                            </div>
                            <div class="detail-row">
                                <label>${strings.email || 'Email'}:</label>
                                <span>${customerData.email || '-'}</span>
                            </div>
                            <div class="detail-row">
                                <label>${strings.phone || 'Phone'}:</label>
                                <span>${customerData.phone || '-'}</span>
                            </div>
                            <div class="detail-row">
                                <label>${strings.totalBookings || 'Total Bookings'}:</label>
                                <span>${customerData.booking_count || 0}</span>
                            </div>
                            <div class="detail-row">
                                <label>${strings.total_spent || 'Total Spent'}:</label>
                                <span>${customerData.total_spent || '0'} ${customerData.currency || (window.mhm_rentiva_customers && window.mhm_rentiva_customers.currencySymbol) || 'USD'}</span>
                            </div>
                            <div class="detail-row">
                                <label>${strings.lastBooking || 'Last Booking'}:</label>
                                <span>${customerData.last_booking || '-'}</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="button modal-close">${strings.close || 'Close'}</button>
                        <a href="admin.php?page=mhm-rentiva-customers&action=edit&customer_id=${customerData.id}" class="button button-primary">${strings.edit || 'Edit'}</a>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modalHtml);
    }

    /**
     * Modal'ı kapat
     */
    function closeModal() {
        $('.customer-modal-overlay').remove();
    }

    /**
     * Müşteri istatistiklerini güncelle
     */
    function updateCustomerStats(stats) {
        $('.customer-stat-number').each(function () {
            const $this = $(this);
            const statType = $this.closest('.customer-stat-card').data('stat-type');

            if (stats[statType] !== undefined) {
                $this.text(stats[statType]);
            }
        });
    }

    /**
     * Müşteri tablosunu güncelle
     */
    function updateCustomersTable(data) {
        // Tablo güncelleme işlemi burada yapılacak
        // Şimdilik sadece loading state'i kaldır
        hideLoadingState();
    }

    /**
     * Loading state'i göster
     */
    function showLoadingState() {
        $('.customers-page').addClass('loading');
        $('.customers-loading').show();
    }

    /**
     * Loading state'i gizle
     */
    function hideLoadingState() {
        $('.customers-page').removeClass('loading');
        $('.customers-loading').hide();
    }

    /**
     * Başarı mesajı göster
     */
    function showSuccess(message) {
        showNotice(message, 'success');
    }

    /**
     * Hata mesajı göster
     */
    function showError(message) {
        showNotice(message, 'error');
    }

    /**
     * Bildirim göster
     */
    function showNotice(message, type) {
        const noticeHtml = `
            <div class="customers-notice ${type}">
                <p>${message}</p>
            </div>
        `;

        $('.customers-page').prepend(noticeHtml);

        // 5 saniye sonra kaldır
        setTimeout(() => {
            $('.customers-notice').fadeOut(() => {
                $(this).remove();
            });
        }, 5000);
    }

    /**
     * Debounce fonksiyonu
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Takvim navigasyon fonksiyonları
    function initCalendarNavigation() {
        $(document).on('click', '.calendar-nav-btn', function (e) {
            e.preventDefault();

            const action = $(this).data('action');
            const currentUrl = new URL(window.location.href);
            let currentMonth = parseInt(currentUrl.searchParams.get('month')) || new Date().getMonth() + 1;
            let currentYear = parseInt(currentUrl.searchParams.get('year')) || new Date().getFullYear();

            if (action === 'prev') {
                currentMonth--;
                if (currentMonth < 1) {
                    currentMonth = 12;
                    currentYear--;
                }
            } else if (action === 'next') {
                currentMonth++;
                if (currentMonth > 12) {
                    currentMonth = 1;
                    currentYear++;
                }
            }

            // URL parametrelerini güncelle
            currentUrl.searchParams.set('month', currentMonth);
            currentUrl.searchParams.set('year', currentYear);

            // Sayfayı yenile
            window.location.href = currentUrl.toString();
        });
    }

    // Sayfa yüklendiğinde takvim navigasyonunu başlat
    $(document).ready(function () {
        initCalendarNavigation();
    });

    // Global fonksiyonları window objesine ekle
    window.MHMRentivaCustomers = {
        loadCustomersData: loadCustomersData,
        loadCustomerStats: loadCustomerStats,
        viewCustomerDetails: viewCustomerDetails,
        showSuccess: showSuccess,
        showError: showError,
        initCalendarNavigation: initCalendarNavigation
    };

})(jQuery);
