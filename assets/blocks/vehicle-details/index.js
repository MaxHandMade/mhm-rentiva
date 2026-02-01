(function (blocks, element, serverSideRender) {
    blocks.registerBlockType('mhm-rentiva/vehicle-details', {
        edit: function (props) {
            return element.createElement(serverSideRender, {
                block: 'mhm-rentiva/vehicle-details',
                attributes: props.attributes
            });
        },
        save: function () { return null; }
    });
}(window.wp.blocks, window.wp.element, window.wp.serverSideRender));
