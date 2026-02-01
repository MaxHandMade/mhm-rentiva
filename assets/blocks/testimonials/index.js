(function (blocks, element, serverSideRender) {
    blocks.registerBlockType('mhm-rentiva/testimonials', {
        edit: function (props) {
            return element.createElement(serverSideRender, {
                block: 'mhm-rentiva/testimonials',
                attributes: props.attributes
            });
        },
        save: function () { return null; }
    });
}(window.wp.blocks, window.wp.element, window.wp.serverSideRender));
