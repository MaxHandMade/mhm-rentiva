(function (blocks, element, serverSideRender) {
    blocks.registerBlockType('mhm-rentiva/payment-history', {
        edit: function (props) {
            return element.createElement(serverSideRender, {
                block: 'mhm-rentiva/payment-history',
                attributes: props.attributes
            });
        },
        save: function () { return null; }
    });
}(window.wp.blocks, window.wp.element, window.wp.serverSideRender));
