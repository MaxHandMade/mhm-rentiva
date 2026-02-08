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

    blocks.registerBlockType('mhm-rentiva/messages', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            var showDate = attributes.showDate;
            var showAuthorAvatar = attributes.showAuthorAvatar;
            var showUnreadBadge = attributes.showUnreadBadge;
            var showThreadPreview = attributes.showThreadPreview;
            var showBookingLink = attributes.showBookingLink;
            var showReplyButton = attributes.showReplyButton;
            var filterStatus = attributes.filterStatus;
            var sortBy = attributes.sortBy;
            var sortOrder = attributes.sortOrder;
            var limitItems = attributes.limitItems;
            var showPagination = attributes.showPagination;
            var className = attributes.className;

            return el('div', blockProps,
                el(InspectorControls, {},
                    /* PANEL 1: GENERAL */
                    el(PanelBody, { title: __('General Settings', 'mhm-rentiva'), initialOpen: true },
                        el(SelectControl, {
                            label: __('Filter Status', 'mhm-rentiva'),
                            value: filterStatus,
                            options: [
                                { label: __('All', 'mhm-rentiva'), value: 'all' },
                                { label: __('Unread', 'mhm-rentiva'), value: 'unread' },
                                { label: __('Read', 'mhm-rentiva'), value: 'read' },
                                { label: __('Archived', 'mhm-rentiva'), value: 'archived' }
                            ],
                            onChange: function (val) { setAttributes({ filterStatus: val }); }
                        }),
                        el(SelectControl, {
                            label: __('Sort By', 'mhm-rentiva'),
                            value: sortBy,
                            options: [
                                { label: __('Date', 'mhm-rentiva'), value: 'date' },
                                { label: __('Unread Status', 'mhm-rentiva'), value: 'unread' },
                            ],
                            onChange: function (val) { setAttributes({ sortBy: val }); }
                        }),
                        el(SelectControl, {
                            label: __('Sort Order', 'mhm-rentiva'),
                            value: sortOrder,
                            options: [
                                { label: __('Descending (Newest First)', 'mhm-rentiva'), value: 'desc' },
                                { label: __('Ascending (Oldest First)', 'mhm-rentiva'), value: 'asc' },
                            ],
                            onChange: function (val) { setAttributes({ sortOrder: val }); }
                        }),
                        el(TextControl, {
                            label: __('Limit Items', 'mhm-rentiva'),
                            value: limitItems,
                            type: 'number',
                            onChange: function (val) { setAttributes({ limitItems: val }); }
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
                            label: __('Show Pagination', 'mhm-rentiva'),
                            checked: showPagination,
                            onChange: function (val) { setAttributes({ showPagination: val }); }
                        })
                    ),

                    /* PANEL 3: VISIBILITY */
                    el(PanelBody, { title: __('Visibility Controls', 'mhm-rentiva'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('Show Date', 'mhm-rentiva'),
                            checked: showDate,
                            onChange: function (val) { setAttributes({ showDate: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Author Avatar', 'mhm-rentiva'),
                            checked: showAuthorAvatar,
                            onChange: function (val) { setAttributes({ showAuthorAvatar: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Unread Badge', 'mhm-rentiva'),
                            checked: showUnreadBadge,
                            onChange: function (val) { setAttributes({ showUnreadBadge: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Thread Preview', 'mhm-rentiva'),
                            checked: showThreadPreview,
                            onChange: function (val) { setAttributes({ showThreadPreview: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Booking Link', 'mhm-rentiva'),
                            checked: showBookingLink,
                            onChange: function (val) { setAttributes({ showBookingLink: val }); }
                        }),
                        el(ToggleControl, {
                            label: __('Show Reply Button', 'mhm-rentiva'),
                            checked: showReplyButton,
                            onChange: function (val) { setAttributes({ showReplyButton: val }); }
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: 'mhm-rentiva/messages',
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
