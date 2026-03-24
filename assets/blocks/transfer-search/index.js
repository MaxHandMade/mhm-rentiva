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

    blocks.registerBlockType('mhm-rentiva/transfer-search', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            return el('div', blockProps,
                el(InspectorControls, {},
                    /* PANEL 1: LAYOUT SETTINGS */
                    el(PanelBody, { title: __('Layout Settings', 'mhm-rentiva'), initialOpen: true },
                        el(SelectControl, {
                            label: __('Layout', 'mhm-rentiva'),
                            value: attributes.layout,
                            options: [
                                { label: __('Horizontal', 'mhm-rentiva'), value: 'horizontal' },
                                { label: __('Vertical', 'mhm-rentiva'), value: 'vertical' }
                            ],
                            onChange: function (val) { setAttributes({ layout: val }); }
                        }),
                        el(TextControl, {
                            label: __('Button Text', 'mhm-rentiva'),
                            value: attributes.buttonText,
                            placeholder: __('Search Transfer', 'mhm-rentiva'),
                            onChange: function (val) { setAttributes({ buttonText: val }); }
                        })
                    ),

                    /* PANEL 2: VISIBILITY */
                    el(PanelBody, { title: __('Visibility', 'mhm-rentiva'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('Show Pickup Location', 'mhm-rentiva'),
                            checked: attributes.showPickup,
                            onChange: function (val) { setAttributes({ showPickup: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Dropoff Location', 'mhm-rentiva'),
                            checked: attributes.showDropoff,
                            onChange: function (val) { setAttributes({ showDropoff: val }); }
                        })
                    ),

                    /* PANEL 3: ADVANCED */
                    el(PanelBody, { title: __('Advanced', 'mhm-rentiva'), initialOpen: false },
                        el(TextControl, {
                            label: __('Custom CSS Class', 'mhm-rentiva'),
                            value: attributes.className,
                            onChange: function (val) { setAttributes({ className: val }); }
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: 'mhm-rentiva/transfer-search',
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
