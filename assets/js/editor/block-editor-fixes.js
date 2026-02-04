/**
 * MHM Rentiva - Block Editor Compatibility Layer
 * Version: 6.2.0
 * 
 * Strategy: Native Datepicker with Interaction Bypass.
 * Fixes block selection layer preventing native picker from opening.
 */
(function (wp, $) {
    'use strict';

    if (!wp || !$) return;

    console.log('[MHM Rentiva] Editor Stability Layer v6.2');

    function getEditorContext() {
        const iframe = document.querySelector('iframe[name="editor-canvas"]');
        return (iframe && iframe.contentDocument) ? iframe.contentDocument : document;
    }

    function stabilizeInputs() {
        const doc = getEditorContext();
        if (!doc || !doc.body) return;

        // 1. CSS Interaction Fix
        const styleId = 'mhm-editor-interaction-fix';
        if (doc.head && !doc.getElementById(styleId)) {
            const style = doc.createElement('style');
            style.id = styleId;
            style.textContent = `
                .rv-search-form select, .rv-search-form-compact select, .rv-date-input {
                    background-image: none !important;
                    appearance: auto !important;
                }
                /* Allow clicks to pass through Gutenberg overlays */
                input[type="date"].js-datepicker,
                .js-datepicker {
                    pointer-events: all !important;
                    user-select: auto !important;
                    cursor: pointer !important;
                    position: relative !important;
                    z-index: 10 !important;
                    background-color: #fff !important;
                    border: 1px solid #ccd0d4 !important;
                    min-height: 40px !important;
                    padding: 8px !important;
                }
                /* Highlight on hover to confirm interactability */
                .js-datepicker:hover {
                    border-color: #2271b1 !important;
                    box-shadow: 0 0 0 1px #2271b1 !important;
                }
            `;
            doc.head.appendChild(style);
        }

        // 2. Conversion and Event Binding
        $(doc).find('.js-datepicker').each(function () {
            const $el = $(this);
            const el = this;

            // Kill jQuery UI
            if ($el.hasClass('hasDatepicker') || $el.data('datepicker')) {
                try { $el.datepicker('destroy'); } catch (e) { }
                $el.removeClass('hasDatepicker').removeData('datepicker').off('.datepicker');
            }

            // Convert to native date
            if (el.type !== 'date') {
                el.type = 'date';
                console.log('[MHM Rentiva] Field converted to native date');
            }
        });
    }

    // Manual Trigger for Native Picker
    function setupTrigger() {
        const doc = getEditorContext();
        if (!doc) return;

        // Delegate click and mousedown
        $(doc).off('.mhmNative').on('mousedown.mhmNative click.mhmNative', '.js-datepicker', function (e) {
            const el = this;

            // stopPropagation is key to prevent Gutenberg block selection
            e.stopPropagation();

            // Native Browser Picker Trigger (Modern Browsers)
            if (el.type === 'date' && typeof el.showPicker === 'function') {
                try {
                    el.showPicker();
                } catch (err) {
                    // Fallback if showPicker is blocked
                    el.focus();
                }
            }
        });
    }

    // Lifecycle
    if (wp.data && wp.data.subscribe) {
        let timeout = null;
        wp.data.subscribe(function () {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                stabilizeInputs();
                setupTrigger();
            }, 500);
        });
    }

    $(function () {
        setTimeout(() => {
            stabilizeInputs();
            setupTrigger();
        }, 2000);
    });

})(window.wp, window.jQuery);
