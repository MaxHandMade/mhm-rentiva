(function (blocks, element, blockEditor, components, serverSideRender, i18n) {
    var el = element.createElement;
    var registerBlockType = blocks.registerBlockType;
    var useBlockProps = blockEditor.useBlockProps;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var ToggleControl = components.ToggleControl;
    var ServerSideRender = serverSideRender;
    var __ = i18n.__;

    blocks.registerBlockType('mhm-rentiva/booking-form', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            return el('div', blockProps,
                el(InspectorControls, {},
                    /* PANEL 1: GENERAL SETTINGS */
                    el(PanelBody, { title: __('General Settings', 'mhm-rentiva'), initialOpen: true },
                        el(TextControl, {
                            label: __('Form Title', 'mhm-rentiva'),
                            value: attributes.title,
                            onChange: function (val) { setAttributes({ title: val }); }
                        }),
                        el(TextControl, {
                            label: __('Form Description', 'mhm-rentiva'),
                            value: attributes.description,
                            onChange: function (val) { setAttributes({ description: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Login Prompt', 'mhm-rentiva'),
                            checked: attributes.show_login_prompt,
                            onChange: function (val) { setAttributes({ show_login_prompt: val }); }
                        })
                    ),

                    /* PANEL 2: LAYOUT & STYLE */
                    el(PanelBody, { title: __('Layout & Style', 'mhm-rentiva'), initialOpen: false },
                        el(TextControl, {
                            label: __('Custom CSS Class', 'mhm-rentiva'),
                            value: attributes.className,
                            onChange: function (val) { setAttributes({ className: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Title', 'mhm-rentiva'),
                            checked: attributes.show_title,
                            onChange: function (val) { setAttributes({ show_title: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Description', 'mhm-rentiva'),
                            checked: attributes.show_description,
                            onChange: function (val) { setAttributes({ show_description: val }); }
                        })
                    ),

                    /* PANEL 3: VISIBILITY CONTROLS */
                    el(PanelBody, { title: __('Visibility Controls', 'mhm-rentiva'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('Show Date Picker', 'mhm-rentiva'),
                            checked: attributes.show_date_picker,
                            onChange: function (val) { setAttributes({ show_date_picker: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Insurance Options', 'mhm-rentiva'),
                            checked: attributes.show_insurance,
                            onChange: function (val) { setAttributes({ show_insurance: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Extras', 'mhm-rentiva'),
                            checked: attributes.show_extras,
                            onChange: function (val) { setAttributes({ show_extras: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Price Summary', 'mhm-rentiva'),
                            checked: attributes.show_price_summary,
                            onChange: function (val) { setAttributes({ show_price_summary: val }); }
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: 'mhm-rentiva/booking-form',
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
