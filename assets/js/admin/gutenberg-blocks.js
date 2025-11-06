/**
 * Gutenberg Blocks JavaScript
 * 
 * MHM Rentiva Gutenberg block'ları için JavaScript
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
        title: __('Araç Kartı', 'mhm-rentiva'),
        description: __('Tekil araç kartını gösterir', 'mhm-rentiva'),
        icon: 'car',
        category: 'mhm-rentiva',
        keywords: [
            __('araç', 'mhm-rentiva'),
            __('kart', 'mhm-rentiva'),
            __('kiralama', 'mhm-rentiva'),
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
                default: __('Rezervasyon Yap', 'mhm-rentiva'),
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

            // Vehicle options (bu gerçek uygulamada AJAX ile yüklenecek)
            const vehicleOptions = window.mhmRentivaGutenberg?.vehicleOptions || [
                { value: 0, label: __('Araç Seçin', 'mhm-rentiva') },
            ];

            const layoutOptions = [
                { value: 'default', label: __('Varsayılan', 'mhm-rentiva') },
                { value: 'compact', label: __('Kompakt', 'mhm-rentiva') },
                { value: 'grid', label: __('Izgara', 'mhm-rentiva') },
                { value: 'featured', label: __('Öne Çıkan', 'mhm-rentiva') },
            ];

            const ratingPositionOptions = [
                { value: 'overlay', label: __('Resim Üzeri', 'mhm-rentiva') },
                { value: 'below_image', label: __('Resim Altı', 'mhm-rentiva') },
                { value: 'footer', label: __('Alt Kısım', 'mhm-rentiva') },
            ];

            const buttonStyleOptions = [
                { value: 'primary', label: __('Birincil', 'mhm-rentiva') },
                { value: 'secondary', label: __('İkincil', 'mhm-rentiva') },
                { value: 'outline', label: __('Çerçeveli', 'mhm-rentiva') },
            ];

            const priceFormatOptions = [
                { value: 'daily', label: __('Günlük', 'mhm-rentiva') },
                { value: 'hourly', label: __('Saatlik', 'mhm-rentiva') },
                { value: 'weekly', label: __('Haftalık', 'mhm-rentiva') },
                { value: 'monthly', label: __('Aylık', 'mhm-rentiva') },
            ];

            return createElement('div', blockProps, [
                createElement(InspectorControls, { key: 'inspector' }, [
                    createElement(PanelBody, {
                        title: __('İçerik Ayarları', 'mhm-rentiva'),
                        initialOpen: true,
                    }, [
                        createElement(SelectControl, {
                            label: __('Araç Seçin', 'mhm-rentiva'),
                            value: attributes.vehicleId,
                            options: vehicleOptions,
                            onChange: (value) => setAttributes({ vehicleId: parseInt(value) }),
                        }),
                        createElement(SelectControl, {
                            label: __('Düzen', 'mhm-rentiva'),
                            value: attributes.layout,
                            options: layoutOptions,
                            onChange: (value) => setAttributes({ layout: value }),
                        }),
                    ]),
                    createElement(PanelBody, {
                        title: __('Gösterim Seçenekleri', 'mhm-rentiva'),
                        initialOpen: false,
                    }, [
                        createElement(ToggleControl, {
                            label: __('Görsel Göster', 'mhm-rentiva'),
                            checked: attributes.showImage,
                            onChange: (value) => setAttributes({ showImage: value }),
                        }),
                        createElement(ToggleControl, {
                            label: __('Başlık Göster', 'mhm-rentiva'),
                            checked: attributes.showTitle,
                            onChange: (value) => setAttributes({ showTitle: value }),
                        }),
                        createElement(ToggleControl, {
                            label: __('Kategori Göster', 'mhm-rentiva'),
                            checked: attributes.showCategory,
                            onChange: (value) => setAttributes({ showCategory: value }),
                        }),
                        createElement(ToggleControl, {
                            label: __('Fiyat Göster', 'mhm-rentiva'),
                            checked: attributes.showPrice,
                            onChange: (value) => setAttributes({ showPrice: value }),
                        }),
                        createElement(SelectControl, {
                            label: __('Fiyat Formatı', 'mhm-rentiva'),
                            value: attributes.priceFormat,
                            options: priceFormatOptions,
                            onChange: (value) => setAttributes({ priceFormat: value }),
                            disabled: !attributes.showPrice,
                        }),
                        createElement(ToggleControl, {
                            label: __('Özellikler Göster', 'mhm-rentiva'),
                            checked: attributes.showFeatures,
                            onChange: (value) => setAttributes({ showFeatures: value }),
                        }),
                    ]),
                    createElement(PanelBody, {
                        title: __('Değerlendirme', 'mhm-rentiva'),
                        initialOpen: false,
                    }, [
                        createElement(ToggleControl, {
                            label: __('Yıldız Değerlendirmesi Göster', 'mhm-rentiva'),
                            checked: attributes.showRating,
                            onChange: (value) => setAttributes({ showRating: value }),
                        }),
                        createElement(SelectControl, {
                            label: __('Yıldız Konumu', 'mhm-rentiva'),
                            value: attributes.ratingPosition,
                            options: ratingPositionOptions,
                            onChange: (value) => setAttributes({ ratingPosition: value }),
                            disabled: !attributes.showRating,
                        }),
                        createElement(ToggleControl, {
                            label: __('Değerlendirme Sayısını Göster', 'mhm-rentiva'),
                            checked: attributes.showRatingCount,
                            onChange: (value) => setAttributes({ showRatingCount: value }),
                            disabled: !attributes.showRating,
                        }),
                    ]),
                    createElement(PanelBody, {
                        title: __('Buton ve Etkileşim', 'mhm-rentiva'),
                        initialOpen: false,
                    }, [
                        createElement(ToggleControl, {
                            label: __('Rezervasyon Butonu Göster', 'mhm-rentiva'),
                            checked: attributes.showBookingBtn,
                            onChange: (value) => setAttributes({ showBookingBtn: value }),
                        }),
                        createElement(components.TextControl, {
                            label: __('Buton Metni', 'mhm-rentiva'),
                            value: attributes.buttonText,
                            onChange: (value) => setAttributes({ buttonText: value }),
                            disabled: !attributes.showBookingBtn,
                        }),
                        createElement(SelectControl, {
                            label: __('Buton Stili', 'mhm-rentiva'),
                            value: attributes.buttonStyle,
                            options: buttonStyleOptions,
                            onChange: (value) => setAttributes({ buttonStyle: value }),
                            disabled: !attributes.showBookingBtn,
                        }),
                        createElement(ToggleControl, {
                            label: __('Favori Butonu Göster', 'mhm-rentiva'),
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
                    }, __('Araç Kartı', 'mhm-rentiva')),
                    createElement('div', {
                        style: {
                            fontSize: '14px',
                            color: '#666',
                        },
                    }, attributes.vehicleId
                        ? __('Seçilen Araç ID: ' + attributes.vehicleId, 'mhm-rentiva')
                        : __('Lütfen bir araç seçin', 'mhm-rentiva')
                    ),
                    createElement('div', {
                        style: {
                            fontSize: '12px',
                            color: '#999',
                            marginTop: '10px',
                        },
                    }, __('Düzen: ' + attributes.layout, 'mhm-rentiva')),
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
