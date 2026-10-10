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
            var showFavoriteButton = attributes.showFavoriteButton;
            var showCompareButton = attributes.showCompareButton;
            var defaultSort = attributes.defaultSort;
            var limit = attributes.limit;
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
                        // The first request's order (default_sort). The options are exactly
                        // the values SearchResults' sort switch applies (spec 3A §2.5 E).
                        el(SelectControl, {
                            label: __('Default Sort Order', 'mhm-rentiva'),
                            value: defaultSort,
                            options: [
                                { label: __('Most Relevant', 'mhm-rentiva'), value: 'relevance' },
                                { label: __('Price: Low to High', 'mhm-rentiva'), value: 'price_asc' },
                                { label: __('Price: High to Low', 'mhm-rentiva'), value: 'price_desc' },
                                { label: __('Name: A to Z', 'mhm-rentiva'), value: 'name_asc' },
                                { label: __('Name: Z to A', 'mhm-rentiva'), value: 'name_desc' },
                                { label: __('Newest First', 'mhm-rentiva'), value: 'year_desc' }
                            ],
                            onChange: function (val) { setAttributes({ defaultSort: val }); }
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
                        el(ToggleControl, {
                            label: __('Show Pagination', 'mhm-rentiva'),
                            checked: showPagination,
                            onChange: function (val) { setAttributes({ showPagination: val }); }
                        })
                    ),

                    /* PANEL 3: VISIBILITY (v1.0 APPROVED ONLY) */
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
                            label: __('Show Favorite Button', 'mhm-rentiva'),
                            checked: showFavoriteButton,
                            onChange: function (val) { setAttributes({ showFavoriteButton: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Compare Button', 'mhm-rentiva'),
                            checked: showCompareButton,
                            onChange: function (val) { setAttributes({ showCompareButton: val }); }
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
