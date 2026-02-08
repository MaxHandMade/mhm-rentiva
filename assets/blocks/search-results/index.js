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

    blocks.registerBlockType('mhm-rentiva/search-results', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            var layout = attributes.layout;
            var showPrice = attributes.showPrice;
            var showRating = attributes.showRating;
            var showFilters = attributes.showFilters;
            var showSorting = attributes.showSorting;
            var showPagination = attributes.showPagination;
            var showAvailability = attributes.showAvailability;
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
                                { label: __('Grid', 'mhm-rentiva'), value: 'grid' },
                                { label: __('List', 'mhm-rentiva'), value: 'list' },
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
                                { label: __('Rating', 'mhm-rentiva'), value: 'rating' }
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
                                { label: '2', value: '2' },
                                { label: '3', value: '3' },
                                { label: '4', value: '4' }
                            ],
                            onChange: function (val) { setAttributes({ columns: val }); }
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
                            label: __('Show Filters', 'mhm-rentiva'),
                            checked: showFilters,
                            onChange: function (val) { setAttributes({ showFilters: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Sorting Options', 'mhm-rentiva'),
                            checked: showSorting,
                            onChange: function (val) { setAttributes({ showSorting: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Price', 'mhm-rentiva'),
                            checked: showPrice,
                            onChange: function (val) { setAttributes({ showPrice: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Rating', 'mhm-rentiva'),
                            checked: showRating,
                            onChange: function (val) { setAttributes({ showRating: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Availability Status', 'mhm-rentiva'),
                            checked: showAvailability,
                            onChange: function (val) { setAttributes({ showAvailability: val }); }
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: 'mhm-rentiva/search-results',
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
