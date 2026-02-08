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

    blocks.registerBlockType('mhm-rentiva/testimonials', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            var layout = attributes.layout;
            var showDate = attributes.showDate;
            var showAuthorAvatar = attributes.showAuthorAvatar;
            var showAuthorName = attributes.showAuthorName;
            var showRating = attributes.showRating;
            var showVehicleName = attributes.showVehicleName;
            var showQuotes = attributes.showQuotes;
            var filterRating = attributes.filterRating;
            var sortBy = attributes.sortBy;
            var sortOrder = attributes.sortOrder;
            var limitItems = attributes.limitItems;
            var columns = attributes.columns;
            var autoplay = attributes.autoplay;
            var className = attributes.className;

            return el('div', blockProps,
                el(InspectorControls, {},
                    /* PANEL 1: GENERAL */
                    el(PanelBody, { title: __('General Settings', 'mhm-rentiva'), initialOpen: true },
                        el(SelectControl, {
                            label: __('Layout', 'mhm-rentiva'),
                            value: layout,
                            options: [
                                { label: __('Carousel', 'mhm-rentiva'), value: 'carousel' },
                                { label: __('Grid', 'mhm-rentiva'), value: 'grid' },
                                { label: __('List', 'mhm-rentiva'), value: 'list' }
                            ],
                            onChange: function (val) { setAttributes({ layout: val }); }
                        }),
                        el(SelectControl, {
                            label: __('Filter by Rating', 'mhm-rentiva'),
                            value: filterRating,
                            options: [
                                { label: __('All', 'mhm-rentiva'), value: '' },
                                { label: __('5 Stars', 'mhm-rentiva'), value: '5' },
                                { label: __('4+ Stars', 'mhm-rentiva'), value: '4' },
                                { label: __('3+ Stars', 'mhm-rentiva'), value: '3' }
                            ],
                            onChange: function (val) { setAttributes({ filterRating: val }); }
                        }),
                        el(SelectControl, {
                            label: __('Sort By', 'mhm-rentiva'),
                            value: sortBy,
                            options: [
                                { label: __('Date', 'mhm-rentiva'), value: 'date' },
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
                            label: __('Limit Items', 'mhm-rentiva'),
                            value: limitItems,
                            type: 'number',
                            onChange: function (val) { setAttributes({ limitItems: val }); }
                        })
                    ),

                    /* PANEL 2: LAYOUT */
                    el(PanelBody, { title: __('Layout & Style', 'mhm-rentiva'), initialOpen: false },
                        el(TextControl, {
                            label: __('Custom CSS Class', 'mhm-rentiva'),
                            value: className,
                            onChange: function (val) { setAttributes({ className: val }); }
                        }),
                        (layout === 'grid' || layout === 'list') && el(SelectControl, {
                            label: __('Columns', 'mhm-rentiva'),
                            value: columns,
                            options: [
                                { label: '2', value: '2' },
                                { label: '3', value: '3' },
                                { label: '4', value: '4' }
                            ],
                            onChange: function (val) { setAttributes({ columns: val }); }
                        }),
                        (layout === 'carousel') && el(ToggleControl, {
                            label: __('Autoplay', 'mhm-rentiva'),
                            checked: autoplay,
                            onChange: function (val) { setAttributes({ autoplay: val }); }
                        })
                    ),

                    /* PANEL 3: VISIBILITY */
                    el(PanelBody, { title: __('Visibility Controls', 'mhm-rentiva'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('Show Date', 'mhm-rentiva'),
                            checked: showDate,
                            onChange: function (val) { setAttributes({ showDate: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Author Avatar', 'mhm-rentiva'),
                            checked: showAuthorAvatar,
                            onChange: function (val) { setAttributes({ showAuthorAvatar: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Author Name', 'mhm-rentiva'),
                            checked: showAuthorName,
                            onChange: function (val) { setAttributes({ showAuthorName: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Rating', 'mhm-rentiva'),
                            checked: showRating,
                            onChange: function (val) { setAttributes({ showRating: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Vehicle Name', 'mhm-rentiva'),
                            checked: showVehicleName,
                            onChange: function (val) { setAttributes({ showVehicleName: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Quotes', 'mhm-rentiva'),
                            checked: showQuotes,
                            onChange: function (val) { setAttributes({ showQuotes: val }); }
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: 'mhm-rentiva/testimonials',
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
