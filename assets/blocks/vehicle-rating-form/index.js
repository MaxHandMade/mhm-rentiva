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

    blocks.registerBlockType('mhm-rentiva/vehicle-rating-form', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            var vehicleId = attributes.vehicleId;
            var showStarRating = attributes.showStarRating;
            var showTextReview = attributes.showTextReview;
            var showCategoryRatings = attributes.showCategoryRatings;
            var showPhotoUpload = attributes.showPhotoUpload;
            var showVehiclePreview = attributes.showVehiclePreview;
            var showRecommendToggle = attributes.showRecommendToggle;
            var requireLogin = attributes.requireLogin;
            var requireBooking = attributes.requireBooking;
            var maxPhotos = attributes.maxPhotos;
            var minReviewLength = attributes.minReviewLength;
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
                        el(ToggleControl, {
                            label: __('Require Login', 'mhm-rentiva'),
                            checked: requireLogin,
                            onChange: function (val) { setAttributes({ requireLogin: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Require Prior Booking', 'mhm-rentiva'),
                            checked: requireBooking,
                            onChange: function (val) { setAttributes({ requireBooking: val }); }
                        })
                    ),

                    /* PANEL 2: FORM CONFIGURATION */
                    el(PanelBody, { title: __('Form Configuration', 'mhm-rentiva'), initialOpen: false },
                        el(TextControl, {
                            label: __('Minimum Review Characters', 'mhm-rentiva'),
                            value: minReviewLength,
                            type: 'number',
                            onChange: function (val) { setAttributes({ minReviewLength: val }); }
                        }),
                        el(TextControl, {
                            label: __('Max Photos to Upload', 'mhm-rentiva'),
                            value: maxPhotos,
                            type: 'number',
                            onChange: function (val) { setAttributes({ maxPhotos: val }); }
                        })
                    ),

                    /* PANEL 3: LAYOUT */
                    el(PanelBody, { title: __('Layout', 'mhm-rentiva'), initialOpen: false },
                        el(TextControl, {
                            label: __('Custom CSS Class', 'mhm-rentiva'),
                            value: className,
                            onChange: function (val) { setAttributes({ className: val }); }
                        })
                    ),

                    /* PANEL 4: VISIBILITY */
                    el(PanelBody, { title: __('Visibility Controls', 'mhm-rentiva'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('Show Vehicle Preview', 'mhm-rentiva'),
                            checked: showVehiclePreview,
                            onChange: function (val) { setAttributes({ showVehiclePreview: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Star Rating', 'mhm-rentiva'),
                            checked: showStarRating,
                            onChange: function (val) { setAttributes({ showStarRating: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Text Review', 'mhm-rentiva'),
                            checked: showTextReview,
                            onChange: function (val) { setAttributes({ showTextReview: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Category Ratings', 'mhm-rentiva'),
                            checked: showCategoryRatings,
                            onChange: function (val) { setAttributes({ showCategoryRatings: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Photo Upload', 'mhm-rentiva'),
                            checked: showPhotoUpload,
                            onChange: function (val) { setAttributes({ showPhotoUpload: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Recommend Toggle', 'mhm-rentiva'),
                            checked: showRecommendToggle,
                            onChange: function (val) { setAttributes({ showRecommendToggle: val }); }
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: 'mhm-rentiva/vehicle-rating-form',
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
