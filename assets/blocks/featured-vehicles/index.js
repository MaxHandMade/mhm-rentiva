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

    blocks.registerBlockType('mhm-rentiva/featured-vehicles', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            return el('div', blockProps,
                el(InspectorControls, {},
                    /* PANEL 1: GENERAL */
                    el(PanelBody, { title: __('General Settings', 'mhm-rentiva'), initialOpen: true },
                        el(SelectControl, {
                            label: __('Service Type', 'mhm-rentiva'),
                            value: attributes.serviceType,
                            options: [
                                { label: __('Rental', 'mhm-rentiva'), value: 'rental' },
                                { label: __('Transfer', 'mhm-rentiva'), value: 'transfer' },
                                { label: __('Both', 'mhm-rentiva'), value: 'both' }
                            ],
                            onChange: function (val) { setAttributes({ serviceType: val }); }
                        }),
                        el(SelectControl, {
                            label: __('Sort By', 'mhm-rentiva'),
                            value: attributes.sortBy,
                            options: [
                                { label: __('Popularity', 'mhm-rentiva'), value: 'popularity' },
                                { label: __('Price', 'mhm-rentiva'), value: 'price' },
                                { label: __('Newest', 'mhm-rentiva'), value: 'newest' },
                                { label: __('Rating', 'mhm-rentiva'), value: 'rating' }
                            ],
                            onChange: function (val) { setAttributes({ sortBy: val }); }
                        }),
                        el(SelectControl, {
                            label: __('Sort Order', 'mhm-rentiva'),
                            value: attributes.sortOrder,
                            options: [
                                { label: __('Descending', 'mhm-rentiva'), value: 'desc' },
                                { label: __('Ascending', 'mhm-rentiva'), value: 'asc' }
                            ],
                            onChange: function (val) { setAttributes({ sortOrder: val }); }
                        }),
                        el(TextControl, {
                            label: __('Limit', 'mhm-rentiva'),
                            value: attributes.limit,
                            type: 'number',
                            onChange: function (val) { setAttributes({ limit: val }); }
                        }),
                        el(TextControl, {
                            label: __('Filter Categories (IDs)', 'mhm-rentiva'),
                            value: attributes.filterCategories,
                            help: __('Comma separated IDs', 'mhm-rentiva'),
                            onChange: function (val) { setAttributes({ filterCategories: val }); }
                        }),
                        el(TextControl, {
                            label: __('Filter Brands (IDs)', 'mhm-rentiva'),
                            value: attributes.filterBrands,
                            help: __('Comma separated IDs', 'mhm-rentiva'),
                            onChange: function (val) { setAttributes({ filterBrands: val }); }
                        })
                    ),

                    /* PANEL 2: LAYOUT */
                    el(PanelBody, { title: __('Layout Options', 'mhm-rentiva'), initialOpen: false },
                        el(SelectControl, {
                            label: __('Layout', 'mhm-rentiva'),
                            value: attributes.layout,
                            options: [
                                { label: __('Grid', 'mhm-rentiva'), value: 'grid' },
                                { label: __('Carousel', 'mhm-rentiva'), value: 'carousel' },
                                { label: __('List', 'mhm-rentiva'), value: 'list' }
                            ],
                            onChange: function (val) { setAttributes({ layout: val }); }
                        }),
                        attributes.layout === 'grid' && el(SelectControl, {
                            label: __('Columns', 'mhm-rentiva'),
                            value: attributes.columns,
                            options: [
                                { label: '1', value: '1' },
                                { label: '2', value: '2' },
                                { label: '3', value: '3' },
                                { label: '4', value: '4' }
                            ],
                            onChange: function (val) { setAttributes({ columns: val }); }
                        }),
                        el(TextControl, {
                            label: __('Custom CSS Class', 'mhm-rentiva'),
                            value: attributes.className,
                            onChange: function (val) { setAttributes({ className: val }); }
                        })
                    ),

                    /* VISIBILITY CONTROLS (v1.0 APPROVED ONLY) */
                    el(PanelBody, { title: __('Visibility Controls', 'mhm-rentiva'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('Show Price', 'mhm-rentiva'),
                            checked: attributes.showPrice,
                            onChange: function (val) { setAttributes({ showPrice: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Features', 'mhm-rentiva'),
                            checked: attributes.showFeatures,
                            onChange: function (val) { setAttributes({ showFeatures: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Book Button', 'mhm-rentiva'),
                            checked: attributes.showBookButton,
                            onChange: function (val) { setAttributes({ showBookButton: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Favorite Button', 'mhm-rentiva'),
                            checked: attributes.showFavoriteButton,
                            onChange: function (val) { setAttributes({ showFavoriteButton: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Compare Button', 'mhm-rentiva'),
                            checked: attributes.showCompareButton,
                            onChange: function (val) { setAttributes({ showCompareButton: val }); }
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: 'mhm-rentiva/featured-vehicles',
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
