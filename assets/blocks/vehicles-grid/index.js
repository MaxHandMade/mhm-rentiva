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
            var showImage = attributes.showImage;
            var showTitle = attributes.showTitle;
            var showPrice = attributes.showPrice;
            var showRating = attributes.showRating;
            var showCategory = attributes.showCategory;
            var showBrand = attributes.showBrand;
            var showAvailability = attributes.showAvailability;
            var showCompareButton = attributes.showCompareButton;
            var showFavoriteButton = attributes.showFavoriteButton;
            var showBookButton = attributes.showBookButton;
            var showFeatures = attributes.showFeatures;
            var filterCategories = attributes.filterCategories;
            var filterBrands = attributes.filterBrands;
            var sortBy = attributes.sortBy;
            var sortOrder = attributes.sortOrder;
            var limit = attributes.limit;
            var columns = attributes.columns;
            var className = attributes.className;
            var minRating = attributes.minRating;
            var minReviews = attributes.minReviews;
            var viewAllUrl = attributes.viewAllUrl;
            var viewAllText = attributes.viewAllText;

            return el('div', blockProps,
                el(InspectorControls, {},
                    /* PANEL 1: QUERY SETTINGS */
                    el(PanelBody, { title: __('Query Settings', 'mhm-rentiva'), initialOpen: true },
                        el(SelectControl, {
                            label: __('Sort By', 'mhm-rentiva'),
                            value: sortBy,
                            options: [
                                { label: __('Newest', 'mhm-rentiva'), value: 'newest' },
                                { label: __('Price', 'mhm-rentiva'), value: 'price' },
                                { label: __('Popularity', 'mhm-rentiva'), value: 'popularity' },
                                { label: __('Rating', 'mhm-rentiva'), value: 'rating' },
                                { label: __('Most Reviewed', 'mhm-rentiva'), value: 'rating_count' },
                                { label: __('Confidence Score', 'mhm-rentiva'), value: 'confidence' }
                            ],
                            onChange: function (val) { setAttributes({ sortBy: val }); }
                        }),
                        el(SelectControl, {
                            label: __('Order', 'mhm-rentiva'),
                            value: sortOrder,
                            options: [
                                { label: __('Descending', 'mhm-rentiva'), value: 'desc' },
                                { label: __('Ascending', 'mhm-rentiva'), value: 'asc' },
                            ],
                            onChange: function (val) { setAttributes({ sortOrder: val }); }
                        }),
                        el(TextControl, {
                            label: __('Limit', 'mhm-rentiva'),
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
                            help: __('Comma-separated list of category IDs.', 'mhm-rentiva')
                        }),
                        el(TextControl, {
                            label: __('Filter Brands (IDs)', 'mhm-rentiva'),
                            value: filterBrands,
                            onChange: function (val) { setAttributes({ filterBrands: val }); },
                            help: __('Comma-separated list of brand IDs.', 'mhm-rentiva')
                        }),
                        el(TextControl, {
                            label: __('Min Rating (1-5)', 'mhm-rentiva'),
                            value: minRating,
                            type: 'number',
                            min: 0,
                            max: 5,
                            step: 0.1,
                            onChange: function (val) { setAttributes({ minRating: val }); }
                        }),
                        el(TextControl, {
                            label: __('Min Reviews', 'mhm-rentiva'),
                            value: minReviews,
                            type: 'number',
                            min: 0,
                            onChange: function (val) { setAttributes({ minReviews: val }); }
                        })
                    ),

                    /* PANEL 3: LAYOUT */
                    el(PanelBody, { title: __('Layout & Style', 'mhm-rentiva'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('Show Image', 'mhm-rentiva'),
                            checked: showImage,
                            onChange: function (val) { setAttributes({ showImage: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Title', 'mhm-rentiva'),
                            checked: showTitle,
                            onChange: function (val) { setAttributes({ showTitle: val }); }
                        }),
                        el(SelectControl, {
                            label: __('Layout', 'mhm-rentiva'),
                            value: layout,
                            options: [
                                { label: __('Grid', 'mhm-rentiva'), value: 'grid' },
                                { label: __('Masonry', 'mhm-rentiva'), value: 'masonry' }
                            ],
                            onChange: function (val) { setAttributes({ layout: val }); }
                        }),
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

                    /* PANEL 4: VISIBILITY (v1.0 APPROVED ONLY) */
                    el(PanelBody, { title: __('Visibility Controls', 'mhm-rentiva'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('Show Rating', 'mhm-rentiva'),
                            checked: attributes.showRating,
                            onChange: function (val) { setAttributes({ showRating: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Price', 'mhm-rentiva'),
                            checked: showPrice,
                            onChange: function (val) { setAttributes({ showPrice: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Features', 'mhm-rentiva'),
                            checked: showFeatures,
                            onChange: function (val) { setAttributes({ showFeatures: val }); }
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
                        })
                    ),

                    /* PANEL 5: VIEW ALL LINK */
                    el(PanelBody, { title: __('View All Link', 'mhm-rentiva'), initialOpen: false },
                        el(TextControl, {
                            label: __('View All URL', 'mhm-rentiva'),
                            value: viewAllUrl,
                            onChange: function (val) { setAttributes({ viewAllUrl: val }); },
                            help: __('Adds a "View All" button below the grid. Leave empty to hide.', 'mhm-rentiva')
                        }),
                        el(TextControl, {
                            label: __('Button Text', 'mhm-rentiva'),
                            value: viewAllText,
                            onChange: function (val) { setAttributes({ viewAllText: val }); },
                            help: __('Default: "View All Vehicles"', 'mhm-rentiva')
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
