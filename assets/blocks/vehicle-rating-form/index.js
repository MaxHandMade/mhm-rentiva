(function (blocks, element, serverSideRender) {
    blocks.registerBlockType('mhm-rentiva/vehicle-rating-form', {
        edit: function (props) {
            return element.createElement(serverSideRender, {
                block: 'mhm-rentiva/vehicle-rating-form',
                attributes: props.attributes
            });
        },
        save: function () { return null; }
    });
}(window.wp.blocks, window.wp.element, window.wp.serverSideRender));
