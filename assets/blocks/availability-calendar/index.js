(function (blocks, element, serverSideRender) {
    blocks.registerBlockType('mhm-rentiva/availability-calendar', {
        edit: function (props) {
            return element.createElement(serverSideRender, {
                block: 'mhm-rentiva/availability-calendar',
                attributes: props.attributes
            });
        },
        save: function () { return null; }
    });
}(window.wp.blocks, window.wp.element, window.wp.serverSideRender));
