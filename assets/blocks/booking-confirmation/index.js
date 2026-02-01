(function (blocks, element, serverSideRender) {
    blocks.registerBlockType('mhm-rentiva/booking-confirmation', {
        edit: function (props) {
            return element.createElement(serverSideRender, {
                block: 'mhm-rentiva/booking-confirmation',
                attributes: props.attributes
            });
        },
        save: function () { return null; }
    });
}(window.wp.blocks, window.wp.element, window.wp.serverSideRender));
