(function (blocks, element, serverSideRender) {
    blocks.registerBlockType('mhm-rentiva/my-favorites', {
        edit: function (props) {
            return element.createElement(serverSideRender, {
                block: 'mhm-rentiva/my-favorites',
                attributes: props.attributes
            });
        },
        save: function () { return null; }
    });
}(window.wp.blocks, window.wp.element, window.wp.serverSideRender));
