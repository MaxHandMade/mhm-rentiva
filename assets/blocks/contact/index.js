(function (blocks, element, blockEditor, components, serverSideRender, i18n) {
    var el = element.createElement;
    var registerBlockType = blocks.registerBlockType;
    var useBlockProps = blockEditor.useBlockProps;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var ToggleControl = components.ToggleControl;
    var TextControl = components.TextControl;
    var ServerSideRender = serverSideRender;
    var __ = i18n.__;

    blocks.registerBlockType('mhm-rentiva/contact', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            // Each control writes the live attribute its shortcode key reads
            // (slice 3A: "Recipient Email" -> emailTo, "Show Phone Field" -> showPhone,
            // "Show Vehicle Select" -> showVehicleSelector).
            var emailTo = attributes.emailTo;
            var successMessage = attributes.successMessage;
            var showPhone = attributes.showPhone;
            var showBookingIdField = attributes.showBookingIdField;
            var showVehicleSelector = attributes.showVehicleSelector;
            var showCompanyInfo = attributes.showCompanyInfo;
            var className = attributes.className;

            return el('div', blockProps,
                el(InspectorControls, {},
                    /* PANEL 1: GENERAL */
                    el(PanelBody, { title: __('General Settings', 'mhm-rentiva'), initialOpen: true },
                        el(TextControl, {
                            label: __('Recipient Email', 'mhm-rentiva'),
                            value: emailTo,
                            onChange: function (val) { setAttributes({ emailTo: val }); },
                            help: __("Overrides the global address only when this page's author can edit others' posts.", 'mhm-rentiva')
                        }),
                        el(TextControl, {
                            label: __('Success Message', 'mhm-rentiva'),
                            value: successMessage,
                            onChange: function (val) { setAttributes({ successMessage: val }); }
                        })
                    ),

                    /* PANEL 2: LAYOUT */
                    el(PanelBody, { title: __('Layout', 'mhm-rentiva'), initialOpen: false },
                        el(TextControl, {
                            label: __('Custom CSS Class', 'mhm-rentiva'),
                            value: className,
                            onChange: function (val) { setAttributes({ className: val }); }
                        })
                    ),

                    /* PANEL 3: VISIBILITY */
                    el(PanelBody, { title: __('Visibility Controls', 'mhm-rentiva'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('Show Phone Field', 'mhm-rentiva'),
                            checked: showPhone,
                            onChange: function (val) { setAttributes({ showPhone: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Booking ID Field', 'mhm-rentiva'),
                            checked: showBookingIdField,
                            onChange: function (val) { setAttributes({ showBookingIdField: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Vehicle Select', 'mhm-rentiva'),
                            checked: showVehicleSelector,
                            onChange: function (val) { setAttributes({ showVehicleSelector: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Company Info', 'mhm-rentiva'),
                            checked: showCompanyInfo,
                            onChange: function (val) { setAttributes({ showCompanyInfo: val }); }
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: 'mhm-rentiva/contact',
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
