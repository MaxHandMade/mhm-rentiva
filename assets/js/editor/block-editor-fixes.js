/**
 * MHM Rentiva - Block Editor Compatibility Layer
 * Version: 6.3.6
 * 
 * Strategy: Native Datepicker with Interaction Bypass and Instance Protection.
 * Fixes block selection layer preventing native picker from opening and React data loss.
 */
(function (wp, $) {
    'use strict';

    if (!wp || !$) return;

    console.log('[MHM Rentiva] Editor Stability Layer v6.3.6');

    // Silence known WP Core annoyance warnings & logs
    const originalWarn = console.warn;
    const originalError = console.error;
    const originalLog = console.log;

    const filterMsg = (msg) => {
        if (!msg || typeof msg !== 'string') return false;
        const ignored = [
            'wp.data.select( "core/navigation" )',
            'Navigation store is deprecated',
            'Deprecation warning:',
            'invalid category',
            'added to the iframe incorrectly',
            'woocommerce-blocktheme-css',
            'mhm-css-variables-css',
            'data-wp-init--refresh-cart-items',
            'JQMIGRATE: Migrate is installed',
            'isEditorPanelEnabled is deprecated',
            'Using custom components as toolbar controls is deprecated'
        ];
        return ignored.some(term => msg.includes(term));
    };

    console.warn = function (...args) {
        if (filterMsg(args[0])) return;
        originalWarn.apply(console, args);
    };

    console.error = function (...args) {
        if (filterMsg(args[0])) return;
        originalError.apply(console, args);
    };

    console.log = function (...args) {
        if (filterMsg(args[0])) return;
        originalLog.apply(console, args);
    };

    function getEditorContext() {
        // Find Gutenberg Iframe or main document
        const iframe = document.querySelector('iframe[name="editor-canvas"]');
        return (iframe && iframe.contentDocument) ? iframe.contentDocument : document;
    }

    /**
     * Patch jQuery UI Datepicker to be resilient against Gutenberg re-renders.
     */
    function patchDatepicker() {
        if (!$.datepicker || $.datepicker._mhmPatched) return;

        const originalGetInst = $.datepicker._getInst;
        $.datepicker._getInst = function (target) {
            try {
                if (!target) return this._curInst;
                const inst = originalGetInst.call(this, target);
                if (inst) return inst;

                // Recovery attempt: check if we are dealing with a known selector
                const $target = $(target);
                if ($target.length > 0) {
                    const recovered = $target.data('datepicker');
                    if (recovered) return recovered;
                }

                // Global fallback to current active instance if we're in the middle of a click
                return this._curInst;
            } catch (err) {
                return this._curInst || null;
            }
        };

        // Also patch _selectDay to avoid crashing if inst is still null
        const originalSelectDay = $.datepicker._selectDay;
        $.datepicker._selectDay = function (id, month, year, td) {
            const target = $(id)[0];
            let inst = this._getInst(target);

            // If inst is still null, try to force-assign current instance
            if (!inst && this._curInst) {
                inst = this._curInst;
            }

            if (!inst) {
                console.warn('[MHM Rentiva] Datepicker SelectDay: No instance found, attempting recovery...');
                const $id = $(id);
                if ($id.hasClass('js-datepicker') || $id.hasClass('rv-date-input')) {
                    // Try to re-init and then select? No, too slow.
                }
                return;
            }

            try {
                // Manually perform the logic if original might fail on null
                if ($(td).hasClass(this._unselectableClass) || this._isDisabledDatepicker(target)) {
                    return;
                }

                // Call original but wrap in try-catch
                originalSelectDay.apply(this, arguments);
            } catch (e) {
                console.error('[MHM Rentiva] Datepicker SelectDay Error:', e);
            }
        };

        $.datepicker._mhmPatched = true;
        console.log('[MHM Rentiva] Datepicker protection active.');
    }

    function stabilizeInputs() {
        const doc = getEditorContext();
        if (!doc) return;

        patchDatepicker();

        const $doc = $(doc);
        const options = window.mhmRentivaSearch || { dateFormat: 'yy-mm-dd' };
        const dpOptions = options.datepicker_options || { dateFormat: 'yy-mm-dd' };

        // Global delegate for datepicker focus
        $doc.off('focus.mhmDatepicker', '.js-datepicker, .rv-date-input, .js-pickup-date, .js-return-date');
        $doc.on('focus.mhmDatepicker', '.js-datepicker, .rv-date-input, .js-pickup-date, .js-return-date', function () {
            const $el = $(this);

            if ($el.attr('type') === 'date') {
                $el.attr('type', 'text').addClass('mhm-force-text');
            }
            $el.attr('autocomplete', 'off');

            // Hard reset if data is lost but class remains
            if ($el.hasClass('hasDatepicker') && !$el.data('datepicker')) {
                $el.removeClass('hasDatepicker').off('.datepicker');
            }

            if (!$el.hasClass('hasDatepicker') || !$el.data('datepicker')) {
                try {
                    $el.datepicker({
                        ...dpOptions,
                        dateFormat: 'yy-mm-dd',
                        appendTo: doc.body,
                        beforeShow: function (input, inst) {
                            $.datepicker._curInst = inst; // Track active instance explicitly
                            setTimeout(() => {
                                if (inst && inst.dpDiv) {
                                    inst.dpDiv.css({
                                        'z-index': 999999,
                                        'pointer-events': 'all'
                                    });
                                }
                            }, 0);
                        }
                    });
                    $el.datepicker('show');
                } catch (e) {
                    console.warn('[MHM Rentiva] Datepicker recovery failed:', e);
                }
            }
        });

        // Periodic cleanup
        $doc.find('.js-datepicker, .rv-date-input').each(function () {
            const $el = $(this);
            if ($el.hasClass('hasDatepicker') && !$el.data('datepicker')) {
                $el.removeClass('hasDatepicker');
            }
        });
    }

    function setupTrigger() {
        const doc = getEditorContext();
        if (!doc) return;

        const styleId = 'mhm-editor-click-through';
        if (doc.head && !doc.getElementById(styleId)) {
            const style = doc.createElement('style');
            style.id = styleId;
            style.textContent = `
                .js-datepicker, .rv-date-input, .rv-time-select, .rv-search-btn {
                    pointer-events: all !important;
                    cursor: pointer !important;
                    position: relative !important;
                    z-index: 10 !important;
                }
                .ui-datepicker {
                    pointer-events: all !important;
                    z-index: 999999 !important;
                }
                .wp-block-mhm-rentiva-search {
                    overflow: visible !important;
                }
            `;
            doc.head.appendChild(style);
        }
    }

    // Lifecycle
    if (wp.data && wp.data.subscribe) {
        let timeout = null;
        wp.data.subscribe(function () {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                stabilizeInputs();
                setupTrigger();
            }, 1000);
        });
    }

    $(function () {
        setTimeout(() => {
            stabilizeInputs();
            setupTrigger();
        }, 1500);
    });

})(window.wp, window.jQuery);
