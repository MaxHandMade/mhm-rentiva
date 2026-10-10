(function (blocks, element, blockEditor, components, serverSideRender, i18n) {
    var el = element.createElement;
    var registerBlockType = blocks.registerBlockType;
    var useBlockProps = blockEditor.useBlockProps;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var ServerSideRender = serverSideRender;
    var __ = i18n.__;

    blocks.registerBlockType('mhm-rentiva/vehicle-rating-form', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            var vehicleId = attributes.vehicleId;
            var className = attributes.className;

            // Login, review length and the review fields come from the global
            // Comments settings, which the form and its AJAX handler apply; the
            // block's own toggles for them were never read (spec 3A Task 8).
            return el('div', blockProps,
                el(InspectorControls, {},
                    /* PANEL 1: GENERAL */
                    el(PanelBody, { title: __('General Settings', 'mhm-rentiva'), initialOpen: true },
                        el(TextControl, {
                            label: __('Vehicle ID (Optional)', 'mhm-rentiva'),
                            value: vehicleId,
                            onChange: function (val) { setAttributes({ vehicleId: val }); },
                            help: __('Leave empty to auto-detect on Vehicle Details page.', 'mhm-rentiva')
                        })
                    ),

                    /* PANEL 2: LAYOUT */
                    el(PanelBody, { title: __('Layout', 'mhm-rentiva'), initialOpen: false },
                        el(TextControl, {
                            label: __('Custom CSS Class', 'mhm-rentiva'),
                            value: className,
                            onChange: function (val) { setAttributes({ className: val }); }
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: 'mhm-rentiva/vehicle-rating-form',
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
