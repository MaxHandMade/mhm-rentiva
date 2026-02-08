(function (blocks, element, blockEditor, components, serverSideRender) {
    var el = element.createElement;
    var registerBlockType = blocks.registerBlockType;
    var InspectorControls = blockEditor.InspectorControls;
    var useBlockProps = blockEditor.useBlockProps;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var SelectControl = components.SelectControl;
    var ToggleControl = components.ToggleControl;
    var RangeControl = components.RangeControl;

    // Check if ServerSideRender is default export or direct
    var ServerSideRender = serverSideRender.default || serverSideRender;

    var blockName = 'mhm-rentiva/featured-vehicles';

    registerBlockType(blockName, {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            return el('div', blockProps,
                el(InspectorControls, {},
                    el(PanelBody, { title: 'General Settings', initialOpen: true },
                        el(TextControl, {
                            label: 'Title',
                            value: attributes.title,
                            onChange: function (val) { setAttributes({ title: val }); }
                        }),
                        el(SelectControl, {
                            label: 'Layout',
                            value: attributes.layout,
                            options: [
                                { label: 'Slider', value: 'slider' },
                                { label: 'Grid', value: 'grid' }
                            ],
                            onChange: function (val) { setAttributes({ layout: val }); }
                        }),
                        el(RangeControl, {
                            label: 'Limit',
                            value: parseInt(attributes.limit) || 6,
                            onChange: function (val) { setAttributes({ limit: String(val) }); },
                            min: 1,
                            max: 20
                        }),
                        el(RangeControl, {
                            label: 'Columns (Grid/Slider View)',
                            value: parseInt(attributes.columns) || 3,
                            onChange: function (val) { setAttributes({ columns: String(val) }); },
                            min: 1,
                            max: 6
                        })
                    ),
                    el(PanelBody, { title: 'Filtering', initialOpen: false },
                        el(TextControl, {
                            label: 'Category Slug',
                            value: attributes.category,
                            onChange: function (val) { setAttributes({ category: val }); },
                            help: 'Enter vehicle category slug to filter.'
                        }),
                        el(TextControl, {
                            label: 'Vehicle IDs',
                            value: attributes.ids,
                            onChange: function (val) { setAttributes({ ids: val }); },
                            help: 'Comma separated list of specific vehicle IDs.'
                        })
                    ),
                    attributes.layout === 'slider' && el(PanelBody, { title: 'Slider Settings', initialOpen: false },
                        el(ToggleControl, {
                            label: 'Autoplay',
                            checked: attributes.autoplay === '1',
                            onChange: function (val) { setAttributes({ autoplay: val ? '1' : '0' }); }
                        }),
                        el(TextControl, {
                            label: 'Interval (ms)',
                            value: attributes.interval,
                            onChange: function (val) { setAttributes({ interval: val }); }
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: blockName,
                    attributes: attributes
                })
            );
        },
        save: function () {
            return null;
        }
    });

})(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.serverSideRender
);
