(function (blocks, element, blockEditor, components, serverSideRender, i18n) {
    var el = element.createElement;
    var registerBlockType = blocks.registerBlockType;
    var useBlockProps = blockEditor.useBlockProps;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var ToggleControl = components.ToggleControl;
    var TextControl = components.TextControl;
    var SelectControl = components.SelectControl;
    var ServerSideRender = serverSideRender;
    var __ = i18n.__;

    blocks.registerBlockType('mhm-rentiva/payment-history', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            var showInvoiceDownload = attributes.showInvoiceDownload;
            var showPaymentMethod = attributes.showPaymentMethod;
            var showTransactionId = attributes.showTransactionId;
            var showDate = attributes.showDate;
            var showBookingLink = attributes.showBookingLink;
            var filterStatus = attributes.filterStatus;
            var sortBy = attributes.sortBy;
            var sortOrder = attributes.sortOrder;
            var limitResults = attributes.limitResults;
            var showPagination = attributes.showPagination;
            var className = attributes.className;

            return el('div', blockProps,
                el(InspectorControls, {},
                    /* PANEL 1: GENERAL */
                    el(PanelBody, { title: __('General Settings', 'mhm-rentiva'), initialOpen: true },
                        el(SelectControl, {
                            label: __('Filter Status', 'mhm-rentiva'),
                            value: filterStatus,
                            options: [
                                { label: __('All', 'mhm-rentiva'), value: 'all' },
                                { label: __('Paid', 'mhm-rentiva'), value: 'paid' },
                                { label: __('Pending', 'mhm-rentiva'), value: 'pending' },
                                { label: __('Refunded', 'mhm-rentiva'), value: 'refunded' },
                                { label: __('Failed', 'mhm-rentiva'), value: 'failed' }
                            ],
                            onChange: function (val) { setAttributes({ filterStatus: val }); }
                        }),
                        el(SelectControl, {
                            label: __('Sort By', 'mhm-rentiva'),
                            value: sortBy,
                            options: [
                                { label: __('Date', 'mhm-rentiva'), value: 'date' },
                                { label: __('Amount', 'mhm-rentiva'), value: 'amount' },
                                { label: __('Status', 'mhm-rentiva'), value: 'status' }
                            ],
                            onChange: function (val) { setAttributes({ sortBy: val }); }
                        }),
                        el(SelectControl, {
                            label: __('Sort Order', 'mhm-rentiva'),
                            value: sortOrder,
                            options: [
                                { label: __('Descending', 'mhm-rentiva'), value: 'desc' },
                                { label: __('Ascending', 'mhm-rentiva'), value: 'asc' },
                            ],
                            onChange: function (val) { setAttributes({ sortOrder: val }); }
                        }),
                        el(TextControl, {
                            label: __('Limit Results', 'mhm-rentiva'),
                            value: limitResults,
                            type: 'number',
                            onChange: function (val) { setAttributes({ limitResults: val }); }
                        })
                    ),

                    /* PANEL 2: LAYOUT */
                    el(PanelBody, { title: __('Layout', 'mhm-rentiva'), initialOpen: false },
                        el(TextControl, {
                            label: __('Custom CSS Class', 'mhm-rentiva'),
                            value: className,
                            onChange: function (val) { setAttributes({ className: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Pagination', 'mhm-rentiva'),
                            checked: showPagination,
                            onChange: function (val) { setAttributes({ showPagination: val }); }
                        })
                    ),

                    /* PANEL 3: VISIBILITY */
                    el(PanelBody, { title: __('Visibility Controls', 'mhm-rentiva'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('Show Invoice Download', 'mhm-rentiva'),
                            checked: showInvoiceDownload,
                            onChange: function (val) { setAttributes({ showInvoiceDownload: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Payment Method', 'mhm-rentiva'),
                            checked: showPaymentMethod,
                            onChange: function (val) { setAttributes({ showPaymentMethod: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Transaction ID', 'mhm-rentiva'),
                            checked: showTransactionId,
                            onChange: function (val) { setAttributes({ showTransactionId: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Date', 'mhm-rentiva'),
                            checked: showDate,
                            onChange: function (val) { setAttributes({ showDate: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Booking Link', 'mhm-rentiva'),
                            checked: showBookingLink,
                            onChange: function (val) { setAttributes({ showBookingLink: val }); }
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: 'mhm-rentiva/payment-history',
                    attributes: attributes
                })
            );
        },
        save: function () { return null; }
    });
}(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.serverSideRender,
    window.wp.i18n
));
