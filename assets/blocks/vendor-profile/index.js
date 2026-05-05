(function (blocks, element, blockEditor, components, serverSideRender, i18n) {
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

    blocks.registerBlockType('mhm-rentiva/vendor-profile', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            return el('div', blockProps,
                el(InspectorControls, {},
                    el(PanelBody, { title: __('Vendor', 'mhm-rentiva'), initialOpen: true },
                        el(TextControl, {
                            label: __('Vendor slug', 'mhm-rentiva'),
                            value: attributes.slug || '',
                            placeholder: 'akif-otomotiv',
                            help: __("The vendor's public profile slug. Required.", 'mhm-rentiva'),
                            onChange: function (val) { setAttributes({ slug: val }); }
                        })
                    ),
                    el(PanelBody, { title: __('Sections', 'mhm-rentiva'), initialOpen: false },
                        yesNoToggle(__('Badge', 'mhm-rentiva'), 'show_badge', attributes, setAttributes),
                        yesNoToggle(__('Rating', 'mhm-rentiva'), 'show_rating', attributes, setAttributes),
                        yesNoToggle(__('About', 'mhm-rentiva'), 'show_about', attributes, setAttributes),
                        yesNoToggle(__('Vehicles', 'mhm-rentiva'), 'show_vehicles', attributes, setAttributes),
                        yesNoToggle(__('Reviews', 'mhm-rentiva'), 'show_reviews', attributes, setAttributes),
                        yesNoToggle(__('Location', 'mhm-rentiva'), 'show_location', attributes, setAttributes)
                    ),
                    el(PanelBody, { title: __('Limits', 'mhm-rentiva'), initialOpen: false },
                        el(TextControl, {
                            label: __('Max vehicles', 'mhm-rentiva'),
                            type: 'number',
                            value: attributes.max_vehicles,
                            onChange: function (val) { setAttributes({ max_vehicles: val }); }
                        }),
                        el(TextControl, {
                            label: __('Max reviews', 'mhm-rentiva'),
                            type: 'number',
                            value: attributes.max_reviews,
                            onChange: function (val) { setAttributes({ max_reviews: val }); }
                        }),
                        el(SelectControl, {
                            label: __('Vehicle sort order', 'mhm-rentiva'),
                            value: attributes.vehicle_sort,
                            options: [
                                { label: __('Rating, then newest', 'mhm-rentiva'), value: 'rating-newest' },
                                { label: __('Newest first', 'mhm-rentiva'), value: 'newest' },
                                { label: __('Price (low to high)', 'mhm-rentiva'), value: 'price-asc' },
                                { label: __('Price (high to low)', 'mhm-rentiva'), value: 'price-desc' }
                            ],
                            onChange: function (val) { setAttributes({ vehicle_sort: val }); }
                        })
                    ),
                    el(PanelBody, { title: __('Empty States', 'mhm-rentiva'), initialOpen: false },
                        el(TextControl, {
                            label: __('Empty vehicles message', 'mhm-rentiva'),
                            value: attributes.empty_vehicles_message || '',
                            help: __('Custom text when vendor has no public vehicles.', 'mhm-rentiva'),
                            onChange: function (val) { setAttributes({ empty_vehicles_message: val }); }
                        }),
                        el(TextControl, {
                            label: __('Empty reviews message', 'mhm-rentiva'),
                            value: attributes.empty_reviews_message || '',
                            help: __('Custom text when vendor has no reviews yet.', 'mhm-rentiva'),
                            onChange: function (val) { setAttributes({ empty_reviews_message: val }); }
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: 'mhm-rentiva/vendor-profile',
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
