(function (blocks, element, serverSideRender) {
    blocks.registerBlockType('mhm-rentiva/search-results', {
        edit: function (props) {
            return element.createElement(serverSideRender, {
                block: 'mhm-rentiva/search-results',
                attributes: props.attributes
            });
        },
        save: function () { return null; }
    });
}(window.wp.blocks, window.wp.element, window.wp.serverSideRender));
