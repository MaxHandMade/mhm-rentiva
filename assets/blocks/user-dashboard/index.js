(function (blocks, element, blockEditor, components, serverSideRender, i18n) {
    var el = element.createElement;
    var registerBlockType = blocks.registerBlockType;
    var useBlockProps = blockEditor.useBlockProps;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var ServerSideRender = serverSideRender;
    var __ = i18n.__;

    blocks.registerBlockType('mhm-rentiva/user-dashboard', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            var className = attributes.className;

            return el('div', blockProps,
                el(InspectorControls, {},
                    el(PanelBody, { title: __('Layout', 'mhm-rentiva'), initialOpen: true },
                        el(TextControl, {
                            label: __('Custom CSS Class', 'mhm-rentiva'),
                            value: className,
                            onChange: function (val) { setAttributes({ className: val }); }
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: 'mhm-rentiva/user-dashboard',
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
