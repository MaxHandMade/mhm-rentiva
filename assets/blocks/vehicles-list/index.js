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

    blocks.registerBlockType('mhm-rentiva/vehicles-list', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            return el('div', blockProps,
                el(InspectorControls, {},
                    /* PANEL 1: QUERY SETTINGS */
                    el(PanelBody, { title: __('Query Settings', 'mhm-rentiva'), initialOpen: true },
                        el(SelectControl, {
                            label: __('Sort By', 'mhm-rentiva'),
                            value: attributes.orderby,
                            options: [
                                { label: __('Newest', 'mhm-rentiva'), value: 'newest' },
                                { label: __('Price', 'mhm-rentiva'), value: 'price' },
                                { label: __('Popularity', 'mhm-rentiva'), value: 'popularity' },
                                { label: __('Rating', 'mhm-rentiva'), value: 'rating' },
                                { label: __('Most Reviewed', 'mhm-rentiva'), value: 'rating_count' },
                                { label: __('Confidence Score', 'mhm-rentiva'), value: 'confidence' }
                            ],
                            onChange: function (val) { setAttributes({ orderby: val }); }
                        }),
                        el(SelectControl, {
                            label: __('Order', 'mhm-rentiva'),
                            value: attributes.order,
                            options: [
                                { label: __('Descending', 'mhm-rentiva'), value: 'desc' },
                                { label: __('Ascending', 'mhm-rentiva'), value: 'asc' }
                            ],
                            onChange: function (val) { setAttributes({ order: val }); }
                        }),
                        el(TextControl, {
                            label: __('Limit', 'mhm-rentiva'),
                            value: attributes.limit,
                            type: 'number',
                            onChange: function (val) { setAttributes({ limit: val }); }
                        })
                    ),

                    /* PANEL 2: FILTERING */
                    el(PanelBody, { title: __('Filtering', 'mhm-rentiva'), initialOpen: false },
                        el(TextControl, {
                            label: __('Filter Categories (IDs)', 'mhm-rentiva'),
                            value: attributes.filterCategories,
                            help: __('Comma-separated list of category IDs.', 'mhm-rentiva'),
                            onChange: function (val) { setAttributes({ filterCategories: val }); }
                        }),
                        el(TextControl, {
                            label: __('Filter Brands (IDs)', 'mhm-rentiva'),
                            value: attributes.filterBrands,
                            help: __('Comma-separated list of brand IDs.', 'mhm-rentiva'),
                            onChange: function (val) { setAttributes({ filterBrands: val }); }
                        }),
                        el(TextControl, {
                            label: __('Min Rating (1-5)', 'mhm-rentiva'),
                            value: attributes.minRating,
                            type: 'number',
                            min: 0,
                            max: 5,
                            step: 0.1,
                            onChange: function (val) { setAttributes({ minRating: val }); }
                        }),
                        el(TextControl, {
                            label: __('Min Reviews', 'mhm-rentiva'),
                            value: attributes.minReviews,
                            type: 'number',
                            min: 0,
                            onChange: function (val) { setAttributes({ minReviews: val }); }
                        })
                    ),

                    /* PANEL 2: LAYOUT */
                    el(PanelBody, { title: __('Layout & Style', 'mhm-rentiva'), initialOpen: false },
                        el(TextControl, {
                            label: __('Custom CSS Class', 'mhm-rentiva'),
                            value: attributes.className,
                            onChange: function (val) { setAttributes({ className: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Image', 'mhm-rentiva'),
                            checked: attributes.showImage,
                            onChange: function (val) { setAttributes({ showImage: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Title', 'mhm-rentiva'),
                            checked: attributes.showTitle,
                            onChange: function (val) { setAttributes({ showTitle: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Description', 'mhm-rentiva'),
                            checked: attributes.showDescription,
                            onChange: function (val) { setAttributes({ showDescription: val }); }
                        })
                    ),

                    /* PANEL 3: VISIBILITY CONTROLS (v1.0 APPROVED ONLY) */
                    el(PanelBody, { title: __('Visibility Controls', 'mhm-rentiva'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('Show Rating', 'mhm-rentiva'),
                            checked: attributes.showRating,
                            onChange: function (val) { setAttributes({ showRating: val }); }
                        }),
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
                            label: __('Show Booking Button', 'mhm-rentiva'),
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
                    block: 'mhm-rentiva/vehicles-list',
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
