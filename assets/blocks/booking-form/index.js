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
                    /* PANEL 2: LAYOUT & STYLE */
                    el(PanelBody, { title: __('Layout & Style', 'mhm-rentiva'), initialOpen: false },
                        el(TextControl, {
                            label: __('Custom CSS Class', 'mhm-rentiva'),
                            value: attributes.className,
                            onChange: function (val) { setAttributes({ className: val }); }
                        })
                    ),

                    /* PANEL 3: VISIBILITY CONTROLS */
                    el(PanelBody, { title: __('Visibility Controls', 'mhm-rentiva'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('Show Extras', 'mhm-rentiva'),
                            checked: attributes.show_addons,
                            onChange: function (val) { setAttributes({ show_addons: val }); }
                        })
                    ),

                    /* PANEL 4: REDIRECT */
                    el(PanelBody, { title: __('Advanced Settings', 'mhm-rentiva'), initialOpen: false },
                        el(TextControl, {
                            label: __('Redirect URL', 'mhm-rentiva'),
                            help: __('URL to redirect after successful booking.', 'mhm-rentiva'),
                            value: attributes.redirectUrl,
                            onChange: function (val) { setAttributes({ redirectUrl: val }); }
                        })
                    ),

                    /* PANEL 5: ADVANCED DATE OVERRIDES */
                    el(PanelBody, { title: __('Advanced Date Overrides', 'mhm-rentiva'), initialOpen: false },
                        el(TextControl, {
                            label: __('Start Date', 'mhm-rentiva'),
                            help: __('Format: YYYY-MM-DD. Leave empty for dynamic.', 'mhm-rentiva'),
                            value: attributes.startDate,
                            onChange: function (val) { setAttributes({ startDate: val }); }
                        }),
                        el(TextControl, {
                            label: __('End Date', 'mhm-rentiva'),
                            help: __('Format: YYYY-MM-DD. Leave empty for dynamic.', 'mhm-rentiva'),
                            value: attributes.endDate,
                            onChange: function (val) { setAttributes({ endDate: val }); }
                        }),
                        el(TextControl, {
                            label: __('Default Days', 'mhm-rentiva'),
                            help: __('Prefills the return date this many days after the chosen pickup date. Leave empty for no prefill.', 'mhm-rentiva'),
                            type: 'number',
                            min: 1,
                            value: attributes.defaultDays,
                            onChange: function (val) { setAttributes({ defaultDays: val }); }
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
