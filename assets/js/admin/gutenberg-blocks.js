/**
 * Gutenberg Blocks JavaScript
 * 
 * JavaScript powering MHM Rentiva Gutenberg blocks.
 * 
 * @package MHMRentiva
 * @since 3.0.1
 */

(function (blocks, element, components, i18n, blockEditor) {
    'use strict';

    const { registerBlockType } = blocks;
    const { createElement } = element;
    const { SelectControl, ToggleControl, PanelBody } = components;
    const { __ } = i18n;
    const { InspectorControls, useBlockProps } = blockEditor;

    // Vehicle Card Block
    registerBlockType('mhm-rentiva/vehicle-card', {
        title: __('Vehicle Card', 'mhm-rentiva'),
        description: __('Displays a single vehicle card.', 'mhm-rentiva'),
        icon: 'car',
        category: 'mhm-rentiva',
        keywords: [
            __('vehicle', 'mhm-rentiva'),
            __('card', 'mhm-rentiva'),
            __('rental', 'mhm-rentiva'),
        ],
        supports: {
            align: ['wide', 'full'],
            anchor: true,
            customClassName: true,
            html: false,
        },
        attributes: {
            vehicleId: {
                type: 'number',
                default: 0,
            },
            layout: {
                type: 'string',
                default: 'default',
            },
            showImage: {
                type: 'boolean',
                default: true,
            },
            showTitle: {
                type: 'boolean',
                default: true,
            },
            showCategory: {
                type: 'boolean',
                default: true,
            },
            showPrice: {
                type: 'boolean',
                default: true,
            },
            showFeatures: {
                type: 'boolean',
                default: true,
            },
            maxFeatures: {
                type: 'number',
                default: 3,
            },
            showRating: {
                type: 'boolean',
                default: true,
            },
            ratingPosition: {
                type: 'string',
                default: 'overlay',
            },
            showRatingCount: {
                type: 'boolean',
                default: true,
            },
            customRating: {
                type: 'number',
                default: 0,
            },
            showBookingBtn: {
                type: 'boolean',
                default: true,
            },
            buttonText: {
                type: 'string',
                default: __('Make a reservation', 'mhm-rentiva'),
            },
            buttonStyle: {
                type: 'string',
                default: 'primary',
            },
            showFavoriteBtn: {
                type: 'boolean',
                default: true,
            },
            priceFormat: {
                type: 'string',
                default: 'daily',
            },
        },
        edit: function (props) {
            const { attributes, setAttributes } = props;
            const blockProps = useBlockProps();

            // Vehicle options (in real application, this will be loaded via AJAX)
            const vehicleOptions = window.mhmRentivaGutenberg?.vehicleOptions || [
                { value: 0, label: __('Select vehicle', 'mhm-rentiva') },
            ];

            const layoutOptions = [
                { value: 'default', label: __('Default', 'mhm-rentiva') },
                { value: 'compact', label: __('Compact', 'mhm-rentiva') },
                { value: 'grid', label: __('Grid', 'mhm-rentiva') },
                { value: 'featured', label: __('Featured', 'mhm-rentiva') },
            ];

            const ratingPositionOptions = [
                { value: 'overlay', label: __('On image', 'mhm-rentiva') },
                { value: 'below_image', label: __('Below image', 'mhm-rentiva') },
                { value: 'footer', label: __('Footer', 'mhm-rentiva') },
            ];

            const buttonStyleOptions = [
                { value: 'primary', label: __('Primary', 'mhm-rentiva') },
                { value: 'secondary', label: __('Secondary', 'mhm-rentiva') },
                { value: 'outline', label: __('Outline', 'mhm-rentiva') },
            ];

            const priceFormatOptions = [
                { value: 'daily', label: __('Daily', 'mhm-rentiva') },
                { value: 'hourly', label: __('Hourly', 'mhm-rentiva') },
                { value: 'weekly', label: __('Weekly', 'mhm-rentiva') },
                { value: 'monthly', label: __('Monthly', 'mhm-rentiva') },
            ];

            return createElement('div', blockProps, [
                createElement(InspectorControls, { key: 'inspector' }, [
                    createElement(PanelBody, {
                        title: __('Content Settings', 'mhm-rentiva'),
                        initialOpen: true,
                    }, [
                        createElement(SelectControl, {
                            label: __('Select vehicle', 'mhm-rentiva'),
                            value: attributes.vehicleId,
                            options: vehicleOptions,
                            onChange: (value) => setAttributes({ vehicleId: parseInt(value) }),
                        }),
                        createElement(SelectControl, {
                            label: __('Layout', 'mhm-rentiva'),
                            value: attributes.layout,
                            options: layoutOptions,
                            onChange: (value) => setAttributes({ layout: value }),
                        }),
                    ]),
                    createElement(PanelBody, {
                        title: __('Display Options', 'mhm-rentiva'),
                        initialOpen: false,
                    }, [
                        createElement(ToggleControl, {
                            label: __('Show image', 'mhm-rentiva'),
                            checked: attributes.showImage,
                            onChange: (value) => setAttributes({ showImage: value }),
                        }),
                        createElement(ToggleControl, {
                            label: __('Show title', 'mhm-rentiva'),
                            checked: attributes.showTitle,
                            onChange: (value) => setAttributes({ showTitle: value }),
                        }),
                        createElement(ToggleControl, {
                            label: __('Show category', 'mhm-rentiva'),
                            checked: attributes.showCategory,
                            onChange: (value) => setAttributes({ showCategory: value }),
                        }),
                        createElement(ToggleControl, {
                            label: __('Show price', 'mhm-rentiva'),
                            checked: attributes.showPrice,
                            onChange: (value) => setAttributes({ showPrice: value }),
                        }),
                        createElement(SelectControl, {
                            label: __('Price format', 'mhm-rentiva'),
                            value: attributes.priceFormat,
                            options: priceFormatOptions,
                            onChange: (value) => setAttributes({ priceFormat: value }),
                            disabled: !attributes.showPrice,
                        }),
                        createElement(ToggleControl, {
                            label: __('Show features', 'mhm-rentiva'),
                            checked: attributes.showFeatures,
                            onChange: (value) => setAttributes({ showFeatures: value }),
                        }),
                    ]),
                    createElement(PanelBody, {
                        title: __('Ratings', 'mhm-rentiva'),
                        initialOpen: false,
                    }, [
                        createElement(ToggleControl, {
                            label: __('Show star rating', 'mhm-rentiva'),
                            checked: attributes.showRating,
                            onChange: (value) => setAttributes({ showRating: value }),
                        }),
                        createElement(SelectControl, {
                            label: __('Star position', 'mhm-rentiva'),
                            value: attributes.ratingPosition,
                            options: ratingPositionOptions,
                            onChange: (value) => setAttributes({ ratingPosition: value }),
                            disabled: !attributes.showRating,
                        }),
                        createElement(ToggleControl, {
                            label: __('Show rating count', 'mhm-rentiva'),
                            checked: attributes.showRatingCount,
                            onChange: (value) => setAttributes({ showRatingCount: value }),
                            disabled: !attributes.showRating,
                        }),
                    ]),
                    createElement(PanelBody, {
                        title: __('Buttons & Interaction', 'mhm-rentiva'),
                        initialOpen: false,
                    }, [
                        createElement(ToggleControl, {
                            label: __('Show booking button', 'mhm-rentiva'),
                            checked: attributes.showBookingBtn,
                            onChange: (value) => setAttributes({ showBookingBtn: value }),
                        }),
                        createElement(components.TextControl, {
                            label: __('Button text', 'mhm-rentiva'),
                            value: attributes.buttonText,
                            onChange: (value) => setAttributes({ buttonText: value }),
                            disabled: !attributes.showBookingBtn,
                        }),
                        createElement(SelectControl, {
                            label: __('Button style', 'mhm-rentiva'),
                            value: attributes.buttonStyle,
                            options: buttonStyleOptions,
                            onChange: (value) => setAttributes({ buttonStyle: value }),
                            disabled: !attributes.showBookingBtn,
                        }),
                        createElement(ToggleControl, {
                            label: __('Show favorite button', 'mhm-rentiva'),
                            checked: attributes.showFavoriteBtn,
                            onChange: (value) => setAttributes({ showFavoriteBtn: value }),
                        }),
                    ]),
                ]),
                createElement('div', {
                    className: 'mhm-rentiva-block-preview',
                    style: {
                        padding: '20px',
                        border: '2px dashed #ddd',
                        borderRadius: '8px',
                        textAlign: 'center',
                        backgroundColor: '#f9f9f9',
                    },
                }, [
                    createElement('div', {
                        style: {
                            fontSize: '18px',
                            fontWeight: 'bold',
                            marginBottom: '10px',
                            color: '#333',
                        },
                    }, __('Vehicle Card', 'mhm-rentiva')),
                    createElement('div', {
                        style: {
                            fontSize: '14px',
                            color: '#666',
                        },
                    }, attributes.vehicleId
                        ? __('Selected vehicle ID: ' + attributes.vehicleId, 'mhm-rentiva')
                        : __('Please select a vehicle', 'mhm-rentiva')
                    ),
                    createElement('div', {
                        style: {
                            fontSize: '12px',
                            color: '#999',
                            marginTop: '10px',
                        },
                    }, __('Layout: ', 'mhm-rentiva') + attributes.layout),
                ]),
            ]);
        },
        save: function () {
            // Server-side rendering kullanıyoruz
            return null;
        },
    });

})(
    window.wp.blocks,
    window.wp.element,
    window.wp.components,
    window.wp.i18n,
    window.wp.blockEditor
);
