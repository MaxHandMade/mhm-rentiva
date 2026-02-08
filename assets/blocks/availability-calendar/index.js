(function (blocks, element, blockEditor, components, serverSideRender, i18n) {
    var el = element.createElement;
    var registerBlockType = blocks.registerBlockType;
    var useBlockProps = blockEditor.useBlockProps;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var ToggleControl = components.ToggleControl;
    var TextControl = components.TextControl;
    var SelectControl = components.SelectControl;
    var ServerSideRender = serverSideRender;
    var __ = i18n.__;

    blocks.registerBlockType('mhm-rentiva/availability-calendar', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            var vehicleId = attributes.vehicleId;
            var showVehicleSelector = attributes.showVehicleSelector;
            var showLegend = attributes.showLegend;
            var showPricing = attributes.showPricing;
            var showBookingLinks = attributes.showBookingLinks;
            var showMonthNavigation = attributes.showMonthNavigation;
            var showTodayButton = attributes.showTodayButton;
            var showWeekNumbers = attributes.showWeekNumbers;
            var monthsToShow = attributes.monthsToShow;
            var startWeekOn = attributes.startWeekOn;
            var calendarHeight = attributes.calendarHeight;
            var className = attributes.className;

            return el('div', blockProps,
                el(InspectorControls, {},
                    /* PANEL 1: GENERAL */
                    el(PanelBody, { title: __('General Settings', 'mhm-rentiva'), initialOpen: true },
                        el(TextControl, {
                            label: __('Vehicle ID (Optional)', 'mhm-rentiva'),
                            value: vehicleId,
                            onChange: function (val) { setAttributes({ vehicleId: val }); },
                            help: __('Leave empty to auto-detect on Vehicle pages.', 'mhm-rentiva')
                        }),
                        el(SelectControl, {
                            label: __('Months to Show', 'mhm-rentiva'),
                            value: monthsToShow,
                            options: [
                                { label: '1', value: '1' },
                                { label: '2', value: '2' },
                                { label: '3', value: '3' },
                                { label: '4', value: '4' },
                                { label: '6', value: '6' },
                                { label: '12', value: '12' },
                            ],
                            onChange: function (val) { setAttributes({ monthsToShow: val }); }
                        }),
                        el(SelectControl, {
                            label: __('Start Week On', 'mhm-rentiva'),
                            value: startWeekOn,
                            options: [
                                { label: __('Monday', 'mhm-rentiva'), value: '1' },
                                { label: __('Sunday', 'mhm-rentiva'), value: '0' },
                            ],
                            onChange: function (val) { setAttributes({ startWeekOn: val }); }
                        })
                    ),

                    /* PANEL 2: LAYOUT */
                    el(PanelBody, { title: __('Layout & Style', 'mhm-rentiva'), initialOpen: false },
                        el(TextControl, {
                            label: __('Calendar Height', 'mhm-rentiva'),
                            value: calendarHeight,
                            onChange: function (val) { setAttributes({ calendarHeight: val }); },
                            help: __('e.g., "auto", "500px"', 'mhm-rentiva')
                        }),
                        el(TextControl, {
                            label: __('Custom CSS Class', 'mhm-rentiva'),
                            value: className,
                            onChange: function (val) { setAttributes({ className: val }); }
                        })
                    ),

                    /* PANEL 3: VISIBILITY */
                    el(PanelBody, { title: __('Visibility Controls', 'mhm-rentiva'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('Show Vehicle Selector', 'mhm-rentiva'),
                            checked: showVehicleSelector,
                            onChange: function (val) { setAttributes({ showVehicleSelector: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Legend', 'mhm-rentiva'),
                            checked: showLegend,
                            onChange: function (val) { setAttributes({ showLegend: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Pricing', 'mhm-rentiva'),
                            checked: showPricing,
                            onChange: function (val) { setAttributes({ showPricing: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Booking Links', 'mhm-rentiva'),
                            checked: showBookingLinks,
                            onChange: function (val) { setAttributes({ showBookingLinks: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Month Navigation', 'mhm-rentiva'),
                            checked: showMonthNavigation,
                            onChange: function (val) { setAttributes({ showMonthNavigation: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Today Button', 'mhm-rentiva'),
                            checked: showTodayButton,
                            onChange: function (val) { setAttributes({ showTodayButton: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Week Numbers', 'mhm-rentiva'),
                            checked: showWeekNumbers,
                            onChange: function (val) { setAttributes({ showWeekNumbers: val }); }
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: 'mhm-rentiva/availability-calendar',
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
