(function (blocks, element, blockEditor, components, serverSideRender, i18n) {
    if (!blocks || !element || !blockEditor || !components || !serverSideRender || !i18n) {
        return;
    }

    var el = element.createElement;
    var useBlockProps = blockEditor.useBlockProps;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var ToggleControl = components.ToggleControl;
    var TextControl = components.TextControl;
    var SelectControl = components.SelectControl;
    var ServerSideRender = serverSideRender;
    var __ = i18n.__;

    var yesNoToggle = function (label, key, attributes, setAttributes) {
        return el(ToggleControl, {
            label: label,
            checked: attributes[key] === 'yes',
            onChange: function (val) {
                var update = {};
                update[key] = val ? 'yes' : 'no';
                setAttributes(update);
            }
        });
    };

    blocks.registerBlockType('mhm-rentiva/vendor-directory', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            return el('div', blockProps,
                el(InspectorControls, {},
                    el(PanelBody, { title: __('Layout', 'mhm-rentiva'), initialOpen: true },
                        el(TextControl, {
                            label: __('Vendors per page', 'mhm-rentiva'),
                            type: 'number',
                            value: attributes.per_page,
                            help: __('Number of vendors per page (1-50).', 'mhm-rentiva'),
                            onChange: function (val) { setAttributes({ per_page: val }); }
                        }),
                        el(SelectControl, {
                            label: __('Default sort', 'mhm-rentiva'),
                            value: attributes.default_sort,
                            options: [
                                { label: __('Rating', 'mhm-rentiva'), value: 'rating' },
                                { label: __('Newest first', 'mhm-rentiva'), value: 'newest' },
                                { label: __('Alphabetical', 'mhm-rentiva'), value: 'alpha' }
                            ],
                            onChange: function (val) { setAttributes({ default_sort: val }); }
                        })
                    ),
                    el(PanelBody, { title: __('Sections', 'mhm-rentiva'), initialOpen: false },
                        yesNoToggle(__('Filter bar', 'mhm-rentiva'), 'show_filter_bar', attributes, setAttributes),
                        yesNoToggle(__('Breadcrumb', 'mhm-rentiva'), 'show_breadcrumb', attributes, setAttributes),
                        yesNoToggle(__('Pagination', 'mhm-rentiva'), 'show_pagination', attributes, setAttributes)
                    ),
                    el(PanelBody, { title: __('Empty State', 'mhm-rentiva'), initialOpen: false },
                        el(TextControl, {
                            label: __('Empty message', 'mhm-rentiva'),
                            value: attributes.empty_message || '',
                            help: __('Custom text when no vendors match the current filters.', 'mhm-rentiva'),
                            onChange: function (val) { setAttributes({ empty_message: val }); }
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: 'mhm-rentiva/vendor-directory',
                    attributes: attributes
                })
            );
        },
        save: function () {
            // Server-side rendered.
            return null;
        }
    });
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.serverSideRender, window.wp.i18n);
