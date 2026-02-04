(function (blocks, element, serverSideRender) {
    blocks.registerBlockType('mhm-rentiva/transfer-search', {
        edit: function (props) {
            return element.createElement(serverSideRender, {
                block: 'mhm-rentiva/transfer-search',
                attributes: props.attributes
            });
        },
        save: function () { return null; }
    });
}(window.wp.blocks, window.wp.element, window.wp.serverSideRender));
