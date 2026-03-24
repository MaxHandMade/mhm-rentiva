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

    blocks.registerBlockType('mhm-rentiva/my-bookings', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            var showVehicleImage = attributes.showVehicleImage;
            var showBookingDates = attributes.showBookingDates;
            var showPrice = attributes.showPrice;
            var showStatus = attributes.showStatus;
            var showCancelButton = attributes.showCancelButton;
            var showModifyButton = attributes.showModifyButton;
            var showDetailsLink = attributes.showDetailsLink;
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
                                { label: __('Active', 'mhm-rentiva'), value: 'active' },
                                { label: __('Upcoming', 'mhm-rentiva'), value: 'upcoming' },
                                { label: __('Completed', 'mhm-rentiva'), value: 'completed' },
                                { label: __('Cancelled', 'mhm-rentiva'), value: 'cancelled' }
                            ],
                            onChange: function (val) { setAttributes({ filterStatus: val }); }
                        }),
                        el(SelectControl, {
                            label: __('Sort By', 'mhm-rentiva'),
                            value: sortBy,
                            options: [
                                { label: __('Date', 'mhm-rentiva'), value: 'date' },
                                { label: __('Status', 'mhm-rentiva'), value: 'status' },
                                { label: __('Price', 'mhm-rentiva'), value: 'price' },
                            ],
                            onChange: function (val) { setAttributes({ sortBy: val }); }
                        }),
                        el(SelectControl, {
                            label: __('Sort Order', 'mhm-rentiva'),
                            value: sortOrder,
                            options: [
                                { label: __('Descending (Newest First)', 'mhm-rentiva'), value: 'desc' },
                                { label: __('Ascending (Oldest First)', 'mhm-rentiva'), value: 'asc' },
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
                            label: __('Show Vehicle Image', 'mhm-rentiva'),
                            checked: showVehicleImage,
                            onChange: function (val) { setAttributes({ showVehicleImage: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Booking Dates', 'mhm-rentiva'),
                            checked: showBookingDates,
                            onChange: function (val) { setAttributes({ showBookingDates: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Price', 'mhm-rentiva'),
                            checked: showPrice,
                            onChange: function (val) { setAttributes({ showPrice: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Status', 'mhm-rentiva'),
                            checked: showStatus,
                            onChange: function (val) { setAttributes({ showStatus: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Cancel Button', 'mhm-rentiva'),
                            checked: showCancelButton,
                            onChange: function (val) { setAttributes({ showCancelButton: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Modify Button', 'mhm-rentiva'),
                            checked: showModifyButton,
                            onChange: function (val) { setAttributes({ showModifyButton: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Details Link', 'mhm-rentiva'),
                            checked: showDetailsLink,
                            onChange: function (val) { setAttributes({ showDetailsLink: val }); }
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: 'mhm-rentiva/my-bookings',
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
