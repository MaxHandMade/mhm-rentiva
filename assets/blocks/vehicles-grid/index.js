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

    blocks.registerBlockType('mhm-rentiva/vehicles-grid', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            var layout = attributes.layout;
            var showPrice = attributes.showPrice;
            var showRating = attributes.showRating;
            var showCategory = attributes.showCategory;
            var showBookButton = attributes.showBookButton;
            var showFeatures = attributes.showFeatures;
            var filterCategories = attributes.filterCategories;
            var filterBrands = attributes.filterBrands;
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
                                { label: __('Masonry', 'mhm-rentiva'), value: 'masonry' }
                            ],
                            onChange: function (val) { setAttributes({ layout: val }); }
                        }),
                        el(SelectControl, {
                            label: __('Sort By', 'mhm-rentiva'),
                            value: sortBy,
                            options: [
                                { label: __('Newest', 'mhm-rentiva'), value: 'newest' },
                                { label: __('Price', 'mhm-rentiva'), value: 'price' },
                                { label: __('Popularity', 'mhm-rentiva'), value: 'popularity' },
                                { label: __('Rating', 'mhm-rentiva'), value: 'rating' }
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
                            value: limit,
                            type: 'number',
                            onChange: function (val) { setAttributes({ limit: val }); }
                        })
                    ),

                    /* PANEL 2: FILTERING */
                    el(PanelBody, { title: __('Filtering', 'mhm-rentiva'), initialOpen: false },
                        el(TextControl, {
                            label: __('Filter Categories (IDs)', 'mhm-rentiva'),
                            value: filterCategories,
                            onChange: function (val) { setAttributes({ filterCategories: val }); },
                            help: 'Comma-separated list of category IDs.'
                        }),
                        el(TextControl, {
                            label: __('Filter Brands (IDs)', 'mhm-rentiva'),
                            value: filterBrands,
                            onChange: function (val) { setAttributes({ filterBrands: val }); },
                            help: 'Comma-separated list of brand IDs.'
                        })
                    ),

                    /* PANEL 3: LAYOUT */
                    el(PanelBody, { title: __('Layout & Style', 'mhm-rentiva'), initialOpen: false },
                        el(TextControl, {
                            label: __('Custom CSS Class', 'mhm-rentiva'),
                            value: className,
                            onChange: function (val) { setAttributes({ className: val }); }
                        }),
                        el(SelectControl, {
                            label: __('Columns', 'mhm-rentiva'),
                            value: columns,
                            options: [
                                { label: '2', value: '2' },
                                { label: '3', value: '3' },
                                { label: '4', value: '4' }
                            ],
                            onChange: function (val) { setAttributes({ columns: val }); }
                        })
                    ),

                    /* PANEL 4: VISIBILITY */
                    el(PanelBody, { title: __('Visibility Controls', 'mhm-rentiva'), initialOpen: false },
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
                            label: __('Show Category', 'mhm-rentiva'),
                            checked: showCategory,
                            onChange: function (val) { setAttributes({ showCategory: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Book Button', 'mhm-rentiva'),
                            checked: showBookButton,
                            onChange: function (val) { setAttributes({ showBookButton: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Features', 'mhm-rentiva'),
                            checked: showFeatures,
                            onChange: function (val) { setAttributes({ showFeatures: val }); }
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: 'mhm-rentiva/vehicles-grid',
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
