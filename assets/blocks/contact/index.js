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

            var recipientEmail = attributes.recipientEmail;
            var subjectPrefix = attributes.subjectPrefix;
            var successMessage = attributes.successMessage;
            var showPhoneField = attributes.showPhoneField;
            var showSubjectField = attributes.showSubjectField;
            var showBookingIdField = attributes.showBookingIdField;
            var showVehicleSelect = attributes.showVehicleSelect;
            var showCompanyInfo = attributes.showCompanyInfo;
            var showMap = attributes.showMap;
            var showSocialLinks = attributes.showSocialLinks;
            var className = attributes.className;

            return el('div', blockProps,
                el(InspectorControls, {},
                    /* PANEL 1: GENERAL */
                    el(PanelBody, { title: __('General Settings', 'mhm-rentiva'), initialOpen: true },
                        el(TextControl, {
                            label: __('Recipient Email', 'mhm-rentiva'),
                            value: recipientEmail,
                            onChange: function (val) { setAttributes({ recipientEmail: val }); },
                            help: __('Overrides global setting.', 'mhm-rentiva')
                        }),
                        el(TextControl, {
                            label: __('Subject Prefix', 'mhm-rentiva'),
                            value: subjectPrefix,
                            onChange: function (val) { setAttributes({ subjectPrefix: val }); }
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
                            checked: showPhoneField,
                            onChange: function (val) { setAttributes({ showPhoneField: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Subject Field', 'mhm-rentiva'),
                            checked: showSubjectField,
                            onChange: function (val) { setAttributes({ showSubjectField: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Booking ID Field', 'mhm-rentiva'),
                            checked: showBookingIdField,
                            onChange: function (val) { setAttributes({ showBookingIdField: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Vehicle Select', 'mhm-rentiva'),
                            checked: showVehicleSelect,
                            onChange: function (val) { setAttributes({ showVehicleSelect: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Company Info', 'mhm-rentiva'),
                            checked: showCompanyInfo,
                            onChange: function (val) { setAttributes({ showCompanyInfo: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Map', 'mhm-rentiva'),
                            checked: showMap,
                            onChange: function (val) { setAttributes({ showMap: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Social Links', 'mhm-rentiva'),
                            checked: showSocialLinks,
                            onChange: function (val) { setAttributes({ showSocialLinks: val }); }
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
