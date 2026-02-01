(function (blocks, element, serverSideRender) {
    blocks.registerBlockType('mhm-rentiva/contact', {
        edit: function (props) {
            return element.createElement(serverSideRender, {
                block: 'mhm-rentiva/contact',
                attributes: props.attributes
            });
        },
        save: function () { return null; }
    });
}(window.wp.blocks, window.wp.element, window.wp.serverSideRender));
