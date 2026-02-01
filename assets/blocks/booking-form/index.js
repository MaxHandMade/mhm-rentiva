(function (blocks, element, serverSideRender) {
    blocks.registerBlockType('mhm-rentiva/booking-form', {
        edit: function (props) {
            return element.createElement(serverSideRender, {
                block: 'mhm-rentiva/booking-form',
                attributes: props.attributes
            });
        },
        save: function () { return null; }
    });
}(window.wp.blocks, window.wp.element, window.wp.serverSideRender));
