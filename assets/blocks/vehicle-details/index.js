(function (blocks, element, blockEditor, components, serverSideRender, i18n) {
    var el = element.createElement;
    var registerBlockType = blocks.registerBlockType;
    var useBlockProps = blockEditor.useBlockProps;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var ToggleControl = components.ToggleControl;
    var TextControl = components.TextControl;
    var ServerSideRender = serverSideRender;
    var __ = i18n.__;

    blocks.registerBlockType('mhm-rentiva/vehicle-details', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            var vehicleId = attributes.vehicleId;
            var showGallery = attributes.showGallery;
            var showPrice = attributes.showPrice;
            var showRating = attributes.showRating;
            var showReviews = attributes.showReviews;
            var showFeatures = attributes.showFeatures;
            var showTechnicalSpecs = attributes.showTechnicalSpecs;
            var showAvailability = attributes.showAvailability;
            var showBookingForm = attributes.showBookingForm;
            var showSimilarVehicles = attributes.showSimilarVehicles;
            var showShareButtons = attributes.showShareButtons;
            var showFavoriteButton = attributes.showFavoriteButton;
            var showBreadcrumb = attributes.showBreadcrumb;
            var similarVehiclesLimit = attributes.similarVehiclesLimit;
            var className = attributes.className;

            return el('div', blockProps,
                el(InspectorControls, {},
                    /* PANEL 1: GENERAL */
                    el(PanelBody, { title: __('General Settings', 'mhm-rentiva'), initialOpen: true },
                        el(TextControl, {
                            label: __('Vehicle ID (Optional)', 'mhm-rentiva'),
                            value: vehicleId,
                            onChange: function (val) { setAttributes({ vehicleId: val }); },
                            help: __('Leave empty to auto-detect on Vehicle Details page.', 'mhm-rentiva')
                        }),
                        el(TextControl, {
                            label: __('Similar Vehicles Limit', 'mhm-rentiva'),
                            value: similarVehiclesLimit,
                            type: 'number',
                            onChange: function (val) { setAttributes({ similarVehiclesLimit: val }); }
                        })
                    ),

                    /* PANEL 2: LAYOUT */
                    el(PanelBody, { title: __('Layout', 'mhm-rentiva'), initialOpen: false },
                        el(TextControl, {
                            label: __('Custom CSS Class', 'mhm-rentiva'),
                            value: className,
                            onChange: function (val) { setAttributes({ className: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Breadcrumb', 'mhm-rentiva'),
                            checked: showBreadcrumb,
                            onChange: function (val) { setAttributes({ showBreadcrumb: val }); }
                        })
                    ),

                    /* PANEL 3: VISIBILITY */
                    el(PanelBody, { title: __('Visibility Controls', 'mhm-rentiva'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('Show Gallery', 'mhm-rentiva'),
                            checked: showGallery,
                            onChange: function (val) { setAttributes({ showGallery: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Price', 'mhm-rentiva'),
                            checked: showPrice,
                            onChange: function (val) { setAttributes({ showPrice: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Rating', 'mhm-rentiva'),
                            checked: showRating,
                            onChange: function (val) { setAttributes({ showRating: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Reviews', 'mhm-rentiva'),
                            checked: showReviews,
                            onChange: function (val) { setAttributes({ showReviews: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Features', 'mhm-rentiva'),
                            checked: showFeatures,
                            onChange: function (val) { setAttributes({ showFeatures: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Technical Specs', 'mhm-rentiva'),
                            checked: showTechnicalSpecs,
                            onChange: function (val) { setAttributes({ showTechnicalSpecs: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Availability', 'mhm-rentiva'),
                            checked: showAvailability,
                            onChange: function (val) { setAttributes({ showAvailability: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Booking Form', 'mhm-rentiva'),
                            checked: showBookingForm,
                            onChange: function (val) { setAttributes({ showBookingForm: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Similar Vehicles', 'mhm-rentiva'),
                            checked: showSimilarVehicles,
                            onChange: function (val) { setAttributes({ showSimilarVehicles: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Share Buttons', 'mhm-rentiva'),
                            checked: showShareButtons,
                            onChange: function (val) { setAttributes({ showShareButtons: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Favorite Button', 'mhm-rentiva'),
                            checked: showFavoriteButton,
                            onChange: function (val) { setAttributes({ showFavoriteButton: val }); }
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: 'mhm-rentiva/vehicle-details',
                    attributes: attributes
                })
            );
        },
        save: function () { return null; }
    });
}(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.serverSideRender,
    window.wp.i18n
));
