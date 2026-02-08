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

    blocks.registerBlockType('mhm-rentiva/booking-confirmation', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            var bookingId = attributes.bookingId;
            var showBookingDetails = attributes.showBookingDetails;
            var showVehicleInfo = attributes.showVehicleInfo;
            var showPaymentSummary = attributes.showPaymentSummary;
            var showPickupInstructions = attributes.showPickupInstructions;
            var showContactInfo = attributes.showContactInfo;
            var showPrintButton = attributes.showPrintButton;
            var showDownloadPDF = attributes.showDownloadPDF;
            var showCancelButton = attributes.showCancelButton;
            var className = attributes.className;

            return el('div', blockProps,
                el(InspectorControls, {},
                    /* PANEL 1: GENERAL */
                    el(PanelBody, { title: __('General Settings', 'mhm-rentiva'), initialOpen: true },
                        el(TextControl, {
                            label: __('Booking ID (Optional)', 'mhm-rentiva'),
                            value: bookingId,
                            onChange: function (val) { setAttributes({ bookingId: val }); },
                            help: __('Leave empty to auto-detect on Confirmation page.', 'mhm-rentiva')
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
                            label: __('Show Booking Details', 'mhm-rentiva'),
                            checked: showBookingDetails,
                            onChange: function (val) { setAttributes({ showBookingDetails: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Vehicle Info', 'mhm-rentiva'),
                            checked: showVehicleInfo,
                            onChange: function (val) { setAttributes({ showVehicleInfo: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Payment Summary', 'mhm-rentiva'),
                            checked: showPaymentSummary,
                            onChange: function (val) { setAttributes({ showPaymentSummary: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Pickup Instructions', 'mhm-rentiva'),
                            checked: showPickupInstructions,
                            onChange: function (val) { setAttributes({ showPickupInstructions: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Contact Info', 'mhm-rentiva'),
                            checked: showContactInfo,
                            onChange: function (val) { setAttributes({ showContactInfo: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Print Button', 'mhm-rentiva'),
                            checked: showPrintButton,
                            onChange: function (val) { setAttributes({ showPrintButton: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Download PDF', 'mhm-rentiva'),
                            checked: showDownloadPDF,
                            onChange: function (val) { setAttributes({ showDownloadPDF: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Cancel Button', 'mhm-rentiva'),
                            checked: showCancelButton,
                            onChange: function (val) { setAttributes({ showCancelButton: val }); }
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: 'mhm-rentiva/booking-confirmation',
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
