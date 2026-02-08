(function (blocks, element, components, serverSideRender) {
    var el = element.createElement;
    var registerBlockType = blocks.registerBlockType;
    var ServerSideRender = serverSideRender;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var SelectControl = components.SelectControl;
    var ToggleControl = components.ToggleControl;
    var RangeControl = components.RangeControl;

    registerBlockType('mhm-rentiva/vehicles-list', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var useBlockProps = wp.blockEditor.useBlockProps;
            var blockProps = useBlockProps();

            return el(
                'div',
                blockProps,
                el(
                    InspectorControls,
                    {},
                    el(
                        PanelBody,
                        { title: 'Query Settings', initialOpen: true },
                        el(RangeControl, {
                            label: 'Number of Vehicles',
                            value: parseInt(attributes.limit),
                            onChange: function (val) { setAttributes({ limit: val.toString() }); },
                            min: 1,
                            max: 24
                        }),
                        el(SelectControl, {
                            label: 'Order By',
                            value: attributes.orderby,
                            options: [
                                { label: 'Title', value: 'title' },
                                { label: 'Price', value: 'price' },
                                { label: 'Date', value: 'date' },
                                { label: 'Random', value: 'rand' }
                            ],
                            onChange: function (val) { setAttributes({ orderby: val }); },
                            __next40pxDefaultSize: true
                        }),
                        el(SelectControl, {
                            label: 'Order',
                            value: attributes.order,
                            options: [
                                { label: 'Ascending', value: 'ASC' },
                                { label: 'Descending', value: 'DESC' }
                            ],
                            onChange: function (val) { setAttributes({ order: val }); },
                            __next40pxDefaultSize: true
                        }),
                        el(ToggleControl, {
                            label: 'Featured Only',
                            checked: attributes.featured === '1',
                            onChange: function (val) { setAttributes({ featured: val ? '1' : '0' }); }
                        })
                    ),
                    el(
                        PanelBody,
                        { title: 'Display Options', initialOpen: false },
                        el(RangeControl, {
                            label: 'Columns',
                            value: parseInt(attributes.columns),
                            onChange: function (val) { setAttributes({ columns: val.toString() }); },
                            min: 1,
                            max: 4
                        }),
                        el(ToggleControl, {
                            label: 'Show Image',
                            checked: attributes.show_image === '1', // Already snake_case in source but confused? No, block.json had showImage? No wait.
                            // Checking block.json again: "showPrice" was camelCase. "showImage" was NOT in block.json I pasted?
                            // Ah, I added show_image to block.json.
                            // Let's stick to snake_case for all.
                            onChange: function (val) { setAttributes({ show_image: val ? '1' : '0' }); }
                        }),
                        el(ToggleControl, {
                            label: 'Show Price',
                            checked: attributes.show_price === '1',
                            onChange: function (val) { setAttributes({ show_price: val ? '1' : '0' }); }
                        }),
                        el(ToggleControl, {
                            label: 'Show Booking Button',
                            checked: attributes.show_booking_btn === '1',
                            onChange: function (val) { setAttributes({ show_booking_btn: val ? '1' : '0' }); }
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: 'mhm-rentiva/vehicles-list',
                    attributes: attributes
                })
            );
        },
        save: function () {
            return null; // Rendered via PHP
        },
    });
}(window.wp.blocks, window.wp.element, window.wp.components, window.wp.serverSideRender));
