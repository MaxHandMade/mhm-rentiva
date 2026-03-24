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
                            checked: attributes.show_addons,
                            onChange: function (val) { setAttributes({ show_addons: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Price Summary', 'mhm-rentiva'),
                            checked: attributes.show_price_summary,
                            onChange: function (val) { setAttributes({ show_price_summary: val }); }
                        })
                    ),

                    /* PANEL 4: REDIRECT & PAYMENT */
                    el(PanelBody, { title: __('Redirect & Payment', 'mhm-rentiva'), initialOpen: false },
                        el(TextControl, {
                            label: __('Redirect URL', 'mhm-rentiva'),
                            help: __('URL to redirect after successful booking.', 'mhm-rentiva'),
                            value: attributes.redirectUrl,
                            onChange: function (val) { setAttributes({ redirectUrl: val }); }
                        }),
                        el(components.SelectControl, {
                            label: __('Default Payment', 'mhm-rentiva'),
                            value: attributes.defaultPayment,
                            options: [
                                { label: __('Deposit', 'mhm-rentiva'), value: 'deposit' },
                                { label: __('Full Payment', 'mhm-rentiva'), value: 'full' }
                            ],
                            onChange: function (val) { setAttributes({ defaultPayment: val }); }
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
                            type: 'number',
                            value: attributes.defaultDays,
                            onChange: function (val) { setAttributes({ defaultDays: val }); }
                        }),
                        el(TextControl, {
                            label: __('Min Days', 'mhm-rentiva'),
                            type: 'number',
                            value: attributes.minDays,
                            onChange: function (val) { setAttributes({ minDays: val }); }
                        }),
                        el(TextControl, {
                            label: __('Max Days', 'mhm-rentiva'),
                            type: 'number',
                            value: attributes.maxDays,
                            onChange: function (val) { setAttributes({ maxDays: val }); }
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
