/**
 * Vendor Report Modal — shared frontend behavior.
 *
 * Modes:
 *   - report:   creates a vendor_report row via mhm_vendor_report_create AJAX
 *   - withdraw: intercepts the lifecycle "Çek" button, captures a reason,
 *               and posts to mhm_vehicle_lifecycle_withdraw with the reason
 *   - pause:    same idea for the "Durdur" lifecycle action
 *
 * Triggers:
 *   - Any element with data-mhm-vrm-trigger="report" + data-context-type +
 *     data-context-id (booking id, vehicle id, penalty UUID, or empty for
 *     general)
 *   - .mhm-lifecycle-btn[data-action="withdraw"|"pause"] (intercepted)
 *
 * Global config (printed inline by the panel page):
 *   window.mhmVendorReportConfig = {
 *     ajaxUrl: '...',
 *     reportNonce: '...',
 *     lifecycleNonce: '...',
 *     i18n: {...},
 *   }
 *
 * @since 4.35.0
 */
(function () {
    'use strict';

    var modal = null;
    var form = null;
    var titleField = null;
    var titleInput = null;
    var descriptionTextarea = null;
    var descriptionLabel = null;
    var subtitleEl = null;
    var titleEl = null;
    var errorEl = null;
    var submitBtn = null;
    var contextTypeInput = null;
    var contextIdInput = null;
    var modeInput = null;

    var currentMode = 'report';
    var currentVehicleId = null;
    var pendingTrigger = null;

    function $(selector, root) {
        return (root || document).querySelector(selector);
    }

    function init() {
        modal = $('[data-mhm-vendor-report-modal]');
        if (!modal) return;

        form = $('[data-mhm-vrm-form]', modal);
        titleField = $('[data-mhm-vrm-title-field]', modal);
        titleInput = $('input[name="title"]', form);
        descriptionTextarea = $('textarea[name="description"]', form);
        descriptionLabel = $('[data-mhm-vrm-description-label]', form);
        subtitleEl = $('[data-mhm-vrm-subtitle]', modal);
        titleEl = $('#mhm-vendor-report-modal-title', modal);
        errorEl = $('[data-mhm-vrm-error]', modal);
        submitBtn = $('[data-mhm-vrm-submit]', modal);
        contextTypeInput = $('input[name="context_type"]', form);
        contextIdInput = $('input[name="context_id"]', form);
        modeInput = $('input[name="mode"]', form);

        // Close handlers
        modal.addEventListener('click', function (e) {
            if (e.target.dataset && 'mhmVrmClose' in e.target.dataset) {
                close();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.hasAttribute('hidden')) close();
        });

        // Capture-phase listener so we can intercept lifecycle withdraw/pause
        // BEFORE user-dashboard.js handles the click.
        document.addEventListener('click', onDocumentClick, true);

        form.addEventListener('submit', onSubmit);
    }

    function onDocumentClick(e) {
        // 1. Generic report trigger
        var reportTrigger = e.target.closest('[data-mhm-vrm-trigger="report"]');
        if (reportTrigger) {
            e.preventDefault();
            e.stopPropagation();
            openForReport(reportTrigger);
            return;
        }

        // 2. Existing booking-card "Sorun Bildir" button (already in markup pre-v4.35.0)
        var bookingReportBtn = e.target.closest('.mhm-vendor-booking-card__action.is-report');
        if (bookingReportBtn) {
            e.preventDefault();
            e.stopPropagation();
            openForReport(bookingReportBtn, {
                contextType: 'booking',
                contextId: bookingReportBtn.dataset.bookingId || '',
            });
            return;
        }

        // 3. Lifecycle withdraw / pause — intercept for reason capture
        var lifecycleBtn = e.target.closest('.mhm-lifecycle-btn');
        if (lifecycleBtn) {
            var action = lifecycleBtn.dataset.action;
            if (action === 'withdraw' || action === 'pause') {
                e.preventDefault();
                e.stopPropagation();
                openForLifecycleReason(lifecycleBtn, action);
            }
        }
    }

    function openForReport(trigger, override) {
        var contextType = (override && override.contextType) || trigger.dataset.contextType || 'general';
        var contextId = (override && override.contextId) || trigger.dataset.contextId || '';

        currentMode = 'report';
        modeInput.value = 'report';
        currentVehicleId = null;
        pendingTrigger = trigger;

        contextTypeInput.value = contextType;
        contextIdInput.value = contextId;

        // Title varies by context.
        var labels = {
            booking: i18n('reportBooking', 'Report a booking issue'),
            vehicle: i18n('reportVehicle', 'Appeal vehicle action'),
            penalty: i18n('reportPenalty', 'Appeal Penalty'),
            general: i18n('reportGeneral', 'Contact Administrator'),
        };
        titleEl.textContent = labels[contextType] || labels.general;
        subtitleEl.textContent = i18n('reportSubtitle', 'Tell the administrator what happened. Your message goes only to the platform team.');
        descriptionLabel.firstChild.textContent = i18n('reportDescriptionLabel', 'Describe the issue in detail...');
        submitBtn.textContent = i18n('submitReport', 'Submit Report');

        titleField.hidden = false;
        if (trigger.dataset.suggestedTitle) {
            titleInput.value = trigger.dataset.suggestedTitle;
        } else {
            titleInput.value = '';
        }
        descriptionTextarea.value = '';

        open();
    }

    function openForLifecycleReason(trigger, action) {
        currentMode = action; // 'withdraw' or 'pause'
        modeInput.value = action;
        currentVehicleId = trigger.dataset.vehicleId || '';
        pendingTrigger = trigger;

        contextTypeInput.value = 'vehicle_action';
        contextIdInput.value = currentVehicleId;

        if (action === 'withdraw') {
            titleEl.textContent = i18n('withdrawTitle', 'Withdraw vehicle');
            subtitleEl.textContent = i18n('withdrawSubtitle', 'A penalty applies to vehicle withdrawals. Tell the administrator the reason — if accepted, the penalty will not apply.');
            descriptionLabel.firstChild.textContent = i18n('reasonForWithdrawal', 'Reason for withdrawal');
            submitBtn.textContent = i18n('confirmWithdraw', 'Withdraw Vehicle');
        } else {
            titleEl.textContent = i18n('pauseTitle', 'Pause vehicle');
            subtitleEl.textContent = i18n('pauseSubtitle', 'A penalty may apply to repeated pauses. Tell the administrator the reason — if accepted, the penalty will not apply.');
            descriptionLabel.firstChild.textContent = i18n('reasonForPausing', 'Reason for pausing');
            submitBtn.textContent = i18n('confirmPause', 'Pause Vehicle');
        }

        // Title field is filled automatically — hide it for the lifecycle flow.
        titleField.hidden = true;
        titleInput.value = action === 'withdraw'
            ? i18n('withdrawalReasonTitle', 'Withdrawal reason')
            : i18n('pauseReasonTitle', 'Pause reason');
        descriptionTextarea.value = '';

        open();
    }

    function open() {
        clearError();
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        setTimeout(function () {
            descriptionTextarea.focus();
        }, 50);
    }

    function close() {
        modal.hidden = true;
        document.body.style.overflow = '';
        clearError();
    }

    function clearError() {
        errorEl.textContent = '';
        errorEl.hidden = true;
    }

    function showError(msg) {
        errorEl.textContent = msg;
        errorEl.hidden = false;
    }

    function onSubmit(e) {
        e.preventDefault();

        var description = descriptionTextarea.value.trim();
        if (description.length < 20) {
            showError(i18n('descTooShort', 'Please describe the issue in at least 20 characters.'));
            return;
        }

        clearError();
        submitBtn.disabled = true;
        var originalText = submitBtn.textContent;
        submitBtn.textContent = i18n('submitting', 'Submitting...');

        if (currentMode === 'report') {
            submitReport(description, originalText);
        } else {
            submitLifecycleAction(description, originalText);
        }
    }

    function submitReport(description, originalText) {
        var data = new URLSearchParams();
        data.append('action', 'mhm_vendor_report_create');
        data.append('nonce', config('reportNonce'));
        data.append('context_type', contextTypeInput.value);
        if (contextIdInput.value !== '') {
            data.append('context_id', contextIdInput.value);
        }
        data.append('title', titleInput.value);
        data.append('description', description);

        fetch(config('ajaxUrl'), { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
            .then(function (res) {
                if (res.body && res.body.success) {
                    close();
                    if (typeof window.mhmShowToast === 'function') {
                        window.mhmShowToast(res.body.data.message || i18n('submitted', 'Report submitted successfully'), 'success');
                    }
                    if (pendingTrigger) {
                        pendingTrigger.classList.add('has-open-report');
                    }
                } else {
                    showError((res.body && res.body.data && res.body.data.message) || i18n('genericError', 'An error occurred.'));
                }
            })
            .catch(function () {
                showError(i18n('networkError', 'Network error. Please try again.'));
            })
            .finally(function () {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
    }

    function submitLifecycleAction(reason, originalText) {
        var data = new URLSearchParams();
        data.append('action', 'mhm_vehicle_lifecycle_' + currentMode);
        data.append('nonce', config('lifecycleNonce'));
        data.append('vehicle_id', currentVehicleId);
        data.append('reason', reason);

        fetch(config('ajaxUrl'), { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
            .then(function (res) {
                if (res.body && res.body.success) {
                    close();
                    var msg = (res.body.data && res.body.data.message) || i18n('lifecycleDone', 'Done.');
                    if (typeof window.mhmShowToast === 'function') {
                        window.mhmShowToast(msg, 'success');
                    }
                    setTimeout(function () { window.location.reload(); }, 1200);
                } else {
                    showError((res.body && res.body.data && res.body.data.message) || i18n('genericError', 'An error occurred.'));
                }
            })
            .catch(function () {
                showError(i18n('networkError', 'Network error. Please try again.'));
            })
            .finally(function () {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
    }

    function config(key) {
        return (window.mhmVendorReportConfig && window.mhmVendorReportConfig[key]) || '';
    }

    function i18n(key, fallback) {
        var dict = (window.mhmVendorReportConfig && window.mhmVendorReportConfig.i18n) || {};
        return dict[key] || fallback;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
