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

    blocks.registerBlockType('mhm-rentiva/vehicle-comparison', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            var vehicleIds = attributes.vehicleIds;
            var showComparisonImages = attributes.showComparisonImages;
            var showPrice = attributes.showPrice;
            var showBookButton = attributes.showBookButton;
            var maxVehicles = attributes.maxVehicles;
            var className = attributes.className;

            return el('div', blockProps,
                el(InspectorControls, {},
                    /* PANEL 1: GENERAL */
                    el(PanelBody, { title: __('General Settings', 'mhm-rentiva'), initialOpen: true },
                        el(TextControl, {
                            label: __('Vehicle IDs (Optional)', 'mhm-rentiva'),
                            value: vehicleIds,
                            onChange: function (val) { setAttributes({ vehicleIds: val }); },
                            help: __('Comma-separated list of vehicle IDs to compare.', 'mhm-rentiva')
                        }),
                        el(TextControl, {
                            label: __('Max Vehicles to Compare', 'mhm-rentiva'),
                            value: maxVehicles,
                            type: 'number',
                            onChange: function (val) { setAttributes({ maxVehicles: val }); }
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
                            label: __('Show Comparison Images', 'mhm-rentiva'),
                            checked: showComparisonImages,
                            onChange: function (val) { setAttributes({ showComparisonImages: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Price', 'mhm-rentiva'),
                            checked: showPrice,
                            onChange: function (val) { setAttributes({ showPrice: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Book Button', 'mhm-rentiva'),
                            checked: showBookButton,
                            onChange: function (val) { setAttributes({ showBookButton: val }); }
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: 'mhm-rentiva/vehicle-comparison',
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
