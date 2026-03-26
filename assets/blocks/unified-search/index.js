
(function (blocks, element, blockEditor, components, serverSideRender, i18n) {
    var el = element.createElement;
    var registerBlockType = blocks.registerBlockType;
    var useBlockProps = blockEditor.useBlockProps;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var SelectControl = components.SelectControl;
    var TextControl = components.TextControl;
    var ExternalLink = components.ExternalLink;
    var ServerSideRender = serverSideRender;
    var __ = i18n.__;

    var Edit = function (props) {
        var attributes = props.attributes;
        var setAttributes = props.setAttributes;
        var blockProps = useBlockProps();

        var service_type = attributes.service_type;
        var default_tab = attributes.default_tab;
        var show_rental_tab = attributes.show_rental_tab;
        var show_transfer_tab = attributes.show_transfer_tab;
        var show_location_select = attributes.show_location_select;
        var show_time_select = attributes.show_time_select;
        var show_date_picker = attributes.show_date_picker;
        var show_dropoff_location = attributes.show_dropoff_location;
        var show_pax = attributes.show_pax;
        var show_luggage = attributes.show_luggage;
        var fieldsRequired = attributes.fieldsRequired;
        var redirect_page = attributes.redirect_page;
        var search_layout = attributes.search_layout;
        var style = attributes.style;
        var className = attributes.className;

        // Helper: Logic to show/hide panels based on service type
        var isTransferOnly = service_type === 'transfer';
        var isRentalOnly = service_type === 'rental';

        // Helper for Ajax URL replacement
        var globalSettings = window.mhmRentivaSearch ? window.mhmRentivaSearch : {};
        var settingsUrl = globalSettings.ajax_url ? globalSettings.ajax_url.replace('admin-ajax.php', 'admin.php?page=mhm_rentiva_settings') : '#';

        return el('div', blockProps,
            el(InspectorControls, {},
                /* PANEL 1: GENERAL */
                el(PanelBody, { title: __('General Settings', 'mhm-rentiva'), initialOpen: true },
                    el(SelectControl, {
                        label: __('Service Mode', 'mhm-rentiva'),
                        value: service_type,
                        options: [
                            { label: __('Both (Rental + Transfer)', 'mhm-rentiva'), value: 'both' },
                            { label: __('Rental Only', 'mhm-rentiva'), value: 'rental' },
                            { label: __('Transfer Only', 'mhm-rentiva'), value: 'transfer' },
                        ],
                        onChange: function (val) { setAttributes({ service_type: val }); },
                        help: __('Controls which search tabs are available.', 'mhm-rentiva')
                    }),
                    el(SelectControl, {
                        label: __('Default Active Tab', 'mhm-rentiva'),
                        value: default_tab,
                        options: [
                            { label: __('Use Global Default', 'mhm-rentiva'), value: 'default' },
                            { label: __('Rental', 'mhm-rentiva'), value: 'rental' },
                            { label: __('Transfer', 'mhm-rentiva'), value: 'transfer' },
                        ],
                        onChange: function (val) { setAttributes({ default_tab: val }); }
                    })
                ),

                /* PANEL 2: BUSINESS LOGIC */
                el(PanelBody, { title: __('Business Logic', 'mhm-rentiva'), initialOpen: false },
                    el('p', { className: 'description' },
                        __('Booking rules (min days, buffers) are inherited from Global Settings.', 'mhm-rentiva')
                    ),
                    el(ExternalLink, {
                        href: settingsUrl
                    }, __('Manage Global Rules', 'mhm-rentiva'))
                ),

                /* PANEL 3: UI & BRANDING */
                el(PanelBody, { title: __('UI & Branding', 'mhm-rentiva'), initialOpen: false },
                    el(SelectControl, {
                        label: __('Layout', 'mhm-rentiva'),
                        value: search_layout,
                        options: [
                            { label: __('Horizontal (Standard)', 'mhm-rentiva'), value: 'horizontal' },
                            { label: __('Vertical (Sidebar)', 'mhm-rentiva'), value: 'vertical' },
                            { label: __('Compact', 'mhm-rentiva'), value: 'compact' },
                        ],
                        onChange: function (val) { setAttributes({ search_layout: val }); }
                    }),
                    el(SelectControl, {
                        label: __('Style Preset', 'mhm-rentiva'),
                        value: style,
                        options: [
                            { label: __('Glassmorphism', 'mhm-rentiva'), value: 'glass' },
                            { label: __('Solid / Flat', 'mhm-rentiva'), value: 'solid' },
                        ],
                        onChange: function (val) { setAttributes({ style: val }); }
                    }),
                    el(TextControl, {
                        label: __('Custom CSS Class', 'mhm-rentiva'),
                        value: className || '',
                        onChange: function (val) { setAttributes({ className: val }); }
                    })
                ),

                /* PANEL 4: PRICING */
                el(PanelBody, { title: __('Pricing & Currency', 'mhm-rentiva'), initialOpen: false },
                    el('p', { className: 'description' },
                        __('Currency settings are managed globally.', 'mhm-rentiva')
                    )
                ),

                /* PANEL 5: FORM UX & VISIBILITY */
                el(PanelBody, { title: __('Form Visibility', 'mhm-rentiva'), initialOpen: false },
                    !isTransferOnly && el(SelectControl, {
                        label: __('Show Rental Tab', 'mhm-rentiva'),
                        value: show_rental_tab,
                        options: [
                            { label: __('Global Default', 'mhm-rentiva'), value: 'default' },
                            { label: __('Show', 'mhm-rentiva'), value: 'true' },
                            { label: __('Hide', 'mhm-rentiva'), value: 'false' },
                        ],
                        onChange: function (val) { setAttributes({ show_rental_tab: val }); }
                    }),
                    !isRentalOnly && el(SelectControl, {
                        label: __('Show Transfer Tab', 'mhm-rentiva'),
                        value: show_transfer_tab,
                        options: [
                            { label: __('Global Default', 'mhm-rentiva'), value: 'default' },
                            { label: __('Show', 'mhm-rentiva'), value: 'true' },
                            { label: __('Hide', 'mhm-rentiva'), value: 'false' },
                        ],
                        onChange: function (val) { setAttributes({ show_transfer_tab: val }); }
                    }),
                    el(SelectControl, {
                        label: __('Location Select', 'mhm-rentiva'),
                        value: show_location_select,
                        options: [
                            { label: __('Global Default', 'mhm-rentiva'), value: 'default' },
                            { label: __('Enabled', 'mhm-rentiva'), value: 'true' },
                            { label: __('Disabled', 'mhm-rentiva'), value: 'false' },
                        ],
                        onChange: function (val) { setAttributes({ show_location_select: val }); }
                    }),
                    el(SelectControl, {
                        label: __('Date Picker', 'mhm-rentiva'),
                        value: show_date_picker,
                        options: [
                            { label: __('Global Default', 'mhm-rentiva'), value: 'default' },
                            { label: __('Enabled', 'mhm-rentiva'), value: 'true' },
                            { label: __('Disabled', 'mhm-rentiva'), value: 'false' },
                        ],
                        onChange: function (val) { setAttributes({ show_date_picker: val }); }
                    }),
                    el(SelectControl, {
                        label: __('Time Select', 'mhm-rentiva'),
                        value: show_time_select,
                        options: [
                            { label: __('Global Default', 'mhm-rentiva'), value: 'default' },
                            { label: __('Enabled', 'mhm-rentiva'), value: 'true' },
                            { label: __('Disabled', 'mhm-rentiva'), value: 'false' },
                        ],
                        onChange: function (val) { setAttributes({ show_time_select: val }); }
                    }),
                    el(SelectControl, {
                        label: __('Dropoff Location', 'mhm-rentiva'),
                        value: show_dropoff_location,
                        options: [
                            { label: __('Global Default', 'mhm-rentiva'), value: 'default' },
                            { label: __('Enabled', 'mhm-rentiva'), value: 'true' },
                            { label: __('Disabled', 'mhm-rentiva'), value: 'false' },
                        ],
                        onChange: function (val) { setAttributes({ show_dropoff_location: val }); }
                    }),
                    el(SelectControl, {
                        label: __('Require Form Fields', 'mhm-rentiva'),
                        value: fieldsRequired,
                        options: [
                            { label: __('Global Default', 'mhm-rentiva'), value: 'default' },
                            { label: __('Required', 'mhm-rentiva'), value: 'true' },
                            { label: __('Optional (Browse All)', 'mhm-rentiva'), value: 'false' },
                        ],
                        onChange: function (val) { setAttributes({ fieldsRequired: val }); },
                        help: __('When optional, users can search without filling any fields to browse all vehicles.', 'mhm-rentiva')
                    }),
                    !isRentalOnly && el(SelectControl, {
                        label: __('Pax Select (Adults/Children)', 'mhm-rentiva'),
                        value: show_pax,
                        options: [
                            { label: __('Global Default', 'mhm-rentiva'), value: 'default' },
                            { label: __('Enabled', 'mhm-rentiva'), value: 'true' },
                            { label: __('Disabled', 'mhm-rentiva'), value: 'false' },
                        ],
                        onChange: function (val) { setAttributes({ show_pax: val }); }
                    }),
                    !isRentalOnly && el(SelectControl, {
                        label: __('Luggage Select', 'mhm-rentiva'),
                        value: show_luggage,
                        options: [
                            { label: __('Global Default', 'mhm-rentiva'), value: 'default' },
                            { label: __('Enabled', 'mhm-rentiva'), value: 'true' },
                            { label: __('Disabled', 'mhm-rentiva'), value: 'false' },
                        ],
                        onChange: function (val) { setAttributes({ show_luggage: val }); }
                    })
                ),

                /* PANEL 6: ADVANCED */
                el(PanelBody, { title: __('Advanced Configuration', 'mhm-rentiva'), initialOpen: false },
                    el(SelectControl, {
                        label: __('Redirect Page ID', 'mhm-rentiva'),
                        value: redirect_page,
                        options: [
                            { label: __('Global Default', 'mhm-rentiva'), value: 'default' },
                            (redirect_page && redirect_page !== 'default' ? { label: 'Custom ID: ' + redirect_page, value: redirect_page } : null)
                        ].filter(Boolean),
                        onChange: function (val) { setAttributes({ redirect_page: val }); },
                        help: __('Leave as default to use global results page.', 'mhm-rentiva')
                    }),
                    el(TextControl, {
                        label: __('Override Redirect Page ID', 'mhm-rentiva'),
                        value: redirect_page === 'default' ? '' : redirect_page,
                        onChange: function (val) { setAttributes({ redirect_page: val || 'default' }); },
                        help: __('Enter a Page ID to override global setting. Clear to reset to Default.', 'mhm-rentiva')
                    })
                )
            ),
            el(ServerSideRender, {
                block: 'mhm-rentiva/unified-search',
                attributes: attributes
            })
        );
    };

    registerBlockType('mhm-rentiva/unified-search', {
        edit: Edit,
        save: function () { return null; }
    });

})(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.serverSideRender,
    window.wp.i18n
);
