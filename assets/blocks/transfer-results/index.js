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

    blocks.registerBlockType('mhm-rentiva/transfer-results', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            var layout = attributes.layout;
            var showPrice = attributes.showPrice;
            var showVehicleDetails = attributes.showVehicleDetails;
            var showLuggageInfo = attributes.showLuggageInfo;
            var showPassengerCount = attributes.showPassengerCount;
            var showBookButton = attributes.showBookButton;
            var showRouteInfo = attributes.showRouteInfo;
            var showFavoriteButton = attributes.showFavoriteButton;
            var showCompareButton = attributes.showCompareButton;
            var sortBy = attributes.sortBy;
            var sortOrder = attributes.sortOrder;
            var limit = attributes.limit;
            var columns = attributes.columns;
            var className = attributes.className;

            return el('div', blockProps,
                el(InspectorControls, {},
                    /* PANEL 1: GENERAL */
                    el(PanelBody, { title: __('General Settings', 'mhm-rentiva'), initialOpen: true },
                        el(SelectControl, {
                            label: __('Layout', 'mhm-rentiva'),
                            value: layout,
                            options: [
                                { label: __('List', 'mhm-rentiva'), value: 'list' },
                                { label: __('Grid', 'mhm-rentiva'), value: 'grid' },
                                { label: __('Compact', 'mhm-rentiva'), value: 'compact' }
                            ],
                            onChange: function (val) { setAttributes({ layout: val }); }
                        }),
                        el(SelectControl, {
                            label: __('Sort By', 'mhm-rentiva'),
                            value: sortBy,
                            options: [
                                { label: __('Price', 'mhm-rentiva'), value: 'price' },
                                { label: __('Popularity', 'mhm-rentiva'), value: 'popularity' },
                                { label: __('Newest', 'mhm-rentiva'), value: 'newest' },
                                { label: __('Capacity', 'mhm-rentiva'), value: 'capacity' }
                            ],
                            onChange: function (val) { setAttributes({ sortBy: val }); }
                        }),
                        el(SelectControl, {
                            label: __('Sort Order', 'mhm-rentiva'),
                            value: sortOrder,
                            options: [
                                { label: __('Ascending', 'mhm-rentiva'), value: 'asc' },
                                { label: __('Descending', 'mhm-rentiva'), value: 'desc' },
                            ],
                            onChange: function (val) { setAttributes({ sortOrder: val }); }
                        }),
                        el(TextControl, {
                            label: __('Limit Results', 'mhm-rentiva'),
                            value: limit,
                            type: 'number',
                            onChange: function (val) { setAttributes({ limit: val }); }
                        })
                    ),

                    /* PANEL 2: LAYOUT */
                    el(PanelBody, { title: __('Layout & Style', 'mhm-rentiva'), initialOpen: false },
                        el(TextControl, {
                            label: __('Custom CSS Class', 'mhm-rentiva'),
                            value: className,
                            onChange: function (val) { setAttributes({ className: val }); }
                        }),
                        (layout === 'grid') && el(SelectControl, {
                            label: __('Columns', 'mhm-rentiva'),
                            value: columns,
                            options: [
                                { label: '1', value: '1' },
                                { label: '2', value: '2' },
                                { label: '3', value: '3' }
                            ],
                            onChange: function (val) { setAttributes({ columns: val }); }
                        })
                    ),

                    /* PANEL 3: VISIBILITY */
                    el(PanelBody, { title: __('Visibility Controls', 'mhm-rentiva'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('Show Price', 'mhm-rentiva'),
                            checked: showPrice,
                            onChange: function (val) { setAttributes({ showPrice: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Vehicle Details', 'mhm-rentiva'),
                            checked: showVehicleDetails,
                            onChange: function (val) { setAttributes({ showVehicleDetails: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Luggage Info', 'mhm-rentiva'),
                            checked: showLuggageInfo,
                            onChange: function (val) { setAttributes({ showLuggageInfo: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Passenger Count', 'mhm-rentiva'),
                            checked: showPassengerCount,
                            onChange: function (val) { setAttributes({ showPassengerCount: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Book Button', 'mhm-rentiva'),
                            checked: showBookButton,
                            onChange: function (val) { setAttributes({ showBookButton: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Favorite Button', 'mhm-rentiva'),
                            checked: showFavoriteButton,
                            onChange: function (val) { setAttributes({ showFavoriteButton: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Compare Button', 'mhm-rentiva'),
                            checked: showCompareButton,
                            onChange: function (val) { setAttributes({ showCompareButton: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Route Info', 'mhm-rentiva'),
                            checked: showRouteInfo,
                            onChange: function (val) { setAttributes({ showRouteInfo: val }); }
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: 'mhm-rentiva/transfer-results',
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
