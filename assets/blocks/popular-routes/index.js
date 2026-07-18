(function (blocks, element, blockEditor, components, serverSideRender, i18n) {
    var el = element.createElement;
    var useBlockProps = blockEditor.useBlockProps;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var ToggleControl = components.ToggleControl;
    var TextControl = components.TextControl;
    var SelectControl = components.SelectControl;
    var ServerSideRender = serverSideRender;
    var __ = i18n.__;

    blocks.registerBlockType('mhm-rentiva/popular-routes', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            return el('div', blockProps,
                el(InspectorControls, {},
                    el(PanelBody, { title: __('Layout', 'mhm-rentiva'), initialOpen: true },
                        el(SelectControl, {
                            label: __('Columns (desktop)', 'mhm-rentiva'),
                            value: attributes.columns,
                            options: [
                                { label: '2', value: '2' },
                                { label: '3', value: '3' },
                                { label: '4', value: '4' }
                            ],
                            onChange: function (val) { setAttributes({ columns: val }); }
                        }),
                        el(TextControl, {
                            label: __('Maximum cards', 'mhm-rentiva'),
                            value: attributes.limit,
                            type: 'number',
                            help: __('The most route cards to display.', 'mhm-rentiva'),
                            onChange: function (val) { setAttributes({ limit: val }); }
                        }),
                        el(SelectControl, {
                            label: __('Theme', 'mhm-rentiva'),
                            value: attributes.theme,
                            options: [
                                { label: __('Light', 'mhm-rentiva'), value: 'light' },
                                { label: __('Dark', 'mhm-rentiva'), value: 'dark' }
                            ],
                            onChange: function (val) { setAttributes({ theme: val }); }
                        })
                    ),

                    el(PanelBody, { title: __('Heading', 'mhm-rentiva'), initialOpen: false },
                        el(TextControl, {
                            label: __('Title', 'mhm-rentiva'),
                            value: attributes.heading,
                            placeholder: __('Popular Routes', 'mhm-rentiva'),
                            onChange: function (val) { setAttributes({ heading: val }); }
                        }),
                        el(TextControl, {
                            label: __('Subtitle', 'mhm-rentiva'),
                            value: attributes.subheading,
                            placeholder: __('Most preferred VIP transfer routes', 'mhm-rentiva'),
                            onChange: function (val) { setAttributes({ subheading: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show "View all" link', 'mhm-rentiva'),
                            checked: attributes.showViewAll,
                            onChange: function (val) { setAttributes({ showViewAll: val }); }
                        }),
                        attributes.showViewAll ? el(TextControl, {
                            label: __('"View all" URL', 'mhm-rentiva'),
                            value: attributes.viewAllUrl,
                            help: __('Leave empty to use the default transfer-search URL filter.', 'mhm-rentiva'),
                            onChange: function (val) { setAttributes({ viewAllUrl: val }); }
                        }) : null
                    ),

                    el(PanelBody, { title: __('Sorting & Filters', 'mhm-rentiva'), initialOpen: false },
                        el(SelectControl, {
                            label: __('Sort order', 'mhm-rentiva'),
                            value: attributes.order,
                            options: [
                                { label: __('Featured (pinned first)', 'mhm-rentiva'), value: 'featured' },
                                { label: __('Price (low → high)', 'mhm-rentiva'), value: 'price_asc' },
                                { label: __('Price (high → low)', 'mhm-rentiva'), value: 'price_desc' },
                                { label: __('Alphabetical', 'mhm-rentiva'), value: 'alphabetical' },
                                { label: __('Newest first', 'mhm-rentiva'), value: 'newest' }
                            ],
                            onChange: function (val) { setAttributes({ order: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show only pinned ("Vitrine") routes', 'mhm-rentiva'),
                            checked: attributes.featuredOnly,
                            onChange: function (val) { setAttributes({ featuredOnly: val }); }
                        }),
                        el(TextControl, {
                            label: __('Filter by origin city', 'mhm-rentiva'),
                            value: attributes.filterOriginCity,
                            placeholder: __('e.g. Istanbul', 'mhm-rentiva'),
                            onChange: function (val) { setAttributes({ filterOriginCity: val }); }
                        }),
                        el(SelectControl, {
                            label: __('Filter by origin type', 'mhm-rentiva'),
                            value: attributes.filterOriginType,
                            options: [
                                { label: __('All types', 'mhm-rentiva'), value: '' },
                                { label: __('Airport', 'mhm-rentiva'), value: 'airport' },
                                { label: __('Train station', 'mhm-rentiva'), value: 'train' },
                                { label: __('Hotel', 'mhm-rentiva'), value: 'hotel' },
                                { label: __('Marina / Port', 'mhm-rentiva'), value: 'marina' },
                                { label: __('City center', 'mhm-rentiva'), value: 'city_center' }
                            ],
                            onChange: function (val) { setAttributes({ filterOriginType: val }); }
                        })
                    ),

                    el(PanelBody, { title: __('Card Display', 'mhm-rentiva'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('Show duration', 'mhm-rentiva'),
                            checked: attributes.showDuration,
                            onChange: function (val) { setAttributes({ showDuration: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show distance', 'mhm-rentiva'),
                            checked: attributes.showDistance,
                            onChange: function (val) { setAttributes({ showDistance: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show traffic note', 'mhm-rentiva'),
                            checked: attributes.showTrafficNote,
                            onChange: function (val) { setAttributes({ showTrafficNote: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show starting price', 'mhm-rentiva'),
                            checked: attributes.showPrice,
                            onChange: function (val) { setAttributes({ showPrice: val }); }
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: 'mhm-rentiva/popular-routes',
                    attributes: attributes
                })
            );
        },
        save: function () {
            // Server-side rendered.
            return null;
        }
    });
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.serverSideRender, window.wp.i18n);
