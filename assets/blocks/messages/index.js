(function (blocks, element, serverSideRender) {
    blocks.registerBlockType('mhm-rentiva/messages', {
        edit: function (props) {
            return element.createElement(serverSideRender, {
                block: 'mhm-rentiva/messages',
                attributes: props.attributes
            });
        },
        save: function () { return null; }
    });
}(window.wp.blocks, window.wp.element, window.wp.serverSideRender));
