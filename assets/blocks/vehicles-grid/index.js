(function (blocks, element, serverSideRender) {
    blocks.registerBlockType('mhm-rentiva/vehicles-grid', {
        edit: function (props) {
            return element.createElement(serverSideRender, {
                block: 'mhm-rentiva/vehicles-grid',
                attributes: props.attributes
            });
        },
        save: function () { return null; }
    });
}(window.wp.blocks, window.wp.element, window.wp.serverSideRender));
