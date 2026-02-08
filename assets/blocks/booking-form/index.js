
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

    var Edit = function (props) {
        var attributes = props.attributes;
        var setAttributes = props.setAttributes;
        var blockProps = useBlockProps();

        var form_title = attributes.form_title;
        var vehicle_id = attributes.vehicle_id;
        var show_vehicle_selector = attributes.show_vehicle_selector;
        var show_vehicle_info = attributes.show_vehicle_info;
        var show_time_select = attributes.show_time_select;
        var show_addons = attributes.show_addons;
        var show_payment_options = attributes.show_payment_options;
        var enable_deposit = attributes.enable_deposit;

        return el(
            'div',
            blockProps,
            el(
                InspectorControls,
                {},
                el(
                    PanelBody,
                    { title: __('General Settings', 'mhm-rentiva'), initialOpen: true },
                    el(TextControl, {
                        label: __('Form Title', 'mhm-rentiva'),
                        value: form_title,
                        onChange: function (val) { setAttributes({ form_title: val }); },
                        help: __('Optional title to display above the form.', 'mhm-rentiva')
                    }),
                    el(TextControl, {
                        label: __('Pre-selected Vehicle ID', 'mhm-rentiva'),
                        value: vehicle_id,
                        onChange: function (val) { setAttributes({ vehicle_id: val }); },
                        help: __('Enter a vehicle ID to pre-select it. Leave empty to allow user selection.', 'mhm-rentiva')
                    })
                ),
                el(
                    PanelBody,
                    { title: __('Visibility Settings', 'mhm-rentiva'), initialOpen: true },
                    el(ToggleControl, {
                        label: __('Show Vehicle Selector', 'mhm-rentiva'),
                        checked: show_vehicle_selector,
                        onChange: function (val) { setAttributes({ show_vehicle_selector: val }); }
                    }),
                    el(ToggleControl, {
                        label: __('Show Vehicle Info', 'mhm-rentiva'),
                        checked: show_vehicle_info,
                        onChange: function (val) { setAttributes({ show_vehicle_info: val }); },
                        help: __('Display details of the selected vehicle.', 'mhm-rentiva')
                    }),
                    el(ToggleControl, {
                        label: __('Show Time Selection', 'mhm-rentiva'),
                        checked: show_time_select,
                        onChange: function (val) { setAttributes({ show_time_select: val }); },
                        help: __('Allow users to select pickup/dropoff times.', 'mhm-rentiva')
                    }),
                    el(ToggleControl, {
                        label: __('Show Additional Services', 'mhm-rentiva'),
                        checked: show_addons,
                        onChange: function (val) { setAttributes({ show_addons: val }); }
                    }),
                    el(ToggleControl, {
                        label: __('Show Payment Options', 'mhm-rentiva'),
                        checked: show_payment_options,
                        onChange: function (val) { setAttributes({ show_payment_options: val }); }
                    })
                ),
                el(
                    PanelBody,
                    { title: __('Payment Settings', 'mhm-rentiva'), initialOpen: false },
                    el(ToggleControl, {
                        label: __('Enable Deposit System', 'mhm-rentiva'),
                        checked: enable_deposit,
                        onChange: function (val) { setAttributes({ enable_deposit: val }); },
                        help: __('Allow split payment (Deposit + Remaining).', 'mhm-rentiva')
                    })
                )
            ),
            el(
                ServerSideRender,
                {
                    block: 'mhm-rentiva/booking-form',
                    attributes: attributes
                }
            )
        );
    };

    registerBlockType('mhm-rentiva/booking-form', {
        edit: Edit,
        save: function () { return null; }
    });

})(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.serverSideRender,
    window.wp.i18n
);
